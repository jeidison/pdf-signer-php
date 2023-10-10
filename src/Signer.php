<?php

namespace Jeidison\PdfSigner;

use DateTime;
use Exception;
use Jeidison\PdfSigner\PdfValue\PDFValueHexString;
use Jeidison\PdfSigner\PdfValue\PDFValueSimple;
use Jeidison\PdfSigner\Xref\Xref;

/**
 * @author Jeidison Farias <jeidison.farias@gmail.com>
 **/
class Signer
{
    private ?Signature $signature;

    private PdfDocument $pdfDocument;

    public function __construct()
    {
        $this->signature = Signature::new();
    }

    public static function new(): static
    {
        return new static();
    }

    public function withContent(string $pdfContent): self
    {
        $pdfDocument = new PdfDocument();
        $pdfDocument->setBufferFromString($pdfContent);

        return $this->withPdfDocument($pdfDocument);
    }

    private function withPdfDocument(PdfDocument $pdfDocument): self
    {
        $this->pdfDocument = $pdfDocument;

        return $this;
    }

    public function withMetadata(Metadata $metadata): self
    {
        $this->signature->withMetadata($metadata);

        return $this;
    }

    private function prepareDocumentToSign(): void
    {
        $structure = Struct::new()
            ->withPdfDocument($this->pdfDocument)
            ->structure();

        $trailer = $structure['trailer'];
        $version = $structure['version'];
        $xrefTable = $structure['xref'];
        $xrefPosition = $structure['xrefposition'];
        $revisions = $structure['revisions'];

        $pdfDocument = $this->pdfDocument;
        $pdfDocument->setPdfVersion($version);
        $pdfDocument->setTrailerObject($trailer);
        $pdfDocument->setXrefPosition($xrefPosition);
        $pdfDocument->setXrefTable($xrefTable);
        $pdfDocument->setXrefTableVersion($structure['xrefversion']);
        $pdfDocument->setRevisions($revisions);

        $oids = array_keys($xrefTable);
        sort($oids);
        $pdfDocument->setMaxOid(array_pop($oids));
        $pdfDocument->acquirePagesInfo();

        $this->signature->withPdfDocument($pdfDocument);
    }

    public function withCertificate(string $pathCertificate, string $password): self
    {
        $certFileContent = file_get_contents($pathCertificate);
        if ($certFileContent === false) {
            throw new Exception('Could not read file '.$pathCertificate);
        }

        if (openssl_pkcs12_read($certFileContent, $certificate, $password) === false) {
            throw new Exception('Could not get the certificates from file '.openssl_error_string());
        }

        $certInfo = openssl_x509_parse($certificate['cert']);
        $expirationDate = $certInfo['validTo_time_t'];

        if ($expirationDate < time()) {
            throw new Exception('Certificate has expired.');
        }

        $this->signature->withCertificate($certificate);

        return $this;
    }

    public function sign(): string
    {
        $this->prepareDocumentToSign();

        return $this->toBuffer();
    }

    private function toBuffer(): Buffer
    {
        if (! $this->signature->hasCertificate()) {
            return $this->pdfDocument->getBuffer();
        }

        $this->pdfDocument->updateModifyDate();
        $signature = $this->signature->generateSignatureInDocument();

        [$docToXref, $objOffSets] = Xref::new()
            ->withPdfDocument($this->pdfDocument)
            ->generateContentToXref();

        $xrefOffset = $docToXref->size();

        $objOffSets[$signature->getOid()] = $docToXref->size();
        $xrefOffset += strlen($signature->toPdfEntry());

        $docVersionString = str_replace('PDF-', '', $this->pdfDocument->getPdfVersion());

        $targetVersion = $this->pdfDocument->getXrefTableVersion();
        if ($this->pdfDocument->getXrefTableVersion() >= '1.5') {
            if ($docVersionString > $targetVersion) {
                $targetVersion = $docVersionString;
            }
        } elseif ($docVersionString < $targetVersion) {
            $targetVersion = $docVersionString;
        }

        if ($targetVersion >= '1.5') {
            $trailer = $this->pdfDocument->createObject(clone $this->pdfDocument->getTrailerObject());

            $objOffSets[$trailer->getOid()] = $xrefOffset;

            $xref = Xref::new()->buildXref15($objOffSets);

            $trailer['Index'] = explode(' ', (string) $xref['Index']);
            $trailer['W'] = $xref['W'];
            $trailer['Size'] = $this->pdfDocument->getMaxOid() + 1;
            $trailer['Type'] = '/XRef';

            $ID2 = md5(''.(new DateTime())->getTimestamp().'-'.$this->pdfDocument->getXrefPosition().$this->pdfDocument->getTrailerObject());
            $trailer['ID'] = [$trailer['ID'][0], new PDFValueHexString(strtoupper($ID2))];

            if (isset($trailer['DecodeParms'])) {
                unset($trailer['DecodeParms']);
            }

            if (isset($trailer['Filter'])) {
                unset($trailer['Filter']);
            }

            $trailer->setStream($xref['stream'], false);
            $trailer['Prev'] = $this->pdfDocument->getXrefPosition();

            $docFromXref = new Buffer($trailer->toPdfEntry());
            $docFromXref->data('startxref'.PHP_EOL.$xrefOffset.PHP_EOL.'%%EOF'.PHP_EOL);
        } else {
            $xrefContent = Xref::new()->buildXref($objOffSets);

            $this->pdfDocument->getTrailerObject()['Size'] = $this->pdfDocument->getMaxOid() + 1;
            $this->pdfDocument->getTrailerObject()['Prev'] = $this->pdfDocument->getXrefPosition();

            $docFromXref = new Buffer($xrefContent);
            $docFromXref->data("trailer\n".$this->pdfDocument->getTrailerObject());
            $docFromXref->data("\nstartxref\n{$xrefOffset}\n%%EOF\n");
        }

        $signature->withSizes($docToXref->size(), $docFromXref->size());
        $signature['Contents'] = new PDFValueSimple('');

        $signableDocument = new Buffer($docToXref->raw().$signature->toPdfEntry().$docFromXref->raw());

        $tmpFolder = sys_get_temp_dir();
        $tempFilename = tempnam($tmpFolder, 'pdfsign');
        $tempFile = fopen($tempFilename, 'wb');
        fwrite($tempFile, $signableDocument->raw());
        fclose($tempFile);

        $signatureContents = $this->signature->calculatePkcs7Signature($tempFilename, $tmpFolder);
        unlink($tempFilename);

        $signature['Contents'] = new PDFValueHexString($signatureContents);

        $docToXref->data($signature->toPdfEntry());

        return new Buffer($docToXref->raw().$docFromXref->raw());
    }
}
