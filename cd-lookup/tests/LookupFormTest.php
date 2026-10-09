<?php

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../cd-lookup.php';

class LookupFormTest extends TestCase
{
    private string $output;
    private DOMXPath $xpath;

    protected function setUp(): void
    {
        $GLOBALS['stub_options'] = [];
        $this->output = $this->render();

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
        $this->assertSame('Find Your Representative', trim($label->textContent));
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

    public function test_script_defines_error_message_from_response_function(): void
    {
        $this->assertStringContainsString('function errorMessageFromResponse(', $this->output);
    }

    public function test_script_throws_the_backend_error_message_on_non_ok_response(): void
    {
        $this->assertStringContainsString(
            'throw new Error(await errorMessageFromResponse(response));',
            $this->output
        );
    }

    public function test_script_reads_message_from_the_json_response_body(): void
    {
        $this->assertStringContainsString('data.message', $this->output);
    }

    public function test_script_falls_back_to_a_generic_error_message_including_the_http_status(): void
    {
        $this->assertStringContainsString(
            "'Something went wrong, please try again. (HTTP ' + response.status + ')'",
            $this->output
        );
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

    public function test_script_passes_state_name_district_and_state_code_to_the_representatives_group(): void
    {
        $this->assertStringContainsString(
            "renderGroup('Representatives', data.representatives, data.state_name, data.district, data.state)",
            $this->output
        );
        $this->assertStringContainsString(
            "renderGroup('Senators', data.senators, data.state_name)",
            $this->output
        );
    }

    public function test_script_renders_representatives_before_senators(): void
    {
        $this->assertLessThan(
            strpos($this->output, "renderGroup('Senators'"),
            strpos($this->output, "renderGroup('Representatives'")
        );
    }

    public function test_script_defines_ordinal_function(): void
    {
        $this->assertStringContainsString('function ordinal(', $this->output);
    }

    public function test_script_appends_congressional_district_suffix_for_non_at_large_districts(): void
    {
        $this->assertStringContainsString(
            'for the ${ordinal(district)} congressional district',
            $this->output
        );
        $this->assertStringContainsString("district !== '0'", $this->output);
    }

    public function test_script_renders_senator_role_with_state_name(): void
    {
        $this->assertStringContainsString(
            '${p.role} of ${stateName}',
            $this->output
        );
    }

    public function test_script_renders_representative_role_with_compact_state_code_and_district(): void
    {
        $this->assertStringContainsString(
            '${p.role} for ${stateCode}-${ordinal(district)} District',
            $this->output
        );
    }

    public function test_script_renders_at_large_representative_role_with_state_name(): void
    {
        $this->assertStringContainsString(
            'role = `${p.role} for ${stateName}`;',
            $this->output
        );
    }

    private function render(): string
    {
        ob_start();
        include __DIR__ . '/../templates/lookup-form.php';
        return ob_get_clean();
    }

    public function test_script_inlines_an_empty_vote_topics_list_when_none_configured(): void
    {
        $this->assertStringContainsString('const voteTopics  = [];', $this->output);
    }

    public function test_script_inlines_configured_vote_topics_with_escaped_labels(): void
    {
        $GLOBALS['stub_options']['cd_lookup_vote_topics'] = "immigration enforcement\nguns & <ammo>";
        $output = $this->render();

        $this->assertStringContainsString(
            '{"label":"immigration enforcement","topic":"immigration enforcement"}',
            $output
        );
        $this->assertStringContainsString('{"label":"guns &amp; &lt;ammo&gt;","topic":"guns & <ammo>"}', $output);
    }

    public function test_script_inlines_the_default_civicdog_app_url(): void
    {
        $this->assertStringContainsString('const civicdogUrl = "https://app.civicdog.com";', $this->output);
    }

    public function test_script_inlines_an_overridden_civicdog_app_url_without_trailing_slash(): void
    {
        $GLOBALS['stub_options']['cd_lookup_civicdog_app_url'] = 'https://staging.civicdog.test/';
        $this->assertStringContainsString('const civicdogUrl = "https://staging.civicdog.test";', $this->render());
    }

    public function test_script_only_offers_vote_topics_to_voting_representatives(): void
    {
        $this->assertStringContainsString(
            "if (p.role !== 'Representative' || !p.bioguide_id || !voteTopics.length) return '';",
            $this->output
        );
    }

    public function test_script_links_to_the_member_page_with_the_encoded_topic(): void
    {
        $this->assertStringContainsString(
            '`${civicdogUrl}/member/${select.dataset.bioguide}?topic=${encodeURIComponent(topic.topic)}`',
            $this->output
        );
    }
}
