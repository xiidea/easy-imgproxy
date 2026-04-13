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

    private static function base64url(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    public function testGeneratorReturnsBuilderInstance(): void
    {
        $this->assertInstanceOf(UrlBuilder::class, $this->generator->builder());
    }

    public function testGeneratorBuilderCanBuildUrl(): void
    {
        $url = $this->generator->builder()
            ->withImageUrl('https://example.com/image.jpg')
            ->withWidth(200)
            ->build();

        $this->assertIsString($url);
        $this->assertStringStartsWith('http://localhost:8080/', $url);
        $this->assertStringContainsString('/w:200/', $url);
    }

    public function testGeneratorCanGenerateUrlInline(): void
    {
        $url = $this->generator->generate(
            'https://example.com/image.jpg',
            ['width' => 200, 'height' => 300],
        );

        $this->assertStringContainsString('/w:200/', $url);
        $this->assertStringContainsString('/h:300/', $url);
    }

    public function testGeneratorCanGenerateUrlWithExtension(): void
    {
        $imageUrl = 'https://example.com/image.jpg';

        $url = $this->generator->generate($imageUrl, ['width' => 200], 'webp');

        $this->assertStringContainsString('/w:200/', $url);
        $this->assertStringEndsWith(self::base64url($imageUrl) . '.webp', $url);
    }

    public function testGeneratorInlineAndBuilderGenerateSameUrl(): void
    {
        $imageUrl = 'https://example.com/image.jpg';
        $options = ['width' => 200, 'height' => 300, 'quality' => 80];
        $extension = 'png';

        $url1 = $this->generator->generate($imageUrl, $options, $extension);

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

        $this->assertStringContainsString(self::base64url($imageUrl), $url);
    }

    public function testGeneratorWithComplexProcessingOptions(): void
    {
        $url = $this->generator->generate(
            'https://example.com/image.jpg',
            [
                'width' => 400,
                'height' => 600,
                'quality' => 75,
                'gravity' => 'center',
                'resizing_type' => 'fill',
                'dpr' => 2,
            ],
            'webp'
        );

        $this->assertStringContainsString('/w:400/', $url);
        $this->assertStringContainsString('/h:600/', $url);
        $this->assertStringContainsString('/q:75/', $url);
        $this->assertStringContainsString('/g:center/', $url);
        $this->assertStringContainsString('/rt:fill/', $url);
        $this->assertStringContainsString('/dpr:2/', $url);
        $this->assertStringEndsWith('.webp', $url);
    }

    public function testGeneratorBuilderWithServerPreset(): void
    {
        $url = $this->generator->builder()
            ->withImageUrl('https://example.com/image.jpg')
            ->withServerPreset('blurry')
            ->build();

        $this->assertStringContainsString('/pr:blurry/', $url);
    }

    public function testGeneratorBuilderWithCustomPreset(): void
    {
        $registry = new PresetRegistry();
        $registry->register('thumbnail', new Preset(
            ['width' => 200, 'height' => 200, 'resizing_type' => 'fill', 'quality' => 80],
            'webp'
        ));

        $generator = new ImgProxyUrlGenerator(
            bin2hex(random_bytes(32)),
            bin2hex(random_bytes(16)),
            'http://localhost:8080',
            $registry,
        );

        $url = $generator->builder()
            ->withImageUrl('https://example.com/image.jpg')
            ->withPreset('thumbnail')
            ->build();

        $this->assertStringContainsString('/w:200/', $url);
        $this->assertStringContainsString('/h:200/', $url);
        $this->assertStringContainsString('/rt:fill/', $url);
        $this->assertStringContainsString('/q:80/', $url);
        $this->assertStringEndsWith('.webp', $url);
    }

    public function testGeneratorMixingServerAndCustomPresets(): void
    {
        $registry = new PresetRegistry();
        $registry->register('quality', new Preset(['quality' => 85], null));

        $generator = new ImgProxyUrlGenerator(
            bin2hex(random_bytes(32)),
            bin2hex(random_bytes(16)),
            'http://localhost:8080',
            $registry,
        );

        $url = $generator->builder()
            ->withImageUrl('https://example.com/image.jpg')
            ->withServerPreset('blur')
            ->withPreset('quality')
            ->withWidth(300)
            ->build();

        $this->assertStringContainsString('/pr:blur/', $url);
        $this->assertStringContainsString('/q:85/', $url);
        $this->assertStringContainsString('/w:300/', $url);
    }

    public function testGeneratorPresetOverrideWithExplicitOptions(): void
    {
        $registry = new PresetRegistry();
        $registry->register('default', new Preset(['width' => 100, 'quality' => 70], 'jpg'));

        $generator = new ImgProxyUrlGenerator(
            bin2hex(random_bytes(32)),
            bin2hex(random_bytes(16)),
            'http://localhost:8080',
            $registry,
        );

        $url = $generator->builder()
            ->withImageUrl('https://example.com/image.jpg')
            ->withPreset('default')
            ->withWidth(400)
            ->withExtension('webp')
            ->build();

        $this->assertStringContainsString('/w:400/', $url);
        $this->assertStringContainsString('/q:70/', $url);
        $this->assertStringEndsWith('.webp', $url);
        $this->assertStringNotContainsString('.jpg', $url);
    }

    // =========================================================================
    // Presets-only mode via generator
    // =========================================================================

    public function testGeneratorPresetsOnlyViaConfig(): void
    {
        $generator = new ImgProxyUrlGenerator(
            bin2hex(random_bytes(32)),
            bin2hex(random_bytes(16)),
            'http://localhost:8080',
            null,
            true,
        );

        $url = $generator->builder()
            ->withImageUrl('https://example.com/image.jpg')
            ->withServerPresets(['blur', 'thumb'])
            ->build();

        $this->assertStringContainsString('/blur:thumb/', $url);
        $this->assertStringNotContainsString('pr:', $url);
    }

    public function testGeneratorPresetsOnlyOverriddenByCode(): void
    {
        $generator = new ImgProxyUrlGenerator(
            bin2hex(random_bytes(32)),
            bin2hex(random_bytes(16)),
            'http://localhost:8080',
            null,
            true,
        );

        $url = $generator->builder()
            ->usePresetsOnly(false)
            ->withImageUrl('https://example.com/image.jpg')
            ->withServerPreset('blur')
            ->withWidth(200)
            ->build();

        $this->assertStringContainsString('/pr:blur/', $url);
        $this->assertStringContainsString('/w:200/', $url);
    }

    public function testGeneratorStandardModeCanSwitchToPresetsOnly(): void
    {
        $url = $this->generator->builder()
            ->usePresetsOnly()
            ->withImageUrl('https://example.com/image.jpg')
            ->withServerPresets(['sharp', 'quality_high'])
            ->build();

        $this->assertStringContainsString('/sharp:quality_high/', $url);
        $this->assertStringNotContainsString('pr:', $url);
    }
}
