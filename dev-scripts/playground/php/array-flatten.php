#! /usr/bin/php
<?php

$data = 'blah blub';
$array = ['blah', 'blub'];

echo implode(' ', (array)$data) . PHP_EOL;
echo implode(' ', (array)$array) . PHP_EOL;
