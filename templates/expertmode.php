<?php
/**Orchestra member, musician and project management application.
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
$buttons['syncevents'] =
  array('name' => L::t('Synchronize Events'),
        'title' => Config::toolTips('syncevents'),
        'id' => 'syncevents',
        'class' => 'operations expert button');
$buttons['makeviews'] =
  array('name' => L::t('Recreate all Views'),
        'title' => L::t('Recreate the `Detailed Instrumentation\' hybrid-table for each project'),
        'id' => 'makeviews',
        'class' => 'operations expert button');
$buttons['check'] =
  array('name' => L::t('Check Instruments'),
        'title' => L::t('Check whether the instrumentation numbers table and the musicians table mention the same instruments'),
        'id' => 'checkinstruments',
        'class' => 'operations expert button');
$buttons['sanitize'] =
  array('name' => L::t('Adjust Instruments'),
        'title' => L::t('Make sure the instruments table contains at least any instrument played by any musician.'),
        'id' => 'adjustinstruments',
        'class' => 'operations expert button');
$buttons['example'] =
  array('name' => L::t('Example'),
        'title' => L::t('Example Do-Nothing Button'),
        'id' => 'example',
        'class' => 'operations example button');
?>
<div id="expertmode">
  <fieldset id="expertmode" class="operations expert"><legend><?php echo L::t('Predefined data-base operations'); ?></legend>
  <?php echo Navigation::button($buttons); ?>
  <label for="" class="bold"><?php echo L::t('Operation generated Response');?></label>
<?php
  echo Navigation::button(
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
