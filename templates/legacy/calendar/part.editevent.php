<div id="event" title="<?php p($l->t("Edit event"));?>">
	<form id="event_form" data-default-duration="<?php p($_['default_duration']) ?>">
		<input type="hidden" name="uri" value="<?php p($_['eventuri']) ?>">
		<input type="hidden" name="calendarid" value="<?php p($_['calendarid']) ?>">
		<input type="hidden" name="lastmodified" value="<?php p($_['lastmodified']) ?>">
<?php print_unescaped($this->inc("legacy/calendar/part.eventform", $_)); ?>
	<div style="width: 100%;text-align: center;color: #FF1D1D;" id="errorbox"></div>
	<div id="actions">
		<input type="button" class="submit actionsfloatright primary" id="editEvent-submit" value="<?php p($l->t('Save event'));?>" data-link="<?php print_unescaped($urlGenerator->linkToRoute('cafevdb.legacy_events.service_switch', ['topic' => 'actions', 'subTopic' => 'edit'])); ?>">
		<input type="button" class="submit actionsfloatleft" id="editEvent-delete"  name="delete" value="<?php p($l->t('Delete event'));?>" data-link="<?php print_unescaped($urlGenerator->linkToRoute('cafevdb.legacy_events.service_switch', ['topic' => 'actions', 'subTopic' => 'delete'])); ?>">
	</div>
	</form>
</div>
