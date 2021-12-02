<?php

print_r(array_map(function($value) { return sprintf('%04d', $value); }, array_merge([0], range(2, 6))));
