<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine <himself@claus-justus-heine.de>
 * @copyright 2022-2025 Claus-Justus Heine
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

namespace OCA\CAFEVDB\Controller\DTO;

use Spatie\TypeScriptTransformer\Attributes as TSAttributes;

/**
 * Intial state emitted for key PHPMyEdit. Consistency between class members
 * and config / enum keys is ensured by unit testing with the fromArray
 * method.
 */
class PMEInitialState extends \OCA\CAFEVDB\Toolkit\DTO\AbstractDTO
{
  /** {@inheritdoc} */
  public function __construct(
    public readonly bool $directChange,
    public readonly bool $showDisabled,
    public readonly bool $deselectInvisibleMiscRecs,
    public readonly bool $initialFilterVisibility,
    public readonly int $pageRowsDefault,
    public readonly bool $selectChosen,
    public readonly string $filterSelectPlaceholder,
    public readonly string $filterSelectNoResult,
    public readonly string $filterSelectChosenTitle,
    public readonly string $inputSelectPlaceholder,
    public readonly string $inputSelectNoResult,
    public readonly string $inputSelectChosenTitle,
    #[TSAttributes\LiteralTypeScriptType('typeof PageRenderer.DataConstants.PAGE_RENDERER')]
    public readonly array $pageRenderer,
  ) {
  }

  /**
   * Initialize from the given array.
   *
   * @param array $data
   *
   * @return self
   */
  public static function fromArray(array $data): self
  {
    static::initKeys();
    extract(array_intersect_key($data, array_flip(static::$keys[__CLASS__])));
    return new self(
      deselectInvisibleMiscRecs: $deselectInvisibleMiscRecs,
      directChange: $directChange,
      filterSelectChosenTitle: $filterSelectChosenTitle,
      filterSelectNoResult: $filterSelectNoResult,
      filterSelectPlaceholder: $filterSelectPlaceholder,
      initialFilterVisibility: $initialFilterVisibility,
      inputSelectChosenTitle: $inputSelectChosenTitle,
      inputSelectNoResult: $inputSelectNoResult,
      inputSelectPlaceholder: $inputSelectPlaceholder,
      pageRenderer: $pageRenderer,
      pageRowsDefault: $pageRowsDefault,
      selectChosen: $selectChosen,
      showDisabled: $showDisabled,
    );
  }
}
