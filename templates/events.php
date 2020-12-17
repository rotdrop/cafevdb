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
?>
<div id="events" class="cafev not-fixed-container"
     title="<?php echo $l->t('Events for').' '.$projectName;?>">
  <form id="eventlistform" class="<?php echo $cssClass; ?> not-fixed-container" >
    <input type="hidden" name="ProjectId"   value="<?php echo $projectId; ?>" />
    <input type="hidden" name="ProjectName" value="<?php echo $projectName; ?>" />
    <input type="hidden" name="requesttoken" value="<?php echo $requestToken; ?>"/>
    <div class="eventcontrols content-controls">
      <select class="event-menu cafevdb-menu tooltip-right"
              data-placeholder="<?php echo $l->t('New Event'); ?>"
              title="<?php echo $toolTips['new-project-event']; ?>">
        <option value=""></option>
        <option value="concerts"><?php echo $l->t('Concert'); ?></option>
        <option value="rehearsals"><?php echo $l->t('Rehearsal'); ?></option>
        <option value="other"><?php echo $l->t('Miscellaneous'); ?></option>
        <option value="management"><?php echo $l->t('Management'); ?></option>
        <option value="finance"><?php echo $l->t('Finance'); ?></option>
      </select>
      <span class="<?php echo $cssClass; ?>-email">
        <input type="button"
               class="<?php echo $cssClass; ?>-sendmail tooltip-bottom"
               name="sendmail"
               value="Em@il"
               title="<?php echo $toolTips['projectevents-sendmail']; ?>"
        />
        <input type="button"
               class="<?php echo $cssClass; ?>-sendmail-select image-button tooltip-bottom"
               name="select"
               value="+"
               title="<?php echo $toolTips['projectevents-select']; ?>"
        />
        <input type="button"
               class="<?php echo $cssClass; ?>-sendmail-deselect image-button tooltip-bottom"
               name="deselect"
               value="-"
               title="<?php echo $toolTips['projectevents-deselect']; ?>"
        />
      </span>
      <span class="<?php echo $cssClass; ?>-download">
        <input id="<?php echo $cssClass; ?>-download"
               class="<?php echo $cssClass; ?>-download image-button tooltip-bottom"
               type="button"
               name="download"
               value="<?php echo $l->t('Download'); ?>"
               title="<?php echo $toolTips['projectevents-download']; ?>"/>
      </span>
    </div>
    <div id="eventlist" class="container">
      <div id="eventlistholder" class="container scroller">
        <?php echo $this->inc('eventslisting', $_); ?>
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
