# WebLiveHub PHP SDK

[![Latest Version]](https://github.com/weblivehub/sdk-php)

Official PHP SDK for WebLiveHub - A powerful WebRTC live streaming and video-on-demand platform.

## 📋 Table of Contents

- [Features](#-features)
- [Requirements](#-requirements)
- [Installation](#-installation)
- [Quick Start](#-quick-start)
- [Usage Examples](#-usage-examples)
  - [Immediate Iframe Embed](#1-immediate-iframe-embed)
  - [Lazy-Loading Iframe](#2-lazy-loading-iframe)
  - [Custom Attributes & Events](#3-custom-attributes--events)
- [Security Considerations](#-security-considerations)
- [Support](#-support)

## ✨ Features

- **🚀 Simple Integration**: One-line setup with Composer
- **🔐 Secure Authentication**: Built-in credential management and token handling
- **⚡ Multiple Embed Modes**: Immediate and lazy-loading iframe support
- **🎨 Customizable**: Flexible attributes
- **🌐 CDN Support**: Configurable CDN endpoints for optimal performance
- **📱 Responsive**: Works seamlessly across devices
- **🔄 Auto-Retry**: Built-in error handling and fallback mechanisms
- **🏢 Hosted Backend Support**: Automatic slug detection for managed deployments

## 📦 Requirements

- PHP 7.4 or higher
- cURL extension (recommended) or `allow_url_fopen` enabled
- Valid WebLiveHub account with Hosted Backend credentials

## 🔧 Installation

Install via Composer:

```bash
composer require weblivehub/sdk
```

Or add to your `composer.json`:

```json
{
  "require": {
    "weblivehub/sdk": "^1.0.2"
  }
}
```

Then run:

```bash
composer install
```

## 🚀 Quick Start

### Step 1: Create a Hosted Backend

1. Log in to your [WebLiveHub Console](https://console.weblivehub.com)
2. Navigate to **Hosted Backends** → **Create Backend**
3. Copy your **Hosted Backend Endpoint** (e.g., `https://console.weblivehub.com/WL_HOST/{slug}/wl_api/backend.php`)

### Step 2: Basic Setup

```php
<?php
require_once 'vendor/autoload.php';

use WebLiveHub\SDK\WLSDK;

// Configure SDK
WLSDK::setup([
    'hb_endpoint' => 'https://console.weblivehub.com/WL_HOST/{your-slug}/wl_api/backend.php',
    'user_id' => 'client_user_id',      // Your WebLiveHub user ID
    'password' => 'client_password',    // Your WebLiveHub password
]);

// Output the SDK script tag (required once per page)
echo WLSDK::script();

// Embed a live stream
echo WLSDK::iframe([
    'hostLabel' => 'live',            // Role: 'live' (streamer) or 'live-client' (viewer)
    'streamer' => 'streamer_user_id'  // The streamer's user ID
]);
?>
```

## ⚙️ Configuration

### Basic Configuration

```php
WLSDK::setup([
    'hb_endpoint' => 'https://your-backend-endpoint/backend.php',  // Required
    'user_id' => 'client_user_id',                                   // Required
    'password' => 'client_password',                                 // Required
    'debug' => false,                                              // Optional: Enable debug mode
]);
```

## 📚 Usage Examples

### 1. Immediate Iframe Embed

Fetches a stream token immediately on the server-side and embeds the iframe:

```php
<?php
use WebLiveHub\SDK\WLSDK;

// Setup (do this once)
WLSDK::setup([
    'hb_endpoint' => getenv('WEBLIVEHUB_ENDPOINT'),
    'user_id' => 'client_user_id',
    'password' => 'client_password',
]);

// Output script tag (once per page)
echo WLSDK::script();

// Embed streamer view (broadcaster interface)
echo WLSDK::iframe([
    'hostLabel' => 'live',
    'streamer' => 'streamer_user_id',  // Streamer's user ID
    'attrs' => [
        'id' => 'my-stream',
        'style' => 'width: 100%; height: 600px; border: none;',
        'data-room' => 'room123'
    ]
]);

// Embed viewer view (audience interface)
echo WLSDK::iframe([
    'hostLabel' => 'live-client',
    'streamer' => 'streamer_user_id',  // Watch this streamer
]);
?>
```

### 2. Lazy-Loading Iframe

Client-side token fetching for dynamic multi-stream pages:

```php
<?php
use WebLiveHub\SDK\WLSDK;

WLSDK::setup([
    'hb_endpoint' => getenv('WEBLIVEHUB_ENDPOINT'),
    'user_id' => 'client_user_id',
    'password' => 'client_password',
]);

echo WLSDK::script();

// Lazy-load multiple streams (tokens fetched on-demand)
foreach ($streamers as $streamer_id) {
    echo WLSDK::lazyIframe([
        'hostLabel' => 'live-client',
        'streamer' => $streamer_id,
        'attrs' => [
            'class' => 'lazy-stream',
            'data-streamer' => $streamer_id
        ]
    ]);
}
?>
```

**Benefits of Lazy-Loading:**
- Reduces initial server load
- Faster page rendering
- Tokens fetched only when needed
- Ideal for multi-stream galleries

### 3. Custom Attributes & Events

Add custom HTML attributes and JavaScript event handlers:

```php
<?php
use WebLiveHub\SDK\WLSDK;

WLSDK::setup([/* ... */]);
echo WLSDK::script();

echo WLSDK::iframe([
    'hostLabel' => 'live',
    'streamer' => 'streamer_user_id',
    'attrs' => [
        'id' => 'main-stream',
        'class' => 'featured-stream border-2',
        'style' => 'width: 100%; height: 500px;',
        'data-analytics-id' => 'stream-001',
        'data-category' => 'gaming'
    ]
]);
?>

<div id="viewer-count">0</div>
```

---

### `WLSDK::setup(array $config): bool`

Initialize SDK configuration.

**Parameters:**
- `$config` (array): Configuration array
  - `hb_endpoint` (string, required): Hosted Backend endpoint URL
  - `user_id` (string, required): User ID for authentication
  - `password` (string, required): User password
  - `authToken` (string, optional): Pre-fetched auth token

**Returns:** `bool` - Always returns `true`

**Example:**
```php
WLSDK::setup([
    'hb_endpoint' => 'https://console.weblivehub.com/WL_HOST/abc123.../backend.php',
    'user_id' => 'client_user_id',
    'password' => 'client_password'
]);
```

---

### `WLSDK::script(): string`

Generate the `<script>` tag to load the embed.js library.

**Returns:** `string` - HTML script tag

**Example:**
```php
echo WLSDK::script();
// Output: <script src="https://console.weblivehub.com/sdk-assets/v1_0_2/js/embed.js"></script>
```

**Note:** Call this once per page, typically in the `<head>` or before any iframe embeds.

---

### `WLSDK::iframe(array $opts): string`

Generate an immediate iframe embed (server-side token fetch).

**Parameters:**
- `$opts` (array): Embed options
  - `hostLabel` (string, required): Role identifier (e.g., `'live'`, `'live-client'`, `'connect'`)
  - `streamer` (string, required): Streamer's user ID
  - `id` (string, optional): DOM element ID (auto-generated if not provided)
  - `attrs` (array, optional): Custom HTML attributes

**Returns:** `string` - HTML output (`<wl-stream>` element + optional event script)

**Example:**
```php
echo WLSDK::iframe([
    'hostLabel' => 'live',
    'streamer' => 'streamer_user_id',
    'id' => 'my-custom-id',
    'attrs' => [
        'style' => 'width: 800px; height: 600px;',
        'data-room' => 'vip-room'
    ]
]);
```
---

### `WLSDK::lazyIframe(array $opts): string`

Generate a lazy-loading iframe embed (client-side token fetch).

**Parameters:**
- `$opts` (array): Embed options
  - `hostLabel` (string, required): Role identifier
  - `streamer` (string, required): Streamer's user ID
  - `authToken` (string, optional): Override auth token
  - `hb_endpoint` (string, optional): Override hosted backend endpoint
  - `attrs` (array, optional): Custom HTML attributes

**Returns:** `string` - HTML output (`<wl-stream-lazy>` element)

**Example:**
```php
echo WLSDK::lazyIframe([
    'hostLabel' => 'live-client',
    'streamer' => 'streamer_user_id',
    'attrs' => [
        'class' => 'lazy-load',
        'loading' => 'lazy'
    ]
]);
```

---

### Custom CSS Styling

```php
<style>
wl-stream, wl-stream-lazy {
    display: block;
    width: 100%;
    max-width: 1200px;
    margin: 0 auto;
    border-radius: 8px;
    overflow: hidden;
    box-shadow: 0 4px 6px rgba(0,0,0,0.1);
}

.wl-stream-error {
    padding: 20px;
    background: #fee;
    border: 1px solid #fcc;
    border-radius: 4px;
    color: #c33;
    text-align: center;
}
</style>
```

## 🔒 Security Considerations

### ⚠️ Never Expose Credentials in Frontend

```php
// ❌ WRONG - Never do this!
<script>
const config = {
    user_id: '<?php echo $client_user_id; ?>',
    password: '<?php echo $client_password; ?>'  // SECURITY RISK!
};
</script>

// ✅ CORRECT - Keep credentials server-side only
<?php
WLSDK::setup([
    'hb_endpoint' => getenv('WEBLIVEHUB_ENDPOINT'),
    'user_id' => 'client_user_id',
    'password' => 'client_password',
]);
echo WLSDK::iframe(['hostLabel' => 'live', 'streamer' => 'streamer_user_id']);
?>
```

## 🎯 Host Labels Guide

| Label | Role | Description |
|-------|------|-------------|
| `live` | Streamer | Broadcaster interface with streaming controls |
| `live-client` | Viewer | Audience view without streaming capabilities |
| `connect` | Test/Demo | General-purpose testing interface |

**Note:** Backend configuration determines available roles and permissions for each label.

## 🤝 Support
- **Email:** support@weblivehub.com
- **Issues:** [GitHub Issues](https://github.com/weblivehub/sdk-php/issues)

## 🔄 Changelog

### v1.0.2 (Current)
- Hosted Backend slug detection
- Versioned asset path support with fallback
- Enhanced error handling
- Improved CDN configuration

---

Made with ❤️ by [WebLiveHub](https://weblivehub.com)
