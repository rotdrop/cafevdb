<?php

include_once __DIR__ . '/../../../../../3rdparty/autoload.php';
include_once __DIR__ . '/../../../vendor/autoload.php';

$fmt = new IntlDateFormatter(
    'de_DE',
    IntlDateFormatter::SHORT,
    IntlDateFormatter::SHORT,
    'Europe/Berlin',
);
echo 'pattern of the formatter is : ' . $fmt->getPattern() . PHP_EOL;
echo 'First Formatted output is ' . $fmt->format(0) . PHP_EOL;


echo ($format = Punic\Calendar::getDateFormat('short', 'de_DE')) . PHP_EOL;
echo Punic\Calendar::getDateFormat('medium', 'de_DE') . PHP_EOL;
echo ($format = Punic\Calendar::getDateFormat('full', 'de_DE')) . PHP_EOL;


echo Punic\Calendar::getTimeFormat('short', 'de_DE') . PHP_EOL;
echo Punic\Calendar::getTimeFormat('medium', 'de_DE') . PHP_EOL;

echo Carbon\Carbon::now()->locale('de')->isoFormat($fmt->getPattern()) . PHP_EOL;
