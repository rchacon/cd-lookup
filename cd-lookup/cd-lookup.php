<?php
/**
 * Plugin Name: CD Lookup
 * Description: Look up congressional representatives for a given street address.
 * Version:     0.4.0
 * Requires at least: 6.0
 * Requires PHP: 8.0
 * Author:      Raul Chacon
 * License:     GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: cd-lookup
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

require_once __DIR__ . '/src/LookupDistrict.php';
require_once __DIR__ . '/src/StateNames.php';
require_once __DIR__ . '/src/Settings.php';

add_action( 'rest_api_init', function () {
    register_rest_route( 'cd-lookup/v1', '/representatives', [
        'methods'             => 'POST',
        'callback'            => 'cd_lookup_get_representatives',
        'permission_callback' => '__return_true',
        'args'                => [
            'address' => [
                'required'          => true,
                'type'              => 'string',
                'sanitize_callback' => 'sanitize_text_field',
            ],
        ],
    ] );
} );

const CD_LOOKUP_DISTRICT_TRANSIENT_PREFIX = 'cd_lookup_district_';
const CD_LOOKUP_DISTRICT_TTL              = DAY_IN_SECONDS;

const CD_LOOKUP_MEMBERS_TRANSIENT_PREFIX = 'cd_lookup_members_';
const CD_LOOKUP_MEMBERS_TTL              = HOUR_IN_SECONDS;

/**
 * Escape a string for safe direct insertion into innerHTML by
 * templates/lookup-form.php's client-side renderer -- the single shared
 * escaping boundary for every value this plugin sends to the browser.
 *
 * ENT_SUBSTITUTE: without it, htmlspecialchars() returns '' for a
 * malformed-UTF-8 input (e.g. bad bytes in cd-platform's error `detail`
 * text) instead of substituting replacement characters, which would make
 * the frontend indistinguishable from "nothing to show" for that value.
 */
