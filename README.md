# PDFMerger with ANNOTATIONS for PHP 5

You've created several PDF files and after merging them with some PDF merger
you've lost the hyperlinks in the resulting file? By means of this PHP library
which exploits a combination
of `FPDI + FPDF` and `TCPDF`, it's possible to merge and maintain the links!


This modified version uses the following:  

* FPDI v1.4.4 
* FPDF_TPL v1.2.3
* Using fpdi2tcpdf it is possible to use FPDF by extending TCPDF

In this way it is possible to *maintain* annotations like e.g. *links* inside
a PDF document and browse it correctly.

Tested with PHP 5.x.

## Example Usage
```php
include 'PDFMerger.php';

$pdf = new PDFMerger;

$pdf->addPDF('samplepdfs/one.pdf', '1, 3, 4'); // with links
$pdf->addPDF('samplepdfs/two.pdf', '1-2'); 
$pdf->addPDF('samplepdfs/three.pdf', 'all');

$pdf->merge('file', 'samplepdfs/test.pdf'); // generate the file
$pdf->merge('download', 'samplepdfs/test1.pdf'); // force download 

// both 'test.pdf' and 'test1.pdf' will have clickable links 
// REPLACE 'file' WITH 'browser', 'download', 'string', or 'file' for output options
```

## Author 
Fork of PDFMerger created by `Jarrod Nettles` December 2009 `<jarrod@squarecrow.com>`.  
This version is maintained by `libremente` <surf [AT] libremente [DOT] eu>. 

### Licensing
* This software has been released under the terms of a MIT License from the
  creator in 2009. Also this modified version of the code is released under the
  same MIT license.
* FPDI is released under Apache License version 2.0, and the copyright
  2004-2013 belongs to Setasign - Jan Slabon.
* TCPDF is released under a GNU-LGPL v3 license, and the Copyright (C)
  2002-2014 belongs to Nicola Asuni.
