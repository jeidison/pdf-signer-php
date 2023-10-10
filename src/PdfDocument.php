<?php

namespace Jeidison\PdfSigner;

use DateTime;
use Exception;
use Jeidison\PdfSigner\PdfValue\PDFValue;
use Jeidison\PdfSigner\PdfValue\PDFValueString;
use Jeidison\PdfSigner\Utils\Date;
use Ramsey\Uuid\Uuid;

/**
 * @author Jeidison Farias <jeidison.farias@gmail.com>
 **/
class PdfDocument
{
    /** @var array<PDFObject> */
    protected array $pdfObjects = [];

    protected string $pdfVersion;

    protected PDFValue $trailerObject;

    protected int $xrefPosition;

    protected array $xrefTable;

    protected string $xrefTableVersion;

    protected array $revisions;

    protected Buffer $buffer;

    protected int $maxOid = 0;

    protected array $pagesInfo = [];

    public function getPdfVersion(): string
    {
        return $this->pdfVersion;
    }

    public function setPdfVersion(string $pdfVersion): void
    {
        $this->pdfVersion = $pdfVersion;
    }

    public function getTrailerObject(): PDFValue
    {
        return $this->trailerObject;
    }

    public function setTrailerObject(PDFValue $trailerObject): void
    {
        $this->trailerObject = $trailerObject;
    }

    public function getXrefPosition(): int
    {
        return $this->xrefPosition;
    }

    public function setXrefPosition(int $xrefPosition): void
    {
        $this->xrefPosition = $xrefPosition;
    }

    public function getXrefTable(): array
    {
        return $this->xrefTable;
    }

    public function setXrefTable(array $xrefTable): void
    {
        $this->xrefTable = $xrefTable;
    }

    public function getXrefTableVersion(): string
    {
        return $this->xrefTableVersion;
    }

    public function setXrefTableVersion(string $xrefTableVersion): void
    {
        $this->xrefTableVersion = $xrefTableVersion;
    }

    public function getRevisions(): array
    {
        return $this->revisions;
    }

    public function setRevisions(array $revisions): void
    {
        $this->revisions = $revisions;
    }

    public function getBuffer(): Buffer
    {
        return $this->buffer;
    }

    public function setBufferFromString(string $buffer): void
    {
        $this->buffer = new Buffer($buffer);
    }

    public function getPdfObjects(): array
    {
        return $this->pdfObjects;
    }

    public function setPdfObjects(array $pdfObjects): void
    {
        $this->pdfObjects = $pdfObjects;
    }

    public function getMaxOid(): int
    {
        return $this->maxOid;
    }

    public function setMaxOid(int $maxOid): void
    {
        $this->maxOid = $maxOid;
    }

    public function acquire_pages_info(): void
    {
        $root = $this->trailerObject['Root'];
        if (($root === false) || (($root = $root->getObjectReferenced()) === false)) {
            throw new Exception('could not find the root object from the trailer');
        }

        $root = $this->getObject($root);
        if ($root !== false) {
            $pages = $root['Pages'];
            if (($pages === false) || (($pages = $pages->getObjectReferenced()) === false)) {
                throw new Exception('could not find the pages for the document');
            }

            $this->pagesInfo = $this->_get_page_info($pages);
        }
    }

    protected function getNewOid(): int
    {
        $this->maxOid++;

        return $this->maxOid;
    }

    public function createObject($value = [], $class = PDFObject::class, $autoAdd = true): PDFObject
    {
        $classPdfObject = new $class($this->getNewOid(), $value);
        if ($autoAdd === true) {
            $this->addObject($classPdfObject);
        }

        return $classPdfObject;
    }

    public function addObject(PDFObject $pdfObject): bool
    {
        $oid = $pdfObject->getOid();

        if (isset($this->pdfObjects[$oid]) && $this->pdfObjects[$oid]->getGeneration() > $pdfObject->getGeneration()) {
            return false;
        }

        $this->pdfObjects[$oid] = $pdfObject;

        if ($oid > $this->maxOid) {
            $this->maxOid = $oid;
        }

        return true;
    }

    public function getObject($oid, $originalVersion = false): ?PDFObject
    {
        if ($originalVersion === true) {
            // Prioritizing the original version
            $object = $this->findObject($oid);
            if ($object === false) {
                $object = $this->pdfObjects[$oid] ?? false;
            }

        } else {
            // Prioritizing the new versions
            $object = $this->pdfObjects[$oid] ?? false;
            if ($object === false) {
                $object = $this->findObject($oid);
            }
        }

        return $object;
    }

