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

use \DateTimeImmutable as DateTime;

use OCA\CAFEVDB\Database\EntityManager;
use Doctrine\Common\Collections\Collection;
use OCA\CAFEVDB\Database\Doctrine\ORM\Entities;
use OCA\CAFEVDB\Database\Doctrine\ORM\Repositories;
use OCA\CAFEVDB\Common\Uuid;
use OCA\CAFEVDB\Service\ConfigService;
use OCA\CAFEVDB\Service\ToolTipsService;

use OCA\CAFEVDB\Common\Functions;
use OCA\CAFEVDB\Database\Doctrine\Util as DBUtil;

/**
 * Do nothing implementation to have something implementing
 * the interface. Would rather belong to a test-suite.
 */
class InstrumentInsuranceReceivablesGenerator extends AbstractReceivablesGenerator
{
  use \OCA\CAFEVDB\Traits\ConfigTrait;

  /** @var InstrumentInsuranceService */
  private $insuranceService;

  /** @var Repositories\InstrumentInsurancesRepository */
  private $insurancesRepository;

  /** @var ToolTipsService */
  private $toolTipsService;

  /** @var \DateTimeZone */
  private $timeZone;

  public function __construct(
    ConfigService $configService
    , InstrumentInsuranceService $insuranceService
    , ToolTipsService $toolTipsService
    , EntityManager $entityManager
  ) {
    parent::__construct($entityManager);

    $this->insuranceService = $insuranceService;
    $this->configService = $configService;
    $this->l = $this->l10n();
    $this->toolTipsService = $toolTipsService;

    $this->insurancesRepository = $this->getDatabaseRepository(Entities\InstrumentInsurance::class);
    $this->timeZone = $this->getDateTimeZone();
  }

  /**
   * {@inheritdoc}
   */
  public function generateReceivables():Collection
  {
    $receivableOptions = $this->serviceFeeField->getDataOptions();

    $startingDate = $this->insurancesRepository->startOfInsurances()
                                               ->setTimezone($this->timeZone);
    $startingYear = $startingDate->format('Y');
    $endingYear   = (new DateTime)->setTimezone($this->timeZone)->format('Y');

    for ($year = $startingYear; $year <= $endingYear; ++$year) {
      if ($receivableOptions->matching(self::criteriaWhere(['data' => $year]))->count() == 0) {
        // add a new option
        $receivable = (new Entities\ProjectParticipantFieldDataOption)
                    ->setField($this->serviceFeeField)
                    ->setKey(Uuid::create())
                    ->setLabel($this->l->t('Insurance Fee %d', $year))
                    ->setToolTip($this->toolTipsService['instrument-insurance-annual-service-fee'])
                    ->setData($year) // may change in the future
                    ->setLimit(null); // may change in the future
        $receivableOptions->set($receivable->getKey()->getBytes(), $receivable);
      }
    }

    return $this->serviceFeeField->getSelectableOptions();
  }

  /**
   * {@inheritdoc}
   */
  protected function updateOne(Entities\ProjectParticipantFieldDataOption $receivable, Entities\ProjectParticipant $participant)
  {
    // @todo
    // * find list of insurance years
    // * walk years from start until now
    //   - add missing items if insurance fee != 0
    //   - remove items without payment when insurance fee == 0
    //   - update all existing items with newly computed insurance sum

    $year = $receivable->getLimit();
    /** @var Entities\Musician $musician */
    $musician = $participant->getMusician();
    /** @var Entities\Project $project */
    $project = $participant->getProject();
    $fee = $this->insuranceService->insuranceFee($musician, new \DateTimeImmutable($year.'-06-01'));

    $participantFieldsData = $participant->getParticipantFieldsData();
    $optionKey = $receivable->getKey();
    $datum = $participant->getParticipantFieldsDatum($optionKey);
    if (empty($datum) && $fee != 0.0) {
      // add a new option
      /** @var Entities\ProjectParticipantFieldDatum $datum */
      $datum = (new Entities\ProjectParticipantFieldDatum)
             ->setField($receivable->getField())
             ->setMusician($musician)
             ->setProject($participant->getProject())
             ->setOptionKey($receivable->getKey())
             ->setOptionValue($fee);
      // @todo Too much connectivity
      $participantFieldsData->set($optionKey->getBytes(), $datum);
      $musician->getProjectParticipantFieldsData()->set($optionKey->getBytes(), $datum);
      $receivable->getFieldData()->set($musician->getId(), $datum);
      $project->getParticipantFieldsData()->add($datum);
    } else if (!empty($datum) && $fee == 0.0) {
      // remove current option
      $this->remove($datum);
      $participantFieldsData->removeElement($datum);
      $musician->getProjectParticipantFieldsData()->removeElement($datum);
      $receivable->getFieldData()->removeElement($datum);
      $project->getParticipantFieldsData()->removeElement($datum);
    } else {
      // just update current data to the computed value
      $datum->setOptionValue($fee);
    }
  }
}
