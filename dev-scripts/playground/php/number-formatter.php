<?php


$fmt = new \NumberFormatter('de_DE.UTF-8', \NumberFormatter::SPELLOUT);

echo $fmt->parse('eine woche und zwei tage') . PHP_EOL;

$fmt = new \NumberFormatter($locale, \NumberFormatter::CURRENCY);
echo $fmt->format(13.41) . PHP_EOL;
echo $fmt->getSymbol(\NumberFormatter::CURRENCY_SYMBOL) . PHP_EOL;
echo $fmt->getTextAttribute(NumberFormatter::CURRENCY_CODE) . PHP_EOL;
