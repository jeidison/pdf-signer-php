<?php

namespace Jeidison\PdfSigner\Utils;

use DateTime;

final class Date
{
    public static function toPdfDateString(?DateTime $date = null): string
    {
        $date ??= new DateTime();
        $timestamp = $date->getTimestamp();
        $formatted = substr_replace(date('YmdHisO', $timestamp), "'", (0 - 2), 0)."'";

        return 'D:'.$formatted;
    }
}