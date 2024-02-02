<?php

$string = 'FILENAME:AndSoOn';

preg_match('/^(\w+)\:\s*(.*?)$/', $string, $matches);

print_r($matches);
