<?php

const URL = 'https://www.govtrack.us/';
const URL_FOR_TOKEN = URL . '_twostream/user-head?path=/';
const DISTRICT_ENDPOINT = URL . 'congress/members/lookup-district.json';

if (!function_exists('fetch_html')) {
    function fetch_html(string $url): string
    {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $html = curl_exec($ch);
        curl_close($ch);
        return $html;
    }
}

/**
 * Fetch a CSRF token from govtrack.us required for authenticated POST requests.
 *
 * govtrack.us only sets its session cookie on the first hit; the csrftoken
 * cookie itself doesn't appear until a subsequent request comes back with
 * that session cookie. So a single call can legitimately come back without
 * a csrftoken yet — retry with the same cookie jar (read + write) so the
 * session cookie from the first attempt is sent on the second.
 */
if (!function_exists('get_token')) {
    function get_token(int $max_attempts = 2): string
    {
        $cookie_jar = '/tmp/govtrack_cookies.txt';
        $last_error = '';

        for ($attempt = 1; $attempt <= $max_attempts; $attempt++) {
            $ch = curl_init(URL_FOR_TOKEN);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 10);
            curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (compatible; cd-lookup-plugin)');
            curl_setopt($ch, CURLOPT_COOKIEJAR, $cookie_jar);
            curl_setopt($ch, CURLOPT_COOKIEFILE, $cookie_jar);
            $result = curl_exec($ch);
            $error = curl_error($ch);
            $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($result === false) {
                $last_error = "Failed to reach govtrack.us for CSRF token: {$error}";
                continue;
            }
            if ($status < 200 || $status >= 300) {
                $last_error = "govtrack.us returned HTTP {$status} while fetching CSRF token";
                continue;
            }
            if (!file_exists($cookie_jar)) {
                $last_error = "govtrack.us did not set any cookies (no cookie jar written)";
                continue;
            }

            $cookies = [];
            foreach (file($cookie_jar) as $line) {
                if (str_starts_with(trim($line), '#') || trim($line) === '') {
                    continue;
                }
                $parts = explode("\t", trim($line));
                if (count($parts) >= 7 && $parts[5] === 'csrftoken') {
                    $cookies['csrftoken'] = $parts[6];
                }
            }

            if (isset($cookies['csrftoken'])) {
                return $cookies['csrftoken'];
            }

            $last_error = "csrftoken cookie not found in govtrack.us response";
        }

        throw new RuntimeException("{$last_error} (after {$max_attempts} attempts)");
    }
}

/** Return the congressional district state and number as an array for the given address. */
if (!function_exists('get_district')) {
    function get_district(string $address, string $token): array
    {
        $ch = curl_init(DISTRICT_ENDPOINT);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query(['address' => $address]));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Referer: ' . URL,
            'x-csrftoken: ' . $token,
        ]);
        curl_setopt($ch, CURLOPT_COOKIE, 'csrftoken=' . $token);
        $response = curl_exec($ch);
        curl_close($ch);

        $data = json_decode($response, true);

        return [$data['state'], $data['number']];
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
        $token = get_token();
        [$state, $district] = get_district($address, $token);
        $html = fetch_html(URL . "congress/members/{$state}/{$district}");
        $reps = parse_reps($html);
        print_r($reps);
    }
}

if (PHP_SAPI === 'cli' && isset($_SERVER['argv']) && realpath($_SERVER['argv'][0]) === __FILE__ && isset($_SERVER['argv'][1])) {
    main($_SERVER['argv'][1]);
}
