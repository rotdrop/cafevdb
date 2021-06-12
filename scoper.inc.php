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
    function(string $filePath, string $prefix, string $content): string {
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
      return $content;
    },
    // /** @var array<\Doctrine\ORM\Mapping\JoinColumn> */
    function(string $filePath, string $prefix, string $content): string {
      if (strpos($filePath, 'doctrine/orm/lib/Doctrine/ORM') !== false) {
        return preg_replace(
          '%(?<!' . preg_quote($prefix) . ')\\\\Doctrine\\\\%',
          $prefix . '\\Doctrine\\',
          $content
        );
      }
      return $content;
    },
    function(string $filePath, string $prefix, string $content): string {
      if (strpos($filePath, 'gedmo/doctrine-extensions/src/Mapping/MappedEventSubscriber.php') !== false) {
        return preg_replace(
          "%'Gedmo\\\\%",
          "'" . $prefix . "\\\\Gedmo\\\\",
          $content
        );
      }
      return $content;
    },
    // Remove scoper namespace prefix from Symfony polyfills namespace
    function(string $filePath, string $prefix, string $contents): string {
      if (!preg_match('{vendor-wrapped/symfony/polyfill[^/]*/bootstrap.php}i', $filePath)) {
        return $contents;
      }

      return preg_replace('/namespace .+;/', '', $contents);
    },
  ],
];
