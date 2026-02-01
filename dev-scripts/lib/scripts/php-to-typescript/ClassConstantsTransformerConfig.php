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

use Spatie\TypeScriptTransformer\TypeScriptTransformerConfig;

/**
 * Extend the default configuration class and add further options.
 */
class ClassConstantsTransformerConfig extends TypeScriptTransformerConfig
{
  private bool $constantsAsConstants = true;

  /**
   * Request to emit constants as literal type typed constants.
   *
   * @param bool $constantsAsConstants
   *
   * @return ConstantsTransformerConfig
   */
  public function constantsAsConstants(bool $constantsAsConstants = true): static
  {
    $this->constantsAsConstants = $constantsAsConstants;

    return $this;
  }

  /**
   * Request to emit constants as literal type typed interface properties.
   *
   * @param bool $constantsAsProperties
   *
   * @return ConstantsTransformerConfig
   */
  public function constantsAsProperties(bool $constantsAsProperties = true)
  {
    return $this->constantsAsConstants(!$constantsAsProperties);
  }

  /**
   * @return bool Whether constants should be emitted as literal type typed constants variables.
   */
  public function emitConstantsAsConstants(): bool
  {
    return $this->constantsAsConstants;
  }

  /**
   * @return bool Whether constants should be emitted as literal type typed interface properties.
   */
  public function emitConstantsAsProperties(): bool
  {
    return !$this->emitConstantsAsConstants();
  }
}
