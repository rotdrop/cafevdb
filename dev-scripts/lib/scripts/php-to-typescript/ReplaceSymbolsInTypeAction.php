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

use Spatie\TypeScriptTransformer\Actions\ReplaceSymbolsInTypeAction as BaseAction;
use Spatie\TypeScriptTransformer\Structures\TransformedType;

/**
 * Differences to upstream: inline types have an empty name and must not
 * trigger the circular dependency check, unresolved symbols are replaced by
 * "unknown" instead of "any".
 */
class ReplaceSymbolsInTypeAction extends BaseAction
{
  /** {@inheritdoc} */
  public function execute(TransformedType $type, array $chain = []): string
  {
    // Upstream throws a false-positive circular dependency for inline types
    // (empty names), so remove the empty names from the chain.
    return parent::execute($type, array_values(array_filter($chain, fn(string $name) => $name !== '')));
  }

  /** {@inheritdoc} */
  protected function replaceSymbol(string $missingSymbol, TransformedType $type, array $chain): TransformedType
  {
    if ($this->collection[$missingSymbol] === null) {
      $type->replaceSymbol($missingSymbol, 'unknown');

      return $type;
    }

    return parent::replaceSymbol($missingSymbol, $type, $chain);
  }
}
