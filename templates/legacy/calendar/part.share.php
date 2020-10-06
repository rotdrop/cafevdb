<?php
$calid = isset($_['calendar']) ? $_['calendar'] : null;
$eventuri = isset($_['eventuri']) ? $_['eventuri'] : null;
$location = isset($_['location']) ? $_['location'] : null;
$description = isset($_['description']) ? $_['description'] : null;
$dtstart = isset($_['dtstart']) ? $_['dtstart'] : null;
$dtend = isset($_['dtend']) ? $_['dtend'] : null;

$calsharees = array();
$eventsharees = array();

$sharedwithByCalendar = OCP\Share::getItemShared('calendar', $calid);
$sharedwithByEvent = OCP\Share::getItemShared('event', $eventuri);

if(is_array($sharedwithByCalendar)) {
	foreach($sharedwithByCalendar as $share) {
		if($share['share_type'] == OCP\Share::SHARE_TYPE_USER || $share['share_type'] == OCP\Share::SHARE_TYPE_GROUP) {
			$calsharees[] = $share;
		}
	}
}
if(is_array($sharedwithByEvent)) {
	foreach($sharedwithByEvent as $share) {
		if($share['share_type'] == OCP\Share::SHARE_TYPE_USER || $share['share_type'] == OCP\Share::SHARE_TYPE_GROUP) {
			$eventsharees[] = $share;
		}
	}
}
?>

<input type="text" id="sharewith"
	placeholder="<?php p($l->t('Share with user or group')); ?>"
	data-item-source="<?php p($eventuri); ?>" />

<ul class="sharedby eventlist">
<?php foreach($eventsharees as $sharee): ?>
	<li data-share-with="<?php p($sharee['share_with']); ?>"
		data-item="<?php p($eventuri); ?>"
		data-item-type="event"
		data-permissions="<?php p($sharee['permissions']); ?>"
		data-share-type="<?php p($sharee['share_type']); ?>">
		<?php p($sharee['share_with'] . ($sharee['share_type'] == OCP\Share::SHARE_TYPE_GROUP ? ' (group)' : '')); ?>
		<span class="shareactions">
			<input id="checkbox-<?php p($sharee['share_with']) ?>-update" class="update sharepermissioncheckbox" type="checkbox" <?php p(($sharee['permissions'] & OCP\PERMISSION_UPDATE?'checked="checked"':''))?>>
			<label for="checkbox-<?php p($sharee['share_with']) ?>-update"><?php p($l->t('can edit')); ?></label>

			<input id="checkbox-<?php p($sharee['share_with']) ?>-share" class="share sharepermissioncheckbox" type="checkbox" <?php p(($sharee['permissions'] & OCP\PERMISSION_SHARE?'checked="checked"':''))?>>
			<label for="checkbox-<?php p($sharee['share_with']) ?>-share"><?php p($l->t('can share')); ?></label>

			<img src="<?php p(OCP\Util::imagePath('core', 'actions/delete.svg')); ?>" class="svg action delete"
				title="<?php p($l->t('Unshare')); ?>">
		</span>
	</li>
<?php endforeach; ?>
</ul>
<?php if(!$eventsharees) {
	$nobody = $l->t('Not shared with anyone');
	print_unescaped('<div id="sharedWithNobody">' . OC_Util::sanitizeHTML($nobody) . '</div>');
} ?>
<br />
<input type="button" id="sendemailbutton" style="float:right;" class="submit" value="<?php p($l->t("Send Email")); ?>" data-eventuri="<?php p($eventuri);?>" data-location="<?php p($location);?>" data-description="<?php p($description);?>" data-dtstart="<?php p($dtstart);?>" data-dtend="<?php p($dtend);?>">
<br />

<br />
<strong><?php p($l->t('Shared via calendar')); ?></strong>
<ul class="sharedby calendarlist">
<?php foreach($calsharees as $sharee): ?>
	<li data-share-with="<?php p($sharee['share_with']); ?>"
		data-item="<?php p($calid); ?>"
		data-item-type="calendar"
		data-permissions="<?php p($sharee['permissions']); ?>"
		data-share-type="<?php p($sharee['share_type']); ?>">
		<?php p($sharee['share_with'] . ($sharee['share_type'] == OCP\Share::SHARE_TYPE_GROUP ? ' (group)' : '')); ?>
		<span class="shareactions">
			<input class="update" type="checkbox"
				<?php p(($sharee['permissions'] & OCP\PERMISSION_UPDATE?'checked="checked"':''))?>
				disabled="disabled">
			<label><?php p($l->t('can edit')); ?></label>
			<input class="share" type="checkbox"
				<?php p(($sharee['permissions'] & OCP\PERMISSION_SHARE?'checked="checked"':''))?>
				disabled="disabled">
			<label><?php p($l->t('can share')); ?></label>
		</span>
	</li>
<?php endforeach; ?>
</ul>
<?php if(!$calsharees) {
	$nobody = $l->t('Not shared with anyone via calendar');
	print_unescaped('<div>' . OC_Util::sanitizeHTML($nobody) . '</div>');
} ?>
