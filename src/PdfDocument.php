<?php

namespace Jeidison\PdfSigner;

use DateTime;
use Exception;
use Jeidison\PdfSigner\PdfValue\PDFValue;
use Jeidison\PdfSigner\PdfValue\PDFValueString;
use Jeidison\PdfSigner\Utils\Date;
use Ramsey\Uuid\Uuid;

class PdfDocument
{
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
        if (($root === false) || (($root = $root->get_object_referenced()) === false)) {
            throw new Exception('could not find the root object from the trailer');
        }

        $root = $this->get_object($root);
        if ($root !== false) {
            $pages = $root['Pages'];
            if (($pages === false) || (($pages = $pages->get_object_referenced()) === false)) {
                throw new Exception('could not find the pages for the document');
            }

            $this->pagesInfo = $this->_get_page_info($pages);
        }
    }

    protected function get_new_oid()
    {
        ++$this->maxOid;

        return $this->maxOid;
    }

    public function create_object($value = [], $class = PDFObject::class, $autoadd = true): PDFObject
    {
        $o = new $class($this->get_new_oid(), $value);
        if ($autoadd === true) {
            $this->add_object($o);
        }

        return $o;
    }

    public function add_object(PDFObject $pdfObject)
    {
        $oid = $pdfObject->get_oid();

        if (isset($this->pdfObjects[$oid]) && $this->pdfObjects[$oid]->get_generation() > $pdfObject->get_generation()) {
            return false;
        }

        $this->pdfObjects[$oid] = $pdfObject;

        // Update the maximum oid
        if ($oid > $this->maxOid) {
            $this->maxOid = $oid;
        }

        return true;
    }

    public function get_object($oid, $originalVersion = false)
    {
        if ($originalVersion === true) {
            // Prioritizing the original version
            $object = $this->find_object($oid);
            if ($object === false) {
                $object = $this->pdfObjects[$oid] ?? false;
            }

        } else {
            // Prioritizing the new versions
            $object = $this->pdfObjects[$oid] ?? false;
            if ($object === false) {
                $object = $this->find_object($oid);
            }
        }

        return $object;
    }

    public function find_object($oid)
    {
        if ($oid === 0) {
            return false;
        }

        if (! isset($this->xrefTable[$oid])) {
            return false;
        }

        // Find the object and get where it ends
        $objectOffset = $this->xrefTable[$oid];

        if (! is_array($objectOffset)) {
            return $this->find_object_at_pos($oid, $objectOffset);
        } else {
            return $this->find_object_in_objstm($objectOffset['stmoid'], $objectOffset['pos'], $oid);
        }
    }

    public function find_object_in_objstm($objstmOid, $objpos, $oid)
    {
        $objstm = $this->find_object($objstmOid);
        if ($objstm === false) {
            throw new Exception('could not get object stream ' . $objstmOid);
        }

        if (($objstm['Extends'] ?? false !== false)) {
            // TODO: support them
            throw new Exception('not supporting extended object streams at this time');
        }

        $First = $objstm['First'] ?? false;
        $N = $objstm['N'] ?? false;
        $Type = $objstm['Type'] ?? false;

        if (($First === false) || ($N === false) || ($Type === false)) {
            throw new Exception('invalid object stream ' . $objstmOid);
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
            throw new Exception('invalid index for object stream ' . $objstmOid);
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
//        for ($i = 0; ($i < count($offsets)) && ($offset >= $offsets[$i]); ++$i);

        $next = $offsets[$i];

        $objectDefStr = $oid . ' 0 obj '.substr($stream, $offset, $next - $offset).' endobj';

        return $this->object_from_string($objectDefStr, $oid);
    }

    public function find_object_at_pos($oid, $objectOffset)
    {
        $object = $this->object_from_string($oid, $objectOffset, $offsetEnd);

        if ($object === false) {
            return false;
        }

        $_stream_pending = false;

        // The distinction is required, because we need to get the proper start for the stream, and if using CRLF instead of LF
        //   - according to https://www.adobe.com/content/dam/acom/en/devnet/pdf/PDF32000_2008.pdf, stream is followed by CRLF
        //     or LF, but not single CR.
        if (substr($this->buffer, $offsetEnd - 7, 7) === "stream\n") {
            $_stream_pending = $offsetEnd;
        }

        if (substr($this->buffer, $offsetEnd - 7, 8) === "stream\r\n") {
            $_stream_pending = $offsetEnd + 1;
        }

        // If it expects a stream, get it
        if ($_stream_pending !== false) {
            $length = $object['Length']->get_int();
            if ($length === false) {
                $lengthObjectId = $object['Length']->get_object_referenced();
                if ($lengthObjectId === false) {
                    throw new Exception('could not get stream for object ');
                }

                $lengthObject = $this->find_object($lengthObjectId);
                if ($lengthObject === false) {
                    throw new Exception('could not get object ' . $oid);
                }

                $length = $lengthObject->get_value()->get_int();
            }

            if ($length === false) {
                throw new Exception('could not get stream length for object ');
            }

            $object->set_stream(substr((string) $this->buffer, $_stream_pending, $length));
        }

        return $object;
    }

    public function object_from_string($expectedObjId, $offset = 0, &$offsetEnd = 0)
    {
        if (preg_match('/(\d+)\s+(\d+)\s+obj/ms', $this->buffer, $matches, 0, $offset) !== 1) {
            throw new Exception('Object is not valid: ' . $expectedObjId);
        }

        $foundObjHeader = $matches[0];
        $foundObjId = (int) $matches[1];
        $foundObjGeneration = (int) $matches[2];

        if ($expectedObjId === null) {
            $expectedObjId = $foundObjId;
        }

        if ($foundObjId !== $expectedObjId) {
            throw new Exception(sprintf('pdf structure is corrupt: found obj %d while searching for obj %s (at %s)', $foundObjId, $expectedObjId, $offset));
        }

        // The object starts after the header
        $offset += strlen($foundObjHeader);

        // Parse the object
        $parser = new PDFObjectParser();

        $stream = new StreamReader($this->buffer, $offset);

        $objParsed = $parser->parse($stream);
        if ($objParsed === false) {
            throw new Exception(sprintf('object %d could not be parsed', $expectedObjId));
        }

        switch ($parser->current_token()) {
            case PDFObjectParser::T_OBJECT_END:
                // The object has ended correctly
                break;
            case PDFObjectParser::T_STREAM_BEGIN:
                // There is an stream
                break;
            default:
                throw new Exception('malformed object');
        }

        $offsetEnd = $stream->getPosition();

        return new PDFObject($foundObjId, $objParsed, $foundObjGeneration);
    }

    protected function _get_page_info($oid, $info = []): array|false
    {
        $object = $this->get_object($oid);
        if ($object === false) {
            throw new Exception('could not get information about the page');
        }

        $pageIds = [];

        switch ($object['Type']->val()) {
            case 'Pages':
                $kids = $object['Kids'];
                $kids = $kids->get_object_referenced();
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

        return $this->get_object($this->pagesInfo[$i]['id']);
    }

    public function update_mod_date(DateTime $date = null)
    {
        $root = $this->trailerObject['Root'];
        if (($root === false) || (($root = $root->get_object_referenced()) === false)) {
            throw new Exception('could not find the root object from the trailer');
        }

        $rootObj = $this->get_object($root);
        if ($rootObj === false) {
            throw new Exception('invalid root object');
        }

        $date ??= new DateTime();

        if (isset($rootObj['Metadata'])) {
            $metadata = $rootObj['Metadata'];
            if ((($referenced = $metadata->get_object_referenced()) !== false) && (! is_array($referenced))) {
                $metadata = $this->get_object($referenced);
                $metastream = $metadata->get_stream();
                $metastream = preg_replace('/<xmp:ModifyDate>([^<]*)<\/xmp:ModifyDate>/', '<xmp:ModifyDate>'.$date->format('c').'</xmp:ModifyDate>', (string) $metastream);
                $metastream = preg_replace('/<xmp:MetadataDate>([^<]*)<\/xmp:MetadataDate>/', '<xmp:MetadataDate>'.$date->format('c').'</xmp:MetadataDate>', $metastream);
                $metastream = preg_replace('/<xmpMM:InstanceID>([^<]*)<\/xmpMM:InstanceID>/', '<xmpMM:InstanceID>uuid:'.Uuid::uuid4()->toString().'</xmpMM:InstanceID>', $metastream);
                $metadata->set_stream($metastream, false);
                $this->add_object($metadata);
            }
        }

        $info = $this->trailerObject['Info'];
        if (($info === false) || (($info = $info->get_object_referenced()) === false)) {
            throw new Exception('could not find the info object from the trailer');
        }

        $infoObj = $this->get_object($info);
        if ($infoObj === false) {
            throw new Exception('Invalid info object');
        }

        $infoObj['ModDate'] = new PDFValueString(Date::toPdfDateString($date));
        $infoObj['Producer'] = 'Modifier with PHP Signer';
        $this->add_object($infoObj);

        return true;
    }

    public function generate_content_to_xref()
    {
        $result = new Buffer($this->buffer);
        $offsets = [];
        $offsets[0] = 0;

        $offset = $result->size();
        foreach ($this->pdfObjects as $objId => $object) {
            $result->data($object->to_pdf_entry());
            $offsets[$objId] = $offset;
            $offset = $result->size();
        }

        return [$result, $offsets];
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