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

    public function test_parse_reps_senator_photo_urls(): void
    {
        $senators = parse_reps($this->html)['senators'];
        $urls = array_column($senators, 'photo_url');
        $this->assertContains('/static/legislator-photos/456856-100px.jpeg', $urls);
        $this->assertContains('/static/legislator-photos/400361-100px.jpeg', $urls);
    }

    public function test_parse_reps_representative_photo_url_empty_without_headshot(): void
    {
        // The fixture's representative has only a placeholder div in place of an <img>.
        $rep = parse_reps($this->html)['representatives'][0];
        $this->assertSame('', $rep['photo_url']);
    }

    public function test_parse_reps_missing_photo_defaults_to_empty_string(): void
    {
        $html = '<html><body>
            <div class="row" style="margin-bottom: 1.5em">
                <div class="col-sm-3"><div style="border: 1px solid black"> </div></div>
                <div class="col-sm-9">
                    <a href="/profile" style="font-weight: bold">Jane Doe</a>
                    <div></div>
                    <div>Representative for Test District</div>
                </div>
            </div>
        </body></html>';
        $rep = parse_reps($html)['representatives'][0];
        $this->assertSame('', $rep['photo_url']);
    }

    public function test_parse_reps_photo_url_from_col_sm_3_image(): void
    {
        $html = '<html><body>
            <div class="row" style="margin-bottom: 1.5em">
                <div class="col-sm-3"><img src="/static/legislator-photos/999-100px.jpeg" alt="Photo" /></div>
                <div class="col-sm-9">
                    <a href="/profile" style="font-weight: bold">Jane Doe</a>
                    <div></div>
                    <div>Representative for Test District</div>
                </div>
            </div>
        </body></html>';
        $rep = parse_reps($html)['representatives'][0];
        $this->assertSame('/static/legislator-photos/999-100px.jpeg', $rep['photo_url']);
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

    public function test_extract_congressional_district_finds_district_field(): void
    {
        $geographies = [
            'States' => [['STATE' => '13', 'STUSAB' => 'GA']],
            '119th Congressional Districts' => [['STATE' => '13', 'CD119' => '05']],
        ];
        $this->assertSame('5', extract_congressional_district($geographies));
    }

    public function test_extract_congressional_district_strips_leading_zero(): void
    {
        $geographies = [
            '119th Congressional Districts' => [['CD119' => '05']],
        ];
        $this->assertSame('5', extract_congressional_district($geographies));
    }

    public function test_extract_congressional_district_at_large_district_returns_zero(): void
    {
        $geographies = [
            '119th Congressional Districts' => [['CD119' => '00']],
        ];
        $this->assertSame('0', extract_congressional_district($geographies));
    }

    public function test_extract_congressional_district_not_pinned_to_a_specific_congress_number(): void
    {
        $geographies = [
            '116th Congressional Districts' => [['CD116' => '12']],
        ];
        $this->assertSame('12', extract_congressional_district($geographies));
    }

    public function test_extract_congressional_district_returns_null_when_layer_absent(): void
    {
        $geographies = [
            'States' => [['STATE' => '13', 'STUSAB' => 'GA']],
        ];
        $this->assertNull(extract_congressional_district($geographies));
    }

    public function test_extract_congressional_district_returns_null_for_empty_geographies(): void
    {
        $this->assertNull(extract_congressional_district([]));
    }

    public function test_district_page_url_includes_district_segment(): void
    {
        $this->assertSame(
            'https://www.govtrack.us/congress/members/GA/5',
            district_page_url('GA', '5')
        );
    }

    public function test_district_page_url_omits_segment_for_at_large_district(): void
    {
        $this->assertSame(
            'https://www.govtrack.us/congress/members/WY',
            district_page_url('WY', '0')
        );
    }
}
