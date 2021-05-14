#! /usr/bin/php
<?php

function dateTime($arg1 = "now", $arg2 = null, $arg3 = null):\DateTimeImmutable
{
  if ($arg1 instanceof \DateTimeImmutable) {
    if ($arg2 !== null || $arg3 !== null) {
      throw \InvalidArgumentException('Excess arguments, expected 1, got 3.');
    }
    return $arg1;
  }
  if ($arg1 instanceof \DateTime) {
    if ($arg2 !== null || $arg3 !== null) {
      throw \InvalidArgumentException('Excess arguments, expected 1, got 3.');
    }
    return \DateTimeImmutable::createFromMutable($arg1);
  }
  if ($arg1 instanceof \DateTimeInterface) {
    if ($arg2 !== null || $arg3 !== null) {
      throw \InvalidArgumentException('Excess arguments, expected 1, got 3.');
    }
    return \DateTimeImmutable::createFromInterface($arg1);
  }
  if (is_string($arg1) && is_string($arg2)) {
    return \DateTimeImmutable::createFromFormat($arg1, $arg2, $arg3);
  } else if ($arg2 === null && $arg3 === null) {
    $timeStamp = filter_var($arg1, FILTER_VALIDATE_INT, [ 'min' => 0 ]);
    if ($timeStamp !== false) {
      return (new \DateTimeImmutable())->setTimestamp($timeStamp);
    } else if (is_string($arg1)) {
      return new \DateTimeImmutable($arg1);
    }
  } else if ($arg3 === null) {
    return new \DateTimeImmutable($arg1, $arg2);
  }
  throw new \InvalidArgumentException('Unsupported arguments');
}

function dueDate($dueDate, $date = null)
{
  $timeZone = new \DateTimeZone('Europe/Berlin');
  if (empty($date)) {
    $date = new \DateTimeImmutable();
  }
  $date = dateTime($date)->setTimezone($timeZone);
  $year = $date->format('Y'); // always until next year

  echo 'YEAR '.$year.PHP_EOL;

  $dueDate = dateTime($dueDate)
           ->setTimezone($timeZone)
           ->modify('+'.($year - $dueDate->format('Y') + 1).' years');

  return $dueDate;
}

$timeZone = new \DateTimeZone('Europe/Berlin');
$dueDate = \DateTimeImmutable::createFromFormat('Y-m-d', '2017-07-06', $timeZone);
print_r($dueDate);

print_r(dueDate($dueDate, '2021-08-01'));
