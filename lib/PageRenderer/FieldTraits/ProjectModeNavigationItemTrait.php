<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine <himself@claus-justus-heine.de>
 * @copyright 2025 Claus-Justus Heine
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

namespace OCA\CAFEVDB\PageRenderer\FieldTraits;

use OCA\CAFEVDB\Service\ConfigService;
use OCA\CAFEVDB\PageRenderer;

/**
 * Provide a navigationItem() method for page-renderers which need a current
 * project.
 */
trait ProjectModeNavigationItemTrait
{
  use \OCA\CAFEVDB\Traits\ConfigTrait;

  /*** {@inheritdoc} */
  public static function navigationItem(?int $projectId = null, ?string $projectName = null):array
  {
    return array_merge(
      parent::navigationItem($projectId, $projectName), [
        'templateParameters' => [ 'projectId' => $projectId, 'projectName' =>  $projectName ],
      ]);
  }

  /** {@inheritdoc} */
  public function navigationItems():array
  {
    $items = ($this->projectId > 0)
      ? array_merge(
        [
          PageRenderer\ProjectParticipants::navigationItem($this->projectId),
          PageRenderer\ProjectParticipantFields::navigationItem($this->projectId),
          PageRenderer\ProjectInstrumentationNumbers::navigationItem($this->projectId),
          PageRenderer\ProjectPayments::navigationItem($this->projectId),
          PageRenderer\SepaBankAccounts::navigationItem($this->projectId),
          PageRenderer\SepaBulkTransactions::navigationItem($this->projectId),
        ],
        ($this->projectId == $this->getConfigValue(ConfigService::CLUB_MEMBER_PROJECT_ID_KEY, 0)
         ? [
           PageRenderer\InstrumentInsurances::navigationItem(),
           PageRenderer\InsuranceRates::navigationItem(),
           PageRenderer\InsuranceBrokers::navigationItem(),
         ]
         : []),
        ($this->projectId == $this->getConfigValue(ConfigService::EXECUTIVE_BOARD_PROJECT_ID_KEY, 0)
         ? [
           PageRenderer\TaxExemptionNotices::navigationItem(),
           PageRenderer\DonationReceipts::navigationItem($this->projectId)
         ]
         : []),
        [
          PageRenderer\Projects::navigationItem(),
          PageRenderer\AllMusicians::navigationItem(),
          PageRenderer\Instruments::navigationItem(),
        ],
      )
      : [
        PageRenderer\Projects::navigationItem(),
        PageRenderer\AllMusicians::navigationItem(),
        PageRenderer\Instruments::navigationItem(),
        InstrumentFamilies::navigationItem(),
        PageRenderer\ProjectParticipantFields::navigationItem(),
        PageRenderer\ProjectInstrumentationNumbers::navigationItem(),
      ];
    return array_values(array_filter($items, fn($item) => $item['template'] != self::TEMPLATE));
  }
}
