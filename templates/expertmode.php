<?php
/* Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine
 * @copyright 2011-2016 Claus-Justus Heine <himself@claus-justus-heine.de>
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

use CAFEVDB\L;
use CAFEVDB\Config;
use CAFEVDB\Util;
use CAFEVDB\Navigation;

echo Util::emitExternalScripts();
echo Util::emitInlineScripts();

$buttons = array();
$buttons['pre'] = '<div>';
$buttons['post'] = '</div>';
$buttons['between'] = '</div><div>';
$buttons['setupdb'] =
  array('name' => L::t('Provide Basic DB Layout'),
        'title' => L::t('Make sure the data-base layout corresponds to the state of the software.'),
        'id' => 'setupdb',
        'class' => 'operations expert button');
$buttons['makeviews'] =
  array('name' => L::t('Recreate all Views'),
        'title' => L::t('Recreate the `Instrumentation\' hybrid-table for each project'),
        'id' => 'makeviews',
        'class' => 'operations expert button');
$buttons['syncevents'] =
  array('name' => L::t('Synchronize Events'),
        'title' => Config::toolTips('syncevents'),
        'id' => 'syncevents',
        'class' => 'operations expert button');
$buttons['wikicontents'] =
  array('name' => L::t('Recreate Wiki Project TOC'),
        'title' => L::t('Recreate table of contents for the project pages in the wiki'),
        'id' => 'makewikiprojecttoc',
        'class' => 'operations expert button');
$buttons['webpages'] =
  array('name' => L::t('Attach matching Web-Pages'),
        'title' => L::t('Link all web-pages matching the project name to the respective project'),
        'id' => 'attachwebpages',
        'class' => 'operations expert buttont');
$buttons['telephone'] =
  array('name' => L::t('Sanitize Phone Numbers'),
        'title' => L::t('Perform some sanity checks on the stored telephone numbers, move the mobile numbers to their respective column, format all numbers in an ``international\'\' format.'),
        'id' => 'sanitizephones',
        'class' => 'operation expert button');
$buttons['geodata'] =
  array('name' => L::t('Update Geo-Data'),
        'title' => L::t('Update the internal cache of geographical data using some backend. We are currently only interested into ZIP codes, the respective name of the corresponding location and the country/continent name'),
        'id' => 'geodata',
        'class' => 'operation expert button');
$buttons['uuid'] =
  array('name' => L::t('Add missing UUIDs'),
        'title' => L::t('Ensure that each musicians is assigned a UUID for vCard export and similar stuff.'),
        'id' => 'uuid',
        'class' => 'operator expert button');
$buttons['imagemeta'] =
  array('name' => L::t('Update Image Meta-Data'),
        'title' => L::t('Update mime-type and MD5 hash for stored images.'),
        'id' => 'imagemeta',
        'class' => 'operator expert button');
$buttons['example'] =
  array('name' => L::t('Example'),
        'title' => L::t('Example Do-Nothing Button'),
        'id' => 'example',
        'class' => 'operations example button');
?>
<div id="expertmode">
  <fieldset id="expertmode" class="operations expert"><legend><?php echo L::t('Predefined data-base operations'); ?></legend>
  <?php echo Navigation::buttonsFromArray($buttons); ?>
  <label for="" class="bold"><?php echo L::t('Operation generated Response');?></label>
<?php
  echo Navigation::buttonsFromArray(
    array('only' =>
          array('name' => L::t('Clear Output'),
                'id' => 'clearoutput',
                'title' => L::t('Remove output, if any is present.'),
                'class' => 'operations expert button')));
?>
<div class="msg"><span style="opacity:0.5"><?php echo L::t('empty') ?></span></div>
  </fieldset>
  <form method="post">
    <fieldset id="expertlinks" class="operations expert links"><legend><?php echo L::t('Links'); ?></legend>
      <input type="submit"
             value="<?php echo L::t('Database musicians/projects'); ?>"
             formaction="<?php echo $_['phpmyadmin']; ?>"
             formtarget="<?php echo Config::APP_NAME.'@phpmyadmin'; ?>"
             title="<?php echo L::t('Open the login-window to the data-base back-bone. Although this is `expert mode\' you will fall in love with the `export\' facilities of the data-base back-bone. TRY IT OUT! DO IT!'); ?>"/>
      <br/>
      <input type="submit"
             value="<?php echo L::t('Database Owncloud'); ?>"
             formaction="<?php echo $_['phpmyadminoc']; ?>"
             formtarget="Owncloud@phpmyadmin"
             title="<?php echo L::t('Open the login-window to the data-base back-bone for the Owncloud WebUI.'); ?>"/>
      <br/>
      <input type="submit"
             value="<?php echo L::t('Source-Code Archive'); ?>"
             formaction="<?php echo $_['sourcecode']; ?>"
             formtarget="GIT@<?php echo Config::APP_NAME; ?>"
             title="<?php echo L::t('View the git-repository holding all revision of this entire mess. Mostly useful for web-developers.'); ?>" />
      <br/>
      <input type="submit"
             value="<?php echo L::t('Source-Code Documentation'); ?>"
             formaction="<?php echo $_['sourcedocs']; ?>"
             formtarget="Doxygen@<?php echo Config::APP_NAME; ?>"
             title="<?php echo L::t('Internal documentation of the `CAFEV-App\', mostly useful for web-developers.'); ?>"/>
      <br/>
      <input type="submit"
             value="<?php echo L::t('Owncloud Developer Documentation'); ?>"
             formaction="<?php echo $_['ownclouddev']; ?>"
             formtarget="Doxygen@<?php echo Config::APP_NAME; ?>"
             title="<?php echo L::t('Owncloud Developer Manual, mostly useful for web-developers.'); ?>"/>
  </fieldset>
</div>
