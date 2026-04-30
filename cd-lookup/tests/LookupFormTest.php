<?php

use PHPUnit\Framework\TestCase;

class LookupFormTest extends TestCase
{
    private string $output;
    private DOMXPath $xpath;

    protected function setUp(): void
    {
        ob_start();
        include __DIR__ . '/../templates/lookup-form.php';
        $this->output = ob_get_clean();

        $dom = new DOMDocument();
        @$dom->loadHTML('<html><body>' . $this->output . '</body></html>');
        $this->xpath = new DOMXPath($dom);
    }

    public function test_wrapper_div_has_correct_id(): void
    {
        $nodes = $this->xpath->query('//div[@id="cd-lookup"]');
        $this->assertSame(1, $nodes->length);
    }

    public function test_form_has_correct_id(): void
    {
        $nodes = $this->xpath->query('//form[@id="cd-lookup-form"]');
        $this->assertSame(1, $nodes->length);
    }

    public function test_address_label_text_and_for_attribute(): void
    {
        $label = $this->xpath->query('//label[@for="cd-lookup-address"]')->item(0);
        $this->assertNotNull($label);
        $this->assertSame('Street Address', trim($label->textContent));
    }

    public function test_address_input_attributes(): void
    {
        $input = $this->xpath->query('//input[@id="cd-lookup-address"]')->item(0);
        $this->assertNotNull($input);
        $this->assertSame('text', $input->getAttribute('type'));
        $this->assertSame('address', $input->getAttribute('name'));
        $this->assertTrue($input->hasAttribute('required'));
    }

    public function test_submit_button_type(): void
    {
        $button = $this->xpath->query('//button[@type="submit"]')->item(0);
        $this->assertNotNull($button);
    }

    public function test_results_div_is_hidden_by_default(): void
    {
        $div = $this->xpath->query('//div[@id="cd-lookup-results"]')->item(0);
        $this->assertNotNull($div);
        $this->assertTrue($div->hasAttribute('hidden'));
    }

    public function test_script_inlines_rest_endpoint(): void
    {
        $this->assertStringContainsString(
            '"https://example.com/wp-json/cd-lookup/v1/representatives"',
            $this->output
        );
    }

    public function test_script_inlines_nonce(): void
    {
        $this->assertStringContainsString('"test_nonce"', $this->output);
    }

    public function test_script_fetch_uses_post_method(): void
    {
        $this->assertStringContainsString("method: 'POST'", $this->output);
    }

    public function test_script_sends_wp_nonce_header(): void
    {
        $this->assertStringContainsString("'X-WP-Nonce': nonce", $this->output);
    }

    public function test_script_sends_json_content_type(): void
    {
        $this->assertStringContainsString("'Content-Type': 'application/json'", $this->output);
    }

    public function test_script_defines_render_results_function(): void
    {
        $this->assertStringContainsString('function renderResults(', $this->output);
    }

    public function test_script_defines_render_group_function(): void
    {
        $this->assertStringContainsString('function renderGroup(', $this->output);
    }

    public function test_script_render_group_uses_both_data_keys(): void
    {
        $this->assertStringContainsString('data.senators', $this->output);
        $this->assertStringContainsString('data.representatives', $this->output);
    }
}
