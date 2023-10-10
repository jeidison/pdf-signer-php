<?php

namespace Jeidison\PdfSigner;

/**
 * @author Jeidison Farias <jeidison.farias@gmail.com>
 **/
class Metadata
{
    private ?string $name = null;

    private ?string $location = null;

    private ?string $contactInfo = null;

    private ?string $reason = null;

    public static function new(): self
    {
        return new self();
    }

    public function withName(?string $name): self
    {
        if ($name) {
            $this->name = $name;
        }

        return $this;
    }

    public function withReason(?string $reason): self
    {
        if ($reason) {
            $this->reason = $reason;
        }

        return $this;
    }

    public function withLocation(?string $location): self
    {
        if ($location) {
            $this->location = $location;
        }

        return $this;
    }

    public function withContactInfo(?string $contact): self
    {
        if ($contact) {
            $this->contactInfo = $contact;
        }

        return $this;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function getLocation(): ?string
    {
        return $this->location;
    }

    public function getContactInfo(): ?string
    {
        return $this->contactInfo;
    }

    public function getReason(): ?string
    {
        return $this->reason;
    }
}
