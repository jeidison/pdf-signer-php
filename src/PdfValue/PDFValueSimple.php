<?php

namespace Jeidison\PdfSigner\PdfValue;

class PDFValueSimple extends PDFValue
{
    public function push($value): bool
    {
        if ($value::class === static::class) {
            $this->value = $this->value.' '.$value->val();

            return true;
        }

        return false;
    }

    public function getObjectReferenced(): mixed
    {
        if (! preg_match('/^\s*([0-9]+)\s+([0-9]+)\s+R\s*$/ms', (string) $this->value, $matches)) {
            return false;
        }

        return (int) $matches[1];
    }

    public function getInt(): bool
    {
        if (! is_numeric($this->value)) {
            return false;
        }

        return (int) $this->value;
    }
}
