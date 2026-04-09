<?php

declare(strict_types=1);

namespace Xiidea\EasyImgProxyBundle\Tests\Functional;

use PHPUnit\Framework\TestCase;
use Xiidea\EasyImgProxyBundle\Builder\UrlBuilder;
use Xiidea\EasyImgProxyBundle\Preset\Preset;
use Xiidea\EasyImgProxyBundle\Preset\PresetRegistry;
use Xiidea\EasyImgProxyBundle\Service\ImgProxyUrlGenerator;

class ImgProxyUrlGeneratorTest extends TestCase
{
    private ImgProxyUrlGenerator $generator;

    protected function setUp(): void
    {
        $key = bin2hex(random_bytes(32));
        $salt = bin2hex(random_bytes(16));
        $baseUrl = 'http://localhost:8080';

        $this->generator = new ImgProxyUrlGenerator($key, $salt, $baseUrl);
    }

    public function testGeneratorReturnsBuilderInstance(): void
    {
        $builder = $this->generator->builder();

        $this->assertInstanceOf(UrlBuilder::class, $builder);
    }

    public function testGeneratorBuilderCanBuildUrl(): void
    {
        $url = $this->generator->builder()
            ->withImageUrl('https://example.com/image.jpg')
            ->withWidth(200)
            ->build();

        $this->assertIsString($url);
        $this->assertStringStartsWith('http://localhost:8080/', $url);
    }

    public function testGeneratorCanGenerateUrlInline(): void
    {
        $imageUrl = 'https://example.com/image.jpg';
        $options = ['width' => 200, 'height' => 300];

        $url = $this->generator->generate($imageUrl, $options);

        $this->assertIsString($url);
        $this->assertStringContainsString('/width/200/', $url);
        $this->assertStringContainsString('/height/300/', $url);
    }

    public function testGeneratorCanGenerateUrlWithExtension(): void
    {
        $imageUrl = 'https://example.com/image.jpg';
        $options = ['width' => 200];
        $extension = 'webp';

        $url = $this->generator->generate($imageUrl, $options, $extension);

        $this->assertStringContainsString('/width/200/', $url);
        $this->assertStringContainsString('/format/webp/', $url);
    }

    public function testGeneratorInlineAndBuilderGenerateSameUrl(): void
    {
        $imageUrl = 'https://example.com/image.jpg';
        $options = ['width' => 200, 'height' => 300, 'quality' => 80];
        $extension = 'png';

        // Using inline generation
        $url1 = $this->generator->generate($imageUrl, $options, $extension);

        // Using builder pattern
        $url2 = $this->generator->builder()
            ->withImageUrl($imageUrl)
            ->withWidth(200)
            ->withHeight(300)
            ->withQuality(80)
            ->withExtension('png')
            ->build();

        $this->assertSame($url1, $url2);
    }

    public function testGeneratorWithEmptyOptions(): void
    {
        $imageUrl = 'https://example.com/image.jpg';

        $url = $this->generator->generate($imageUrl, []);

        $this->assertIsString($url);
        $this->assertStringContainsString(rawurlencode($imageUrl), $url);
    }

    public function testGeneratorWithComplexProcessingOptions(): void
    {
        $imageUrl = 'https://example.com/image.jpg';
        $options = [
            'width' => 400,
            'height' => 600,
            'quality' => 75,
            'gravity' => 'center',
            'resizing_type' => 'fill',
            'dpr' => 2,
        ];

        $url = $this->generator->generate($imageUrl, $options, 'webp');

        $this->assertStringContainsString('/width/400/', $url);
        $this->assertStringContainsString('/height/600/', $url);
        $this->assertStringContainsString('/quality/75/', $url);
        $this->assertStringContainsString('/gravity/center/', $url);
        $this->assertStringContainsString('/resizing_type/fill/', $url);
        $this->assertStringContainsString('/dpr/2/', $url);
        $this->assertStringContainsString('/format/webp/', $url);
    }

    public function testGeneratorConstructorWithValidCredentials(): void
    {
        $key = 'deadbeef' . str_repeat('0', 56);
        $salt = 'cafebabe' . str_repeat('0', 24);
        $baseUrl = 'https://imgproxy.example.com';

        $generator = new ImgProxyUrlGenerator($key, $salt, $baseUrl);

        $url = $generator->generate('https://example.com/image.jpg', ['width' => 100]);

        $this->assertStringStartsWith($baseUrl, $url);
    }

    public function testGeneratorBuilderWithServerPreset(): void
    {
        $url = $this->generator->builder()
            ->withImageUrl('https://example.com/image.jpg')
            ->withServerPreset('blurry')
            ->build();

        $this->assertStringContainsString('/preset/blurry/', $url);
    }

