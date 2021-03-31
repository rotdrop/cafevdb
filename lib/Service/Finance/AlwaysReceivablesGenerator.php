<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine
 * @copyright 2011-2016, 2020, 2021 Claus-Justus Heine <himself@claus-justus-heine.de>
 *
 * This library is free software; you can redistribute it and/or
 * modify it under the terms of the GNU GENERAL PUBLIC LICENSE
 * License as published by the Free Software Foundation; either
 * version 3 of the License, or any later version.
 *
 * This library is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU AFFERO GENERAL PUBLIC LICENSE for more details.
 *
 * You should have received a copy of the GNU Lesser General Public
 * License along with this library.  If not, see <http://www.gnu.org/licenses/>.
 */

namespace OCA\CAFEVDB\Service\Finance;

use Ramsey\Uuid\Uuid;

use OCA\CAFEVDB\Database\EntityManager;
use Doctrine\Common\Collections\Collection;
use OCA\CAFEVDB\Database\Doctrine\ORM\Entities;

/**
 * Always generate a new payment request.
 */
class AlwaysReceivablesGenerator extends AbstractReceivablesGenerator
{
  /** @var float */
  protected $amount;

  public function __construct(EntityManager $entityManager) {
    parent::__construct($entityManager);
    $this->amount = 1.0;
  }

  /**
   * {@inheritdoc}
   */
  public function generateReceivables():Collection
  {
    $count = count($this->serviceFeeField->getDataOptions());
    $receivable = (new Entities\ProjectExtraFieldDataOption)
                ->setField($this->serviceFeeField)
                ->setKey(Uuid::uuid1())
                ->setLabel($this->l->t('Option %d', $count+1))
                ->setData($this->amount);
    $this->serviceFeeField->getDataOptions()->add($receivable);
    return $this->serviceFeeField->getDataOptions();
  }

  /**
   * {@inheritdoc}
   */
  public function updateReceivable(Entities\ProjectExtraFieldDataOption $receivable, ?Entities\ProjectParticipant $participant = null):Entities\ProjectExtraFieldDataOption
  {
    // - fetch all project participants
    // - enter the payment option into the data table

    $participants = $receivable->getField()->getProject()->getParticipants();

    /** @var Entities\ProjectParticipant $participant */
    foreach ($participants as $participant) {
      $this->updateParticipant($receivable, $participant);
    }
    return $receivable;
  }

  private function updateParticipant(Entities\ProjectExtraFieldDataOption $receivable, Entities\ProjectParticipant $participant)
  {
    $extraFieldsData = $participant->getExtraFieldsData();
    if (empty($extraFieldsData->matching(self::criteriaWhere(['key' => $receivable->getKey()])))) {
      $datum = (new Entities\ProjectExtraFieldDatum)
             ->setField($receivable->getField())
             ->setMusician($participant->getMusician())
             ->setOptionKey($receivable->getKey())
             ->setOptionValue($receivable->getData());
      $extraFieldsData->add($datum);
    }
  }
}
