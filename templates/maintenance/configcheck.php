<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine
 * @copyright 2011-2014, 2020, 2021 Claus-Justus Heine <himself@claus-justus-heine.de>
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

namespace OCA\CAFEVDB;

$css_pfx = 'cafevdb-page'; //@@TODO ???
$css_class = 'config-check';

//style($appName, 'config-check');
//script($appName, 'events-test');

$nav = '';
$nav .= $pageNavigation->pageControlElement('all');
$nav .= $pageNavigation->pageControlElement('projects');
$nav .= $pageNavigation->pageControlElement('instruments');
$nav .= $pageNavigation->pageControlElement('project-participant-fields');
$nav .= $pageNavigation->pageControlElement('project-instrumentation-numbers');
$nav .= $pageNavigation->pageControlElement('blog');

$header = ''
  .'<div class="'.$css_pfx.'-config-check" id="'.$css_pfx.'-config-check-header">
  '.$l->t('It may be that you simply have to log-off and log-in again because your login-session has timed out. Otherwise:')
   .'<p>'
   .$l->t('Several basic configuraton options are missing. Please follow the
instructions below. If this is a new installation then you will
probably also have to adjust several other app-settings. The settings
can be accessed through the configuration menu in the top-right corner.
You need to have the role of a group-administrator to do.')
  .'</div>
';

echo $this->inc('part.common.header', [
  'css-prefix' => $css_pfx,
  'css-class' => $css_class,
  'navigationcontrols' => $nav,
  'header' => $header,
]);

$cfgchk = $_['configcheck'];

$missingtext = [
  'orchestra' => $l->t(
    'You need to specify a name for the orchestra.  Please access the
app-settings through the settings-menu in the upper right corner
and specify a short-hand name for the orchestra in the
"Administration"-tab. This is just a tag to provide defaults for
user-ids and folders; it should be a short one-word identifier.'),
  'usergroup' => $l->t('You need to create a dedicatd user group.
You have to log-in as administrator to do so.'),
  'shareowner' => $l->t(
    'You need to create a dummy-user which owns all shared resources
(calendars, files etc.). You need to be a group-admin for the
orchestra user group "%s" to do so. You can create the share-owner
uid by setting the respective field in the application settings menu in the
"Sharing"-tab).',
                        [$_['usergroup']]),
  'sharedfolder' => $l->t(
    'You need to create a dedicated shared folder shared among the
user-group "%s". You can do so through the respective web-form in the
application settings windows accessible through the settings-menu in the
top-right corner. Choose the "Sharing"-tab in
the settings-window. You need to be a group-admin, otherwise the
application settings are not visible for you.',
                          [$_['usergroup']]),
  'sharedaddressbooks' => $l->t('Shared addressbooks do not exist or are inaccessible.'),
  'database' => $l->t('You need to configure the database access. You can do so through the
respective web-form in the application settings windows accessible
through the settings-menu in the upper right corner. You need to be a
group-admin, otherwise the application settings are not visible for
you.',
                      [$_['usergroup']]),
  'encryptionkey' => $l->t(
    'You may want to set an encryption key for encrypting configuration
values and (in the future) sensitive data in the members- and project
database.  You can do so through the respective web-form in the
application settings windows accessible through the settings-menu in the
upper right corner. You need to be a group-admin, otherwise the
application settings are not visible for you. Note also that after
installing a non-empty encryption key each user has to log-out and
log-in again in order to be able to access the encrypted values.',
                           [$_['usergroup']]),
  ];

?>

<div id="<?php echo $css_pfx; ?>-body-config-check">
  <form id="configrecheckform" action="?app=cafevdb" method="get">
    <input type="hidden" name="app" value="<?php echo $appName; ?>" /><!-- @@TODO should not be needed -->
    <input
      type="submit"
      title="<?php echo $toolTips['configrecheck'];?>"
      value="<?php echo $l->t('Test again'); ?>"
      id="configrecheck"
    />
    <!-- <a href="#" class="new-event button">NewEventDialog</a>
         <a href="#"
         class="edit-event button"
         data-uri="E59CC606-AB43-443F-ACC8-3EA742ADD672.ics"
         data-calendar-id="30">EditEventDialog</a>
         <input id="edit-event-test-uri" type="text" name="uri" placeholder="uri"/>
         <input id="edit-event-test-calendar-id" type="number" name="calendar-id" placeholder="calendar-id"/>
         <a href="#" class="geo-coding button">TestGeoCodingCache</a>
    <br/>
    <br/>
    <div class="<?php echo $css_pfx; ?>-playground">
      <a href="#" class="progress-status button">TestProgressStatus</a>
      <span id="progress-status-info"></span>
      <a href="#" class="pdfletter-download button">TestPdfLetter</a>
    </div> -->
  </form>
  <br/>
  <!-- <br/>
       <pre><?php echo $_SERVER['PHP_SELF']; ?></pre>
       <pre>Image: <?php echo $urlGenerator->imagePath('cafevdb', ''); ?></pre>
       <pre>File: <?php echo $urlGenerator->linkTo('cafevdb', ''); ?></pre>
       <pre>Route: <?php echo $urlGenerator->linkToRoute('cafevdb.page.index'); ?></pre> -->
  <ul>

<?php

$diagnosticItems = [
  'usergroup',
  'groupadmin',
  'encryptionkey',
  'orchestra',
  'shareowner',
  'sharedfolder',
  'sharedaddressbooks',
  'database',
  'migrations',
];

$failedItems = [];
$operationalItems = [];
foreach ($diagnosticItems as $key) {
  if (isset($cfgchk[$key]['status']) && $cfgchk[$key]['status'] === false) {
    $failedItems[] = $key;
  } else {
    $operationalItems[] = $key;
  }
}
$diagnosticItems = $failedItems + $operationalItems;

foreach ($diagnosticItems as $key) {

  switch ($key) {
    case 'groupadmin':
      $ok    = $_['groupadmin'] ? 'set' : 'missing';
      $key   = 'groupadmin';
      $value = ($_['groupadmin']
              ? $l->t('You are a group administrator.')
              : $l->t('You are not a group administrator.'));
      $text = (!$_['groupadmin']
             ? $l->t('Ask a user with group-administrator rights to perform the required
settings or ask the Owncloud-administror to assign to you the rol of a
group-administrator for the group `%s\'.',
                     array($_['usergroup']))
             : '');

      echo '    <li class="'.$css_pfx.'-config-check '.$ok.'">
      <span class="'.$css_pfx.'-config-check key"> '.$key.'</span>
      <span class="'.$css_pfx.'-config-check value"> '.$value.'</span>
      <div class="'.$css_pfx.'-config-check comment"> '.$text.'</div>
    </li>';
      break;
    case 'encryptionkey':
      $key = 'encryptionkey';
      $encrkey = $encryptionkey;
      $encrkeyhash = $encryptionkeyhash;
      $error   = $cfgchk[$key]['message'];
      if ($error != '') {
            $text .= '<p>'.$l->t('Additional diagnostic message:').'<br/>'.'<div class="errormessage">'.nl2br($error).'</div>';
      }

      if (!empty($encrkey)) {
        $ok    = 'set';
        $tok   = $l->t('is set');
        $value = 'XXXXXXXX'; // $encrkey;
        $text  = '';
      } else if (!empty($encrkeyhash)) {
        $ok    = 'missing';
        $tok   = $appconfigkey . ' / ' . $userconfigkey . ' / ' . $l->t('is set, but inaccessible');
        $value = '';
        $text  = $missingtext[$key];
      } else {
        $ok    = 'missing';
        $tok   = $l->t('is not set');
        $value = '';
        $text  = $missingtext[$key];
      }

      echo '    <li class="'.$css_pfx.'-config-check '.$ok.'">
      <span class="'.$css_pfx.'-config-check key"> '.$key.'</span>
      <span class="'.$css_pfx.'-config-check value"> '.$value.'</span>
      <span class="'.$css_pfx.'-config-check '.$ok.'"> '.$tok.'</span>
      <div class="'.$css_pfx.'-config-check comment"> '.$text.'</div>
    </li>';
      break;
    case 'migrations':
      $status = $cfgchk[$key]['status'];
      $ok     = $status ? 'set' : 'missing';
      $tok    = $status ? $l->t('not needed') : $l->t('data needs migration');
      $text   = $status ? '' : ($missingtext[$key]??'');
      $error  = $cfgchk[$key]['message'];
      if ($error != '') {
        $text .= '<p>'.$l->t('Additional diagnostic message:').'<br/>'.'<div class="errormessage">'.nl2br($error).'</div>';
      }

      echo '    <li class="'.$css_pfx.'-config-check '.$ok.'">
      <span class="'.$css_pfx.'-config-check key"> '.$key.'</span>
      <span class="'.$css_pfx.'-config-check value"> '.($_[$key]??'').'</span>
      <span class="'.$css_pfx.'-config-check '.$ok.'"> '.$tok.'</span>
      <div class="'.$css_pfx.'-config-check comment"> '.$text.'</div>
    </li>';
      break;
    default:
      $status = $cfgchk[$key]['status'];
      $ok     = $status ? 'set' : 'missing';
      $tok    = $status ? $l->t('is set') : $l->t('is missing');
      $text   = $status ? '' : $missingtext[$key];
      $error  = $cfgchk[$key]['message']??'';
      if ($error != '') {
        $text .= '<p>'.$l->t('Additional diagnostic message:').'<br/>'.'<div class="errormessage">'.nl2br($error).'</div>';
      }

      echo '    <li class="'.$css_pfx.'-config-check '.$ok.'">
      <span class="'.$css_pfx.'-config-check key"> '.$key.'</span>
      <span class="'.$css_pfx.'-config-check value"> '.($_[$key]??'').'</span>
      <span class="'.$css_pfx.'-config-check '.$ok.'"> '.$tok.'</span>
      <div class="'.$css_pfx.'-config-check comment"> '.$text.'</div>
    </li>';
      break;
  }
}

?>
  </ul>
</div>

<?php
// Close some still opened divs
echo $this->inc('part.common.footer', array('css-prefix' => $css_pfx));

?>
