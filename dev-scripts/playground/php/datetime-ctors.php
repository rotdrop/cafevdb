#! /usr/bin/php
<?php

echo phpversion().PHP_EOL;
echo date_default_timezone_get().PHP_EOL;
// print_r(new \DateTime);
// print_r(new \DateTimeImmutable);
// print_r(new \DateTimeImmutable('@123'));
// # PHP 8
// #print_r(\DateTimeImmutable::createFromInterface(new \DateTime));

// echo (new \DateTime)->format('U.u').PHP_EOL;
// echo (new \DateTimeImmutable)->format('U.u').PHP_EOL;
// echo microtime(true).PHP_EOL;
// echo number_format(microtime(true), 6, '.', '').PHP_EOL;

// $class = \DateTime::class;
// $date = new \DateTimeImmutable;

// print_r($class::createFromFormat('U.u', $date->format('U.u')));

// echo (new \DateTimeImmutable)->format("Y-m-d H:i:s.u").PHP_EOL;

$timeZone = new \DateTimeZone('Europe/Berlin');
print_r($timeZone);

$now = new \DateTimeImmutable('now');
print_r($now);

$nowTz = new \DateTimeImmutable('now', $timeZone);
print_r($nowTz);

echo "STAMP NOW ".$now->getTimestamp().PHP_EOL;
echo "STAMP NOWTZ ".$nowTz->getTimestamp().PHP_EOL;
echo "TIME NOW ".(string)$now->format('Y-m-d H:i:s').PHP_EOL;
echo "TIME NOW TZ  ".(string)$nowTz->format('Y-m-d H:i:s').PHP_EOL;
echo "EQUAL: ".(int)($now == $nowTz).PHP_EOL;
echo "EQUAL STAMP: ".(int)($now->getTimestamp() == $nowTz->getTimestamp()).PHP_EOL;
echo "LT: ".(int)($now < $nowTz).PHP_EOL;
echo "GT: ".(int)($now > $nowTz).PHP_EOL;
