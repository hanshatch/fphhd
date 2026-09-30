<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

class ParseMoneyTest extends TestCase
{
    public function test_accepts_usual_formats(): void
    {
        $this->assertSame('1234.56', parse_money('1,234.56'));
        $this->assertSame('1234.56', parse_money('$1,234.56'));
        $this->assertSame('0.50', parse_money('.50'));
        $this->assertSame('20.00', parse_money('20'));
        $this->assertSame('20.00', parse_money('20.00'));
        $this->assertNull(parse_money('abc'));
    }

    public function test_dot_as_thousands_separator_is_not_read_as_decimals(): void
    {
        $this->assertSame('20000.00', parse_money('20.000'));
        $this->assertSame('1234567.00', parse_money('1.234.567'));
        $this->assertSame('20491.18', parse_money('20,491.18'));
    }
}
