<?php

$blah = 'foo.bar';

echo substr($blah, 0, strrpos($blah, '.')) . PHP_EOL;

$blah = 'foobar';

echo substr($blah, 0, strrpos($blah, '.') ?: null) . PHP_EOL;

$blah = '.foobar';

echo substr($blah, 0, strrpos($blah, '.') ?: null) . PHP_EOL;
