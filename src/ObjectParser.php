<?php

namespace Jeidison\PdfSigner;

use Exception;
use Jeidison\PdfSigner\PdfValue\PDFValueHexString;
use Jeidison\PdfSigner\PdfValue\PDFValueList;
use Jeidison\PdfSigner\PdfValue\PDFValueObject;
use Jeidison\PdfSigner\PdfValue\PDFValueSimple;
use Jeidison\PdfSigner\PdfValue\PDFValueString;
use Jeidison\PdfSigner\PdfValue\PDFValueType;
use Stringable;

class ObjectParser implements Stringable
{
    // Possible tokens in a PDF document
    final public const T_NOTOKEN = 0;

    final public const T_LIST_START = 1;

    final public const T_LIST_END = 2;

    final public const T_FIELD = 3;

    final public const T_STRING = 4;

    final public const T_HEX_STRING = 12;

    final public const T_SIMPLE = 5;

    final public const T_DICT_START = 6;

    final public const T_DICT_END = 7;

    final public const T_OBJECT_BEGIN = 8;

    final public const T_OBJECT_END = 9;

    final public const T_STREAM_BEGIN = 10;

    final public const T_STREAM_END = 11;

    final public const T_COMMENT = 13;

    final public const T_NAMES = [
        self::T_NOTOKEN => 'no token',
        self::T_LIST_START => 'list start',
        self::T_LIST_END => 'list end',
        self::T_FIELD => 'field',
        self::T_STRING => 'string',
        self::T_HEX_STRING => 'hex string',
        self::T_SIMPLE => 'simple',
        self::T_DICT_START => 'dict start',
        self::T_DICT_END => 'dict end',
        self::T_OBJECT_BEGIN => 'object begin',
        self::T_OBJECT_END => 'object end',
        self::T_STREAM_BEGIN => 'stream begin',
        self::T_STREAM_END => 'stream end',
        self::T_COMMENT => 'comment',
    ];

    final public const T_SIMPLE_OBJECTS = [
        self::T_SIMPLE,
        self::T_OBJECT_BEGIN,
        self::T_OBJECT_END,
        self::T_STREAM_BEGIN,
        self::T_STREAM_END,
        self::T_COMMENT,
    ];

    protected ?StreamReader $_buffer = null;

    protected $_c = false;

    protected $_n = false;

    protected $_t = false;

    protected $_tt = self::T_NOTOKEN;

    /**
     * Retrieves the current token type (one of T_* constants)
     *
     * @return token the current token
     */
    public function currentToken()
    {
        return $this->_tt;
    }

    /**
     * Obtains the next char and prepares the variable $this->_c and $this->_n to contain the current char and the next char
     *  - if EOF, _c will be false
     *  - if the last char before EOF, _n will be false
     *
     * @return char the next char
     */
    protected function nextchar()
    {
        $this->_c = $this->_n;
        $this->_n = $this->_buffer->nextchar();

        return $this->_c;
    }

    /**
     * Prepares the parser to analythe the text (i.e. prepares the parsing variables)
     */
    protected function start($buffer)
    {
        $this->_buffer = $buffer;
        $this->_c = false;
        $this->_n = false;
        $this->_t = false;
        $this->_tt = self::T_NOTOKEN;

        if ($this->_buffer->size() === 0) {
            return false;
        }

        $this->_n = $this->_buffer->currentchar();
        $this->nextchar();
    }

    /**
     * Parses the document
     */
    public function parse($stream)
    {
        $this->start($stream);
        $this->nexttoken();

        return $this->_parse_value();
    }

    public function parsestr($str, $offset = 0)
    {
        $stream = new StreamReader($str);
        $stream->goto($offset);

        return $this->parse($stream);
    }

    public function __toString(): string
    {
        return 'pos: '.$this->_buffer->getPosition().sprintf(', c: %s, n: %s, t: %s, tt: ', $this->_c, $this->_n, $this->_t).
        self::T_NAMES[$this->_tt].', b: '.$this->_buffer->substratpos(50).
        "\n";
    }

