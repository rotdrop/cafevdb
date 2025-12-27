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

use OCA\CAFEVDB\Database\Doctrine\DBAL\Types\EnumProjectTemporalType as ProjectType;

/**
 * DTO for a debit mandate, only the bare minimum of data.
 */
class SepaDebitMandateValidation extends \OCA\CAFEVDB\Toolkit\DTO\AbstractResponseDTO
{
  /** {@inheritdoc} */
  public function __construct(
    /** @var string[] */
    public readonly array $messages,
    /** @var string[] */
    public readonly ?array $suggestions,
    public readonly ?int $mandateProjectId,
    public readonly ?string $reference,
    public readonly ?string $iban,
    public readonly ?int $blz,
    public readonly ?string $bic,
    public readonly ?string $owner,
    /** @var array<string, string> */
    public readonly ?array $feedback,
    public readonly ?bool $mandateNonRecurring,
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
    return new self(
      $messages,
      $suggestions ?? null,
      $mandateProjectId ?? null,
      $reference ?? null,
      $iban ?? null,
      $blz ?? null,
      $bic ?? null,
      $owner ?? null,
      $feedback ?? null,
      $mandateNonRecurring ?? null,
    );
  }
}
