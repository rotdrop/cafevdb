#! /usr/bin/php
<?php

$str = 'PREFIX ${MEMBER||ABLAH|<style>table.blah * \{ border:4px solid red; \} } POSTFIX';

$nameSpace = 'MEMBER';
$nameSpace2 = 'MITGLIED';

// (?<!mailto:)

// $regexp = '/([^$]|^)[$]{('.$nameSpace.'|'.$nameSpace2.')(.)\3([^}]+) }/u';
$regexp = '/([^$]|^)[$]{('.$nameSpace.'|'.$nameSpace2.')(.)\3(.*?)(?<!\\\)}/u';

echo $regexp.PHP_EOL;

$result = preg_replace_callback(
  $regexp,
  function($matches) {
    print_r($matches);
    return $matches[1].$matches[4];
  },
  $str);

echo $result.PHP_EOL;

echo preg_replace('/\\\\(.)/u', '$1', $result).PHP_EOL;
