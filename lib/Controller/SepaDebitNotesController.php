<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine
 * @copyright 2020, 2021 Claus-Justus Heine <himself@claus-justus-heine.de>
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

namespace OCA\CAFEVDB\Controller;

use \PHP_IBAN\IBAN;

use OCP\AppFramework\Controller;
use OCP\IRequest;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\TemplateResponse;

use OCA\CAFEVDB\Service\ConfigService;
use OCA\CAFEVDB\Service\RequestParameterService;
use OCA\CAFEVDB\Service\ProjectService;
use OCA\CAFEVDB\Service\Finance\FinanceService;

use OCA\CAFEVDB\Database\EntityManager;
use OCA\CAFEVDB\Database\Legacy\PME\PHPMyEdit;
use OCA\CAFEVDB\Database\Doctrine\ORM\Entities;

use OCA\CAFEVDB\Common\Util;
use OCA\CAFEVDB\Common\Uuid;

class SepaDebitNotesController extends Controller {
  use \OCA\CAFEVDB\Traits\ResponseTrait;
  use \OCA\CAFEVDB\Traits\ConfigTrait;
  use \OCA\CAFEVDB\Traits\EntityManagerTrait;

  /** @var RequestParameterService */
  private $parameterService;

  /** @var FinanceService */
  private $financeService;

  /** @var ProjectService */
  private $projectService;

  /** @var PHPMyEdit */
  protected $pme;

  /** @var EntityManager */
  protected $entityManager;

  public function __construct(
    $appName
    , IRequest $request
    , RequestParameterService $parameterService
    , ConfigService $configService
    , FinanceService $financeService
    , ProjectService $projectService
    , EntityManager $entityManager
    , PHPMyEdit $phpMyEdit
  ) {
    parent::__construct($appName, $request);
    $this->parameterService = $parameterService;
    $this->configService = $configService;
    $this->financeService = $financeService;
    $this->projectService = $projectService;
    $this->entityManager = $entityManager;
    $this->pme = $phpMyEdit;
    $this->l = $this->l10N();
  }

  /**
   * @NoAdminRequired
   */
  public function serviceSwitch($topic)
  {
    // PME_sys_mrecs[] = "{\"project_id\":\"67\",\"musician_id\":\"1\",\"sequence\":\"1\"}"
    $mandateRecords = $this->parameterService->getParam($this->pme->cgiSysName('mrecs'), []);

    // decode all mandate ids and fetch the mandates from the data-base
    $mandatesRepository = $this->getDatabaseRepository(Entities\SepaDebitMandate::class);
    $mandateRecords = array_map(
      function($value) use ($mandatesRepository) {
        $mandateId = json_decode($value, true);
        return $mandatesRepository->find([
          'project' => $mandateId['project_id'],
          'musician' => $mandateId['musician_id'],
          'sequence' => $mandateId['sequence'],
        ]);
      },
      $mandateRecords);

    // Fetch all desired field options. We have two cases: for most
    // field-types all options are charged at once, but for recurring
    // service-fees only the selected options are taken into account.
    $debitJobs = array_unique($this->parameterService->getParam('debitJobs', []));

    $fieldRepository = $this->getDatabaseRepository(Entities\ProjectParticipantField::class);
    $receivablesRepository = $this->getDatabaseRepository(Entities\ProjectParticipantFieldDataOption::class);
    $receivables = [];
    foreach ($debitJobs as $debitJob) {
      $fieldId = filter_var($debitJob, FILTER_VALIDATE_INT, ['min_range' => 1]);
      if ($fieldId !== false) {
        // all options from this field
        /** @var Entities\ProjectParticipantField $field */
        $field = $fieldRepository->find($fieldId);
        foreach ($field->getSelectableOptions() as $receivable) {
          $receivables[] = $receivable;
        }
      } else if (Uuid::isValid($debitJob)) {
        // just this option, should be a recurring receivable
        $receivable = $receivablesRepository->findOneBy(['key' => Uuid::asUuid($debitJob) ]);
        $receivables[] = $receivable;
      } else {
        return self::grumble($this->l->t('Submitted debit-job id "%s" is neither a participant field nor a field-option uuid.', $debitJob));
      }
    }

    foreach ($receivables as $receivable) {
      $this->logInfo('RECEIVABLE '.$receivable->getKey().' / '.$receivable->getLabel().' / '.$receivable->getData());
    }

    return self::grumble($this->l->t('Unknown Request: "%s".', $topic));
  }

}

// Local Variables: ***
// c-basic-offset: 2 ***
// indent-tabs-mode: nil ***
// End: ***
