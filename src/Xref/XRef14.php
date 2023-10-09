<?php

namespace Jeidison\PdfSigner\Xref;

use Exception;
use Jeidison\PdfSigner\PdfValue\PDFValue;
use Jeidison\PdfSigner\Trailer;

class XRef14
{
    private string $buffer;
    private ?int $depth = null;
    private int $xrefPosition;

    public static function new(): static
    {
        return new static();
    }

    public function withBuffer(string $buffer): self
    {
        $this->buffer = $buffer;

        return $this;
    }

    public function withXRefPos(?int $xrefPos): self
    {
        $this->xrefPosition = $xrefPos;

        return $this;
    }

    public function withDepth(?int $depth): self
    {
        $this->depth = $depth;

        return $this;
    }

    public function getXref()
    {
        if ($this->depth !== null) {
            if ($this->depth <= 0) {
                return false;
            }

            --$this->depth;
        }

        $trailerPos = strpos($this->buffer, 'trailer', $this->xrefPosition);
        $minPdfVersion = '1.4';

        $xrefSubstr = substr($this->buffer, $this->xrefPosition, $trailerPos - $this->xrefPosition);

        $separator = "\r\n";
        $xrefLine = strtok($xrefSubstr, $separator);
        if ($xrefLine !== 'xref') {
            throw new Exception('Xref tag not found at position ' . $this->xrefPosition);
        }

        $objId = false;
        $objCount = 0;
        $xrefTable = [];

        while (($xrefLine = strtok($separator)) !== false) {

            if (preg_match('/(\d+) (\d+)$/', $xrefLine, $matches) === 1) {
                if ($objCount > 0) {
                    throw new Exception('Malformed xref at position ' . $this->xrefPosition);
                }

                $objId = (int) $matches[1];
                $objCount = (int) $matches[2];

                continue;
            }

            $qtd = preg_match('/^(\d+) (\d+) (.)\s*/', $xrefLine, $matches);
            if ($qtd !== 1) {
                continue;
            }

            if ($objCount === 0) {
                throw new Exception('Unexpected entry for xref: ' . $xrefLine);
            }

            $objOffset = (int) $matches[1];
            $objGeneration = (int) $matches[2];
            $objOperation = $matches[3];

            if ($objOffset !== 0) {
                switch ($objOperation) {
                    case 'f':
                        $xrefTable[$objId] = null;
                        break;
                    case 'n':
                        $xrefTable[$objId] = $objOffset;
                        if ($objGeneration != 0) {
                            throw new Exception('Objects of non-zero generation are not fully checked... please double check your document.');
                        }
                        break;
                }
            }

            --$objCount;
            ++$objId;
        }

        $trailerObj = Trailer::new()
            ->withBuffer($this->buffer)
            ->withTrailerPosition($trailerPos)
            ->getTrailer();

        if (isset($trailerObj['Prev'])) {
            $xrefTable = $this->getPreviousXref($trailerObj, $minPdfVersion, $xrefTable);
        }

        return [$xrefTable, $trailerObj, $minPdfVersion];
    }

    private function getPreviousXref(PDFValue $trailerObj, string $minPdfVersion, array $xrefTable): array
    {
        $xrefPrevPos = $trailerObj['Prev']->val();
        if (!is_numeric($xrefPrevPos)) {
            throw new Exception('Invalid trailer');
        }

        $xrefPrevPos = (int)$xrefPrevPos;
        [$prevTable, , $prevMinPdfVersion] = XRef14::new()
            ->withBuffer($this->buffer)
            ->withXRefPos($xrefPrevPos)
            ->withDepth($this->depth)
            ->getXref();

        if ($prevMinPdfVersion !== $minPdfVersion) {
            throw new Exception('Mixed type of xref tables are not supported');
        }

        if ($prevTable !== false) {
            foreach ($prevTable as $objId => $objOffset) {
                if (!isset($xrefTable[$objId])) {
                    $xrefTable[$objId] = $objOffset;
                }
            }
        }

        return $xrefTable;
    }
}