    public function findObject(int $oid): ?PDFObject
    {
        if ($oid === 0) {
            return null;
        }

        if (! isset($this->xrefTable[$oid])) {
            return null;
        }

        $objectOffset = $this->xrefTable[$oid];

        if (! is_array($objectOffset)) {
            return $this->findObjectAtPos($oid, $objectOffset);
        }

        return $this->findObjectInObjStm($objectOffset['stmoid'], $objectOffset['pos'], $oid);
    }

    public function findObjectInObjStm($objstmOid, $objpos, $oid): PDFObject
    {
        $objstm = $this->findObject($objstmOid);
        if ($objstm === false) {
            throw new Exception('could not get object stream '.$objstmOid);
        }

        if (($objstm['Extends'] ?? false !== false)) {
            throw new Exception('not supporting extended object streams at this time');
        }

        $First = $objstm['First'] ?? false;
        $N = $objstm['N'] ?? false;
        $Type = $objstm['Type'] ?? false;

        if (($First === false) || ($N === false) || ($Type === false)) {
            throw new Exception('invalid object stream '.$objstmOid);
        }

        if ($Type->val() !== 'ObjStm') {
            throw new Exception(sprintf('object %s is not an object stream', $objstmOid));
        }

        $First = $First->get_int();

        $stream = $objstm->get_stream(false);
        $index = substr((string) $stream, 0, $First);
        $index = explode(' ', trim($index));

        $stream = substr((string) $stream, $First);

        if (count($index) % 2 !== 0) {
            throw new Exception('invalid index for object stream '.$objstmOid);
        }

        $objpos *= 2;
        if ($objpos > count($index)) {
            throw new Exception(sprintf('object %s not found in object stream %s', $oid, $objstmOid));
        }

        $offset = (int) $index[$objpos + 1];
        $offsets = [];
        $counter = count($index);
        for ($i = 1; ($i < $counter); $i += 2) {
            $offsets[] = (int) $index[$i];
        }

        $offsets[] = strlen($stream);
        sort($offsets);

        $next = $offsets[$i];
        $objectDefStr = $oid.' 0 obj '.substr($stream, $offset, $next - $offset).' endobj';

        return $this->objectFromString($objectDefStr, $oid);
    }

    public function findObjectAtPos($oid, $objectOffset): PDFObject
    {
        $object = $this->objectFromString($oid, $objectOffset, $offsetEnd);

        $streamPending = false;
        if (substr($this->buffer, $offsetEnd - 7, 7) === "stream\n") {
            $streamPending = $offsetEnd;
        }

        if (substr($this->buffer, $offsetEnd - 7, 8) === "stream\r\n") {
            $streamPending = $offsetEnd + 1;
        }

        if ($streamPending !== false) {
            $length = $object['Length']->getInt();
            if ($length === false) {
                $lengthObjectId = $object['Length']->getObjectReferenced();
                if ($lengthObjectId === false) {
                    throw new Exception('could not get stream for object ');
                }

                $lengthObject = $this->findObject($lengthObjectId);
                if ($lengthObject === false) {
                    throw new Exception('could not get object '.$oid);
                }

                $length = $lengthObject->getValue()->getInt();
            }

            if ($length === false) {
                throw new Exception('could not get stream length for object ');
            }

            $object->setStream(substr((string) $this->buffer, $streamPending, $length));
        }

        return $object;
    }

    public function objectFromString($expectedObjId, $offset = 0, &$offsetEnd = 0): PDFObject
    {
        if (preg_match('/(\d+)\s+(\d+)\s+obj/ms', $this->buffer, $matches, 0, $offset) !== 1) {
            throw new Exception('Object is not valid: '.$expectedObjId);
        }

        $foundObjHeader = $matches[0];
        $foundObjId = (int) $matches[1];
        $foundObjGeneration = (int) $matches[2];

        if ($expectedObjId === null) {
            $expectedObjId = $foundObjId;
        }

        if ($foundObjId !== $expectedObjId) {
            throw new Exception(sprintf('Pdf structure is corrupt: found obj %d while searching for obj %s (at %s)', $foundObjId, $expectedObjId, $offset));
        }

        $offset += strlen($foundObjHeader);

        $parser = new ObjectParser();
        $stream = new StreamReader($this->buffer, $offset);

        $objParsed = $parser->parse($stream);
        if ($objParsed === false) {
            throw new Exception(sprintf('Object %d could not be parsed', $expectedObjId));
        }

        switch ($parser->currentToken()) {
            case ObjectParser::T_STREAM_BEGIN:
            case ObjectParser::T_OBJECT_END:
                break;
            default:
                throw new Exception('Malformed object');
        }

        $offsetEnd = $stream->getPosition();

        return new PDFObject($foundObjId, $objParsed, $foundObjGeneration);
    }

