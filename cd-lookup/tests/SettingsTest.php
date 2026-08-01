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
}
