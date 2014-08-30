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
use CAFEVDB\Util;
use CAFEVDB\Config;

echo Util::emitExternalScripts();

?>
<div id="events" title="<?php echo L::t('Events for').' '.$_['ProjectName'];?>">
<?php
$prjId   = $_['ProjectId'];
$prjName = $_['ProjectName'];
$class   = $_['CSSClass'];
?>
<form id="eventlistform" class="<?php echo $class; ?>" >
  <input type="hidden" name="ProjectId"   value="<?php echo $prjId; ?>" />
  <input type="hidden" name="ProjectName" value="<?php echo $prjName; ?>" />
  <div class="topbuttons"><table class="nostyle topbuttons">
    <tr><td class="topbuttons">
      <input id="concert"   class="submit" name="concerts"   type="button" value="<?php echo L::t('Add Concert'); ?>"    title="<?php echo Config::toolTips('projectevents-newconcert'); ?>" />
      <input id="rehearsal" class="submit" name="rehearsals" type="button" value="<?php echo L::t('Add Rehearsal') ?>"   title="<?php echo Config::toolTips('projectevents-newrehearsal'); ?>"/>
      <input id="other"     class="submit" name="other"      type="button" value="<?php echo L::t('Add Other Event') ?>" title="<?php echo Config::toolTips('projectevents-newother'); ?>" />
      <input id="management"     class="submit" name="management"      type="button" value="<?php echo L::t('Management Event') ?>" title="<?php echo Config::toolTips('projectevents-newmanagement'); ?>" />
    </td></tr>
    <tr><td>
      <span class="<?php echo $class; ?>-email">
        <input type="button" class="<?php echo $class; ?>-sendmail" name="sendmail" value="Em@il" title="<?php echo Config::toolTips('projectevents-sendmail'); ?>" /><input type="button" class="<?php echo $class; ?>-sendmail-select" name="select" value="+" title="<?php echo Config::toolTips('projectevents-select'); ?>" /><input type="button" class="<?php echo $class; ?>-sendmail-deselect" name="deselect" value="-" title="<?php echo Config::toolTips('projectevents-deselect'); ?>" />
      </span>
      <span class="<?php echo $class; ?>-download">
        <input type="button" class="<?php echo $class; ?>-download" name="download" value="<?php echo L::t('Download'); ?>" title="<?php echo Config::toolTips('projectevents-download'); ?>" />
      </span>
    </td>
</tr>
  </table>
  </div>
  <div class="listing">
  <?php echo $this->inc("eventslisting"); ?>
  </div>
</form>
<div id="debug"></div>
</div>

<?php

/*
 * Local Variables: ***
 * c-basic-offset: 2 ***
 * End: ***
 */

?>

