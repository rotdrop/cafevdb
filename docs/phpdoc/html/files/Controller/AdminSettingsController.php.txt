<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine <himself@claus-justus-heine.de>
 * @copyright 2020, 2021, 2022, 2023 Claus-Justus Heine
 * @license AGPL-3.0-or-later
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  S$parameteree the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 */

namespace OCA\CAFEVDB\Controller;

use Throwable;

use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\DataResponse;
use OCP\IRequest;
use OCP\IL10N;

use OCA\DokuWiki\Service\AuthDokuWiki as WikiRPC;
use OCA\CAFEVDB\Service\ConfigService;
use OCA\CAFEVDB\Service\CloudUserConnectorService;
use OCA\CAFEVDB\Service\FontService;
use OCA\CAFEVDB\Settings\Admin as AdminSettings;

/** AJAX end-points for admin setttings. */
class AdminSettingsController extends Controller
{
  use \OCA\CAFEVDB\Traits\ConfigTrait;
  use \OCA\CAFEVDB\Toolkit\Traits\ResponseTrait;

  public const POST_REQUEST_FONT_CACHE = 'font-cache';
  public const DELEGATABLE_POST_REQUESTS = [
    self::POST_REQUEST_FONT_CACHE,
    FontService::DEFAULT_OFFICE_FONT_CONFIG,
    AdminSettings::CLOUD_USER_BACKEND_CONFIG_KEY,
  ];

  public const FONT_CACHE_UPDATE = 'update'; // scan for new entries
  public const FONT_CACHE_RESCAN = 'rescan'; // == purge + update
  public const FONT_CACHE_PURGE = 'purge'; // just remove everything

  /** @var OCA\DokuWikiEmedded\Service\AuthDokuWiki */
  private $wikiRPC;

  // phpcs:disable Squiz.Commenting.FunctionComment.Missing
  public function __construct(
    ?string $appName,
    IRequest $request,
    ConfigService $configService,
    WikiRPC $wikiRPC,
  ) {
    parent::__construct($appName, $request);

    $this->configService = $configService;
    $this->wikiRPC = $wikiRPC;
    $this->wikiRPC->errorReporting(WikiRPC::ON_ERROR_THROW);
    $this->l = $this->l10N();
  }
  // phpcs:enable

  /**
   * @param string $parameter
   *
   * @return DataResponse
   *
   * @NoGroupMemberRequired
   * _AT_SubAdminRequired
   * @AuthorizedAdminSetting(settings=OCA\CAFEVDB\Settings\Admin)
   */
  public function get(string $parameter):DataResponse
  {
    $value = null;
    switch ($parameter) {
      case AdminSettings::ORCHESTRA_USER_GROUP_KEY:
        $value = $this->getAppValue('usergroup');
        break;
      case AdminSettings::ORCHESTRA_USER_GROUP_KEY . 'Admins':
        $admins = $this->configService->getGroupSubAdmins();
        $value = [];
        /** @var \OCP\IUser $admin */
        foreach ($admins as $admin) {
          $value[] = $admin->getUID();
        }
        break;
      case AdminSettings::WIKI_NAME_SPACE_KEY:
        $value = $this->getAppValue('wikinamespace');
        break;
      case AdminSettings::CLOUD_USER_BACKEND_CONFIG_KEY:
        $value = $this->di(CloudUserConnectorService::class)->haveCloudUserBackendConfig();
        break;
      case FontService::TTF_FONTS_FOLDER_CONFIG:
        /** @var FontService $fontService */
        $fontService = $this->di(CloudUserConnectorService::class);
        $value = $fontService->getFontsFolderName();
        break;
      case AdminSettings::TRUE_TYPE_FONTS:
        /** @var FontService $fontService */
        $fontService = $this->di(CloudUserConnectorService::class);
        $value = $fontService->getFontFolderEntries();
        break;
      case FontService::DEFAULT_OFFICE_FONT:
        /** @var FontService $fontService */
        $fontService = $this->di(CloudUserConnectorService::class);
        $value = $fontService->getDefaultFontName();
        break;
    }
    if ($value != null) {
      return new DataResponse([ 'value' => $value ]);
    } else {
      return new DataResponse([ 'key' => $parameter ], Http::STATUS_NOT_FOUND);
    }
  }

  /**
   * @param string $parameter
   *
   * @param mixed $value
   *
   * @return DataResponse
   *
   * @NoGroupMemberRequired
   */
  public function postAdminOnly(string $parameter, mixed $value):DataResponse
  {
    return $this->post($parameter, $value);
  }

