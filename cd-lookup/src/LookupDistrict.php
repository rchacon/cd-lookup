<?php

const URL = 'https://www.govtrack.us/';
const CENSUS_GEOCODER_ENDPOINT = 'https://geocoding.geo.census.gov/geocoder/geographies/onelineaddress';

/** A problem with the address itself (no match or too ambiguous to resolve) rather than a geocoder/network failure. */
if (!class_exists('InvalidAddressException')) {
    class InvalidAddressException extends RuntimeException {}
}

/** govtrack.us omits the district segment entirely for at-large ("0") districts, e.g. /congress/members/WY. */
if (!function_exists('district_page_url')) {
    function district_page_url(string $state, string $district): string
    {
        return $district === '0'
            ? URL . "congress/members/{$state}"
            : URL . "congress/members/{$state}/{$district}";
    }
}

/** Issue a GET request and return its body, error string, and HTTP status, so callers can compose their own error messages. */
if (!function_exists('curl_get')) {
    function curl_get(string $url): array
    {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        $body = curl_exec($ch);
        $error = curl_error($ch);
        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        return ['body' => $body, 'error' => $error, 'status' => $status];
    }
}

if (!function_exists('fetch_html')) {
    function fetch_html(string $url): string
    {
        ['body' => $html, 'error' => $error, 'status' => $status] = curl_get($url);

        if ($html === false) {
            throw new RuntimeException("Failed to reach govtrack.us for district page: {$error}");
        }
        if ($status < 200 || $status >= 300) {
            throw new RuntimeException("govtrack.us returned HTTP {$status} while fetching district page");
        }

        return $html;
    }
}

/**
 * Find the congressional district number in a Census geocoder `geographies`
 * object. Both the layer name and its district field embed the Congress
 * number (e.g. "119th Congressional Districts" / "CD119"), so match by
 * pattern instead of a hardcoded Congress number that will go stale — but
 * require the field name to match the *same* layer's Congress number,
 * rather than taking the first CD* field found, so a stray/legacy layer
 * can't silently supply the wrong district. If multiple qualifying layers
 * disagree on the district, that's an unresolvable ambiguity, not a guess.
 * A non-numeric CD value is also treated as unresolvable rather than cast
 * to 0, so a malformed response can't masquerade as an at-large district.
 */
if (!function_exists('extract_congressional_district')) {
    function extract_congressional_district(array $geographies): ?string
    {
        $district = null;

        foreach ($geographies as $layer_name => $entries) {
            if (!str_contains($layer_name, 'Congressional Districts') || empty($entries[0])) {
                continue;
            }
            if (!preg_match('/^(\d+)/', $layer_name, $congress)) {
                continue;
            }

            $field = "CD{$congress[1]}";
            if (!array_key_exists($field, $entries[0])) {
                continue;
            }
            if (!is_numeric($entries[0][$field])) {
                return null;
            }

            $found = (string) (int) $entries[0][$field];

            if ($district !== null && $district !== $found) {
                return null;
            }

            $district = $found;
        }

        return $district;
    }
}

/** Return the congressional district state and number as an array for the given address. */
if (!function_exists('get_district')) {
    function get_district(string $address): array
    {
        $url = CENSUS_GEOCODER_ENDPOINT . '?' . http_build_query([
            'address'   => $address,
            'benchmark' => 'Public_AR_Current',
            'vintage'   => 'Current_Current',
            'format'    => 'json',
        ]);

        ['body' => $response, 'error' => $error, 'status' => $status] = curl_get($url);

        if ($response === false) {
            throw new RuntimeException("Failed to reach the Census geocoder for district lookup: {$error}");
        }
        if ($status < 200 || $status >= 300) {
            throw new RuntimeException("Census geocoder returned HTTP {$status} while looking up district");
        }

        $data = json_decode($response, true);

        if (!is_array($data) || !isset($data['result']['addressMatches']) || !is_array($data['result']['addressMatches'])) {
            throw new RuntimeException('Census geocoder returned an unexpected response while looking up district');
        }

        $matches = $data['result']['addressMatches'];

        if (count($matches) === 0) {
            throw new InvalidAddressException("Census geocoder found no address match for \"{$address}\"");
        }
        if (count($matches) > 1) {
            throw new InvalidAddressException("Census geocoder found multiple possible matches for \"{$address}\"; please provide a more specific address");
        }

        $match = $matches[0];
        $state = $match['addressComponents']['state'] ?? null;
        $geographies = $match['geographies'] ?? null;
        $district = extract_congressional_district(is_array($geographies) ? $geographies : []);

        if ($state === null) {
            throw new RuntimeException('Census geocoder response was missing addressComponents.state while looking up district');
        }
        if ($district === null) {
            throw new RuntimeException('Census geocoder response was missing a Congressional Districts geography while looking up district');
        }

        return [$state, $district];
    }
}

