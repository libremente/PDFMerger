<?php
/**
 *
 * Class for easily merging PDFs (or specific pages of PDFs) together into one.
 * Output to a file, browser, download, or return as a string.  Unfortunately,
 * this class does not preserve many of the enhancements your original PDF
 * might contain. It treats your PDF page as an image and then concatenates
 * them all together.
 * 
 * Note that your PDFs are merged in the order that you provide them using the
 * addPDF function, same as the pages.  If you put pages 12-14 before 1-5 then
 * 12-15 will be placed first in the output.
 * 
 * === Originally uses: ===
 * FPDI 1.3.1 from Setasign
 * FPDF 1.6 by Olivier Plathey with FPDF_TPL extension 1.1.3 by Setasign
 *
 * === This version uses: ====
 * FPDI v1.4.4 
 * FPDF_TPL v1.2.3 
 * in order to provide the possibility of rendering annotations (links) inside
 * the PDF By using fpdi2tcpdf it is possible to use FPDF by extending TCPDF
 * (see fpdi2tcpdf_bridge.php)
 * 
 * Both of these packages are free and open source software, bundled with this
 * class for ease of use. 
 * They are not modified in any way. 
 * 
 */

class PDFMerger
{
    private $_files; //['form.pdf']  ["1,2,4, 5-19"]
    private $_fpdi;

    /**
     * Merge PDFs.
     * @return void
     */
    public function __construct()
    {
        // importing fpdi + fpdi2tcpdf (the bridge) + fpdf_tpl
        require_once('fpdi/fpdi2tcpdf_bridge.php');
        require_once('fpdi/fpdf_tpl.php');
        require_once('fpdi/fpdi.php');
        // adding the annotations extension in order to render links
        require_once('fpdi/fpdi_with_annotations.php');
    }

    /**
     * Add a PDF for inclusion in the merge with a valid file path. Pages should be formatted: 1,3,6, 12-16.
     * @param $filepath
     * @param $pages
     * @return void
     */
    public function addPDF($filepath, $pages = 'all')
    {
        if (file_exists($filepath)) {
            if (strtolower($pages) != 'all') {
                $pages = $this->_rewritepages($pages);
            }

            $this->_files[] = array($filepath, $pages);
        } else {
            throw new exception("Could not locate PDF on '$filepath'");
        }

        return $this;
    }

    /**
     * Merges your provided PDFs and outputs to specified location.
     * @param $outputmode
     * @param $outputname
     * @return PDF
     */
    public function merge($outputmode = 'browser', $outputpath = 'newfile.pdf')
    {
        if (!isset($this->_files) || !is_array($this->_files)): throw new exception("No PDFs to merge."); endif;
        // new FPDI_with_annots object for rendering links
        $fpdi = new FPDI_with_annots;

        try {
            //merger operations
            foreach ($this->_files as $file) {
                $filename = $file[0];
                $filepages = $file[1];
                $count = $fpdi->setSourceFile($filename);

                //add the pages
                if ($filepages == 'all') {
                    for ($i = 1; $i <= $count; $i++) {
                        $template = $fpdi->importPage($i);
                        $size = $fpdi->getTemplateSize($template);
                        $fpdi->AddPage('P', array($size['w'], $size['h']));
                        $fpdi->useTemplate($template);
                    }
                } else {
                    foreach ($filepages as $page) {
                        if (!$template = $fpdi->importPage($page)) {
                            throw new exception("Impossible to load the '$page' in the PDF called '$filename'. Check that the page exists.");
                        }

                        $size = $fpdi->getTemplateSize($template);
                        $fpdi->AddPage('P', array($size['w'], $size['h']));
                        $fpdi->useTemplate($template);
                    }
                }
            }
        } catch (Exception $e) {
            printf("Some files use some unknown formats. It is not possible to create the final pdf file.");
        }

        //output operations
        $mode = $this->_switchmode($outputmode);

        if ($mode == 'S') {
            return $fpdi->Output($outputpath, 'S');
        } else {
            if ($fpdi->Output($outputpath, $mode)) {
                return true;
            } else {
                throw new exception("Error outputting PDF to '$outputmode'.");
                return false;
            }
        }
    }

    /**
     * FPDI uses single characters for specifying the output location. Change our more descriptive string into proper format.
     * @param $mode
     * @return Character
     */
    private function _switchmode($mode)
    {
        switch (strtolower($mode)) {
            case 'download':
                return 'D';
                break;
            case 'browser':
                return 'I';
                break;
            case 'file':
                return 'F';
                break;
            case 'string':
                return 'S';
                break;
            default:
                return 'I';
                break;
        }
    }

    /**
     * Takes our provided pages in the fotcrm of 1,3,4,16-50 and creates an array of all pages
     * @param $pages
     * @return unknown_type
     */
    private function _rewritepages($pages)
    {
        $pages = str_replace(' ', '', $pages);
        $part = explode(',', $pages);

        //parse hyphens
        foreach ($part as $i) {
            $ind = explode('-', $i);

            if (count($ind) == 2) {
                $x = $ind[0]; //start page
                $y = $ind[1]; //end page

                if ($x > $y): throw new exception("Starting page, '$x' is greater than ending page '$y'.");
                    return false; endif;

                //add middle pages
                while ($x <= $y): $newpages[] = (int)$x;
                    $x++; endwhile;
            } else {
                $newpages[] = (int)$ind[0];
            }
        }

        return $newpages;
    }

}
