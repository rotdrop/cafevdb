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

use DateInterval;
use RuntimeException;
use DateTimeImmutable;

use Psr\Log\LoggerInterface as ILogger;
use OCP\IL10N;

use OCA\CAFEVDB\Database\EntityManager;
use OCA\CAFEVDB\Service\ConfigService;
use OCA\CAFEVDB\Service\ToolTipsService;
use OCA\CAFEVDB\Service\ProgressStatusService;
use OCA\CAFEVDB\Wrapped\Doctrine\Common\Collections\Collection;
use OCA\CAFEVDB\Database\Doctrine\ORM\Entities;
use OCA\CAFEVDB\Common\Uuid;
use OCA\CAFEVDB\Common\Util;

/**
 * Always generate a new payment request. This is just a dummy
 * nonsense proof-of-concept implementation. It will always generate
 * new receivables, and calling updateReceivable() will also always
 * just change the amount to pay.
 */
class PeriodicReceivablesGenerator extends AbstractReceivablesGenerator
{
  use \OCA\CAFEVDB\Traits\ConfigTrait;
  use \OCA\CAFEVDB\Traits\EntityTranslationTrait;

  /** @var float */
  protected $amount;

  /** @var ToolTipsService */
  protected $toolTipsService;

  /** @var \DateTimeZone */
  private $timeZone;

  /** @var int */
  private $intervalSeconds;

  // phpcs:disable Squiz.Commenting.FunctionComment.Missing
  public function __construct(
    ConfigService $configService,
    EntityManager $entityManager,
    ToolTipsService $toolTipsService,
    ProgressStatusService $progressStatusService,
    ?\DateInterval $interval = null,
  ) {
    parent::__construct($entityManager, $progressStatusService);
    $this->configService = $configService;
    $this->l = $this->l10n();

    $this->amount = 1.0;

    if (empty($interval)) {
      $interval = new DateInterval('P1D');
    }

    // Horner's scheme, leap years not taken into account
    $this->intervalSeconds = $interval->s + 60 * ($interval->i + 60 * ($interval->h + 24 * ($interval->d + 12 * $interval->m + 365 * $interval->y)));

    $this->timeZone = $this->getDateTimeZone();
  }
  // phpcs:enable

  /** {@inheritdoc} */
  public static function slug():string
  {
    return self::t('periodic');
  }

  /** {@inheritdoc} */
  public function generateReceivables():Collection
  {
    $receivableOptions = $this->serviceFeeField->getDataOptions();

    /** @var Entities\ProjectParticipantFieldDataOption $managementOption */
    $managementOption = $this->serviceFeeField->getManagementOption();
    if (empty($managementOption)) {
      throw new RuntimeException(
        $this->l->t(
          'Unable to find management option for participant field "%s".',
          $this->serviceFeeField->getName()
        ));
    }
    $managementDate = Util::convertToDateTime($managementOption->getLimit());
    if (empty($managementDate)) {
      $startingDate = new DateTimeImmutable;
    } else {
      $startingDate = $managementDate;
    }

    /** @var \DateTimeImmutable $startingDate */
    $startingDate = $startingDate->setTimezone($this->timeZone);
    $seconds = ($startingDate->getTimestamp() + $this->intervalSeconds - 1)
             / $this->intervalSeconds * $this->intervalSeconds;
    $startingDate = $startingDate->setTimestamp($seconds);
    $managementOption->setLimit($startingDate->getTimestamp());
    $endingSeconds = (new DateTimeImmutable)->getTimestamp();

    for (; $seconds <= $endingSeconds; $seconds += $this->intervalSeconds) {
      $timeStamp = $this->formatTimeStamp($seconds);
      $labelText = $this->l->t($labelTemplate = 'Option %s', $timeStamp);
      $tooltipTemplate = $this->toolTipsService['periodic-recurring-receivable-option']??'';
      $tooltipText = $this->l->t($tooltipTemplate);

      $existingReceivables = $receivableOptions->matching(self::criteriaWhere(['data' => (string)$seconds]));
      if ($existingReceivables->isEmpty()) {
        // add a new option
        $receivable = (new Entities\ProjectParticipantFieldDataOption)
                    ->setField($this->serviceFeeField)
                    ->setKey(Uuid::create())
                    ->setLabel($labelText)
                    ->setToolTip($tooltipText)
                    ->setData($seconds) // may change in the future
                    ->setLimit(null); // may change in the future
        $receivableOptions->set($receivable->getKey()->getBytes(), $receivable);
      } else {
        // update display things, but keep the essential data untouched
        /** @var Entities\ProjectParticipantFieldDataOption $receivable */
        $receivable = $existingReceivables->first();
        $receivable->setLabel($labelText)
                   ->setTooltip($tooltipText);
      }
      $this->translate($receivable, 'label', null, sprintf($labelTemplate, $timeStamp))
           ->translate($receivable, 'tooltip', null, $tooltipTemplate);
    }
    return $this->serviceFeeField->getSelectableOptions();
  }

  /** {@inheritdoc} */
  protected function updateOne(
    Entities\ProjectParticipantFieldDataOption $receivable,
    Entities\ProjectParticipant $participant,
    string $updateStrategy = self::UPDATE_STRATEGY_EXCEPTION,
  ):array {
    $participantFieldsData = $participant->getParticipantFieldsData();
    $existingReceivableData = $participantFieldsData->matching(self::criteriaWhere(['optionKey' => $receivable->getKey()]));
    $added = $removed = $changed = $skipped = false;
    $notices = [];
    if ($existingReceivableData->isEmpty()) {
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
      $added = true;
    } else {
      /** @var Entities\ProjectParticipantFieldDatum $datum */
      $datum = $existingReceivableData->first(); // there is at most one ...
      $datum->setOptionValue((float)$datum->getOptionValue()+0.01);
      $changed = true;
    }
    return [
      'added' => (int)$added,
      'removed' => (int)$removed,
      'changed' => (int)$changed,
      'skipped' => (int)$skipped,
      'notices' => $notices,
    ];
  }
}
