<?php

namespace Jeidison\PdfSigner\Xref;

use Exception;
use Jeidison\PdfSigner\PdfDocument;
use Jeidison\PdfSigner\StreamReader;

class XRef15
{
    private PdfDocument $pdfDocument;
    private ?int $depth = null;
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

        $xrefO = $this->pdfDocument->find_object_at_pos($this->xrefPosition, []);
        if ($xrefO === false) {
            throw new Exception('cross reference object not found when parsing xref at position ' . $this->xrefPosition);
        }

        if (! (isset($xrefO['Type'])) || ($xrefO['Type']->val() !== 'XRef')) {
            throw new Exception('invalid xref table', [false, false, false]);
        }

        $stream = $xrefO->get_stream(false);
        if ($stream === null) {
            throw new Exception('cross reference stream not found when parsing xref at position ' . $this->xrefPosition);
        }

        $W = $xrefO['W']->val(true);
        if (count($W) !== 3) {
            throw new Exception('invalid cross reference object', [false, false, false]);
        }

        $W[0] = (int) $W[0];
        $W[1] = (int) $W[1];
        $W[2] = (int) $W[2];

        $Size = $xrefO['Size']->get_int();
        if ($Size === false) {
            throw new Exception('could not get the size of the xref table', [false, false, false]);
        }

        $Index = [0, $Size];
        if (isset($xrefO['Index'])) {
            $Index = $xrefO['Index']->val(true);
        }

        if (count($Index) % 2 !== 0) {
            throw new Exception('invalid indexes of xref table', [false, false, false]);
        }

        // Get the previous xref table, to build up on it
        $trailerObj = null;
        $xrefTable = [];

        // If still want to get more versions, let's check whether there is a previous xref table or not
        if ((($this->depth === null) || ($this->depth > 0)) && isset($xrefO['Prev'])) {
            $Prev = $xrefO['Prev'];
            $Prev = $Prev->get_int();
            if ($Prev === false) {
                throw new Exception('invalid reference to a previous xref table', [false, false, false]);
            }

            // When dealing with 1.5 cross references, we do not allow to use other than cross references
            [$xrefTable, $trailerObj] = XRef15::new()
                ->withPdfDocument($this->pdfDocument)
                ->withXRefPos($Prev)
                ->withDepth($this->depth)
                ->getXref();
        }

        // p_debug("xref table found at $xref_pos (oid: " . $xref_o->get_oid() . ")");
        $streamV = new StreamReader($stream);

        // Get the format function to un pack the values
        $getFmtFunction = static function ($f) {
            if ($f === false) {
                return false;
            }

            return match ($f) {
                0 => static fn($v) => 0,
                1 => static fn($v) => unpack('C', str_pad($v, 1, chr(0), STR_PAD_LEFT))[1],
                2 => static fn($v) => unpack('n', str_pad($v, 2, chr(0), STR_PAD_LEFT))[1],
                3, 4 => static fn($v) => unpack('N', str_pad($v, 4, chr(0), STR_PAD_LEFT))[1],
                5, 6, 7, 8 => static fn($v) => unpack('J', str_pad($v, 8, chr(0), STR_PAD_LEFT))[1],
                default => false,
            };
        };

        $fmtFunction = [
            $getFmtFunction($W[0]),
            $getFmtFunction($W[1]),
            $getFmtFunction($W[2]),
        ];

        // Parse the stream according to the indexes and the W array
        $indexI = 0;
        while ($indexI < count($Index)) {
            $objectI = $Index[$indexI++];
            $objectCount = $Index[$indexI++];

            while (($streamV->currentChar() !== false) && ($objectCount > 0)) {
                $f1 = $W[0] != 0 ? ($fmtFunction[0]($streamV->nextChars($W[0]))) : 1;
                $f2 = $fmtFunction[1]($streamV->nextChars($W[1]));
                $f3 = $fmtFunction[2]($streamV->nextChars($W[2]));

                if (($f1 === false) || ($f2 === false) || ($f3 === false)) {
                    throw new Exception('invalid stream for xref table', [false, false, false]);
                }

                switch ($f1) {
                    case 0:
                        // Free object
                        $xrefTable[$objectI] = null;
                        break;
                    case 1:
                        // Add object
                        $xrefTable[$objectI] = $f2;
                        /*
                        TODO: consider creating a generation table, but for the purpose of the xref there is no matter... if the document if well-formed.
                        */
                        if ($f3 !== 0) {
                            throw new Exception('Objects of non-zero generation are not fully checked... please double check your document.');
                        }

                        break;
                    case 2:
                        // Stream object
                        // $f2 is the number of a stream object, $f3 is the index in that stream object
                        $xrefTable[$objectI] = ['stmoid' => $f2, 'pos' => $f3];
                        break;
                }

                ++$objectI;
                --$objectCount;
            }
        }

        return [$xrefTable, $xrefO->get_value(), '1.5'];
    }
}