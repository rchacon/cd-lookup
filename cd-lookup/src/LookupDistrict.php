<?php

const CENSUS_GEOCODER_ENDPOINT = 'https://geocoding.geo.census.gov/geocoder/geographies/onelineaddress';
const CD_PLATFORM_MEMBERS_ENDPOINT_DEFAULT = 'https://lix3lbjjkl.execute-api.us-west-2.amazonaws.com/prod/members';

/** A problem with the address itself (no match or too ambiguous to resolve) rather than a geocoder/network failure. */
if (!class_exists('InvalidAddressException')) {
    class InvalidAddressException extends RuntimeException {}
}

/** The address didn't match any known location. */
if (!class_exists('NoAddressMatchException')) {
    class NoAddressMatchException extends InvalidAddressException {}
}

/** The address matched more than one candidate location; the caller should ask for a more specific address. */
if (!class_exists('AmbiguousAddressException')) {
    class AmbiguousAddressException extends InvalidAddressException {}
}

/** Issue a GET request and return its body, error string, and HTTP status, so callers can compose their own error messages. */
if (!function_exists('curl_get')) {
    function curl_get(string $url, array $headers = []): array
    {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        if ($headers) {
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        }
        $body = curl_exec($ch);
        $error = curl_error($ch);
        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        return ['body' => $body, 'error' => $error, 'status' => $status];
    }
}

/**
 * Fetch senators/representatives for a state (and optional district) from
 * cd-platform's cd-api. Returns ['senators' => [...], 'representatives' => [...]],
 * each person an array with keys: role, party, phone, website, photo_url, plus
 * either full_name (older cd-api deploys) or first_name/middle_name/last_name/
 * nickname/suffix (current cd-api) -- see cd_lookup_display_name().
 *
 * No retry/backoff on failure -- a single attempt only, same convention the
 * old govtrack.us fetch followed (see cd-lookup#8).
 */
if (!function_exists('fetch_members')) {
    function fetch_members(string $state, string $district, string $api_key, string $endpoint = CD_PLATFORM_MEMBERS_ENDPOINT_DEFAULT): array
    {
        $url = $endpoint . '?' . http_build_query([
            'state'    => $state,
            'district' => (int) $district,
        ]);

        ['body' => $response, 'error' => $error, 'status' => $status] = curl_get($url, ["x-api-key: {$api_key}"]);

        if ($response === false) {
            throw new RuntimeException("Failed to reach cd-platform API: {$error}");
        }
        if ($status < 200 || $status >= 300) {
            $problem = json_decode($response, true);
            $detail = is_array($problem) && isset($problem['detail']) ? $problem['detail'] : null;
            throw new RuntimeException($detail ?? "cd-platform API returned HTTP {$status}");
        }

        $data = json_decode($response, true);

        if (!is_array($data) || !isset($data['senators'], $data['representatives'])
            || !is_array($data['senators']) || !is_array($data['representatives'])) {
            throw new RuntimeException('cd-platform API returned an unexpected response while fetching members');
        }

        return $data;
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
            throw new NoAddressMatchException("Census geocoder found no address match for \"{$address}\"");
        }
        if (count($matches) > 1) {
            throw new AmbiguousAddressException("Census geocoder found multiple possible matches for \"{$address}\"; please provide a more specific address");
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
 * Look up and display congressional representatives for the given street
 * address, via cd-platform. Reads the API key from the CD_API_KEY env var
 * since get_option() (WordPress) isn't available in this CLI context.
 */
if (!function_exists('main')) {
    function main(string $address): void
    {
        $api_key = getenv('CD_API_KEY');
        if ($api_key === false || $api_key === '') {
            throw new RuntimeException('CD_API_KEY environment variable is not set');
        }

        [$state, $district] = get_district($address);
        $reps = fetch_members($state, $district, $api_key);
        print_r($reps);
    }
}

if (PHP_SAPI === 'cli' && isset($_SERVER['argv']) && realpath($_SERVER['argv'][0]) === __FILE__ && isset($_SERVER['argv'][1])) {
    main($_SERVER['argv'][1]);
}
