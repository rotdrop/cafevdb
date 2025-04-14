<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine <himself@claus-justus-heine.de>
 * @copyright 2022, 2024, Claus-Justus Heine <himself@claus-justus-heine.de>
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

namespace OCA\CAFEVDB\Storage\Database;

use OCA\CAFEVDB\Service\ConfigService;
use OCA\CAFEVDB\Database\Doctrine\ORM\Entities;

/**
 * Utility class in order to hide the fancy CTOR pattern of the NC storage
 * classes.
 */
class Factory
{
  use \OCA\CAFEVDB\Traits\ConfigTrait;

  /** @var array */
  protected $projectStorages = [];

  /** @var array */
  protected $participantStorages = [];

  /**
   * @param ConfigService $configService
   */
  public function __construct(
    protected ConfigService $configService,
  ) {
  }

  /** @return Storage */
  public function getBaseStorage():Storage
  {
    return $this->di(Storage::class);
  }

  /** @return BankTransactionsStorage */
  public function getBankTransactionsStorage():BankTransactionsStorage
  {
    return $this->di(BankTransactionsStorage::class);
  }

  /** @return TaxExemptionNoticesStorage */
  public function getTaxExemptionNoticesStorage():TaxExemptionNoticesStorage
  {
    return $this->di(TaxExemptionNoticesStorage::class);
  }

  /** @return TaxExemptionNoticesStorage */
  public function getDonationReceiptsStorage():DonationReceiptsStorage
  {
    return $this->di(DonationReceiptsStorage::class);
  }

  /**
   * @param Entities\Project $project
   *
   * @return ProjectBalanceSupportingDocumentsStorage
   */
  public function getProjectBalanceSupportingDocumentsStorage(Entities\Project $project):ProjectBalanceSupportingDocumentsStorage
  {
    $projectId = $project->getId();
    if (empty($this->projectStorages[$projectId])) {
      $this->projectStorages[$projectId] = new ProjectBalanceSupportingDocumentsStorage([
        'configService' => $this->configService,
        'project' => $project,
      ]);
    }
    return $this->projectStorages[$projectId];
  }

  /**
   * @param Entities\ProjectParticipant $participant
   *
   * @return ProjectParticipantsStorage
   */
  public function getProjectParticipantsStorage(Entities\ProjectParticipant $participant):ProjectParticipantsStorage
  {
    $participantId = $participant->getMusician()->getId() . ' ' . $participant->getProject()->getId();
    if (empty($this->participantStorages[$participantId])) {
      $this->participantStorages[$participantId] = new ProjectParticipantsStorage([
        'configService' => $this->configService,
        'participant' => $participant,
      ]);
    }
    return $this->participantStorages[$participantId];
  }
}
