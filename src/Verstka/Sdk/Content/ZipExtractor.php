<?php

declare(strict_types=1);

namespace Verstka\Sdk\Content;

use ZipArchive;

final class ZipExtractor
{
    private const MEDIA_EXTENSIONS = [
        '.jpg', '.jpeg', '.png', '.gif', '.bmp', '.webp', '.svg', '.ico', '.avif',
        '.mp4', '.webm', '.ogv',
        '.mp3', '.wav', '.ogg', '.aac', '.m4a',
        '.pdf', '.txt',
        '.json', '.lottie',
    ];

    private const FONT_EXTENSIONS = [
        '.woff', '.woff2', '.ttf', '.otf', '.eot',
    ];

    public function extractContentZip(string $zipPath, string $tempDir): ExtractedContent
    {
        $mediaFiles = [];
        $vmsJsonContent = null;
        $vmsHtmlContent = null;
        $tempDirAbs = realpath($tempDir) ?: $tempDir;

        $zip = new ZipArchive();
        if ($zip->open($zipPath) !== true) {
            return new ExtractedContent([], null, null, $tempDirAbs);
        }

        for ($index = 0; $index < $zip->numFiles; $index++) {
            $stat = $zip->statIndex($index);
            if ($stat === false || str_ends_with($stat['name'], '/')) {
                continue;
            }

            $filename = $stat['name'];
            if (!$this->isSafeMember($filename)) {
                continue;
            }

            $normalized = str_replace('\\', '/', $filename);

            if ($normalized === 'vms_json.json') {
                $content = $zip->getFromIndex($index);
                if (is_string($content)) {
                    $vmsJsonContent = $content;
                }
                continue;
            }

            if ($normalized === 'vms_html.html') {
                $content = $zip->getFromIndex($index);
                if (is_string($content)) {
                    $vmsHtmlContent = $content;
                }
                continue;
            }

            if (!str_starts_with($normalized, 'vms_media/')) {
                continue;
            }

            $basename = basename($normalized);
            if ($basename === '') {
                continue;
            }

            $extension = strtolower(pathinfo($basename, PATHINFO_EXTENSION));
            if (!in_array('.' . $extension, self::MEDIA_EXTENSIONS, true)) {
                continue;
            }

            $extractedPath = $tempDirAbs . '/' . $basename;
            if (!$zip->extractTo($tempDirAbs, [$filename])) {
                continue;
            }

            $absolutePath = realpath($tempDirAbs . '/' . $filename) ?: ($tempDirAbs . '/' . $filename);
            if (!str_starts_with($absolutePath, $tempDirAbs)) {
                @unlink($absolutePath);
                continue;
            }

            $mediaFiles[$basename] = $absolutePath;
        }

        $zip->close();

        return new ExtractedContent($mediaFiles, $vmsJsonContent, $vmsHtmlContent, $tempDirAbs);
    }

    public function extractFontsZip(string $zipPath, string $tempDir): ExtractedFonts
    {
        $fontFiles = [];
        $vmsFontsJsonPath = null;
        $vmsFontsCssPath = null;
        $tempDirAbs = realpath($tempDir) ?: $tempDir;

        $zip = new ZipArchive();
        if ($zip->open($zipPath) !== true) {
            return new ExtractedFonts([], null, null, $tempDirAbs);
        }

        for ($index = 0; $index < $zip->numFiles; $index++) {
            $stat = $zip->statIndex($index);
            if ($stat === false || str_ends_with($stat['name'], '/')) {
                continue;
            }

            $filename = $stat['name'];
            if (!$this->isSafeMember($filename)) {
                continue;
            }

            $normalized = str_replace('\\', '/', $filename);
            $basename = basename($normalized);
            if ($basename === '') {
                continue;
            }

            if ($normalized === 'vms_fonts.json') {
                $zip->extractTo($tempDirAbs, [$filename]);
                $vmsFontsJsonPath = realpath($tempDirAbs . '/' . $filename) ?: ($tempDirAbs . '/' . $filename);
                continue;
            }

            if ($normalized === 'vms_fonts.css') {
                $zip->extractTo($tempDirAbs, [$filename]);
                $vmsFontsCssPath = realpath($tempDirAbs . '/' . $filename) ?: ($tempDirAbs . '/' . $filename);
                continue;
            }

            if (!str_starts_with($normalized, 'vms_fonts/')) {
                continue;
            }

            $extension = strtolower(pathinfo($basename, PATHINFO_EXTENSION));
            if (!in_array('.' . $extension, self::FONT_EXTENSIONS, true)) {
                continue;
            }

            $targetDir = $tempDirAbs . '/vms_fonts';
            if (!is_dir($targetDir)) {
                mkdir($targetDir, 0775, true);
            }

            $target = $targetDir . '/' . $basename;
            $content = $zip->getFromIndex($index);
            if (!is_string($content)) {
                continue;
            }
            file_put_contents($target, $content);

            $absolutePath = realpath($target) ?: $target;
            if (!str_starts_with($absolutePath, $tempDirAbs)) {
                @unlink($absolutePath);
                continue;
            }

            $fontFiles[$basename] = $absolutePath;
        }

        $zip->close();

        return new ExtractedFonts($fontFiles, $vmsFontsJsonPath, $vmsFontsCssPath, $tempDirAbs);
    }

    private function isSafeMember(string $name): bool
    {
        return !str_contains($name, '..') && !str_starts_with($name, '/');
    }
}
