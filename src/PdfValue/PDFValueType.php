<?php

namespace Jeidison\PdfSigner\PdfValue;

class PDFValueType extends PDFValue
{
    public function __toString(): string
    {
        return '/'.trim((string) $this->value);
    }
}
