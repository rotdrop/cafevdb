<?php

include_once __DIR__ . '/console-setup.php';

include_once __DIR__ . '/../../../vendor/autoload.php';

use MathPHP\Number\Rational;
use BcMath\Number;
use OCA\CAFEVDB\Common\RationalNumber;

echo (new RationalNumber(0, 43, 10000))->toDecimal(4) . PHP_EOL;
echo (new RationalNumber(0, 43, -10000))->toDecimal(4) . PHP_EOL;
echo (new RationalNumber(-1, 43, -10000))->toDecimal(4) . PHP_EOL;

$decimals = [
  '.1234',
  '-.1234',
  '0.1234',
  '-0.1234',
  '12345.678',
  '-12345.678',
  '1234.',
  '1234',
  1234,
];

foreach ($decimals as $decimal) {
  $rational = RationalNumber::fromDecimal($decimal);
  // print_r($rational);
  echo $rational->toFloat() . PHP_EOL;
  echo (string)$rational . PHP_EOL;
}

exit;

$insuranceRate1 = new Number('0.0043');
print_r($insuranceRate1);
print_r((new Number(1)) / (new Number(3)));

exit;

$taxRate = new Rational(1, 19, 100); // 1.19
$insuranceRate1 = new Rational(0, 43, 10000); // 0.0043
$insuranceRate2 = new Rational(0, 51, 10000);
$insuranceRate3 = new Rational(0, 50, 10000);

$insurances = [
  [ new Rational(2600, 0, 1), $insuranceRate3, new Rational(1, 0, 12) ],
  [ new Rational(2980, 0, 1), $insuranceRate3, new Rational(0, 5, 12) ],
  [ new Rational(2800, 0, 1), $insuranceRate3, new Rational(0, 7, 12) ],
  [ new Rational(6000, 0, 1), $insuranceRate2, new Rational(0, 11, 12) ],
];

$sum = new Rational(0, 0, 1);

foreach ($insurances as $item) {
  print_r($item);
  $sum = $sum->add($item[0]->multiply($item[1])->multiply($item[2]));
  print_r($sum);
}

$result = $sum->multiply($taxRate);

echo (10000 % $result->getDenominator()) . PHP_EOL;

print_r($result);

print_r($result->multiply(1000)->add(5));

echo (new Rational(0, (($result->multiply(1000)->add(5)->getWholePart() / 10)), 100))->toFloat() . PHP_EOL;

echo $result->toFloat() . PHP_EOL;

// denominators:
// instrument values:   100 * 12 = 1200 yearfraction
// insurance rates:   10000
// tax-rates:           100
//
// maximum denominator: 1.200.000.000

// N / 1.200.000.000
//
// Runden auf 2 Stellen: + 5/1000 = 6.000.000 / 1.200.000.000
