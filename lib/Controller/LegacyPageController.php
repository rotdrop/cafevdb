<?php
/**
 * Nextcloud - cafevdb
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Claus-Justus Heine <himself@claus-justus-heine.de>
 * @copyright Claus-Justus Heine 2014-2026
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

use InvalidArgumentException;

use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute as CoreAttributes;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\Http\JSONResponse;
use OCP\AppFramework\IAppContainer;
use OCP\IRequest;
use OCP\IURLGenerator;
use OC\AppFramework\Utility\QueryNotFoundException;
use Psr\Log\LoggerInterface;

use OCA\CAFEVDB\Attributes;
use OCA\CAFEVDB\Common\Util;
use OCA\CAFEVDB\Constants;
use OCA\CAFEVDB\Controller\DTO;
use OCA\CAFEVDB\Exceptions;
use OCA\CAFEVDB\PageRenderer;
use OCA\CAFEVDB\PageRenderer\DataConstants;
use OCA\CAFEVDB\PageRenderer\IPageRenderer;
use OCA\CAFEVDB\PageRenderer\PersistentCGIKeys;
use OCA\CAFEVDB\PageRenderer\Registration as RendererRegistration;
use OCA\CAFEVDB\PageRenderer\Util\Navigation as PageNavigation;
use OCA\CAFEVDB\Service\AssetService;
use OCA\CAFEVDB\Service\AuthorizationService;
use OCA\CAFEVDB\Service\ConfigCheckService;
use OCA\CAFEVDB\Service\ConfigService;
use OCA\CAFEVDB\Service\EncryptionService;
use OCA\CAFEVDB\Service\HistoryService;
use OCA\CAFEVDB\Service\OrganizationalRolesService;
use OCA\CAFEVDB\Service\ToolTipsService;
use OCA\CAFEVDB\Settings\ConfigConstants;

/** UI entry point providing the non-Vue front pages. */
class LegacyPageController extends Controller
{
  use \OCA\CAFEVDB\Toolkit\Traits\ResponseTrait;
  use \OCA\CAFEVDB\Traits\ConfigTrait;

  const DEFAULT_TEMPLATE = PageRenderer\Projects::TEMPLATE;

  /** @var array
   *
   * Result of ConfigCheckService.
   */
  private $configCheck;

  // phpcs:disable Squiz.Commenting.FunctionComment.Missing
  public function __construct(
    ?string $appName,
    IRequest $request,
    protected IAppContainer $appContainer,
    private AssetService $assetService,
    protected ConfigService $configService,
    protected HistoryService $historyService,
    private OrganizationalRolesService $organizationalRolesService,
    private AuthorizationService $authorizationService,
    protected ToolTipsService $toolTipsService,
    private PageNavigation $pageNavigation,
    private ConfigCheckService $configCheckService,
    private IURLGenerator $urlGenerator,
    protected LoggerInterface $logger,
  ) {
    parent::__construct($appName, $request);

    $this->l = $this->l10n();

    // See if we are configured
    $this->configCheck = $this->configCheckService->configured();
  }
  // phpcs:enable

