<?php

$dateTime = '1740259295.933';

$timeStamp = filter_var($dateTime, FILTER_VALIDATE_INT, [ 'min_range' => 0 ]);
if ($timeStamp === false) {
  $timeStamp = filter_var($dateTime, FILTER_VALIDATE_FLOAT, [ 'min_range' => 0 ]);
}

echo "TIMESTAMP " . $timeStamp . PHP_EOL;

print_r((new DateTimeImmutable())->modify('@' . $timeStamp));