/**
 * Parse a govtrack.us district page and return senators and representatives.
 *
 * Returns an array with keys 'senators' and 'representatives', each a list of
 * arrays with keys: full_name, role, party, phone, website, profile_url, photo_url.
 * photo_url is '' when govtrack has no headshot on file.
 */
if (!function_exists('parse_reps')) {
    function parse_reps(string $html): array
    {
        $dom = new DOMDocument();
        @$dom->loadHTML($html);
        $xpath = new DOMXPath($dom);

        $senators = [];
        $representatives = [];

        $rows = $xpath->query('//div[contains(@class,"row") and contains(@style,"margin-bottom: 1.5em")]');
        foreach ($rows as $row) {
            $info_divs = $xpath->query('.//div[contains(@class,"col-sm-9")]', $row);
            if ($info_divs->length === 0) {
                continue;
            }
            $info_div = $info_divs->item(0);

            $name_tags = $xpath->query('.//a[contains(@style,"font-weight: bold")]', $info_div);
            if ($name_tags->length === 0) {
                continue;
            }
            $name_tag = $name_tags->item(0);
            $full_name = trim($name_tag->textContent);
            $profile_url = $name_tag->getAttribute('href');

            $photo_tags = $xpath->query('.//div[contains(@class,"col-sm-3")]//img', $row);
            $photo_url = $photo_tags->length > 0 ? $photo_tags->item(0)->getAttribute('src') : '';

            $child_divs = $xpath->query('div', $info_div);
            $role = $child_divs->length > 1 ? trim($child_divs->item(1)->textContent) : '';

            $party_divs = $xpath->query('.//div[contains(@style,"margin-bottom: .45em")]', $info_div);
            $party = $party_divs->length > 0 ? trim($party_divs->item(0)->textContent) : '';

            $phone_tags = $xpath->query('.//a[starts-with(@href,"tel:")]', $info_div);
            $phone = $phone_tags->length > 0 ? trim($phone_tags->item(0)->textContent) : '';

            $website = '';
            $spanbullets = $xpath->query('.//div[contains(@class,"spanbullets")]', $info_div);
            if ($spanbullets->length > 0) {
                $website_tags = $xpath->query('.//a[not(starts-with(@href,"tel:"))]', $spanbullets->item(0));
                if ($website_tags->length > 0) {
                    $website = $website_tags->item(0)->getAttribute('href');
                }
            }

            $person = [
                'full_name'   => $full_name,
                'role'        => $role,
                'party'       => $party,
                'phone'       => $phone,
                'website'     => $website,
                'profile_url' => $profile_url,
                'photo_url'   => $photo_url,
            ];

            if (str_contains($role, 'Senator')) {
                $senators[] = $person;
            } else {
                $representatives[] = $person;
            }
        }

        return [
            'senators'        => $senators,
            'representatives' => $representatives,
        ];
    }
}

/** Look up and display congressional representatives for the given street address. */
if (!function_exists('main')) {
    function main(string $address): void
    {
        [$state, $district] = get_district($address);
        $html = fetch_html(district_page_url($state, $district));
        $reps = parse_reps($html);
        print_r($reps);
    }
}

if (PHP_SAPI === 'cli' && isset($_SERVER['argv']) && realpath($_SERVER['argv'][0]) === __FILE__ && isset($_SERVER['argv'][1])) {
    main($_SERVER['argv'][1]);
}
