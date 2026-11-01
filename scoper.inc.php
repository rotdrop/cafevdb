<?php

declare(strict_types=1);

use Isolated\Symfony\Component\Finder\Finder;

return [

  'prefix' => 'OCA\\CAFEVDB\\Wrapped',
  'exclude-classes' => [
    'OC',
    'SortDirection',
    // aliased as a namespace, e.g. "use Symfony\\Component\\Console;"
    'Symfony\\Component\\Console',
  ],
  'expose-global-classes' => false,
  'exclude-namespaces' => [
    '/^PHPUnit/',
    'Psr\\Clock',
    'Psr\\Log',
    'Symfony\\Component\\Console',
    '/^Symfony\\\\Component\\\\(Console|Process)(\\\\|$)/',
    '/^Psr\\\\Container(\\\\|$)/',
    '/^GuzzleHttp(\\\\|$)/',
    // Needed by the nextcloud-app-toolkit, provided by the cloud or the app itself
    '/^(OC|OCP|NCU)(\\\\|$)/',
    '/^OCA\\\\(GroupFolders|RotDrop\\\\DevScripts)(\\\\|$)/',
    '/^MathPHP(\\\\|$)/',
    '/^Spatie\\\\TypeScriptTransformer(\\\\|$)/',
    // 'OC',
    //'OCA',
    //'OCP',
  ],
  'exclude-files' => [
    './symfony/polyfill-php86/Resources/stubs/SortDirection.php',
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
    static function(string $filePath, string $prefix, string $content):string {
      if (str_ends_with($filePath, '/doctrine/orm/src/Mapping/Driver/AttributeDriver.php')) {
        return str_replace(
          "'Doctrine\\ORM\\Mapping\\ClassMetadata::",
          "ClassMetadata::class . '::",
          $content,
        );
      }

      if (str_ends_with($filePath, '/doctrine/orm/src/Query/Lexer.php')) {
        return str_replace(
          "'Doctrine\\ORM\\Query\\TokenType::",
          "TokenType::class . '::",
          $content,
        );
      }

      return $content;
    },
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
    // Symfony ProxyHelper, literal \\Symfony\\Component\\VarExporter in HERE doc
    function(string $filePath, string $prefix, string $content): string {
      if (str_ends_with($filePath, 'symfony/var-exporter/ProxyHelper.php')) {
        $content = str_replace(
          '\\\\Symfony\\\\Component\\\\VarExporter',
          '\\\\' . str_replace('\\', '\\\\', $prefix) . '\\\\Symfony\\\\Component\\\\VarExporter',
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
    // Doctrine Migrations Generator
    function(string $filePath, string $prefix, string $content): string {
      if (!str_ends_with($filePath, 'vendor-wrapped/doctrine/migrations/src/Generator/Generator.php')) {
        return $content;
      }
      return str_replace('use Doctrine', 'use OCA\\CAFEVDB\\Wrapped\\Doctrine', $content);
    },
  ],
];
