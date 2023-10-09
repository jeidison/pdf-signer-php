<?php
//
//namespace Jeidison\PdfSigner;
//
//use Jeidison\PdfSigner\helpers\DependencyTreeObject;
//use Jeidison\PdfSigner\helpers\UUID;
//use Jeidison\PdfSigner\PdfValue\PDFValueHexString;
//use Jeidison\PdfSigner\PdfValue\PDFValueList;
//use Jeidison\PdfSigner\PdfValue\PDFValueObject;
//use Jeidison\PdfSigner\PdfValue\PDFValueReference;
//use Jeidison\PdfSigner\PdfValue\PDFValueSimple;
//use Jeidison\PdfSigner\PdfValue\PDFValueString;
//use Jeidison\PdfSigner\Utils\Img;
//use function Jeidison\PdfSigner\helpers\get_random_string;
//use function Jeidison\PdfSigner\helpers\p_debug;
//use function Jeidison\PdfSigner\helpers\p_error;
//use function Jeidison\PdfSigner\helpers\p_warning;
//use function Jeidison\PdfSigner\helpers\references_in_object;
//use function Jeidison\PdfSigner\helpers\timestamp_to_pdfdatestring;
//
//if (! defined('__TMP_FOLDER')) {
//    define('__TMP_FOLDER', sys_get_temp_dir());
//}
//
//// TODO: move the signature of documents to a new class (i.e. PDFDocSignable)
//// TODO: create a new class "PDFDocIncremental"
//class PDFDoc extends Buffer
//{
//    // The PDF version of the parsed file
//    protected array $pdfObjects = [];
//
//    protected $pdfVersionString = null;
//
//    protected $_pdf_trailer_object = null;
//
//    protected $_xref_position = 0;
//
//    protected $_xref_table = [];
//
//    protected $_max_oid = 0;
//
//    protected string $buffer = '';
//
//    protected $_backup_state = [];
//
//    protected $_certificate = null;
//
//    protected $_appearance = null;
//
//    protected $_xref_table_version;
//
//    protected $_revisions;
//
//    // Array of pages ordered by appearance in the final doc (i.e. index 0 is the first page rendered; index 1 is the second page rendered, etc.)
//    // Each entry is an array with the following fields:
//    //  - id: the id in the document (oid); can be translated into <id> 0 R for references
//    //  - info: an array with information about the page
//    //      - size: the size of the page
//    protected $_pages_info = [];
//
//    // Gets a new oid for a new object
//    protected function get_new_oid()
//    {
//        ++$this->_max_oid;
//
//        return $this->_max_oid;
//    }
//
//    /**
//     * Retrieve the number of pages in the document (not considered those pages that could be added by the user using this object or derived ones)
//     *
//     * @return pagecount number of pages in the original document
//     */
//    public function get_page_count()
//    {
//        return count($this->_pages_info);
//    }
//
//    /**
//     * Function that backups the current objects with the objective of making temporary modifications, and to restore
//     *   the state using function "pop_state". Many states can be stored, and they will be retrieved in reverse order
//     *   using pop_state
//     */
//    public function push_state()
//    {
//        $clonedObjects = [];
//        foreach ($this->pdfObjects as $oid => $object) {
//            $clonedObjects[$oid] = clone $object;
//        }
//
//        $this->_backup_state[] = ['max_oid' => $this->_max_oid, 'pdf_objects' => $clonedObjects];
//    }
//
//    /**
//     * Function that retrieves an stored state by means of function "push_state"
//     *
//     * @return restored true if a previous state was restored; false if there was no stored state
//     */
//    public function pop_state()
//    {
//        if (count($this->_backup_state) > 0) {
//            $state = array_pop($this->_backup_state);
//            $this->_max_oid = $state['max_oid'];
//            $this->pdfObjects = $state['pdf_objects'];
//
//            return true;
//        }
//
//        return false;
//    }
//
//    /**
//     * The function parses a document from a string: analyzes the structure and obtains and object
//     *   of type PDFDoc (if possible), or false, if an error happens.
//     *
//     * @param buffer a string that contains the file to analyze
//     * @param depth the number of previous versions to consider; if null, will consider any version;
//     *              otherwise only the object ids from the latest $depth versions will be considered
//     *              (if it is an incremental updated document)
//     */
//    public static function from_string(string $buffer, $depth = null)
//    {
////        $structure = PDFUtilFnc::acquire_structure($buffer, $depth);
//        $structure = Struct::new()
//            ->withDepth($depth)
//            ->withBuffer($buffer)
//            ->structure();
//
//        if ($structure === false) {
//            return false;
//        }
//
//        $trailer = $structure['trailer'];
//        $version = $structure['version'];
//        $xrefTable = $structure['xref'];
//        $xrefPosition = $structure['xrefposition'];
//        $revisions = $structure['revisions'];
//
//        $pdfdoc = new PDFDoc();
//        $pdfdoc->pdfVersionString = $version;
//        $pdfdoc->_pdf_trailer_object = $trailer;
//        $pdfdoc->_xref_position = $xrefPosition;
//        $pdfdoc->_xref_table = $xrefTable;
//        $pdfdoc->_xref_table_version = $structure['xrefversion'];
//        $pdfdoc->_revisions = $revisions;
//        $pdfdoc->buffer = $buffer;
//
//        if ($trailer !== false && $trailer['Encrypt'] !== false) {
//            // TODO: include encryption (maybe borrowing some code: http://www.fpdf.org/en/script/script37.php)
//            p_error('encrypted documents are not fully supported; maybe you cannot get the expected results');
//        }
//
//        $oids = array_keys($xrefTable);
//        sort($oids);
//        $pdfdoc->_max_oid = array_pop($oids);
//
//        if ($trailer === false) {
//            p_warning('invalid trailer object');
//        } else {
//            $pdfdoc->_acquire_pages_info();
//        }
//
//        return $pdfdoc;
//    }
//
//    public function get_revision($revI)
//    {
//        if ($revI === null) {
//            $revI = count($this->_revisions) - 1;
//        }
//
//        if ($revI < 0) {
//            $revI = count($this->_revisions) + $revI - 1;
//        }
//
//        return substr((string) $this->buffer, 0, $this->_revisions[$revI]);
//    }
//
//    /**
//     * Function that builds the object list from the xref table
//     */
//    public function build_objects_from_xref()
//    {
//        foreach ($this->_xref_table as $oid => $obj) {
//            $obj = $this->get_object($oid);
//            $this->add_object($obj);
//        }
//    }
//
//    /**
//     * This function creates an interator over the objects of the document, and makes use of function "get_object".
//     *   This mechanism enables to walk over any object, either they are new ones or they were in the original doc.
//     *   Enables:
//     *         foreach ($doc->get_object_iterator() as $oid => obj) { ... }
//     *
//     * @param allobjects the iterator obtains any possible object, according to the oids; otherwise, only will return the
//     *      objects that appear in the current version of the xref
//     * @return oid=>obj the objects
//     */
//    public function get_object_iterator($allobjects = false)
//    {
//        if ($allobjects === true) {
//            for ($i = 0; $i <= $this->_max_oid; ++$i) {
//                yield $i => $this->get_object($i);
//            }
//        } else {
//            foreach ($this->_xref_table as $oid => $offset) {
//                if ($offset === null) {
//                    continue;
//                }
//
//                $o = $this->get_object($oid);
//                if ($o === false) {
//                    continue;
//                }
//
//                yield $oid => $o;
//            }
//        }
//    }
//
//    /**
//     * This function checks whether the passed object is a reference or not, and in case that
//     *   it is a reference, it returns the referenced object; otherwise it return the object itself
//     *
//     * @param reference the reference value to obtain
//     * @return obj it reference can be interpreted as a reference, the referenced object; otherwise, the object itself.
//     *   If the passed value is an array of references, it will return false
//     */
//    public function get_indirect_object($reference)
//    {
//        $objectId = $reference->get_object_referenced();
//        if ($objectId !== false) {
//            if (is_array($objectId)) {
//                return false;
//            }
//
//            return $this->get_object($objectId);
//        }
//
//        return $reference;
//    }
//
//    /**
//     * Obtains an object from the document, usign the oid in the PDF document.
//     *
//     * @param oid the oid of the object that is being retrieved
//     * @param original if true and the object has been overwritten in this document, the object
//     *                 retrieved will be the original one. Setting to false will retrieve the
//     *                 more recent object
//     * @return obj the object retrieved (or false if not found)
//     */
//    public function get_object($oid, $originalVersion = false)
//    {
//        if ($originalVersion === true) {
//            // Prioritizing the original version
//            $object = PDFUtilFnc::find_object($this->buffer, $this->_xref_table, $oid);
//            if ($object === false) {
//                $object = $this->pdfObjects[$oid] ?? false;
//            }
//
//        } else {
//            // Prioritizing the new versions
//            $object = $this->pdfObjects[$oid] ?? false;
//            if ($object === false) {
//                $object = PDFUtilFnc::find_object($this->buffer, $this->_xref_table, $oid);
//            }
//        }
//
//        return $object;
//    }
//
//    /**
//     * Function that sets the appearance of the signature (if the document is to be signed). At this time, it is possible to set
//     *   the page in which the signature will appear, the rectangle, and an image that will be shown in the signature form.
//     *
//     * @param page the page (zero based) in which the signature will appear
//     * @param rect the rectangle (in page-based coordinates) where the signature will appear in that page
//     * @param imagefilename an image file name (or an image in a buffer, with symbol '@' prepended) that will be put inside the rect
//     */
//    public function set_signature_appearance($pageToAppear = 0, $rectToAppear = [0, 0, 0, 0], $imagefilename = null)
//    {
//        $this->_appearance = [
//            'page' => $pageToAppear,
//            'rect' => $rectToAppear,
//            'image' => $imagefilename,
//        ];
//    }
//
//    /**
//     * Removes the settings of signature appearance (i.e. no signature will appear in the document)
//     */
//    public function clear_signature_appearance()
//    {
//        $this->_appearance = null;
//    }
//
//    /**
//     * Removes the certificate for the signature (i.e. the document will not be signed)
//     */
//    public function clear_signature_certificate()
//    {
//        $this->_certificate = null;
//    }
//
//    /**
//     * Function that stores the certificate to use, when signing the document
//     *
//     * @param certfile a file that contains a user certificate in pkcs12 format, or an array [ 'cert' => <cert.pem>, 'pkey' => <key.pem> ]
//     *                 that would be the output of openssl_pkcs12_read
//     * @param password the password to read the private key
//     * @return valid true if the certificate can be used to sign the document, false otherwise
//     */
//    public function set_signature_certificate($certfile, $certpass = null)
//    {
//        // First we read the certificate
//        if (is_array($certfile)) {
//            $certificate = $certfile;
//            $certificate['pkey'] = [$certificate['pkey'], $certpass];
//
//            // If a password is provided, we'll try to decode the private key
//            if (openssl_pkey_get_private($certificate['pkey']) === false) {
//                return p_error('invalid private key');
//            }
//
//            if (! openssl_x509_check_private_key($certificate['cert'], $certificate['pkey'])) {
//                return p_error("private key doesn't corresponds to certificate");
//            }
//        } else {
//            $certfilecontent = file_get_contents($certfile);
//            if ($certfilecontent === false) {
//                return p_error('could not read file ' . $certfile);
//            }
//
//            if (openssl_pkcs12_read($certfilecontent, $certificate, $certpass) === false) {
//                return p_error('could not get the certificates from file ' . $certfile);
//            }
//        }
//
//        // Store the certificate
//        $this->_certificate = $certificate;
//
//        return true;
//    }
//
//    /**
//     * Function that creates and updates the PDF objects needed to sign the document. The workflow for a signature is:
//     * - create a signature object
//     * - create an annotation object whose value is the signature object
//     * - create a form object (along with other objects) that will hold the appearance of the annotation object
//     * - modify the root object to make acroform point to the annotation object
//     * - modify the page object to make the annotations of that page include the annotation object
//     *
//     * > If the appearance is not set, the image will not appear, and the signature object will be invisible.
//     * > If the certificate is not set, the signature created will be a placeholder (that acrobat will able to sign)
//     *
//     *      LIMITATIONS: one document can be signed once at a time; if wanted more signatures, then chain the documents:
//     *      $o1->set_signature_certificate(...);
//     *      $o2 = PDFDoc::fromstring($o1->to_pdf_file_s);
//     *      $o2->set_signature_certificate(...);
//     *      $o2->to_pdf_file_s();
//     *
//     * @return false|helpers\retval a signature object, or null if the document is not signed; false if an error happens
//     */
//    protected function _generate_signature_in_document()
//    {
//        $imagefilename = null;
//        $recttoappear = [0, 0, 0, 0];
//        $pagetoappear = 0;
//
//        if ($this->_appearance !== null) {
//            $imagefilename = $this->_appearance['image'];
//            $recttoappear = $this->_appearance['rect'];
//            $pagetoappear = $this->_appearance['page'];
//        }
//
//        // First of all, we are searching for the root object (which should be in the trailer)
//        $root = $this->_pdf_trailer_object['Root'];
//
//        if (($root === false) || (($root = $root->get_object_referenced()) === false)) {
//            return p_error('could not find the root object from the trailer');
//        }
//
//        $rootObj = $this->get_object($root);
//        if ($rootObj === false) {
//            return p_error('invalid root object');
//        }
//
//        // Now the object corresponding to the page number in which to appear
//        $pageObj = $this->get_page($pagetoappear);
//        if ($pageObj === false) {
//            return p_error('invalid page');
//        }
//
//        // The objects to update
//        $updatedObjects = [];
//
//        // Add the annotation to the page
//        if (! isset($pageObj['Annots'])) {
//            $pageObj['Annots'] = new PDFValueList();
//        }
//
//        $annots = &$pageObj['Annots'];
//        $pageRotation = $pageObj['Rotate'] ?? new PDFValueSimple(0);
//
//        if ((($referenced = $annots->get_object_referenced()) !== false) && (! is_array($referenced))) {
//            // It is an indirect object, so we need to update that object
//            $newannots = $this->create_object(
//                $this->get_object($referenced)->get_value()
//            );
//        } else {
//            $newannots = $this->create_object(
//                new PDFValueList()
//            );
//            $newannots->push($annots);
//        }
//
//        // Create the annotation object, annotate the offset and append the object
//        $annotationObject = $this->create_object([
//            'Type' => '/Annot',
//            'Subtype' => '/Widget',
//            'FT' => '/Sig',
//            'V' => new PDFValueString(''),
//            'T' => new PDFValueString('Signature'.get_random_string()),
//            'P' => new PDFValueReference($pageObj->get_oid()),
//            'Rect' => $recttoappear,
//            'F' => 132,  // TODO: check this value
//        ]
//        );
//
//        // Prepare the signature object (we need references to it)
//        $signature = null;
//        if ($this->_certificate !== null) {
//            $signature = $this->create_object([], \Jeidison\PdfSigner\PDFSignatureObject::class, false);
//            // $signature = new PDFSignatureObject([]);
//            $signature->set_certificate($this->_certificate);
//
//            // Update the value to the annotation object
//            $annotationObject['V'] = new PDFValueReference($signature->get_oid());
//        }
//
//        // If an image is provided, let's load it
//        if ($imagefilename !== null) {
//            // Signature with appearance, following the Adobe workflow:
//            //   1. form
//            //   2. layers /n0 (empty) and /n2
//            // https://www.adobe.com/content/dam/acom/en/devnet/acrobat/pdfs/acrobat_digital_signature_appearances_v9.pdf
//
//            // Get the page height, to change the coordinates system (up to down)
//            $pagesize = $this->get_page_size($pagetoappear);
//            $pagesize = explode(' ', (string) $pagesize[0]->val());
//            $pagesizeH = (float) (''.$pagesize[3]) - (float) (''.$pagesize[1]);
//
//            $bbox = [0, 0, $recttoappear[2] - $recttoappear[0], $recttoappear[3] - $recttoappear[1]];
//            $formObject = $this->create_object([
//                'BBox' => $bbox,
//                'Subtype' => '/Form',
//                'Type' => '/XObject',
//                'Group' => [
//                    'Type' => '/Group',
//                    'S' => '/Transparency',
//                    'CS' => '/DeviceRGB',
//                ],
//            ]);
//
//            $containerFormObject = $this->create_object([
//                'BBox' => $bbox,
//                'Subtype' => '/Form',
//                'Type' => '/XObject',
//                'Resources' => ['XObject' => [
//                    'n0' => new PDFValueSimple(''),
//                    'n2' => new PDFValueSimple(''),
//                ]],
//            ]);
//            $containerFormObject->set_stream("q 1 0 0 1 0 0 cm /n0 Do Q\nq 1 0 0 1 0 0 cm /n2 Do Q\n", false);
//
//            $layerN0 = $this->create_object([
//                'BBox' => [0.0, 0.0, 100.0, 100.0],
//                'Subtype' => '/Form',
//                'Type' => '/XObject',
//                'Resources' => new PDFValueObject(),
//            ]);
//
//            // Add the same structure than Acrobat Reader
//            $layerN0->set_stream('% DSBlank'.__EOL, false);
//
//            $layerN2 = $this->create_object([
//                'BBox' => $bbox,
//                'Subtype' => '/Form',
//                'Type' => '/XObject',
//                'Resources' => new PDFValueObject(),
//            ]);
//
//            $result = Img::add_image($this->create_object(...), $imagefilename, $bbox[0], $bbox[1], $bbox[2], $bbox[3], $pageRotation->val());
//            if ($result === false) {
//                return p_error('could not add the image');
//            }
//
//            $layerN2['Resources'] = $result['resources'];
//            $layerN2->set_stream($result['command'], false);
//
//            $containerFormObject['Resources']['XObject']['n0'] = new PDFValueReference($layerN0->get_oid());
//            $containerFormObject['Resources']['XObject']['n2'] = new PDFValueReference($layerN2->get_oid());
//
//            $formObject['Resources'] = new PDFValueObject([
//                'XObject' => [
//                    'FRM' => new PDFValueReference($containerFormObject->get_oid()),
//                ],
//            ]);
//            $formObject->set_stream('/FRM Do', false);
//
//            // Set the signature appearance field to the form object
//            $annotationObject['AP'] = ['N' => new PDFValueReference($formObject->get_oid())];
//            $annotationObject['Rect'] = [$recttoappear[0], $pagesizeH - $recttoappear[1], $recttoappear[2], $pagesizeH - $recttoappear[3]];
//        }
//
//        if (! $newannots->push(new PDFValueReference($annotationObject->get_oid()))) {
//            return p_error('Could not update the page where the signature has to appear');
//        }
//
//        $pageObj['Annots'] = new PDFValueReference($newannots->get_oid());
//        $updatedObjects[] = $pageObj;
//
//        // AcroForm may be an indirect object
//        if (! isset($rootObj['AcroForm'])) {
//            $rootObj['AcroForm'] = new PDFValueObject();
//        }
//
//        $acroform = &$rootObj['AcroForm'];
//        if ((($referenced = $acroform->get_object_referenced()) !== false) && (! is_array($referenced))) {
//            $acroform = $this->get_object($referenced);
//            $updatedObjects[] = $acroform;
//        } else {
//            $updatedObjects[] = $rootObj;
//        }
//
//        // Add the annotation to the interactive form
//        $acroform['SigFlags'] = 3;
//        if (! isset($acroform['Fields'])) {
//            $acroform['Fields'] = new PDFValueList();
//        }
//
//        // Add the annotation object to the interactive form
//        if (! $acroform['Fields']->push(new PDFValueReference($annotationObject->get_oid()))) {
//            return p_error('could not create the signature field');
//        }
//
//        // Store the objects
//        foreach ($updatedObjects as &$object) {
//            $this->add_object($object);
//        }
//
//        return $signature;
//    }
//
//    /**
//     * Function that updates the modification date of the document. If modifies two parts: the "info" field of the trailer object
//     *   and the xmp metadata field pointed by the root object.
//     *
//     * @param date a DateTime object that contains the date to be set; null to set "now"
//     * @return ok true if the date could be set; false otherwise
//     */
//    protected function update_mod_date(\DateTime $date = null)
//    {
//        // First of all, we are searching for the root object (which should be in the trailer)
//        $root = $this->_pdf_trailer_object['Root'];
//
//        if (($root === false) || (($root = $root->get_object_referenced()) === false)) {
//            return p_error('could not find the root object from the trailer');
//        }
//
//        $rootObj = $this->get_object($root);
//        if ($rootObj === false) {
//            return p_error('invalid root object');
//        }
//
//        if (!$date instanceof \DateTime) {
//            $date = new \DateTime();
//        }
//
//        // Update the xmp metadata if exists
//        if (isset($rootObj['Metadata'])) {
//            $metadata = $rootObj['Metadata'];
//            if ((($referenced = $metadata->get_object_referenced()) !== false) && (! is_array($referenced))) {
//                $metadata = $this->get_object($referenced);
//                $metastream = $metadata->get_stream();
//                $metastream = preg_replace('/<xmp:ModifyDate>([^<]*)<\/xmp:ModifyDate>/', '<xmp:ModifyDate>'.$date->format('c').'</xmp:ModifyDate>', (string) $metastream);
//                $metastream = preg_replace('/<xmp:MetadataDate>([^<]*)<\/xmp:MetadataDate>/', '<xmp:MetadataDate>'.$date->format('c').'</xmp:MetadataDate>', $metastream);
//                $metastream = preg_replace('/<xmpMM:InstanceID>([^<]*)<\/xmpMM:InstanceID>/', '<xmpMM:InstanceID>uuid:'.UUID::v4().'</xmpMM:InstanceID>', $metastream);
//                $metadata->set_stream($metastream, false);
//                $this->add_object($metadata);
//            }
//        }
//
//        // Update the information object (not really needed)
//        $info = $this->_pdf_trailer_object['Info'];
//        if (($info === false) || (($info = $info->get_object_referenced()) === false)) {
//            return p_error('could not find the info object from the trailer');
//        }
//
//        $infoObj = $this->get_object($info);
//        if ($infoObj === false) {
//            return p_error('invalid info object');
//        }
//
//        $infoObj['ModDate'] = new PDFValueString(timestamp_to_pdfdatestring($date));
//        $infoObj['Producer'] = 'Modificado con SAPP';
//        $this->add_object($infoObj);
//
//        return true;
//    }
//
//    /**
//     * Function that gets the objects that have been read from the document
//     *
//     * @return objects an array of objects, indexed by the oid of each object
//     */
//    public function get_objects()
//    {
//        return $this->pdfObjects;
//    }
//
//    /**
//     * Function that gets the version of the document. It will have the form
//     *   PDF-1.x
//     *
//     * @return version the PDF version
//     */
//    public function get_version()
//    {
//        return $this->pdfVersionString;
//    }
//
//    /**
//     * Function that sets the version for the document.
//     *
//     * @param version the version of the PDF document (it shall have the form PDF-1.x)
//     * @return correct true if the version had the proper form; false otherwise
//     */
//    public function set_version($version)
//    {
//        if (preg_match("/PDF-1.\[0-9\]/", (string) $version) !== 1) {
//            return false;
//        }
//
//        $this->pdfVersionString = $version;
//
//        return true;
//    }
//
//    /**
//     * Function that creates a new PDFObject and stores it in the document object list, so that
//     *   it is automatically managed by the document. The returned object can be modified and
//     *   that modifications will be reflected in the document.
//     *
//     * @param value the value that the object will contain
//     * @return obj the PDFObject created
//     */
//    public function create_object($value = [], $class = PDFObject::class, $autoadd = true): PDFObject
//    {
//        $o = new $class($this->get_new_oid(), $value);
//        if ($autoadd === true) {
//            $this->add_object($o);
//        }
//
//        return $o;
//    }
//
//    /**
//     * Adds a pdf object to the document (overwrites the one with the same oid, if existed)
//     *
//     * @param pdf_object the object to add to the document
//     * @return true if the object was added; false otherwise (e.g. already exists an object of a greater generation)
//     */
//    public function add_object(PDFObject $pdfObject)
//    {
//        $oid = $pdfObject->get_oid();
//
//        if (isset($this->pdfObjects[$oid]) && $this->pdfObjects[$oid]->get_generation() > $pdfObject->get_generation()) {
//            return false;
//        }
//
//        $this->pdfObjects[$oid] = $pdfObject;
//
//        // Update the maximum oid
//        if ($oid > $this->_max_oid) {
//            $this->_max_oid = $oid;
//        }
//
//        return true;
//    }
//
//    /**
//     * This function generates all the contents of the file up to the xref entry.
//     *
//     * @param rebuild whether to generate the xref with all the objects in the document (true) or
//     *                consider only the new ones (false)
//     * @return xref_data [ the text corresponding to the objects, array of offsets for each object ]
//     */
//    protected function _generate_content_to_xref($rebuild = false)
//    {
//        if ($rebuild === true) {
//            $result = new Buffer('%' . $this->pdfVersionString.__EOL);
//        } else {
//            $result = new Buffer($this->buffer);
//        }
//
//        // Need to calculate the objects offset
//        $offsets = [];
//        $offsets[0] = 0;
//
//        // The objects
//        $offset = $result->size();
//
//        if ($rebuild === true) {
//            for ($i = 0; $i <= $this->_max_oid; ++$i) {
//                if (($object = $this->get_object($i)) === false) {
//                    continue;
//                }
//
//                $result->data($object->to_pdf_entry());
//                $offsets[$i] = $offset;
//                $offset = $result->size();
//            }
//        } else {
//            foreach ($this->pdfObjects as $objId => $object) {
//                $result->data($object->to_pdf_entry());
//                $offsets[$objId] = $offset;
//                $offset = $result->size();
//            }
//        }
//
//        return [$result, $offsets];
//    }
//
//    /**
//     * This functions outputs the document to a buffer object, ready to be dumped to a file.
//     *
//     * @param rebuild whether we are rebuilding the whole xref table or not (in case of incremental versions, we should use "false")
//     * @return buffer a buffer that contains a pdf dumpable document
//     */
//    public function to_pdf_file_b($rebuild = false): Buffer
//    {
//        // We made no updates, so return the original doc
//        if (($rebuild === false) && (count($this->pdfObjects) === 0) && ($this->_certificate === null) && ($this->_appearance === null)) {
//            return new Buffer($this->buffer);
//        }
//
//        // Save the state prior to generating the objects
//        $this->push_state();
//
//        // Update the timestamp
//        $this->update_mod_date();
//
//        $_signature = null;
//        if (($this->_appearance !== null) || ($this->_certificate !== null)) {
//            $_signature = $this->_generate_signature_in_document();
//            if ($_signature === false) {
//                $this->pop_state();
//
//                return p_error('could not generate the signed document');
//            }
//        }
//
//        // Generate the first part of the document
//        [$_doc_to_xref, $_obj_offsets] = $this->_generate_content_to_xref($rebuild);
//        $xrefOffset = $_doc_to_xref->size();
//
//        if ($_signature !== null) {
//            $_obj_offsets[$_signature->get_oid()] = $_doc_to_xref->size();
//            $xrefOffset += strlen((string) $_signature->to_pdf_entry());
//        }
//
//        $docVersionString = str_replace('PDF-', '', (string) $this->pdfVersionString);
//
//        // The version considered for the cross reference table depends on the version of the current xref table,
//        //   as it is not possible to mix xref tables. Anyway we are
//        $targetVersion = $this->_xref_table_version;
//        if ($this->_xref_table_version >= '1.5') {
//            // i.e. xref streams
//            if ($docVersionString > $targetVersion) {
//                $targetVersion = $docVersionString;
//            }
//        } elseif ($docVersionString < $targetVersion) {
//            // i.e. xref+trailer
//            $targetVersion = $docVersionString;
//        }
//
//        if ($targetVersion >= '1.5') {
//            p_debug('generating xref using cross-reference streams');
//
//            // Create a new object for the trailer
//            $trailer = $this->create_object(
//                clone $this->_pdf_trailer_object
//            );
//
//            // Add this object to the offset table, to be also considered in the xref table
//            $_obj_offsets[$trailer->get_oid()] = $xrefOffset;
//
//            // Generate the xref cross-reference stream
//            $xref = PDFUtilFnc::build_xref_1_5($_obj_offsets);
//
//            // Set the parameters for the trailer
//            $trailer['Index'] = explode(' ', (string) $xref['Index']);
//            $trailer['W'] = $xref['W'];
//            $trailer['Size'] = $this->_max_oid + 1;
//            $trailer['Type'] = '/XRef';
//
//            // Not needed to generate new IDs, as in metadata the IDs will be set
//            // $ID1 = md5("" . (new \DateTime())->getTimestamp() . "-" . $this->_xref_position . $xref["stream"]);
//            $ID2 = md5(''.(new \DateTime())->getTimestamp().'-'.$this->_xref_position.$this->_pdf_trailer_object);
//            // $trailer["ID"] = [ new PDFValueHexString($ID1), new PDFValueHexString($ID2) ];
//            $trailer['ID'] = [$trailer['ID'][0], new PDFValueHexString(strtoupper($ID2))];
//
//            // We are not using predictors nor encoding
//            if (isset($trailer['DecodeParms'])) {
//                unset($trailer['DecodeParms']);
//            }
//
//            // We are not compressing the stream
//            if (isset($trailer['Filter'])) {
//                unset($trailer['Filter']);
//            }
//
//            $trailer->set_stream($xref['stream'], false);
//
//            // If creating an incremental modification, point to the previous xref table
//            if ($rebuild === false) {
//                $trailer['Prev'] = $this->_xref_position;
//            } elseif // If rebuilding the document, remove the references to previous xref tables, because it will be only one
//            (isset($trailer['Prev'])) {
//                unset($trailer['Prev']);
//            }
//
//            // And generate the part of the document related to the xref
//            $_doc_from_xref = new Buffer($trailer->to_pdf_entry());
//            $_doc_from_xref->data('startxref'.__EOL.$xrefOffset.__EOL.'%%EOF'.__EOL);
//        } else {
//            p_debug('generating xref using classic xref...trailer');
//            $xrefContent = PDFUtilFnc::build_xref($_obj_offsets);
//
//            // Update the trailer
//            $this->_pdf_trailer_object['Size'] = $this->_max_oid + 1;
//
//            if ($rebuild === false) {
//                $this->_pdf_trailer_object['Prev'] = $this->_xref_position;
//            }
//
//            // Not needed to generate new IDs, as in metadata the IDs may be set
//            // $ID1 = md5("" . (new \DateTime())->getTimestamp() . "-" . $this->_xref_position . $xref_content);
//            // $ID2 = md5("" . (new \DateTime())->getTimestamp() . "-" . $this->_xref_position . $this->_pdf_trailer_object);
//            // $this->_pdf_trailer_object['ID'] = new PDFValueList(
//            //    [ new PDFValueHexString($ID1), new PDFValueHexString($ID2) ]
//            // );
//
//            // Generate the part of the document related to the xref
//            $_doc_from_xref = new Buffer($xrefContent);
//            $_doc_from_xref->data('trailer
//' . $this->_pdf_trailer_object);
//            $_doc_from_xref->data("\nstartxref\n{$xrefOffset}\n%%EOF\n");
//        }
//
//        if ($_signature !== null) {
//            // In case that the document is signed, calculate the signature
//
//            $_signature->set_sizes($_doc_to_xref->size(), $_doc_from_xref->size());
//            $_signature['Contents'] = new PDFValueSimple('');
//            $_signable_document = new Buffer($_doc_to_xref->raw().$_signature->to_pdf_entry().$_doc_from_xref->raw());
//
//            // We need to write the content to a temporary folder to use the pkcs7 signature mechanism
//            $tempFilename = tempnam(__TMP_FOLDER, 'pdfsign');
//            $tempFile = fopen($tempFilename, 'wb');
//            fwrite($tempFile, $_signable_document->raw());
//            fclose($tempFile);
//
//            // Calculate the signature and remove the temporary file
//            $certificate = $_signature->get_certificate();
//            $signatureContents = PDFUtilFnc::calculate_pkcs7_signature($tempFilename, $certificate['cert'], $certificate['pkey'], __TMP_FOLDER);
//            unlink($tempFilename);
//
//            // Then restore the contents field
//            $_signature['Contents'] = new PDFValueHexString($signatureContents);
//
//            // Add this object to the content previous to this document xref
//            $_doc_to_xref->data($_signature->to_pdf_entry());
//        }
//
//        // Reset the state to make signature objects not to mess with the user's objects
//        $this->pop_state();
//
//        return new Buffer($_doc_to_xref->raw().$_doc_from_xref->raw());
//    }
//
//    /**
//     * This functions outputs the document to a string, ready to be written
//     *
//     * @return buffer a buffer that contains a pdf document
//     */
//    public function to_pdf_file_s($rebuild = false)
//    {
//        $pdfContent = $this->to_pdf_file_b($rebuild);
//
//        return $pdfContent->raw();
//    }
//
//    /**
//     * This function writes the document to a file
//     *
//     * @param filename the name of the file to be written (it will be overwritten, if exists)
//     * @return written true if the file has been correcly written to the file; false otherwise
//     */
//    public function to_pdf_file($filename, $rebuild = false)
//    {
//        $pdfContent = $this->to_pdf_file_b($rebuild);
//
//        $file = fopen($filename, 'wb');
//        if ($file === false) {
//            return p_error('failed to create the file');
//        }
//
//        if (fwrite($file, $pdfContent->raw()) !== $pdfContent->size()) {
//            fclose($file);
//
//            return p_error('failed to write to file');
//        }
//
//        fclose($file);
//
//        return true;
//    }
//
//    /**
//     * Gets the page object which is rendered in position i
//     *
//     * @param i the number of page (according to the rendering order)
//     * @return page the page object
//     */
//    public function get_page($i)
//    {
//        if ($i < 0) {
//            return false;
//        }
//
//        if ($i >= count($this->_pages_info)) {
//            return false;
//        }
//
//        return $this->get_object($this->_pages_info[$i]['id']);
//    }
//
//    /**
//     * Gets the size of the page in the form of a rectangle [ x0 y0 x1 y1 ]
//     *
//     * @param i the number of page (according to the rendering order), or the page object
//     * @return box the bounding box of the page
//     */
//    public function get_page_size($i)
//    {
//        $pageinfo = false;
//
//        if (is_int($i)) {
//            if ($i < 0) {
//                return false;
//            }
//
//            if ($i > count($this->_pages_info)) {
//                return false;
//            }
//
//            $pageinfo = $this->_pages_info[$i]['info'];
//        } else {
//            foreach ($this->_pages_info as $k => $info) {
//                if ($info['oid'] === $i->get_oid()) {
//                    $pageinfo = $info['info'];
//                    break;
//                }
//            }
//        }
//
//        // The page has not been found
//        if (($pageinfo === false) || (! isset($pageinfo['size']))) {
//            return false;
//        }
//
//        return $pageinfo['size'];
//    }
//
//    /**
//     * This function builds the page IDs for object with id oid. If it is a page, it returns the oid; if it is not and it has
//     *   kids and every kid is a page (or a set of pages), it finds the pages.
//     *
//     * @param oid the object id to inspect
//     * @return pages the ordered list of page ids corresponding to object oid, or false if any of the kid objects
//     *               is not of type page or pages.
//     */
//    protected function _get_page_info($oid, $info = [])
//    {
//        $object = $this->get_object($oid);
//        if ($object === false) {
//            return p_error('could not get information about the page');
//        }
//
//        $pageIds = [];
//
//        switch ($object['Type']->val()) {
//            case 'Pages':
//                $kids = $object['Kids'];
//                $kids = $kids->get_object_referenced();
//                if ($kids !== false) {
//                    if (isset($object['MediaBox'])) {
//                        $info['size'] = $object['MediaBox']->val();
//                    }
//
//                    foreach ($kids as $kid) {
//                        $ids = $this->_get_page_info($kid, $info);
//                        if ($ids === false) {
//                            return false;
//                        }
//
//                        array_push($pageIds, ...$ids);
//                    }
//                } else {
//                    return p_error('could not get the pages');
//                }
//
//                break;
//            case 'Page':
//                if (isset($object['MediaBox'])) {
//                    $info['size'] = $object['MediaBox']->val();
//                }
//
//                return [['id' => $oid, 'info' => $info]];
//            default:
//                return false;
//        }
//
//        return $pageIds;
//    }
//
//    /**
//     * Obtains an ordered list of objects that contain the ids of the page objects of the document.
//     *   The order is made according to the catalog and the document structure.
//     *
//     * @return list an ordered list of the id of the page objects, or false if could not be found
//     */
//    protected function _acquire_pages_info()
//    {
//        $root = $this->_pdf_trailer_object['Root'];
//        if (($root === false) || (($root = $root->get_object_referenced()) === false)) {
//            return p_error('could not find the root object from the trailer');
//        }
//
//        $root = $this->get_object($root);
//        if ($root !== false) {
//            $pages = $root['Pages'];
//            if (($pages === false) || (($pages = $pages->get_object_referenced()) === false)) {
//                return p_error('could not find the pages for the document');
//            }
//
//            $this->_pages_info = $this->_get_page_info($pages);
//        } else {
//            p_warning('root object does not exist, so cannot get information about pages');
//        }
//    }
//
//    /**
//     * This function compares this document with other document, object by object. The idea is to compare the objects with the same oid in the
//     *  different documents, checking field by field; it does not take into account the streams.
//     */
//    public function compare($other)
//    {
//        $otherObjects = [];
//        foreach ($other->get_object_iterator(false) as $oid => $object) {
//            $otherObjects[$oid] = $object;
//        }
//
//        $differences = [];
//
//        foreach ($this->get_object_iterator(false) as $oid => $object) {
//            if (isset($otherObjects[$oid])) {
//                // The object exists, so we need to compare
//                $diff = $object->get_value()->diff($otherObjects[$oid]->get_value());
//                if ($diff !== null) {
//                    $differences[$oid] = new PDFObject($oid, $diff);
//                }
//            } else {
//                $differences[$oid] = new PDFObject($oid, $object->get_value());
//            }
//
//        }
//
//        return $differences;
//    }
//
//    /**
//     * Obtains the tree of objects in the PDF Document. The result is an array of DependencyTreeObject objects (indexed by the oid), where
//     *  each element has a set of children that can be retrieved using the iterator (foreach $o->children() as $oid => $object ...)
//     */
//    public function get_object_tree()
//    {
//
//        // Prepare the return value
//        $objects = [];
//
//        foreach ($this->_xref_table as $oid => $offset) {
//            if ($offset === null) {
//                continue;
//            }
//
//            $o = $this->get_object($oid);
//            if ($o === false) {
//                continue;
//            }
//
//            // foreach ($this->get_object_iterator() as $oid => $o) {
//
//            // Create the object in the dependency tree and add it to the list of objects
//            if (! array_key_exists($oid, $objects)) {
//                $objects[$oid] = new DependencyTreeObject($oid, $o['Type']);
//            }
//
//            // The object is a PDFObject so we need the PDFValueObject to get the value of the fields
//            $object = $objects[$oid];
//            $val = $o->get_value();
//
//            // We'll only consider those objects that may create an structure (i.e. the objects, whose fields may include references to other objects)
//            if (is_a($val, 'ddn\\sapp\\pdfvalue\\PDFValueObject')) {
//                $references = references_in_object($val, $oid);
//            } else {
//                $references = $val->get_object_referenced();
//                if ($references === false) {
//                    continue;
//                }
//
//                if (! is_array($references)) {
//                    $references = [$references];
//                }
//            }
//
//            // p_debug("$oid references " . implode(", ", $references));
//            foreach ($references as $rObject) {
//                if (! array_key_exists($rObject, $objects)) {
//                    $rObjectO = $this->get_object($rObject);
//                    $objects[$rObject] = new DependencyTreeObject($rObject, $rObjectO['Type']);
//                }
//
//                $object->addchild($rObject, $objects[$rObject]);
//            }
//        }
//
//        //
//        $xrefChildren = [];
//        foreach ($objects as $oid => $tObject) {
//            if ($tObject->info == '/XRef') {
//                array_push($xrefChildren, ...iterator_to_array($tObject->children()));
//            }
//        }
//
//        $xrefChildren = array_unique($xrefChildren);
//
//        // Remove those objects that are child of other objects from the top of the tree
//        foreach ($objects as $oid => $tObject) {
//            if ((($tObject->is_child > 0) || (in_array($tObject->info, ['/XRef', '/ObjStm']))) && ! in_array($oid, $xrefChildren)) {
//                unset($objects[$oid]);
//            }
//        }
//
//        return $objects;
//    }
//
//    /**
//     * Retrieve the signatures in the document
//     *
//     * @return array of signatures in the original document
//     */
//    public function get_signatures()
//    {
//
//        // Prepare the return value
//        $signatures = [];
//
//        foreach ($this->_xref_table as $oid => $offset) {
//            if ($offset === null) {
//                continue;
//            }
//
//            $o = $this->get_object($oid);
//            if ($o === false) {
//                continue;
//            }
//
//            $oValue = $o->get_value()->val();
//            if (! is_array($oValue) || ! isset($oValue['Type'])) {
//                continue;
//            }
//
//            if ($oValue['Type']->val() != 'Sig') {
//                continue;
//            }
//
//            $signature = ['content' => $oValue['Contents']->val()];
//
//            try {
//                $cert = [];
//
//                openssl_pkcs7_read(
//                    "-----BEGIN CERTIFICATE-----\n"
//                       .chunk_split(base64_encode(hex2bin((string) $signature['content'])), 64, "\n")
//                       ."-----END CERTIFICATE-----\n",
//                    $cert
//                );
//
//                $signature += openssl_x509_parse($cert[0]);
//            } catch (\Exception) {
//            }
//
//            $signatures[] = $signature;
//        }
//
//        return $signatures;
//    }
//
//    /**
//     * Retrieve the number of signatures in the document
//     *
//     * @return int signatures number in the original document
//     */
//    public function get_signature_count()
//    {
//        return count($this->get_signatures());
//    }
//
//    /**
//     * Generates a new document that is the result of signing the current
//     * document
//     *
//     * @param certfile a file that contains a user certificate in pkcs12 format, or an array [ 'cert' => <cert.pem>, 'pkey' => <key.pem> ]
//     *                 that would be the output of openssl_pkcs12_read
//     * @param password the password to read the private key
//     * @param page_to_appear the page (zero based) in which the signature will appear
//     * @param imagefilename an image file name (or an image in a buffer, with symbol '@' prepended) that will be put inside the rect; if
//     *                      set to null, the signature will be invisible.
//     * @param px
//     * @param py x and y position for the signature.
//     * @param size
//     *          - if float, it will be a scale for the size of the image to be included as a signature appearance
//     *          - if array [ width, height ], it will be the width and the height for the image to be included as a signature appearance (if
//     *            one of these values is null, it will fallback to the actual width or height of the image)
//     */
//    public function sign_document($certfile, $password = null, $pageToAppear = 0, $imagefilename = null, $px = 0, $py = 0, $size = null)
//    {
//
//        if ($imagefilename !== null) {
//            $position = [];
//            $imagesize = @getimagesize($imagefilename);
//            if ($imagesize === false) {
//                return p_warning('failed to open the image ' . $image);
//            }
//
//            if (($pageToAppear < 0) || ($pageToAppear > $this->get_page_count())) {
//                return p_error('invalid page number');
//            }
//
//            $pagesize = $this->get_page_size($pageToAppear);
//            if ($pagesize === false) {
//                return p_error('failed to get page size');
//            }
//
//            $pagesize = explode(' ', (string) $pagesize[0]->val());
//
//            // Get the bounding box for the image
//            $pX = (int) (''.$pagesize[0]);
//            $pY = (int) (''.$pagesize[1]);
//            $pW = (int) (''.$pagesize[2]) - $pX;
//            $pH = (int) (''.$pagesize[3]) - $pY;
//
//            // Add the position for the image
//            $pX += $px;
//            $pY += $py;
//
//            $iW = $imagesize[0];
//            $iH = $imagesize[1];
//
//            if (is_array($size)) {
//                if (count($size) != 2) {
//                    return p_error('invalid size');
//                }
//
//                $width = $size[0];
//                $height = $size[1];
//            } elseif ($size === null) {
//                $width = $iW;
//                $height = $iH;
//            } elseif (is_float($size) || is_int($size)) {
//                $width = $iW * $size;
//                $height = $iH * $size;
//            } else {
//                return p_error('invalid size format');
//            }
//
//            $iW = $width ?? $imagesize[0];
//            $iH = $height ?? $imagesize[1];
//
//            // Set the image appearance and the certificate file
//            $this->set_signature_appearance(0, [$pX, $pY, $pX + $iW, $pY + $iH], $imagefilename);
//        }
//
//        if (! $this->set_signature_certificate($certfile, $password)) {
//            return p_error('the certificate or the signature is not valid');
//        }
//
//        $docsigned = $this->to_pdf_file_s();
//        if ($docsigned === false) {
//            return p_error('failed to sign the document');
//        }
//
//        return PDFDoc::from_string($docsigned);
//    }
//}
