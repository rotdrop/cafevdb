<?php


/** Transliterate the given string to the given or default locale */
function transliterate(string $string, $locale):string
{
  $oldlocale = setlocale(LC_CTYPE, '0');
  setlocale(LC_CTYPE, $locale);
  $result = iconv('UTF-8', 'ASCII//TRANSLIT', $string);
  setlocale(LC_CTYPE, $oldlocale);
  return $result;
}

$string = 'èÈéÉüÜöÖäÄß';

echo $string . ' -> ' . transliterate($string, 'de_DE.UTF-8').PHP_EOL;
echo $string . ' -> ' . transliterate($string, 'de_DE').PHP_EOL;
echo $string . ' -> ' . transliterate($string, 'fr_FR.UTF-8').PHP_EOL;
echo $string . ' -> ' . transliterate($string, 'fr_FR').PHP_EOL;
