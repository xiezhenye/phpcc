<?php
include '../sample/calc.php';

class CalcTest extends \PHPUnit_Framework_TestCase {
    function testCalc() {
        $calc = new Calculator();
        $result = $calc->calc('123');
        $this->assertEquals(123, $result);
        $result = $calc->calc('100 - (35 - 4)');
        $this->assertEquals(100 - (35 - 4), $result);
        $result = $calc->calc('2 * (3 + 4)');
        $this->assertEquals(2 * (3 + 4), $result);
        $result = $calc->calc('9 / 3 * 2');
        $this->assertEquals(9 / 3 * 2, $result);
        $result = $calc->calc('(4+1) * (6 - 1)');
        $this->assertEquals((4+1) * (6 - 1), $result);
        $result = $calc->calc('-(11+29)');
        $this->assertEquals(-(11+29), $result);
        $result = $calc->calc('4 - -3 * 4');
        $this->assertEquals(4 - -3 * 4, $result);
        $result = $calc->calc('-1 * -1');
        $this->assertEquals(-1 * -1, $result);
        $result = $calc->calc('2.5 * 8');
        $this->assertEquals(2.5 * 8, $result);
        $result = $calc->calc('cos pi/2');
        $this->assertEquals(cos(M_PI)/2, $result);
        $result = $calc->calc('4 pi-1');
        $this->assertEquals(4 * M_PI-1, $result);
        $result = $calc->calc('2cos -pi');
        $this->assertEquals(2*cos(-M_PI), $result);
    }
}

