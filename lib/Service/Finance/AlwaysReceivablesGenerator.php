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

use OCP\ILogger;
use OCP\IL10N;

use OCA\CAFEVDB\Database\EntityManager;
use Doctrine\Common\Collections\Collection;
use OCA\CAFEVDB\Database\Doctrine\ORM\Entities;
use OCA\CAFEVDB\Common\Uuid;

/**
 * Always generate a new payment request. This is just a dummy
 * nonsense proof-of-concept implementation. It will always generate
 * new receivables, and calling updateReceivable() will also always
 * just change the amount to pay.
 */
class AlwaysReceivablesGenerator extends AbstractReceivablesGenerator
{
  use \OCA\CAFEVDB\Traits\LoggerTrait;

  /** @var float */
  protected $amount;

  public function __construct(
    EntityManager $entityManager
    , ILogger $logger
    , IL10N $l10n
  ) {
    parent::__construct($entityManager);
    $this->logger = $logger;
    $this->l = $l10n;

    $this->amount = 1.0;
  }

  /**
   * {@inheritdoc}
   */
  public function generateReceivables():Collection
  {
    $count = count($this->serviceFeeField->getDataOptions());
    $receivable = (new Entities\ProjectParticipantFieldDataOption)
                ->setField($this->serviceFeeField)
                ->setKey(Uuid::create())
                ->setLabel($this->l->t('Option %d', $count))
                ->setData($this->amount);
    $this->serviceFeeField->getDataOptions()->set($receivable->getKey()->getBytes(), $receivable);
    return $this->serviceFeeField->getDataOptions()->filter(function(Entities\ProjectParticipantFieldDataOption $receivable) {
      return (string)$receivable->getKey() != Uuid::NIL;
    });
  }

  /**
   * {@inheritdoc}
   */
  public function updateReceivable(Entities\ProjectParticipantFieldDataOption $receivable, ?Entities\ProjectParticipant $participant = null):Entities\ProjectParticipantFieldDataOption
  {
    if (!empty($participant)) {
      $this->updateParticipant($receivable, $participant);
    } else {
      $participants = $receivable->getField()->getProject()->getParticipants();
      /** @var Entities\ProjectParticipant $participant */
      foreach ($participants as $participant) {
        $this->updateParticipant($receivable, $participant);
      }
    }

    return $receivable;
  }

  private function updateParticipant(Entities\ProjectParticipantFieldDataOption $receivable, Entities\ProjectParticipant $participant)
  {
    $participantFieldsData = $participant->getParticipantFieldsData();
    $existingReceivableData = $participantFieldsData->matching(self::criteriaWhere(['optionKey' => $receivable->getKey()]));
    if ($existingReceivableData->count() == 0) {
      $this->logInfo('RECEIVABLE update of '.$participant->getMusician()->getFirstName());
      $datum = (new Entities\ProjectParticipantFieldDatum)
             ->setField($receivable->getField())
             ->setMusician($participant->getMusician())
             ->setProject($participant->getProject())
             ->setOptionKey($receivable->getKey())
             ->setOptionValue($receivable->getData());
      $participantFieldsData->set($datum->getOptionKey()->getBytes(), $datum);
      $receivable->getFieldData()->add($datum);
      $participant->getMusician()->getProjectParticipantFieldsData()->add($datum);
      $participant->getProject()->getParticipantFieldsData()->add($datum);
    } else {
      // there is at most one ...
      /** @var Entities\ProjectParticipantFieldDatum $datum */
      $datum = $existingReceivableData->first();
      $datum->setOptionValue((float)$datum->getOptionValue()+0.01);
    }
  }
}
