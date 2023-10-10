<?php

namespace Jeidison\PdfSigner\Xref;

use Exception;
use Jeidison\PdfSigner\PdfDocument;
use Jeidison\PdfSigner\StreamReader;

/**
 * @author Jeidison Farias <jeidison.farias@gmail.com>
 **/
class XRef15
{
    private PdfDocument $pdfDocument;

    private int $xrefPosition;

    public static function new(): static
    {
        return new static();
    }

    public function withPdfDocument(PdfDocument $pdfDocument): self
    {
        $this->pdfDocument = $pdfDocument;

        return $this;
    }

    public function withXRefPos(?int $xrefPos): self
    {
        $this->xrefPosition = $xrefPos;

        return $this;
    }

    public function getXref()
    {
        $xrefO = $this->pdfDocument->findObjectAtPos($this->xrefPosition, []);
        if ($xrefO === false) {
            throw new Exception('Cross reference object not found when parsing xref at position '.$this->xrefPosition);
        }

        if (! (isset($xrefO['Type'])) || ($xrefO['Type']->val() !== 'XRef')) {
            throw new Exception('Invalid xref table');
        }

        $stream = $xrefO->getStream(false);
        if ($stream === null) {
            throw new Exception('Cross reference stream not found when parsing xref at position '.$this->xrefPosition);
        }

        $W = $xrefO['W']->val(true);
        if (count($W) !== 3) {
            throw new Exception('Invalid cross reference object');
        }

        $W[0] = (int) $W[0];
        $W[1] = (int) $W[1];
        $W[2] = (int) $W[2];

        $Size = $xrefO['Size']->get_int();
        if ($Size === false) {
            throw new Exception('Could not get the size of the xref table');
        }

        $Index = [0, $Size];
        if (isset($xrefO['Index'])) {
            $Index = $xrefO['Index']->val(true);
        }

        if (count($Index) % 2 !== 0) {
            throw new Exception('Invalid indexes of xref table');
        }

        $xrefTable = [];
        if (isset($xrefO['Prev'])) {
            $Prev = $xrefO['Prev'];
            $Prev = $Prev->get_int();
            if ($Prev === false) {
                throw new Exception('Invalid reference to a previous xref table');
            }

            [$xrefTable] = XRef15::new()
                ->withPdfDocument($this->pdfDocument)
                ->withXRefPos($Prev)
                ->getXref();
        }

        $streamV = new StreamReader($stream);

        $getFmtFunction = function ($f) {
            if ($f === false) {
                return false;
            }

            return match ($f) {
                0 => static fn ($v) => 0,
                1 => static fn ($v) => unpack('C', str_pad($v, 1, chr(0), STR_PAD_LEFT))[1],
                2 => static fn ($v) => unpack('n', str_pad($v, 2, chr(0), STR_PAD_LEFT))[1],
                3, 4 => static fn ($v) => unpack('N', str_pad($v, 4, chr(0), STR_PAD_LEFT))[1],
                5, 6, 7, 8 => static fn ($v) => unpack('J', str_pad($v, 8, chr(0), STR_PAD_LEFT))[1],
                default => false,
            };
        };

        $fmtFunction = [
            $getFmtFunction($W[0]),
            $getFmtFunction($W[1]),
            $getFmtFunction($W[2]),
        ];

        $indexI = 0;
        while ($indexI < count($Index)) {
            $objectI = $Index[$indexI++];
            $objectCount = $Index[$indexI++];

            while (($streamV->currentChar() !== false) && ($objectCount > 0)) {
                $f1 = $W[0] != 0 ? ($fmtFunction[0]($streamV->nextChars($W[0]))) : 1;
                $f2 = $fmtFunction[1]($streamV->nextChars($W[1]));
                $f3 = $fmtFunction[2]($streamV->nextChars($W[2]));

                if (($f1 === false) || ($f2 === false) || ($f3 === false)) {
                    throw new Exception('Invalid stream for xref table');
                }

                switch ($f1) {
                    case 0:
                        $xrefTable[$objectI] = null;
                        break;
                    case 1:
                        $xrefTable[$objectI] = $f2;
                        if ($f3 !== 0) {
                            throw new Exception('Objects of non-zero generation are not fully checked... please double check your document.');
                        }

                        break;
                    case 2:
                        $xrefTable[$objectI] = ['stmoid' => $f2, 'pos' => $f3];
                        break;
                }

                $objectI++;
                $objectCount--;
            }
        }

        return [$xrefTable, $xrefO->getValue(), '1.5'];
    }
}
