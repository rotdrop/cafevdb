<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine
 * @copyright 2021 Claus-Justus Heine <himself@claus-justus-heine.de>
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

use OCA\CAFEVDB\Database\EntityManager;
use OCA\CAFEVDB\Database\Doctrine\ORM\Entities;
use OCA\CAFEVDB\Database\Doctrine\DBAL\Types\EnumParticipantFieldDataType as FieldType;
use OCA\CAFEVDB\Service\ProgressStatusService;
use OCA\CAFEVDB\Common\IProgressStatus;
use OCA\CAFEVDB\Common\DoNothingProgressStatus;
use OCA\CAFEVDB\Exceptions;

abstract class AbstractReceivablesGenerator implements IRecurringReceivablesGenerator
{
  use \OCA\CAFEVDB\Traits\EntityManagerTrait;
  use \OCA\CAFEVDB\Traits\FakeTranslationTrait;

  /** @var Entities\ProjectParticipantField */
  protected $serviceFeeField;

  /** @var ProgressStatusService */
  protected $progressStatusService;

  /** @var IProgressStatus */
  protected $progressStatus;

  /** @var array */
  protected $progressData;

  public function __construct(
    EntityManager $entityManager
    , ProgressStatusService $progressStatusService
  ) {
    $this->entityManager = $entityManager;
    $this->progressStatusService = $progressStatusService;
    $this->progressStatus = new DoNothingProgressStatus;
    $this->progressData = [
      'field' => null,
      'musician' => null,
      'receivable' => null,
    ];
  }

  /**
   * {@inheritdoc}
   */
  static public function updateStrategyChoices():array
  {
    return self::UPDATE_STRATEGIES;
  }

  /**
   * {@inheritdoc}
   */
  static public function operationLabels(?string $slug = null)
  {
    $labels = [
      self::OPERATION_OPTION_REGENERATE => true,
      self::OPERATION_OPTION_REGENERATE_ALL => function(string $dataType) {
        return $dataType == FieldType::SERVICE_FEE
          ? self::t('Recompute all Receivables')
          : self::t('Update all Input-Options');
      },
      self::OPERATION_GENERATOR_RUN => true,
      self::OPERATION_GENERATOR_REGENERATE => true,
    ];
    return $slug === null ? $labels : $labels[$slug]??null;
  }

  /**
   * {@inheritdoc}
   */
  public function bind(Entities\ProjectParticipantField $serviceFeeField, $progressToken = null)
  {
    $this->serviceFeeField = $serviceFeeField;
    $this->progressStatus = null;
    if (!empty($progressToken)) {
      $this->progressStatus = $this->progressStatusService->get($progressToken);
    }
    if (empty($this->progressStatus)) { // handles also invalid token
      $this->progressStatus = new DoNothingProgressStatus;
    }
    $this->progressData['field'] = $serviceFeeField->getName();
    $this->progressStatus->update(-1, 0, $this->progressData);
  }

  /**
   * {@inheritdoc}
   */
  public function updateAll($updateStrategy = self::UPDATE_STRATEGY_EXCEPTION):array
  {
    ignore_user_abort(false);
    $added = $removed = $changed = $skipped = 0;
    $notices = [];
    $receivables = $this->serviceFeeField->getSelectableOptions();
    $participants = $this->serviceFeeField->getProject()->getParticipants();
    $totals = $receivables->count() * $participants->count();
    $this->progressStatus->update(0, $totals);
    /** @var Entities\ProjectParticipantFieldDataOption $receivable */
    foreach ($receivables as $receivable) {
      list(
        'added' => $a,
        'removed' => $r,
        'changed' => $c,
        'skipped' => $s,
        'notices' => $n
      ) = $this->updateReceivable($receivable, null, $updateStrategy);
      $added += $a;
      $removed += $r;
      $changed += $c;
      $skipped += $s;
      $notices = array_merge($notices, $n);
    }
    return [
      'added' => $added,
      'removed' => $removed,
      'changed' => $changed,
      'skipped' => $skipped,
      'notices' => $notices,
    ];
  }

  /**
   * Update just the given receivable and participant.
   *
   * @param Entities\ProjectParticipantFieldDataOption $receivable
   *   The option to update/recompute.
   *
   * @param Entities\ProjectParticipant $participant
   *   The musician to update the service fee claim for.
   *
   * @return array<string, int>
   * ```
   * [ 'added' => #ADDED, 'removed' => #REMOVED, 'changed' => #CHANGED ]
   * ```
   * where of course each component is either 0 or 1.
   *
   * @throws \RuntimeException depending on $updateStrategy.
   */
  protected abstract function updateOne(Entities\ProjectParticipantFieldDataOption $receivable, Entities\ProjectParticipant $participant, $updateStrategy = self::UPDATE_STRATEGY_EXCEPTION):array;

