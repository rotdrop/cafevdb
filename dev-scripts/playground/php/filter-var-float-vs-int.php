<?php

$dateTime = '1740259295.933';
$dateTime = '0';

$timeStamp = filter_var($dateTime, FILTER_VALIDATE_INT, [ 'options' => [ 'min_range' => 1 ] ]);
if ($timeStamp === false) {
  $timeStamp = filter_var($dateTime, FILTER_VALIDATE_FLOAT, [ 'options' => [ 'min_range' => 1 ] ]);
}

echo "TIMESTAMP " . $timeStamp . PHP_EOL;

print_r((new DateTimeImmutable())->modify('@' . $timeStamp));
