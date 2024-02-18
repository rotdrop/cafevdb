<?php

include_once __DIR__ . '/console-setup.php';

include_once __DIR__ . '/../../../vendor/autoload.php';

use NumberToWords\NumberToWords;

$numberToWords = \OC::$server->get(NumberToWords::class);

$locale = 'de_DE.UTF-8';
$lang = locale_get_primary_language($locale);

$myFormatter = new OCA\CAFEVDB\Common\NumberFormatter($locale);

$value = -1234.56789;

echo $myFormatter->currencyToWords(1, 'CHF') . PHP_EOL;
echo $myFormatter->currencyToWords(2, 'EUR') . PHP_EOL;
echo $myFormatter->currencyToWords(1.234, 'USD') . PHP_EOL;
echo $myFormatter->currencyToWords($value, 'USD') . PHP_EOL;
echo $myFormatter->formatCurrency($value, 'USD') . PHP_EOL;

echo $myFormatter->numberToWordsKWN(0, 0, 0) . PHP_EOL;
echo $myFormatter->numberToWordsKWN($value, 0, 3) . PHP_EOL;
echo $myFormatter->numberToWordsKWN($value, 2, 3) . PHP_EOL;
echo $myFormatter->numberToWordsKWN($value, 6, 10) . PHP_EOL;
echo $myFormatter->numberToWordsKWN(-15, 0, 0) . PHP_EOL;

echo $myFormatter->numberToWordsPhp($value, 0, 3) . PHP_EOL;
echo $myFormatter->numberToWordsPhp($value, 2, 3) . PHP_EOL;
echo $myFormatter->numberToWordsPhp($value, 6, 7) . PHP_EOL;
echo $myFormatter->numberToWordsPhp(0.1, 1, 1) . PHP_EOL;

echo $myFormatter->formatNumber($value, 0, 3) . PHP_EOL;
echo $myFormatter->formatNumber($value, 2, 3) . PHP_EOL;
echo $myFormatter->formatNumber($value, 6, 10) . PHP_EOL;
