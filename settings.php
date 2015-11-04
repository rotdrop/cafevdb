<?php
/* Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine
 * @copyright 2011-2014 Claus-Justus Heine <himself@claus-justus-heine.de>
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

namespace CAFEVDB {

  // Check if we are a user and the needed apps are enabled.
  \OCP\User::checkLoggedIn();
  \OCP\JSON::checkAppEnabled('cafevdb');
  \OCP\JSON::checkAppEnabled('calendar');

  Config::init();

  $group = Config::getAppValue('usergroup', '');
  $user  = \OCP\USER::getUser();

  if (!\OC_Group::inGroup($user, $group)) {
    $tmpl = new \OCP\Template( 'cafevdb', 'errorpage', 'user' );
    $tmpl->assign('error', 'notamember');
    return $tmpl->printPage();
  }

  try {

    Error::exceptions(true);

    $tmpl = new \OCP\Template( 'cafevdb', 'settings');

    $tooltips         = Config::getUserValue('tooltips', 'on', $user);
    $filtervisibility = Config::getUserValue('filtervisibility', 'off', $user);

    $pagerows    = Config::getUserValue('pagerows', 20, $user);
    $editor      = Config::getUserValue('wysiwygEditor', 'tinymce', $user);
    $expertmode  = Config::getUserValue('expertmode', '', $user);
    $encrkey     = Config::getEncryptionKey();

    $tmpl->assign('pagerows', $pagerows);
    $tmpl->assign('expertmode', $expertmode);
    $tmpl->assign('tooltips', $tooltips);
    $tmpl->assign('editor', $editor);
    $tmpl->assign('filtervisibility', $filtervisibility);
    $tmpl->assign('encryptionkey', $encrkey);
    $tmpl->assign('adminsettings', false);

    \OCP\Util::addStyle('cafevdb', 'cafevdb');
    \OCP\Util::addStyle('cafevdb', 'tooltips');
    \OCP\Util::addStyle('cafevdb', 'settings');

    \OCP\Util::addStyle("cafevdb/3rdparty", "chosen/chosen");

    if (Config::encryptionKeyValid() &&
        ($cafevgroup = Config::getAppValue('usergroup', '')) != '' &&
        \OC_SubAdmin::isGroupAccessible($user, $cafevgroup)) {

      $admin = true;
      $tmpl->assign('adminsettings', $admin);

      $tmpl->assign('streetAddressName01', Config::getValue('streetAddressName01'));
      $tmpl->assign('streetAddressName02', Config::getValue('streetAddressName02'));
      $tmpl->assign('streetAddressStreet', Config::getValue('streetAddressStreet'));
      $tmpl->assign('streetAddressHouseNumber', Config::getValue('streetAddressHouseNumber'));
      $tmpl->assign('streetAddressCity', Config::getValue('streetAddressCity'));
      $tmpl->assign('streetAddressZIP', Config::getValue('streetAddressZIP'));
      $tmpl->assign('streetAddressCountry', Config::getValue('streetAddressCountry'));

      $tmpl->assign('phoneNumber', Config::getValue('phoneNumber'));

      $tmpl->assign('bankAccountOwner', Config::getValue('bankAccountOwner'));
      $tmpl->assign('bankAccountIBAN', Config::getValue('bankAccountIBAN'));
      $tmpl->assign('bankAccountBLZ', Config::getValue('bankAccountBLZ'));
      $tmpl->assign('bankAccountBIC', Config::getValue('bankAccountBIC'));
      $tmpl->assign('bankAccountCreditorIdentifier', Config::getValue('bankAccountCreditorIdentifier'));

      $tmpl->assign('memberTable',
                    Config::getSetting('memberTable', L::t('ClubMembers')));
      $tmpl->assign('executiveBoardTable',
                    Config::getSetting('executiveBoardTable', L::t('ExecutiveBoardMembers')));
      $tmpl->assign('memberTableId',
                    Config::getSetting('memberTableId', -1));
      $tmpl->assign('executiveBoardTableId',
                    Config::getSetting('executiveBoardTableId', -1));

      $tmpl->assign('orchestra', Config::getValue('orchestra'));

      // musician ids of the officials
      $tmpl->assign('presidentId', Config::getSetting('presidentId', -1));
      $tmpl->assign('secretaryId', Config::getSetting('secretaryId', -1));
      $tmpl->assign('treasurerId', Config::getSetting('treasurerId', -1));

      $tmpl->assign('dbserver', Config::getValue('dbserver'));
      $tmpl->assign('dbname', Config::getValue('dbname'));
      $tmpl->assign('dbuser', Config::getValue('dbuser'));
      $tmpl->assign('dbpassword', Config::getValue('dbpassword'));
      $tmpl->assign('encryptionkey', Config::getValue('encryptionkey'));

      $tmpl->assign('shareowner', Config::getSetting('shareowner', ''));
      $tmpl->assign('concertscalendar', Config::getSetting('concertscalendar', L::t('concerts')));
      $tmpl->assign('rehearsalscalendar', Config::getSetting('rehearsalscalendar', L::t('rehearsals')));
      $tmpl->assign('othercalendar', Config::getSetting('othercalendar', L::t('other')));
      $tmpl->assign('managementcalendar', Config::getSetting('managementcalendar', L::t('management')));
      $tmpl->assign('financecalendar', Config::getSetting('financecalendar', L::t('finance')));
      $tmpl->assign('eventduration', Config::getSetting('eventduration', '180'));

      $tmpl->assign('sharedaddressbook', Config::getSetting('sharedaddressbook', L::t('contacts')));

      $tmpl->assign('sharedfolder', Config::getSetting('sharedfolder',''));
      $tmpl->assign('projectsfolder', Config::getSetting('projectsfolder',''));
      $tmpl->assign('projectsbalancefolder', Config::getSetting('projectsbalancefolder',''));

      foreach (array('smtp', 'imap') as $proto) {
        foreach (array('server', 'port', 'secure') as $key) {
          $tmpl->assign($proto.$key, Config::getValue($proto.$key));
        }
      }
      foreach (array('user', 'password', 'fromname', 'fromaddress', 'testaddress', 'testmode') as $key) {
        $tmpl->assign('email'.$key, Config::getValue('email'.$key));
      }

      foreach (array('Preview',
                     'Archive',
                     'Rehearsals',
                     'Trashbin',
                     'Template',
                     'ConcertModule',
                     'RehearsalsModule') as $key) {
        $tmpl->assign('redaxo'.$key, Config::getValue('redaxo'.$key));
      }

      $links = array('phpmyadmin',
                     'phpmyadminoc',
                     'sourcecode',
                     'sourcedocs',
                     'ownclouddev');
      foreach ($links as $link) {
        $tmpl->assign($link, Config::getValue($link));
      }
    }

    $result = $tmpl->printPage();

    return $result;

  } catch (Exception $e) {
    $tmpl = new \OCP\Template( 'cafevdb', 'errorpage', 'user' );
    $tmpl->assign('error', 'exception');
    $tmpl->assign('exception', $e->getMessage());
    $tmpl->assign('trace', $e->getTraceAsString());
    $tmpl->assign('debug', true);
    $admin =
      \OCP\User::getDisplayName('admin').
      ' <'.\OCP\Config::getUserValue('admin', 'settings', 'email').'>';
    $tmpl->assign('admin', Util::htmlEncode($admin));
    return $tmpl->printPage();
  }

} // namespsace CAFEVDB

?>
