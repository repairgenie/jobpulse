<?php

namespace App;

use Exception;

class PdfParserWrapper
{
    private $parser;

    public function __construct()
    {
        // Require composer autoload if it exists
        $autoloadPath = __DIR__ . '/../vendor/autoload.php';
        if (file_exists($autoloadPath)) {
            require_once $autoloadPath;
        }

        if (!class_exists('\Smalot\PdfParser\Parser')) {
            throw new Exception("Smalot/PdfParser not found. Please run 'composer require smalot/pdfparser'.");
        }

        $this->parser = new \Smalot\PdfParser\Parser();
    }

    public function extractText(string $filePath): string
    {
        if (!file_exists($filePath)) {
            throw new Exception("PDF file not found at path: $filePath");
        }

        $pdf = $this->parser->parseFile($filePath);
        return $pdf->getText();
    }
}
