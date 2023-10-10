<?php

namespace Jeidison\PdfSigner;

use ArrayAccess;
use Exception;
use Jeidison\PdfSigner\PdfValue\PDFValue;
use Jeidison\PdfSigner\PdfValue\PDFValueObject;
use Jeidison\PdfSigner\PdfValue\PDFValueSimple;
use Stringable;

class PDFObject implements ArrayAccess, Stringable
{
    protected mixed $stream = null;

    protected null|array|PDFValue $value = null;

    protected int $generation;

    public function __construct(protected $oid, array|PDFValue $value = null, $generation = 0)
    {
        if ($value === null) {
            $value = new PDFValueObject();
        }

        if (is_array($value)) {
            $obj = new PDFValueObject();
            foreach ($value as $field => $v) {
                $obj[$field] = $v;
            }

            $value = $obj;
        }

        $this->value = $value;
        $this->generation = $generation;
    }

    public function getKeys(): array
    {
        return $this->value->getKeys();
    }

    public function setOid($oid): void
    {
        $this->oid = $oid;
    }

    public function getGeneration(): int
    {
        return $this->generation;
    }

    public function __toString(): string
    {
        return $this->oid.' 0 obj
'.
            ($this->value.PHP_EOL).
            ($this->stream === null ? '' :
                'stream
...
endstream
'
            ).
            "endobj\n";
    }

    public function toPdfEntry(): string
    {
        return $this->oid.' 0 obj'.PHP_EOL.
                $this->value.PHP_EOL.
                ($this->stream === null ? '' :
                    "stream\r\n".
                    $this->stream.
                    PHP_EOL.'endstream'.PHP_EOL
                ).
                'endobj'.PHP_EOL;
    }

    public function getOid()
    {
        return $this->oid;
    }

    public function getValue()
    {
        return $this->value;
    }

    protected static function flateDecode($stream, $params): ?string
    {
        switch ($params['Predictor']->get_int()) {
            case 1:
                return $stream;
            case 10:
            case 11:
            case 12:
            case 13:
            case 14:
            case 15:
                break;
            default:
                throw new Exception('other predictor than PNG is not supported in this version');
        }

        switch ($params['Colors']->get_int()) {
            case 1:
                break;
            default:
                throw new Exception('other color count than 1 is not supported in this version');
        }

        switch ($params['BitsPerComponent']->get_int()) {
            case 8:
                break;
            default:
                throw new Exception('other bit count than 8 is not supported in this version');
        }

        $decoded = new Buffer();
        $columns = $params['Columns']->get_int();
        $streamLen = strlen((string) $stream);

        $dataPrev = str_pad('', $columns, chr(0));
        $posI = 0;
        while ($posI < $streamLen) {
            $filterByte = ord($stream[$posI++]);
            $data = substr((string) $stream, $posI, $columns);
            $posI += strlen($data);
            $data = str_pad($data, $columns, chr(0));

            switch ($filterByte) {
                case 0:
                    break;
                case 1:
                    for ($i = 1; $i < $columns; $i++) {
                        $data[$i] = ($data[$i] + $data[$i - 1]) % 256;
                    }

                    break;
                case 2:
                    for ($i = 0; $i < $columns; $i++) {
                        $data[$i] = chr((ord($data[$i]) + ord($dataPrev[$i])) % 256);
                    }

                    break;
                default:
                    throw new Exception('Unsupported stream');
            }

            $decoded->data($data);
            $dataPrev = $data;
        }

        return $decoded->raw();
    }

    public function getStream($raw = true): string
    {
        if ($raw === true) {
            return $this->stream;
        }

        if (isset($this->value['Filter'])) {
            switch ($this->value['Filter']) {
                case '/FlateDecode':
                    $DecodeParams = $this->value['DecodeParms'] ?? [];
                    $params = [
                        'Columns' => $DecodeParams['Columns'] ?? new PDFValueSimple(0),
                        'Predictor' => $DecodeParams['Predictor'] ?? new PDFValueSimple(1),
                        'BitsPerComponent' => $DecodeParams['BitsPerComponent'] ?? new PDFValueSimple(8),
                        'Colors' => $DecodeParams['Colors'] ?? new PDFValueSimple(1),
                    ];

                    return self::flateDecode(gzuncompress($this->stream), $params);
                default:
                    throw new Exception('Unknown compression method '.$this->value['Filter']);
            }
        }

        return $this->stream;
    }

    public function setStream($stream, $raw = true): void
    {
        if ($raw === true) {
            $this->stream = $stream;

            return;
        }

        if (isset($this->value['Filter'])) {
            if ($this->value['Filter'] == '/FlateDecode') {
                $stream = gzcompress((string) $stream);
            }
        }

        $this->value['Length'] = strlen((string) $stream);
        $this->stream = $stream;
    }

    public function offsetSet($field, $value): void
    {
        $this->value[$field] = $value;
    }

    public function offsetExists($field): bool
    {
        return $this->value->offsetExists($field);
    }

    #[\ReturnTypeWillChange]
    public function offsetGet($field)
    {
        return $this->value[$field];
    }

    public function offsetUnset($field): void
    {
        $this->value->offsetUnset($field);
    }

    public function push($v)
    {
        return $this->value->push($v);
    }
}