  /**
   * Load a page and remembers the request parameters in the history.
   *
   * @param string $renderAs
   *
   * @return Http\Response
   */
  #[CoreAttributes\NoAdminRequired]
  #[CoreAttributes\FrontpageRoute(
    verb: 'POST',
    url: '/page/remember/{renderAs}',
    defaults: [
      'renderAs' => 'user',
    ],
  )]
  #[Attributes\AllowIFrameSelf]
  public function remember(string $renderAs): DataResponse|JSONResponse
  {
    $this->historyService->save($this->request->getParams());
    return $this->loader(
      renderAs: $renderAs,
      template: $this->request[PersistentCGIKeys::TEMPLATE],
      projectName: $this->request[PersistentCGIKeys::PROJECT_NAME],
      projectId: $this->request[PersistentCGIKeys::PROJECT_ID],
      musicianId: $this->request[PersistentCGIKeys::MUSICIAN_ID],
      historyAction: EnumLegacyHistoryAction::PUSH->value,
    );
  }

  /**
   * Load a specific page, also used to dynamically replace html content.
   *
   * @param string $renderAs
   *
   * @param null|string $template
   *
   * @param null|string $projectName
   *
   * @param mixed $projectId
   *
   * @param mixed $musicianId
   *
   * @param string $historyAction
   *
   * @return Http\Response
   */
  #[CoreAttributes\NoAdminRequired]
  #[CoreAttributes\FrontpageRoute(
    verb: 'POST',
    url: '/page/loader/{renderAs}',
    defaults: [
      'renderAs' => 'user',
    ],
  )]
  #[Attributes\AllowIFrameSelf]
  public function loader(
    string $renderAs,
    ?string $template,
    ?string $projectName = '',
    mixed $projectId = null,
    mixed $musicianId = null,
    string $historyAction = EnumLegacyHistoryAction::PUSH->value,
  ): DataResponse|JSONResponse {
    $historyAction = EnumLegacyHistoryAction::get($historyAction);
    if ($renderAs != Constants::RENDER_AS_PARTS) {
      throw new InvalidArgumentException($this->l->t('This controller can no longer serve front-page requests, in may only be accessed by the Vue frontend.'));
    }

    if ($historyAction != EnumLegacyHistoryAction::PUSH) {
      $this->logInfo('HISTORY ACTION ' . $historyAction->value);
    }

    $template = $this->getTemplate($template, $renderAs);
    $this->logDebug("Try load template ".$template);
    /** @var IPageRenderer $renderer */
    $renderer = $this->appContainer->get(DataConstants::RENDERER_PREFIX_TAG . $template);
    if (empty($renderer)) {
      // in principle this cannot happen has the DI container should already
      // have issued a QueryNotFoundException.
      throw new Exceptions\Exception(
        $this->l->t('Template-renderer for template "%s" is empty.', [$template]),
      );
    }

    $requiredPermissions = AuthorizationService::PERMISSION_FRONTEND|$renderer->requiredPermissions();

    if (!$this->authorizationService->authorized(null, $requiredPermissions)) {
      throw new Exceptions\NotAuthorizedException(
        $this->userId(),
        $this->authorizationService->getUserPermissions(),
        $requiredPermissions,
        $this->l->t('Access to the web frontend was denied for user "%s".', $this->userId()),
      );
    }

    // The most important ...
    $encryptionKey = $this->getAppEncryptionKey();

    $toolTipsEnabled = $this->getUserValue(EnumPersonalSettingsKey::TOOL_TIPS_ENABLED, 'on');
    $initialFilterVisibility = $this->getUserValue(EnumPersonalSettingsKey::INITIAL_FILTER_VISIBILITY, 'off');
    $directChange = $this->getUserValue(EnumPersonalSettingsKey::DIRECT_CHANGE, 'off');
    $deselectInvisibleMiscRecs = $this->getUserValue(EnumPersonalSettingsKey::DESELECT_INVISIBLE_MISC_RECS, 'off');
    $showDisabled = $this->getUserValue(EnumPersonalSettingsKey::SHOW_DISABLED, 'off');
    $restoreHistory = $this->getUserValue(EnumPersonalSettingsKey::RESTORE_HISTORY, 'off');
    $expertMode = $this->getUserValue(EnumPersonalSettingsKey::EXPERT_MODE, false);
    $financeMode = $this->getUserValue(EnumPersonalSettingsKey::FINANCE_MODE, false);
    $pageRowsDefault = $this->getUserValue(EnumPersonalSettingsKey::PAGE_ROWS_DEFAULT, 20);
    $debugMode = $this->getUserValue(EnumPersonalSettingsKey::DEBUG_MODE, 0);

    $encryptionKeyHash = $this->getConfigValue(ConfigConstants::APP_ENCRYPTION_KEY_HASH_KEY);

    $this->toolTipsService->debug(!!($debugMode & ConfigConstants::DEBUG_TOOLTIPS));

    $templateParameters = [
      'template' => $template,
      'renderer' => $renderer,
      'assets' => [
        AssetService::JS => $this->assetService->getJSAsset('app'),
        AssetService::CSS => $this->assetService->getCSSAsset('app'),
      ],
      'appConfig' => $this->configService,
      'pageNavigation' => $this->pageNavigation,
      'roles' => $this->organizationalRolesService,

      //'l' => $this->l,
      'appName' => $this->appName,
      'appNameTag' => 'app-' . $this->appName,

      'configcheck' => $this->configCheck,
      ConfigConstants::ORCHESTRA_NAME_KEY => $this->getConfigValue(ConfigConstants::ORCHESTRA_NAME_KEY),
      ConfigConstants::WIKI_NAME_SPACE_KEY => $this->getAppValue(ConfigConstants::WIKI_NAME_SPACE_KEY),
      ConfigConstants::USER_GROUP_KEY => $this->groupId(),
      ConfigConstants::SHARE_OWNER_KEY => $this->getConfigValue(ConfigConstants::SHARE_OWNER_KEY),
      ConfigConstants::SHARED_FOLDER => $this->getConfigValue(ConfigConstants::SHARED_FOLDER),
      ConfigConstants::IS_GROUP_ADMIN => $this->isSubAdminOfGroup(),
      Constants::RENDER_AS_USER => $this->userId(),
      EnumPersonalSettingsKey::EXPERT_MODE->value => $expertMode,
      EnumPersonalSettingsKey::FINANCE_MODE->value => $financeMode,
      EnumPersonalSettingsKey::TOOL_TIPS_ENABLED->value => $toolTipsEnabled,
      EnumPersonalSettingsKey::DEBUG_MODE->value => $debugMode,
      EnumPersonalSettingsKey::INITIAL_FILTER_VISIBILITY->value => $initialFilterVisibility,
      EnumPersonalSettingsKey::DIRECT_CHANGE->value => $directChange,
      EnumPersonalSettingsKey::DESELECT_INVISIBLE_MISC_RECS->value => $deselectInvisibleMiscRecs,
      EnumPersonalSettingsKey::SHOW_DISABLED->value => $showDisabled,
      EnumPersonalSettingsKey::RESTORE_HISTORY->value => $restoreHistory,
      EnumPersonalSettingsKey::PAGE_ROWS_DEFAULT->value => $pageRowsDefault,
      EnumPersonalSettingsKey::ENCRYPTION_KEY->value => $encryptionKey,
      ConfigConstants::APP_ENCRYPTION_KEY_HASH_KEY => $encryptionKeyHash,
      'toolTips' => $this->toolTipsService,
      'urlGenerator' => $this->urlGenerator,
      'uploadMaxFilesize' => Util::maxUploadSize(),
      'uploadMaxHumanFilesize' => \OCP\Util::humanFileSize(Util::maxUploadSize()),
      'projectName' => $projectName,
      'projectId' => $projectId,
      'musicianId' => $musicianId,
      'localeSymbol' => $this->getLocale(), // locale itself should already have been provided by NC core
      'timezone' => $this->getTimezone(),
      'requesttoken' => \OCP\Util::callRegister(),
    ];

    $templateParameters['omitEnvelope'] = true;
    $pageHtml = $this->templateResponse(
      $template,
      $templateParameters,
      Constants::RENDER_AS_BLANK,
    )->render();

    return DTO\LegacyPageLoaderResponse::fromArray([
      'template' => $template,
      'defaultTemplateParameters' => $renderer->navigationItem($projectId, $projectName)->templateParameters,
      'headerHtml' => $renderer->headerText(), // actually html
      'bodyHtml' => $pageHtml,
      'cssPrefix' => $renderer->cssPrefix(),
      'cssClass' => $renderer->cssClass(),
      'historyAction' => $historyAction,
    ])->response();
  }

  /**
   * @param null|string $template
   *
   * @param string $renderAs
   *
   * @return string
   */
  private function getTemplate(?string $template, string $renderAs): string
  {
    // Replace colons back to path separators. All our template parameters are
    // alpha-numeric with the exception that they man contain path separators
    // (i.e. '/') which optionally are replaced by colons (i.e. ':') in order
    // to avoid url en-/decoding. Here we need to convert back to path
    // separators.
    if (!$this->configCheck['summary']) {
      return PageRenderer\ConfigCheck::TEMPLATE;
    }
    if (empty($template)) {
      $template = self::DEFAULT_TEMPLATE;
    }

    /** @var BlogRenderer $blogRenderer */
    $blogRenderer = $this->appContainer->get(PageRenderer\Blog::class);
    if ($blogRenderer->notificationsPending()) {
      $template = PageRenderer\BLOG::TEMPLATE;
    }
    $template = str_replace(':', Constants::PATH_SEP, $template);

    return $template;
  }
}
