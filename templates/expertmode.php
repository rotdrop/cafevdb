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

namespace OCA\CAFEVDB;

style($appName, 'cafevdb');
style($appName, 'tooltips');

script($appName, 'cafevdb');
script($appName, 'jquery-extensions');
script($appName, 'expertmode');

$buttons = [];
$buttons['pre'] = '<div>';
$buttons['post'] = '</div>';
$buttons['between'] = '</div><div>';
$buttons['setupdb'] =
  [ 'name' => $l->t('Provide Basic DB Layout'),
    'title' => $l->t('Make sure the data-base layout corresponds to the state of the software.'),
    'id' => 'setupdb',
    'class' => 'setupdb operations expert button' ];
$buttons['makeviews'] =
  [ 'name' => $l->t('Recreate all Views'),
    'title' => $l->t('Recreate the `Instrumentation\' hybrid-table for each project'),
    'id' => 'makeviews',
    'class' => 'makeviews operations expert button' ];
$buttons['syncevents'] =
  [ 'name' => $l->t('Synchronize Events'),
    'title' => $toolTips['syncevents'],
    'id' => 'syncevents',
    'class' => 'syncevents operations expert button' ];
$buttons['wikicontents'] =
  [ 'name' => $l->t('Recreate Wiki Project TOC'),
    'title' => $l->t('Recreate table of contents for the project pages in the wiki'),
    'id' => 'wikiprojecttoc',
    'class' => 'wikiprojecttoc operations expert button' ];
$buttons['webpages'] =
  [ 'name' => $l->t('Attach matching Web-Pages'),
    'title' => $l->t('Link all web-pages matching the project name to the respective project'),
    'id' => 'attachwebpages',
    'class' => 'attachwebpages operations expert buttont' ];
$buttons['telephone'] =
  [ 'name' => $l->t('Sanitize Phone Numbers'),
    'title' => $l->t('Perform some sanity checks on the stored telephone numbers, move the mobile numbers to their respective column, format all numbers in an ``international\'\' format.'),
    'id' => 'sanitizephones',
    'class' => 'sanitizephones operation expert button' ];
$buttons['geodata'] =
  [ 'name' => $l->t('Update Geo-Data'),
    'title' => $l->t('Update the internal cache of geographical data using some backend. We are currently only interested into ZIP codes, the respective name of the corresponding location and the country/continent name'),
    'id' => 'geodata',
    'class' => 'geodata operation expert button' ];
$buttons['uuid'] =
  [ 'name' => $l->t('Add missing UUIDs'),
    'title' => $l->t('Ensure that each musicians is assigned a UUID for vCard export and similar stuff.'),
    'id' => 'uuid',
    'class' => 'uuid operator expert button' ];
$buttons['imagemeta'] =
  [ 'name' => $l->t('Update Image Meta-Data'),
    'title' => $l->t('Update mime-type and MD5 hash for stored images.'),
    'id' => 'imagemeta',
    'class' => 'imagemeta operator expert button' ];
$buttons['example'] =
  [ 'name' => $l->t('Example'),
    'title' => $l->t('Example Do-Nothing Button'),
    'id' => 'example',
    'class' => 'example operations example button' ];

$links = [
  'phpmyadmin' => [
    'title' => $l->t('Open the login-window to the management portal for the data-base back-bone.'),
    'text' => $l->t('Database musicians/projects'),
  ],
  'phpmyadmincloud' => [
    'title' => $l->t('Open the login-window to the data-base back-bone for the Cloud WebUI.'),
    'text' => $l->t('Database Cloud'),
  ],
  'sourcecode' => [
    'text' => $l->t('Source-Code Archive'),
    'title' => $l->t('View the git-repository holding all revision of this entire mess. Mostly useful for web-developers.'),
  ],
  'sourcedocs' => [
    'text' => $l->t('Source-Code Documentation'),
    'title' => $l->t('Internal documentation of the `CAFEV-App\', mostly useful for web-developers.'),
  ],
  'clouddev' => [
    'text' => $l->t('Nextcloud Developer Documentation'),
    'title' => $l->t('Nextcloud Developer Manual, mostly useful for web-developers.'),
  ],
];

?>
<div id="expertmode">
  <div class="popup-title">
    <h2><?php p($l->t('Advanced operations, use with care')); ?></h2>
  </div>
  <div class="popup-content">
    <fieldset class="operations expert"><legend class="bold"><?php echo $l->t('Predefined data-base operations'); ?></legend>
      <?php echo $pageNavigation->buttonsFromArray($buttons); ?>
      <label for="" class="bold"><?php echo $l->t('Operation generated Response');?></label>
      <?php
      echo $pageNavigation->buttonsFromArray(
        [
          'only' => [ 'name' => $l->t('Clear Output'),
                      'id' => 'clearoutput',
                      'title' => $l->t('Remove output, if any is present.'),
                      'class' => 'clearoutput operations expert button' ],
      ]);
      ?>
      <div class="msg"><span style="opacity:0.5"><?php echo $l->t('empty') ?></span></div>
      <div class="error"><span style="opacity:0.5;display:none"><?php echo $l->t('empty') ?></span></div>
    </fieldset>
    <form>
      <fieldset class="operations expert links"><legend class="bold"><?php echo $l->t('Links'); ?></legend>
        <?php
        foreach ($links as $link => $info) {
        ?>
          <a href="<?php echo $_[$link]; ?>"
             class="button"
             target="<?php p($link.':'.$appName); ?>"
             title="<?php echo $info['title']; ?>"
          >
            <?php echo $info['text']; ?>
          </a>
          <br/>
        <?php } ?>
      </fieldset>
    </form>
  </div>
</div>
