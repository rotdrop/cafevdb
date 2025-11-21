<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine <himself@claus-justus-heine.de>
 * @copyright 2020, 2021, 2022, 2023, 2024, 2025 Claus-Justus Heine
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

namespace OCA\CAFEVDB\DevScripts\PhpToTypeScript;

use ReflectionClass;
use ReflectionProperty;

use Spatie\TypeScriptTransformer\Transformers\DtoTransformer;

/** Transform database entities, including and in particular their private and
 *  protected properties.
 */
class DatabaseEntityTransformer extends DtoTransformer
{
  protected function resolveProperties(ReflectionClass $class): array
  {
    $foo = false;
    $visibility = ReflectionProperty::IS_PUBLIC
      |ReflectionProperty::IS_PROTECTED
      |ReflectionProperty::IS_PRIVATE;
    $properties = array_filter(
      $class->getProperties($visibility),
      fn (ReflectionProperty $property) => ! $property->isStatic()
    );

    return array_values($properties);
  }
}
