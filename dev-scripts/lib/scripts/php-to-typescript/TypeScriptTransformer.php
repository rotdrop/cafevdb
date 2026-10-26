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

use Spatie\TypeScriptTransformer\Actions\FormatTypeScriptAction;
use Spatie\TypeScriptTransformer\Structures\TypesCollection;
use Spatie\TypeScriptTransformer\TypeScriptTransformer as BaseTransformer;
use Spatie\TypeScriptTransformer\TypeScriptTransformerConfig;
use Symfony\Component\Finder\Finder;

/**
 * Run the pipeline with our own actions.
 */
class TypeScriptTransformer extends BaseTransformer
{
  /** {@inheritdoc} */
  public static function create(TypeScriptTransformerConfig $config): static
  {
    return new static($config);
  }

  /** {@inheritdoc} */
  public function transform(): TypesCollection
  {
    $typesCollection = (new ResolveTypesCollectionAction(
      new Finder(),
      $this->config,
    ))->execute();

    (new PersistTypesCollectionAction($this->config))->execute($typesCollection);

    (new FormatTypeScriptAction($this->config))->execute();

    return $typesCollection;
  }
}