    /**
     * Obtains the next token and returns it
     */
    public function nexttoken()
    {
        [$this->_t, $this->_tt] = $this->token();

        return $this->_t;
    }

    /**
     * Function that returns wether the current char is a separator or not
     */
    protected function _c_is_separator()
    {
        $DSEPS = ['<<', '>>'];

        return ($this->_c === false) || (str_contains("%<>()[]{}/ \n\r\t", (string) $this->_c)) || (in_array($this->_c.$this->_n, $DSEPS));
    }

    /**
     * This function assumes that the next content is an hex string, so it should be called after "<" is detected; it skips the trailing ">"
     *
     *  @return string the hex string
     */
    protected function _parse_hex_string()
    {
        $token = '';

        if ($this->_c !== '<') {
            throw new Exception('Invalid hex string');
        }

        $this->nextchar();  // This char is "<"
        while (($this->_c !== '>') && (str_contains("0123456789abcdefABCDEF \t\r\n\f", $this->_c))) {
            $token .= $this->_c;
            if ($this->nextchar() === false) {
                break;
            }
        }

        if (($this->_c !== false) && (! str_contains(">0123456789abcdefABCDEF \t\r\n\f", $this->_c))) {
            throw new Exception('invalid hex string');
        }

        // The only way to get to here is that char is ">"
        if ($this->_c !== '>') {
            throw new Exception('Invalid hex string');
        }

        $this->nextchar();

        return $token;
    }

    protected function _parse_string()
    {
        $token = '';
        if ($this->_c !== '(') {
            throw new Exception('Invalid string');
        }

        $nParenthesis = 1;
        while ($this->_c !== false) {
            $this->nextchar();
            if (($this->_c === ')') && (! strlen($token) || ($token[strlen($token) - 1] !== '\\'))) {
                $nParenthesis--;
                if ($nParenthesis == 0) {
                    break;
                }
            } else {
                if (($this->_c === '(') && (! strlen($token) || ($token[strlen($token) - 1] !== '\\'))) {
                    $nParenthesis++;
                }

                $token .= $this->_c;
            }
        }

        if ($this->_c !== ')') {
            throw new Exception('Invalid string');
        }

        $this->nextchar();

        return $token;
    }

    protected function token()
    {
        if ($this->_c === false) {
            return [false, false];
        }

        $token = false;

        while ($this->_c !== false) {
            // Skip the spaces
            while ((str_contains("\t\n\r ", (string) $this->_c)) && ($this->nextchar() !== false));

            $tokenType = self::T_NOTOKEN;

            // TODO: also the special characters are not "strictly" considered, according to section 7.3.4.2: \n \r \t \b \f \( \) \\ are valid; the other not; but also \bbb should be considered; all of them are "sufficiently" treated, but other unknown caracters such as \u are also accepted
            switch ($this->_c) {
                case '%':
                    $this->nextchar();
                    $token = '';
                    while (! str_contains("\n\r", (string) $this->_c)) {
                        $token .= $this->_c;
                        $this->nextchar();
                    }

                    $tokenType = self::T_COMMENT;
                    break;
                case '<':
                    if ($this->_n === '<') {
                        $this->nextchar();
                        $this->nextchar();
                        $token = '<<';
                        $tokenType = self::T_DICT_START;
                    } else {
                        $token = $this->_parse_hex_string();
                        $tokenType = self::T_HEX_STRING;
                    }

                    break;
                case '(':
                    $token = $this->_parse_string();
                    $tokenType = self::T_STRING;
                    break;
                case '>':
                    if ($this->_n === '>') {
                        $this->nextchar();
                        $this->nextchar();
                        $token = '>>';
                        $tokenType = self::T_DICT_END;
                    }

                    break;
                case '[':
                    $token = $this->_c;
                    $this->nextchar();
                    $tokenType = self::T_LIST_START;
                    break;
                case ']':
                    $token = $this->_c;
                    $this->nextchar();
                    $tokenType = self::T_LIST_END;
                    break;
                case '/':
                    // Skip the field idenifyer
                    $this->nextchar();

                    // We are assuming any char (i.e. /MY+difficult_id is valid)
                    while (! $this->_c_is_separator()) {
                        $token .= $this->_c;
                        if ($this->nextchar() === false) {
                            break;
                        }
                    }

                    $tokenType = self::T_FIELD;
                    break;
            }

            if ($token === false) {
                $token = '';

                while (! $this->_c_is_separator()) {
                    $token .= $this->_c;
                    if ($this->nextchar() === false) {
                        break;
                    }
                }

                $tokenType = match ($token) {
                    'obj' => self::T_OBJECT_BEGIN,
                    'endobj' => self::T_OBJECT_END,
                    'stream' => self::T_STREAM_BEGIN,
                    'endstream' => self::T_STREAM_END,
                    default => self::T_SIMPLE,
                };

            }

            return [$token, $tokenType];
        }
    }

