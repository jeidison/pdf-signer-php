<?php

namespace Jeidison\PdfSigner\Utils;

use Exception;
use finfo;
use Jeidison\PdfSigner\PdfValue\PDFValueList;
use Jeidison\PdfSigner\PdfValue\PDFValueObject;
use Jeidison\PdfSigner\PdfValue\PDFValueReference;
use Jeidison\PdfSigner\PdfValue\PDFValueType;

final class Img
{
    public static function create_image_objects($info, $objectFactory)
    {
        $objects = [];

        $image = call_user_func($objectFactory,
            [
                'Type' => '/XObject',
                'Subtype' => '/Image',
                'Width' => $info['w'],
                'Height' => $info['h'],
                'ColorSpace' => [],
                'BitsPerComponent' => $info['bpc'],
                'Length' => strlen((string) $info['data']),
            ]
        );

        switch ($info['cs']) {
            case 'Indexed':
                $data = gzcompress((string) $info['pal']);
                $streamobject = call_user_func($objectFactory, [
                    'Filter' => '/FlateDecode',
                    'Length' => strlen($data),
                ]);
                $streamobject->set_stream($data);

                $image['ColorSpace']->push([
                    '/Indexed', '/DeviceRGB', (strlen((string) $info['pal']) / 3) - 1, new PDFValueReference($streamobject->get_oid()),
                ]);
                $objects[] = $streamobject;
                break;
            case 'DeviceCMYK':
                $image['Decode'] = new PDFValueList([1, 0, 1, 0, 1, 0, 1, 0]);
            default:
                $image['ColorSpace'] = new PDFValueType($info['cs']);
                break;
        }

        if (isset($info['f'])) {
            $image['Filter'] = new PDFValueType($info['f']);
        }

        if (isset($info['dp'])) {
            $image['DecodeParms'] = PDFValueObject::fromString($info['dp']);
        }

        if (isset($info['trns']) && is_array($info['trns'])) {
            $image['Mask'] = new PDFValueList($info['trns']);
        }

        if (isset($info['smask'])) {
            $smaskinfo = [
                'w' => $info['w'],
                'h' => $info['h'],
                'cs' => 'DeviceGray',
                'bpc' => 8,
                'f' => $info['f'],
                'dp' => '/Predictor 15 /Colors 1 /BitsPerComponent 8 /Columns '.$info['w'],
                'data' => $info['smask'],
            ];

            // In principle, it may return multiple objects
            $smasks = self::create_image_objects($smaskinfo, $objectFactory);
            foreach ($smasks as $smask) {
                $objects[] = $smask;
            }

            $image['SMask'] = new PDFValueReference($smask->get_oid());
        }

        $image->set_stream($info['data']);
        array_unshift($objects, $image);

        return $objects;
    }

    public static function add_image($objectFactory, $filename, $x = 0, $y = 0, $w = 0, $h = 0, $angle = 0, $keepProportions = true)
    {
        if (empty($filename)) {
            throw new Exception('invalid image name or stream');
        }

        if ($filename[0] === '@') {
            $filecontent = substr((string) $filename, 1);
        } elseif (Str::isBase64($filename)) {
            $filecontent = base64_decode((string) $filename);
        } else {
            $filecontent = @file_get_contents($filename);

            if ($filecontent === false) {
                throw new Exception('failed to get the image');
            }
        }

        $finfo = new finfo();
        $contentType = $finfo->buffer($filecontent, FILEINFO_MIME_TYPE);

        $ext = Mime::mimeToExt($contentType);

        // TODO: support more image types than jpg
        $addAlpha = false;
        switch ($ext) {
            case 'jpg':
            case 'jpeg':
                $info = parseJpg($filecontent);
                break;
            case 'png':
                $addAlpha = true;
                $info = parsePng($filecontent);
                break;
            default:
                throw new Exception('unsupported mime type');
        }

        // Generate a new identifier for the image
        $info['i'] = 'Im'.Str::random(4);

        if ($w === null) {
            $w = -96;
        }

        if ($h === null) {
            $h = -96;
        }

        if ($w < 0) {
            $w = -$info['w'] * 72 / $w;
        }

        if ($h < 0) {
            $h = -$info['h'] * 72 / $h;
        }

        if ($w == 0) {
            $w = $h * $info['w'] / $info['h'];
        }

        if ($h == 0) {
            $h = $w * $info['h'] / $info['w'];
        }

        $imagesObjects = self::create_image_objects($info, $objectFactory);

        // Generate the command to translate and scale the image
        if ($keepProportions) {
            $angleRads = deg2rad($angle);
            $W = abs($w * cos($angleRads) + $h * sin($angleRads));
            $H = abs($w * sin($angleRads) + $h * cos($angleRads));
            $rW = $W / $w;
            $rH = $H / $h;
            $r = min($rW, $rH);
            $w = $W * $r;
            $h = $H * $r;
        }

        $data = 'q';
        $data .= ContentGeneration::tx($x, $y);
        $data .= ContentGeneration::sx($w, $h);
        if ($angle != 0) {
            $data .= ContentGeneration::tx(0.5, 0.5);
            $data .= ContentGeneration::rx($angle);
            $data .= ContentGeneration::tx(-0.5, -0.5);
        }

        $data .= sprintf(' /%s Do Q', $info['i']);

        $resources = new PDFValueObject([
            'ProcSet' => ['/PDF', '/Text', '/ImageB', '/ImageC', '/ImageI'],
            'XObject' => new PDFValueObject([
                $info['i'] => new PDFValueReference($imagesObjects[0]->get_oid()),
            ]),
        ]);

        return ['image' => $imagesObjects[0], 'command' => $data, 'resources' => $resources, 'alpha' => $addAlpha];
    }
}
