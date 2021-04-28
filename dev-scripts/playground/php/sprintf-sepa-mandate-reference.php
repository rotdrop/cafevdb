<?php

$musId = 13;
$prjId = 15;
$firstName = 'Claus-Justus';
$lastName = '';
$projectName = 'AbcdefgHijklmnopQrstuvwXyz';
$projectYear = '2021';
$sequence = 13;

// 5 + 5 + 3 + 3 = 16, 35 - 16 = 19 Project + Jahr, 15 für Projectame

$ref = sprintf('%04d-%04d-%\'X1.1s%\'X1.1s-%.15s%d+%02d',
               $prjId, $musId,
               $firstName, $lastName,
               $projectName, $projectYear,
               $sequence);

echo preg_replace(
  '/[+][0-9]{2}$/',
  sprintf('+%02d', $sequence),
  $ref).PHP_EOL;
