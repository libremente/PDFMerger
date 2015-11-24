<?php

// FPDI extension that preserves hyperlinks when copying PDF pages.
//
// (c) 2012, Andrey Tarantsov <andrey@tarantsov.com>, provided under the MIT license.
//
// Published at: https://gist.github.com/2020422
//
// Note: the free version of FPDI requires unprotected PDFs conforming to spec version 1.4.
// I use qpdf (http://qpdf.sourceforge.net/) to preprocess PDFs before running through this
// code, invoking it like this:
//
//     qpdf --decrypt --stream-data=uncompress --force-version=1.4 src.pdf temp.pdf
//
// then, after processing temp.pdf into out.pdf with FPDI, I run the following to re-establish
// protection:
//
//     qpdf --encrypt "" "" 40 --extract=n -- out.pdf final.pdf
//
class FPDI_with_annots extends FPDI {

    // default maxdepth prevents an infinite recursion on malformed PDFs (not theoretical, actually found in the wild)
    function resolve(&$parser, $smt, $maxdepth=10) {
        if ($maxdepth == 0)
            return $smt;

        if ($smt[0] == PDF_TYPE_OBJREF) {
            $result = $parser->pdf_resolve_object($parser->c, $smt);
            return $this->resolve($parser, $result, $maxdepth-1);

        } else if ($smt[0] == PDF_TYPE_OBJECT) {
            return $this->resolve($parser, $smt[1], $maxdepth-1);

        } else if ($smt[0] == PDF_TYPE_ARRAY) {
            $result = array();
            foreach ($smt[1] as $item) {
                $result[] = $this->resolve($parser, $item, $maxdepth-1);
            }
            $smt[1] = $result;
            return $smt;

        } else if ($smt[0] == PDF_TYPE_DICTIONARY) {
            $result = array();
            foreach ($smt[1] as $key => $item) {
                $result[$key] = $this->resolve($parser, $item, $maxdepth-1);
            }
            $smt[1] = $result;
            return $smt;

        } else {
            return $smt;
        }
    }

    function findPageNoForRef(&$parser, $pageRef) {
        $ref_obj = $pageRef[1]; $ref_gen = $pageRef[2];

        foreach ($parser->pages as $index => $page) {
          $page_obj = $page['obj']; $page_gen = $page['gen'];
          if ($page_obj == $ref_obj && $page_gen == $ref_gen) {
              return $index + 1;
          }
        }

        return -1;
    }

    function importPage($pageno, $boxName = '/CropBox') {
        $tplidx = parent::importPage($pageno, $boxName);

        $tpl =& $this->tpls[$tplidx];
        $parser =& $tpl['parser'];

        // look for hyperlink annotations and store them in the template
        if (isset($parser->pages[$pageno - 1][1][1]['/Annots'])) {
            $annots = $parser->pages[$pageno - 1][1][1]['/Annots'];
            $annots = $this->resolve($parser, $annots);

            $links = array();
            foreach ($annots[1] as $annot) if ($annot[0] == PDF_TYPE_DICTIONARY) {

                // all links look like:  << /Type /Annot /Subtype /Link /Rect [...] ... >>
                // but since not all the files contain links need to add a check
                if (isset($annot[1]['/Type'])) {
                    if ($annot[1]['/Type'][1] == '/Annot' && $annot[1]['/Subtype'][1] == '/Link') {
                        $rect = $annot[1]['/Rect'];
                        if ($rect[0] == PDF_TYPE_ARRAY && count($rect[1]) == 4) {
                            $x = $rect[1][0][1];
                            $y = $rect[1][1][1];
                            $x2 = $rect[1][2][1];
                            $y2 = $rect[1][3][1];
                            $w = $x2 - $x;
                            $h = $y2 - $y;
                            $h = -$h;
                        }

                        if (isset($annot[1]['/A'])) {
                            $A = $annot[1]['/A'];

                            if ($A[0] == PDF_TYPE_DICTIONARY && isset($A[1]['/S'])) {
                                $S = $A[1]['/S'];

                                //  << /Type /Annot ... /A << /S /URI /URI ... >> >>
                                if ($S[1] == '/URI' && isset($A[1]['/URI'])) {
                                    $URI = $A[1]['/URI'];

                                    if (is_string($URI[1])) {
                                        $uri = str_replace("\\000", '', trim($URI[1]));
                                        if (!empty($uri)) {
                                            $links[] = array($x, $y, $w, $h, $uri);
                                        }
                                    }

                                    //  << /Type /Annot ... /A << /S /GoTo /D [%d 0 R /Fit] >> >>
                                } else if ($S[1] == '/GoTo' && isset($A[1]['/D'])) {
                                    $D = $A[1]['/D'];
                                    if ($D[0] == PDF_TYPE_ARRAY && count($D[1]) > 0 && $D[1][0][0] == PDF_TYPE_OBJREF) {
                                        $target_pageno = $this->findPageNoForRef($parser, $D[1][0]);
                                        if ($target_pageno >= 0) {
                                            $links[] = array($x, $y, $w, $h, $target_pageno);
                                        }
                                    }
                                }
                            }

                        } else if (isset($annot[1]['/Dest'])) {
                            $Dest = $annot[1]['/Dest'];

                            //  << /Type /Annot ... /Dest [42 0 R ...] >>
                            if ($Dest[0] == PDF_TYPE_ARRAY && $Dest[0][1][0] == PDF_TYPE_OBJREF) {
                                $target_pageno = $this->findPageNoForRef($parser, $Dest[0][1][0]);
                                if ($target_pageno >= 0) {
                                    $links[] = array($x, $y, $w, $h, $target_pageno);
                                }
                            }
                        }
                    }
                }
            }
        }
        // echo "Links on page $pageno:\n";
        // print_r($links);
        if(isset($links)) {
            $tpl['links'] = $links;
        }

        return $tplidx;
    }

    function useTemplate($tplidx, $_x = null, $_y = null, $_w = 0, $_h = 0, $adjustPageSize = false) {
        $result = parent::useTemplate($tplidx, $_x, $_y, $_w, $_h, $adjustPageSize);

        // apply links from the template
        $tpl =& $this->tpls[$tplidx];
        if (isset($tpl['links'])) {
            foreach ($tpl['links'] as $link) {
                // $link[4] is either a string (external URL) or an integer (page number)
                if (is_int($link[4])) {
                    $l = $this->AddLink();
                    $this->SetLink($l, 0, $link[4]);
                    $link[4] = $l;
                }

                $this->Link(
                    $link[0]/$this->k,
                    ($this->fhPt-$link[1]+$link[3])/$this->k, 
                    $link[2]/$this->k, 
                    -$link[3]/$this->k, 
                    $link[4]
                );
            }
        }

        return $result;
    }

}