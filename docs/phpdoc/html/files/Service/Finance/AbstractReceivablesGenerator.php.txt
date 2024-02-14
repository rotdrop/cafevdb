<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine <himself@claus-justus-heine.de>
 * @copyright 2021, 2022, 2023, 2024 Claus-Justus Heine
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

use RuntimeException;

use OCA\CAFEVDB\Database\EntityManager;
use OCA\CAFEVDB\Database\Doctrine\ORM\Entities;
use OCA\CAFEVDB\Database\Doctrine\DBAL\Types\EnumParticipantFieldDataType as FieldType;
use OCA\CAFEVDB\Service\ProgressStatusService;
use OCA\CAFEVDB\Common\IProgressStatus;
use OCA\CAFEVDB\Common\DoNothingProgressStatus;
use OCA\CAFEVDB\Exceptions;

/** Base class for the specific receivable generators. */
abstract class AbstractReceivablesGenerator implements IRecurringReceivablesGenerator
{
  use \OCA\CAFEVDB\Traits\EntityManagerTrait;
  use \OCA\CAFEVDB\Toolkit\Traits\FakeTranslationTrait;

  /** @var Entities\ProjectParticipantField */
  protected $serviceFeeField;

  /** @var IProgressStatus */
  protected $progressStatus;

  /** @var array */
  protected $progressData;

  // phpcs:disable Squiz.Commenting.FunctionComment.Missing
  public function __construct(
    protected EntityManager $entityManager,
    protected ProgressStatusService $progressStatusService,
  ) {
    $this->progressStatus = new DoNothingProgressStatus;
    $this->progressData = [
      'field' => null,
      'musician' => null,
      'receivable' => null,
    ];
  }
  // phpcs:enable

  /**
   * Inject some translations of constants into the machinery.
   *
   * @return void
   */
  protected static function translationDummy():void
  {
    self::t(self::UPDATE_STRATEGY_EXCEPTION);
    self::t(self::UPDATE_STRATEGY_REPLACE);
    self::t(self::UPDATE_STRATEGY_SKIP);
  }

  /** {@inheritdoc} */
  public static function uiFlags():int
  {
    return self::UI_PROTECTED_LABEL|self::UI_PROTECTED_VALUE;
  }

  /** {@inheritdoc} */
  public static function updateStrategyChoices():array
  {
    return self::UPDATE_STRATEGIES;
  }

  /** {@inheritdoc} */
  public static function operationLabels(?string $slug = null)
  {
    $labels = [
      self::OPERATION_OPTION_REGENERATE => true,
      self::OPERATION_OPTION_REGENERATE_ALL => function(string $dataType) {
        switch ($dataType) {
          case FieldType::RECEIVABLES:
            return self::t('Recompute all Receivables');
          case FieldType::LIABILITIES:
            return self::t('Recompute all Liabilities');
          default:
            return self::t('Update all Input-Options');
        }
      },
      self::OPERATION_GENERATOR_RUN => true,
      self::OPERATION_GENERATOR_REGENERATE => true,
    ];
    return $slug === null ? $labels : $labels[$slug]??null;
  }

  /** {@inheritdoc} */
  public function bind(Entities\ProjectParticipantField $serviceFeeField, ?IProgressStatus $progressStatus = null):void
  {
    $this->serviceFeeField = $serviceFeeField;
    $this->progressStatus = $progressStatus;
    if (empty($this->progressStatus)) {
      $this->progressStatus = new DoNothingProgressStatus;
    }
    $this->progressData['field'] = $serviceFeeField->getName();
    $this->progressStatus->update(-1, 0, $this->progressData);
  }

