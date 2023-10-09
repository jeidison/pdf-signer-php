<?php

namespace Jeidison\PdfSigner;

use Exception;
use Jeidison\PdfSigner\Xref\Xref;

class Struct
{
    private PdfDocument $pdfDocument;
    private ?int $depth = null;
    private string $separator = "\r\n";
    private const REGEX_PDF_VERSION = '/^%PDF-\d+\.\d+$/';

    public static function new(): static
    {
        return new static();
    }

    public function withPdfDocument(PdfDocument $pdfDocument): self
    {
        $this->pdfDocument = $pdfDocument;

        return $this;
    }

    public function withDepth(?int $depth): self
    {
        $this->depth = $depth;

        return $this;
    }

    public function structure(): array
    {
        $pdfVersion = strtok($this->pdfDocument->getBuffer()->raw(), $this->separator);
        if ($pdfVersion === false) {
            throw new Exception("Failed to get PDF version");
        }

        if (preg_match(self::REGEX_PDF_VERSION, $pdfVersion, $matches) !== 1) {
            throw new Exception('PDF version not found');
        }

        if (preg_match_all('/startxref\s*([0-9]+)\s*%%EOF($|[\r\n])/ms', $this->pdfDocument->getBuffer()->raw(), $matches, PREG_SET_ORDER | PREG_OFFSET_CAPTURE) === false) {
            throw new Exception('Failed to get structure');
        }

        $versions = [];
        foreach ($matches as $match) {
            $versions[] = intval($match[2][1]) + strlen($match[2][0]);
        }

        $startXRefPos = strrpos($this->pdfDocument->getBuffer()->raw(), 'startxref');
        if ($startXRefPos === false) {
            throw new Exception('startxref not found');
        }

        if (preg_match('/startxref\s*([0-9]+)\s*%%EOF\s*$/ms', $this->pdfDocument->getBuffer()->raw(), $matches, 0, $startXRefPos) !== 1) {
            throw new Exception('startxref and %%EOF not found');
        }

        $xrefPos = intval($matches[1]);

        if ($xrefPos === 0) {
            return [
                'trailer' => null,
                'version' => substr($pdfVersion, 1),
                'xref' => [],
                'xrefposition' => 0,
                'xrefversion' => substr($pdfVersion, 1),
                'revisions' => $versions,
            ];
        }

        [$xrefTable, $trailerObject, $minPdfVersion] = Xref::new()
            ->withDepth($this->depth)
            ->withXRefPos($xrefPos)
            ->withPdfDocument($this->pdfDocument)
            ->getXref();

        if ($xrefTable === false) {
            throw new Exception('Could not find the xref table');
        }

        if ($trailerObject === false) {
            throw new Exception('Could not find the trailer object');
        }

        return [
            'trailer' => $trailerObject,
            'version' => substr($pdfVersion, 1),
            'xref' => $xrefTable,
            'xrefposition' => $xrefPos,
            'xrefversion' => $minPdfVersion,
            'revisions' => $versions,
        ];
    }
}