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

  echo Util::emitExternalScripts();

  $prjId   = $_['ProjectId'];
  $prjName = $_['ProjectName'];
  $class   = $_['CSSClass'];

?>
<div id="events" title="<?php echo L::t('Events for').' '.$_['ProjectName'];?>">
<form id="eventlistform" class="<?php echo $class; ?>" >
  <input type="hidden" name="ProjectId"   value="<?php echo $prjId; ?>" />
  <input type="hidden" name="ProjectName" value="<?php echo $prjName; ?>" />
  <div class="eventcontrols">
    <select class="event-menu cafevdb-menu tooltip-right"
            data-placeholder="<?php echo L::t('New Event'); ?>"
            title="<?php echo Config::toolTips('new-project-event'); ?>">
      <option value=""></option>
      <option value="concerts"><?php echo L::t('Concert'); ?></option>
      <option value="rehearsals"><?php echo L::t('Rehearsal'); ?></option>
      <option value="other"><?php echo L::t('Miscellaneous'); ?></option>
      <option value="management"><?php echo L::t('Management'); ?></option>
      <option value="finance"><?php echo L::t('Finance'); ?></option>
    </select>
    <span class="<?php echo $class; ?>-email">
      <input type="button"
             class="<?php echo $class; ?>-sendmail tooltip-bottom"
             name="sendmail"
             value="Em@il"
             title="<?php echo Config::toolTips('projectevents-sendmail'); ?>"
             />
      <input type="button"
             class="<?php echo $class; ?>-sendmail-select tooltip-bottom"
             name="select"
             value="+"
             title="<?php echo Config::toolTips('projectevents-select'); ?>"
             />
      <input type="button"
             class="<?php echo $class; ?>-sendmail-deselect tooltip-bottom"
             name="deselect"
             value="-"
             title="<?php echo Config::toolTips('projectevents-deselect'); ?>"
             />
    </span>
    <span class="<?php echo $class; ?>-download">
      <input id="projectevents-download"
             class="projectevents-download tooltip-bottom"
             type="button"
             name="download"
             value="<?php echo L::t('Download'); ?>"
             title="<?php echo Config::toolTips('projectevents-download'); ?>"/>
    </span>
  </div>
  <div class="listing">
  <?php echo $this->inc("eventslisting"); ?>
  </div>
</form>
<div id="debug"></div>
</div>

<?php

} // namespace CAFEVDB

/*
 * Local Variables: ***
 * c-basic-offset: 2 ***
 * End: ***
 */

?>
