<?php

namespace Jeidison\PdfSigner\PdfValue;

class PDFValueObject extends PDFValue
{
    public function __construct(array $value = [])
    {
        $result = [];
        foreach ($value as $k => $v) {
            $result[$k] = self::_convert($v);
        }

        parent::__construct($result);
    }

    public function diff($other)
    {
        $different = parent::diff($other);
        if (($different === false) || ($different === null)) {
            return $different;
        }

        $result = new PDFValueObject();
        $differences = 0;

        foreach ($this->value as $k => $v) {
            if (isset($other->value[$k])) {
                if (is_a($this->value[$k], PDFValue::class)) {
                    $different = $this->value[$k]->diff($other->value[$k]);
                    if ($different === false) {
                        $result[$k] = $v;
                        ++$differences;
                    } elseif ($different !== null) {
                        $result[$k] = $different;
                        ++$differences;
                    }
                }
            } else {
                $result[$k] = $v;
                ++$differences;
            }
        }

        if ($differences === 0) {
            return null;
        }

        return $result;
    }

    public static function fromarray($parts)
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
            return false;
        }

        foreach ($parts as $k => $v) {
            $result[$k] = self::_convert($v);
        }

        return new PDFValueObject($result);
    }

    public static function fromstring($str)
    {
        $result = [];
        $field = null;
        $value = null;
        $parts = explode(' ', (string) $str);
        $counter = count($parts);
        for ($i = 0; $i < $counter; ++$i) {
            if ($field === null) {
                $field = $parts[$i];
                if ($field === '') {
                    return false;
                }

                if ($field[0] !== '/') {
                    return false;
                }

                $field = substr($field, 1);
                if ($field === '') {
                    return false;
                }

                continue;
            }

            $value = $parts[$i];
            $result[$field] = $value;
            $field = null;
        }

        // If there is no pair of values, there is no valid
        if ($field !== null) {
            return false;
        }

        return new PDFValueObject($result);
    }

    public function get_keys()
    {
        return array_keys($this->value);
    }

    /**
     * Function used to enable using [x] to set values to the fields of the object (from ArrayAccess interface)
     *  i.e. object[offset]=value
     *
     * @param offset the index used inside the braces
     * @param value the value to set to that index (it will be converted to a PDFValue* object)
     * @return value the value set to the field
     */
    public function offsetSet($offset, $value): void
    {
        if ($value === null) {
            if (isset($this->value[$offset])) {
                unset($this->value[$offset]);
            }

            // return null;
        }

        $this->value[$offset] = self::_convert($value);
        // return $this->value[$offset];
    }

    public function offsetExists($offset): bool
    {
        return isset($this->value[$offset]);
    }

    /**
     * Function to output the object using the PDF format, and trying to make it compact (by reducing spaces, depending on the values)
     *
     * @return pdfentry the PDF entry for the object
     */
    public function __toString(): string
    {
        $result = [];
        foreach ($this->value as $k => $v) {
            $v = ''.$v;
            if ($v === '') {
                $result[] = '/' . $k;

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
