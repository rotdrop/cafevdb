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

use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\DataResponse;
use OCP\IRequest;
use OCP\IL10N;

use OCA\DokuWikiEmbedded\Service\AuthDokuWiki as WikiRPC;
use OCA\CAFEVDB\Service\ConfigService;

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
        $result['message'] = $this->l->t('Setting orchestra group to `%s\'. Please login as group administrator and configure the Camerata DB application.', [$realValue]);
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

        $result['message'] = $this->l->t('Setting wiki name-space to `%s\'.', [$realValue]);
        return self::dataResponse($result);
        break;
      default:
        return self::grumble($this->l->t('Unknown Request'));
      }
    } catch (\Throwable $t) {
      return self::grumble($this->exceptionChainData($t));
    }
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

}

// Local Variables: ***
// c-basic-offset: 2 ***
// indent-tabs-mode: nil ***
// End: ***
