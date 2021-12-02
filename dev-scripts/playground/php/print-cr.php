<?php

function normalizeSpaces($name, $singleSpace = ' ')
{
  /* Normalize name and translation */
  $name = str_replace("\xc2\xa0", "\x20", $name);
  $name = trim($name);
  $name = str_replace("\r\n", "\n", $name);
  $name = preg_replace('/\h*,/', ',', $name);
  $name = preg_replace('/\h+/', $singleSpace, $name);
  $name = preg_replace('/\h+([\n.,;:?!])/', '$1', $name);

  return $name;
}

$string = "Hello ,  \r\n   World   !\xc2\xa0

Blah





";

echo $string . PHP_EOL . '--' . PHP_EOL;
echo normalizeSpaces($string, ' ') . PHP_EOL;

$value = '4.000,00 €';

if (preg_match('/^(€)?\h*([+-]?)\h*(\d{1,3}(\.\d{3})*|(\d+))(\,(\d{2}))?\h*(€)?$/',
               $value, $matches)
    ||
    preg_match('/^(€)?\h*([+-]?)\h*(\d{1,3}(\,\d{3})*|(\d+))(\.(\d{2}))?\h*(€)?$/',
               $value, $matches)) {
  print_r($matches);
} else {
  echo 'NO MATCH' . PHP_EOL;
}
