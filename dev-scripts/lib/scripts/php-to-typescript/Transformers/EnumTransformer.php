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

namespace OCA\RotDrop\DevScripts\PhpToTypeScript\Transformers;

use OCA\RotDrop\DevScripts\PhpToTypeScript\Attributes\TypeScript;
use ReflectionClass;
use ReflectionEnum;
use Spatie\TypeScriptTransformer\Structures\TransformedType;
use Spatie\TypeScriptTransformer\Transformers\EnumTransformer as BaseTransformer;

/**
 * Allow to override the global "native enums" setting per enum by means of
 * the "nativeEnums" option of our TypeScript attribute.
 */
class EnumTransformer extends BaseTransformer
{
  /** {@inheritdoc} */
  public function transform(ReflectionClass $class, string $name): ?TransformedType
  {
    if (!$class->isEnum()) {
      return null;
    }

    $enum = new ReflectionEnum($class->getName());

    if (!$enum->isBacked()) {
      return null;
    }

    if (empty($enum->getCases())) {
      return null;
    }

    $shouldTransformToNativeEnums = $this->config->shouldTransformToNativeEnums();
    $tsAttributes = $class->getAttributes(TypeScript::class);
    if (!empty($tsAttributes)) {
      $tsAttribute = $tsAttributes[0]->newInstance();
      $override = $tsAttribute->options['nativeEnums'] ?? null;
      if ($override !== null) {
        $shouldTransformToNativeEnums = (bool)$override;
      }
    }

    return $shouldTransformToNativeEnums
      ? $this->toEnum($enum, $name)
      : $this->toType($enum, $name);
  }
}
