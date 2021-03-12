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

function linkToolTip(string $tag, string $value)
{
  return empty($value) ? $toolTips[$tag] : $value;
}

?>
<div id="tabs-<?php echo $tabNr; ?>" class="personalblock admin devel">
  <form id="develsettings">
    <fieldset id="devlinks"><legend><?php echo $l->t('Links');?></legend>
      <input type="button"
             class="devlinktest"
             id="testphpmyadmin"
             name="testphpmyadmin"
             value="<?php echo $l->t('Test Link'); ?>"
             title="<?php echo $toolTips['test-linktarget']; ?>"  />
      <input type="text"
             class="devlink"
             id="phpmyadmin"
             name="phpmyadmin"
             placeholder="<?php echo $appName.'@phpmyadmin'; ?>"
             value="<?php echo $phpmyadmin; ?>"
             title="<?php p(linkToolTip('phpmyadmin-link', $phpmyadmin)); ?>" />
      <label for="phpmyadmin"><?php echo $l->t('Link to %s', 'phpMyAdmin'); ?></label>
      <br/>
      <input type="button"
             class="devlinktest"
             id="testphpmyadmincloud"
             name="testphpmyadmincloud"
             value="<?php echo $l->t('Test Link'); ?>"
             title="<?php echo $toolTips['test-linktarget']; ?>" />
      <input type="text" class="devlink"
             id="phpmyadmincloud"
             name="phpmyadmincloud"
             placeholder="Owncloud@phpMyAdmin"
             value="<?php echo $phpmyadmincloud; ?>"
             title="<?php p(linkToolTip('phpmyadmincloud-link', $phpmyadmincloud)); ?>" />
      <label for="phpmyadmincloud"><?php echo $l->t('Link to Cloud@%s', array('phpMyAdmin')); ?></label>
      <br/>
      <input type="button"
             class="devlinktest"
             id="testsourcecode"
             name="testsourcecode"
             value="<?php echo $l->t('Test Link'); ?>"
             title="<?php echo $toolTips['test-linktarget']; ?>" />
      <input type="text" class="devlink"
             id="sourcecode"
             name="sourcecode"
             placeholder="<?php echo $l->t('Link to the source-code'); ?>"
             value="<?php echo $sourcecode; ?>"
             title="<?php p(linkToolTip('sourcecode-link', $sourcecode)); ?>" />
      <label for="phpmyadmin"><?php echo $l->t('Link to the source-code'); ?></label>
      <br/>
      <input type="button"
             class="devlinktest"
             id="testsourcedocs"
             name="testsourcedocs"
             value="<?php echo $l->t('Test Link'); ?>"
             title="<?php echo $toolTips['test-linktarget']; ?>"  />
      <input type="text"
             class="devlink"
             id="sourcedocs"
             name="sourcedocs"
             placeholder="<?php echo $l->t('Link to the source-code documentation'); ?>"
             value="<?php echo $sourcedocs; ?>"
             title="<?php p(linkToolTip('sourcedocs-link', $sourcedocs)); ?>"/>
      <label for="phpmyadmin"><?php echo $l->t('Link to the source-code documentation'); ?></label>
      <br/>
      <input type="button"
             class="devlinktest"
             id="testclouddev"
             name="testclouddev"
             value="<?php echo $l->t('Test Link'); ?>"
             title="<?php echo $toolTips['test-linktarget']; ?>"  />
      <input type="text" class="devlink"
             id="clouddev"
             name="clouddev"
             placeholder="<?php echo $l->t('Link to cloud Developer Information'); ?>"
             value="<?php echo $clouddev; ?>"
             title="<?php p(linkToolTip('clouddev-link', $clouddev)); ?>"/>
      <label for="phpmyadmin"><?php echo $l->t('Ambient cloud provider developer documentation'); ?></label>
      <br/>
      <input type="button"
             class="devlinktest"
             id="testcspfailurereporting"
             name="testcspfailurereporting"
             value="<?php echo $l->t('Test Link'); ?>"
             title="<?php echo $toolTips['test-linktarget']; ?>"  />
      <input type="text" class="devlink"
             id="cspfailurereporting"
             name="cspfailurereporting"
             placeholder="<?php echo $l->t('Link for uploading CSP failure information'); ?>"
             value="<?php echo $cspfailurereporting; ?>"
             title="<?php p(linkToolTip('cspfailure-link', $cspfailurereporting)); ?>"/>
      <label for="phpmyadmin"><?php echo $l->t('CSP-failure upload link'); ?></label>
    </fieldset>
    <span class="statusmessage" id="msg"></span>
  </form>
</div>
