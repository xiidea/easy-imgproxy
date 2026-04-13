<?php

declare(strict_types=1);

namespace Xiidea\EasyImgProxyBundle\Tests\Unit\Processing;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Xiidea\EasyImgProxyBundle\Processing\ProcessingOption;

class ProcessingOptionTest extends TestCase
{
    // =========================================================================
    // shortName()
    // =========================================================================

    #[DataProvider('shortNameProvider')]
    public function testShortName(string $input, string $expected): void
    {
        $this->assertSame($expected, ProcessingOption::shortName($input));
    }

    public static function shortNameProvider(): iterable
    {
        yield 'width' => ['width', 'w'];
        yield 'height' => ['height', 'h'];
        yield 'quality' => ['quality', 'q'];
        yield 'resizing_type' => ['resizing_type', 'rt'];
        yield 'gravity' => ['gravity', 'g'];
        yield 'blur' => ['blur', 'bl'];
        yield 'sharpen' => ['sharpen', 'sh'];
        yield 'brightness (pro)' => ['brightness', 'br'];
        yield 'preset' => ['preset', 'pr'];
        yield 'format' => ['format', 'f'];
        yield 'dpr stays dpr' => ['dpr', 'dpr'];
    }

    public function testShortNameReturnsUnknownAsIs(): void
    {
        $this->assertSame('foobar', ProcessingOption::shortName('foobar'));
    }

    public function testShortNameReturnsShortFormAsIs(): void
    {
        // 'w' is the short form of 'width' — but shortName() only maps full names
        $this->assertSame('w', ProcessingOption::shortName('w'));
    }

    // =========================================================================
    // isPro()
    // =========================================================================

    public function testIsProReturnsTrueForProOption(): void
    {
        $this->assertTrue(ProcessingOption::isPro('brightness'));
        $this->assertTrue(ProcessingOption::isPro('contrast'));
        $this->assertTrue(ProcessingOption::isPro('saturation'));
        $this->assertTrue(ProcessingOption::isPro('resizing_algorithm'));
    }

    public function testIsProReturnsTrueForProShortForm(): void
    {
        $this->assertTrue(ProcessingOption::isPro('br')); // brightness
        $this->assertTrue(ProcessingOption::isPro('co')); // contrast
        $this->assertTrue(ProcessingOption::isPro('sa')); // saturation
    }

    public function testIsProReturnsFalseForFreeOption(): void
    {
        $this->assertFalse(ProcessingOption::isPro('width'));
        $this->assertFalse(ProcessingOption::isPro('height'));
        $this->assertFalse(ProcessingOption::isPro('quality'));
        $this->assertFalse(ProcessingOption::isPro('blur'));
    }

    public function testIsProReturnsFalseForFreeShortForm(): void
    {
        $this->assertFalse(ProcessingOption::isPro('w'));
        $this->assertFalse(ProcessingOption::isPro('h'));
        $this->assertFalse(ProcessingOption::isPro('q'));
    }

    public function testIsProReturnsFalseForUnknownOption(): void
    {
        $this->assertFalse(ProcessingOption::isPro('unknown_option'));
    }

    // =========================================================================
    // isKnown()
    // =========================================================================

    public function testIsKnownReturnsTrueForFullName(): void
    {
        $this->assertTrue(ProcessingOption::isKnown('width'));
        $this->assertTrue(ProcessingOption::isKnown('brightness'));
    }

    public function testIsKnownReturnsTrueForShortForm(): void
    {
        $this->assertTrue(ProcessingOption::isKnown('w'));
        $this->assertTrue(ProcessingOption::isKnown('br'));
    }

    public function testIsKnownReturnsFalseForUnknown(): void
    {
        $this->assertFalse(ProcessingOption::isKnown('not_an_option'));
    }

    // =========================================================================
    // resolveFullName()
    // =========================================================================

    public function testResolveFullNameFromShortForm(): void
    {
        $this->assertSame('width', ProcessingOption::resolveFullName('w'));
        $this->assertSame('height', ProcessingOption::resolveFullName('h'));
        $this->assertSame('quality', ProcessingOption::resolveFullName('q'));
        $this->assertSame('brightness', ProcessingOption::resolveFullName('br'));
    }

    public function testResolveFullNameReturnsNullForUnknown(): void
    {
        $this->assertNull(ProcessingOption::resolveFullName('xyz'));
    }

    public function testResolveFullNameReturnsNullForFullName(): void
    {
        // 'width' is a full name, not a short form — should return null
        // unless 'width' happens to also be a short form of something else
        $this->assertNull(ProcessingOption::resolveFullName('width'));
    }

    // =========================================================================
    // allNames(), proOptions(), freeOptions()
    // =========================================================================

    public function testAllNamesReturnsNonEmptyArray(): void
    {
        $names = ProcessingOption::allNames();
        $this->assertNotEmpty($names);
        $this->assertContains('width', $names);
        $this->assertContains('brightness', $names);
    }

    public function testProOptionsReturnsOnlyProOptions(): void
    {
        $proOptions = ProcessingOption::proOptions();
        $this->assertNotEmpty($proOptions);

        foreach ($proOptions as $name) {
            $this->assertTrue(ProcessingOption::isPro($name), "$name should be pro");
        }

        $this->assertContains('brightness', $proOptions);
        $this->assertNotContains('width', $proOptions);
    }

    public function testFreeOptionsReturnsOnlyFreeOptions(): void
    {
        $freeOptions = ProcessingOption::freeOptions();
        $this->assertNotEmpty($freeOptions);

        foreach ($freeOptions as $name) {
            $this->assertFalse(ProcessingOption::isPro($name), "$name should be free");
        }

        $this->assertContains('width', $freeOptions);
        $this->assertNotContains('brightness', $freeOptions);
    }

    public function testProAndFreeOptionsCoverAllOptions(): void
    {
        $all = ProcessingOption::allNames();
        $combined = array_merge(ProcessingOption::proOptions(), ProcessingOption::freeOptions());
        sort($all);
        sort($combined);

        $this->assertSame($all, $combined);
    }
}
