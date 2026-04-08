<?php

declare(strict_types=1);

namespace Xiidea\EasyImgProxyBundle\Tests\Unit\Preset;

use PHPUnit\Framework\TestCase;
use Xiidea\EasyImgProxyBundle\Preset\Preset;

class PresetTest extends TestCase
{
    public function testPresetCanBeCreatedWithOptions(): void
    {
        $options = ['width' => 200, 'height' => 300, 'quality' => 80];

        $preset = new Preset($options);

        $this->assertSame($options, $preset->getOptions());
    }

    public function testPresetCanBeCreatedWithExtension(): void
    {
        $preset = new Preset([], 'webp');

        $this->assertSame('webp', $preset->getExtension());
    }

    public function testPresetCanBeCreatedWithBothOptionsAndExtension(): void
    {
        $options = ['width' => 200, 'quality' => 85];
        $preset = new Preset($options, 'png');

        $this->assertSame($options, $preset->getOptions());
        $this->assertSame('png', $preset->getExtension());
    }

    public function testPresetHasOption(): void
    {
        $preset = new Preset(['width' => 200, 'height' => 300]);

        $this->assertTrue($preset->hasOption('width'));
        $this->assertTrue($preset->hasOption('height'));
        $this->assertFalse($preset->hasOption('quality'));
    }

    public function testPresetGetOption(): void
    {
        $preset = new Preset(['width' => 200, 'quality' => 75]);

        $this->assertSame(200, $preset->getOption('width'));
        $this->assertSame(75, $preset->getOption('quality'));
        $this->assertNull($preset->getOption('nonexistent'));
    }

    public function testPresetExtensionIsNullByDefault(): void
    {
        $preset = new Preset(['width' => 200]);

        $this->assertNull($preset->getExtension());
    }

    public function testPresetCanBeCreatedEmpty(): void
    {
        $preset = new Preset();

        $this->assertEmpty($preset->getOptions());
        $this->assertNull($preset->getExtension());
    }

    public function testPresetWithVariousOptionTypes(): void
    {
        $options = [
            'width' => 200,
            'dpr' => 2.5,
            'background' => 'ffffff',
            'gravity' => 'center',
        ];

        $preset = new Preset($options);

        $this->assertSame(200, $preset->getOption('width'));
        $this->assertSame(2.5, $preset->getOption('dpr'));
        $this->assertSame('ffffff', $preset->getOption('background'));
        $this->assertSame('center', $preset->getOption('gravity'));
    }
}
