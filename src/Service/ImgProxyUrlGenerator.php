<?php

declare(strict_types=1);

namespace Xiidea\EasyImgProxyBundle\Service;

use Xiidea\EasyImgProxyBundle\Builder\UrlBuilder;
use Xiidea\EasyImgProxyBundle\Preset\PresetRegistry;

class ImgProxyUrlGenerator
{
    public function __construct(
        private string $key,
        private string $salt,
        private string $baseUrl,
        private ?PresetRegistry $presetRegistry = null,
    ) {
    }

    /**
     * Create a new URL builder instance.
     */
    public function builder(): UrlBuilder
    {
        return new UrlBuilder($this->key, $this->salt, $this->baseUrl, $this->presetRegistry);
    }

    /**
     * Generate a URL with inline configuration.
     *
     * @param array<string, mixed> $options Processing options
     */
    public function generate(
        string $imageUrl,
        array $options = [],
        ?string $extension = null,
    ): string {
        $builder = $this->builder();
        $builder->withImageUrl($imageUrl);

        foreach ($options as $key => $value) {
            $builder->withOption($key, $value);
        }

        if ($extension !== null) {
            $builder->withExtension($extension);
        }

        return $builder->build();
    }
}
