<?php

namespace Jeidison\PdfSigner;

use Exception;
use Jeidison\PdfSigner\PdfValue\PDFValueList;
use Jeidison\PdfSigner\PdfValue\PDFValueObject;
use Jeidison\PdfSigner\PdfValue\PDFValueReference;
use Jeidison\PdfSigner\PdfValue\PDFValueSimple;
use Jeidison\PdfSigner\PdfValue\PDFValueString;
use Jeidison\PdfSigner\Utils\Img;
use Jeidison\PdfSigner\Utils\Str;

class Signature
{
    const SIGNATURE_MAX_LENGTH = 11742;

    private array $certificate = [
        'cert' => '',
        'pkey' => '',
        'extracerts' => '',
    ];

    private ?array $appearance = null;

    private PdfDocument $pdfDocument;

    public static function new(): self
    {
        return new self();
    }

    public function withCertificate(array $certificate): self
    {
        $this->certificate = $certificate;

        return $this;
    }

    public function withAppearance(array $appearance): self
    {
        $this->appearance = $appearance;

        return $this;
    }

    public function withoutAppearance(): self
    {
        $this->appearance = null;

        return $this;
    }

    public function withPdfDocument(PdfDocument $pdfDocument): self
    {
        $this->pdfDocument = $pdfDocument;

        return $this;
    }

    public function hasCertificate(): bool
    {
        return !empty($this->certificate['cert']);
    }

