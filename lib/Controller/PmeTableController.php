<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine <himself@claus-justus-heine.de>
 * @copyright 2020, 2021, 2022 Claus-Justus Heine
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

namespace OCA\CAFEVDB\Controller;

/*-*****************************************************************************
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
use OCP\AppFramework\Http\ContentSecurityPolicy;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\IAppContainer;
use OCP\IUserSession;
use OCP\IRequest;
use OCP\ISession;
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
use OCA\CAFEVDB\Common\Util;
use OCA\CAFEVDB\PageRenderer\IPageRenderer;
use OCA\CAFEVDB\Response\PreRenderedTemplateResponse;

/** AJAX backends for legacy PME table stuff. */
class PmeTableController extends Controller
{
  use \OCA\CAFEVDB\Traits\ConfigTrait;
  use \OCA\CAFEVDB\Traits\ResponseTrait;

  /** @var ISession */
  private $session;

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

  // phpcs:disable Squiz.Commenting.FunctionComment.Missing
  public function __construct(
    string $appName,
    IRequest $request,
    ISession $session,
    IAppContainer $appContainer,
    ConfigService $configService,
    HistoryService $historyService,
    RequestParameterService $parameterService,
    ProjectService $projectService,
    PHPMyEdit $phpMyEdit,
    ITempManager $tempManager,
    $userId,
    IL10N $l10n,
    ILogger $logger,
  ) {
    parent::__construct($appName, $request);

    $this->session = $session;
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
  // phpcs:enable

  /**
   * Return template for table load.
   *
   * @param string $topic
   *
   * @return Http\DataResponse
   *
   * @NoAdminRequired
   * @UseSession
   */
  public function serviceSwitch(string $topic):Http\DataResponse
  {
    switch ($topic) {
      case 'load':
        return $this->load();
      case 'export':
        $this->session->close();
        return $this->export();
    }
    return self::grumble($this->l->t('Unknown Request: "%s".', $topic));
  }

  /**
   * Return template for table load.
   *
   * @return Http\Response
   */
  private function load():Http\Response
  {
    $this->logInfo('Start');
    try {
      $templateRenderer = $this->parameterService->getParam('templateRenderer');
      $template = $this->parameterService->getParam('template');
      $dialogMode = !empty($this->parameterService->getParam('ambientContainerSelector'));
      $reloadAction = false;
      $reloadAction = $this->parameterService->getParam(
        $this->pme->cgiSysName('_reloadfilter'),
        $this->parameterService->getParam($this->pme->cgiSysName('_reloadlist'))
      ) !== null;

      if (empty($templateRenderer)) {
        return self::grumble(['error' => $this->l->t('missing arguments'),
                              'message' => $this->l->t('No template-renderer submitted.'), ]);
      }

      /** @var IPageRenderer $renderer */
      $renderer = $this->appContainer->query($templateRenderer);
      if (empty($renderer)) {
        return self::response(
          $this->l->t("Template-renderer `%s' cannot be found.", [$templateRenderer]),
          Http::INTERNAL_SERVER_ERROR);
      }
      // $renderer->navigation(false); NOPE, navigation is needed, number of query records may change.

      if ($dialogMode || $reloadAction) {
        if (!$renderer->needPhpSession()) {
          $this->logInfo('Closing session');
          $this->session->close();
        }
        $historyAction = PageController::HISTORY_ACTION_LOAD;
      } else {
        $this->historyService->push($this->parameterService->getParams());
        $historyAction = PageController::HISTORY_ACTION_PUSH;
      }

      $template = 'pme-table';
      $templateParameters = [
        'renderer' => $renderer,
        'templateRenderer' => $templateRenderer,
        'template' => $template,
        'recordId' => $this->pme->getCGIRecordId(),
      ];

      $response = new PreRenderedTemplateResponse($this->appName, $template, $templateParameters, 'blank');

      $response->addHeader('X-'.$this->appName.'-history-action', $historyAction);

      if ($renderer->needPhpSession()) {
        $response->preRender();
      }

      if (!$dialogMode && !$reloadAction) {
        $this->historyService->store();
      }

      if (!$this->session->isClosed()) {
        $this->logInfo('Closing session');
        $this->session->close();
      }

      return $response;

    } catch (\Throwable $t) {
      $this->logException($t);
      return self::grumble($this->exceptionChainData($t));
    }
  }

  /**
   * Return template for table load
   *
   * @return Http\Response
   *
   * @todo Most of this stuff should be moved somewhere else, e.g. to
   * PageRenderer.
   */
  private function export():Http\Response
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

    $fileName  = implode('-', [
      $this->formatTimeStamp($fileMeta['date']),
      $this->appName(),
      Util::normalizeSpaces($this->transliterate($fileMeta['name']), '-'),
    ]) . '.' .  $fileMeta['extension'];

    return $this->dataDownloadResponse($data, $fileName, $fileMeta['mimeType']);
  }
}
