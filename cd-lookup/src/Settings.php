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
 * Return the heading text when $line is a "[Heading]" line, else null.
 */
if (!function_exists('cd_lookup_vote_topic_heading')) {
    function cd_lookup_vote_topic_heading(string $line): ?string
    {
        return preg_match('/^\[(.*)\]$/su', $line, $m) ? trim($m[1]) : null;
    }
}

/**
 * Clean the admin's one-entry-per-line text: each line trimmed and capped
 * at CD_LOOKUP_VOTE_TOPIC_MAX chars, blank lines and empty "[]" headings
 * dropped, and "[ Heading ]" normalized to "[Heading]".
 */
if (!function_exists('cd_lookup_vote_topic_lines')) {
    function cd_lookup_vote_topic_lines(string $text): array
    {
        $lines = [];

        foreach (preg_split('/\R/u', $text) ?: [] as $line) {
            $line = trim($line);
            $heading = cd_lookup_vote_topic_heading($line);

            if ($heading !== null) {
                $line = $heading === '' ? '' : '[' . mb_substr($heading, 0, CD_LOOKUP_VOTE_TOPIC_MAX) . ']';
            } else {
                $line = mb_substr($line, 0, CD_LOOKUP_VOTE_TOPIC_MAX);
            }

            if ($line !== '') {
                $lines[] = $line;
            }
        }

        return $lines;
    }
}

/**
 * Parse the admin's text into a flat list of ['topic' => ..., 'group' => ...]
 * entries. A "[Heading]" line starts a group (rendered as an <optgroup>)
 * that every topic after it belongs to, until the next heading; topics
 * before the first heading have a null group and come first. A repeated
 * heading continues its earlier group rather than starting a second one,
 * a heading with no topics under it is dropped, and a topic repeated within
 * the same group is listed once.
 */
if (!function_exists('cd_lookup_parse_vote_topics')) {
    function cd_lookup_parse_vote_topics(string $text): array
    {
        // Keyed by group name ('' for ungrouped, which is always first);
        // PHP arrays keep insertion order, so groups stay in the order the
        // admin first wrote them.
        $groups = ['' => []];
        $current = '';

        foreach (cd_lookup_vote_topic_lines($text) as $line) {
            $heading = cd_lookup_vote_topic_heading($line);

            if ($heading !== null) {
                $current = $heading;
                $groups[$current] ??= [];
            } else {
                $groups[$current][$line] = true;
            }
        }

        $topics = [];
        foreach ($groups as $group => $members) {
            foreach (array_keys($members) as $topic) {
                $topics[] = ['topic' => (string) $topic, 'group' => $group === '' ? null : (string) $group];
            }
        }

        return $topics;
    }
}

if (!function_exists('cd_lookup_sanitize_vote_topics')) {
    function cd_lookup_sanitize_vote_topics($value): string
    {
        return implode("\n", cd_lookup_vote_topic_lines(sanitize_textarea_field((string) $value)));
    }
}

/**
 * The admin-curated topics offered on each voting Representative's card,
 * as cd_lookup_parse_vote_topics() entries.
 */
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
        echo '<p class="description">One topic per line. Put a heading in square brackets on its own line (e.g. <code>[Gender]</code>) to group the topics below it under that heading. Shown as a dropdown on each voting Representative&rsquo;s card, linking to their CivicDog voting record for that topic. Leave empty to hide.</p>';
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
