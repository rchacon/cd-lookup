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

        register_setting('cd_lookup', 'cd_lookup_vote_topics', [
            'type'              => 'string',
            'sanitize_callback' => 'cd_lookup_sanitize_vote_topics',
            'default'           => '',
        ]);

        add_settings_field(
            'cd_lookup_vote_topics',
            'Voting record topics',
            'cd_lookup_render_vote_topics_field',
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

// cd-server rejects a voting-record search query over 200 chars (mirrored
// as TOPIC_MAX in cd-webapp's VotingRecord.tsx), so a longer topic would
// land the visitor on a failed search.
const CD_LOOKUP_VOTE_TOPIC_MAX = 200;

/**
 * Split the admin's one-topic-per-line text into a clean list: trimmed,
 * blank lines dropped, capped at CD_LOOKUP_VOTE_TOPIC_MAX chars, deduped.
 */
if (!function_exists('cd_lookup_parse_vote_topics')) {
    function cd_lookup_parse_vote_topics(string $text): array
    {
        $topics = array_map(
            fn ($line) => mb_substr(trim($line), 0, CD_LOOKUP_VOTE_TOPIC_MAX),
            preg_split('/\R/u', $text) ?: []
        );

        return array_values(array_unique(array_filter($topics, fn ($topic) => $topic !== '')));
    }
}

if (!function_exists('cd_lookup_sanitize_vote_topics')) {
    function cd_lookup_sanitize_vote_topics($value): string
    {
        return implode("\n", cd_lookup_parse_vote_topics(sanitize_textarea_field((string) $value)));
    }
}

/** The admin-curated topics offered on each voting Representative's card. */
if (!function_exists('cd_lookup_vote_topics')) {
    function cd_lookup_vote_topics(): array
    {
        return cd_lookup_parse_vote_topics((string) get_option('cd_lookup_vote_topics', ''));
    }
}

if (!function_exists('cd_lookup_render_vote_topics_field')) {
    function cd_lookup_render_vote_topics_field(): void
    {
        $value = get_option('cd_lookup_vote_topics', '');
        echo '<textarea name="cd_lookup_vote_topics" rows="8" class="large-text">' . esc_textarea($value) . '</textarea>';
        echo '<p class="description">One topic per line. Shown as a dropdown on each voting Representative&rsquo;s card, linking to their CivicDog voting record for that topic. Leave empty to hide.</p>';
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
