<?php
///*
//    This file is part of SAPP
//
//    Simple and Agnostic PDF Parser (SAPP) - Parse PDF documents in PHP (and update them)
//    Copyright (C) 2020 - Carlos de Alfonso (caralla76@gmail.com)
//
//    This program is free software: you can redistribute it and/or modify
//    it under the terms of the GNU Lesser General Public License as published by
//    the Free Software Foundation, either version 3 of the License, or
//    (at your option) any later version.
//
//    This program is distributed in the hope that it will be useful,
//    but WITHOUT ANY WARRANTY; without even the implied warranty of
//    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
//    GNU General Public License for more details.
//
//    You should have received a copy of the GNU Lesser General Public License
//    along with this program.  If not, see <https://www.gnu.org/licenses/>.
//*/
//
//namespace Jeidison\PdfSigner;
//
//use Exception;
//use function Jeidison\PdfSigner\helpers\p_error;
//use function Jeidison\PdfSigner\helpers\p_warning;
//
//// TODO: use the streamreader to deal with the document in the file, instead of a buffer
//
//class PDFUtilFnc
//{
//    public static function get_trailer(&$buffer, $trailerPos)
//    {
//        // Search for the trailer structure
//        if (preg_match('/trailer\s*(.*)\s*startxref/ms', (string) $buffer, $matches, 0, $trailerPos) !== 1) {
//            return p_error('trailer not found');
//        }
//
//        $trailerStr = $matches[1];
//
//        // We create the object to parse (this is not innefficient, because it is disposed when returning from the function)
//        //   and parse the trailer content.
//        $parser = new PDFObjectParser();
//        try {
//            $trailerObj = $parser->parsestr($trailerStr);
//        } catch (Exception) {
//            return p_error('trailer is not valid');
//        }
//
//        return $trailerObj;
//    }
//
//    public static function build_xref_1_5($offsets)
//    {
//        if (isset($offsets[0])) {
//            unset($offsets[0]);
//        }
//
//        $k = array_keys($offsets);
//        sort($k);
//
//        $indexes = [];
//        $iK = 0;
//        $cK = 0;
//        $count = 1;
//        $result = '';
//        $counter = count($k);
//        for ($i = 0; $i < $counter; ++$i) {
//            if ($cK === 0) {
//                $cK = $k[$i] - 1;
//                $iK = $k[$i];
//                $count = 0;
//            }
//
//            if ($k[$i] === $cK + 1) {
//                ++$count;
//            } else {
//                $indexes[] = sprintf('%s %d', $iK, $count);
//                $count = 1;
//                $iK = $k[$i];
//            }
//
//            $cOffset = $offsets[$k[$i]];
//
//            if (is_array($cOffset)) {
//                $result .= pack('C', 2);
//                $result .= pack('N', $cOffset['stmoid']);
//                $result .= pack('C', $cOffset['pos']);
//            } elseif ($cOffset === null) {
//                $result .= pack('C', 0);
//                $result .= pack('N', $cOffset);
//                $result .= pack('C', 0);
//            } else {
//                $result .= pack('C', 1);
//                $result .= pack('N', $cOffset);
//                $result .= pack('C', 0);
//            }
//
//            $cK = $k[$i];
//        }
//
//        $indexes[] = sprintf('%s %d', $iK, $count);
//        $indexes = implode(' ', $indexes);
//
//        // p_debug(show_bytes($result, 6));
//
//        return [
//            'W' => [1, 4, 1],
//            'Index' => $indexes,
//            'stream' => $result,
//        ];
//    }
//
//    /**
//     * This function obtains the xref from the cross reference streams (7.5.8 Cross-Reference Streams)
//     *   which started in PDF 1.5.
//     */
//    public static function get_xref_1_5(&$_buffer, $xrefPos, $depth = null)
//    {
//        if ($depth !== null) {
//            if ($depth <= 0) {
//                return false;
//            }
//
//            --$depth;
//        }
//
//        $xrefO = PDFUtilFnc::find_object_at_pos($_buffer, null, $xrefPos, []);
//        if ($xrefO === false) {
//            return p_error('cross reference object not found when parsing xref at position ' . $xrefPos, [false, false, false]);
//        }
//
//        if (! (isset($xrefO['Type'])) || ($xrefO['Type']->val() !== 'XRef')) {
//            return p_error('invalid xref table', [false, false, false]);
//        }
//
//        $stream = $xrefO->get_stream(false);
//        if ($stream === null) {
//            return p_error('cross reference stream not found when parsing xref at position ' . $xrefPos, [false, false, false]);
//        }
//
//        $W = $xrefO['W']->val(true);
//        if (count($W) !== 3) {
//            return p_error('invalid cross reference object', [false, false, false]);
//        }
//
//        $W[0] = (int) $W[0];
//        $W[1] = (int) $W[1];
//        $W[2] = (int) $W[2];
//
//        $Size = $xrefO['Size']->get_int();
//        if ($Size === false) {
//            return p_error('could not get the size of the xref table', [false, false, false]);
//        }
//
//        $Index = [0, $Size];
//        if (isset($xrefO['Index'])) {
//            $Index = $xrefO['Index']->val(true);
//        }
//
//        if (count($Index) % 2 !== 0) {
//            return p_error('invalid indexes of xref table', [false, false, false]);
//        }
//
//        // Get the previous xref table, to build up on it
//        $trailerObj = null;
//        $xrefTable = [];
//
//        // If still want to get more versions, let's check whether there is a previous xref table or not
//        if ((($depth === null) || ($depth > 0)) && isset($xrefO['Prev'])) {
//            $Prev = $xrefO['Prev'];
//            $Prev = $Prev->get_int();
//            if ($Prev === false) {
//                return p_error('invalid reference to a previous xref table', [false, false, false]);
//            }
//
//            // When dealing with 1.5 cross references, we do not allow to use other than cross references
//            [$xrefTable, $trailerObj] = PDFUtilFnc::get_xref_1_5($_buffer, $Prev, $depth);
//            // p_debug_var($xref_table);
//        }
//
//        // p_debug("xref table found at $xref_pos (oid: " . $xref_o->get_oid() . ")");
//        $streamV = new StreamReader($stream);
//
//        // Get the format function to un pack the values
//        $getFmtFunction = static function ($f) {
//            if ($f === false) {
//                return false;
//            }
//
//            return match ($f) {
//                0 => static fn($v) => 0,
//                1 => static fn($v) => unpack('C', str_pad($v, 1, chr(0), STR_PAD_LEFT))[1],
//                2 => static fn($v) => unpack('n', str_pad($v, 2, chr(0), STR_PAD_LEFT))[1],
//                3, 4 => static fn($v) => unpack('N', str_pad($v, 4, chr(0), STR_PAD_LEFT))[1],
//                5, 6, 7, 8 => static fn($v) => unpack('J', str_pad($v, 8, chr(0), STR_PAD_LEFT))[1],
//                default => false,
//            };
//        };
//
//        $fmtFunction = [
//            $getFmtFunction($W[0]),
//            $getFmtFunction($W[1]),
//            $getFmtFunction($W[2]),
//        ];
//
//        // p_debug("xref entries at $xref_pos for object " . $xref_o->get_oid());
//        // p_debug(show_bytes($stream, $W[0] + $W[1] + $W[2]));
//
//        // Parse the stream according to the indexes and the W array
//        $indexI = 0;
//        while ($indexI < count($Index)) {
//            $objectI = $Index[$indexI++];
//            $objectCount = $Index[$indexI++];
//
//            while (($streamV->currentChar() !== false) && ($objectCount > 0)) {
//                $f1 = $W[0] != 0 ? ($fmtFunction[0]($streamV->nextChars($W[0]))) : 1;
//                $f2 = $fmtFunction[1]($streamV->nextChars($W[1]));
//                $f3 = $fmtFunction[2]($streamV->nextChars($W[2]));
//
//                if (($f1 === false) || ($f2 === false) || ($f3 === false)) {
//                    return p_error('invalid stream for xref table', [false, false, false]);
//                }
//
//                switch ($f1) {
//                    case 0:
//                        // Free object
//                        $xrefTable[$objectI] = null;
//                        break;
//                    case 1:
//                        // Add object
//                        $xrefTable[$objectI] = $f2;
//                        /*
//                        TODO: consider creating a generation table, but for the purpose of the xref there is no matter... if the document if well-formed.
//                        */
//                        if ($f3 !== 0) {
//                            p_warning('Objects of non-zero generation are not fully checked... please double check your document and (if possible) please send examples via issues to https://github.com/dealfonso/sapp/issues/');
//                        }
//
//                        break;
//                    case 2:
//                        // Stream object
//                        // $f2 is the number of a stream object, $f3 is the index in that stream object
//                        $xrefTable[$objectI] = ['stmoid' => $f2, 'pos' => $f3];
//                        break;
//                    default:
//                        p_error(sprintf('do not know about entry of type %s in xref table', $f1));
//                }
//
//                ++$objectI;
//                --$objectCount;
//            }
//        }
//
//        return [$xrefTable, $xrefO->get_value(), '1.5'];
//    }
//
//    public static function get_xref_1_4(&$_buffer, $xrefPos, $depth = null)
//    {
//        if ($depth !== null) {
//            if ($depth <= 0) {
//                return false;
//            }
//
//            --$depth;
//        }
//
//        $trailerPos = strpos((string) $_buffer, 'trailer', $xrefPos);
//        $minPdfVersion = '1.4';
//
//        // Get the xref content and make sure that the buffer passed contains the xref tag at the offset provided
//        $xrefSubstr = substr((string) $_buffer, $xrefPos, $trailerPos - $xrefPos);
//
//        $separator = "\r\n";
//        $xrefLine = strtok($xrefSubstr, $separator);
//        if ($xrefLine !== 'xref') {
//            return p_error('xref tag not found at position ' . $xrefPos, [false, false, false]);
//        }
//
//        // Now parse the lines and build the xref table
//        $objId = false;
//        $objCount = 0;
//        $xrefTable = [];
//
//        while (($xrefLine = strtok($separator)) !== false) {
//
//            // The first type of entry contains the id of the next object and the amount of continuous objects defined
//            if (preg_match('/(\d+) (\d+)$/', $xrefLine, $matches) === 1) {
//                if ($objCount > 0) {
//                    // If still expecting objects, we'll assume that the xref is malformed
//                    return p_error('malformed xref at position ' . $xrefPos, [false, false, false]);
//                }
//
//                $objId = (int) $matches[1];
//                $objCount = (int) $matches[2];
//
//                continue;
//            }
//
//            // The other type of entry contains the offset of the object, the generation and the command (which is "f" for "free" or "n" for "new")
//            if (preg_match('/^(\d+) (\d+) (.)\s*/', $xrefLine, $matches) === 1) {
//
//                // If no object expected, we'll assume that the xref is malformed
//                if ($objCount === 0) {
//                    return p_error('unexpected entry for xref: ' . $xrefLine, [false, false, false]);
//                }
//
//                $objOffset = (int) $matches[1];
//                $objGeneration = (int) $matches[2];
//                $objOperation = $matches[3];
//
//                if ($objOffset !== 0) {
//                    // Make sure that the operation is one of those expected
//                    switch ($objOperation) {
//                        case 'f':
//                            // * a "f" entry is read as:
//                            //      (e.g. for object_id = 6) 0000000015 00001 f
//                            //         the next free object is the one with id 15; if wanted to re-use this object id 6, it must be using generation 1
//                            //      if the next generation is 65535, it would mean that this ID cannot be used again.
//                            // - a "f" entry means that the object is "free" for now
//                            // - the "f" entries form a linked list, where the last element in the list must point to "0"
//                            //
//                            // For the purpose of the xref table, there is no need to take care of the free-object list. And for the purpose
//                            //   of SAPP, neither. If ever wanted to add a new object SAPP will get a greater ID than the actual greater one.
//                            // TODO: consider taking care of the free linked list, (e.g.) to check consistency
//                            $xrefTable[$objId] = null;
//                            break;
//                        case 'n':
//                            // - a "n" entry means that the object is in the offset, with the given generation
//                            // For the purpose of the xref table, there is no issue with non-zero generation; the problem may arise if
//                            //  for example, in the xref table we include a generation that is different from the generarion of the object
//                            //  in the actual offset.
//                            // TODO: consider creating a "generation table"
//                            $xrefTable[$objId] = $objOffset;
//                            if ($objGeneration != 0) {
//                                p_warning('Objects of non-zero generation are not fully checked... please double check your document and (if possible) please send examples via issues to https://github.com/dealfonso/sapp/issues/');
//                            }
//
//                            break;
//                        default:
//                            // If it is not one of the expected, let's skip the object
//                            p_error('invalid entry for xref: ' . $xrefLine, [false, false, false]);
//                    }
//                }
//
//                --$objCount;
//                ++$objId;
//
//                continue;
//            }
//
//            // If the entry is not recongised, show the error
//            p_error('invalid xref entry ' . $xrefLine);
//            $xrefLine = strtok($separator);
//        }
//
//        // Get the trailer object
//        $trailerObj = PDFUtilFnc::get_trailer($_buffer, $trailerPos);
//
//        // If there exists a previous xref (for incremental PDFs), get it and merge the objects that do not exist in the current xref table
//        if (isset($trailerObj['Prev'])) {
//
//            $xrefPrevPos = $trailerObj['Prev']->val();
//            if (! is_numeric($xrefPrevPos)) {
//                return p_error('invalid trailer ' . $trailerObj, [false, false, false]);
//            }
//
//            $xrefPrevPos = (int) $xrefPrevPos;
//
//            [$prevTable, $prevTrailer, $prevMinPdfVersion] = PDFUtilFnc::get_xref_1_4($_buffer, $xrefPrevPos, $depth);
//
//            if ($prevMinPdfVersion !== $minPdfVersion) {
//                return p_error('mixed type of xref tables are not supported', [false, false, false]);
//            }
//
//            if ($prevTable !== false) {
//                foreach ($prevTable as $objId => &$objOffset) {              // Not modifying the objects, but to make sure that it does not consume additional memory
//                    // If there not exists a new version, we'll acquire it
//                    if (! isset($xrefTable[$objId])) {
//                        $xrefTable[$objId] = $objOffset;
//                    }
//                }
//            }
//        }
//
//        return [$xrefTable, $trailerObj, $minPdfVersion];
//    }
//
//    public static function get_xref(&$_buffer, $xrefPos, $depth = null)
//    {
//
//        // Each xref is immediately followed by a trailer
//        $trailerPos = strpos((string) $_buffer, 'trailer', $xrefPos);
//        if ($trailerPos === false) {
//            [$xrefTable, $trailerObj, $minPdfVersion] = PDFUtilFnc::get_xref_1_5($_buffer, $xrefPos, $depth);
//        } else {
//            [$xrefTable, $trailerObj, $minPdfVersion] = PDFUtilFnc::get_xref_1_4($_buffer, $xrefPos, $depth);
//        }
//
//        return [$xrefTable, $trailerObj, $minPdfVersion];
//    }
//
//    public static function acquire_structure(&$_buffer, $depth = null)
//    {
//        // Get the first line and acquire the PDF version of the document
//        $separator = "\r\n";
//        $pdfVersion = strtok($_buffer, $separator);
//        if ($pdfVersion === false) {
//            return false;
//        }
//
//        if (preg_match('/^%PDF-\d+\.\d+$/', $pdfVersion, $matches) !== 1) {
//            return p_error('PDF version string not found');
//        }
//
//        if (preg_match_all('/startxref\s*([0-9]+)\s*%%EOF($|[\r\n])/ms', (string) $_buffer, $matches, PREG_SET_ORDER | PREG_OFFSET_CAPTURE) === false) {
//            return p_error('failed to get structure');
//        }
//
//        $_versions = [];
//        /*
//        print_r($matches);
//        exit();
//        */
//        foreach ($matches as $match) {
//            $_versions[] = $match[2][1] + strlen($match[2][0]);
//        }
//
//        // Now get the trailing part and make sure that it has the proper form
//        $startxrefPos = strrpos((string) $_buffer, 'startxref');
//        if ($startxrefPos === false) {
//            return p_error('startxref not found');
//        }
//
//        if (preg_match('/startxref\s*([0-9]+)\s*%%EOF\s*$/ms', (string) $_buffer, $matches, 0, $startxrefPos) !== 1) {
//            return p_error('startxref and %%EOF not found');
//        }
//
//        $xrefPos = (int) $matches[1];
//
//        if ($xrefPos === 0) {
//            // This is a dummy xref position from linearized documents
//            return [
//                'trailer' => false,
//                'version' => substr($pdfVersion, 1),
//                'xref' => [],
//                'xrefposition' => 0,
//                'xrefversion' => substr($pdfVersion, 1),
//                'revisions' => $_versions,
//            ];
//        }
//
//        [$xrefTable, $trailerObject, $minPdfVersion] = PDFUtilFnc::get_xref($_buffer, $xrefPos, $depth);
//
//        // We are providing a lot of information to be able to inspect the problems of a PDF file
//        if ($xrefTable === false) {
//            // TODO: Maybe we could include a "recovery" method for this: if xref is not at pos $xref_pos, we could search for xref by hand
//            return p_error('could not find the xref table');
//        }
//
//        if ($trailerObject === false) {
//            return p_error('could not find the trailer object');
//        }
//
//        return [
//            'trailer' => $trailerObject,
//            'version' => substr($pdfVersion, 1),
//            'xref' => $xrefTable,
//            'xrefposition' => $xrefPos,
//            'xrefversion' => $minPdfVersion,
//            'revisions' => $_versions,
//        ];
//    }
//
//    /**
//     * Signs a file using the certificate and key and obtains the signature content padded to the max signature length
//     *
//     * @param filename the name of the file to sign
//     * @param certificate the public key to sign
//     * @param key the private key to sign
//     * @param tmpfolder the folder in which to store a temporary file needed
//     * @return signature the signature, in hexadecimal string, padded to the maximum length (i.e. for PDF) or false in case of error
//     */
//    public static function calculate_pkcs7_signature($filenametosign, $certificate, $key, $tmpfolder = '/tmp')
//    {
//        $filesizeOriginal = filesize($filenametosign);
//        if ($filesizeOriginal === false) {
//            return p_error('could not open file ' . $filenametosign);
//        }
//
//        $tempFilename = tempnam($tmpfolder, 'pdfsign');
//
//        if ($tempFilename === false) {
//            return p_error('could not create a temporary filename');
//        }
//
//        if (!openssl_pkcs7_sign($filenametosign, $tempFilename, $certificate, $key, [], PKCS7_BINARY | PKCS7_DETACHED)) {
//            unlink($tempFilename);
//
//            return p_error('failed to sign file ' . $filenametosign);
//        }
//
//        $signature = file_get_contents($tempFilename);
//        // extract signature
//        $signature = substr($signature, $filesizeOriginal);
//        $signature = substr($signature, (strpos($signature, "%%EOF\n\n------") + 13));
//
//        $tmparr = explode("\n\n", $signature);
//        $signature = $tmparr[1];
//        // decode signature
//        $signature = base64_decode(trim($signature));
//
//        // convert signature to hex
//        $signature = current(unpack('H*', $signature));
//
//        return str_pad((string) $signature, __SIGNATURE_MAX_LENGTH, '0');
//    }
//
//    /**
//     * Function that finds a the object at the specific position in the buffer
//     *
//     * @param buffer the buffer from which to read the document
//     * @param oid the target object id to read (if null, will return the first object, if found)
//     * @param offset the offset at which the object is expected to be
//     * @param xref_table the xref table, to be able to find indirect objects
//     * @return obj the PDFObject obtained from the file or false if could not be found
//     */
//    public static function find_object_at_pos(&$_buffer, $oid, $objectOffset, $xrefTable)
//    {
//
//        $object = PDFUtilFnc::object_from_string($_buffer, $oid, $objectOffset, $offsetEnd);
//
//        if ($object === false) {
//            return false;
//        }
//
//        $_stream_pending = false;
//
//        // The distinction is required, because we need to get the proper start for the stream, and if using CRLF instead of LF
//        //   - according to https://www.adobe.com/content/dam/acom/en/devnet/pdf/PDF32000_2008.pdf, stream is followed by CRLF
//        //     or LF, but not single CR.
//        if (substr((string) $_buffer, $offsetEnd - 7, 7) === "stream\n") {
//            $_stream_pending = $offsetEnd;
//        }
//
//        if (substr((string) $_buffer, $offsetEnd - 7, 8) === "stream\r\n") {
//            $_stream_pending = $offsetEnd + 1;
//        }
//
//        // If it expects a stream, get it
//        if ($_stream_pending !== false) {
//            $length = $object['Length']->get_int();
//            if ($length === false) {
//                $lengthObjectId = $object['Length']->get_object_referenced();
//                if ($lengthObjectId === false) {
//                    return p_error('could not get stream for object ' . $objId);
//                }
//
//                $lengthObject = PDFUtilFnc::find_object($_buffer, $xrefTable, $lengthObjectId);
//                if ($lengthObject === false) {
//                    return p_error('could not get object ' . $oid);
//                }
//
//                $length = $lengthObject->get_value()->get_int();
//            }
//
//            if ($length === false) {
//                return p_error('could not get stream length for object ' . $objId);
//            }
//
//            $object->set_stream(substr((string) $_buffer, $_stream_pending, $length), true);
//        }
//
//        return $object;
//    }
//
//    /**
//     * Function that finds a specific object in the document, using the xref table as a base
//     *
//     * @param buffer the buffer from which to read the document
//     * @param xref_table the xref table
//     * @param oid the target object id to read
//     * @return obj the PDFObject obtained from the file or false if could not be found
//     */
//    public static function find_object(&$_buffer, $xrefTable, $oid)
//    {
//
//        if ($oid === 0) {
//            return false;
//        }
//
//        if (! isset($xrefTable[$oid])) {
//            return false;
//        }
//
//        // Find the object and get where it ends
//        $objectOffset = $xrefTable[$oid];
//
//        if (! is_array($objectOffset)) {
//            return PDFUtilFnc::find_object_at_pos($_buffer, $oid, $objectOffset, $xrefTable);
//        } else {
//            return PDFUtilFnc::find_object_in_objstm($_buffer, $xrefTable, $objectOffset['stmoid'], $objectOffset['pos'], $oid);
//        }
//    }
//
//    /**
//     * Function that searches for an object in an object stream
//     */
//    public static function find_object_in_objstm(&$_buffer, $xrefTable, $objstmOid, $objpos, $oid)
//    {
//        $objstm = PDFUtilFnc::find_object($_buffer, $xrefTable, $objstmOid);
//        if ($objstm === false) {
//            return p_error('could not get object stream ' . $objstmOid);
//        }
//
//        if (($objstm['Extends'] ?? false !== false)) {
//            // TODO: support them
//            return p_error('not supporting extended object streams at this time');
//        }
//
//        $First = $objstm['First'] ?? false;
//        $N = $objstm['N'] ?? false;
//        $Type = $objstm['Type'] ?? false;
//
//        if (($First === false) || ($N === false) || ($Type === false)) {
//            return p_error('invalid object stream ' . $objstmOid);
//        }
//
//        if ($Type->val() !== 'ObjStm') {
//            return p_error(sprintf('object %s is not an object stream', $objstmOid));
//        }
//
//        $First = $First->get_int();
//        $N = $N->get_int();
//
//        $stream = $objstm->get_stream(false);
//        $index = substr((string) $stream, 0, $First);
//        $index = explode(' ', trim($index));
//
//        $stream = substr((string) $stream, $First);
//
//        if (count($index) % 2 !== 0) {
//            return p_error('invalid index for object stream ' . $objstmOid);
//        }
//
//        $objpos *= 2;
//        if ($objpos > count($index)) {
//            return p_error(sprintf('object %s not found in object stream %s', $oid, $objstmOid));
//        }
//
//        $offset = (int) $index[$objpos + 1];
//        $next = 0;
//        $offsets = [];
//        $counter = count($index);
//        for ($i = 1; ($i < $counter); $i += 2) {
//            $offsets[] = (int) $index[$i];
//        }
//
//        $offsets[] = strlen($stream);
//        sort($offsets);
//        for ($i = 0; ($i < count($offsets)) && ($offset >= $offsets[$i]); ++$i);
//
//        $next = $offsets[$i];
//
//        $objectDefStr = $oid . ' 0 obj '.substr($stream, $offset, $next - $offset).' endobj';
//
//        return PDFUtilFnc::object_from_string($objectDefStr, $oid);
//    }
//
//    /**
//     * Function that parses an object
//     */
//    public static function object_from_string(&$buffer, $expectedObjId, $offset = 0, &$offsetEnd = 0)
//    {
//        if (preg_match('/([0-9]+)\s+([0-9+])\s+obj(\s+)/ms', (string) $buffer, $matches, 0, $offset) !== 1) {
//            // p_debug_var(substr($buffer))
//            return p_error('object is not valid: ' . $expectedObjId);
//        }
//
//        $foundObjHeader = $matches[0];
//        $foundObjId = (int) $matches[1];
//        $foundObjGeneration = (int) $matches[2];
//
//        if ($expectedObjId === null) {
//            $expectedObjId = $foundObjId;
//        }
//
//        if ($foundObjId !== $expectedObjId) {
//            return p_error(sprintf('pdf structure is corrupt: found obj %d while searching for obj %s (at %s)', $foundObjId, $expectedObjId, $offset));
//        }
//
//        // The object starts after the header
//        $offset += strlen($foundObjHeader);
//
//        // Parse the object
//        $parser = new PDFObjectParser();
//
//        $stream = new StreamReader($buffer, $offset);
//
//        $objParsed = $parser->parse($stream);
//        if ($objParsed === false) {
//            return p_error(sprintf('object %d could not be parsed', $expectedObjId));
//        }
//
//        switch ($parser->current_token()) {
//            case PDFObjectParser::T_OBJECT_END:
//                // The object has ended correctly
//                break;
//            case PDFObjectParser::T_STREAM_BEGIN:
//                // There is an stream
//                break;
//            default:
//                return p_error('malformed object');
//        }
//
//        $offsetEnd = $stream->getPosition();
//
//        return new PDFObject($foundObjId, $objParsed, $foundObjGeneration);
//    }
//
//    /**
//     * Builds the xref for the document, using the list of objects
//     *
//     * @param offsets an array indexed by the oid of the objects, with the offset of each
//     *  object in the document.
//     * @return xref_string a string that contains the xref table, ready to be inserted in the document
//     */
//    public static function build_xref($offsets)
//    {
//        $k = array_keys($offsets);
//        sort($k);
//
//        $iK = 0;
//        $cK = 0;
//        $count = 1;
//        $result = '';
//        $references = "0000000000 65535 f \n";
//        $counter = count($k);
//        for ($i = 0; $i < $counter; ++$i) {
//            if ($k[$i] === 0) {
//                continue;
//            }
//
//            if ($k[$i] === $cK + 1) {
//                ++$count;
//            } else {
//                $result .= sprintf('%s %d%s%s', $iK, $count, PHP_EOL, $references);
//                $count = 1;
//                $iK = $k[$i];
//                $references = '';
//            }
//
//            $references .= sprintf("%010d 00000 n \n", $offsets[$k[$i]]);
//            $cK = $k[$i];
//        }
//
//        $result .= sprintf('%s %d%s%s', $iK, $count, PHP_EOL, $references);
//
//        return 'xref
//' . $result;
//    }
//}
