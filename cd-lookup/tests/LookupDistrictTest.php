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
}
