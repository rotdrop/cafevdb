<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine <himself@claus-justus-heine.de>
 * @copyright 2026 Claus-Justus Heine <himself@claus-justus-heine.de>
 * @license AGPL-3.0-or-later
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 */

namespace OCA\CAFEVDB\Tests\Unit\Controller;

use ReflectionClass;

use OCA\CAFEVDB\Tests\MockProvider;

/**
 * Test the routes are defined. The trait needs the two constants
 * static::EXPECTED_ROUTES and
 * static::CONTROLLER_CLASS. static::EXPECTED_ROUTES may contains sub-array
 * 'ocs' and 'front' in order to allow to determine the prefix. If it is a
 * flat array 'front' is assume. Controller and app-name need not to be
 * prependet and are automatically added.
 */
trait TestRoutesAreDefinedTrait
{
  private MockProvider $mockProvider;

  /**
   * @param bool $verbose If \true print the routes names for convenience
   * during test implementation. Defaults to \false.
   *
   * @return void
   */
  public function testRoutesAreDefined(): void
  {
    $this->mockProvider = $this->mockProvider ?? MockProvider::create($this);

    $entityManager = $this->entityManager ?? null;
    if ($entityManager && method_exists($entityManager, 'expects')) {
      $this->entityManager->expects($this->never())->method('recryptEncryptedProperties');
    }
    $appName = $this->mockProvider->appName;
    $appRoutes = $this->mockProvider->getAppRoutes();
    $reflectionController = new ReflectionClass(static::CONTROLLER_CLASS);
    $controllerName = $reflectionController->getShortName();
    $controllerPrefix = strtolower(substr($controllerName, 0, -strlen('Controller')));
    $prefix = implode('.', [$appName, $controllerPrefix]);
    $ocsPrefix = implode('.', ['ocs', $prefix]);
    $controllerRoutes = array_filter(
      $appRoutes,
      fn(string $key) => str_starts_with($key, $prefix) || str_starts_with($key, $ocsPrefix),
      ARRAY_FILTER_USE_KEY,
    );
    // if ($verbose) {
    //   print_r(array_keys($controllerRoutes));
    // }
    $expectedRoutes = [];
    $prefixCallback = fn(string $name) => (str_starts_with($name, $appName)
                                           ? $name
                                           : implode('.', (str_starts_with($name, $controllerPrefix)
                                                           ? [$appName, $name]
                                                           : [$prefix, $name])));
    $ocsPrefixCallback = fn(string $name) => (str_starts_with($name, 'ocs')
                                              ? $name
                                              : implode('.', (str_starts_with($name, $controllerPrefix)
                                                              ? [implode('.', ['ocs', $appName]), $name]
                                                              : [$ocsPrefix, $name])));

    $expectedRoutes = array_merge(
      $expectedRoutes,
      array_map($ocsPrefixCallback, static::EXPECTED_ROUTES['ocs'] ?? []),
    );
    $expectedRoutes = array_merge(
      $expectedRoutes,
      array_map($ocsPrefixCallback, static::EXPECTED_ROUTES['front'] ?? []),
    );
    $expectedRoutes = array_merge(
      $expectedRoutes,
      array_map(
        $prefixCallback,
        array_filter(
          static::EXPECTED_ROUTES,
          fn(string $key) => $key != 'ocs' && $key != 'front',
          ARRAY_FILTER_USE_KEY,
        ),
      ),
    );
    $this->assertEqualsCanonicalizing($expectedRoutes, array_keys($controllerRoutes));
    /** @var \Symfony\Component\Routing\Route $route */
    foreach ($controllerRoutes as $route) {
      $defaults = $route->getDefaults();
      $caller = $defaults['caller'];
      $this->assertEquals($appName, $caller[0]);
      $this->assertEquals($controllerName, $caller[1]);
      if (!method_exists(static::CONTROLLER_CLASS, $caller[2])) {
        print_r($caller);
      }
      $this->assertTrue(method_exists(static::CONTROLLER_CLASS, $caller[2]));
    }
  }
}
