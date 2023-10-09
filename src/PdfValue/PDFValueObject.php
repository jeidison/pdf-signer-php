<?php

namespace Jeidison\PdfSigner\PdfValue;

class PDFValueObject extends PDFValue
{
    public function __construct(array $value = [])
    {
        $result = [];
        foreach ($value as $k => $v) {
            $result[$k] = self::convert($v);
        }

        parent::__construct($result);
    }

    public static function fromArray($parts): ?PDFValueObject
    {
        $k = array_keys($parts);
        $intkeys = false;
        $result = [];
        foreach ($k as $ck) {
            if (is_int($ck)) {
                $intkeys = true;
                break;
            }
        }

        if ($intkeys) {
            return null;
        }

        foreach ($parts as $k => $v) {
            $result[$k] = self::convert($v);
        }

        return new PDFValueObject($result);
    }

    public static function fromString($str): ?PDFValueObject
    {
        $result = [];
        $field = null;
        $parts = explode(' ', (string) $str);
        $counter = count($parts);
        for ($i = 0; $i < $counter; $i++) {

            if ($field === null) {
                $field = $parts[$i];
                if ($field === '') {
                    return null;
                }

                if ($field[0] !== '/') {
                    return null;
                }

                $field = substr($field, 1);
                if ($field === '') {
                    return null;
                }

                continue;
            }

            $value = $parts[$i];
            $result[$field] = $value;
            $field = null;
        }

        if ($field !== null) {
            return null;
        }

        return new PDFValueObject($result);
    }

    public function getKeys(): array
    {
        return array_keys($this->value);
    }

    public function offsetSet($offset, $value): void
    {
        if ($value === null) {
            if (isset($this->value[$offset])) {
                unset($this->value[$offset]);
            }
        }

        $this->value[$offset] = self::convert($value);
    }

    public function offsetExists($offset): bool
    {
        return isset($this->value[$offset]);
    }

    public function __toString(): string
    {
        $result = [];
        foreach ($this->value as $k => $v) {
            $v = ''.$v;
            if ($v === '') {
                $result[] = '/'.$k;

                continue;
            }

            match ($v[0]) {
                '/', '[', '(', '<' => array_push($result, sprintf('/%s%s', $k, $v)),
                default => array_push($result, sprintf('/%s %s', $k, $v)),
            };
        }

        return '<<'.implode('', $result).'>>';
    }
}
