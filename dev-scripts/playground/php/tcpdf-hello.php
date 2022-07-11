<?php

include_once __DIR__ . '/../../../vendor/autoload.php';

$text = 'eHello gg yy World';
$fontSize = 16;

$pageWidth = 595.3;
$pageHeight = 841.9;
$orientation = $pageHeight > $pageWidth ? 'P' : 'L';

$pdf = new TCPDF();

$pdf->setPageUnit('pt');
$pdf->setFont('dejavusansmono');
$margin = 0; // self::OVERLAY_FONTSIZE;
$pdf->setMargins($margin, $margin, $margin, $margin);
$pdf->setAutoPageBreak(false);
$pdf->setPrintHeader(false);
$pdf->setPrintFooter(false);

$pdf->setFontSize($fontSize);
$stringWidth = $pdf->GetStringWidth($text);
$fontSize = 0.4 * $pageWidth / $stringWidth * $fontSize;
$pdf->setFontSize($fontSize);
$padding = 0.25 * $fontSize;
$pdf->setCellPaddings($padding, $padding, $padding, $padding);

$pdf->startPage($orientation, [ $pageWidth, $pageHeight ]);


$cellWidth = 0.4 * $pageWidth + 2*$padding;


$pdf->setXY(0.5 * $pageWidth, .75 * $fontSize);
$pdf->Cell(0.5 * $pageWidth, 1.5*$fontSize, $text, calign: 'A', valign: 'T', align: 'L', fill: false);



$pdf->setColor('fill', 200);
$pdf->setColor('text', 255, 0, 0);

$pdf->SetAlpha(1, 'Normal', 0.2);
$pdf->Rect($pageWidth - $cellWidth, 0, $cellWidth, 1.5 * $fontSize, style: 'F', fill_color: [ 200 ]);
$pdf->SetAlpha(1, 'Normal', 1);
$pdf->setXY($pageWidth - $cellWidth, 0.25 * $fontSize);
$pdf->Cell($cellWidth, 1.5*$fontSize, $text, calign: 'A', valign: 'T', align: 'R', fill: false);
$pdf->endPage();

$output = $pdf->Output('blah.pdf', 'S');

file_put_contents('blah.pdf', $output);
