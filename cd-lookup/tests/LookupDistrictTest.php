<?php

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../src/LookupDistrict.php';

class LookupDistrictTest extends TestCase
{
    private string $html;

    protected function setUp(): void
    {
        $this->html = file_get_contents(
            __DIR__ . '/data/12th_congressional_district.html'
        );
    }

    public function test_parse_reps_returns_senators_and_representatives_keys(): void
    {
        $result = parse_reps($this->html);
        $this->assertArrayHasKey('senators', $result);
        $this->assertArrayHasKey('representatives', $result);
    }

    public function test_parse_reps_senator_count(): void
    {
        $result = parse_reps($this->html);
        $this->assertCount(2, $result['senators']);
    }

    public function test_parse_reps_representative_count(): void
    {
        $result = parse_reps($this->html);
        $this->assertCount(1, $result['representatives']);
    }

    public function test_parse_reps_senator_names(): void
    {
        $senators = parse_reps($this->html)['senators'];
        $names = array_column($senators, 'full_name');
        $this->assertContains("Alejandro \u{201c}Alex\u{201d} Padilla", $names);
        $this->assertContains('Adam Schiff', $names);
    }

    public function test_parse_reps_representative_name(): void
    {
        $reps = parse_reps($this->html)['representatives'];
        $this->assertSame('Lateefah Simon', $reps[0]['full_name']);
    }

    public function test_parse_reps_person_fields(): void
    {
        $rep = parse_reps($this->html)['representatives'][0];
        $this->assertSame('Democrat', $rep['party']);
        $this->assertSame('202-225-2661', $rep['phone']);
        $this->assertSame('https://simon.house.gov', $rep['website']);
        $this->assertSame('/congress/members/lateefah_simon/456974', $rep['profile_url']);
    }

    public function test_parse_reps_senator_fields(): void
    {
        $senators = parse_reps($this->html)['senators'];
        $padilla = current(array_filter($senators, fn($s) => str_contains($s['full_name'], 'Padilla')));
        $this->assertSame('Democrat', $padilla['party']);
        $this->assertSame('202-224-3553', $padilla['phone']);
        $this->assertSame('https://www.padilla.senate.gov', $padilla['website']);
        $this->assertStringContainsString('Senior Senator', $padilla['role']);
    }

    public function test_parse_reps_empty_html(): void
    {
        $result = parse_reps('<html></html>');
        $this->assertSame(['senators' => [], 'representatives' => []], $result);
    }

    public function test_parse_reps_representative_role(): void
    {
        $rep = parse_reps($this->html)['representatives'][0];
        $this->assertStringContainsString('Representative', $rep['role']);
    }

    public function test_parse_reps_senator_profile_urls(): void
    {
        $senators = parse_reps($this->html)['senators'];
        $urls = array_column($senators, 'profile_url');
        $this->assertContains('/congress/members/alejandro_padilla/456856', $urls);
        $this->assertContains('/congress/members/adam_schiff/400361', $urls);
    }

    public function test_parse_reps_row_without_info_div_is_skipped(): void
    {
        $html = '<html><body>
            <div class="row" style="margin-bottom: 1.5em"><div class="col-sm-3">no info here</div></div>
        </body></html>';
        $result = parse_reps($html);
        $this->assertSame([], $result['senators']);
        $this->assertSame([], $result['representatives']);
    }

    public function test_parse_reps_row_without_name_link_is_skipped(): void
    {
        $html = '<html><body>
            <div class="row" style="margin-bottom: 1.5em">
                <div class="col-sm-9"><p>No bold link here</p></div>
            </div>
        </body></html>';
        $result = parse_reps($html);
        $this->assertSame([], $result['senators']);
        $this->assertSame([], $result['representatives']);
    }

    public function test_parse_reps_missing_phone_defaults_to_empty_string(): void
    {
        $html = '<html><body>
            <div class="row" style="margin-bottom: 1.5em">
                <div class="col-sm-9">
                    <a href="/profile" style="font-weight: bold">Jane Doe</a>
                    <div></div>
                    <div>Representative for Test District</div>
                    <div style="margin-bottom: .45em">Republican</div>
                </div>
            </div>
        </body></html>';
        $rep = parse_reps($html)['representatives'][0];
        $this->assertSame('', $rep['phone']);
    }

    public function test_parse_reps_missing_website_defaults_to_empty_string(): void
    {
        $html = '<html><body>
            <div class="row" style="margin-bottom: 1.5em">
                <div class="col-sm-9">
                    <a href="/profile" style="font-weight: bold">Jane Doe</a>
                    <div></div>
                    <div>Representative for Test District</div>
                    <div style="margin-bottom: .45em">Republican</div>
                    <a href="tel:555-1234">555-1234</a>
                </div>
            </div>
        </body></html>';
        $rep = parse_reps($html)['representatives'][0];
        $this->assertSame('', $rep['website']);
    }

    public function test_parse_reps_senator_role_routes_to_senators_array(): void
    {
        $html = '<html><body>
            <div class="row" style="margin-bottom: 1.5em">
                <div class="col-sm-9">
                    <a href="/profile" style="font-weight: bold">John Smith</a>
                    <div></div>
                    <div>Junior Senator for TestState</div>
                </div>
            </div>
        </body></html>';
        $result = parse_reps($html);
        $this->assertCount(1, $result['senators']);
        $this->assertCount(0, $result['representatives']);
        $this->assertSame('John Smith', $result['senators'][0]['full_name']);
    }

    public function test_parse_reps_role_empty_when_only_one_child_div(): void
    {
        $html = '<html><body>
            <div class="row" style="margin-bottom: 1.5em">
                <div class="col-sm-9">
                    <a href="/profile" style="font-weight: bold">Jane Doe</a>
                    <div>only child div</div>
                </div>
            </div>
        </body></html>';
        $rep = parse_reps($html)['representatives'][0];
        $this->assertSame('', $rep['role']);
    }
}
