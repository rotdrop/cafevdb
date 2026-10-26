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

use Attribute;

use phpDocumentor\Reflection\Type;
use Spatie\TypeScriptTransformer\Attributes\TypeScriptTransformableAttribute;
use Spatie\TypeScriptTransformer\Types\StructType;
use Spatie\TypeScriptTransformer\Types\TypeScriptType;

/**
 * Like the upstream attribute, but converts PHP namespace separators to dots.
 */
#[Attribute]
class LiteralTypeScriptType implements TypeScriptTransformableAttribute
{
  /** {@inheritdoc} */
  public function __construct(
    private string|array $typeScript,
  ) {
  }

  /** {@inheritdoc} */
  public function getType(): Type
  {
    if (is_string($this->typeScript)) {
      return new TypeScriptType(str_replace('\\', '.', $this->typeScript));
    }

    $types = array_map(
      fn (string $type) => new TypeScriptType(str_replace('\\', '.', $type)),
      $this->typeScript
    );

    return new StructType($types);
  }
}