    protected function _get_page_info($oid, $info = []): array|false
    {
        $object = $this->getObject($oid);
        if ($object === false) {
            throw new Exception('could not get information about the page');
        }

        $pageIds = [];

        switch ($object['Type']->val()) {
            case 'Pages':
                $kids = $object['Kids'];
                $kids = $kids->getObjectReferenced();
                if ($kids !== false) {
                    if (isset($object['MediaBox'])) {
                        $info['size'] = $object['MediaBox']->val();
                    }

                    foreach ($kids as $kid) {
                        $ids = $this->_get_page_info($kid, $info);
                        if ($ids === false) {
                            return false;
                        }

                        array_push($pageIds, ...$ids);
                    }
                } else {
                    throw new Exception('could not get the pages');
                }

                break;
            case 'Page':
                if (isset($object['MediaBox'])) {
                    $info['size'] = $object['MediaBox']->val();
                }

                return [['id' => $oid, 'info' => $info]];
            default:
                return false;
        }

        return $pageIds;
    }

    public function get_page($i)
    {
        if ($i < 0) {
            return false;
        }

        if ($i >= count($this->pagesInfo)) {
            return false;
        }

        return $this->getObject($this->pagesInfo[$i]['id']);
    }

    public function updateModifyDate(DateTime $date = null): bool
    {
        $root = $this->trailerObject['Root'];
        if (($root === false) || (($root = $root->getObjectReferenced()) === false)) {
            throw new Exception('Could not find the root object from the trailer');
        }

        $rootObj = $this->getObject($root);
        if ($rootObj === false) {
            throw new Exception('Invalid root object');
        }

        $date ??= new DateTime();

        if (isset($rootObj['Metadata'])) {
            $metadata = $rootObj['Metadata'];
            if ((($referenced = $metadata->get_object_referenced()) !== false) && (! is_array($referenced))) {
                $metadata = $this->getObject($referenced);
                $metaStream = $metadata->get_stream();
                $metaStream = preg_replace('/<xmp:ModifyDate>([^<]*)<\/xmp:ModifyDate>/', '<xmp:ModifyDate>'.$date->format('c').'</xmp:ModifyDate>', (string) $metaStream);
                $metaStream = preg_replace('/<xmp:MetadataDate>([^<]*)<\/xmp:MetadataDate>/', '<xmp:MetadataDate>'.$date->format('c').'</xmp:MetadataDate>', $metaStream);
                $metaStream = preg_replace('/<xmpMM:InstanceID>([^<]*)<\/xmpMM:InstanceID>/', '<xmpMM:InstanceID>uuid:'.Uuid::uuid4()->toString().'</xmpMM:InstanceID>', $metaStream);
                $metadata->setStream($metaStream, false);
                $this->addObject($metadata);
            }
        }

        $info = $this->trailerObject['Info'];
        if (($info === false) || (($info = $info->getObjectReferenced()) === false)) {
            throw new Exception('Could not find the info object from the trailer');
        }

        $infoObj = $this->getObject($info);
        if ($infoObj === false) {
            throw new Exception('Invalid info object');
        }

        $infoObj['ModDate'] = new PDFValueString(Date::toPdfDateString($date));
        $infoObj['Producer'] = 'Modifier with PHP Signer';
        $this->addObject($infoObj);

        return true;
    }

    public function get_page_size($i): ?array
    {
        if (is_int($i)) {
            if ($i < 0) {
                return null;
            }

            if ($i > count($this->pagesInfo)) {
                return null;
            }

            $pageinfo = $this->pagesInfo[$i]['info'];
        } else {
            foreach ($this->pagesInfo as $info) {
                if ($info['oid'] === $i->get_oid()) {
                    $pageinfo = $info['info'];
                    break;
                }
            }
        }

        return $pageinfo['size'] ?? null;
    }
}
