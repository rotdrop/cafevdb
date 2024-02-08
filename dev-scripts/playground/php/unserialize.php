<?php

$data = [
  'admin' => 'a:0:{}',
  'camerata' => 'a:9:{i:0;s:18:"cameratashareowner";i:1;s:18:"claus-justus.heine";i:2;s:13:"elke.gliemann";i:3;s:10:"katha.puff";i:4;s:16:"katharina.dufner";i:5;s:13:"maren.kroeger";i:6;s:12:"martina.meng";i:7;s:13:"michael.wendt";i:8;s:12:"uschi.kemeny";}',
  'camerata-admin' => 'a:2:{i:0;s:18:"claus-justus.heine";i:1;s:13:"michael.wendt";}',
  'dummy' => 'a:1:{i:0;s:18:"claus-justus.heine";}',
  'people' =>
'a:12:{i:0;s:15:"bettina.kriegel";i:1;s:13:"bilbo.baggins";i:2;s:21:"camerata-authprovider";i:3;s:18:"cameratashareowner";i:4;s:18:"claus-justus.heine";i:5;s:13:"elke.gliemann";i:6;s:10:"katha.puff";i:7;s:16:"katharina.dufner";i:8;s:13:"maren.kroeger";i:9;s:12:"martina.meng";i:10;s:13:"michael.wendt";i:11;s:12:"uschi.kemeny";}',
  'schatzmeister' => 'a:1:{i:0;s:18:"claus-justus.heine";}',
];

foreach ($data as $group => $datum) {
  echo "GROUP " . $group . PHP_EOL;
  print_r(unserialize($datum));
}
