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

    private function memberResource(string $role, ?int $district, string $last): array
    {
        return [
            'type'       => 'member',
            'id'         => strtolower($last[0]) . '000001',
            'attributes' => [
                'first_name' => 'Test',
                'last_name'  => $last,
                'role'       => $role,
                'district'   => $district,
                'state'      => 'GA',
                'in_office'  => true,
            ],
        ];
    }

    public function test_members_by_chamber_splits_senators_and_the_district_representative(): void
    {
        $document = [
            'data' => [
                $this->memberResource('Senator', null, 'Ossoff'),
                $this->memberResource('Senator', null, 'Warnock'),
                $this->memberResource('Representative', 5, 'Williams'),
                $this->memberResource('Representative', 6, 'McBath'),
            ],
            'meta' => [],
        ];

        $grouped = members_by_chamber($document, '5');

        $this->assertSame(['Ossoff', 'Warnock'], array_column($grouped['senators'], 'last_name'));
        $this->assertSame(['Williams'], array_column($grouped['representatives'], 'last_name'));
    }

    public function test_members_by_chamber_flattens_resources_to_their_attributes(): void
    {
        $document = ['data' => [$this->memberResource('Senator', null, 'Ossoff')]];

        $senator = members_by_chamber($document, '5')['senators'][0];

        $this->assertSame('Ossoff', $senator['last_name']);
        $this->assertArrayNotHasKey('attributes', $senator);
    }

    public function test_members_by_chamber_carries_the_resource_id_as_bioguide_id(): void
    {
        $document = ['data' => [$this->memberResource('Representative', 5, 'Williams')]];

        $rep = members_by_chamber($document, '5')['representatives'][0];

        $this->assertSame('w000001', $rep['bioguide_id']);
    }

    public function test_members_by_chamber_prefers_a_bioguide_id_attribute_over_the_resource_id(): void
    {
        $resource = $this->memberResource('Representative', 5, 'Williams');
        $resource['attributes']['bioguide_id'] = 'W000788';

        $rep = members_by_chamber(['data' => [$resource]], '5')['representatives'][0];

        $this->assertSame('W000788', $rep['bioguide_id']);
    }

    public function test_members_by_chamber_matches_at_large_district_zero(): void
    {
        $document = ['data' => [$this->memberResource('Representative', 0, 'Stansbury')]];

        $grouped = members_by_chamber($document, '0');

        $this->assertSame(['Stansbury'], array_column($grouped['representatives'], 'last_name'));
    }

    public function test_members_by_chamber_returns_empty_representatives_for_a_vacant_district(): void
    {
        $document = [
            'data' => [
                $this->memberResource('Senator', null, 'Ossoff'),
                $this->memberResource('Representative', 6, 'McBath'),
            ],
        ];

        $grouped = members_by_chamber($document, '5');

        $this->assertCount(1, $grouped['senators']);
        $this->assertSame([], $grouped['representatives']);
    }

    public function test_members_by_chamber_skips_resources_without_attributes(): void
    {
        $document = ['data' => [['type' => 'member', 'id' => 'x000001'], 'garbage']];

        $grouped = members_by_chamber($document, '5');

        $this->assertSame(['senators' => [], 'representatives' => []], $grouped);
    }

    public function test_members_by_chamber_throws_when_data_is_missing(): void
    {
        $this->expectException(RuntimeException::class);
        members_by_chamber(['errors' => []], '5');
    }

    public function test_cd_platform_error_message_reads_jsonapi_error_detail(): void
    {
        $body = json_encode(['errors' => [['status' => '404', 'title' => 'Not Found', 'detail' => 'No such district for GA.']]]);
        $this->assertSame('No such district for GA.', cd_platform_error_message($body, 404));
    }

    public function test_cd_platform_error_message_falls_back_to_jsonapi_error_title(): void
    {
        $body = json_encode(['errors' => [['status' => '422', 'title' => 'Unprocessable Entity']]]);
        $this->assertSame('Unprocessable Entity', cd_platform_error_message($body, 422));
    }

    public function test_cd_platform_error_message_reads_pre_jsonapi_detail(): void
    {
        $body = json_encode(['detail' => 'legacy problem+json message']);
        $this->assertSame('legacy problem+json message', cd_platform_error_message($body, 400));
    }

    public function test_cd_platform_error_message_falls_back_to_http_status(): void
    {
        $this->assertSame('cd-platform API returned HTTP 502', cd_platform_error_message('<html>gateway</html>', 502));
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
