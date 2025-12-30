<?php
/**
 * Vite Helper
 * Handles asset loading for development and production builds
 *
 * @package VisaChatbot
 * @version 1.0.0
 */

/**
 * Check if we're in development mode (Vite dev server running)
 *
 * @return bool
 */
function isViteDev(): bool {
    // Check for dev mode flag
    if (defined('VITE_DEV') && VITE_DEV) {
        return true;
    }

    // Check if Vite dev server is running
    $vitePort = 3000;
    $connection = @fsockopen('localhost', $vitePort, $errno, $errstr, 0.1);

    if ($connection) {
        fclose($connection);
        return true;
    }

    return false;
}

/**
 * Get the Vite manifest
 *
 * @return array|null
 */
function getViteManifest(): ?array {
    static $manifest = null;

    if ($manifest !== null) {
        return $manifest;
    }

    $manifestPath = CHATBOT_ROOT . '/dist/manifest.json';

    if (file_exists($manifestPath)) {
        $content = file_get_contents($manifestPath);
        $manifest = json_decode($content, true);
        return $manifest;
    }

    // Try PHP manifest
    $phpManifestPath = CHATBOT_ROOT . '/dist/manifest.php';
    if (file_exists($phpManifestPath)) {
        $manifest = require $phpManifestPath;
        return $manifest;
    }

    return null;
}

/**
 * Get Vite asset URL
 *
 * @param string $entry Entry name (e.g., 'main', 'styles')
 * @return string|null
 */
function viteAsset(string $entry): ?string {
    if (isViteDev()) {
        // Development: use Vite dev server
        $devServerUrl = 'http://localhost:3000';

        if ($entry === 'main') {
            return $devServerUrl . '/js/modules/index.js';
        }
        if ($entry === 'styles') {
            return $devServerUrl . '/css/main.css';
        }

        return $devServerUrl . '/' . $entry;
    }

    // Production: use built assets
    $manifest = getViteManifest();

    if ($manifest && isset($manifest[$entry])) {
        $file = $manifest[$entry]['file'] ?? $manifest[$entry];
        return url('dist/' . $file);
    }

    return null;
}

/**
 * Render Vite script tag
 *
 * @param string $entry Entry name
 * @param array $attributes Additional attributes
 * @return string
 */
function viteScript(string $entry, array $attributes = []): string {
    $url = viteAsset($entry);

    if (!$url) {
        return '';
    }

    $attrs = ['type' => 'module', 'src' => $url];
    $attrs = array_merge($attrs, $attributes);

    $attrStr = '';
    foreach ($attrs as $key => $value) {
        $attrStr .= ' ' . htmlspecialchars($key) . '="' . htmlspecialchars($value) . '"';
    }

    $output = '<script' . $attrStr . '></script>';

    // In dev mode, add Vite client
    if (isViteDev()) {
        $output = '<script type="module" src="http://localhost:3000/@vite/client"></script>' . "\n" . $output;
    }

    return $output;
}

/**
 * Render Vite stylesheet link
 *
 * @param string $entry Entry name
 * @param array $attributes Additional attributes
 * @return string
 */
function viteStyles(string $entry, array $attributes = []): string {
    // In dev mode, styles are injected by Vite
    if (isViteDev()) {
        return '';
    }

    $url = viteAsset($entry);

    if (!$url) {
        return '';
    }

    $attrs = ['rel' => 'stylesheet', 'href' => $url];
    $attrs = array_merge($attrs, $attributes);

    $attrStr = '';
    foreach ($attrs as $key => $value) {
        $attrStr .= ' ' . htmlspecialchars($key) . '="' . htmlspecialchars($value) . '"';
    }

    return '<link' . $attrStr . '>';
}

/**
 * Render all Vite tags for an entry
 * Includes both CSS and JS
 *
 * @param string $entry Entry name
 * @return string
 */
function vite(string $entry = 'main'): string {
    $output = '';

    // Styles (only in production, dev injects via JS)
    $output .= viteStyles($entry);

    // Script
    $output .= viteScript($entry);

    // CSS files associated with the entry
    if (!isViteDev()) {
        $manifest = getViteManifest();
        if ($manifest && isset($manifest[$entry]['css'])) {
            foreach ($manifest[$entry]['css'] as $cssFile) {
                $output .= '<link rel="stylesheet" href="' . url('dist/' . $cssFile) . '">';
            }
        }
    }

    return $output;
}

/**
 * Render preload hints for better performance
 *
 * @return string
 */
function vitePreload(): string {
    if (isViteDev()) {
        return '';
    }

    $manifest = getViteManifest();
    if (!$manifest) {
        return '';
    }

    $output = '';

    foreach ($manifest as $entry => $info) {
        if (is_array($info) && isset($info['file'])) {
            $file = $info['file'];

            if (str_ends_with($file, '.js')) {
                $output .= '<link rel="modulepreload" href="' . url('dist/' . $file) . '">' . "\n";
            } elseif (str_ends_with($file, '.css')) {
                $output .= '<link rel="preload" href="' . url('dist/' . $file) . '" as="style">' . "\n";
            }
        }
    }

    return $output;
}
