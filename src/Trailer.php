<?php

namespace Jeidison\PdfSigner;

use Exception;

class Trailer
{
    private string $buffer;
    private int $trailerPos;

    public static function new(): static
    {
        return new static();
    }

    public function withBuffer(string $buffer): self
    {
        $this->buffer = $buffer;

        return $this;
    }

    public function withTrailerPosition(int $trailerPos): self
    {
        $this->trailerPos = $trailerPos;

        return $this;
    }

    public function getTrailer()
    {
        if (preg_match('/trailer\s*(.*)\s*startxref/ms', $this->buffer, $matches, 0, $this->trailerPos) !== 1) {
            throw new Exception('Trailer not found.');
        }

        $trailerStr = $matches[1];
        try {
            $parser = new PDFObjectParser();
            return $parser->parsestr($trailerStr);
        } catch (Exception $e) {
            throw new Exception('Trailer is not valid.', previous: $e);
        }
    }
}