function cd_lookup_esc( string $value ): string {
    return htmlspecialchars( $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8' );
}

function cd_lookup_get_representatives( WP_REST_Request $request ): WP_REST_Response {
    $address = $request->get_param( 'address' );

    try {
        [ $state, $district ] = cd_lookup_get_district( $address );

        $api_key = get_option( 'cd_lookup_api_key', '' );
        if ( $api_key === '' ) {
            throw new RuntimeException( 'CD Lookup API key is not configured.' );
        }

        $endpoint = get_option( 'cd_lookup_api_endpoint', CD_PLATFORM_MEMBERS_ENDPOINT_DEFAULT );
        $members  = cd_lookup_fetch_members( $state, $district, $api_key, $endpoint );
    } catch ( InvalidAddressException $e ) {
        return new WP_REST_Response( [ 'message' => cd_lookup_esc( $e->getMessage() ) ], 422 );
    } catch ( RuntimeException $e ) {
        return new WP_REST_Response( [ 'message' => cd_lookup_esc( $e->getMessage() ) ], 502 );
    }

    $response = cd_lookup_sanitize_reps( $members );
    $response['district']   = $district;
    $response['state_name'] = ( $name = state_name( $state ) ) !== null
        ? cd_lookup_esc( $name )
        : null;

    return new WP_REST_Response( $response, 200 );
}

/**
 * Return $compute()'s result, reusing a cached value under $cache_key when
 * one passes $is_valid, to avoid re-doing $compute()'s work on every request.
 * $is_valid guards against trusting a corrupted or foreign transient value
 * as a hit (get_transient() returns bool false for a miss, which every
 * caller's $is_valid here correctly rejects).
 */
function cd_lookup_cached( string $cache_key, int $ttl, callable $is_valid, callable $compute ): mixed {
    $cached = get_transient( $cache_key );

    if ( $is_valid( $cached ) ) {
        return $cached;
    }

    $result = $compute();
    set_transient( $cache_key, $result, $ttl );

    return $result;
}

/**
 * Reuse a cached district lookup for this address, to avoid a Census geocoder round trip on every request.
 *
 * Cache entries are keyed per address with no cap on distinct entries, so an
 * anonymous caller could grow wp_options by submitting many distinct
 * addresses; accepted as a low risk for this plugin's traffic level rather
 * than adding rate limiting or an entry cap. The 1 day TTL is the only bound.
 */
function cd_lookup_get_district( string $address ): array {
    $cache_key = CD_LOOKUP_DISTRICT_TRANSIENT_PREFIX . md5( cd_lookup_normalize_address_for_cache_key( $address ) );

    return cd_lookup_cached(
        $cache_key,
        CD_LOOKUP_DISTRICT_TTL,
        fn ( $cached ) => is_array( $cached ) && isset( $cached[0], $cached[1] ),
        fn () => get_district( $address )
    );
}

/**
 * Collapse trivial formatting differences (case, surrounding/repeated
 * whitespace) before hashing an address into a cache key, so "123 Main St"
 * and "123  main st" share a cache entry instead of each causing their own
 * live Census geocoder call. Only used for the cache key — the original
 * $address is still what's sent to the geocoder.
 */
function cd_lookup_normalize_address_for_cache_key( string $address ): string {
    return strtolower( preg_replace( '/\s+/', ' ', trim( $address ) ) );
}

/**
 * Reuse a cached cd-platform members lookup for this state/district, to
 * avoid a round trip on every request. Shorter TTL than the district cache
 * since a district's roster of representatives can change (resignation,
 * special election) far more often than its boundaries do.
 */
function cd_lookup_fetch_members( string $state, string $district, string $api_key, string $endpoint ): array {
    $cache_key = CD_LOOKUP_MEMBERS_TRANSIENT_PREFIX . md5( "{$state}:{$district}" );

    return cd_lookup_cached(
        $cache_key,
        CD_LOOKUP_MEMBERS_TTL,
        fn ( $cached ) => is_array( $cached ) && isset( $cached['senators'], $cached['representatives'] ),
        fn () => fetch_members( $state, $district, $api_key, $endpoint )
    );
}

/**
 * Sanitize cd-platform's member data before it reaches the browser -- this
 * is the boundary where that data becomes safe for the client-side renderer
 * in templates/lookup-form.php to drop directly into innerHTML.
 */
function cd_lookup_sanitize_reps( array $reps ): array {
    return [
        'senators'        => array_map( 'cd_lookup_sanitize_person', $reps['senators'] ),
        'representatives' => array_map( 'cd_lookup_sanitize_person', $reps['representatives'] ),
    ];
}

function cd_lookup_sanitize_person( array $person ): array {
    return [
        'display_name' => cd_lookup_esc( cd_lookup_display_name( $person ) ),
        'role'         => cd_lookup_esc( $person['role'] ?? '' ),
        'party'        => cd_lookup_esc( $person['party'] ?? '' ),
        'phone'        => cd_lookup_sanitize_phone( $person['phone'] ?? '' ),
        'website'      => cd_lookup_sanitize_url( $person['website'] ?? '' ),
        'photo_url'    => cd_lookup_sanitize_url( $person['photo_url'] ?? '' ),
    ];
}

/**
 * cd-api used to derive a single `full_name` field itself; it now sends raw
 * name parts (`first_name`, `middle_name`, `last_name`, `nickname`, `suffix`)
 * and leaves display-name derivation to the client. Prefer `full_name` when
 * an older cd-api deploy still sends it, so this plugin works against both
 * versions; fall back to deriving it here the same way cd-api used to.
 *
 * TODO: drop the `full_name` branch and this whole derivation, once every
 * deployed cd-api is confirmed to only send the raw name parts.
 */
function cd_lookup_display_name( array $person ): string {
    if ( isset( $person['full_name'] ) ) {
        return $person['full_name'];
    }

    if ( ! empty( $person['nickname'] ) ) {
        return trim( $person['nickname'] . ' ' . ( $person['last_name'] ?? '' ) );
    }

    $parts = array_filter(
        [ $person['first_name'] ?? null, $person['middle_name'] ?? null, $person['last_name'] ?? null ],
        fn ( $part ) => $part !== null && $part !== ''
    );
    $name = implode( ' ', $parts );

    return ! empty( $person['suffix'] ) ? "{$name} {$person['suffix']}" : $name;
}

/** Strip everything but digits and common phone punctuation before it's used in a tel: link. */
function cd_lookup_sanitize_phone( string $phone ): string {
    return trim( preg_replace( '/[^0-9+\-() ]/', '', $phone ) );
}

/** Only allow http(s) URLs through, so the API response can't smuggle a javascript: URI into an href/src. */
function cd_lookup_sanitize_url( string $url ): string {
    if ( ! in_array( parse_url( $url, PHP_URL_SCHEME ), [ 'http', 'https' ], true ) ) {
        return '';
    }
    return cd_lookup_esc( $url );
}

add_shortcode( 'cd_lookup', function () {
    ob_start();
    include __DIR__ . '/templates/lookup-form.php';
    return ob_get_clean();
} );
