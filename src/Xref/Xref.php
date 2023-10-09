<?php

namespace Jeidison\PdfSigner\Xref;

use Jeidison\PdfSigner\PdfDocument;

class Xref
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

    public function getXref(): array
    {
        $trailerPos = strpos($this->pdfDocument->getBuffer()->raw(), 'trailer', $this->xrefPosition);
        if ($trailerPos === false) {
            return XRef15::new()
                ->withPdfDocument($this->pdfDocument)
                ->withXRefPos($this->xrefPosition)
                ->withDepth($this->depth)
                ->getXref();
        }

        return XRef14::new()
            ->withBuffer($this->pdfDocument->getBuffer()->raw())
            ->withXRefPos($this->xrefPosition)
            ->withDepth($this->depth)
            ->getXref();
    }

    public function build_xref_1_5(array $offsets): array
    {
        if (isset($offsets[0])) {
            unset($offsets[0]);
        }

        $k = array_keys($offsets);
        sort($k);

        $indexes = [];
        $iK = 0;
        $cK = 0;
        $count = 1;
        $result = '';
        $counter = count($k);
        for ($i = 0; $i < $counter; ++$i) {
            if ($cK === 0) {
                $cK = $k[$i] - 1;
                $iK = $k[$i];
                $count = 0;
            }

            if ($k[$i] === $cK + 1) {
                ++$count;
            } else {
                $indexes[] = sprintf('%s %d', $iK, $count);
                $count = 1;
                $iK = $k[$i];
            }

            $cOffset = $offsets[$k[$i]];

            if (is_array($cOffset)) {
                $result .= pack('C', 2);
                $result .= pack('N', $cOffset['stmoid']);
                $result .= pack('C', $cOffset['pos']);
            } elseif ($cOffset === null) {
                $result .= pack('C', 0);
                $result .= pack('N', $cOffset);
                $result .= pack('C', 0);
            } else {
                $result .= pack('C', 1);
                $result .= pack('N', $cOffset);
                $result .= pack('C', 0);
            }

            $cK = $k[$i];
        }

        $indexes[] = sprintf('%s %d', $iK, $count);
        $indexes = implode(' ', $indexes);

        // p_debug(show_bytes($result, 6));

        return [
            'W' => [1, 4, 1],
            'Index' => $indexes,
            'stream' => $result,
        ];
    }

    public function build_xref(array $offsets): string
    {
        $k = array_keys($offsets);
        sort($k);

        $iK = 0;
        $cK = 0;
        $count = 1;
        $result = '';
        $references = "0000000000 65535 f \n";
        $counter = count($k);
        for ($i = 0; $i < $counter; ++$i) {
            if ($k[$i] === 0) {
                continue;
            }

            if ($k[$i] === $cK + 1) {
                ++$count;
            } else {
                $result .= sprintf('%s %d%s%s', $iK, $count, PHP_EOL, $references);
                $count = 1;
                $iK = $k[$i];
                $references = '';
            }

            $references .= sprintf("%010d 00000 n \n", $offsets[$k[$i]]);
            $cK = $k[$i];
        }

        $result .= sprintf('%s %d%s%s', $iK, $count, PHP_EOL, $references);

        return 'xref
' . $result;
    }
}