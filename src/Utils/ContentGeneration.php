<?php

namespace Jeidison\PdfSigner\Utils;

final class ContentGeneration
{
    public static function tx($x, $y): string
    {
        return sprintf(' 1 0 0 1 %.2F %.2F cm', $x, $y);
    }

    public static function sx($w, $h): string
    {
        return sprintf(' %.2F 0 0 %.2F 0 0 cm', $w, $h);
    }

    public static function deg2rad($angle): float|int
    {
        return $angle * pi() / 180;
    }

    public static function rx($angle): string
    {
        $angle = deg2rad($angle);

        return sprintf(' %.2F %.2F %.2F %.2F 0 0 cm', cos($angle), sin($angle), -sin($angle), cos($angle));
    }
}
