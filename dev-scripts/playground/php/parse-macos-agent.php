<?php

$agent = 'macOS/12.6.3 (21G419) CalendarAgent/961.4.2';
$agent = 'macOS/12.6.3 (21G419) AddressBookCore/2498.5';
$agent = 'OSX agent string: macOS/13.2.1 (22D68) dataaccessd/1.0';

if (preg_match('|macOS/([0-9]+)\\.([0-9]+)\\.([0-9]+)\s+\((\w+)\)\s+([^/]+)/([0-9]+)(?:\\.([0-9]+))?(?:\\.([0-9]+))?$|i', $agent, $matches)) {
  print_r($matches);
}
