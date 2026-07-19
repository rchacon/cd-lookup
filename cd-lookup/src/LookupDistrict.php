<?php

const URL = 'https://www.govtrack.us/';
const CENSUS_GEOCODER_ENDPOINT = 'https://geocoding.geo.census.gov/geocoder/geographies/onelineaddress';

/** govtrack.us omits the district segment entirely for at-large ("0") districts, e.g. /congress/members/WY. */
if (!function_exists('district_page_url')) {
    function district_page_url(string $state, string $district): string
    {
        return $district === '0'
            ? URL . "congress/members/{$state}"
            : URL . "congress/members/{$state}/{$district}";
    }
}

if (!function_exists('fetch_html')) {
    function fetch_html(string $url): string
    {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        $html = curl_exec($ch);
        $error = curl_error($ch);
        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

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
 * pattern instead of a hardcoded Congress number that will go stale.
 */
if (!function_exists('extract_congressional_district')) {
    function extract_congressional_district(array $geographies): ?string
    {
        foreach ($geographies as $layer_name => $entries) {
            if (!str_contains($layer_name, 'Congressional Districts') || empty($entries[0])) {
                continue;
            }

            foreach ($entries[0] as $field => $value) {
                if (preg_match('/^CD\d+$/', $field)) {
                    return ltrim((string) $value, '0') ?: '0';
                }
            }
        }

        return null;
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

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        $response = curl_exec($ch);
        $error = curl_error($ch);
        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($response === false) {
            throw new RuntimeException("Failed to reach the Census geocoder for district lookup: {$error}");
        }
        if ($status < 200 || $status >= 300) {
            throw new RuntimeException("Census geocoder returned HTTP {$status} while looking up district");
        }

        $data = json_decode($response, true);
        $match = $data['result']['addressMatches'][0] ?? null;

        if ($match === null) {
            throw new RuntimeException("Census geocoder found no address match for \"{$address}\"");
        }

        $state = $match['addressComponents']['state'] ?? null;
        $district = extract_congressional_district($match['geographies'] ?? []);

        if ($state === null || $district === null) {
            throw new RuntimeException('Census geocoder returned an unexpected response while looking up district');
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
