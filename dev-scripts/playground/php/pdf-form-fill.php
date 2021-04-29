<?php

include_once __DIR__ . '/../../../vendor/autoload.php';

$pdfFile = __DIR__ . '/../forms/LastSchriftAnhangProjektEinladung.pdf';

$fields = [
  'bankAccountIBAN' => 'DE50360100430333155436',
];

$pdfContent = file_get_contents($pdfFile);

$pdfData = 'data://application/pdf;base64,'.base64_encode($pdfContent);

// $pdfForm = new FPDM($pdfContent);
// $pdfForm->Load($fields);
// $pdfForm->Merge();
// $pdfForm->Output('F', 'foo.pdf');

$pdfForm = (new \mikehaertl\pdftk\Pdf('-'));
$pdfForm->getCommand()
        ->setStdIn($pdfContent);
$pdfForm->fillForm($fields)
        ->needAppearances()
        ->saveAs('foo.pdf');
