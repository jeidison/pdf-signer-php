<?php

namespace Jeidison\PdfSigner;

use Jeidison\PdfSigner\PdfValue\PDFValue;
use Jeidison\PdfSigner\PdfValue\PDFValueObject;
use Jeidison\PdfSigner\PdfValue\PDFValueReference;
use Jeidison\PdfSigner\PdfValue\PDFValueSimple;
use Jeidison\PdfSigner\Utils\Img;

class SignatureAppearance
{
    private ?string $imageFileName = null;

    private array $rectToAppear = [0, 0, 0, 0];

    private int $pageToAppear = 0;

    private PdfDocument $pdfDocument;

    private PDFObject $annotationObject;

    private PDFValue $pageRotation;

    public static function new(): self
    {
        return new self();
    }

    public function getReact(): array
    {
        return $this->rectToAppear;
    }

    public function getPageToAppear(): int
    {
        return $this->pageToAppear;
    }

    public function getImage(): ?string
    {
        return $this->imageFileName;
    }

    public function addSignAppearanceInPage(int $pageToAppear): self
    {
        $this->pageToAppear = $pageToAppear;

        return $this;
    }

    public function withRect(array $rect): self
    {
        $this->rectToAppear = $rect;

        return $this;
    }

    public function withImage(?string $imageFileName): self
    {
        $this->imageFileName = $imageFileName;

        return $this;
    }

    public function withPdfDocument(PdfDocument $pdfDocument): self
    {
        $this->pdfDocument = $pdfDocument;

        return $this;
    }

    public function withAnnotationObject(PDFObject $annotationObject): self
    {
        $this->annotationObject = $annotationObject;

        return $this;
    }

    public function withPageRotate(PDFValue $pageRotation): self
    {
        $this->pageRotation = $pageRotation;

        return $this;
    }

    public function generate(): PDFObject
    {
        $pageSize = $this->pdfDocument->getPageInfo()->getPageSize($this->pageToAppear);
        $pageSize = explode(' ', (string) $pageSize[0]->val());
        $pageSizeH = (float) ($pageSize[3]) - (float) ($pageSize[1]);

        $bbox = [0, 0, $this->rectToAppear[2] - $this->rectToAppear[0], $this->rectToAppear[3] - $this->rectToAppear[1]];
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

        $result = Img::addImage(
            $this->pdfDocument->createObject(...),
            $this->imageFileName,
            $bbox[0],
            $bbox[1],
            $bbox[2],
            $bbox[3],
            $this->pageRotation->val()
        );

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

        $this->annotationObject['AP'] = ['N' => new PDFValueReference($formObject->getOid())];
        $this->annotationObject['Rect'] = [
            $this->rectToAppear[0],
            $pageSizeH - $this->rectToAppear[1],
            $this->rectToAppear[2],
            $pageSizeH - $this->rectToAppear[3],
        ];

        return $this->annotationObject;
    }
}
