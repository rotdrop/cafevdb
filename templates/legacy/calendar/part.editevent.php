<div id="event" title="<?php p($l->t("Edit event"));?>">
  <form id="event_form" data-default-duration="<?php p($_['default_duration']) ?>">
    <input type="hidden" name="uri" value="<?php p($_['eventuri']) ?>">
    <input type="hidden" name="calendarid" value="<?php p($_['calendarid']) ?>">
    <input type="hidden" name="lastmodified" value="<?php p($_['lastmodified']) ?>">
    <?php print_unescaped($this->inc("legacy/calendar/part.eventform", $_)); ?>
    <div style="width: 100%;text-align: center;color: #FF1D1D;" id="errorbox"></div>
    <div id="actions" class="flex-container flex-center flex-justify-full">
      <input type="button"
             class="submit no-flex"
             id="editEvent-delete"
             name="delete"
             title="<?php echo $toolTips['projectevents:event:delete']; ?>"
             value="<?php p($l->t('Delete event'));?>"
             data-link="<?php print_unescaped($urlGenerator->linkToRoute('cafevdb.legacy_events.service_switch', ['topic' => 'actions', 'subTopic' => 'delete'])); ?>">
      <input type="button"
             class="submit no-flex"
             id="editEvent-clone"
             name="clone"
             title="<?php echo $toolTips['projectevents:event:clone']; ?>"
             value="<?php p($l->t('Clone event'));?>"
             data-link="<?php print_unescaped($urlGenerator->linkToRoute('cafevdb.legacy_events.service_switch', ['topic' => 'forms', 'subTopic' => 'clone'])); ?>?eventuri=<?php echo urlencode($_['eventuri']); ?>&calendarid=<?php echo $_['calendarid']; ?>"
      >
      <input type="button"
             class="submit no-flex primary"
             id="editEvent-submit"
             value="<?php p($l->t('Save event'));?>"
             data-link="<?php print_unescaped($urlGenerator->linkToRoute('cafevdb.legacy_events.service_switch', ['topic' => 'actions', 'subTopic' => 'edit'])); ?>"
      >
    </div>
  </form>
</div>
