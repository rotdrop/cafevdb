<?php

$locale = 'de_DE.UTF-8';

$fmt = new \NumberFormatter($locale, \NumberFormatter::SPELLOUT);
echo $fmt->format(97.41) . PHP_EOL;
echo $fmt->format(7143.00) . PHP_EOL;

echo $fmt->parse('eine woche und zwei tage') . PHP_EOL;

$fmt = new \NumberFormatter($locale, \NumberFormatter::CURRENCY);
echo $fmt->format(13.41) . PHP_EOL;
echo $fmt->getSymbol(\NumberFormatter::CURRENCY_SYMBOL) . PHP_EOL;
echo $fmt->getTextAttribute(NumberFormatter::CURRENCY_CODE) . PHP_EOL;

$fmt = new \NumberFormatter(
  $locale,
  0
  // |\NumberFormatter::SPELLOUT
  |\NumberFormatter::CURRENCY
);

$code = $fmt->getTextAttribute(NumberFormatter::CURRENCY_CODE);
$symbol = $fmt->getSymbol(\NumberFormatter::CURRENCY_SYMBOL);

echo $fmt->formatCurrency((float)13.41, $code) . PHP_EOL;
echo $symbol . PHP_EOL;
echo $code . PHP_EOL;
