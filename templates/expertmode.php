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

use OCA\CAFEVDB\Common\Navigation;

style($appName, 'cafevdb');
style($appName, 'tooltips');

script($appName, 'cafevdb');
script($appName, 'jquery-extensions');
script($appName, 'expertmode');

$buttons = array();
$buttons['pre'] = '<div>';
$buttons['post'] = '</div>';
$buttons['between'] = '</div><div>';
$buttons['setupdb'] =
  array('name' => $l->t('Provide Basic DB Layout'),
        'title' => $l->t('Make sure the data-base layout corresponds to the state of the software.'),
        'id' => 'setupdb',
        'class' => 'setupdb operations expert button');
$buttons['makeviews'] =
  array('name' => $l->t('Recreate all Views'),
        'title' => $l->t('Recreate the `Instrumentation\' hybrid-table for each project'),
        'id' => 'makeviews',
        'class' => 'makeviews operations expert button');
$buttons['syncevents'] =
  array('name' => $l->t('Synchronize Events'),
        'title' => $toolTips['syncevents'],
        'id' => 'syncevents',
        'class' => 'syncevents operations expert button');
$buttons['wikicontents'] =
  array('name' => $l->t('Recreate Wiki Project TOC'),
        'title' => $l->t('Recreate table of contents for the project pages in the wiki'),
        'id' => 'wikiprojecttoc',
        'class' => 'wikiprojecttoc operations expert button');
$buttons['webpages'] =
  array('name' => $l->t('Attach matching Web-Pages'),
        'title' => $l->t('Link all web-pages matching the project name to the respective project'),
        'id' => 'attachwebpages',
        'class' => 'attachwebpages operations expert buttont');
$buttons['telephone'] =
  array('name' => $l->t('Sanitize Phone Numbers'),
        'title' => $l->t('Perform some sanity checks on the stored telephone numbers, move the mobile numbers to their respective column, format all numbers in an ``international\'\' format.'),
        'id' => 'sanitizephones',
        'class' => 'sanitizephones operation expert button');
$buttons['geodata'] =
  array('name' => $l->t('Update Geo-Data'),
        'title' => $l->t('Update the internal cache of geographical data using some backend. We are currently only interested into ZIP codes, the respective name of the corresponding location and the country/continent name'),
        'id' => 'geodata',
        'class' => 'geodata operation expert button');
$buttons['uuid'] =
  array('name' => $l->t('Add missing UUIDs'),
        'title' => $l->t('Ensure that each musicians is assigned a UUID for vCard export and similar stuff.'),
        'id' => 'uuid',
        'class' => 'uuid operator expert button');
$buttons['imagemeta'] =
  array('name' => $l->t('Update Image Meta-Data'),
        'title' => $l->t('Update mime-type and MD5 hash for stored images.'),
        'id' => 'imagemeta',
        'class' => 'imagemeta operator expert button');
$buttons['example'] =
  array('name' => $l->t('Example'),
        'title' => $l->t('Example Do-Nothing Button'),
        'id' => 'example',
        'class' => 'example operations example button');
?>
<div id="expertmode">
  <h2 class="popup-title"><?php p($l->t('Advanced operations, use with care')); ?></h2>
  <fieldset id="expertmode" class="operations expert"><legend><?php echo $l->t('Predefined data-base operations'); ?></legend>
  <?php echo Navigation::buttonsFromArray($buttons); ?>
  <label for="" class="bold"><?php echo $l->t('Operation generated Response');?></label>
<?php
  echo Navigation::buttonsFromArray(
    array('only' =>
          array('name' => $l->t('Clear Output'),
                'id' => 'clearoutput',
                'title' => $l->t('Remove output, if any is present.'),
                'class' => 'clearoutput operations expert button')));
?>
<div class="msg"><span style="opacity:0.5"><?php echo $l->t('empty') ?></span></div>
  </fieldset>
  <form method="post">
    <fieldset id="expertlinks" class="operations expert links"><legend><?php echo $l->t('Links'); ?></legend>
      <input type="submit"
             value="<?php echo $l->t('Database musicians/projects'); ?>"
             formaction="<?php echo $_['phpmyadmin']; ?>"
             formtarget="<?php echo $appName.'@phpmyadmin'; ?>"
             title="<?php echo $l->t('Open the login-window to the data-base back-bone. Although this is `expert mode\' you will fall in love with the `export\' facilities of the data-base back-bone. TRY IT OUT! DO IT!'); ?>"/>
      <br/>
      <input type="submit"
             value="<?php echo $l->t('Database Nextcloud'); ?>"
             formaction="<?php echo $_['phpmyadminoc']; ?>"
             formtarget="Nextcloud@phpmyadmin"
             title="<?php echo $l->t('Open the login-window to the data-base back-bone for the Nextcloud WebUI.'); ?>"/>
      <br/>
      <input type="submit"
             value="<?php echo $l->t('Source-Code Archive'); ?>"
             formaction="<?php echo $_['sourcecode']; ?>"
             formtarget="GIT@<?php echo $appName; ?>"
             title="<?php echo $l->t('View the git-repository holding all revision of this entire mess. Mostly useful for web-developers.'); ?>" />
      <br/>
      <input type="submit"
             value="<?php echo $l->t('Source-Code Documentation'); ?>"
             formaction="<?php echo $_['sourcedocs']; ?>"
             formtarget="Doxygen@<?php echo $appName; ?>"
             title="<?php echo $l->t('Internal documentation of the `CAFEV-App\', mostly useful for web-developers.'); ?>"/>
      <br/>
      <input type="submit"
             value="<?php echo $l->t('Nextcloud Developer Documentation'); ?>"
             formaction="<?php echo $_['nextclouddev']; ?>"
             formtarget="Doxygen@<?php echo $appName; ?>"
             title="<?php echo $l->t('Nextcloud Developer Manual, mostly useful for web-developers.'); ?>"/>
  </fieldset>
</div>