    public function generate_signature_in_document(): PDFObject
    {
        $imageFileName = null;
        $rectToAppear = [0, 0, 0, 0];
        $pageToAppear = 0;

        if ($this->appearance !== null) {
            $imageFileName = $this->appearance['image'];
            $rectToAppear = $this->appearance['rect'];
            $pageToAppear = $this->appearance['page'];
        }

        $trailerObject = $this->pdfDocument->getTrailerObject();
        $root = $trailerObject['Root'];

        if (($root === false) || (($root = $root->get_object_referenced()) === false)) {
            throw new Exception('Could not find the root object from the trailer');
        }

        $rootObj = $this->pdfDocument->get_object($root);
        if ($rootObj === false) {
            throw new Exception('Invalid root object');
        }

        $pageObj = $this->pdfDocument->get_page($pageToAppear);
        if ($pageObj === false) {
            throw new Exception('Invalid page');
        }

        $updatedObjects = [];
        if (! isset($pageObj['Annots'])) {
            $pageObj['Annots'] = new PDFValueList();
        }

        $annots = &$pageObj['Annots'];
        $pageRotation = $pageObj['Rotate'] ?? new PDFValueSimple(0);
        if ((($referenced = $annots->get_object_referenced()) !== false) && (! is_array($referenced))) {
            $newannots = $this->pdfDocument->create_object(
                $this->pdfDocument->get_object($referenced)->get_value()
            );
        } else {
            $newannots = $this->pdfDocument->create_object(new PDFValueList());
            $newannots->push($annots);
        }

        $annotationObject = $this->pdfDocument->create_object([
                'Type' => '/Annot',
                'Subtype' => '/Widget',
                'FT' => '/Sig',
                'V' => new PDFValueString(''),
                'T' => new PDFValueString('Signature'.Str::random()),
                'P' => new PDFValueReference($pageObj->get_oid()),
                'Rect' => $rectToAppear,
                'F' => 132,  // TODO: check this value
            ]
        );

        $signature = $this->pdfDocument->create_object([], PDFSignatureObject::class, false);
        $annotationObject['V'] = new PDFValueReference($signature->get_oid());

        if ($imageFileName !== null) {
            $pagesize = $this->pdfDocument->get_page_size($pageToAppear);
            $pagesize = explode(' ', (string) $pagesize[0]->val());
            $pagesizeH = (float) ($pagesize[3]) - (float) ($pagesize[1]);

            $bbox = [0, 0, $rectToAppear[2] - $rectToAppear[0], $rectToAppear[3] - $rectToAppear[1]];
            $formObject = $this->pdfDocument->create_object([
                'BBox' => $bbox,
                'Subtype' => '/Form',
                'Type' => '/XObject',
                'Group' => [
                    'Type' => '/Group',
                    'S' => '/Transparency',
                    'CS' => '/DeviceRGB',
                ],
            ]);

            $containerFormObject = $this->pdfDocument->create_object([
                'BBox' => $bbox,
                'Subtype' => '/Form',
                'Type' => '/XObject',
                'Resources' => ['XObject' => [
                    'n0' => new PDFValueSimple(''),
                    'n2' => new PDFValueSimple(''),
                ]],
            ]);
            $containerFormObject->set_stream("q 1 0 0 1 0 0 cm /n0 Do Q\nq 1 0 0 1 0 0 cm /n2 Do Q\n", false);

            $layerN0 = $this->pdfDocument->create_object([
                'BBox' => [0.0, 0.0, 100.0, 100.0],
                'Subtype' => '/Form',
                'Type' => '/XObject',
                'Resources' => new PDFValueObject(),
            ]);

            $layerN0->set_stream('% DSBlank'.PHP_EOL, false);

            $layerN2 = $this->pdfDocument->create_object([
                'BBox' => $bbox,
                'Subtype' => '/Form',
                'Type' => '/XObject',
                'Resources' => new PDFValueObject(),
            ]);

            $result = Img::add_image($this->pdfDocument->create_object(...), $imageFileName, $bbox[0], $bbox[1], $bbox[2], $bbox[3], $pageRotation->val());

            $layerN2['Resources'] = $result['resources'];
            $layerN2->set_stream($result['command'], false);

            $containerFormObject['Resources']['XObject']['n0'] = new PDFValueReference($layerN0->get_oid());
            $containerFormObject['Resources']['XObject']['n2'] = new PDFValueReference($layerN2->get_oid());

            $formObject['Resources'] = new PDFValueObject([
                'XObject' => [
                    'FRM' => new PDFValueReference($containerFormObject->get_oid()),
                ],
            ]);
            $formObject->set_stream('/FRM Do', false);

            $annotationObject['AP'] = ['N' => new PDFValueReference($formObject->get_oid())];
            $annotationObject['Rect'] = [$rectToAppear[0], $pagesizeH - $rectToAppear[1], $rectToAppear[2], $pagesizeH - $rectToAppear[3]];
        }

        if (! $newannots->push(new PDFValueReference($annotationObject->get_oid()))) {
            throw new Exception('Could not update the page where the signature has to appear');
        }

        $pageObj['Annots'] = new PDFValueReference($newannots->get_oid());
        $updatedObjects[] = $pageObj;

        if (! isset($rootObj['AcroForm'])) {
            $rootObj['AcroForm'] = new PDFValueObject();
        }

        $acroform = $rootObj['AcroForm'];
        if ((($referenced = $acroform->get_object_referenced()) !== false) && (! is_array($referenced))) {
            $acroform = $this->pdfDocument->get_object($referenced);
            $updatedObjects[] = $acroform;
        } else {
            $updatedObjects[] = $rootObj;
        }

        $acroform['SigFlags'] = 3;
        if (! isset($acroform['Fields'])) {
            $acroform['Fields'] = new PDFValueList();
        }

        if (! $acroform['Fields']->push(new PDFValueReference($annotationObject->get_oid()))) {
            throw new Exception('could not create the signature field');
        }

        foreach ($updatedObjects as $object) {
            $this->pdfDocument->add_object($object);
        }

        return $signature;
    }

    public function calculatePkcs7Signature($fileNameToSign, $tmpFolder = '/tmp'): string
    {
        $filesizeOriginal = filesize($fileNameToSign);
        if ($filesizeOriginal === false) {
            throw new Exception('Could not open file ' . $fileNameToSign);
        }

        $tempFilename = tempnam($tmpFolder, 'pdfsign');
        if ($tempFilename === false) {
            throw new Exception('Could not create a temporary filename');
        }

        if (!openssl_pkcs7_sign($fileNameToSign, $tempFilename, $this->certificate['cert'], $this->certificate['pkey'], [], PKCS7_BINARY | PKCS7_DETACHED)) {
            unlink($tempFilename);

            throw new Exception('Failed to sign file ' . $fileNameToSign);
        }

        $signature = file_get_contents($tempFilename);

        $signature = substr($signature, $filesizeOriginal);
        $signature = substr($signature, (strpos($signature, "%%EOF\n\n------") + 13));

        $tmpArr = explode("\n\n", $signature);
        $signature = $tmpArr[1];

        $signature = base64_decode(trim($signature));
        $signature = current(unpack('H*', $signature));

        return str_pad($signature, self::SIGNATURE_MAX_LENGTH, '0');
    }
}