<?php

namespace Jeidison\PdfSigner;

use Exception;
use Stringable;

class Buffer implements Stringable
{
    protected string $buffer = '';

    protected int $bufferLen = 0;

    public function __construct($string = null)
    {
        if ($string === null) {
            $string = '';
        }

        $this->buffer = $string;
        $this->bufferLen = strlen((string) $string);
    }

    public function data(...$datas): void
    {
        foreach ($datas as $data) {
            $this->bufferLen += strlen((string) $data);
            $this->buffer .= $data;
        }
    }

    public function size(): int
    {
        return $this->bufferLen;
    }

    public function raw()
    {
        return $this->buffer;
    }

    public function append($b): static
    {
        if ($b::class !== static::class) {
            throw new Exception('invalid buffer to add to this one');
        }

        $this->buffer .= $b->raw();
        $this->bufferLen = strlen($this->buffer);

        return $this;
    }

    public function add(...$bs): Buffer
    {
        foreach ($bs as $b) {
            if ($b::class !== static::class) {
                throw new Exception('Invalid buffer to add to this one');
            }
        }

        $buffer = new Buffer($this->buffer);
        foreach ($bs as $b) {
            $buffer->append($b);
        }

        return $buffer;
    }

    public function __toString(): string
    {
        return $this->buffer;
    }
}
