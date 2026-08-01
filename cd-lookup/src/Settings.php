<?php

/**
 * Admin settings page for the cd-platform API key (Settings > CD Lookup).
 * Stored as a plain WordPress option -- there's no separate secrets store
 * in WP core beyond the Options API, so anyone with DB access to wp_options
 * can read it, same as any other plugin setting.
 */

if (!function_exists('cd_lookup_register_settings')) {
    function cd_lookup_register_settings(): void
    {
        register_setting('cd_lookup', 'cd_lookup_api_key', [
            'type'              => 'string',
            'sanitize_callback' => 'sanitize_text_field',
            'default'           => '',
        ]);

        add_settings_section(
            'cd_lookup_main',
            '',
            '__return_false',
            'cd-lookup'
        );

        add_settings_field(
            'cd_lookup_api_key',
            'cd-platform API Key',
            'cd_lookup_render_api_key_field',
            'cd-lookup',
            'cd_lookup_main'
        );
    }
}
add_action('admin_init', 'cd_lookup_register_settings');

if (!function_exists('cd_lookup_render_api_key_field')) {
    function cd_lookup_render_api_key_field(): void
    {
        $value = get_option('cd_lookup_api_key', '');
        echo '<input type="password" name="cd_lookup_api_key" value="' . esc_attr($value) . '" class="regular-text" autocomplete="off">';
    }
}

if (!function_exists('cd_lookup_register_settings_page')) {
    function cd_lookup_register_settings_page(): void
    {
        add_options_page(
            'CD Lookup',
            'CD Lookup',
            'manage_options',
            'cd-lookup',
            'cd_lookup_render_settings_page'
        );
    }
}
add_action('admin_menu', 'cd_lookup_register_settings_page');

if (!function_exists('cd_lookup_render_settings_page')) {
    function cd_lookup_render_settings_page(): void
    {
        echo '<div class="wrap"><h1>CD Lookup</h1><form method="post" action="options.php">';
        settings_fields('cd_lookup');
        do_settings_sections('cd-lookup');
        submit_button();
        echo '</form></div>';
    }
}
