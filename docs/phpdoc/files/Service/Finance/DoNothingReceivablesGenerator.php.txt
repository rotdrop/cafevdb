<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine <himself@claus-justus-heine.de>
 * @copyright 2011-2016, 2020, 2021, 2022 Claus-Justus Heine
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

namespace OCA\CAFEVDB\Service\Finance;

use OCA\CAFEVDB\Database\EntityManager;
use OCA\CAFEVDB\Wrapped\Doctrine\Common\Collections\Collection;
use OCA\CAFEVDB\Database\Doctrine\ORM\Entities;
use OCA\CAFEVDB\Service\ProgressStatusService;

/**
 * Do nothing implementation to have something implementing
 * the interface. Would rather belong to a test-suite.
 */
class DoNothingReceivablesGenerator extends AbstractReceivablesGenerator
{
  // phpcs:disable Squiz.Commenting.FunctionComment.Missing
  public function __construct(
    EntityManager $entityManager,
    ProgressStatusService $progressStatusService,
  ) {
    parent::__construct($entityManager, $progressStatusService);
  }
  // phpcs:enable

  /** {@inheritdoc} */
  public static function slug():string
  {
    return self::t('nothing');
  }

  /** {@inheritdoc} */
  public function generateReceivables():Collection
  {
    // This is the dummy implementation, just do nothing.
    return $this->serviceFeeField->getSelectableOptions();
  }

  /** {@inheritdoc} */
  protected function updateOne(
    Entities\ProjectParticipantFieldDataOption $receivable,
    Entities\ProjectParticipant $participant,
    string $updateStrategy = self::UPDATE_STRATEGY_EXCEPTION,
  ):array {
    // Do nothing
    return [ 'added' => 0, 'removed' => 0, 'changed' => 0 ];
  }
}
