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

$prjId   = $_['ProjectId'];
$prjName = $_['ProjectName'];
$class   = $_['CSSClass'];

?>
<div id="events" class="cafev fixed-container"
     title="<?php echo $l->t('Events for').' '.$_['ProjectName'];?>">
  <form id="eventlistform" class="<?php echo $class; ?> fixed-container" >
    <input type="hidden" name="ProjectId"   value="<?php echo $prjId; ?>" />
    <input type="hidden" name="ProjectName" value="<?php echo $prjName; ?>" />
    <div class="eventcontrols content-controls">
      <select class="event-menu cafevdb-menu tooltip-right"
              data-placeholder="<?php echo $l->t('New Event'); ?>"
              title="<?php echo $toolTips[new-project-event]; ?>">
        <option value=""></option>
        <option value="concerts"><?php echo $l->t('Concert'); ?></option>
        <option value="rehearsals"><?php echo $l->t('Rehearsal'); ?></option>
        <option value="other"><?php echo $l->t('Miscellaneous'); ?></option>
        <option value="management"><?php echo $l->t('Management'); ?></option>
        <option value="finance"><?php echo $l->t('Finance'); ?></option>
      </select>
      <span class="<?php echo $class; ?>-email">
        <input type="button"
               class="<?php echo $class; ?>-sendmail tooltip-bottom"
               name="sendmail"
               value="Em@il"
               title="<?php echo $toolTips[projectevents-sendmail]; ?>"
        />
        <input type="button"
               class="<?php echo $class; ?>-sendmail-select tooltip-bottom"
               name="select"
               value="+"
               title="<?php echo $toolTips[projectevents-select]; ?>"
        />
        <input type="button"
               class="<?php echo $class; ?>-sendmail-deselect tooltip-bottom"
               name="deselect"
               value="-"
               title="<?php echo $toolTips[projectevents-deselect]; ?>"
        />
      </span>
      <span class="<?php echo $class; ?>-download">
        <input id="projectevents-download"
               class="projectevents-download tooltip-bottom"
               type="button"
               name="download"
               value="<?php echo $l->t('Download'); ?>"
               title="<?php echo $toolTips[projectevents-download]; ?>"/>
      </span>
    </div>
    <div id="eventlist" class="container">
      <div id="eventlistholder" class="container scroller">
        <?php echo $this->inc("eventslisting"); ?>
      </div>
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
