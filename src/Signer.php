<?php

namespace Jeidison\PdfSigner;

use DateTime;
use Exception;
use Jeidison\PdfSigner\PdfValue\PDFValueHexString;
use Jeidison\PdfSigner\PdfValue\PDFValueSimple;
use Jeidison\PdfSigner\Xref\Xref;

class Signer
{
    private ?int $depth = null;
    private ?Signature $signature;

    private PdfDocument $pdfDocument;

    private array $backupState = [];

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
        $this->withPdfDocument($pdfDocument);

        return $this;
    }

    public function withDepth(?int $depth): self
    {
        $this->depth = $depth;

        return $this;
    }

    public function withPdfDocument(PdfDocument $pdfDocument): self
    {
        $this->pdfDocument = $pdfDocument;

        return $this;
    }

    public function prepareDocumentToSign(): self
    {
        $structure = Struct::new()
            ->withDepth($this->depth)
            ->withPdfDocument($this->pdfDocument)
            ->structure();

        $trailer      = $structure['trailer'];
        $version      = $structure['version'];
        $xrefTable    = $structure['xref'];
        $xrefPosition = $structure['xrefposition'];
        $revisions    = $structure['revisions'];

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
        $pdfDocument->acquire_pages_info();

        $this->signature->withPdfDocument($pdfDocument);

        return $this;
    }

    public function withCertificate(string $pathCertificate, string $password): self
    {
        $certFileContent = file_get_contents($pathCertificate);
        if ($certFileContent === false) {
            throw new Exception('Could not read file ' . $pathCertificate);
        }

        if (openssl_pkcs12_read($certFileContent, $certificate, $password) === false) {
            throw new Exception('Could not get the certificates from file ' . openssl_error_string());
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

        return $this->to_pdf_file_b();
    }

    public function pushState(): void
    {
        $clonedObjects = [];
        foreach ($this->pdfDocument->getPdfObjects() as $oid => $object) {
            $clonedObjects[$oid] = clone $object;
        }

        $this->backupState[] = [
            'max_oid'     => $this->pdfDocument->getMaxOid(),
            'pdf_objects' => $clonedObjects,
        ];
    }

    public function popState(): bool
    {
        if (count($this->backupState) > 0) {
            $state = array_pop($this->backupState);
            $this->pdfDocument->setMaxOid($state['max_oid']);
            $this->pdfDocument->setPdfObjects($state['pdf_objects']);

            return true;
        }

        return false;
    }

    private function to_pdf_file_b(): Buffer
    {
        if (!$this->signature->hasCertificate()) {
            return $this->pdfDocument->getBuffer();
        }

        $this->pushState();
        $this->pdfDocument->update_mod_date();

        $signature = $this->signature->generate_signature_in_document();
        if (!$signature) {
            $this->popState();

            throw new Exception('could not generate the signed document');
        }

        [$_doc_to_xref, $_obj_offsets] = $this->pdfDocument->generate_content_to_xref();
        $xrefOffset = $_doc_to_xref->size();

        $_obj_offsets[$signature->get_oid()] = $_doc_to_xref->size();
        $xrefOffset += strlen((string) $signature->to_pdf_entry());

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
            $trailer = $this->pdfDocument->create_object(clone $this->pdfDocument->getTrailerObject());

            $_obj_offsets[$trailer->get_oid()] = $xrefOffset;

            $xref = Xref::new()->build_xref_1_5($_obj_offsets);

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

            $trailer->set_stream($xref['stream'], false);
            $trailer['Prev'] = $this->pdfDocument->getXrefPosition();

            $_doc_from_xref = new Buffer($trailer->to_pdf_entry());
            $_doc_from_xref->data('startxref'.PHP_EOL.$xrefOffset.PHP_EOL.'%%EOF'.PHP_EOL);
        } else {
            $xrefContent = Xref::new()->build_xref($_obj_offsets);

            $this->pdfDocument->getTrailerObject()['Size'] = $this->pdfDocument->getMaxOid() + 1;
            $this->pdfDocument->getTrailerObject()['Prev'] = $this->pdfDocument->getXrefPosition();

            $_doc_from_xref = new Buffer($xrefContent);
            $_doc_from_xref->data("trailer\n" . $this->pdfDocument->getTrailerObject());
            $_doc_from_xref->data("\nstartxref\n{$xrefOffset}\n%%EOF\n");
        }

        $signature->set_sizes($_doc_to_xref->size(), $_doc_from_xref->size());
        $signature['Contents'] = new PDFValueSimple('');
        $_signable_document = new Buffer($_doc_to_xref->raw().$signature->to_pdf_entry().$_doc_from_xref->raw());

        $tmpFolder = sys_get_temp_dir();
        $tempFilename = tempnam($tmpFolder, 'pdfsign');
        $tempFile = fopen($tempFilename, 'wb');
        fwrite($tempFile, $_signable_document->raw());
        fclose($tempFile);

        $signatureContents = $this->signature->calculatePkcs7Signature($tempFilename, $tmpFolder);
        unlink($tempFilename);

        $signature['Contents'] = new PDFValueHexString($signatureContents);

        $_doc_to_xref->data($signature->to_pdf_entry());

        $this->popState();

        return new Buffer($_doc_to_xref->raw().$_doc_from_xref->raw());
    }
}