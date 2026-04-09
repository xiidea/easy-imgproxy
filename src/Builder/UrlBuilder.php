<?php

declare(strict_types=1);

namespace Xiidea\EasyImgProxyBundle\Builder;

use Xiidea\EasyImgProxyBundle\Exception\InvalidUrlBuilderException;
use Xiidea\EasyImgProxyBundle\Preset\Preset;
use Xiidea\EasyImgProxyBundle\Preset\PresetRegistry;

class UrlBuilder
{
    /** @var array<string, mixed> */
    private array $processingOptions = [];

    private ?string $extension = null;

    private ?string $imageUrl = null;

    private string $key;

    private string $salt;

    private string $baseUrl;

    private ?PresetRegistry $presetRegistry = null;

    /** @var array<string, mixed> */
    private array $presetOptions = [];

    private ?string $presetExtension = null;

    /** @var array<string> */
    private array $serverPresets = [];

    private bool $presetsOnly;

    public function __construct(
        string $key,
        string $salt,
        string $baseUrl,
        ?PresetRegistry $presetRegistry = null,
        bool $presetsOnly = false,
    ) {
        $this->key = $key;
        $this->salt = $salt;
        $this->baseUrl = $baseUrl;
        $this->presetRegistry = $presetRegistry;
        $this->presetsOnly = $presetsOnly;
    }

    /**
     * Set the image source URL.
     */
    public function withImageUrl(string $imageUrl): self
    {
        $this->imageUrl = $imageUrl;

        return $this;
    }

    /**
     * Set the output image extension/format.
     */
    public function withExtension(string $extension): self
    {
        $this->extension = $extension;

        return $this;
    }

    /**
     * Set the image width in pixels.
     */
    public function withWidth(int $width): self
    {
        return $this->withOption('width', $width);
    }

    /**
     * Set the image height in pixels.
     */
    public function withHeight(int $height): self
    {
        return $this->withOption('height', $height);
    }

    /**
     * Set the resizing type (fit, fill, auto, etc.).
     */
    public function withResizing(string $resizing): self
    {
        return $this->withOption('resizing_type', $resizing);
    }

    /**
     * Set the gravity for positioning within the canvas.
     */
    public function withGravity(string $gravity): self
    {
        return $this->withOption('gravity', $gravity);
    }

    /**
     * Set the quality for JPEG compression.
     */
    public function withQuality(int $quality): self
    {
        return $this->withOption('quality', $quality);
    }

    /**
     * Add a custom processing option.
     */
    public function withOption(string $key, mixed $value): self
    {
        $this->processingOptions[$key] = $value;

        return $this;
    }

    /**
     * Apply a preset by name.
     *
     * @throws InvalidUrlBuilderException
     */
    public function withPreset(string $presetName): self
    {
        if ($this->presetRegistry === null) {
            throw new InvalidUrlBuilderException('No preset registry configured.');
        }

        try {
            $preset = $this->presetRegistry->get($presetName);
        } catch (\Exception $e) {
            throw new InvalidUrlBuilderException(
                sprintf('Failed to apply preset "%s": %s', $presetName, $e->getMessage()),
                0,
                $e
            );
        }

        return $this->applyPreset($preset);
    }

    /**
     * Apply a Preset object directly.
     */
    public function applyPreset(Preset $preset): self
    {
        // Store preset options separately so explicit options can override them
        $this->presetOptions = array_merge($this->presetOptions, $preset->getOptions());

        if ($preset->getExtension() !== null) {
            $this->presetExtension = $preset->getExtension();
        }

        return $this;
    }

    /**
     * Apply multiple presets in order (later presets override earlier ones).
     *
     * @param array<string> $presetNames
     *
     * @throws InvalidUrlBuilderException
     */
    public function withPresets(array $presetNames): self
    {
        foreach ($presetNames as $presetName) {
            $this->withPreset($presetName);
        }

        return $this;
    }

    /**
     * Apply a server preset from imgproxy.
     * Server presets can optionally include parameters (e.g., 'blurry:thumb', 'quality:high').
     */
    public function withServerPreset(string $presetName): self
    {
        if (!in_array($presetName, $this->serverPresets, true)) {
            $this->serverPresets[] = $presetName;
        }

        return $this;
    }

    /**
     * Apply multiple server presets.
     *
     * @param array<string> $presetNames Server preset names (can include parameters like 'blurry:thumb')
     */
    public function withServerPresets(array $presetNames): self
    {
        foreach ($presetNames as $presetName) {
            $this->withServerPreset($presetName);
        }

        return $this;
    }

