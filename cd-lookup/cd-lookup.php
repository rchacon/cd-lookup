<?php
/**
 * Plugin Name: CD Lookup
 * Description: Look up congressional representatives for a given street address.
 * Version:     0.1.0
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

function cd_lookup_get_representatives( WP_REST_Request $request ): WP_REST_Response {
    $address = $request->get_param( 'address' );

    try {
        [ $state, $district ] = cd_lookup_get_district( $address );
        $html = fetch_html( district_page_url( $state, $district ) );
    } catch ( InvalidAddressException $e ) {
        return new WP_REST_Response( [ 'message' => $e->getMessage() ], 422 );
    } catch ( RuntimeException $e ) {
        return new WP_REST_Response( [ 'message' => $e->getMessage() ], 502 );
    }

    return new WP_REST_Response( cd_lookup_sanitize_reps( parse_reps( $html ) ), 200 );
}

/** Reuse a cached district lookup for this address, to avoid a Census geocoder round trip on every request. */
function cd_lookup_get_district( string $address ): array {
    $cache_key = CD_LOOKUP_DISTRICT_TRANSIENT_PREFIX . md5( $address );
    $cached    = get_transient( $cache_key );

    if ( is_array( $cached ) && isset( $cached[0], $cached[1] ) ) {
        return $cached;
    }

    $result = get_district( $address );
    set_transient( $cache_key, $result, CD_LOOKUP_DISTRICT_TTL );

    return $result;
}

/**
 * Sanitize scraped representative data before it reaches the browser.
 *
 * parse_reps() returns raw scraped text (also used by the CLI tool and its tests),
 * so this is the boundary where that data becomes safe for the client-side
 * renderer in templates/lookup-form.php to drop directly into innerHTML.
 */
function cd_lookup_sanitize_reps( array $reps ): array {
    return [
        'senators'        => array_map( 'cd_lookup_sanitize_person', $reps['senators'] ),
        'representatives' => array_map( 'cd_lookup_sanitize_person', $reps['representatives'] ),
    ];
}

function cd_lookup_sanitize_person( array $person ): array {
    return [
        'full_name'   => htmlspecialchars( $person['full_name'], ENT_QUOTES, 'UTF-8' ),
        'role'        => htmlspecialchars( $person['role'], ENT_QUOTES, 'UTF-8' ),
        'party'       => htmlspecialchars( $person['party'], ENT_QUOTES, 'UTF-8' ),
        'phone'       => cd_lookup_sanitize_phone( $person['phone'] ),
        'website'     => cd_lookup_sanitize_url( $person['website'] ),
        'profile_url' => $person['profile_url'],
        'photo_url'   => cd_lookup_sanitize_photo_path( $person['photo_url'] ),
    ];
}

/** Strip everything but digits and common phone punctuation before it's used in a tel: link. */
function cd_lookup_sanitize_phone( string $phone ): string {
    return trim( preg_replace( '/[^0-9+\-() ]/', '', $phone ) );
}

/** Only allow http(s) URLs through, so scraped markup can't smuggle a javascript: URI into an href. */
function cd_lookup_sanitize_url( string $url ): string {
    if ( ! in_array( parse_url( $url, PHP_URL_SCHEME ), [ 'http', 'https' ], true ) ) {
        return '';
    }
    return htmlspecialchars( $url, ENT_QUOTES, 'UTF-8' );
}

/** Only allow a plain relative path through, so it's safe to concatenate onto the govtrack.us host. */
function cd_lookup_sanitize_photo_path( string $path ): string {
    if ( ! preg_match( '#^/[A-Za-z0-9/_.-]*$#', $path ) ) {
        return '';
    }
    return htmlspecialchars( $path, ENT_QUOTES, 'UTF-8' );
}

add_shortcode( 'cd_lookup', function () {
    ob_start();
    include __DIR__ . '/templates/lookup-form.php';
    return ob_get_clean();
} );
