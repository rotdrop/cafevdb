<?php

ini_set("mbstring.internal_encoding", "UTF-8" );

// $fontFile = '/usr/share/fonts/corefonts/arial.ttf';
$fontFile = '/usr/share/fonts/dejavu/DejaVuSans.ttf';
$text = '✔';

echo mb_detect_encoding($text) . PHP_EOL;

$textBox = imagettfbbox(10.0, 0, $fontFile, $text);
echo $text . PHP_EOL;
