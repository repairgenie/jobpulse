<?php

namespace App;

use Exception;

/**
 * Convert PDF pages to base64-encoded PNG images.
 * Uses pdftoppm (Poppler) for rasterization — no Imagick required.
 */
class PdfToImages
{
    /**
     * Convert a PDF file to an array of base64 PNG image strings.
     *
     * @param string $pdfPath  Absolute path to the PDF file
     * @param int    $dpi      Resolution (default 150 — good balance for vision OCR)
     * @return string[]        Array of base64-encoded PNG image data (no prefix)
     */
    public static function toBase64Images(string $pdfPath, int $dpi = 150): array
    {
        if (!file_exists($pdfPath)) {
            throw new Exception("PDF file not found: $pdfPath");
        }

        $tmpDir = sys_get_temp_dir() . '/pdf2img_' . uniqid();
        mkdir($tmpDir, 0755, true);

        // pdftoppm dumps pages as page-1.png, page-2.png, ...
        $prefix = $tmpDir . '/page';
        $cmd = sprintf(
            'pdftoppm -r %d -png %s %s 2>&1',
            $dpi,
            escapeshellarg($pdfPath),
            escapeshellarg($prefix)
        );

        $output = [];
        $exitCode = 0;
        exec($cmd, $output, $exitCode);

        if ($exitCode !== 0) {
            self::cleanup($tmpDir);
            throw new Exception("pdftoppm failed: " . implode("\n", $output));
        }

        $files = glob($tmpDir . '/page-*.png');
        sort($files);

        if (empty($files)) {
            self::cleanup($tmpDir);
            throw new Exception("pdftoppm produced no output images");
        }

        $images = [];
        foreach ($files as $f) {
            $images[] = base64_encode(file_get_contents($f));
        }

        self::cleanup($tmpDir);
        return $images;
    }

    private static function cleanup(string $tmpDir): void
    {
        array_map('unlink', glob($tmpDir . '/*'));
        @rmdir($tmpDir);
    }
}