    protected function _parse_obj()
    {
        if ($this->_tt !== self::T_DICT_START) {
            throw new Exception('Invalid object definition');
        }

        $this->nexttoken();
        $object = [];
        while ($this->_t !== false) {
            switch ($this->_tt) {
                case self::T_FIELD:
                    $field = $this->_t;
                    $this->nexttoken();
                    $object[$field] = $this->_parse_value();
                    break;
                case self::T_DICT_END:
                    $this->nexttoken();

                    return new PDFValueObject($object);
                default:
                    throw new Exception('Invalid token: '.$this);
            }
        }

        return false;
    }

    protected function _parse_list()
    {
        if ($this->_tt !== self::T_LIST_START) {
            throw new Exception('Invalid list definition');
        }

        $this->nexttoken();
        $list = [];
        while ($this->_t !== false) {
            switch ($this->_tt) {
                case self::T_LIST_END:
                    $this->nexttoken();

                    return new PDFValueList($list);

                case self::T_OBJECT_BEGIN:
                case self::T_OBJECT_END:
                case self::T_STREAM_BEGIN:
                case self::T_STREAM_END:
                    throw new Exception('Invalid list definition');
                default:
                    $value = $this->_parse_value();
                    if ($value !== false) {
                        $list[] = $value;
                    }

                    break;
            }
        }

        return new PDFValueList($list);
    }

    protected function _parse_value()
    {
        while ($this->_t !== false) {
            switch ($this->_tt) {
                case self::T_DICT_START:
                    return $this->_parse_obj();
                case self::T_LIST_START:
                    return $this->_parse_list();
                case self::T_STRING:
                    $string = new PDFValueString($this->_t);
                    $this->nexttoken();

                    return $string;
                case self::T_HEX_STRING:
                    $string = new PDFValueHexString($this->_t);
                    $this->nexttoken();

                    return $string;
                case self::T_FIELD:
                    $field = new PDFValueType($this->_t);
                    $this->nexttoken();

                    return $field;
                case self::T_OBJECT_BEGIN:
                case self::T_STREAM_END:
                    throw new Exception('invalid keyword');
                case self::T_OBJECT_END:
                case self::T_STREAM_BEGIN:
                    return null;
                case self::T_COMMENT:
                    $this->nexttoken();
                    break;
                case self::T_SIMPLE:
                    $simpleValue = $this->_t;
                    $this->nexttoken();

                    while (($this->_t !== false) && ($this->_tt == self::T_SIMPLE)) {
                        $simpleValue .= ' '.$this->_t;
                        $this->nexttoken();
                    }

                    return new PDFValueSimple($simpleValue);
                default:
                    throw new Exception('Invalid token: '.$this);
            }
        }

        return false;
    }
}
