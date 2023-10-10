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

    private Metadata $metadata;

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

    public function withMetadata(Metadata $metadata): self
    {
        $this->metadata = $metadata;

        return $this;
    }

    public function hasCertificate(): bool
    {
        return ! empty($this->certificate['cert']);
    }

    public function generateSignatureInDocument(): SignatureObject
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

        if (($root === false) || (($root = $root->getObjectReferenced()) === false)) {
            throw new Exception('Could not find the root object from the trailer');
        }

        $rootObj = $this->pdfDocument->getObject($root);
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
        if ((($referenced = $annots->getObjectReferenced()) !== false) && (! is_array($referenced))) {
            $newannots = $this->pdfDocument->createObject(
                $this->pdfDocument->getObject($referenced)->getValue()
            );
        } else {
            $newannots = $this->pdfDocument->createObject(new PDFValueList());
            $newannots->push($annots);
        }

        $annotationObject = $this->pdfDocument->createObject([
            'Type' => '/Annot',
            'Subtype' => '/Widget',
            'FT' => '/Sig',
            'V' => new PDFValueString(''),
            'T' => new PDFValueString('Signature'.Str::random()),
            'P' => new PDFValueReference($pageObj->getOid()),
            'Rect' => $rectToAppear,
            'F' => 132,  // TODO: check this value
        ]
        );

        $signature = $this->pdfDocument->createObject([], SignatureObject::class, false);
        $annotationObject['V'] = new PDFValueReference($signature->getOid());

        if ($imageFileName !== null) {
            $pagesize = $this->pdfDocument->get_page_size($pageToAppear);
            $pagesize = explode(' ', (string) $pagesize[0]->val());
            $pagesizeH = (float) ($pagesize[3]) - (float) ($pagesize[1]);

            $bbox = [0, 0, $rectToAppear[2] - $rectToAppear[0], $rectToAppear[3] - $rectToAppear[1]];
            $formObject = $this->pdfDocument->createObject([
                'BBox' => $bbox,
                'Subtype' => '/Form',
                'Type' => '/XObject',
                'Group' => [
                    'Type' => '/Group',
                    'S' => '/Transparency',
                    'CS' => '/DeviceRGB',
                ],
            ]);

            $containerFormObject = $this->pdfDocument->createObject([
                'BBox' => $bbox,
                'Subtype' => '/Form',
                'Type' => '/XObject',
                'Resources' => ['XObject' => [
                    'n0' => new PDFValueSimple(''),
                    'n2' => new PDFValueSimple(''),
                ]],
            ]);
            $containerFormObject->setStream("q 1 0 0 1 0 0 cm /n0 Do Q\nq 1 0 0 1 0 0 cm /n2 Do Q\n", false);

            $layerN0 = $this->pdfDocument->createObject([
                'BBox' => [0.0, 0.0, 100.0, 100.0],
                'Subtype' => '/Form',
                'Type' => '/XObject',
                'Resources' => new PDFValueObject(),
            ]);

            $layerN0->setStream('% DSBlank'.PHP_EOL, false);

            $layerN2 = $this->pdfDocument->createObject([
                'BBox' => $bbox,
                'Subtype' => '/Form',
                'Type' => '/XObject',
                'Resources' => new PDFValueObject(),
            ]);

            $result = Img::add_image($this->pdfDocument->createObject(...), $imageFileName, $bbox[0], $bbox[1], $bbox[2], $bbox[3], $pageRotation->val());

            $layerN2['Resources'] = $result['resources'];
            $layerN2->setStream($result['command'], false);

            $containerFormObject['Resources']['XObject']['n0'] = new PDFValueReference($layerN0->getOid());
            $containerFormObject['Resources']['XObject']['n2'] = new PDFValueReference($layerN2->getOid());

            $formObject['Resources'] = new PDFValueObject([
                'XObject' => [
                    'FRM' => new PDFValueReference($containerFormObject->getOid()),
                ],
            ]);
            $formObject->setStream('/FRM Do', false);

            $annotationObject['AP'] = ['N' => new PDFValueReference($formObject->getOid())];
            $annotationObject['Rect'] = [$rectToAppear[0], $pagesizeH - $rectToAppear[1], $rectToAppear[2], $pagesizeH - $rectToAppear[3]];
        }

        if (! $newannots->push(new PDFValueReference($annotationObject->getOid()))) {
            throw new Exception('Could not update the page where the signature has to appear');
        }

        $pageObj['Annots'] = new PDFValueReference($newannots->getOid());
        $updatedObjects[] = $pageObj;

        if (! isset($rootObj['AcroForm'])) {
            $rootObj['AcroForm'] = new PDFValueObject();
        }

        $acroForm = $rootObj['AcroForm'];
        if ((($referenced = $acroForm->getObjectReferenced()) !== false) && (! is_array($referenced))) {
            $updatedObjects[] = $acroForm = $this->pdfDocument->getObject($referenced);
        } else {
            $updatedObjects[] = $rootObj;
        }

        $acroForm['SigFlags'] = 3;
        if (! isset($acroForm['Fields'])) {
            $acroForm['Fields'] = new PDFValueList();
        }

        if (! $acroForm['Fields']->push(new PDFValueReference($annotationObject->getOid()))) {
            throw new Exception('Could not create the signature field');
        }

        foreach ($updatedObjects as $object) {
            $this->pdfDocument->addObject($object);
        }

        return $signature->withName($this->metadata->getName())
            ->withLocation($this->metadata->getLocation())
            ->withReason($this->metadata->getReason())
            ->withContactInfo($this->metadata->getContactInfo());
    }

    public function calculatePkcs7Signature($fileNameToSign, $tmpFolder = '/tmp'): string
    {
        $filesizeOriginal = filesize($fileNameToSign);
        if ($filesizeOriginal === false) {
            throw new Exception('Could not open file '.$fileNameToSign);
        }

        $tempFilename = tempnam($tmpFolder, 'pdfsign');
        if ($tempFilename === false) {
            throw new Exception('Could not create a temporary filename');
        }

        if (! openssl_pkcs7_sign($fileNameToSign, $tempFilename, $this->certificate['cert'], $this->certificate['pkey'], [], PKCS7_BINARY | PKCS7_DETACHED)) {
            unlink($tempFilename);

            throw new Exception('Failed to sign file '.$fileNameToSign);
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
