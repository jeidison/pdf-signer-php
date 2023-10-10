<?php

namespace Jeidison\PdfSigner;

use Exception;
use Jeidison\PdfSigner\PdfValue\PDFValueList;
use Jeidison\PdfSigner\PdfValue\PDFValueObject;
use Jeidison\PdfSigner\PdfValue\PDFValueReference;
use Jeidison\PdfSigner\PdfValue\PDFValueSimple;
use Jeidison\PdfSigner\PdfValue\PDFValueString;
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

    private SignatureAppearance $appearance;

    private PdfDocument $pdfDocument;

    public function __construct()
    {
        $this->appearance = SignatureAppearance::new();
    }

    public static function new(): self
    {
        return new self();
    }

    public function withCertificate(array $certificate): self
    {
        $this->certificate = $certificate;

        return $this;
    }

    public function withAppearance(SignatureAppearance $appearance): self
    {
        $this->appearance = $appearance;

        return $this;
    }

    public function withoutAppearance(): self
    {
        $this->appearance->withImage(null);

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
        $rectToAppear = $this->appearance->getReact();
        $pageToAppear = $this->appearance->getPageToAppear();

        $trailerObject = $this->pdfDocument->getTrailerObject();
        $root = $trailerObject['Root'];

        if (($root === false) || (($root = $root->getObjectReferenced()) === false)) {
            throw new Exception('Could not find the root object from the trailer');
        }

        $rootObj = $this->pdfDocument->getObject($root);
        if ($rootObj == null) {
            throw new Exception('Invalid root object');
        }

        $pageObj = $this->pdfDocument->getPageInfo()->getPage($pageToAppear);
        if ($pageObj == null) {
            throw new Exception('Invalid page');
        }

        $updatedObjects = [];
        if (! isset($pageObj['Annots'])) {
            $pageObj['Annots'] = new PDFValueList();
        }

        $annots = $pageObj['Annots'];

        if ((($referenced = $annots->getObjectReferenced()) !== false) && (! is_array($referenced))) {
            $newAnnots = $this->pdfDocument->createObject(
                $this->pdfDocument->getObject($referenced)->getValue()
            );
        } else {
            $newAnnots = $this->pdfDocument->createObject(new PDFValueList());
            $newAnnots->push($annots);
        }

        $annotationObject = $this->pdfDocument->createObject([
            'Type' => '/Annot',
            'Subtype' => '/Widget',
            'FT' => '/Sig',
            'V' => new PDFValueString(''),
            'T' => new PDFValueString('Signature'.Str::random()),
            'P' => new PDFValueReference($pageObj->getOid()),
            'Rect' => $rectToAppear,
            'F' => 132,
        ]);

        $signature = $this->pdfDocument->createObject([], SignatureObject::class, false);
        $annotationObject['V'] = new PDFValueReference($signature->getOid());

        if ($this->appearance->getImage() != null) {
            $pageRotation = $pageObj['Rotate'] ?? new PDFValueSimple(0);

            $annotationObject = $this->appearance
                ->withPageRotate($pageRotation)
                ->withAnnotationObject($annotationObject)
                ->withPdfDocument($this->pdfDocument)
                ->generate();
        }

        if (! $newAnnots->push(new PDFValueReference($annotationObject->getOid()))) {
            throw new Exception('Could not update the page where the signature has to appear');
        }

        $pageObj['Annots'] = new PDFValueReference($newAnnots->getOid());
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

        /** @var SignatureObject $signature */
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
