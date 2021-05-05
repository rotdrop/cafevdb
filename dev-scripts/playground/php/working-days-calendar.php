#! /usr/bin/php
<?php


$tzGMT = new \DateTimeZone('GMT');
$tzBRL = new \DateTimeZone('Europe/Berlin');

function get_easter_datetime($year = null, ?\DateTimeZone $timeZone = null)
{
  if (empty($year)) {
    $year = date('Y');
  }
  if (empty($timeZone)) {
    $timeZone = new \DateTimeZone('UTC');
  }
  $base = new \DateTimeImmutable($year . '-03-21', $timeZone);
  $days = easter_days($year);

  $easterSunday = $base->add(new \DateInterval("P{$days}D"));

  return $easterSunday;
}

$easterSunday1 = get_easter_datetime(null, $tzGMT);
$easterSunday2 = get_easter_datetime(null, $tzBRL);

echo date_default_timezone_get().PHP_EOL;
echo $easterSunday1->setTimeZone($tzBRL)->format('Y-m-d H:i:s').PHP_EOL;
echo $easterSunday2->format('Y-m-d H:i:s').PHP_EOL;

function workingDaysOffset($offsetDays, \DateTimeInterface $from = null)
{
  $holidays = array_flip([ '01-01', '05-01', '12-25', '12-26' ]);
  $weekend = array_flip([ 'Sun', 'Sat' ]);

  if (empty($from)) {
    $from = new \DateTimeImmutable();
  }
  $year = $from->format('Y');
  $timezone = $from->getTimezone();

  // variable holidays probably need to be recomputed based on $offsetDays
  // $surroundingEasterSundays = [
  //   get_easter_datetime($year - 1, $timezone),
  //   get_easter_datetime($year, $timezone),
  //   get_easter_datetime($year + 1, $timezone),
  // ];

  // foreach ($surroundingEasterSundays as $easterSunday) {
  //   $holidays[] = $easterSunday->modify('- 2 days')->format('Y-m-d');
  //   $holidays[] = $easterSunday->modify('+ 1 day')->format('Y-m-d');
  // }

  print_r($holidays);

  $dateIncr = $offsetDays > 0 ? '+ 1 day' : '- 1 day';
  $dayIncr = $offsetDays > 0 ? 1 : -1;
  $workingDates = [];
  $interval = new \DateInterval('P0D');
  $curDay = $from;
  while ($offsetDays != 0) {
    $curDay = $curDay->modify($dateIncr);
    if (isset($holidays[$curDay->format('m-d')])) {
      $interval->d += $dayIncr;
      continue;
    }
    if (isset($weekend[$curDay->format('D')])) {
      $interval->d += $dayIncr;
      continue;
    }
    $interval->d += $dayIncr;
    $workingDates[] = $curDay;
    $offsetDays -= $dayIncr;
  }

  return [ $interval, $workingDates ];
}

list($interval, $days) = workingDaysOffset(10);
