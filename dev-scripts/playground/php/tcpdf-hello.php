<?php

include_once __DIR__ . '/../../../vendor/autoload.php';

$string = 'eHello gg yy World';
$fontSize = 32;

$pdf = new TCPDF('L', 'pt', [1*$fontSize, 500]);

$pdf->setFont('dejavusans');
$pdf->setFontSize($fontSize);
$pdf->setMargins(0, 0);
$pdf->setCellPaddings(0, 0);
$pdf->setAutoPageBreak(false);
$pdf->setPrintHeader(false);
$pdf->setPrintFooter(false);
$pdf->SetAlpha(0);
$stringWidth = $pdf->getStringWidth($string);
$pdf->startPage('L', [1*$fontSize, $stringWidth]);
$pdf->Text(0, $fontSize, $string, calign: 'D', valign: 'T', align: 'L');
// $pdf->Text(0, $fontSize+8, $string);
$output = $pdf->Output('blah.pdf', 'S');

file_put_contents('blah.pdf', $output);
