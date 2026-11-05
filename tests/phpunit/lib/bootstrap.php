<?php
/**
 * @license AGPL-3.0-or-later
 */

namespace OCA\RotDrop\Tests;

// Keep bootstrap variables out of the scope of the including file.
(static function(): void {
  if (!defined('PHPUNIT_NC_APP_NAMESPACE') || !is_string(\PHPUNIT_NC_APP_NAMESPACE) || \PHPUNIT_NC_APP_NAMESPACE === '') {
    throw new \LogicException('Define PHPUNIT_NC_APP_NAMESPACE before loading the shared PHPUnit helpers.');
  }

  $toolkitNamespace = \PHPUNIT_NC_APP_NAMESPACE . '\\Wrapped\\OCA\\RotDrop\\Toolkit';
  $aliases = [
    'DTO\\AbstractDTO',
    'Traits\\LoggerTrait',
    'Service\\ExecutableFinder',
  ];

  foreach ($aliases as $suffix) {
    $source = $toolkitNamespace . '\\' . $suffix;
    if (!class_exists($source) && !trait_exists($source)) {
      throw new \LogicException('Load the consuming app\'s scoped autoloader before loading the shared PHPUnit helpers: ' . $source . ' is unavailable.');
    }
  }

  foreach ($aliases as $suffix) {
    class_alias($toolkitNamespace . '\\' . $suffix, __NAMESPACE__ . '\\Toolkit\\' . $suffix);
  }

  // A PHP class alias alone would let DI construct a second finder instance.
  \OC::$server->registerAlias(
    Toolkit\Service\ExecutableFinder::class,
    $toolkitNamespace . '\\Service\\ExecutableFinder',
  );
})();
