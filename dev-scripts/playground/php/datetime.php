<?php

$date = new \DateTimeImmutable('Europe/Berlin midnight');

print_r($date);

print_r(json_decode(json_encode($date), associative: true));
