<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine <himself@claus-justus-heine.de>
 * @copyright 2026 Claus-Justus Heine
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

use OCA\CAFEVDB\Database\Doctrine\DBAL\Types\EnumProjectTemporalType as ProjectType;
use OCA\CAFEVDB\Controller\EnumSepaDebitMandateRevocationStatus;

/**
 * DTO for a debit mandate, only the bare minimum of data.
 */
class SepaBankAccount extends \OCA\CAFEVDB\Toolkit\DTO\AbstractResponseDTO
{
  /** {@inheritdoc} */
  public function __construct(
    public readonly int $musicianId,
    public readonly int $bankAccountSequence,
    public readonly bool $bankAccountDeleted,
    /** @var string[] */
    public readonly ?array $messages = null,
    /**
     * Need not be present, if we have a submitted project-id from the
     * context, then it will be echoed back.
     */
    public readonly int $projectId = 0,
    public readonly ?EnumSepaDebitMandateRevocationStatus $state = null,
  ) {
  }

  /**
   * @param array $data
   *
   * @return self
   *
   * @SuppressWarnings(PHPMD.UndefinedVariable)
   * @SuppressWarnings(PHPMD.UnusedLocalVariable)
   */
  public static function fromArray(array $data): self
  {
    static::initKeys();
    extract($data);
    try {
      if ($state ?? null) {
        $state = EnumSepaDebitMandateRevocationStatus::get($state);
      }
    } catch (InvalidArgumentException $e) {
      throw $e;
    }
    return new self(
      projectId: $projectId ?? 0,
      musicianId: $musicianId,
      bankAccountSequence: $bankAccountSequence,
      bankAccountDeleted: $bankAccountDeleted,
      messages: $messages ?? null,
      state: $state ?? null,
    );
  }
}
