<?php

namespace Jeidison\PdfSigner\PdfValue;

class PDFValueReference extends PDFValueSimple
{
    public function __construct($oid)
    {
        parent::__construct(sprintf('%d 0 R', $oid));
    }
}
