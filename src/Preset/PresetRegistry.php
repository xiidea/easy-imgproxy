<?php

declare(strict_types=1);

namespace Xiidea\EasyImgProxyBundle\Preset;

use Xiidea\EasyImgProxyBundle\Exception\PresetNotFoundException;

/**
 * Registry for managing named presets.
 */
class PresetRegistry
{
    /** @var array<string, Preset> */
    private array $presets = [];

    /**
     * Register a preset.
     */
    public function register(string $name, Preset $preset): self
    {
        $this->presets[$name] = $preset;

        return $this;
    }

    /**
     * Register multiple presets at once.
     *
     * @param array<string, Preset> $presets
     */
    public function registerMany(array $presets): self
    {
        foreach ($presets as $name => $preset) {
            $this->register($name, $preset);
        }

        return $this;
    }

    /**
     * Get a preset by name.
     *
     * @throws PresetNotFoundException
     */
    public function get(string $name): Preset
    {
        if (!isset($this->presets[$name])) {
            throw new PresetNotFoundException(sprintf('Preset "%s" not found.', $name));
        }

        return $this->presets[$name];
    }

    /**
     * Check if a preset exists.
     */
    public function has(string $name): bool
    {
        return isset($this->presets[$name]);
    }

    /**
     * Get all registered presets.
     *
     * @return array<string, Preset>
     */
    public function all(): array
    {
        return $this->presets;
    }

    /**
     * Get all preset names.
     *
     * @return array<string>
     */
    public function names(): array
    {
        return array_keys($this->presets);
    }

    /**
     * Create a registry from an array of presets.
     *
     * @param array<string, Preset> $presets
     */
    public static function fromArray(array $presets): self
    {
        $registry = new self();
        $registry->registerMany($presets);

        return $registry;
    }
}
