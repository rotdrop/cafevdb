<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine
 * @copyright 2020, 2021, 2022 Claus-Justus Heine <himself@claus-justus-heine.de>
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

use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\DataResponse;
use OCP\IRequest;
use OCP\IL10N;

use OCA\DokuWikiEmbedded\Service\AuthDokuWiki as WikiRPC;
use OCA\CAFEVDB\Service\ConfigService;
use OCA\CAFEVDB\Service\CloudUserConnectorService;
use OCA\CAFEVDB\Service\RequestService;

class AdminSettingsController extends Controller {
  use \OCA\CAFEVDB\Traits\ConfigTrait;
  use \OCA\CAFEVDB\Traits\ResponseTrait;

  /** @var OCA\DokuWikiEmedded\Service\AuthDokuWiki */
  private $wikiRPC;

  public function __construct(
    $appName
    , IRequest $request
    , ConfigService $configService
    , WikiRPC $wikiRPC
  ) {
    parent::__construct($appName, $request);

    $this->configService = $configService;
    $this->wikiRPC = $wikiRPC;
    $this->wikiRPC->errorReporting(WikiRPC::ON_ERROR_THROW);
    $this->l = $this->l10N();
  }

  /**
   * @NoGroupMemberRequired
   */
  public function set($parameter, $value) {
    $wikiNameSpace = $this->getAppValue('wikinamespace');
    $orchestraUserGroup = $this->getAppValue('usergroup');
    try {
      switch ($parameter) {
        case 'orchestraUserGroup':
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
          $result['message'] = $this->l->t('Setting orchestra group to "%s". Please login as group administrator and configure the Camerata DB application.', [$realValue]);
          return self::dataResponse($result);
          break;
        case 'wikiNameSpace':
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

          $result['message'] = $this->l->t('Setting wiki name-space to "%s".', [$realValue]);
          return self::dataResponse($result);
          break;
        case 'cloudUserBackendConf':
          $messages = [];
          $responses = $this->configureCloudUserBackend();
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
          $messages[] = $settingsHint;
          return self::dataResponse([ 'message' => $messages ]);
          break;
        default:
          break;
      }
    } catch (\Throwable $t) {
      return self::grumble($this->exceptionChainData($t));
    }
    return self::grumble($this->l->t('Unknown Request: "%s"', $parameter));
  }

  /**
   * Grant access to wiki-namespace
   */
  private function grantWikiAccess($nameSpace, $group)
  {
    $this->wikiRPC->addAcl($nameSpace.':*', '@'.$group, WikiRPC::AUTH_DELETE);
    $this->wikiRPC->addAcl('*', '@'.$group, WikiRPC::AUTH_READ);
  }

  /**
   * Revoke access to wiki-namespace
   */
  private function revokeWikiAccess($nameSpace, $group)
  {
    $this->wikiRPC->delAcl('*', '@'.$group);
    $this->wikiRPC->delAcl($nameSpace.':*', '@'.$group);
  }

  /**
   * Hijack the user-sql backend by flushing pre-computed values into its
   * config-space.
   */
  private function configureCloudUserBackend()
  {
    $cloudUserBackend = CloudUserConnectorService::CLOUD_USER_BACKEND;

    $cloudConfig = $this->cloudConfig();
    $configKeys = $cloudConfig->getAppKeys($this->appName);
    $prefix = $cloudUserBackend . ':';
    $prefixLen = strlen($prefix);
    $cloudUserBackendKeys = array_map(function($key) use ($prefixLen) {
      return substr($key, $prefixLen);
    }, array_filter($configKeys, function($key) use ($prefix) {
      return str_starts_with($key, $prefix);
    }));

    $this->logDebug('USER SQL KEYS ' . print_r($cloudUserBackendKeys, true));

    $cloudUserBackendParams = [];
    foreach ($cloudUserBackendKeys as $cloudUserBackendKey) {
      $cloudUserBackendValue = $cloudConfig->getAppValue($this->appName, $prefix . $cloudUserBackendKey);
      if (preg_match('/%system:(\w+)%/', $cloudUserBackendValue, $matches)) {
        $cloudUserBackendValue = $cloudConfig->getSystemValue($matches[1]);
      }
      // $cloudConfig->setAppValue($cloudUserBackend, $cloudUserBackendKey, $cloudUserBackendValue);
      $cloudUserBackendParams[str_replace('.', '-', $cloudUserBackendKey)] = $cloudUserBackendValue;
    }
    $this->logDebug('USER SQL POST PARAMS ' . print_r($cloudUserBackendParams, true));

    $messages = [];

    /** @var RequestService $requestService */
    $requestService = $this->di(RequestService::class);

    // try also to clear the cache after and before changing the configuration
    $this->clearUserBackendCache($requestService, $cloudUserBackend, $messages);

    $route = implode('.', [
      $cloudUserBackend,
      'settings',
      'saveProperties',
    ]);
    $result = $requestService->postToRoute($route, postData: $cloudUserBackendParams, type: RequestService::URL_ENCODED);
    $messages[] = $result['message']??$this->l->t('"%s" configuration may have succeeded.', $cloudUserBackend);

    // try also to clear the cache after and before changing the configuration
    $this->clearUserBackendCache($requestService, $cloudUserBackend, $messages);

    return $messages;
  }

  private function clearUserBackendCache(RequestService $requestService, string $cloudUserBackend, array &$messages)
  {
    $route = implode('.', [
      $cloudUserBackend,
      'settings',
      'clearCache',
    ]);
    try {
      $result = $requestService->postToRoute($route);
      $messages[] = $result['message']??$this->l->t('Clearing "%s"\'s cache may have succeeded.', $cloudUserBackend);
    } catch (\Throwable $t) {
      // essentially ignore ...
      $this->logError($t);
      $messages[] = $this->l->t('An attempt to clear the cache of the "%1$s"-app has failed: %2$s.', [
        $cloudUserBackend,
        $t->getMessage(),
      ]);
    }
  }
}

// Local Variables: ***
// c-basic-offset: 2 ***
// indent-tabs-mode: nil ***
// End: ***
