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
 * DTO for receivables statistics during recomputation.
 */
class ReceivablesStatistics extends \OCA\CAFEVDB\Toolkit\DTO\AbstractResponseDTO
{
  /** {@inheritdoc} */
  public function __construct(
    /** @var string[] */
    public readonly array $messages,
    /**
     * @var array<int, float>
     *
     * Amounts keyed by musician ids.
     */
    #[TSAttributes\LiteralTypeScriptType('{ [musicianId: number]: number }')]
    public readonly array $amounts,
    public readonly int $added,
    public readonly int $removed,
    public readonly int $changed,
    public readonly int $skipped,
    /**
     * @var array<int, string>
     *
     * Musician name keyed by musician id.
     */
    #[TSAttributes\LiteralTypeScriptType('{ [musicianId: number]: string }')]
    public readonly array $musicians,
    /**
     * @var array<string, string>
     *
     * Translated receivable label keyed by its key (UUID).
     */
    public readonly array $receivables,
  ) {
  }

  /**
   * Create from array.
   *
   * @param array $data
   *
   * @return self
   *
   * @throws UnexpectedValueException
   *
   * @SuppressWarnings(PHPMD.UndefinedVariable)
   * @SuppressWarnings(PHPMD.UnusedLocalVariable)
   */
  public static function fromArray(array $data): self
  {
    static::initKeys();
    extract(array_intersect_key($data, array_flip(static::$keys[__CLASS__])));
    return new self(
      $messages,
      $amounts,
      $added,
      $removed,
      $changed,
      $skipped,
      $musicians,
      $receivables,
    );
  }
}
