<?php

/**
 * Extracts the validated back-office mockups into readable files.
 *
 * The mockups in docs/references are self-extracting bundles: a manifest of
 * base64 resources (gzip-compressed for scripts) and an HTML template that
 * references them by identifier. This script decodes them next to the
 * originals, which are only read, never modified.
 *
 * Usage: php docs/references/tools/extract-mockups.php
 */
$source = dirname(__DIR__);
$target = $source.'/extracted';

$mockups = [
    'Allo Vaho Back-office Connexion.html' => 'connexion',
    'Allo Vaho Back-office Dashboard.html' => 'dashboard',
    'Allo Vaho Back-office Activation.html' => 'activation',
];

$extensions = [
    'image/png' => 'png',
    'image/jpeg' => 'jpg',
    'image/svg+xml' => 'svg',
    'font/woff2' => 'woff2',
    'font/woff' => 'woff',
    'text/css' => 'css',
    'text/javascript' => 'js',
    'application/javascript' => 'js',
];

$block = function (string $html, string $type): string {
    $open = '<script type="__bundler/'.$type.'">';
    $start = strpos($html, $open);

    if ($start === false) {
        throw new RuntimeException("Block [{$type}] not found.");
    }

    $start += strlen($open);

    return trim(substr($html, $start, strpos($html, '</script>', $start) - $start));
};

foreach ($mockups as $file => $name) {
    $html = file_get_contents($source.'/'.$file);
    $manifest = json_decode($block($html, 'manifest'), true, flags: JSON_THROW_ON_ERROR);
    $template = json_decode($block($html, 'template'), flags: JSON_THROW_ON_ERROR);

    $directory = $target.'/'.$name;
    is_dir($directory.'/assets') || mkdir($directory.'/assets', 0777, true);

    foreach ($manifest as $id => $entry) {
        $bytes = base64_decode($entry['data'], true);

        if ($entry['compressed']) {
            $bytes = gzdecode($bytes);
        }

        if ($bytes === false) {
            throw new RuntimeException("Resource [{$id}] of [{$file}] could not be decoded.");
        }

        $path = 'assets/'.$id.'.'.($extensions[$entry['mime']] ?? 'bin');

        file_put_contents($directory.'/'.$path, $bytes);

        $template = str_replace($id, $path, $template);
    }

    // Same clean-up as the bundle loader: local files cannot satisfy these.
    $template = preg_replace('/\s+(integrity|crossorigin)="[^"]*"/i', '', $template);

    file_put_contents($directory.'/index.html', $template);

    printf("%-12s %2d resources, index.html %d bytes\n", $name, count($manifest), strlen($template));
}
