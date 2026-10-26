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

use Spatie\TypeScriptTransformer\Attributes\TypeScript as BaseTypeScript;

/**
 * Like the upstream attribute, but with additional options, e.g.
 * ['nativeEnums' => true] to override the global enum setting.
 */
#[Attribute]
class TypeScript extends BaseTypeScript
{
  public ?array $options;

  /** {@inheritdoc} */
  public function __construct(?string $name = null, ?array $options = null)
  {
    parent::__construct($name);
    $this->options = $options;
  }
}
