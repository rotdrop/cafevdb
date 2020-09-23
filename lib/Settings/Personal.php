<?php
/* Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine
 * @copyright 2011-2016, 2020 Claus-Justus Heine <himself@claus-justus-heine.de>
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

namespace OCA\CAFEVDB\Settings;

use OCP\AppFramework\Http\TemplateResponse;
use OCP\Settings\ISettings;

use OCA\CAFEVDB\Service\ConfigService;
use OCA\CAFEVDB\Service\ProjectService;
use OCA\CAFEVDB\Service\DatabaseService;
use OCA\CAFEVDB\Service\ToolTipsService;

class Personal implements ISettings {
  use \OCA\CAFEVDB\Traits\ConfigTrait;
  use \OCA\CAFEVDB\Traits\WysiwygTrait;

  const ERROR_TEMPLATE = "errorpage";
  const TEMPLATE = "settings";
  const DEFAULT_EDITOR = 'tinymce';

  /** @var ProjectService */
  private $projectService;

  /** @var ToolTipsService */
  private $toolTipsService;

  public function __construct(
    ConfigService $configService,
    //ProjectService $projectService,
    ToolTipsService $toolTipsService) {
    $this->configService = $configService;
    $this->toolTipsService = $toolTipsService;
    $this->l = $this->l10N();
  }

  public function getForm() {
    if (!$this->inGroup()) {
      return new TemplateResponse(
        $this->appName(),
        self::ERROR_TEMPLATE,
        [
          'error' => 'notamember',
          'userId' => $this->userId(),
        ]);
    } else {
      // Are we a group-admin?
      $isGroupAdmin = $this->isSubAdminofGroup() && $this->encryptionKeyValid();

      $templateParameters = [
        'appName' => $this->appName(),
        'userId' => $this->userId(),
        'locale' => $this->getLocale(),
        'localeCountryNames' => $this->localeCountryNames(),
        'timezone' => $this->getTimezone(),
        'adminsettings' => $isGroupAdmin,
        'encryptionkey' => $this->getAppEncryptionKey(),
        'showToolTips' => $this->getUserValue('tooltips', 'on'),
        'debugMode' => $this->getUserValue('debug', 0),
        'pagerows' => $this->getUserValue('pagerows', 20),
        'toolTips' => $this->toolTipsService,
        'filtervisibility' => $this->getUserValue('filtervisibility', 'off'),
        'directchange' => $this->getUserValue('directchange', 'off'),
        'showdisabled' => $this->getUserValue('showdisabled', 'off'),
        'expertmode' => $this->getUserValue('expertmode', false),
        'editor' => $this->getUserValue('editor', self::DEFAULT_EDITOR),
        'wysiwygOptions' => $this->wysiwygOptions,
      ];

      if ($isGroupAdmin) {
        $this->projectService = new ProjectService($this->configService, new DatabaseService($this->configService));

        $executiveBoardTable = $this->getConfigValue('executiveBoardTable', $this->l->t('ExecutiveBoardMembers'));
        $executiveBoardTableId = $this->getConfigValue('executiveBoardTableId', -1);
        $templateParameters = array_merge(
          $templateParameters,
          [
            'streetAddressName01' => $this->getConfigValue('streetAddressName01'),
            'streetAddressName02' => $this->getConfigValue('streetAddressName02'),
            'streetAddressStreet' => $this->getConfigValue('streetAddressStreet'),
            'streetAddressHouseNumber' => $this->getConfigValue('streetAddressHouseNumber'),
            'streetAddressCity' => $this->getConfigValue('streetAddressCity'),
            'streetAddressZIP' => $this->getConfigValue('streetAddressZIP'),
            'streetAddressCountry' => $this->getConfigValue('streetAddressCountry'),

            'phoneNumber' => $this->getConfigValue('phoneNumber'),

            'bankAccountOwner' => $this->getConfigValue('bankAccountOwner'),
            'bankAccountIBAN' => $this->getConfigValue('bankAccountIBAN'),
            'bankAccountBLZ' => $this->getConfigValue('bankAccountBLZ'),
            'bankAccountBIC' => $this->getConfigValue('bankAccountBIC'),
            'bankAccountCreditorIdentifier' => $this->getConfigValue('bankAccountCreditorIdentifier'),

            'memberTable' => $this->getConfigValue('memberTable', $this->l->t('ClubMembers')),
            'memberTableId' => $this->getConfigValue('memberTableId', -1),
            'executiveBoardTable' => $executiveBoardTable,
            'executiveBoardTableId' => $execitiveBoardTableId,
            'executiveBoardMembers' => $this->projectService->participantOptions($executiveBoardTableId, $executiveBoardTable),
            'userGroupMembers' => array_map(function($user) { return $user->getUID(); }, $this->group()->getUsers()),
            'userGroups' => array_map(function($group) { return $group->getGID(); }, $this->groupManager()->search('')),
            'orchestra' => $this->getConfigValue('orchestra'),

            'dbserver' => $this->getConfigValue('dbserver'),
            'dbname' => $this->getConfigValue('dbname'),
            'dbuser' => $this->getConfigValue('dbuser'),
            'dbpassword' => $this->getConfigValue('dbpassword'),
            'encryptionkey' => $this->getConfigValue('encryptionkey'),

            'shareowner', $this->getConfigValue('shareowner', ''),
            'concertscalendar', $this->getConfigValue('concertscalendar', $this->l->t('concerts')),
            'rehearsalscalendar', $this->getConfigValue('rehearsalscalendar', $this->l->t('rehearsals')),
            'othercalendar', $this->getConfigValue('othercalendar', $this->l->t('other')),
            'managementcalendar', $this->getConfigValue('managementcalendar', $this->l->t('management')),
            'financecalendar', $this->getConfigValue('financecalendar', $this->l->t('finance')),
            'eventduration', $this->getConfigValue('eventduration', '180'),

            'sharedaddressbook', $this->getConfigValue('sharedaddressbook', $this->l->t('contacts')),

            'sharedfolder', $this->getConfigValue('sharedfolder',''),
            'projectsfolder', $this->getConfigValue('projectsfolder',''),
            'projectsbalancefolder', $this->getConfigValue('projectsbalancefolder',''),
          ]);

        // musician ids of the officials
        foreach (['president', 'secretary', 'treasurer'] as $prefix) {
          foreach (['Id', 'UserId', 'GroupId'] as $postfix) {
            $official = $prefix.$postfix;
            $templateParameters[$official] = $this->getConfigValue($official, -1);
          }
        }

        foreach (['smtp', 'imap'] as $proto) {
          foreach (['server', 'port', 'secure'] as $key) {
            $templateParameters[$proto.$key] =  $this->getConfigValue($proto.$key);
          }
        }
        foreach (['user', 'password', 'fromname', 'fromaddress', 'testaddress', 'testmode'] as $key) {
          $templateParameters['email'.$key] = $this->getConfigValue('email'.$key);
        }

        foreach (['Preview',
                  'Archive',
                  'Rehearsals',
                  'Trashbin',
                  'Template',
                  'ConcertModule',
                  'RehearsalsModule'] as $key) {
          $templateParameters['redaxo'.$key] = $this->getConfigValue('redaxo'.$key);
        }

        foreach (['phpmyadmin',
                  'phpmyadminoc',
                  'sourcecode',
                  'sourcedocs',
                  'ownclouddev'] as $link) {
          $templateParamerers[$link] = $this->getConfigValue($link);
        }
      }

      return new TemplateResponse(
        $this->appName(),
        self::TEMPLATE,
        $templateParameters,
      );
    }
  }

  /**
   * @return stribng the section ID, e.g. 'sharing'
   * @since 9.1
   */
  public function getSection() {
    return $this->appName();
  }

  /**
   * @return int whether the form should be rather on the top or bottom of
   * the admin section. The forms are arranged in ascending order of the
   * priority values. It is required to return a value between 0 and 100.
   *
   * E.g.: 70
   * @since 9.1
   */
  public function getPriority() {
    // @@TODO could be made a configure option.
    return 50;
  }
}

// Local Variables: ***
// c-basic-offset: 2 ***
// indent-tabs-mode: nil ***
// End: ***
