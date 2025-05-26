<?php

namespace HayderHatem\FilamentExcelImport\Tests\Unit;

use HayderHatem\FilamentExcelImport\Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

class BasicTest extends TestCase
{
    #[Test]
    public function it_can_load_service_provider()
    {
        $this->assertTrue(true);
    }

    #[Test]
    public function it_has_correct_package_namespace()
    {
        $this->assertEquals('HayderHatem\FilamentExcelImport', 'HayderHatem\FilamentExcelImport');
    }

    #[Test]
    public function it_can_instantiate_test_case()
    {
        $this->assertInstanceOf(TestCase::class, $this);
    }
}