  /**
   * {@inheritdoc}
   */
  public function updateReceivable(
    Entities\ProjectParticipantFieldDataOption $receivable
    , ?Entities\ProjectParticipant $participant = null
    , $updateStrategy = self::UPDATE_STRATEGY_EXCEPTION):array
  {
    $added = $removed = $changed = $skipped = 0;
    $notices = [];
    $this->progressData['receivable'] = $receivable->getLabel();
    if (!empty($participant)) {
      $this->progressData['musician'] = $participant->getMusician()->getPublicName(true);
      $this->progressStatus->update(0, 1, $this->progressData);
      list(
        'added' => $added,
        'removed' => $removed,
        'changed' => $changed,
        'skipped' => $skipped,
        'notices' => $notices,
      ) = $this->updateOne($receivable, $participant, $updateStrategy);
      if ($this->progressStatus->increment() === false) {
        throw new Exceptions\EnduserNotificationException($this->l->t('Operation has been cancelled by user, last processed data was %s / %s.', [ $receivable->getLabel(), $participant->getMusician()->getPublicName(true) ]));
      }
    } else {
      ignore_user_abort(false);
      $participants = $receivable->getField()->getProject()->getParticipants();
      if ($this->progressStatus->getTarget() <= 0) {
        $this->progressStatus->update(0, $participants->count());
      }
      /** @var Entities\ProjectParticipant $participant */
      foreach ($participants as $participant) {
        $this->progressData['musician'] = $participant->getMusician()->getPublicName(true);
        $this->progressStatus->setData($this->progressData);
        list(
          'added' => $a,
          'removed' => $r,
          'changed' => $c,
          'skipped' => $s,
          'notices' => $n,
        ) = $this->updateOne($receivable, $participant, $updateStrategy);
        if ($this->progressStatus->increment() === false) {
          throw new Exceptions\EnduserNotificationException($this->l->t('Operation has been cancelled by user, last processed data was %s / %s.', [ $receivable->getLabel(), $participant->getMusician()->getPublicName(true) ]));
        }
        $added += $a;
        $removed += $r;
        $changed += $c;
        $skipped += $s;
        $notices = array_merge($notices, $n);
      }
    }
    return [
      'added' => $added,
      'removed' => $removed,
      'changed' => $changed,
      'skipped' => $skipped,
      'notices' => $notices,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function updateParticipant(Entities\ProjectParticipant $participant, ?Entities\ProjectParticipantFieldDataOption $receivable, $updateStrategy = self::UPDATE_STRATEGY_EXCEPTION):array
  {
    $this->progressData['musician'] = $participant->getMusician()->getPublicName(true);
    $added = $removed = $changed = $skipped = 0;
    $notices = [];
    if (!empty($receivable)) {
      $this->progressData['receivable'] = $receivable->getLabel();
      $this->progressStatus->update(0, 1, $this->progressData);
      list(
        'added' => $added,
        'removed' => $removed,
        'changed' => $changed,
        'skipped' => $skipped,
        'notices' => $notices) = $this->updateOne($receivable, $participant, $updateStrategy);
        if ($this->progressStatus->increment() === false) {
          throw new Exceptions\EnduserNotificationException($this->l->t('Operation has been cancelled by user, last processed data was %s / %s.', [ $receivable->getLabel(), $participant->getMusician()->getPublicName(true) ]));
        }
    } else {
      ignore_user_abort(false);
      $receivables = $this->serviceFeeField->getSelectableOptions();
      if ($this->progressStatus->getTarget() <= 0) {
        $this->progressStatus->update(0, $receivables->count());
      }
      foreach ($receivables as $receivable) {
        $this->progressData['receivable'] = $receivable->getLabel();
        $this->progressStatus->setData($this->progressData);
        list(
          'added' => $a,
          'removed' => $r,
          'changed' => $c,
          'skipped' => $s,
          'notices' => $n,
        ) = $this->updateOne($receivable, $participant, $updateStrategy);
        if ($this->progressStatus->increment() === false) {
          throw new Exceptions\EnduserNotificationException($this->l->t('Operation has been cancelled by user, last processed data was %s / %s.', [ $receivable->getLabel(), $participant->getMusician()->getPublicName(true) ]));
        }
        $added += $a;
        $removed += $r;
        $changed += $c;
        $skipped += $s;
        $notices = array_merge($notices, $n);
      }
    }
    return [
      'added' => $added,
      'removed' => $removed,
      'changed' => $changed,
      'skipped' => $skipped,
      'notices' => $notices,
    ];
  }

}
