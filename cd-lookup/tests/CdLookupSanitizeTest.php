<?php

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../cd-lookup.php';

class CdLookupSanitizeTest extends TestCase
{
    private function person(array $overrides = []): array
    {
        return array_merge([
            'full_name' => 'Jane Doe',
            'role'      => 'Representative',
            'party'     => 'Independent',
            'phone'     => '202-225-2661',
            'website'   => 'https://example.gov',
            'photo_url' => 'https://www.congress.gov/img/member/1-200.jpg',
        ], $overrides);
    }

    /** A person as current cd-api sends it: no full_name, just raw name parts. */
    private function personWithoutFullName(array $overrides = []): array
    {
        $person = array_merge([
            'role'        => 'Representative',
            'party'       => 'Independent',
            'phone'       => '202-225-2661',
            'website'     => 'https://example.gov',
            'photo_url'   => 'https://www.congress.gov/img/member/1-200.jpg',
            'first_name'  => 'Maria',
            'middle_name' => null,
            'last_name'   => 'Cantwell',
            'nickname'    => null,
            'suffix'      => null,
        ], $overrides);
        unset($person['full_name']);
        return $person;
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
        $this->assertSame('&lt;img src=x onerror=alert(1)&gt;', $sanitized['display_name']);
    }

    public function test_sanitize_person_escapes_quotes_that_could_break_out_of_an_attribute(): void
    {
        $person = $this->person(['full_name' => 'Jane" onerror="alert(1)']);
        $sanitized = cd_lookup_sanitize_person($person);
        $this->assertSame('Jane&quot; onerror=&quot;alert(1)', $sanitized['display_name']);
    }

    public function test_sanitize_person_uses_full_name_when_cd_api_still_sends_it(): void
    {
        // Backward compat with an older cd-api deploy that still derives
        // full_name itself.
        $person = $this->person(['full_name' => 'Jane Doe']);
        $sanitized = cd_lookup_sanitize_person($person);
        $this->assertSame('Jane Doe', $sanitized['display_name']);
    }

    public function test_sanitize_person_derives_display_name_when_full_name_absent(): void
    {
        $person = $this->personWithoutFullName();
        $sanitized = cd_lookup_sanitize_person($person);
        $this->assertSame('Maria Cantwell', $sanitized['display_name']);
    }

    public function test_sanitize_person_derivation_includes_middle_name_when_present(): void
    {
        $person = $this->personWithoutFullName(['middle_name' => 'E.']);
        $sanitized = cd_lookup_sanitize_person($person);
        $this->assertSame('Maria E. Cantwell', $sanitized['display_name']);
    }

    public function test_sanitize_person_derivation_includes_suffix_when_present(): void
    {
        $person = $this->personWithoutFullName(['suffix' => 'III']);
        $sanitized = cd_lookup_sanitize_person($person);
        $this->assertSame('Maria Cantwell III', $sanitized['display_name']);
    }

    public function test_sanitize_person_derivation_prefers_nickname_over_first_and_middle_name(): void
    {
        $person = $this->personWithoutFullName(['middle_name' => 'E.', 'nickname' => 'Cindy']);
        $sanitized = cd_lookup_sanitize_person($person);
        $this->assertSame('Cindy Cantwell', $sanitized['display_name']);
    }

    public function test_sanitize_person_derivation_nickname_takes_precedence_over_suffix(): void
    {
        $person = $this->personWithoutFullName(['nickname' => 'Cindy', 'suffix' => 'III']);
        $sanitized = cd_lookup_sanitize_person($person);
        $this->assertSame('Cindy Cantwell', $sanitized['display_name']);
    }

    public function test_sanitize_person_escapes_html_in_derived_display_name(): void
    {
        $person = $this->personWithoutFullName(['last_name' => '<img src=x onerror=alert(1)>']);
        $sanitized = cd_lookup_sanitize_person($person);
        $this->assertSame('Maria &lt;img src=x onerror=alert(1)&gt;', $sanitized['display_name']);
    }

    public function test_sanitize_person_escapes_role_and_party(): void
    {
        $person = $this->person(['role' => '<b>Senator</b>', 'party' => '<i>Democrat</i>']);
        $sanitized = cd_lookup_sanitize_person($person);
        $this->assertSame('&lt;b&gt;Senator&lt;/b&gt;', $sanitized['role']);
        $this->assertSame('&lt;i&gt;Democrat&lt;/i&gt;', $sanitized['party']);
    }

    public function test_sanitize_person_does_not_include_a_profile_url_field(): void
    {
        $sanitized = cd_lookup_sanitize_person($this->person());
        $this->assertArrayNotHasKey('profile_url', $sanitized);
    }

    public function test_sanitize_person_allows_absolute_https_photo_url(): void
    {
        $person = $this->person(['photo_url' => 'https://www.congress.gov/img/member/1-200.jpg']);
        $sanitized = cd_lookup_sanitize_person($person);
        $this->assertSame('https://www.congress.gov/img/member/1-200.jpg', $sanitized['photo_url']);
    }

    public function test_sanitize_person_rejects_non_http_photo_url(): void
    {
        $person = $this->person(['photo_url' => 'javascript:alert(1)']);
        $sanitized = cd_lookup_sanitize_person($person);
        $this->assertSame('', $sanitized['photo_url']);
    }

    public function test_sanitize_person_does_not_crash_on_null_phone(): void
    {
        $sanitized = cd_lookup_sanitize_person($this->person(['phone' => null]));
        $this->assertSame('', $sanitized['phone']);
    }

    public function test_sanitize_person_does_not_crash_on_null_website(): void
    {
        $sanitized = cd_lookup_sanitize_person($this->person(['website' => null]));
        $this->assertSame('', $sanitized['website']);
    }

    public function test_sanitize_person_does_not_crash_on_null_photo_url(): void
    {
        $sanitized = cd_lookup_sanitize_person($this->person(['photo_url' => null]));
        $this->assertSame('', $sanitized['photo_url']);
    }

    public function test_sanitize_person_does_not_crash_on_null_party(): void
    {
        $sanitized = cd_lookup_sanitize_person($this->person(['party' => null]));
        $this->assertSame('', $sanitized['party']);
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

}
