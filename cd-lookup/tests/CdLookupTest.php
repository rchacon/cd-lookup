<?php

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../cd-lookup.php';

class CdLookupTest extends TestCase
{
    protected function setUp(): void
    {
        $GLOBALS['stub_get_district_args'] = null;
        $GLOBALS['stub_fetch_html_url'] = null;
        $GLOBALS['stub_get_token_throws'] = null;
    }

    private function makeRequest(string $address): WP_REST_Request
    {
        return new WP_REST_Request('POST', '', ['address' => $address]);
    }

    public function test_returns_wp_rest_response(): void
    {
        $result = cd_lookup_get_representatives($this->makeRequest('123 Main St'));
        $this->assertInstanceOf(WP_REST_Response::class, $result);
    }

    public function test_response_status_is_200(): void
    {
        $result = cd_lookup_get_representatives($this->makeRequest('123 Main St'));
        $this->assertSame(200, $result->get_status());
    }

    public function test_response_data_has_senators_and_representatives_keys(): void
    {
        $data = cd_lookup_get_representatives($this->makeRequest('123 Main St'))->get_data();
        $this->assertArrayHasKey('senators', $data);
        $this->assertArrayHasKey('representatives', $data);
    }

    public function test_passes_address_from_request_to_get_district(): void
    {
        cd_lookup_get_representatives($this->makeRequest('123 Main St, Oakland, CA 94601'));
        $this->assertSame('123 Main St, Oakland, CA 94601', $GLOBALS['stub_get_district_args']['address']);
    }

    public function test_passes_token_to_get_district(): void
    {
        cd_lookup_get_representatives($this->makeRequest('123 Main St'));
        $this->assertSame('stub_token', $GLOBALS['stub_get_district_args']['token']);
    }

    public function test_fetches_correct_district_page_url(): void
    {
        cd_lookup_get_representatives($this->makeRequest('123 Main St'));
        $this->assertSame(
            'https://www.govtrack.us/congress/members/CA/12',
            $GLOBALS['stub_fetch_html_url']
        );
    }

    public function test_response_data_populated_from_parsed_html(): void
    {
        $data = cd_lookup_get_representatives($this->makeRequest('123 Main St'))->get_data();
        $this->assertNotEmpty($data['senators']);
        $this->assertNotEmpty($data['representatives']);
    }

    public function test_get_token_failure_returns_502_instead_of_throwing(): void
    {
        $GLOBALS['stub_get_token_throws'] = 'Failed to reach govtrack.us for CSRF token: timed out (after 2 attempts)';
        $result = cd_lookup_get_representatives($this->makeRequest('123 Main St'));
        $this->assertInstanceOf(WP_REST_Response::class, $result);
        $this->assertSame(502, $result->get_status());
    }

    public function test_get_token_failure_response_includes_original_message(): void
    {
        $GLOBALS['stub_get_token_throws'] = 'Failed to reach govtrack.us for CSRF token: timed out (after 2 attempts)';
        $data = cd_lookup_get_representatives($this->makeRequest('123 Main St'))->get_data();
        $this->assertSame(
            'Failed to reach govtrack.us for CSRF token: timed out (after 2 attempts)',
            $data['message']
        );
    }
}
