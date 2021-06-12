<?php

declare(strict_types=1);

use Isolated\Symfony\Component\Finder\Finder;

return [

  'prefix' => 'OCA\\CAFEVDB\\Wrapped',

  // By default when running php-scoper add-prefix, it will prefix all relevant code found in the current working
  // directory. You can however define which files should be scoped by defining a collection of Finders in the
  // following configuration key.
  //
  // For more see: https://github.com/humbug/php-scoper#finders-and-paths
  'finders' => [
    Finder::create()
      ->files()
      ->ignoreVCS(true)
      ->followLinks(true)
      ->in('vendor-wrapped'),
  ],
  'patchers' => [
    function (string $filePath, string $prefix, string $content): string {
      //
      // PHP-Parser patch conditions for file targets
      //
      // if ($filePath === '/path/to/offending/file') {
      //   return preg_replace(
      //     "%\$class = 'Humbug\\\\Format\\\\Type\\\\' . \$type;%",
      //     '$class = \'' . $prefix . '\\\\Humbug\\\\Format\\\\Type\\\\\' . $type;',
      //     $content
      //   );
      // }
      if (strpos($filePath, 'doctrine/orm/lib/Doctrine/ORM') !== false) {
        return preg_replace(
          "%(constant|defined)\\('Doctrine\\\\%",
          "\$1('" . $prefix . "\\Doctrine\\",
          $content
        );
      }
      if (strpos($filePath, 'gedmo/doctrine-extensions/src/Mapping/MappedEventSubscriber.php') !== false) {
        return preg_replace(
          "%'Gedmo\\\\%",
          "'" . $prefix . "\\\\Gedmo\\\\",
          $content
        );
      }

      return $content;
    },
  ],
];
