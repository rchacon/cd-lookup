<?php

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../src/LookupDistrict.php';

class LookupDistrictTest extends TestCase
{
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

    public function test_extract_congressional_district_ignores_field_from_a_different_congress(): void
    {
        $geographies = [
            '119th Congressional Districts' => [['CD116' => '05']],
        ];
        $this->assertNull(extract_congressional_district($geographies));
    }

    public function test_extract_congressional_district_returns_null_when_layers_disagree(): void
    {
        $geographies = [
            '119th Congressional Districts' => [['CD119' => '05']],
            '119th Congressional Districts (legacy)' => [['CD119' => '07']],
        ];
        $this->assertNull(extract_congressional_district($geographies));
    }

    public function test_extract_congressional_district_returns_null_for_non_numeric_value(): void
    {
        $geographies = [
            '119th Congressional Districts' => [['CD119' => 'ZZ']],
        ];
        $this->assertNull(extract_congressional_district($geographies));
    }

    public function test_no_address_match_exception_is_an_invalid_address_exception(): void
    {
        $this->assertInstanceOf(InvalidAddressException::class, new NoAddressMatchException());
    }

    public function test_ambiguous_address_exception_is_an_invalid_address_exception(): void
    {
        $this->assertInstanceOf(InvalidAddressException::class, new AmbiguousAddressException());
    }
}
