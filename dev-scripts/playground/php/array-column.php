<?php

$CALENDARS = [
  [ 'uri' => 'concerts', 'public' => true ],
  [ 'uri' => 'rehearsals', 'public' => true ],
  [ 'uri' => 'other', 'public' => true ],
  [ 'uri' => 'management', 'public' => false ],
  [ 'uri' => 'finance', 'public' => false ],
];

print_r(array_column($CALENDARS, 'uri'));
