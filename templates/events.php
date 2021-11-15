<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine
 * @copyright 2011-2016, 2020, 2021 Claus-Justus Heine <himself@claus-justus-heine.de>
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
?>
<div id="events" class="cafev not-fixed-container"
     title="<?php p($l->t('Events for').' '.$projectName); ?>">
  <form id="eventlistform" class="<?php p($cssClass); ?> not-fixed-container" >
    <input type="hidden" name="projectId"   value="<?php p($projectId); ?>" />
    <input type="hidden" name="projectName" value="<?php p($projectName); ?>" />
    <input type="hidden" name="requesttoken" value="<?php p($requesttoken); ?>"/>
    <div class="eventcontrols content-controls">
      <select class="event-menu cafevdb-menu tooltip-right"
              data-placeholder="<?php p($l->t('New Event')); ?>"
              title="<?php echo $toolTips['new-project-event']; ?>">
        <option value=""></option>
        <option value="concerts"><?php p($l->t('Concert')); ?></option>
        <option value="rehearsals"><?php p($l->t('Rehearsal')); ?></option>
        <option value="other"><?php p($l->t('Miscellaneous')); ?></option>
        <option value="management"><?php p($l->t('Management')); ?></option>
        <option value="finance"><?php p($l->t('Finance')); ?></option>
      </select>
      <span class="<?php p($cssClass); ?>-email">
        <input type="button"
               class="<?php p($cssClass); ?>-sendmail tooltip-bottom"
               name="sendmail"
               value="Em@il"
               title="<?php echo $toolTips['projectevents-sendmail']; ?>"
        />
        <input type="button"
               class="<?php p($cssClass); ?>-sendmail-select image-button tooltip-bottom"
               name="select"
               value="+"
               title="<?php echo $toolTips['projectevents-select']; ?>"
        />
        <input type="button"
               class="<?php p($cssClass); ?>-sendmail-deselect image-button tooltip-bottom"
               name="deselect"
               value="-"
               title="<?php echo $toolTips['projectevents-deselect']; ?>"
        />
      </span>
      <span class="<?php p($cssClass); ?>-download">
        <input id="<?php p($cssClass); ?>-download"
               class="<?php p($cssClass); ?>-download image-button tooltip-bottom"
               type="button"
               name="download"
               value="<?php p($l->t('Download')); ?>"
               title="<?php echo $toolTips['projectevents-download']; ?>"/>
      </span>
      <span class="<?php p($cssClass); ?>-reload">
        <input id="<?php p($cssClass); ?>-reload"
               class="<?php p($cssClass); ?>-reload image-button tooltip-bottom"
               type="button"
               name="reload"
               value="<?php p($l->t('Reload')); ?>"
               title="<?php echo $toolTips['projectevents-reload']; ?>"/>
      </span>
    </div>
    <div id="eventlistholder" class="container scroller eventlist">
      <?php echo $this->inc('eventslisting', $_); ?>
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
