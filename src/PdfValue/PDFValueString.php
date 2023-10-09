<?php

namespace Jeidison\PdfSigner\PdfValue;

class PDFValueString extends PDFValue
{
    public function __toString(): string
    {
        return '('.trim($this->value).')';
    }
}
