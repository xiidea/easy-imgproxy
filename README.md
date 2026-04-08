# Easy ImgProxy Bundle

[![Tests](https://github.com/xiidea/easy-imgproxy/actions/workflows/tests.yml/badge.svg)](https://github.com/xiidea/easy-imgproxy/actions/workflows/tests.yml)
[![codecov](https://codecov.io/gh/xiidea/easy-imgproxy/graph/badge.svg)](https://codecov.io/gh/xiidea/easy-imgproxy)

A Symfony Bundle for generating secure, signed URLs for the [imgproxy](https://imgproxy.net) service.

## Features

- Clean, fluent Builder pattern API
- HMAC-SHA256 signing with URL-safe Base64 encoding
- Full Symfony integration with Dependency Injection
- Comprehensive test coverage
- PHP 8.1+ support

## Installation

```bash
composer require xiidea/easy-imgproxy-bundle
```

## Configuration

Add the bundle to your `config/bundles.php`:

```php
return [
    // ...
    Xiidea\EasyImgProxyBundle\XiideaEasyImgProxyBundle::class => ['all' => true],
];
```

Create `config/packages/xiidea_easy_img_proxy.yaml`:

```yaml
xiidea_easy_img_proxy:
  key: '%env(IMGPROXY_KEY)%'
  salt: '%env(IMGPROXY_SALT)%'
  base_url: '%env(IMGPROXY_BASE_URL)%'
```

Add to `.env`:

```env
# Hex-encoded 32-byte key
IMGPROXY_KEY=0123456789abcdef0123456789abcdef0123456789abcdef0123456789abcdef

# Hex-encoded 16-byte salt
IMGPROXY_SALT=0123456789abcdef0123456789abcdef

# imgproxy service URL
IMGPROXY_BASE_URL=http://localhost:8080
```

## Usage

### Using the Builder Pattern

```php
use Xiidea\EasyImgProxyBundle\Service\ImgProxyUrlGenerator;

// Inject the service
public function __construct(ImgProxyUrlGenerator $generator)
{
    $this->generator = $generator;
}

// Build a URL
$url = $this->generator->builder()
    ->withImageUrl('https://example.com/image.jpg')
    ->withWidth(200)
    ->withHeight(300)
    ->withQuality(80)
    ->withExtension('webp')
    ->build();
```

### Using Inline Generation

```php
$url = $this->generator->generate(
    'https://example.com/image.jpg',
    [
        'width' => 200,
        'height' => 300,
        'quality' => 80,
        'gravity' => 'center',
    ],
    'webp' // optional extension
);
```

### Available Options

**Dimension Options:**
- `withWidth(int)` - Image width in pixels
- `withHeight(int)` - Image height in pixels
- `withResizing(string)` - Resizing type: `fit`, `fill`, `auto`, `force`
- `withGravity(string)` - Gravity: `center`, `north`, `south`, `east`, `west`, etc.
- `withQuality(int)` - JPEG quality: 0-100

**Format Option:**
- `withExtension(string)` - Output format: `webp`, `png`, `jpg`, `gif`, etc.

**Custom Options:**
- `withOption(string $key, mixed $value)` - Add any custom processing option

## Presets

The bundle supports two types of presets:

### 1. Custom Presets (Defined in Configuration)

Define reusable configurations in `config/packages/xiidea_easy_img_proxy.yaml`:

```yaml
xiidea_easy_img_proxy:
  key: '%env(IMGPROXY_KEY)%'
  salt: '%env(IMGPROXY_SALT)%'
  base_url: '%env(IMGPROXY_BASE_URL)%'

  presets:
    thumbnail:
      options:
        width: 200
        height: 200
        resizing_type: fill
        gravity: center
        quality: 85
      extension: webp

    hero:
      options:
        width: 1200
        height: 400
        resizing_type: fill
        quality: 90
      extension: jpg

    product:
      options:
        width: 600
        quality: 90
```

Use custom presets in your code:

```php
// Apply a single preset
$url = $this->generator->builder()
    ->withImageUrl('https://example.com/image.jpg')
    ->withPreset('thumbnail')
    ->build();

// Apply multiple presets (later ones override earlier ones)
$url = $this->generator->builder()
    ->withImageUrl('https://example.com/image.jpg')
    ->withPresets(['product', 'quality'])
    ->build();

// Override preset options with explicit values
$url = $this->generator->builder()
    ->withImageUrl('https://example.com/image.jpg')
    ->withPreset('thumbnail')
    ->withQuality(95)  // Overrides preset quality
    ->withExtension('png')  // Overrides preset extension
    ->build();
```

### 2. Server Presets (Defined in imgproxy)

Apply presets defined on the imgproxy server:

```php
// Apply a single server preset
$url = $this->generator->builder()
    ->withImageUrl('https://example.com/image.jpg')
    ->withServerPreset('blurry')
    ->build();

// Apply server preset with parameters
$url = $this->generator->builder()
    ->withImageUrl('https://example.com/image.jpg')
    ->withServerPreset('blur:strong')
    ->build();

// Apply multiple server presets
$url = $this->generator->builder()
    ->withImageUrl('https://example.com/image.jpg')
    ->withServerPresets(['sharpen', 'quality:high'])
    ->build();
```

### Combining Custom and Server Presets

Both preset types can be used together:

```php
$url = $this->generator->builder()
    ->withImageUrl('https://example.com/image.jpg')
    ->withServerPreset('blur')          // imgproxy server preset
    ->withPreset('product')             // custom preset
    ->withQuality(90)                   // explicit option (highest priority)
    ->build();
```

**Priority Order** (highest to lowest):
1. Explicitly set options (e.g., `withWidth(300)`)
2. Custom preset options
3. Server presets (imgproxy-side)

### URL Structure

Generated URLs follow the imgproxy format:

```
{BASE_URL}/{SIGNATURE}/{PROCESSING_PATH}/{IMAGE_URL}
```

Example:
```
http://localhost:8080/UtBg7s3YMkw5-gP...bQ/width/200/height/300/format/webp/https://example.com/image.jpg
```

## Testing

Run the test suite:

```bash
vendor/bin/phpunit
```

## Security

The bundle correctly implements imgproxy's signing specification:

1. Processes all options into a URL path
2. Signs the path using HMAC-SHA256 with the provided key
3. Prepends the salt to the signature
4. Encodes using URL-safe Base64 without padding
5. Builds the final signed URL

## License

MIT