  /**
   * @param string $parameter
   *
   * @param mixed $value Value to set.
   *
   * @param null|string $operation Operation to perform.
   *
   * @return DataResponse
   *
   * @NoGroupMemberRequired
   * @AuthorizedAdminSetting(settings=OCA\CAFEVDB\Settings\Admin)
   */
  public function postDelegated(string $parameter, mixed $value = null, ?string $operation = null):DataResponse
  {
    switch ($parameter) {
      case AdminSettings::CLOUD_USER_BACKEND_CONFIG_KEY:
      case FontService::DEFAULT_OFFICE_FONT_CONFIG:
      case self::POST_REQUEST_FONT_CACHE:
        return $this->post($parameter, $value, $operation);
    }
    return self::grumble($this->l->t('Settings is reserved to cloud-administrators: "%s".', $parameter));
  }

  /**
   * @param string $parameter
   *
   * @param mixed $value Value to set.
   *
   * @param null|string $operation Operation to perform.
   *
   * @return DataResponse
   */
  private function post(string $parameter, mixed $value = null, ?string $operation = null):DataResponse
  {
    $wikiNameSpace = $this->getAppValue('wikinamespace');
    $orchestraUserGroup = $this->getAppValue('usergroup');
    try {
      switch ($parameter) {
        case AdminSettings::ORCHESTRA_USER_GROUP_KEY:
          $realValue = trim($value);
          if (!empty($orchestraUserGroup) && !empty($wikiNameSpace)) {
            $this->revokeWikiAccess($wikiNameSpace, $orchestraUserGroup);
          }
          $orchestraUserGroup = $realValue;
          $this->setAppValue('usergroup', $orchestraUserGroup);
          $result = [
            'orchestraUserGroup' => $orchestraUserGroup,
          ];
          if (empty($wikiNameSpace)) {
            $wikiNameSpace = $orchestraUserGroup;
            $this->setAppValue('wikinamespace', $wikiNameSpace);
            $result['wikiNameSpace'] = $wikiNameSpace;
          }
          $this->grantWikiAccess($wikiNameSpace, $orchestraUserGroup);
          $result = [
            'messages' => [
              'transient' => [
                $this->l->t('Setting orchestra group to "%s". Please login as group administrator and configure the Camerata DB application.', [$realValue]),
              ],
            ],
          ];
          return self::dataResponse($result);

        case AdminSettings::ORCHESTRA_USER_GROUP_KEY . 'Admins':
          if (!is_array($value)) {
            return self::grumble($this->l->t('Expecting a list of user-ids.'));
          }
          $userGroup = $this->group();
          if (empty($userGroup)) {
            return self::grumble($this->l->t('Orchestra management group is unset or non-existent'));
          }
          $currentAdmins = array_map(fn($user) => $user->getUID(), $this->getGroupSubAdmins());
          $missing = array_diff($value, $currentAdmins);
          $remaining = array_intersect($value, $currentAdmins);
          $excess = array_diff($currentAdmins, $value);
          $success = [];
          $failure = [];
          if (!empty($remaining)) {
            $success[] = $this->l->t('Already as sub-admin of "%1$s" configured: %2$s.', [ $userGroup->getGID(), implode(', ', $remaining) ]);
          }
          foreach ($missing as $userId) {
            try {
              $user = $this->user($userId);
              $this->subAdminManager()->createSubAdmin($user, $userGroup);
              $success[] = $this->l->t('Added "%1$s" as sub-admin of "%2$s".', [ $userId, $userGroup->getGID(), ]);
            } catch (Throwable $t) {
              $this->logException($t);
              $failure[] = $this->t->t('Failed to add "%1$s" as sub-admin to "%2$s": %3$s', [ $userId, $userGroup->getGID(), $t->getMessage(), ]);
            }
          }
          foreach ($excess as $userId) {
            try {
              $user = $this->user($userId);
              $this->subAdminManager()->deleteSubAdmin($user, $userGroup);
              $success[] = $this->l->t('Deleted "%1$s" as sub-admin from "%2$s".', [ $userId, $userGroup->getGID(), ]);
            } catch (Throwable $t) {
              $this->logException($t);
              $failure[] = $this->t->t('Failed to delete "%1$s" as sub-admin from "%2$s": %3$s', [ $userId, $userGroup->getGID(), $t->getMessage(), ]);
            }
          }
          $result = [
            'messages' => [
              'transient' => $success,
              'permanent' => $failure,
            ],
          ];
          return self::dataResponse($result);

        case AdminSettings::WIKI_NAME_SPACE_KEY:
          if (!empty($orchestraUserGroup) && !empty($wikiNameSpace)) {
            $this->revokeWikiAccess($wikiNameSpace, $orchestraUserGroup);
          }
          $realValue = trim($value);
          $wikiNameSpace = $realValue;
          $this->setAppValue('wikinamespace', $wikiNameSpace);
          $result['wikiNameSpace'] = $wikiNameSpace;

          if (!empty($orchestraUserGroup)) {
            $this->grantWikiAccess($wikiNameSpace, $orchestraUserGroup);
          }

          $result = [
            'messages' => [
              'transient' => [
                $this->l->t('Setting wiki name-space to "%s".', [$realValue]),
              ],
            ],
          ];
          return self::dataResponse($result);

        case AdminSettings::CLOUD_USER_BACKEND_CONFIG_KEY:
          $delete = $value !== null && $value !== '' && filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) === false;
          $messages = [];
          /** @var CloudUserConnectorService $cloudUserConnector */
          $cloudUserConnector = $this->di(CloudUserConnectorService::class);
          if ($delete) {
            $cloudUserConnector->setCloudUserSubAdmins(delete: true);
            $responses = $cloudUserConnector->configureCloudUserBackend(erase: true);
          } else {
            $cloudUserConnector = $this->di(CloudUserConnectorService::class);
            $responses = $cloudUserConnector->configureCloudUserBackend(erase: false);
            $cloudUserConnector->setCloudUserSubAdmins(delete: false);
          }
          $messages[] = $this->l->t('"%1$s" controller answered with "%2$s".', [
            CloudUserConnectorService::CLOUD_USER_BACKEND,
            implode('", "', $responses)
          ]);

          // Get front-end link to user-sql config
          //
          // https://example.com/nextcloud/index.php/settings/admin/user_sql
          $cloudUserBackendSettings = $this->urlGenerator()->getBaseUrl() . '/index.php/settings/admin/' . CloudUserConnectorService::CLOUD_USER_BACKEND;
          $settingsHint = $this->l->t('Please head over to the %1$s settings and check the generated "%2$s"-configuration.', [
            '<a class="external settings" href="' . $cloudUserBackendSettings . '" target="' . \md5($cloudUserBackendSettings) . '">' . CloudUserConnectorService::CLOUD_USER_BACKEND . '</a>',
            CloudUserConnectorService::CLOUD_USER_BACKEND,
          ]);
          return self::dataResponse([
            'messages' => [
              'transient' => $messages,
              'permanent' => [ $settingsHint, ],
            ],
          ]);
        case FontService::DEFAULT_OFFICE_FONT_CONFIG:
          if (empty($value)) {
            $this->deleteAppValue($parameter);
            /** @var FontService $fontService */
            $fontService = $this->di(FontService::class);
            $defaultFont = $fontService->getDefaultFontName();
            $value = $defaultFont;
            $transient = [ $this->l->t('Default office font reset to default "%s".', $value), ];
          } else {
            $this->setAppValue($parameter, $value);
            $transient = [ $this->l->t('Default office font set to "%s".', $value), ];
          }

          return self::dataResponse([
            'messages' => [ 'transient' => $transient, ],
            'value' => $value,
          ]);
        case self::POST_REQUEST_FONT_CACHE:
          return $this->fontCache($operation);
        default:
          break;
      }
    } catch (Throwable $t) {
      $this->logException($t);
      return self::grumble($this->exceptionChainData($t));
    }
    return self::grumble($this->l->t('Unknown Request: "%s"', $parameter));
  }

  /**
   * Grant access to wiki-namespace
   *
   * @param string $nameSpace
   *
   * @param string $group
   *
   * @return void
   */
  private function grantWikiAccess(string $nameSpace, string $group):void
  {
    $this->wikiRPC->addAcl($nameSpace.':*', '@'.$group, WikiRPC::AUTH_DELETE);
    $this->wikiRPC->addAcl('*', '@'.$group, WikiRPC::AUTH_READ);
  }

  /**
   * Revoke access to wiki-namespace
   *
   * @param string $nameSpace
   *
   * @param string $group
   *
   * @return void
   */
  private function revokeWikiAccess(string $nameSpace, string $group):void
  {
    $this->wikiRPC->delAcl('*', '@'.$group);
    $this->wikiRPC->delAcl($nameSpace.':*', '@'.$group);
  }

  /**
   * Font cache maintenance.
   *
   * @param string $operation
   *
   * @return DataResponse
   */
  private function fontCache(string $operation):DataResponse
  {
    /** @var FontService $fontService */
    $fontService = $this->di(FontService::class);
    $fonts = null;
    try {
      switch ($operation) {
        case self::FONT_CACHE_PURGE:
          $fontService->purgeFontsFolder();
          $fonts = [];
          break;
        case self::FONT_CACHE_RESCAN:
          $fontService->purgeFontsFolder();
          // fall through
        case self::FONT_CACHE_UPDATE:
          $fonts = $fontService->populateFontsFolder();
          break;
        default:
          return self::grumble($this->l->t('Unknown font cache operation: "%s".', $operation));
      }
    } catch (Throwable $t) {
      $this->logException($t);
      return self::grumble($this->l->t('Font operation "%1$s" failed with exception "%2$s".', [
        $operation, $t->getMessage(),
      ]));
    }
    if ($fonts !== null) {
      return self::dataResponse([
        'message' => $this->l->t('Font operation "%s" successful.', $operation),
        'fonts' => $fonts,
        'default' => $fontService->getDefaultFontName(),
      ]);
    }
    return self::grumble($this->l->t('Unknown internal error during font-cache operation: "%s".', $operation));
  }
}
