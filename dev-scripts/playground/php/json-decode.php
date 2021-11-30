<?php

echo PHP_EOL . 'true' . PHP_EOL;
print_r(json_decode(true, true));
echo PHP_EOL . 'false' . PHP_EOL;
print_r(json_decode(false, true));
echo PHP_EOL . 'null' . PHP_EOL;
print_r(json_decode(null, true));
echo PHP_EOL . 'string' . PHP_EOL;
print_r(json_decode('"blah"', true));
echo PHP_EOL . 'string in braces' . PHP_EOL;
print_r(json_decode('["blah"]', true));
