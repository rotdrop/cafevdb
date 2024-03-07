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

$string = '\'Horde_
\\class_alias(\'OCA\\\\CAFEVDB\\\\Wrapped\\\\Horde_Imap_Client_Socket\', \'Horde_Imap_Client_Socket\')';

echo $string . PHP_EOL . PHP_EOL;

echo preg_replace('/(?<!class_alias[^,]+,\*=)\'Horde_/', '\'\\OCA\\CAFEVDB\\Wrapped\\Horde_', $string) . PHP_EOL;
