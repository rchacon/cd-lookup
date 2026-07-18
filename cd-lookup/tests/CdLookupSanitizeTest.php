<?php

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../cd-lookup.php';

class CdLookupSanitizeTest extends TestCase
{
    private function person(array $overrides = []): array
    {
        return array_merge([
            'full_name'   => 'Jane Doe',
            'role'        => 'Representative',
            'party'       => 'Independent',
            'phone'       => '202-225-2661',
            'website'     => 'https://example.gov',
            'profile_url' => '/congress/members/jane_doe/1',
            'photo_url'   => '/static/legislator-photos/1-100px.jpeg',
        ], $overrides);
    }

    public function test_sanitize_reps_maps_senators_and_representatives(): void
    {
        $reps = ['senators' => [$this->person()], 'representatives' => [$this->person()]];
        $result = cd_lookup_sanitize_reps($reps);
        $this->assertCount(1, $result['senators']);
        $this->assertCount(1, $result['representatives']);
    }

    public function test_sanitize_person_escapes_html_in_full_name(): void
    {
        $person = $this->person(['full_name' => '<img src=x onerror=alert(1)>']);
        $sanitized = cd_lookup_sanitize_person($person);
        $this->assertSame('&lt;img src=x onerror=alert(1)&gt;', $sanitized['full_name']);
    }

    public function test_sanitize_person_escapes_quotes_that_could_break_out_of_an_attribute(): void
    {
        $person = $this->person(['full_name' => 'Jane" onerror="alert(1)']);
        $sanitized = cd_lookup_sanitize_person($person);
        $this->assertSame('Jane&quot; onerror=&quot;alert(1)', $sanitized['full_name']);
    }

    public function test_sanitize_person_escapes_role_and_party(): void
    {
        $person = $this->person(['role' => '<b>Senator</b>', 'party' => '<i>Democrat</i>']);
        $sanitized = cd_lookup_sanitize_person($person);
        $this->assertSame('&lt;b&gt;Senator&lt;/b&gt;', $sanitized['role']);
        $this->assertSame('&lt;i&gt;Democrat&lt;/i&gt;', $sanitized['party']);
    }

    public function test_sanitize_person_passes_profile_url_through_unchanged(): void
    {
        $person = $this->person(['profile_url' => '/congress/members/jane_doe/1']);
        $sanitized = cd_lookup_sanitize_person($person);
        $this->assertSame('/congress/members/jane_doe/1', $sanitized['profile_url']);
    }

    public function test_sanitize_phone_strips_non_phone_characters(): void
    {
        $this->assertSame('202-225-2661', cd_lookup_sanitize_phone('202-225-2661'));
        $this->assertSame('(1)', cd_lookup_sanitize_phone('<script>alert(1)</script>'));
    }

    public function test_sanitize_url_allows_http_and_https(): void
    {
        $this->assertSame('https://example.gov', cd_lookup_sanitize_url('https://example.gov'));
        $this->assertSame('http://example.gov', cd_lookup_sanitize_url('http://example.gov'));
    }

    public function test_sanitize_url_rejects_javascript_scheme(): void
    {
        $this->assertSame('', cd_lookup_sanitize_url('javascript:alert(1)'));
    }

    public function test_sanitize_url_rejects_empty_and_malformed_urls(): void
    {
        $this->assertSame('', cd_lookup_sanitize_url(''));
        $this->assertSame('', cd_lookup_sanitize_url('not a url'));
    }

    public function test_sanitize_url_escapes_html_special_characters(): void
    {
        $this->assertSame(
            'https://example.gov/?a=1&amp;b=2',
            cd_lookup_sanitize_url('https://example.gov/?a=1&b=2')
        );
    }

    public function test_sanitize_photo_path_allows_expected_govtrack_path(): void
    {
        $this->assertSame(
            '/static/legislator-photos/1-100px.jpeg',
            cd_lookup_sanitize_photo_path('/static/legislator-photos/1-100px.jpeg')
        );
    }

    public function test_sanitize_photo_path_rejects_absolute_urls_and_attribute_breakouts(): void
    {
        $this->assertSame('', cd_lookup_sanitize_photo_path('https://evil.example/x.jpg'));
        $this->assertSame('', cd_lookup_sanitize_photo_path('/x.jpg" onerror="alert(1)'));
    }

    public function test_sanitize_photo_path_rejects_empty_path(): void
    {
        $this->assertSame('', cd_lookup_sanitize_photo_path(''));
    }
}
