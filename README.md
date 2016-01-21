PDFMerger
=========

PHP Code to merge different PDFs using several libraries in order to maintain annotations (links & co)

* Originally uses FPDI 1.3.1 from Setasign.
* In this modified version uses FPDI v1.4.4 + FPDF_TPL v1.2.3 in order to use the annotations (render also links).
* By using fpdi2tcpdf it is possible to use FPDF by extending TCPDF!

#PDFMerger with ANNOTATIONS for PHP

Fork of PDFMerger created by ```Jarrod Nettles``` December 2009 jarrod@squarecrow.com.

This modified version uses the following:  

- FPDI 1.3.1 from Setasign
- FPDF 1.6 by Olivier Plathey with FPDF_TPL extension 1.1.3 by Setasign

In this way it is possible to *maintain* annotations like e.g. *links* inside a PDF document and browse it correctly.

### Example Usage
```php
include 'PDFMerger.php';

$pdf = new PDFMerger;

$pdf->addPDF('samplepdfs/one.pdf', '1, 3, 4');
$pdf->addPDF('samplepdfs/two.pdf', '1-2');
$pdf->addPDF('samplepdfs/three.pdf', 'all');

$pdf->merge('file', 'samplepdfs/test.pdf'); // generate the file

$pdf->merge('download', 'samplepdfs/test1.pdf'); // force download 

// REPLACE 'file' WITH 'browser', 'download', 'string', or 'file' for output options
```