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

namespace OCA\RotDrop\DevScripts\PhpToTypeScript\Structures;

use ReflectionClass;
use Spatie\TypeScriptTransformer\Structures\MissingSymbolsCollection;
use Spatie\TypeScriptTransformer\Structures\TransformedType;
use Spatie\TypeScriptTransformer\Structures\TypesCollection;

/**
 * Vanilla spatie/typescript-transformer v2.5 transformers and collectors can
 * only return a single TransformedType. This wrapper allows to return a whole
 * collection; it is unwrapped by ResolveTypesCollectionAction.
 */
class TransformedTypeSet extends TransformedType
{
  public function __construct(
    ReflectionClass $class,
    public TypesCollection $types = new TypesCollection(),
  ) {
    parent::__construct($class, null, '', new MissingSymbolsCollection(), false);
  }

  /** @return TypesCollection */
  public function getTypes(): TypesCollection
  {
    return $this->types;
  }
}
