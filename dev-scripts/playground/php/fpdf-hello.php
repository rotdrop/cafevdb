<?php

include_once __DIR__ . '/../../../vendor/autoload.php';

$string = 'Hello yy gg World!';
$fontSize = 32;

$pdf=new FPDF('L', 'pt', [$fontSize, 500]);
//$pdf = new FPDF('P', 'pt', 'a4');
$pdf->AddPage();
$pdf->SetFont('Arial', 'B', $fontSize);
$pdf->Text(0, $fontSize-8, $string);
$pdf->Output('F', 'blah.pdf');
