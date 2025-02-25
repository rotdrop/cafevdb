<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine <himself@claus-justus-heine.de>
 * @copyright 2020-2025 Claus-Justus Heine
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

use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\ContentSecurityPolicy;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\IAppContainer;
use OCP\IL10N;
use OCP\IRequest;
use OCP\ITempManager;
use OCP\IUserSession;
use Psr\Log\LoggerInterface as ILogger;

use OCA\CAFEVDB\Common\Util;
use OCA\CAFEVDB\Database\Doctrine\ORM\Entities;
use OCA\CAFEVDB\Database\EntityManager;
use OCA\CAFEVDB\Database\Legacy\PME\PHPMyEdit;
use OCA\CAFEVDB\Exceptions;
use OCA\CAFEVDB\PageRenderer\IPageRenderer;
use OCA\CAFEVDB\Service\ConfigService;
use OCA\CAFEVDB\Service\HistoryService;
use OCA\CAFEVDB\Service\ProjectService;

/** AJAX backends for legacy PME table stuff. */
class PmeTableController extends Controller
{
  use \OCA\CAFEVDB\Traits\ConfigTrait;
  use \OCA\CAFEVDB\Toolkit\Traits\ResponseTrait;

  // phpcs:disable Squiz.Commenting.FunctionComment.Missing
  public function __construct(
    string $appName,
    IRequest $request,
    protected IAppContainer $appContainer,
    protected ConfigService $configService,
    private HistoryService $historyService,
    private ProjectService $projectService,
    protected PHPMyEdit $pme,
    private ITempManager $tempManager,
    private $userId,
    protected ILogger $logger,
  ) {
    parent::__construct($appName, $request);

    $this->l = $this->l10n();
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
   */
  public function serviceSwitch(string $topic):Http\Response
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
   * Return template for table load.
   *
   * @return Http\Response
   */
  private function load():Http\Response
  {
    $this->logDebug('Start');
    $templateRenderer = $this->request->getParam('templateRenderer');
    $template = $this->request->getParam('template');
    $dialogMode = !empty($this->request->getParam('ambientContainerSelector'));
    $reloadAction = false;
    $reloadAction = $this->request->getParam(
      $this->pme->cgiSysName('reloadfilter'),
      $this->request->getParam($this->pme->cgiSysName('reloadlist'))
    ) !== null;

    if (empty($templateRenderer)) {
      return self::grumble(['error' => $this->l->t('missing arguments'),
                            'message' => $this->l->t('No template-renderer submitted.'), ]);
    }

    /** @var IPageRenderer $renderer */
    $renderer = $this->appContainer->query($templateRenderer);
    if (empty($renderer)) {
      throw new Exceptions\Exception(
        $this->l->t("Template-renderer `%s' cannot be found.", [$templateRenderer]),
      );
    }

    if ($dialogMode || $reloadAction) {
      $historyAction = PageController::HISTORY_ACTION_REPLACE;
    } else {
      $this->historyService->save($this->request->getParams());
      $historyAction = PageController::HISTORY_ACTION_PUSH;
    }

    $template = 'pme-table';
    $templateParameters = [
      'renderer' => $renderer,
      'templateRenderer' => $templateRenderer,
      'template' => $template,
      'recordId' => $this->pme->getCGIRecordId(),
    ];

    $response = $this->templateResponse(
      $template,
      $templateParameters,
    );

    $response->addHeader('X-'.$this->appName.'-history-action', $historyAction);

    return $response;
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
    $exportFormat = $this->request['exportFormat'];
    if (empty($exportFormat)) {
      return self::grumble($this->l->t('No export-format submitted'));
    }

    $template = $this->request->getParam('template');
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
