<?php

declare(strict_types=1);

use Isolated\Symfony\Component\Finder\Finder;

return [

  'prefix' => 'OCA\\CAFEVDB\\Wrapped',
  'exclude-classes' => [
    'OC',
  ],
  'exclude-namespaces' => [
    // 'OC',
    //'OCA',
    //'OCP',
  ],
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
    // /** @var array<\Doctrine\ORM\Mapping\JoinColumn> */
    function(string $filePath, string $prefix, string $content): string {
      if (str_contains($filePath, 'doctrine/orm/src')) {
        $twoSlashPrefix = preg_quote(str_replace('\\', '\\\\', $prefix));
        $content = preg_replace(
          '%(?<!' . $twoSlashPrefix . '\\\\\\\\)(Doctrine\\\\\\\\)%',
          $twoSlashPrefix . '\\\\\\\\$1',
          $content,
        );
        $oneSlashPrefix = preg_quote($prefix);
        $content = preg_replace(
          '%(?<!' . $oneSlashPrefix . '\\\\)(Doctrine\\\\)([^\\\\])%',
          $oneSlashPrefix . '\\\\$1$2',
          $content,
        );
      }
      return $content;
    },
    // Gedmo behaviours
    function(string $filePath, string $prefix, string $content): string {
      if (str_contains($filePath, 'gedmo/doctrine-extensions/src')) {
        $search = [
          'repositoryClass="Gedmo\\',
          '* @see \\Gedmo',
          '* Gedmo\\',
          ' \\Gedmo\\',
          'throw new \\Gedmo\\',
          '`Gedmo\\',
          '`Doctrine\\',
        ];
        $replace = [
          'repositoryClass="Gedmo\\' . $prefix . '\\',
          '* @see \\' . $prefix . '\\Gedmo',
          '* ' . $prefix . '\\Gedmo\\',
          ' \\' . $prefix. '\\Gedmo\\',
          'throw new \\' . $prefix . '\\Gedmo\\',
          '`' . $prefix . '\\Gedmo\\',
          '`' . $prefix . '\\Doctrine\\',
        ];
        if (str_contains($filePath, 'gedmo/doctrine-extensions/src/DoctrineExtensions.php')) {
          $search[] = '$driverChain->addDriver($driver, \'Gedmo\')';
          $replace[] = '$driverChain->addDriver($driver, \''. $prefix . '\\Gedmo\')';
        }
        if (str_contains($filePath, 'gedmo/doctrine-extensions/src/Mapping/MappedEventSubscriber.php')) {
          $search[] = '$adapterClass = \'Gedmo';
          $replace[] = '$adapterClass = \'' .  $prefix . '\\Gedmo';
        }
        $content = str_replace($search, $replace, $content);
      }
      return $content;
    },
    // Horde
    function(string $filePath, string $prefix, string $content): string {
      if (str_contains($filePath, 'bytestream/horde')) {
        $lines = explode("\n", $content);
        foreach ($lines as &$line) {
          if (str_contains($line, 'class_alias')) {
            continue;
          }
          $line = str_replace('\'Horde_', '\'' . $prefix . '\\Horde_', $line);
        }
        return implode("\n", $lines);
      }
      return $content;
    },
    // Remove scoper namespace prefix from Symfony polyfills namespace
    function(string $filePath, string $prefix, string $content): string {
      if (!preg_match('{vendor-wrapped/symfony/polyfill[^/]*/bootstrap.php}i', $filePath)) {
        return $content;
      }
      return preg_replace('/namespace .+;/', '', $content);
    },
  ],
];
