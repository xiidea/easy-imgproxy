<?php

declare(strict_types=1);

namespace Xiidea\EasyImgProxyBundle\Tests\Unit\Preset;

use PHPUnit\Framework\TestCase;
use Xiidea\EasyImgProxyBundle\Exception\PresetNotFoundException;
use Xiidea\EasyImgProxyBundle\Preset\Preset;
use Xiidea\EasyImgProxyBundle\Preset\PresetRegistry;

class PresetRegistryTest extends TestCase
{
    private PresetRegistry $registry;

    protected function setUp(): void
    {
        $this->registry = new PresetRegistry();
    }

    public function testRegistryCanRegisterPreset(): void
    {
        $preset = new Preset(['width' => 200], 'webp');

        $this->registry->register('thumbnail', $preset);

        $this->assertTrue($this->registry->has('thumbnail'));
    }

    public function testRegistryCanRetrievePreset(): void
    {
        $preset = new Preset(['width' => 200, 'height' => 200], 'webp');
        $this->registry->register('thumbnail', $preset);

        $retrieved = $this->registry->get('thumbnail');

        $this->assertSame($preset, $retrieved);
    }

    public function testRegistryThrowsExceptionForNonexistentPreset(): void
    {
        $this->expectException(PresetNotFoundException::class);
        $this->expectExceptionMessage('Preset "nonexistent" not found.');

        $this->registry->get('nonexistent');
    }

    public function testRegistryCanRegisterMultiplePresets(): void
    {
        $presets = [
            'thumbnail' => new Preset(['width' => 200, 'height' => 200], 'webp'),
            'hero' => new Preset(['width' => 1200, 'height' => 400], null),
            'avatar' => new Preset(['width' => 48, 'height' => 48], 'png'),
        ];

        $this->registry->registerMany($presets);

        $this->assertTrue($this->registry->has('thumbnail'));
        $this->assertTrue($this->registry->has('hero'));
        $this->assertTrue($this->registry->has('avatar'));
    }

    public function testRegistryReturnsAllPresets(): void
    {
        $preset1 = new Preset(['width' => 200], 'webp');
        $preset2 = new Preset(['width' => 400], 'png');

        $this->registry->register('small', $preset1);
        $this->registry->register('large', $preset2);

        $all = $this->registry->all();

        $this->assertCount(2, $all);
        $this->assertArrayHasKey('small', $all);
        $this->assertArrayHasKey('large', $all);
    }

    public function testRegistryReturnsPresetNames(): void
    {
        $this->registry->register('thumbnail', new Preset([], null));
        $this->registry->register('hero', new Preset([], null));
        $this->registry->register('avatar', new Preset([], null));

        $names = $this->registry->names();

        $this->assertContains('thumbnail', $names);
        $this->assertContains('hero', $names);
        $this->assertContains('avatar', $names);
        $this->assertCount(3, $names);
    }

    public function testRegistryCanCheckIfPresetExists(): void
    {
        $this->registry->register('exists', new Preset([], null));

        $this->assertTrue($this->registry->has('exists'));
        $this->assertFalse($this->registry->has('doesnotexist'));
    }

    public function testRegistryCanBeCreatedFromArray(): void
    {
        $presets = [
            'small' => new Preset(['width' => 200], 'webp'),
            'large' => new Preset(['width' => 1200], 'jpg'),
        ];

        $registry = PresetRegistry::fromArray($presets);

        $this->assertTrue($registry->has('small'));
        $this->assertTrue($registry->has('large'));
        $this->assertCount(2, $registry->all());
    }

    public function testRegistryCanBeCreatedFromEmptyArray(): void
    {
        $registry = PresetRegistry::fromArray([]);

        $this->assertCount(0, $registry->all());
        $this->assertFalse($registry->has('anything'));
    }

    public function testRegistryRegisterReturnsItself(): void
    {
        $result = $this->registry->register('test', new Preset([], null));

        $this->assertSame($this->registry, $result);
    }

    public function testRegistryRegisterManyReturnsItself(): void
    {
        $result = $this->registry->registerMany([
            'test' => new Preset([], null),
        ]);

        $this->assertSame($this->registry, $result);
    }

    public function testRegistryOverwritesPreviousPresetWithSameName(): void
    {
        $preset1 = new Preset(['width' => 100], 'jpg');
        $preset2 = new Preset(['width' => 200], 'webp');

        $this->registry->register('same', $preset1);
        $this->registry->register('same', $preset2);

        $retrieved = $this->registry->get('same');
        $this->assertSame($preset2, $retrieved);
    }
}
