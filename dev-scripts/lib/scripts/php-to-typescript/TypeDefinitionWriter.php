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

namespace OCA\RotDrop\DevScripts\PhpToTypeScript;

use Spatie\TypeScriptTransformer\Structures\TransformedType;
use Spatie\TypeScriptTransformer\Structures\TypesCollection;
use Spatie\TypeScriptTransformer\Writers\TypeDefinitionWriter as BaseWriter;

/**
 * Differences to upstream: two-space indentation, namespaces are taken from
 * TransformedType::getNamespaceSegments().
 */
class TypeDefinitionWriter extends BaseWriter
{
  /** {@inheritdoc} */
  public function format(TypesCollection $collection): string
  {
    (new ReplaceSymbolsInCollectionAction())->execute($collection);

    [$namespaces, $rootTypes] = $this->groupByNamespace($collection);

    $output = '';

    foreach ($namespaces as $namespace => $types) {
      asort($types);

      $output .= "declare namespace {$namespace} {" . PHP_EOL;

      $output .= join(PHP_EOL, array_map(
        fn (TransformedType $type) => "  export {$type->toString()}",
        $types
      ));

      $output .= PHP_EOL . "}" . PHP_EOL;
    }

    $output .= join(PHP_EOL, array_map(
      fn (TransformedType $type) => "export {$type->toString()}",
      $rootTypes
    ));

    return $output;
  }

  /** {@inheritdoc} */
  protected function groupByNamespace(TypesCollection $collection): array
  {
    $namespaces = [];
    $rootTypes = [];

    foreach ($collection as $type) {
      if ($type->isInline) {
        continue;
      }

      $namespace = implode('.', $type->getNamespaceSegments());

      if (empty($namespace)) {
        $rootTypes[] = $type;

        continue;
      }

      $namespaces[$namespace][] = $type;
    }

    ksort($namespaces);

    return [$namespaces, $rootTypes];
  }
}
