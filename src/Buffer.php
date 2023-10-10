<?php

namespace Jeidison\PdfSigner;

use Stringable;

/**
 * @author Jeidison Farias <jeidison.farias@gmail.com>
 **/
class Buffer implements Stringable
{
    protected string $buffer;

    protected int $bufferLen = 0;

    public function __construct(string $string = null)
    {
        if ($string === null) {
            $string = '';
        }

        $this->buffer = $string;
        $this->bufferLen = strlen($string);
    }

    public function data(...$datas): void
    {
        foreach ($datas as $data) {
            $this->bufferLen += strlen($data);
            $this->buffer .= $data;
        }
    }

    public function size(): int
    {
        return $this->bufferLen;
    }

    public function raw(): ?string
    {
        return $this->buffer;
    }

    public function append(Buffer $b): static
    {
        $this->buffer .= $b->raw();
        $this->bufferLen = strlen($this->buffer);

        return $this;
    }

    public function add(...$bs): Buffer
    {
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
