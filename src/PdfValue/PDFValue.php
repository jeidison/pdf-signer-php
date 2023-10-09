<?php

namespace Jeidison\PdfSigner\PdfValue;

use ArrayAccess;
use Exception;
use Stringable;

abstract class PDFValue implements ArrayAccess, Stringable
{
    public function __construct(protected mixed $value)
    {
    }

    public function val(): mixed
    {
        return $this->value;
    }

    public function __toString(): string
    {
        return ''.$this->value;
    }

    public function offsetExists($offset): bool
    {
        if (! is_array($this->value)) {
            return false;
        }

        return isset($this->value[$offset]);
    }

    public function offsetGet($offset): mixed
    {
        if (! is_array($this->value)) {
            return null;
        }

        if (! isset($this->value[$offset])) {
            return null;
        }

        return $this->value[$offset];
    }

    public function offsetSet($offset, $value): void
    {
        if (! is_array($this->value)) {
            return;
        }

        $this->value[$offset] = $value;
    }

    public function offsetUnset($offset): void
    {
        if ((! is_array($this->value)) || (! isset($this->value[$offset]))) {
            throw new Exception('Invalid offset');
        }

        unset($this->value[$offset]);
    }

    public function push(mixed $value): bool
    {
        return false;
    }

    public function get_int(): bool
    {
        return false;
    }

    public function get_object_referenced(): mixed
    {
        return false;
    }

    public function getKeys()
    {
        return null;
    }

    /**
     * Function that converts standard types into PDFValue
     *  - integer, double are translated into PDFValueSimple
     *  - string beginning with /, is translated into PDFValueType
     *  - string without separator (e.g. "\t\n ") are translated into PDFValueSimple
     *  - other strings are translated into PDFValueString
     *  - array is translated into PDFValueList, and its inner elements are also converted.
     *
     * @param  mixed  $value a standard php object (e.g. string, integer, double, array, etc.)
     * @return PDFValue an object of type PDFValue*, depending on the
     */
    protected static function convert(mixed $value): PDFValue
    {
        switch (gettype($value)) {
            case 'integer':
            case 'double':
                $value = new PDFValueSimple($value);
                break;
            case 'string':
                if ($value[0] === '/') {
                    $value = new PDFValueType(substr($value, 1));
                } elseif (preg_match("/\s/ms", $value) === 1) {
                    $value = new PDFValueString($value);
                } else {
                    $value = new PDFValueSimple($value);
                }

                break;
            case 'array':
                if ($value === []) {
                    $value = new PDFValueList();
                } else {
                    $obj = PDFValueObject::fromArray($value);
                    if ($obj !== null) {
                        $value = $obj;
                    } else {
                        $list = [];
                        foreach ($value as $v) {
                            $list[] = self::convert($v);
                        }

                        $value = new PDFValueList($list);
                    }
                }

                break;
        }

        return $value;
    }
}
