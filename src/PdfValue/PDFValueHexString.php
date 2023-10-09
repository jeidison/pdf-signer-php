<?php

namespace Jeidison\PdfSigner\PdfValue;

class PDFValueHexString extends PDFValueString
{
    public function __toString(): string
    {
        return '<'.trim((string) $this->value).'>';
    }
}
