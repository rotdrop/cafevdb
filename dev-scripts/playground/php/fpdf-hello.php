<?php

include_once __DIR__ . '/console-setup.php';

include_once __DIR__ . '/../../../vendor/autoload.php';

$pdf=new FPDF('L', 'mm', [150, 20]);
// $pdf->AddPage();
// $pdf->SetFont('Arial','B',16);
$pdf->Cell(0,20,'Hello World!');
$pdf->Output();
