<?php

use Jeidison\PdfSigner\StreamReader;

function parseJpg($filecontent)
{
    // Extract info from a JPEG file
    $a = getimagesizefromstring($filecontent);
    if (! $a) {
        throw new Exception('Missing or incorrect image');
    }

    if ($a[2] != 2) {
        throw new Exception('Not a JPEG image');
    }

    if (! isset($a['channels']) || $a['channels'] == 3) {
        $colspace = 'DeviceRGB';
    } elseif ($a['channels'] == 4) {
        $colspace = 'DeviceCMYK';
    } else {
        $colspace = 'DeviceGray';
    }

    $bpc = $a['bits'] ?? 8;
    $data = $filecontent;

    return ['w' => $a[0], 'h' => $a[1], 'cs' => $colspace, 'bpc' => $bpc, 'f' => 'DCTDecode', 'data' => $data];
}

function parsePng(string $fileContent)
{
    $f = new StreamReader($fileContent);

    return parsePngStream($f);
}

function parsePngStream(&$f)
{
    // Check signature
    if (($res = readStream($f, 8)) != chr(137).'PNG'.chr(13).chr(10).chr(26).chr(10)) {
        throw new Exception('Not a PNG image '.$res);
    }

    // Read header chunk
    readStream($f, 4);
    if (readStream($f, 4) != 'IHDR') {
        throw new Exception('Incorrect PNG image');
    }

    $w = readInt($f);
    $h = readInt($f);
    $bpc = ord(readStream($f, 1));
    if ($bpc > 8) {
        throw new Exception('16-bit depth not supported');
    }

    $ct = ord(readStream($f, 1));
    if ($ct == 0 || $ct == 4) {
        $colspace = 'DeviceGray';
    } elseif ($ct == 2 || $ct == 6) {
        $colspace = 'DeviceRGB';
    } elseif ($ct == 3) {
        $colspace = 'Indexed';
    } else {
        throw new Exception('Unknown color type');
    }

    if (ord(readStream($f, 1)) != 0) {
        throw new Exception('Unknown compression method');
    }

    if (ord(readStream($f, 1)) !== 0) {
        throw new Exception('Unknown filter method');
    }

    if (ord(readStream($f, 1)) !== 0) {
        throw new Exception('Interlacing not supported');
    }

    readStream($f, 4);
    $dp = '/Predictor 15 /Colors '.($colspace == 'DeviceRGB' ? 3 : 1).' /BitsPerComponent '.$bpc.' /Columns '.$w;

    // Scan chunks looking for palette, transparency and image data
    $pal = '';
    $trns = '';
    $data = '';
    do {
        $n = readInt($f);
        $type = readStream($f, 4);
        if ($type == 'PLTE') {
            // Read palette
            $pal = readStream($f, $n);
            readStream($f, 4);
        } elseif ($type == 'tRNS') {
            // Read transparency info
            $t = readStream($f, $n);
            if ($ct == 0) {
                $trns = [ord(substr((string) $t, 1, 1))];
            } elseif ($ct == 2) {
                $trns = [ord(substr((string) $t, 1, 1)), ord(substr((string) $t, 3, 1)), ord(substr((string) $t, 5, 1))];
            } else {
                $pos = strpos((string) $t, chr(0));
                if ($pos !== false) {
                    $trns = [$pos];
                }
            }

            readStream($f, 4);
        } elseif ($type == 'IDAT') {
            // Read image data block
            $data .= readStream($f, $n);
            readStream($f, 4);
        } elseif ($type == 'IEND') {
            break;
        } else {
            readStream($f, $n + 4);
        }
    } while ($n);

    if ($colspace == 'Indexed' && empty($pal)) {
        throw new Exception('Missing palette in image');
    }

    $info = ['w' => $w, 'h' => $h, 'cs' => $colspace, 'bpc' => $bpc, 'f' => 'FlateDecode', 'dp' => $dp, 'pal' => $pal, 'trns' => $trns];
    if ($ct >= 4) {
        // Extract alpha channel
        $data = gzuncompress($data);
        if ($data === false) {
            throw new Exception('failed to uncompress the image');
        }

        $color = '';
        $alpha = '';
        if ($ct == 4) {
            // Gray image
            $len = 2 * $w;
            for ($i = 0; $i < $h; $i++) {
                $pos = (1 + $len) * $i;
                $color .= $data[$pos];
                $alpha .= $data[$pos];
                $line = substr($data, $pos + 1, $len);
                $color .= preg_replace('/(.)./s', '$1', $line);
                $alpha .= preg_replace('/.(.)/s', '$1', $line);
            }
        } else {
            // RGB image
            $len = 4 * $w;
            for ($i = 0; $i < $h; $i++) {
                $pos = (1 + $len) * $i;
                $color .= $data[$pos];
                $alpha .= $data[$pos];
                $line = substr($data, $pos + 1, $len);
                $color .= preg_replace('/(.{3})./s', '$1', $line);
                $alpha .= preg_replace('/.{3}(.)/s', '$1', $line);
            }
        }

        unset($data);
        $data = gzcompress($color);
        $info['smask'] = gzcompress($alpha);
    }

    $info['data'] = $data;

    return $info;
}

function readStream($f, $n): string
{
    $res = '';

    while ($n > 0 && ! $f->eos()) {
        $s = $f->nextchars($n);
        if ($s === false) {
            throw new Exception('Error while reading the stream');
        }

        $n -= strlen((string) $s);
        $res .= $s;
    }

    if ($n > 0) {
        throw new Exception('Unexpected end of stream');
    }

    return $res;
}

function readInt($f): int
{
    // Read a 4-byte integer from stream
    $a = unpack('Ni', readStream($f, 4));

    return $a['i'];
}
