<?php
/**
 * Some PHP utility functions for Nextcloud apps.
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

namespace OCA\RotDrop\DevScripts\PhpToTypeScript\Actions;

use OCA\RotDrop\DevScripts\PhpToTypeScript\Structures\TransformedTypeSet;
use OCA\RotDrop\DevScripts\PhpToTypeScript\TransformerConfig;
use Exception;
use Generator;
use ReflectionClass;
use RuntimeException;
use Spatie\TypeScriptTransformer\Actions\ResolveTypesCollectionAction as BaseAction;
use Spatie\TypeScriptTransformer\Exceptions\NoAutoDiscoverTypesPathsDefined;
use Spatie\TypeScriptTransformer\Structures\TransformedType;
use Spatie\TypeScriptTransformer\Structures\TypesCollection;
use Symfony\Component\Finder\Finder;

/**
 * Extend the default action by
 *
 * - pre-populating the collection with the default type replacements
 * - support for exclude paths and regular expressions
 * - support for transformers returning multiple types
 * - propagating errors instead of silently ignoring them
 */
class ResolveTypesCollectionAction extends BaseAction
{
  /** {@inheritdoc} */
  public function __construct(Finder $finder, TransformerConfig $config)
  {
    parent::__construct($finder, $config);
  }

  /** {@inheritdoc} */
  public function execute(): TypesCollection
  {
    $collection = new TypesCollection();

    $paths = $this->config->getAutoDiscoverTypesPaths();

    if (empty($paths)) {
      throw NoAutoDiscoverTypesPathsDefined::create();
    }

    foreach ($this->config->getDefaultTypeReplacements() as $key => $type) {
      $class = new ReflectionClass($key);

      $collection[$key] = TransformedType::create(
        $class,
        $class->getShortName(),
        (string)$type,
      );
    }
    foreach ($this->config->getDefaultInlineTypeReplacements() as $key => $type) {
      $class = new ReflectionClass($key);

      $collection[$key] = TransformedType::create(
        $class,
        $class->getShortName(),
        (string)$type,
        inline: true,
      );
    }

    foreach ($this->resolveIterator($paths) as $class) {
      $transformedType = $this->resolveTransformedType($class);

      if ($transformedType === null) {
        continue;
      }

      if ($transformedType instanceof TransformedTypeSet) {
        foreach ($transformedType->getTypes() as $key => $type) {
          $collection[$key] = $type;
        }
      } else {
        $collection[] = $transformedType;
      }
    }

    return $collection;
  }

  /** {@inheritdoc} */
  protected function resolveIterator(array $paths): Generator
  {
    $paths = array_map(
      fn (string $path) => is_dir($path) ? $path : dirname($path),
      $paths
    );

    $exclude = $this->config->getAutoDiscoverExcludePaths();
    $excludeRegExp = $this->config->getAutoDiscoverExcludeRegExp();

    foreach ($this->finder->files()->in($paths) as $fileInfo) {
      $path = $fileInfo->getPathname();
      foreach ($exclude as $dir) {
        if (str_starts_with($path, $dir)) {
          continue 2;
        }
      }
      if (!empty($excludeRegExp) && preg_match($excludeRegExp, $path)) {
        continue;
      }
      try {
        $classes = (new ResolveClassesInPhpFileAction())->execute($fileInfo);

        foreach ($classes as $name) {
          yield $name => new ReflectionClass($name);
        }
      } catch (Exception $exception) {
        throw new RuntimeException('Error while processing "' . $path . '": ' . $exception->getMessage(), $exception->getCode(), $exception);
      }
    }
  }
}
