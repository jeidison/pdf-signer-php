<?php

namespace Jeidison\PdfSigner;

use Exception;

class PageInfo
{
    private PdfDocument $pdfDocument;

    protected array $pagesInfo = [];

    public static function new(): self
    {
        return new self();
    }

    public function withPdfDocument(PdfDocument $pdfDocument): self
    {
        $this->pdfDocument = $pdfDocument;

        return $this;
    }

    public function acquirePagesInfo(): self
    {
        $root = $this->pdfDocument->getTrailerObject()['Root'];
        if (($root === false) || (($root = $root->getObjectReferenced()) === false)) {
            throw new Exception('could not find the root object from the trailer');
        }

        $root = $this->pdfDocument->getObject($root);
        if ($root !== false) {
            $pages = $root['Pages'];
            if (($pages === false) || (($pages = $pages->getObjectReferenced()) === false)) {
                throw new Exception('could not find the pages for the document');
            }

            $this->pagesInfo = $this->getPageInfo($pages);
        }

        return $this;
    }

    protected function getPageInfo(int $oid, array $info = []): array|false
    {
        $object = $this->pdfDocument->getObject($oid);
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
                        $ids = $this->getPageInfo($kid, $info);
                        if ($ids === false) {
                            return false;
                        }

                        array_push($pageIds, ...$ids);
                    }
                } else {
                    throw new Exception('Could not get the pages');
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

    public function getPageSize($i): ?array
    {
        if (is_int($i)) {
            if ($i < 0) {
                return null;
            }

            if ($i > count($this->pagesInfo)) {
                return null;
            }

            $pageInfo = $this->pagesInfo[$i]['info'];
        } else {
            foreach ($this->pagesInfo as $info) {
                if ($info['oid'] === $i->get_oid()) {
                    $pageInfo = $info['info'];
                    break;
                }
            }
        }

        return $pageInfo['size'] ?? null;
    }

    public function getPage(int $i): ?PDFObject
    {
        if ($i < 0) {
            return null;
        }

        if ($i >= count($this->pagesInfo)) {
            return null;
        }

        return $this->pdfDocument->getObject($this->pagesInfo[$i]['id']);
    }
}