    public function testGeneratorBuilderWithCustomPreset(): void
    {

        $registry = new PresetRegistry();
        $registry->register('thumbnail', new Preset(
            ['width' => 200, 'height' => 200, 'resizing_type' => 'fill', 'quality' => 80],
            'webp'
        ));

        $key = bin2hex(random_bytes(32));
        $salt = bin2hex(random_bytes(16));
        $baseUrl = 'http://localhost:8080';

        $generator = new ImgProxyUrlGenerator($key, $salt, $baseUrl, $registry);

        $url = $generator->builder()
            ->withImageUrl('https://example.com/image.jpg')
            ->withPreset('thumbnail')
            ->build();

        $this->assertStringContainsString('/width/200/', $url);
        $this->assertStringContainsString('/height/200/', $url);
        $this->assertStringContainsString('/resizing_type/fill/', $url);
        $this->assertStringContainsString('/quality/80/', $url);
        $this->assertStringContainsString('/format/webp/', $url);
    }

    public function testGeneratorMixingServerAndCustomPresets(): void
    {

        $registry = new PresetRegistry();
        $registry->register('quality', new Preset(['quality' => 85], null));

        $key = bin2hex(random_bytes(32));
        $salt = bin2hex(random_bytes(16));
        $baseUrl = 'http://localhost:8080';

        $generator = new ImgProxyUrlGenerator($key, $salt, $baseUrl, $registry);

        $url = $generator->builder()
            ->withImageUrl('https://example.com/image.jpg')
            ->withServerPreset('blur')
            ->withPreset('quality')
            ->withWidth(300)
            ->build();

        $this->assertStringContainsString('/preset/blur/', $url);
        $this->assertStringContainsString('/quality/85/', $url);
        $this->assertStringContainsString('/width/300/', $url);
    }

    public function testGeneratorPresetOverrideWithExplicitOptions(): void
    {

        $registry = new PresetRegistry();
        $registry->register('default', new Preset(['width' => 100, 'quality' => 70], 'jpg'));

        $key = bin2hex(random_bytes(32));
        $salt = bin2hex(random_bytes(16));
        $baseUrl = 'http://localhost:8080';

        $generator = new ImgProxyUrlGenerator($key, $salt, $baseUrl, $registry);

        $url = $generator->builder()
            ->withImageUrl('https://example.com/image.jpg')
            ->withPreset('default')
            ->withWidth(400)  // Override preset width
            ->withExtension('webp')  // Override preset extension
            ->build();

        $this->assertStringContainsString('/width/400/', $url);
        $this->assertStringContainsString('/quality/70/', $url);  // From preset
        $this->assertStringContainsString('/format/webp/', $url);
        $this->assertStringNotContainsString('/format/jpg/', $url);
    }

    public function testGeneratorPresetsOnlyViaConfig(): void
    {
        $key = bin2hex(random_bytes(32));
        $salt = bin2hex(random_bytes(16));
        $baseUrl = 'http://localhost:8080';

        $generator = new ImgProxyUrlGenerator($key, $salt, $baseUrl, null, true);

        $url = $generator->builder()
            ->withImageUrl('https://example.com/image.jpg')
            ->withServerPresets(['blur', 'thumb'])
            ->build();

        $this->assertStringContainsString('/blur:thumb/', $url);
        $this->assertStringNotContainsString('/preset/', $url);
    }

    public function testGeneratorPresetsOnlyOverriddenByCode(): void
    {
        $key = bin2hex(random_bytes(32));
        $salt = bin2hex(random_bytes(16));
        $baseUrl = 'http://localhost:8080';

        // Config says presets_only: true
        $generator = new ImgProxyUrlGenerator($key, $salt, $baseUrl, null, true);

        // But code disables it for this builder
        $url = $generator->builder()
            ->usePresetsOnly(false)
            ->withImageUrl('https://example.com/image.jpg')
            ->withServerPreset('blur')
            ->withWidth(200)
            ->build();

        $this->assertStringContainsString('/preset/blur/', $url);
        $this->assertStringContainsString('/width/200/', $url);
    }

    public function testGeneratorStandardModeCanSwitchToPresetsOnly(): void
    {
        // Default: presets_only false
        $url = $this->generator->builder()
            ->usePresetsOnly()
            ->withImageUrl('https://example.com/image.jpg')
            ->withServerPresets(['sharp', 'quality_high'])
            ->build();

        $this->assertStringContainsString('/sharp:quality_high/', $url);
        $this->assertStringNotContainsString('/preset/', $url);
    }
}
