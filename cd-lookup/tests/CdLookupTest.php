<?php

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../cd-lookup.php';

class CdLookupTest extends TestCase
{
    protected function setUp(): void
    {
        $GLOBALS['stub_get_district_args'] = null;
        $GLOBALS['stub_get_district_calls'] = 0;
        $GLOBALS['stub_get_district_throws'] = null;
        $GLOBALS['stub_get_district_throws_invalid_address'] = null;
        $GLOBALS['stub_get_district_return'] = null;
        $GLOBALS['stub_fetch_members_args'] = null;
        $GLOBALS['stub_fetch_members_calls'] = 0;
        $GLOBALS['stub_fetch_members_throws'] = null;
        $GLOBALS['stub_fetch_members_return'] = null;
        $GLOBALS['stub_transients'] = [];
        $GLOBALS['stub_options'] = ['cd_lookup_api_key' => 'test-api-key'];
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

    public function test_response_data_includes_the_resolved_district(): void
    {
        $data = cd_lookup_get_representatives($this->makeRequest('123 Main St'))->get_data();
        $this->assertSame('12', $data['district']);
    }

    public function test_response_data_includes_at_large_district(): void
    {
        $GLOBALS['stub_get_district_return'] = ['WY', '0'];
        $data = cd_lookup_get_representatives($this->makeRequest('200 W 24th St, Cheyenne, WY 82002'))->get_data();
        $this->assertSame('0', $data['district']);
    }

    public function test_response_data_includes_the_resolved_state_name(): void
    {
        $data = cd_lookup_get_representatives($this->makeRequest('123 Main St'))->get_data();
        $this->assertSame('California', $data['state_name']);
    }

    public function test_response_data_includes_state_name_for_at_large_district(): void
    {
        $GLOBALS['stub_get_district_return'] = ['WY', '0'];
        $data = cd_lookup_get_representatives($this->makeRequest('200 W 24th St, Cheyenne, WY 82002'))->get_data();
        $this->assertSame('Wyoming', $data['state_name']);
    }

    public function test_response_data_state_name_is_null_for_unrecognized_state_abbreviation(): void
    {
        $GLOBALS['stub_get_district_return'] = ['ZZ', '1'];
        $result = cd_lookup_get_representatives($this->makeRequest('123 Main St'));
        $data = $result->get_data();
        $this->assertSame(200, $result->get_status());
        $this->assertNull($data['state_name']);
        $this->assertNotEmpty($data['senators']);
        $this->assertNotEmpty($data['representatives']);
    }

    public function test_passes_address_from_request_to_get_district(): void
    {
        cd_lookup_get_representatives($this->makeRequest('123 Main St, Oakland, CA 94601'));
        $this->assertSame('123 Main St, Oakland, CA 94601', $GLOBALS['stub_get_district_args']['address']);
    }

    public function test_fetches_members_for_the_resolved_state_and_district(): void
    {
        cd_lookup_get_representatives($this->makeRequest('123 Main St'));
        $this->assertSame(
            [
                'state'    => 'CA',
                'district' => '12',
                'api_key'  => 'test-api-key',
                'endpoint' => CD_PLATFORM_MEMBERS_ENDPOINT_DEFAULT,
            ],
            $GLOBALS['stub_fetch_members_args']
        );
    }

    public function test_fetches_members_for_at_large_district(): void
    {
        $GLOBALS['stub_get_district_return'] = ['WY', '0'];
        cd_lookup_get_representatives($this->makeRequest('200 W 24th St, Cheyenne, WY 82002'));
        $this->assertSame('0', $GLOBALS['stub_fetch_members_args']['district']);
    }

    public function test_uses_the_default_endpoint_when_no_override_option_is_set(): void
    {
        cd_lookup_get_representatives($this->makeRequest('123 Main St'));
        $this->assertSame(CD_PLATFORM_MEMBERS_ENDPOINT_DEFAULT, $GLOBALS['stub_fetch_members_args']['endpoint']);
    }

    public function test_uses_the_overridden_endpoint_option_when_set(): void
    {
        $GLOBALS['stub_options']['cd_lookup_api_endpoint'] = 'https://staging.example.test/members';
        cd_lookup_get_representatives($this->makeRequest('123 Main St'));
        $this->assertSame('https://staging.example.test/members', $GLOBALS['stub_fetch_members_args']['endpoint']);
    }

    public function test_missing_api_key_returns_502(): void
    {
        $GLOBALS['stub_options'] = [];
        $result = cd_lookup_get_representatives($this->makeRequest('123 Main St'));
        $this->assertSame(502, $result->get_status());
        $this->assertSame(0, $GLOBALS['stub_fetch_members_calls']);
    }

    public function test_missing_api_key_response_includes_a_clear_message(): void
    {
        $GLOBALS['stub_options'] = [];
        $data = cd_lookup_get_representatives($this->makeRequest('123 Main St'))->get_data();
        $this->assertSame('CD Lookup API key is not configured.', $data['message']);
    }

    public function test_response_data_populated_from_fetched_members(): void
    {
        $data = cd_lookup_get_representatives($this->makeRequest('123 Main St'))->get_data();
        $this->assertNotEmpty($data['senators']);
        $this->assertNotEmpty($data['representatives']);
    }

    public function test_get_district_failure_returns_502_instead_of_throwing(): void
    {
        $GLOBALS['stub_get_district_throws'] = 'Failed to reach the Census geocoder for district lookup: timed out';
        $result = cd_lookup_get_representatives($this->makeRequest('123 Main St'));
        $this->assertInstanceOf(WP_REST_Response::class, $result);
        $this->assertSame(502, $result->get_status());
    }

    public function test_get_district_failure_response_includes_original_message(): void
    {
        $GLOBALS['stub_get_district_throws'] = 'Failed to reach the Census geocoder for district lookup: timed out';
        $data = cd_lookup_get_representatives($this->makeRequest('123 Main St'))->get_data();
        $this->assertSame(
            'Failed to reach the Census geocoder for district lookup: timed out',
            $data['message']
        );
    }

    public function test_invalid_address_returns_422_instead_of_502(): void
    {
        $GLOBALS['stub_get_district_throws_invalid_address'] = 'Census geocoder found no address match for "not a real address"';
        $result = cd_lookup_get_representatives($this->makeRequest('not a real address'));
        $this->assertInstanceOf(WP_REST_Response::class, $result);
        $this->assertSame(422, $result->get_status());
    }

    public function test_invalid_address_response_includes_original_message(): void
    {
        $GLOBALS['stub_get_district_throws_invalid_address'] = 'Census geocoder found no address match for "not a real address"';
        $data = cd_lookup_get_representatives($this->makeRequest('not a real address'))->get_data();
        $this->assertSame(
            'Census geocoder found no address match for &quot;not a real address&quot;',
            $data['message']
        );
    }

    public function test_error_message_is_escaped_for_html_special_characters(): void
    {
        $GLOBALS['stub_get_district_throws_invalid_address'] = 'No match for "<script>alert(1)</script> & friends"';
        $data = cd_lookup_get_representatives($this->makeRequest('<script>alert(1)</script>'))->get_data();
        $this->assertSame(
            'No match for &quot;&lt;script&gt;alert(1)&lt;/script&gt; &amp; friends&quot;',
            $data['message']
        );
    }

    public function test_error_message_with_malformed_utf8_is_not_silently_blanked(): void
    {
        $GLOBALS['stub_get_district_throws_invalid_address'] = "No match for \"123 \xB1\x31 Ave\"";
        $data = cd_lookup_get_representatives($this->makeRequest('123 invalid-utf8 Ave'))->get_data();
        $this->assertNotSame('', $data['message']);
        $this->assertStringContainsString('No match for', $data['message']);
    }

    public function test_second_request_for_same_address_reuses_cached_district(): void
    {
        cd_lookup_get_representatives($this->makeRequest('123 Main St'));
        cd_lookup_get_representatives($this->makeRequest('123 Main St'));
        $this->assertSame(1, $GLOBALS['stub_get_district_calls']);
    }

    public function test_request_for_a_different_address_does_not_reuse_the_cache(): void
    {
        cd_lookup_get_representatives($this->makeRequest('123 Main St'));
        cd_lookup_get_representatives($this->makeRequest('456 Elm St'));
        $this->assertSame(2, $GLOBALS['stub_get_district_calls']);
    }

    public function test_no_cached_district_fetches_a_new_one(): void
    {
        cd_lookup_get_representatives($this->makeRequest('123 Main St'));
        $this->assertSame(1, $GLOBALS['stub_get_district_calls']);
    }

    public function test_differently_cased_or_spaced_address_reuses_the_cache(): void
    {
        cd_lookup_get_representatives($this->makeRequest('123 Main St'));
        cd_lookup_get_representatives($this->makeRequest('  123  main st  '));
        $this->assertSame(1, $GLOBALS['stub_get_district_calls']);
    }

    public function test_second_request_for_same_district_reuses_cached_members(): void
    {
        cd_lookup_get_representatives($this->makeRequest('123 Main St'));
        cd_lookup_get_representatives($this->makeRequest('123 Main St'));
        $this->assertSame(1, $GLOBALS['stub_fetch_members_calls']);
    }

    public function test_request_for_a_different_district_does_not_reuse_the_members_cache(): void
    {
        cd_lookup_get_representatives($this->makeRequest('123 Main St'));
        $GLOBALS['stub_get_district_return'] = ['WY', '0'];
        cd_lookup_get_representatives($this->makeRequest('200 W 24th St, Cheyenne, WY 82002'));
        $this->assertSame(2, $GLOBALS['stub_fetch_members_calls']);
    }

    public function test_no_cached_members_fetches_new_ones(): void
    {
        cd_lookup_get_representatives($this->makeRequest('123 Main St'));
        $this->assertSame(1, $GLOBALS['stub_fetch_members_calls']);
    }

    public function test_response_has_no_profile_url_field(): void
    {
        $data = cd_lookup_get_representatives($this->makeRequest('123 Main St'))->get_data();
        $this->assertArrayNotHasKey('profile_url', $data['senators'][0]);
        $this->assertArrayNotHasKey('profile_url', $data['representatives'][0]);
    }
}
