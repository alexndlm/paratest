<?php

class TestOfUnits extends PHPUnit\Framework\TestCase
{
    /**
     * @group fixtures
     */
    public function testTruth()
    {
        $this->assertTrue(true);
    }

    /**
     * @group fixtures
     */
    public function testArrayLength()
    {
        $elems = [1,2,3,4,5];
        $this->assertEquals(5, sizeof($elems));
    }
}
