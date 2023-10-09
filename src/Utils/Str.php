<?php

namespace Jeidison\PdfSigner\Utils;

final class Str
{
    public static function isBase64($string): bool
    {
        if (! preg_match('/^[a-zA-Z0-9\/\r\n+]*={0,2}$/', (string) $string)) {
            return false;
        }

        $decoded = base64_decode((string) $string, true);
        if ($decoded === false) {
            return false;
        }

        if (base64_encode($decoded) != $string) {
            return false;
        }

        return true;
    }

    public static function random($length = 8, $extended = false, $hard = false): string
    {
        $token = '';
        $codeAlphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789';
        if ($extended === true) {
            $codeAlphabet .= "!\"#$%&'()*+,-./:;<=>?@[\\]_{}";
        }

        if ($hard === true) {
            $codeAlphabet .= '^`|~';
        }

        $max = strlen($codeAlphabet);
        for ($i = 0; $i < $length; $i++) {
            $token .= $codeAlphabet[random_int(0, $max - 1)];
        }

        return $token;
    }
}
