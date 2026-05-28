<?php

namespace App;

use Exception;

/**
 * Convert PDF pages to base64-encoded PNG images, then optionally
 * call a vision-capable LLM to produce a Markdown transcription.
 *
 * Uses pdftoppm (Poppler) for rasterisation — no Imagick required.
 *
 * Usage:
 *   $markdown = PdfToMarkdown::convert('/path/to/file.pdf');
 *   $markdown = PdfToMarkdown::convert('/path/to/file.pdf', $modelName);
 */
class PdfToMarkdown
{
    /**
     * Convert a PDF to a Markdown string using a vision model.
     *
     * @param string $pdfPath     Absolute path to the PDF
     * @param string $modelName   Model name override (default: from config)
     * @return string             Extracted Markdown text
     */
    public static function convert(string $pdfPath, string $modelName = ''): string
    {
        $images = PdfToImages::toBase64Images($pdfPath);

        if (empty($images)) {
            throw new Exception("No pages could be extracted from PDF");
        }

        $llm = new LLMProvider();
        $md = $llm->visionChat(
            'You are an expert at reading and transcribing professional resumes and documents. '
            . 'Output ONLY the complete text content transcribed as clean Markdown. '
            . 'Preserve all headings, bullet points, contact info, dates, and technical details exactly as they appear. '
            . 'Do NOT add commentary, introductions, or notes — only the raw Markdown transcription.',
            $images,
            $modelName
        );

        return $md;
    }
}
