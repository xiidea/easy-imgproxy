<?php

declare(strict_types=1);

namespace Xiidea\EasyImgProxyBundle\Builder;

use Xiidea\EasyImgProxyBundle\Exception\InvalidUrlBuilderException;
use Xiidea\EasyImgProxyBundle\Preset\Preset;
use Xiidea\EasyImgProxyBundle\Preset\PresetRegistry;
use Xiidea\EasyImgProxyBundle\Processing\ProcessingOption;

class UrlBuilder
{
    /** @var array<string, mixed> */
    private array $processingOptions = [];

    private ?string $extension = null;

    private ?string $imageUrl = null;

    private ?PresetRegistry $presetRegistry = null;

    /** @var array<string, mixed> */
    private array $presetOptions = [];

    private ?string $presetExtension = null;

    /** @var array<string> */
    private array $serverPresets = [];

    private bool $presetsOnly;

    private bool $enablePro;

    public function __construct(
        private readonly string $key,
        private readonly string $salt,
        private readonly string $baseUrl,
        ?PresetRegistry $presetRegistry = null,
        bool $presetsOnly = false,
        bool $enablePro = false,
    ) {
        $this->presetRegistry = $presetRegistry;
        $this->presetsOnly = $presetsOnly;
        $this->enablePro = $enablePro;
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
     * Set the output image extension/format (e.g., webp, png, jpg).
     * Appended as .{ext} suffix on the encoded image URL.
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
     * Add a processing option.
     *
     * Accepts both full names (e.g., 'width') and short forms (e.g., 'w').
     * Options are always rendered using their short form in the URL.
     */
    public function withOption(string $key, mixed $value): self
    {
        $this->processingOptions[$key] = $value;

        return $this;
    }

    /**
     * Apply a preset by name from the registry.
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
     * @param array<string> $presetNames
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
     * URL format becomes: /{signature}/{preset1:preset2}/{encoded_url}.{ext}
     */
    public function usePresetsOnly(bool $presetsOnly = true): self
    {
        $this->presetsOnly = $presetsOnly;

        return $this;
    }

    /**
     * Enable or disable imgproxy Pro options.
     *
     * When disabled, pro options are silently dropped with a trigger_error warning.
     */
    public function enablePro(bool $enablePro = true): self
    {
        $this->enablePro = $enablePro;

        return $this;
    }

    /**
     * Build and return the complete signed imgproxy URL.
     *
     * @throws InvalidUrlBuilderException
     */
    public function build(): string
    {
        $this->validate();

        $optionsPath = $this->buildPath();

        // Encode source URL as URL-safe Base64 (no padding)
        $encodedUrl = rtrim(strtr(base64_encode($this->imageUrl), '+/', '-_'), '=');

        // Extension is appended as .ext suffix
        $extension = $this->resolveExtension();
        $extensionSuffix = $extension !== null ? '.' . $extension : '';

        $path = $optionsPath . '/' . $encodedUrl . $extensionSuffix;
        $signature = $this->sign($path);

        return rtrim($this->baseUrl, '/') . '/' . $signature . $path;
    }

    /**
     * Resolve the final extension.
     *
     * Priority: explicit withExtension() > preset extension > image URL path extension.
     */
    private function resolveExtension(): ?string
    {
        return $this->extension
            ?? $this->presetExtension
            ?? $this->extractExtensionFromUrl();
    }

    /**
     * Extract file extension from the image URL path.
     */
    private function extractExtensionFromUrl(): ?string
    {
        $path = parse_url($this->imageUrl, PHP_URL_PATH);

        if ($path === null || $path === false) {
            return null;
        }

        $extension = pathinfo($path, PATHINFO_EXTENSION);

        return $extension !== '' ? $extension : null;
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
     * Build path in standard mode using short option names.
     *
     * Server presets: /pr:name1:name2
     * Options: /short_key:value (e.g., /w:200/h:300)
     */
    private function buildStandardPath(): string
    {
        $segments = [];

        // Server presets as a single "pr:name1:name2" segment
        if (!empty($this->serverPresets)) {
            $segments[] = 'pr:' . implode(':', $this->serverPresets);
        }

        // Merge preset options with explicit options (explicit override preset)
        $allOptions = array_merge($this->presetOptions, $this->processingOptions);

        foreach ($allOptions as $key => $value) {
            if (!$this->enablePro && ProcessingOption::isPro($key)) {
                @trigger_error(
                    sprintf(
                        'imgproxy Pro option "%s" ignored: enable_pro is false. '
                        . 'Set enable_pro to true in your configuration to use this option.',
                        $key
                    ),
                    E_USER_WARNING
                );
                continue;
            }

            $shortKey = ProcessingOption::shortName($key);
            $segments[] = $shortKey . ':' . $value;
        }

        if (empty($segments)) {
            return '';
        }

        return '/' . implode('/', $segments);
    }

    /**
     * Build path in presets-only mode: /preset1:preset2
     */
    private function buildPresetsOnlyPath(): string
    {
        if (empty($this->serverPresets)) {
            return '';
        }

        return '/' . implode(':', $this->serverPresets);
    }

    /**
     * Generate HMAC-SHA256 signature per imgproxy spec.
     *
     * Signs: HMAC-SHA256(key, salt + path)
     * Output: URL-safe Base64 without padding.
     */
    private function sign(string $path): string
    {
        $key = @hex2bin($this->key);
        $salt = @hex2bin($this->salt);

        if ($key === false || $salt === false) {
            throw new InvalidUrlBuilderException('Invalid hex-encoded key or salt.');
        }

        // Salt is prepended to the path as HMAC input data
        $signature = hash_hmac('sha256', $salt . $path, $key, true);

        return rtrim(strtr(base64_encode($signature), '+/', '-_'), '=');
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

        if (!empty($this->presetOptions) || $this->presetExtension !== null) {
            throw new InvalidUrlBuilderException(
                'Custom presets with options are not allowed in presets-only mode. Use server presets instead.'
            );
        }
    }

    /**
     * Reset the builder to its initial state (preserves mode, pro flag, and credentials).
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
