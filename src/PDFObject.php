<?php

namespace Jeidison\PdfSigner;

use ArrayAccess;
use Jeidison\PdfSigner\PdfValue\PDFValueObject;
use Jeidison\PdfSigner\PdfValue\PDFValueSimple;

// The character used to end lines
if (! defined('__EOL')) {
    define('__EOL', "\n");
}

/**
 * Class to gather the information of a PDF object: the OID, the definition and the stream. The purpose is to
 *   ease the generation of the PDF entries for an individual object.
 */
class PDFObject implements ArrayAccess, \Stringable
{
    protected static $_revisions;

    protected static $_xref_table_version;

    protected $_stream = null;

    protected $_value = null;

    protected $_generation;

    public function __construct(protected $_oid, $value = null, $generation = 0)
    {
        if ($generation !== 0) {
            p_warning('Objects of non-zero generation are not fully checked... please double check your document and (if possible) please send examples via issues to https://github.com/dealfonso/sapp/issues/');
        }

        // If the value is null, we suppose that we are creating an empty object
        if ($value === null) {
            $value = new PDFValueObject();
        }

        // Ease the creation of the object
        if (is_array($value)) {
            $obj = new PDFValueObject();
            foreach ($value as $field => $v) {
                $obj[$field] = $v;
            }

            $value = $obj;
        }

        $this->_value = $value;
        $this->_generation = $generation;
    }

    public function get_keys()
    {
        return $this->_value->get_keys();
    }

    public function set_oid($oid)
    {
        $this->_oid = $oid;
    }

    public function get_generation()
    {
        return $this->_generation;
    }

    public function __toString(): string
    {
        return $this->_oid . ' 0 obj
'.
            ($this->_value . PHP_EOL).
            ($this->_stream === null ? '' :
                'stream
...
endstream
'
            ).
            "endobj\n";
    }

    /**
     * Converts the object to a well-formed PDF entry with a form like
     *  1 0 obj
     *  ...
     *  stream
     *  ...
     *  endstream
     *  endobj
     *
     * @return pdfentry a string that contains the PDF entry
     */
    public function to_pdf_entry()
    {
        return $this->_oid . ' 0 obj'.__EOL.
                $this->_value.__EOL.
                ($this->_stream === null ? '' :
                    "stream\r\n".
                    $this->_stream.
                    __EOL.'endstream'.__EOL
                ).
                'endobj'.__EOL;
    }

    /**
     * Gets the object ID
     *
     * @return int the object id
     */
    public function get_oid()
    {
        return $this->_oid;
    }

    /**
     * Gets the definition of the object (a PDFValue object)
     *
     * @return value the definition of the object
     */
    public function get_value()
    {
        return $this->_value;
    }

    protected static function FlateDecode($_stream, $params)
    {
        switch ($params['Predictor']->get_int()) {
            case 1:
                return $_stream;
            case 10:
            case 11:
            case 12:
            case 13:
            case 14:
            case 15:
                break;
            default:
                return p_error('other predictor than PNG is not supported in this version');
        }

        switch ($params['Colors']->get_int()) {
            case 1:
                break;
            default:
                return p_error('other color count than 1 is not supported in this version');
        }

        switch ($params['BitsPerComponent']->get_int()) {
            case 8:
                break;
            default:
                return p_error('other bit count than 8 is not supported in this version');
        }

        $decoded = new Buffer();
        $columns = $params['Columns']->get_int();

        $rowLen = $columns + 1;
        $streamLen = strlen((string) $_stream);

        // The previous row is zero
        $dataPrev = str_pad('', $columns, chr(0));
        $rowI = 0;
        $posI = 0;
        $data = str_pad('', $columns, chr(0));
        while ($posI < $streamLen) {
            $filterByte = ord($_stream[$posI++]);

            // Get the current row
            $data = substr((string) $_stream, $posI, $columns);
            $posI += strlen($data);

            // Zero pad, in case that the content is not paired
            $data = str_pad($data, $columns, chr(0));

            // Depending on the filter byte of the row, we should unpack on one way or another
            switch ($filterByte) {
                case 0:
                    break;
                case 1:
                    for ($i = 1; $i < $columns; ++$i) {
                        $data[$i] = ($data[$i] + $data[$i - 1]) % 256;
                    }

                    break;
                case 2:
                    for ($i = 0; $i < $columns; ++$i) {
                        $data[$i] = chr((ord($data[$i]) + ord($dataPrev[$i])) % 256);
                    }

                    break;
                default:
                    return p_error('Unsupported stream');
            }

            // Store and prepare the previous row
            $decoded->data($data);
            $dataPrev = $data;
        }

        // p_debug_var($decoded->show_bytes($columns));
        return $decoded->raw();
    }

    /**
     * Gets the stream of the object
     *
     * @return stream a string that contains the stream of the object
     */
    public function get_stream($raw = true)
    {
        if ($raw === true) {
            return $this->_stream;
        }

        if (isset($this->_value['Filter'])) {
            switch ($this->_value['Filter']) {
                case '/FlateDecode':
                    $DecodeParams = $this->_value['DecodeParms'] ?? [];
                    $params = [
                        'Columns' => $DecodeParams['Columns'] ?? new PDFValueSimple(0),
                        'Predictor' => $DecodeParams['Predictor'] ?? new PDFValueSimple(1),
                        'BitsPerComponent' => $DecodeParams['BitsPerComponent'] ?? new PDFValueSimple(8),
                        'Colors' => $DecodeParams['Colors'] ?? new PDFValueSimple(1),
                    ];

                    return self::FlateDecode(gzuncompress($this->_stream), $params);

                    break;
                default:
                    return p_error('unknown compression method '.$this->_value['Filter']);
            }
        }

        return $this->_stream;
    }

    /**
     * Sets the stream for the object (overwrites a previous existing stream)
     *
     * @param stream the stream for the object
     */
    public function set_stream($stream, $raw = true)
    {
        if ($raw === true) {
            $this->_stream = $stream;

            return;
        }

        if (isset($this->_value['Filter'])) {
            switch ($this->_value['Filter']) {
                case '/FlateDecode':
                    $stream = gzcompress((string) $stream);
                    break;
                default:
                    p_error('unknown compression method '.$this->_value['Filter']);
            }
        }

        $this->_value['Length'] = strlen((string) $stream);
        $this->_stream = $stream;
    }

    /**
     * The next functions enble to make use of this object in an array-like manner,
     *  using the name of the fields as positions in the array. It is useful is the
     *  value is of type PDFValueObject or PDFValueList, using indexes
     */

    /**
     * Sets the value of the field offset, using notation $obj['field'] = $value
     *
     * @param field the field to set the value
     * @param value the value to set
     */
    public function offsetSet($field, $value): void
    {
        $this->_value[$field] = $value;
    }

    /**
     * Checks whether the field exists in the object or not (or if the index exists
     *   in the list)
     *
     * @param field the field to check wether exists or not
     * @return exists true if the field exists; false otherwise
     */
    public function offsetExists($field): bool
    {
        return $this->_value->offsetExists($field);
    }

    /**
     * Gets the value of the field (or the value at position)
     *
     * @param field the field to get the value
     * @return value the value of the field
     */
    #[\ReturnTypeWillChange]
    public function offsetGet($field)
    {
        return $this->_value[$field];
    }

    /**
     * Unsets the value of the field (or the value at position)
     *
     * @param field the field to unset the value
     */
    public function offsetUnset($field): void
    {
        $this->_value->offsetUnset($field);
    }

    public function push($v)
    {
        return $this->_value->push($v);
    }
}
