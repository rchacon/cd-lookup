<?php

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../src/StateNames.php';

class StateNamesTest extends TestCase
{
    public function test_resolves_known_state_abbreviations(): void
    {
        $this->assertSame('Georgia', state_name('GA'));
        $this->assertSame('California', state_name('CA'));
        $this->assertSame('Wyoming', state_name('WY'));
        $this->assertSame('New York', state_name('NY'));
    }

    public function test_resolves_dc_and_territories(): void
    {
        $this->assertSame('District of Columbia', state_name('DC'));
        $this->assertSame('Puerto Rico', state_name('PR'));
        $this->assertSame('U.S. Virgin Islands', state_name('VI'));
        $this->assertSame('Guam', state_name('GU'));
        $this->assertSame('American Samoa', state_name('AS'));
        $this->assertSame('Northern Mariana Islands', state_name('MP'));
    }

    public function test_returns_null_for_unrecognized_abbreviation(): void
    {
        $this->assertNull(state_name('ZZ'));
    }

    public function test_normalizes_lowercase_input(): void
    {
        $this->assertSame('Georgia', state_name('ga'));
    }

    public function test_normalizes_surrounding_whitespace(): void
    {
        $this->assertSame('Georgia', state_name(' GA '));
    }
}
