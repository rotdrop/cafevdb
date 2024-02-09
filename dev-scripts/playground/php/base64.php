<?php


$string = 'test'; // '+4915165166660';


$result = base64_decode($string, true);

echo 'RESULT: ' . $result . ' ' . strlen($result) . PHP_EOL;

base64_encode($result);

echo 'EQUAL: ' . $result . ' <-> ' . $string . PHP_EOL;
