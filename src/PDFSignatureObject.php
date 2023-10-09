<?php

namespace Jeidison\PdfSigner;

use Jeidison\PdfSigner\PdfValue\PDFValueSimple;
use Jeidison\PdfSigner\PdfValue\PDFValueString;
use Jeidison\PdfSigner\Utils\Date;
use function Jeidison\PdfSigner\helpers\timestamp_to_pdfdatestring;

// The maximum signature length, needed to create a placeholder to calculate the range of bytes
// that will cover the signature.
if (! defined('__SIGNATURE_MAX_LENGTH')) {
    define('__SIGNATURE_MAX_LENGTH', 11742);
}

// The maximum expected length of the byte range, used to create a placeholder while the size
// is not known. 68 digits enable 20 digits for the size of the document
if (! defined('__BYTERANGE_SIZE')) {
    define('__BYTERANGE_SIZE', 68);
}

// This is an special object that has a set of fields
class PDFSignatureObject extends PDFObject
{
    protected $_prev_content_size = 0;

    protected $_post_content_size = null;

    // A placeholder for the certificate to use to sign the document
    protected $_certificate = null; // todo: acho que podemos remover isso, agr a assinatura está na classe Signature

    /**
     * Sets the certificate to use to sign
     *
     * @param cert the pem-formatted certificate and private to use to sign as
     *             [ 'cert' => ..., 'pkey' => ... ]
     */
    public function set_certificate($certificate)
    {
        $this->_certificate = $certificate;
    }

    /**
     * Obtains the certificate set with function set_certificate
     *
     * @return cert the certificate
     */
    public function get_certificate()
    {
        return $this->_certificate;
    }

    /**
     * Constructs the object and sets the default values needed to sign
     *
     * @param oid the oid for the object
     */
    public function __construct($oid)
    {
        parent::__construct($oid, [
            'Filter' => '/Adobe.PPKLite',
            'Type' => '/Sig',
            'SubFilter' => '/adbe.pkcs7.detached',
            'ByteRange' => new PDFValueSimple(str_repeat(' ', __BYTERANGE_SIZE)),
            'Contents' => '<'.str_repeat('0', __SIGNATURE_MAX_LENGTH).'>',
            'M' => new PDFValueString(Date::toPdfDateString()),
        ]);
    }

    /**
     * Function used to add some metadata fields to the signature: name, reason of signature, etc.
     *
     * @param name the name of the signer
     * @param reason the reason for the signature
     * @param location the location of signature
     * @param contact the contact info
     */
    public function set_metadata($name = null, $reason = null, $location = null, $contact = null)
    {
        $this->_value['Name'] = $name;
        $this->_value['Reason'] = $reason;
        $this->_value['Location'] = $location;
        $this->_value['ContactInfo'] = $contact;
    }

    /**
     * Function that sets the size of the content that will appear in the file, previous to this object,
     *   and the content that will be included after. This is needed to get the range of bytes of the
     *   signature.
     */
    public function set_sizes($prevContentSize, $postContentSize = null)
    {
        $this->_prev_content_size = $prevContentSize;
        $this->_post_content_size = $postContentSize;
    }

    /**
     * This function gets the offset of the marker, relative to this object. To make correct, the offset of the object
     *   shall have properly been set. It makes use of the parent "to_pdf_entry" function to avoid recursivity.
     *
     * @return int the position of the <0000 marker
     */
    public function get_signature_marker_offset()
    {
        $tmpOutput = parent::to_pdf_entry();
        $marker = '/Contents';
        $position = strpos($tmpOutput, $marker);

        return $position + strlen($marker);
    }

    /**
     * Overrides the parent function to calculate the proper range of bytes, according to the sizes provided and the
     *   string representation of this object
     *
     * @return string the string representation of this object
     */
    public function to_pdf_entry()
    {
        $signatureSize = strlen(parent::to_pdf_entry());
        $offset = $this->get_signature_marker_offset();
        $startingSecondPart = $this->_prev_content_size + $offset + __SIGNATURE_MAX_LENGTH + 2;

        $contentsSize = strlen(''.$this->_value['Contents']);

        $byterangeStr = '[ 0 '.
            ($this->_prev_content_size + $offset).' '.
            ($startingSecondPart).' '.
            ($this->_post_content_size !== null ? $this->_post_content_size + ($signatureSize - $contentsSize - $offset) : 0).' ]';

        $this->_value['ByteRange'] =
            new PDFValueSimple($byterangeStr.str_repeat(' ', __BYTERANGE_SIZE - strlen($byterangeStr) + 1)
            );

        return parent::to_pdf_entry();
    }
}
