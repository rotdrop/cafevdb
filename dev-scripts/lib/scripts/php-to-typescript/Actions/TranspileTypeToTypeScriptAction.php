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

use phpDocumentor\Reflection\Type;
use phpDocumentor\Reflection\Types\Mixed_;
use phpDocumentor\Reflection\Types\Resource_;
use phpDocumentor\Reflection\Types\Self_;
use phpDocumentor\Reflection\Types\Static_;
use phpDocumentor\Reflection\Types\This;
use Spatie\TypeScriptTransformer\Actions\TranspileTypeToTypeScriptAction as BaseAction;
use Spatie\TypeScriptTransformer\Structures\MissingSymbolsCollection;

/**
 * Emit "unknown" instead of "any".
 */
class TranspileTypeToTypeScriptAction extends BaseAction
{
  /** {@inheritdoc} */
  public function __construct(
    MissingSymbolsCollection $missingSymbolsCollection,
    bool $nullablesAreOptional = false,
    protected ?string $selfClass = null,
  ) {
    parent::__construct($missingSymbolsCollection, $nullablesAreOptional, $selfClass);
  }

  /**
   * {@inheritdoc}
   *
   * The nested calls of the parent class are dispatched to this method, too.
   */
  public function execute(Type $type): string
  {
    if ($type instanceof Mixed_ || $type instanceof Resource_) {
      return 'unknown';
    }
    if ($this->selfClass === null && ($type instanceof Self_ || $type instanceof Static_ || $type instanceof This)) {
      return 'unknown';
    }
    return parent::execute($type);
  }
}
