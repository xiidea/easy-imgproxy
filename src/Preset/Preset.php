<?php

declare(strict_types=1);

namespace Xiidea\EasyImgProxyBundle\Preset;

/**
 * Represents a reusable set of image processing options.
 */
class Preset
{
    /** @var array<string, mixed> */
    private array $options;

    private ?string $extension;

    /**
     * @param array<string, mixed> $options Processing options (width, height, quality, etc.)
     * @param string|null $extension Output format (webp, png, jpg, etc.)
     */
    public function __construct(array $options = [], ?string $extension = null)
    {
        $this->options = $options;
        $this->extension = $extension;
    }

    /**
     * Get all processing options.
     *
     * @return array<string, mixed>
     */
    public function getOptions(): array
    {
        return $this->options;
    }

    /**
     * Get the output extension.
     */
    public function getExtension(): ?string
    {
        return $this->extension;
    }

    /**
     * Check if preset has a specific option.
     */
    public function hasOption(string $key): bool
    {
        return isset($this->options[$key]);
    }

    /**
     * Get a specific option value.
     */
    public function getOption(string $key): mixed
    {
        return $this->options[$key] ?? null;
    }
}
