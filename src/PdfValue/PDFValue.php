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

    public function getInt(): bool
    {
        return false;
    }

    public function getObjectReferenced(): mixed
    {
        return false;
    }

    public function getKeys()
    {
        return null;
    }

    protected static function convert(mixed $value): PDFValue
    {
        switch (gettype($value)) {
            case 'integer':
            case 'double':
                return new PDFValueSimple($value);
            case 'string':
                if ($value[0] === '/') {
                    return new PDFValueType(substr($value, 1));
                } elseif (preg_match("/\s/ms", $value) === 1) {
                    return new PDFValueString($value);
                }

                return new PDFValueSimple($value);
            case 'array':
                if ($value === []) {
                    return new PDFValueList();
                }

                $obj = PDFValueObject::fromArray($value);
                if ($obj != null) {
                    return $obj;
                }

                $list = [];
                foreach ($value as $v) {
                    $list[] = self::convert($v);
                }

                return new PDFValueList($list);
        }

        return $value;
    }
}
