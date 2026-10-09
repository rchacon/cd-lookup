<?php

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../src/Settings.php';

class SettingsTest extends TestCase
{
    protected function setUp(): void
    {
        $GLOBALS['stub_options'] = [];
        $GLOBALS['stub_registered_settings'] = [];
        $GLOBALS['stub_settings_fields'] = [];
    }

    public function test_register_settings_registers_the_api_key_option(): void
    {
        cd_lookup_register_settings();
        $this->assertArrayHasKey('cd_lookup_api_key', $GLOBALS['stub_registered_settings']);
    }

    public function test_registered_option_uses_sanitize_text_field(): void
    {
        cd_lookup_register_settings();
        $this->assertSame(
            'sanitize_text_field',
            $GLOBALS['stub_registered_settings']['cd_lookup_api_key']['sanitize_callback']
        );
    }

    public function test_registers_the_api_key_settings_field(): void
    {
        cd_lookup_register_settings();
        $this->assertArrayHasKey('cd_lookup_api_key', $GLOBALS['stub_settings_fields']);
    }

    public function test_render_api_key_field_outputs_a_password_input(): void
    {
        $GLOBALS['stub_options']['cd_lookup_api_key'] = 'super-secret';
        ob_start();
        cd_lookup_render_api_key_field();
        $output = ob_get_clean();

        $this->assertStringContainsString('type="password"', $output);
        $this->assertStringContainsString('name="cd_lookup_api_key"', $output);
        $this->assertStringContainsString('value="super-secret"', $output);
    }

    public function test_render_api_key_field_escapes_the_option_value(): void
    {
        $GLOBALS['stub_options']['cd_lookup_api_key'] = '"><script>alert(1)</script>';
        ob_start();
        cd_lookup_render_api_key_field();
        $output = ob_get_clean();

        $this->assertStringNotContainsString('<script>', $output);
    }

    public function test_register_settings_registers_the_vote_topics_option_and_field(): void
    {
        cd_lookup_register_settings();
        $this->assertSame(
            'cd_lookup_sanitize_vote_topics',
            $GLOBALS['stub_registered_settings']['cd_lookup_vote_topics']['sanitize_callback']
        );
        $this->assertArrayHasKey('cd_lookup_vote_topics', $GLOBALS['stub_settings_fields']);
    }

    public function test_render_vote_topics_field_outputs_an_escaped_textarea(): void
    {
        $GLOBALS['stub_options']['cd_lookup_vote_topics'] = "immigration\n</textarea><script>";
        ob_start();
        cd_lookup_render_vote_topics_field();
        $output = ob_get_clean();

        $this->assertStringContainsString('<textarea name="cd_lookup_vote_topics"', $output);
        $this->assertStringContainsString('&lt;/textarea&gt;&lt;script&gt;', $output);
    }

    public function test_parse_vote_topics_trims_drops_blanks_and_dedupes(): void
    {
        $this->assertSame(
            ['immigration enforcement', 'firearm regulation'],
            cd_lookup_parse_vote_topics("  immigration enforcement \r\n\n\tfirearm regulation\nimmigration enforcement\n   \n")
        );
    }

    public function test_parse_vote_topics_caps_each_topic_length(): void
    {
        $topics = cd_lookup_parse_vote_topics(str_repeat('a', 250));
        $this->assertSame(200, mb_strlen($topics[0]));
    }

    public function test_sanitize_vote_topics_strips_tags_and_normalizes_lines(): void
    {
        $this->assertSame(
            "abortion access\ntransgender rights",
            cd_lookup_sanitize_vote_topics("<b>abortion access</b>\r\n\r\ntransgender rights")
        );
    }

    public function test_vote_topics_is_empty_when_option_unset(): void
    {
        $this->assertSame([], cd_lookup_vote_topics());
    }

    public function test_vote_topics_reads_the_saved_option(): void
    {
        $GLOBALS['stub_options']['cd_lookup_vote_topics'] = "immigration enforcement\nfirearm regulation";
        $this->assertSame(['immigration enforcement', 'firearm regulation'], cd_lookup_vote_topics());
    }
}
