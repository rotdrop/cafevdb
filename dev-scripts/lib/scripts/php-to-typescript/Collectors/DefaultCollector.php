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

namespace OCA\RotDrop\DevScripts\PhpToTypeScript\Collectors;

use OCA\RotDrop\DevScripts\PhpToTypeScript\Actions\TranspileTypeToTypeScriptAction;
use ReflectionClass;
use Spatie\TypeScriptTransformer\Collectors\DefaultCollector as BaseCollector;
use Spatie\TypeScriptTransformer\Structures\MissingSymbolsCollection;
use Spatie\TypeScriptTransformer\Structures\TransformedType;
use Spatie\TypeScriptTransformer\TypeReflectors\ClassTypeReflector;

/**
 * Add the "shouldCollect()" hook which sub-classes can override in order to
 * decide whether a class is to be collected. The upstream class hard-codes
 * this to ClassTypeReflector::isTransformable().
 */
class DefaultCollector extends BaseCollector
{
  /** {@inheritdoc} */
  public function getTransformedType(ReflectionClass $class): ?TransformedType
  {
    $reflector = ClassTypeReflector::create($class);

    if (!$this->shouldCollect($reflector)) {
      return null;
    }

    $transformedType = $reflector->getType()
      ? $this->resolveAlreadyTransformedType($reflector)
      : $this->resolveTypeViaTransformer($reflector);

    if ($transformedType !== null && $reflector->isInline()) {
      $transformedType->name = null;
      $transformedType->isInline = true;
    }

    return $transformedType;
  }

  /**
   * @param ClassTypeReflector $reflector
   *
   * @return bool
   */
  protected function shouldCollect(ClassTypeReflector $reflector): bool
  {
    return $reflector->isTransformable();
  }

  /** {@inheritdoc} */
  protected function resolveAlreadyTransformedType(ClassTypeReflector $reflector): TransformedType
  {
    $missingSymbols = new MissingSymbolsCollection();
    $name = $reflector->getName();
    $nullablesAreOptional = $this->config->shouldConsiderNullAsOptional();

    $transpiler = new TranspileTypeToTypeScriptAction(
      $missingSymbols,
      $nullablesAreOptional,
      $name
    );

    return TransformedType::create(
      $reflector->getReflectionClass(),
      $reflector->getName(),
      $transpiler->execute($reflector->getType()),
      $missingSymbols
    );
  }
}
