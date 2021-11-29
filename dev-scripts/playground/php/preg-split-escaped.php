<?php
$string = '10\\\\.11.2021\, 22:00, 17.11.2021\, 21:00';


$array = preg_split('~\\\\.(*SKIP)(*FAIL)|,~s', $string);

echo '~\\\\.(*SKIP)(*FAIL)|,~s'.PHP_EOL;
print_r($array);

$delim = ',';
$trimExpr = '\s*';
$escape = '\\';
$splitExpr =  '/'.$trimExpr.preg_quote($escape).'.'.'(*SKIP)(*FAIL)|'.preg_quote($delim, '/').$trimExpr.'/s';

echo $string . PHP_EOL;
print_r(
  str_replace(
    [ $escape.$escape, $escape.$delim ],
    [ $escape, $delim ],
    preg_split($splitExpr, $string, -1, PREG_SPLIT_NO_EMPTY))
);
