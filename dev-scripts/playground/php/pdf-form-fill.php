<?php

include_once __DIR__ . '/../../../vendor/autoload.php';

$pdfFile = __DIR__ . '/../forms/LastSchriftAnhangProjektEinladung.pdf';

$fields = [
  'bankAccountIBAN' => 'DE50360100430333155436',
];

$pdfForm = new FPDM($pdfFile);
$pdfForm->Load($fields);
$pdfForm->Merge();
$pdfForm->Output('F', 'foo.pdf');
