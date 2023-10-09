<?php

namespace Jeidison\PdfSigner\PdfValue;

class PDFValueList extends PDFValue
{
    public function __construct($value = [])
    {
        parent::__construct($value);
    }

    public function __toString(): string
    {
        return '['.implode(' ', $this->value).']';
    }

    public function diff($other)
    {
        $different = parent::diff($other);
        if (($different === false) || ($different === null)) {
            return $different;
        }

        $s1 = $this->__toString();
        $s2 = $other->__toString();

        if ($s1 === $s2) {
            return null;
        }

        return $this;
    }

    public function val($list = false)
    {
        if ($list === true) {
            $result = [];
            foreach ($this->value as $v) {
                $v = is_a($v, PDFValueSimple::class) ? explode(' ', (string) $v->val()) : [$v->val()];
                array_push($result, ...$v);
            }

            return $result;
        } else {
            return parent::val();
        }
    }

    public function get_object_referenced(): array|false
    {
        $ids = [];
        $plainTextVal = implode(' ', $this->value);
        if (trim($plainTextVal) == '') {
            return $ids;
        }

        $countFound = preg_match_all('/(([0-9]+)\s+[0-9]+\s+R)[^0-9]*/m', $plainTextVal, $matches);
        if ($countFound <= 0) {
            return false;
        }

        $rebuilt = implode(' ', $matches[0]);
        $rebuilt = preg_replace('/\s+/m', ' ', $rebuilt);

        $plainTextVal = preg_replace('/\s+/m', ' ', $plainTextVal);
        if ($plainTextVal === $rebuilt) {
            foreach ($matches[2] as $id) {
                $ids[] = (int) $id;
            }
        }

        return $ids;
    }

    public function push(mixed $value): bool
    {
        if (is_object($value) && ($value::class === static::class)) {
            $value = $value->val();
        }

        if (! is_array($value)) {
            $value = [$value];
        }

        foreach ($value as $e) {
            $e = self::_convert($e);
            $this->value[] = $e;
        }

        return true;
    }
}
