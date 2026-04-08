<?php

declare(strict_types=1);

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Xiidea\EasyImgProxyBundle\Service\ImgProxyUrlGenerator;

class ExampleController extends AbstractController
{
    public function __construct(
        private ImgProxyUrlGenerator $imgProxyGenerator,
    ) {
    }

    /**
     * Example 1: Using the builder pattern for thumbnail generation
     */
    public function thumbnail(): Response
    {
        $imageUrl = 'https://example.com/products/image.jpg';

        $url = $this->imgProxyGenerator->builder()
            ->withImageUrl($imageUrl)
            ->withWidth(200)
            ->withHeight(200)
            ->withResizing('fill')
            ->withGravity('center')
            ->withQuality(85)
            ->withExtension('webp')
            ->build();

        return new Response(sprintf('<img src="%s" alt="Thumbnail">', $url));
    }

    /**
     * Example 2: Using inline generation for hero image
     */
    public function heroImage(): Response
    {
        $imageUrl = 'https://example.com/banner/hero.jpg';

        $url = $this->imgProxyGenerator->generate(
            $imageUrl,
            [
                'width' => 1200,
                'height' => 400,
                'resizing_type' => 'fill',
                'gravity' => 'center',
                'quality' => 90,
            ],
            'webp'
        );

        return new Response(sprintf('<img src="%s" alt="Hero">', $url));
    }

    /**
     * Example 3: Responsive image with multiple sizes
     */
    public function responsiveImage(): Response
    {
        $imageUrl = 'https://example.com/product/image.jpg';

        // Generate URLs for different sizes
        $small = $this->imgProxyGenerator->builder()
            ->withImageUrl($imageUrl)
            ->withWidth(400)
            ->withQuality(80)
            ->withExtension('webp')
            ->build();

        $medium = $this->imgProxyGenerator->builder()
            ->withImageUrl($imageUrl)
            ->withWidth(800)
            ->withQuality(85)
            ->withExtension('webp')
            ->build();

        $large = $this->imgProxyGenerator->builder()
            ->withImageUrl($imageUrl)
            ->withWidth(1200)
            ->withQuality(90)
            ->withExtension('webp')
            ->build();

        $html = sprintf(
            '<picture><source srcset="%s" media="(max-width: 600px)"><source srcset="%s" media="(max-width: 1024px)"><img src="%s" alt="Product"></picture>',
            $small,
            $medium,
            $large
        );

        return new Response($html);
    }

    /**
     * Example 4: Avatar with custom processing
     */
    public function avatar(string $userId): Response
    {
        $userAvatarUrl = sprintf('https://example.com/avatars/%s.jpg', $userId);

        $url = $this->imgProxyGenerator->builder()
            ->withImageUrl($userAvatarUrl)
            ->withWidth(48)
            ->withHeight(48)
            ->withResizing('fill')
            ->withGravity('center')
            ->withQuality(80)
            ->withExtension('webp')
            ->build();

        return new Response(sprintf('<img src="%s" alt="Avatar" class="rounded-full">', $url));
    }

    /**
     * Example 5: Using custom presets defined in configuration
     */
    public function withCustomPreset(): Response
    {
        $imageUrl = 'https://example.com/products/item.jpg';

        // Use predefined 'thumbnail' preset
        $url = $this->imgProxyGenerator->builder()
            ->withImageUrl($imageUrl)
            ->withPreset('thumbnail')
            ->build();

        return new Response(sprintf('<img src="%s" alt="Product Thumbnail">', $url));
    }

    /**
     * Example 6: Combining custom presets with server presets
     */
    public function withServerPreset(): Response
    {
        $imageUrl = 'https://example.com/images/photo.jpg';

        // Use server preset + custom preset + explicit options
        $url = $this->imgProxyGenerator->builder()
            ->withImageUrl($imageUrl)
            ->withServerPreset('blur')  // Apply imgproxy server preset
            ->withPreset('product')      // Apply custom preset
            ->withQuality(95)             // Override preset quality
            ->build();

        return new Response(sprintf('<img src="%s" alt="Processed Image">', $url));
    }

    /**
     * Example 7: Product images with responsive sizes using presets
     */
    public function responsiveProductImages(): Response
    {
        $imageUrl = 'https://example.com/products/featured.jpg';

        $thumbnail = $this->imgProxyGenerator->builder()
            ->withImageUrl($imageUrl)
            ->withPreset('thumbnail')
            ->build();

        $medium = $this->imgProxyGenerator->builder()
            ->withImageUrl($imageUrl)
            ->withPreset('product')
            ->build();

        $hero = $this->imgProxyGenerator->builder()
            ->withImageUrl($imageUrl)
            ->withPreset('hero')
            ->build();

        $html = sprintf(
            '<picture>'
            . '<source srcset="%s" media="(max-width: 600px)">'
            . '<source srcset="%s" media="(max-width: 1024px)">'
            . '<img src="%s" alt="Product">'
            . '</picture>',
            $thumbnail,
            $medium,
            $hero
        );

        return new Response($html);
    }

    /**
     * Example 8: Multiple server presets with custom options
     */
    public function multipleServerPresets(): Response
    {
        $imageUrl = 'https://example.com/images/raw.jpg';

        // Combine multiple imgproxy server presets
        $url = $this->imgProxyGenerator->builder()
            ->withImageUrl($imageUrl)
            ->withServerPresets(['sharpen', 'quality:high'])
            ->withWidth(800)
            ->withExtension('webp')
            ->build();

        return new Response(sprintf('<img src="%s" alt="Processed">', $url));
    }
}
