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
//use Jeidison\PdfSigner\PdfValue\PDFValueList;
//use Jeidison\PdfSigner\PdfValue\PDFValueObject;
//use Jeidison\PdfSigner\PdfValue\PDFValueReference;
//use function Jeidison\PdfSigner\helpers\get_random_string;
//use function Jeidison\PdfSigner\helpers\p_error;
//use function Jeidison\PdfSigner\helpers\p_warning;
//
//class PDFDocWithContents extends PDFDoc
//{
//    final public const T_STANDARD_FONTS = [
//        'Times-Roman',
//        'Times-Bold',
//        'Time-Italic',
//        'Time-BoldItalic',
//        'Courier',
//        'Courier-Bold',
//        'Courier-Oblique',
//        'Courier-BoldOblique',
//        'Helvetica',
//        'Helvetica-Bold',
//        'Helvetica-Oblique',
//        'Helvetica-BoldOblique',
//        'Symbol',
//        'ZapfDingbats',
//    ];
//
//    /**
//     * This is a function that allows to add a very basic text to a page, using a standard font.
//     *   The function is mainly oriented to add banners and so on, and not to use for writting.
//     *
//     * @param page the number of page in which the text should appear
//     * @param text the text
//     * @param x the x offset from left for the text (we do not take care of margins)
//     * @param y the y offset from top for the text (we do not take care of margins)
//     * @param params an array of values [ "font" => <fontname>, "size" => <size in pt>,
//     *               "color" => <#hexcolor>, "angle" => <rotation angle>]
//     */
//    public function add_text($pageToAppear, $text, $x, $y, $params = [])
//    {
//        // TODO: maybe we can create a function that "adds content to a page", and that
//        //       function will search for the content field and merge the resources, if
//        //       needed
//        p_warning('This function still needs work');
//
//        $default = [
//            'font' => 'Helvetica',
//            'size' => 24,
//            'color' => '#000000',
//            'angle' => 0,
//        ];
//
//        $params = array_merge($default, $params);
//
//        $pageObj = $this->get_page($pageToAppear);
//        if ($pageObj === false) {
//            return p_error('invalid page');
//        }
//
//        $resourcesObj = $this->get_indirect_object($pageObj['Resources']);
//
//        if (!in_array($params['font'], self::T_STANDARD_FONTS)) {
//            return p_error('only standard fonts are allowed Times-Roman, Helvetica, Courier, Symbol, ZapfDingbats');
//        }
//
//        $fontId = 'F'.get_random_string(4);
//        $resourcesObj['Font'][$fontId] = [
//            'Type' => '/Font',
//            'Subtype' => '/Type1',
//            'BaseFont' => '/'.$params['font'],
//        ];
//
//        // Get the contents for the page
//        $contentsObj = $this->get_indirect_object($pageObj['Contents']);
//
//        $data = $contentsObj->get_stream(false);
//        if ($data === false) {
//            return p_error('could not interpret the contents of the page');
//        }
//
//        // Get the page height, to change the coordinates system (up to down)
//        $pagesize = $this->get_page_size($pageToAppear);
//        $pagesizeH = (float) (''.$pagesize[3]) - (float) (''.$pagesize[1]);
//
//        $angle = $params['angle'];
//        $angle *= M_PI / 180;
//        $c = cos($angle);
//        $s = sin($angle);
//        $cx = $x;
//        $cy = ($pagesizeH - $y);
//
//        if ($angle !== 0) {
//            $rotateCommand = sprintf('%.5F %.5F %.5F %.5F %.2F %.2F cm 1 0 0 1 %.2F %.2F cm', $c, $s, -$s, $c, $cx, $cy, -$cx, -$cy);
//        }
//
//        $textCommand = 'BT ';
//        $textCommand .= sprintf('/%s ', $fontId).$params['size'].' Tf ';
//        $textCommand .= sprintf('%.2f %.2f Td ', $x, $pagesizeH - $y); // Ubicar en x, y
//        $textCommand .= sprintf('(%s) Tj ', $text);
//        $textCommand .= 'ET ';
//
//        $color = $params['color'];
//        if ($color[0] === '#') {
//            $colorvalid = true;
//            $r = null;
//            switch (strlen((string) $color)) {
//                case 4:
//                    $color = '#'.$color[1].$color[1].$color[2].$color[2].$color[3].$color[3];
//                case 7:
//                    [$r, $g, $b] = sscanf($color, '#%02x%02x%02x');
//                    break;
//                default:
//                    p_error('please use html-like colors (e.g. #ffbbaa)');
//            }
//
//            if ($r !== null) {
//                $textCommand = sprintf(' q %d %s %s rg %s Q', $r, $g, $b, $textCommand);
//            } // Color RGB
//        } else {
//            p_error('please use html-like colors (e.g. #ffbbaa)');
//        }
//
//        if ($angle !== 0) {
//            $textCommand = sprintf(' q %s %s Q', $rotateCommand, $textCommand);
//        }
//
//        $data .= $textCommand;
//
//        $contentsObj->set_stream($data, false);
//
//        // Update the contents
//        $this->add_object($resourcesObj);
//        $this->add_object($contentsObj);
//    }
//
//    /**
//     * Adds an image to the document, in the specific page
//     *   NOTE: the image inclusion is taken from http://www.fpdf.org/; this is an adaptation
//     *         and simplification of function Image(); it does not take care about units nor
//     *         page breaks
//     *
//     * @param page_obj the page object (or the page number) in which the image will appear
//     * @param filename the name of the file that contains the image (or the content of the file, with the character '@' prepended)
//     * @param x the x position (in pixels) where the image will appear
//     * @param y the y position (in pixels) where the image will appear
//     * @param w the width of the image
//     * @param w the height of the image
//     */
//    public function add_image($pageObj, $filename, $x = 0, $y = 0, $w = 0, $h = 0)
//    {
//
//        // TODO: maybe we can create a function that "adds content to a page", and that
//        //       function will search for the content field and merge the resources, if
//        //       needed
//        p_warning('This function still needs work');
//
//        // Check that the page is valid
//        if (is_int($pageObj)) {
//            $pageObj = $this->get_page($pageObj);
//        }
//
//        if ($pageObj === false) {
//            return p_error('invalid page');
//        }
//
//        // Get the page height, to change the coordinates system (up to down)
//        $pagesize = $this->get_page_size($pageObj);
//        $pagesizeH = (float) (''.$pagesize[3]) - (float) (''.$pagesize[1]);
//
//        $result = $this->_add_image($filename, $x, $pagesizeH - $y, $w, $h);
//
//        return p_error('this function still needs work');
//
//        // Get the resources for the page
//        $resourcesObj = $this->get_indirect_object($pageObj['Resources']);
//        if (! isset($resourcesObj['ProcSet'])) {
//            $resourcesObj['ProcSet'] = new PDFValueList(['/PDF']);
//        }
//
//        $resourcesObj['ProcSet']->push(['/ImageB', '/ImageC', '/ImageI']);
//        if (! isset($resourcesObj['XObject'])) {
//            $resourcesObj['XObject'] = new PDFValueObject();
//        }
//
//        $resourcesObj['XObject'][$info['i']] = new PDFValueReference($imagesObjects[0]->get_oid());
//
//        // TODO: get the contents object in which to add the image.
//        //       this is a bit hard, because we have multiple options (e.g. the contents is an indirect object
//        //       or the contents is an array of objects)
//        $contentsObj = $this->get_indirect_object($pageObj['Contents']);
//
//        $data = $contentsObj->get_stream(false);
//        if ($data === false) {
//            return p_error('could not interpret the contents of the page');
//        }
//
//        // Append the command to draw the image
//        $data .= $result['command'];
//
//        // Update the contents of the page
//        $contentsObj->set_stream($data, false);
//
//        if ($addAlpha === true) {
//            $pageObj['Group'] = new PDFValueObject([
//                'Type' => '/Group',
//                'S' => '/Transparency',
//                'CS' => '/DeviceRGB',
//            ]);
//            $this->add_object($pageObj);
//        }
//
//        foreach ([$resourcesObj, $contentsObj] as $o) {
//            $this->add_object($o);
//        }
//
//        return true;
//    }
//}
