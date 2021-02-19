<?php
/* Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine
 * @copyright 2020 Claus-Justus Heine <himself@claus-justus-heine.de>
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

/******************************************************************************
 *
 * We may want to move the office stuff to a separate service
 *
 */

use PhpOffice\PhpSpreadsheet;
use OCA\CAFEVDB\PageRenderer\Util\PhpSpreadsheetValueBinder;

/*
 *
 *****************************************************************************/

use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\AppFramework\Http\DataDownloadResponse;
use OCP\AppFramework\Http\ContentSecurityPolicy;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\IAppContainer;
use OCP\IUserSession;
use OCP\IRequest;
use OCP\ILogger;
use OCP\IL10N;
use OCP\ITempManager;

use OCA\CAFEVDB\Service\ConfigService;
use OCA\CAFEVDB\Service\HistoryService;
use OCA\CAFEVDB\Service\RequestParameterService;
use OCA\CAFEVDB\Service\ProjectService;
use OCA\CAFEVDB\Database\EntityManager;
use OCA\CAFEVDB\Database\Doctrine\ORM\Entities;
use OCA\CAFEVDB\Database\Legacy\PME\PHPMyEdit;

class PmeTableController extends Controller {
  use \OCA\CAFEVDB\Traits\ConfigTrait;
  use \OCA\CAFEVDB\Traits\ResponseTrait;

  /** @var HistoryService */
  private $historyService;

  /** @var ParameterService */
  private $parameterService;

  /** @var ProjectService */
  private $projectService;

  /** @var \OCA\CAFEVDB\Database\Legacy\PME\PHPMyEdit */
  protected $pme;

  /** @var string */
  private $userId;

  /** @var IL10N */
  protected $l;

  /** @var ILogger */
  protected $logger;

  /** @var \OCP\AppFramework\IAppContainer */
  private $appContainer;

  /** @var \OCP\ITempManager */
  private $tempManager;

  public function __construct(
    $appName
    , IRequest $request
    , IAppContainer $appContainer
    , ConfigService $configService
    , HistoryService $historyService
    , RequestParameterService $parameterService
    , ProjectService $projectService
    , PHPMyEdit $phpMyEdit
    , ITempManager $tempManager
    , $userId
    , IL10N $l10n
    , ILogger $logger
  ) {
    parent::__construct($appName, $request);

    $this->appContainer = $appContainer;
    $this->parameterService = $parameterService;
    $this->projectService = $projectService;
    $this->historyService = $historyService;
    $this->configService = $configService;
    $this->pme = $phpMyEdit;
    $this->tempManager = $tempManager;
    $this->logger = $logger;
    $this->userId = $userId;
    $this->l = $l10n;
  }

  /**
   * Return template for table load
   *
   * @NoAdminRequired
   * @UseSession
   */
  public function serviceSwitch($topic)
  {
    switch ($topic) {
    case 'load':
      return $this->load();
    case 'export':
      return $this->export();
    }
    return self::grumble($this->l->t('Unknown Request: "%s".', $topic));
  }

  /**
   * Return template for table load
   */
  private function load()
  {
    try {
      $templateRenderer = $this->parameterService->getParam('templateRenderer');
      $template = $this->parameterService->getParam('template');
      $dialogMode = !empty($this->parameterService->getParam('ambientContainerSelector'));
      $reloadAction = false;
      $reloadAction = $this->parameterService->getParam(
        $this->pme->cgiSysName('_reloadfilter'),
        $this->parameterService->getParam($this->pme->cgiSysName('_reloadlist'))
      ) !== null;

      $historySize = -1;
      $historyPosition = -1;
      if (!$dialogMode && !$reloadAction) {
        $this->historyService->push($this->parameterService->getParams());
        $historySize = $this->historyService->size();
        $historyPosition = $this->historyService->position();
      }

      if (empty($templateRenderer)) {
        return self::grumble(['error' => $this->l->t('missing arguments'),
                              'message' => $this->l->t('No template-renderer submitted.'), ]);
      }

      $renderer = $this->appContainer->query($templateRenderer);
      if (empty($renderer)) {
        return self::response(
          $this->l->t("Template-renderer `%s' cannot be found.", [$templateRenderer]),
          Http::INTERNAL_SERVER_ERROR);
      }
      // $renderer->navigation(false); NOPE, navigation is needed, number of query records may change.

      $template = 'pme-table';
      $templateParameters = [
        'renderer' => $renderer,
        'templateRenderer' => $templateRenderer,
        'template' => $template,
        'recordId' => $this->pme->getCGIRecordId(),
      ];

      $response = new TemplateResponse($this->appName, $template, $templateParameters, 'blank');

      $response->addHeader('X-'.$this->appName.'-history-size', $historySize);
      $response->addHeader('X-'.$this->appName.'-history-position', $historyPosition);

      if (!$dialogMode && !$reloadAction) {
        $this->historyService->store();
      }

      return $response;

    } catch (\Throwable $t) {
      $this->logException($t, __METHOD__);
      return self::grumble($this->exceptionChainData($t));
    }
  }

  /**
   * Return template for table load
   *
   * @todo Most of this stuff should be moved somewhere else, e.g. to
   * PageRenderer.
   */
  private function export()
  {
    $exportFormat = $this->parameterService['exportFormat'];
    if (empty($exportFormat)) {
      return self::grumble($this->l->t('No export-format submitted'));
    }

    $template = $this->parameterService->getParam('template');
    if (empty($template)) {
      return self::grumble(['error' => $this->l->t('missing arguments'),
                            'message' => $this->l->t('No template submitted.'), ]);
    }

    /** @var OCA\CAFEVDB\PageRenderer\Export\AbstractSpreadsheetExporter */
    $exporter = $this->appContainer->query('export'.':'.$template);
    if (empty($exporter)) {
      return self::response(
        $this->l->t('Template-exporter for template "%s" cannot be found.', [$template]),
        Http::STATUS_BAD_REQUEST);
    }

    $tmpFile = $this->tempManager->getTemporaryFile($this->appName());
    register_shutdown_function(function() {
      $this->tempManager->clean();
    });

    $fileMeta = $exporter->export($tmpFile, $exportFormat);

    $data = file_get_contents($tmpFile);
    unlink($tmpFile);

    $fileName  = $date.'-'.$this->appName().'-'.$name.'.'.$fileMeta['extension'];

    return new DataDownloadResponse($data, $fileName, $fileMeta['mimeType']);
  }

}

// Local Variables: ***
// c-basic-offset: 2 ***
// indent-tabs-mode: nil ***
// End: ***
