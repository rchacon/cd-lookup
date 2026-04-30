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

function cd_lookup_get_representatives( WP_REST_Request $request ): WP_REST_Response {
    $address = $request->get_param( 'address' );

    $token = get_token();
    [ $state, $district ] = get_district( $address, $token );
    $html = fetch_html( URL . "congress/members/{$state}/{$district}" );

    return new WP_REST_Response( parse_reps( $html ), 200 );
}

add_shortcode( 'cd_lookup', function () {
    ob_start();
    include __DIR__ . '/templates/lookup-form.php';
    return ob_get_clean();
} );