    /**
     * Enable or disable presets-only mode.
     *
     * When enabled, matches IMGPROXY_ONLY_PRESETS=true on the server.
     * Only server presets are used and the URL format becomes:
     * /{signature}/{preset1}:{preset2}/{encoded_image_url}
     */
    public function usePresetsOnly(bool $presetsOnly = true): self
    {
        $this->presetsOnly = $presetsOnly;

        return $this;
    }

    /**
     * Build and return the complete imgproxy URL.
     *
     * @throws InvalidUrlBuilderException
     */
    public function build(): string
    {
        $this->validate();

        $optionsPath = $this->buildPath();
        $encodedImageUrl = rawurlencode($this->imageUrl);
        $path = $optionsPath . '/' . $encodedImageUrl;
        $signature = $this->sign($path);

        return rtrim($this->baseUrl, '/') . '/' . $signature . $path;
    }

    /**
     * Build the processing options path string.
     */
    private function buildPath(): string
    {
        if ($this->presetsOnly) {
            return $this->buildPresetsOnlyPath();
        }

        return $this->buildStandardPath();
    }

    /**
     * Build path in standard mode: /preset/name/key/value/format/ext
     */
    private function buildStandardPath(): string
    {
        $pathParts = [];

        // Add server presets first (they appear as /preset/name/ in the path)
        foreach ($this->serverPresets as $presetName) {
            $pathParts[] = 'preset';
            $pathParts[] = $presetName;
        }

        // Merge preset options with explicit options (explicit options override presets)
        $allOptions = array_merge($this->presetOptions, $this->processingOptions);

        foreach ($allOptions as $key => $value) {
            $pathParts[] = $key;
            $pathParts[] = (string) $value;
        }

        // Explicit extension takes precedence over preset extension
        $finalExtension = $this->extension ?? $this->presetExtension;
        if ($finalExtension !== null) {
            $pathParts[] = 'format';
            $pathParts[] = $finalExtension;
        }

        if (empty($pathParts)) {
            return '';
        }

        return '/' . implode('/', $pathParts);
    }

    /**
     * Build path in presets-only mode: /preset1:preset2:preset3
     */
    private function buildPresetsOnlyPath(): string
    {
        if (empty($this->serverPresets)) {
            return '';
        }

        return '/' . implode(':', $this->serverPresets);
    }

    /**
     * Generate HMAC-SHA256 signature.
     */
    private function sign(string $path): string
    {
        $key = @hex2bin($this->key);
        $salt = @hex2bin($this->salt);

        if ($key === false || $salt === false) {
            throw new InvalidUrlBuilderException('Invalid hex-encoded key or salt.');
        }

        $signature = hash_hmac('sha256', $path, $key, true);
        $signatureWithSalt = $salt . $signature;

        // URL-safe Base64 encoding without padding
        return rtrim(strtr(base64_encode($signatureWithSalt), '+/', '-_'), '=');
    }

    /**
     * Validate builder state before building.
     *
     * @throws InvalidUrlBuilderException
     */
    private function validate(): void
    {
        if ($this->imageUrl === null) {
            throw new InvalidUrlBuilderException('Image URL is required.');
        }

        if (empty($this->imageUrl)) {
            throw new InvalidUrlBuilderException('Image URL cannot be empty.');
        }

        if ($this->presetsOnly) {
            $this->validatePresetsOnly();
        }
    }

    /**
     * Validate constraints specific to presets-only mode.
     *
     * @throws InvalidUrlBuilderException
     */
    private function validatePresetsOnly(): void
    {
        if (empty($this->serverPresets)) {
            throw new InvalidUrlBuilderException(
                'At least one server preset is required in presets-only mode.'
            );
        }

        if (!empty($this->processingOptions)) {
            throw new InvalidUrlBuilderException(
                'Processing options are not allowed in presets-only mode. Use server presets instead.'
            );
        }

        if ($this->extension !== null) {
            throw new InvalidUrlBuilderException(
                'Extension is not allowed in presets-only mode. Configure the format in your server preset.'
            );
        }

        if (!empty($this->presetOptions) || $this->presetExtension !== null) {
            throw new InvalidUrlBuilderException(
                'Custom presets with options are not allowed in presets-only mode. Use server presets instead.'
            );
        }
    }

    /**
     * Reset the builder to its initial state.
     */
    public function reset(): self
    {
        $this->processingOptions = [];
        $this->extension = null;
        $this->imageUrl = null;
        $this->presetOptions = [];
        $this->presetExtension = null;
        $this->serverPresets = [];

        return $this;
    }
}