  /** {@inheritdoc} */
  public function updateAll(string $updateStrategy = self::UPDATE_STRATEGY_EXCEPTION):array
  {
    ignore_user_abort(false);
    $added = $removed = $changed = $skipped = 0;
    $notices = [];
    $progressStatus = [];
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
        'notices' => $n,
        'musicians' => $musicians,
      ) = $this->updateReceivable($receivable, null, $updateStrategy);
      $added += $a;
      $removed += $r;
      $changed += $c;
      $skipped += $s;
      $notices = array_merge($notices, $n);
      $progressStatus[(string)$receivable->getKey()] = [
        'label' => $receivable->getLabel(),
        'musicians' => $musicians,
      ];
    }
    return [
      'added' => $added,
      'removed' => $removed,
      'changed' => $changed,
      'skipped' => $skipped,
      'notices' => $notices,
      'status' => $progressStatus,
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
   * @param string $updateStrategy
   *
   * @return array<string, int>
   * ```
   * [ 'added' => #ADDED, 'removed' => #REMOVED, 'changed' => #CHANGED ]
   * ```
   * where of course each component is either 0 or 1.
   *
   * @throws RuntimeException Depending on $updateStrategy.
   */
  abstract protected function updateOne(
    Entities\ProjectParticipantFieldDataOption $receivable,
    Entities\ProjectParticipant $participant,
    string $updateStrategy = self::UPDATE_STRATEGY_EXCEPTION,
  ):array;

  /** {@inheritdoc} */
  public function updateReceivable(
    Entities\ProjectParticipantFieldDataOption $receivable,
    ?Entities\ProjectParticipant $participant = null,
    string $updateStrategy = self::UPDATE_STRATEGY_EXCEPTION,
  ):array {
    $added = $removed = $changed = $skipped = 0;
    $notices = [];
    $this->progressData['receivable'] = $receivable->getLabel();
    $musicians = [];
    if (!empty($participant)) {
      $musician = $participant->getMusician();
      $musicianId = $musician->getId();
      $musicianName = $musician->getPublicName(firstNameFirst: true);
      $musicians[$musicianId] = $musicianName;
      $this->progressData['musician'] = $musicianName;
      $this->progressStatus->update(0, 1, $this->progressData);
      list(
        'added' => $added,
        'removed' => $removed,
        'changed' => $changed,
        'skipped' => $skipped,
        'notices' => $notices,
      ) = $this->updateOne($receivable, $participant, $updateStrategy);
      if ($this->progressStatus->increment() === false) {
        throw new Exceptions\EnduserNotificationException(
          $this->l->t('Operation has been cancelled by user, last processed data was %s / %s.', [
            $receivable->getLabel(), $participant->getPublicName(true),
          ]));
      }
    } else {
      ignore_user_abort(false);
      $participants = $receivable->getField()->getProject()->getParticipants();
      if ($this->progressStatus->getTarget() <= 0) {
        $this->progressStatus->update(0, $participants->count());
      }
      /** @var Entities\ProjectParticipant $participant */
      foreach ($participants as $participant) {
        $musician = $participant->getMusician();
        $musicianId = $musician->getId();
        $musicianName = $musician->getPublicName(firstNameFirst: true);
        $musicians[$musicianId] = $musicianName;
        $this->progressData['musician'] = $musicianName;
        $this->progressStatus->setData($this->progressData);
        list(
          'added' => $a,
          'removed' => $r,
          'changed' => $c,
          'skipped' => $s,
          'notices' => $n,
        ) = $this->updateOne($receivable, $participant, $updateStrategy);
        if ($this->progressStatus->increment() === false) {
          throw new Exceptions\EnduserNotificationException($this->l->t(
            'Operation has been cancelled by user, last processed data was %s / %s.', [
              $receivable->getLabel(), $participant->getPublicName(true),
            ]));
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
      'receivables' => [
        (string)$receivable->getKey() => $receivable->getLabel(),
      ],
      'muscians' => $musicians,
    ];
  }

  /** {@inheritdoc} */
  public function updateParticipant(
    Entities\ProjectParticipant $participant,
    ?Entities\ProjectParticipantFieldDataOption $receivable,
    string $updateStrategy = self::UPDATE_STRATEGY_EXCEPTION,
  ):array {
    $musician = $participant->getMusician();
    $musicianId = $musician->getId();
    $musicianName = $musician->getPublicName(firstNameFirst: true);
    $this->progressData['musician'] = $musicianName;
    $added = $removed = $changed = $skipped = 0;
    $notices = [];
    $receivables = [];
    if (!empty($receivable)) {
      $receivableKey = (string)$receivable->getKey();
      $receivableLabel = $receivable->getLabel();
      $receivables[$receivableKey] = $receivableLabel;
      $this->progressData['receivable'] = $receivableLabel;
      $this->progressStatus->update(0, 1, $this->progressData);
      list(
        'added' => $added,
        'removed' => $removed,
        'changed' => $changed,
        'skipped' => $skipped,
        'notices' => $notices) = $this->updateOne($receivable, $participant, $updateStrategy);
      if ($this->progressStatus->increment() === false) {
        throw new Exceptions\EnduserNotificationException($this->l->t(
          'Operation has been cancelled by user, last processed data was %s / %s.', [
            $receivableLabel, $musicianName,
          ]));
      }
    } else {
      ignore_user_abort(false);
      $receivables = $this->serviceFeeField->getSelectableOptions();
      if ($this->progressStatus->getTarget() <= 0) {
        $this->progressStatus->update(0, $receivables->count());
      }
      foreach ($receivables as $receivable) {
        $receivableKey = (string)$receivable->getKey();
        $receivableLabel = $receivable->getLabel();
        $receivables[$receivableKey] = $receivableLabel;
        $this->progressData['receivable'] = $receivableLabel;
        $this->progressStatus->setData($this->progressData);
        list(
          'added' => $a,
          'removed' => $r,
          'changed' => $c,
          'skipped' => $s,
          'notices' => $n,
        ) = $this->updateOne($receivable, $participant, $updateStrategy);
        if ($this->progressStatus->increment() === false) {
          throw new Exceptions\EnduserNotificationException($this->l->t(
            'Operation has been cancelled by user, last processed data was %s / %s.', [
              $receivableLabel, $musicianName,
            ]));
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
      'musicians' => [
        $musicianId => $musicianName,
      ],
      'receivables' => $receivables,
    ];
  }

  /** {@inheritdoc} */
  public function dueDate(?Entities\ProjectParticipantFieldDataOption $receivable = null):?\DateTimeInterface
  {
    return $this->serviceFeeField->getDueDate();
  }
}
