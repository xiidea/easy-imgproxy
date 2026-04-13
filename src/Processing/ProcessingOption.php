<?php

declare(strict_types=1);

namespace Xiidea\EasyImgProxyBundle\Processing;

final class ProcessingOption
{
    /**
     * Maps full option name => [short_form, is_pro].
     *
     * @var array<string, array{string, bool}>
     */
    private const OPTIONS = [
        // Resizing / Sizing
        'resize'             => ['rs', false],
        'size'               => ['s', false],
        'resizing_type'      => ['rt', false],
        'resizing_algorithm' => ['ra', true],
        'width'              => ['w', false],
        'height'             => ['h', false],
        'min-width'          => ['mw', false],
        'min-height'         => ['mh', false],
        'zoom'               => ['z', false],
        'dpr'                => ['dpr', false],
        'enlarge'            => ['el', false],
        'extend'             => ['ex', false],
        'extend_aspect_ratio' => ['exar', true],

        // Positioning / Cropping
        'gravity'            => ['g', false],
        'objects_position'   => ['op', true],
        'crop'               => ['c', false],
        'crop_aspect_ratio'  => ['car', true],
        'trim'               => ['trim', false],
        'padding'            => ['pd', false],

        // Rotation / Orientation
        'auto_rotate'        => ['ar', false],
        'rotate'             => ['rot', false],
        'flip'               => ['fl', false],

        // Background
        'background'         => ['bg', false],
        'background_alpha'   => ['bga', true],

        // Color Adjustments (all Pro)
        'adjust'             => ['a', true],
        'brightness'         => ['br', true],
        'contrast'           => ['co', true],
        'saturation'         => ['sa', true],
        'monochrome'         => ['mc', true],
        'duotone'            => ['dt', true],

        // Filters / Effects
        'blur'               => ['bl', false],
        'sharpen'            => ['sh', false],
        'pixelate'           => ['pix', false],
        'unsharp_masking'    => ['ush', true],
        'blur_areas'         => ['ba', true],
        'blur_detections'    => ['bd', true],
        'draw_detections'    => ['dd', true],
        'colorize'           => ['col', true],
        'gradient'           => ['gr', true],

        // Watermark
        'watermark'          => ['wm', false],
        'watermark_url'      => ['wmu', true],
        'watermark_text'     => ['wmt', true],
        'watermark_size'     => ['wms', true],
        'watermark_rotate'   => ['wmr', true],
        'watermark_shadow'   => ['wmsh', true],

        // Style
        'style'              => ['st', true],

        // Metadata / Color Profile
        'strip_metadata'     => ['sm', false],
        'keep_copyright'     => ['kcr', false],
        'dpi'                => ['dpi', true],
        'strip_color_profile' => ['scp', false],
        'color_profile'      => ['icc', true],
        'enforce_thumbnail'  => ['eth', false],

        // Quality / Format
        'quality'            => ['q', false],
        'format_quality'     => ['fq', false],
        'autoquality'        => ['aq', true],
        'max_bytes'          => ['mb', false],
        'jpeg_options'       => ['jpgo', true],
        'png_options'        => ['pngo', true],
        'webp_options'       => ['webpo', true],
        'avif_options'       => ['avifo', true],
        'format'             => ['f', false],

        // Pages / Animation / Video (all Pro)
        'page'                       => ['pg', true],
        'pages'                      => ['pgs', true],
        'disable_animation'          => ['da', true],
        'video_thumbnail_second'     => ['vts', true],
        'video_thumbnail_keyframes'  => ['vtk', true],
        'video_thumbnail_tile'       => ['vtt', true],
        'video_thumbnail_animation'  => ['vta', true],

        // Fallback / Control
        'fallback_image_url'  => ['fiu', true],
        'skip_processing'     => ['skp', false],
        'raw'                 => ['raw', false],
        'cachebuster'         => ['cb', false],
        'expires'             => ['exp', false],
        'filename'            => ['fn', false],
        'return_attachment'   => ['att', false],
        'preset'              => ['pr', false],
        'hashsum'             => ['hs', true],

        // Source Limits
        'max_src_resolution'           => ['msr', false],
        'max_src_file_size'            => ['msfs', false],
        'max_animation_frames'         => ['maf', false],
        'max_animation_frame_resolution' => ['mafr', false],
        'max_result_dimension'         => ['mrd', false],
    ];

    /**
     * Reverse map: short form => full name (built lazily).
     *
     * @var array<string, string>|null
     */
    private static ?array $shortToFull = null;

    /**
     * Get the short form for a given option name.
     *
     * If the name is already a short form, returns it as-is.
     * If unknown, returns the name unchanged.
     */
    public static function shortName(string $name): string
    {
        if (isset(self::OPTIONS[$name])) {
            return self::OPTIONS[$name][0];
        }

        // Already a short form or unknown — return as-is
        return $name;
    }

    /**
     * Check if an option requires imgproxy Pro.
     *
     * Checks both full name and short form.
     */
    public static function isPro(string $name): bool
    {
        if (isset(self::OPTIONS[$name])) {
            return self::OPTIONS[$name][1];
        }

        // Check if it's a short form
        $fullName = self::resolveFullName($name);

        if ($fullName !== null) {
            return self::OPTIONS[$fullName][1];
        }

        return false;
    }

    /**
     * Check if the option name is a known imgproxy option.
     */
    public static function isKnown(string $name): bool
    {
        return isset(self::OPTIONS[$name]) || self::resolveFullName($name) !== null;
    }

    /**
     * Resolve a short form back to its full name.
     */
    public static function resolveFullName(string $shortName): ?string
    {
        if (self::$shortToFull === null) {
            self::$shortToFull = [];
            foreach (self::OPTIONS as $full => [$short]) {
                self::$shortToFull[$short] = $full;
            }
        }

        return self::$shortToFull[$shortName] ?? null;
    }

    /**
     * Get all known option names (full form).
     *
     * @return array<string>
     */
    public static function allNames(): array
    {
        return array_keys(self::OPTIONS);
    }

    /**
     * Get all pro-only option names (full form).
     *
     * @return array<string>
     */
    public static function proOptions(): array
    {
        return array_keys(array_filter(
            self::OPTIONS,
            static fn (array $meta): bool => $meta[1]
        ));
    }

    /**
     * Get all free option names (full form).
     *
     * @return array<string>
     */
    public static function freeOptions(): array
    {
        return array_keys(array_filter(
            self::OPTIONS,
            static fn (array $meta): bool => !$meta[1]
        ));
    }
}
