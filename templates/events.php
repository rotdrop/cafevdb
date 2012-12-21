<div id="events" title="<?php echo $l->t('Events for ').$_['ProjectName'];?>">
Hello World!
<br/>
Project-Id <?php echo $_['ProjectId'] ?>
<form id="eventlist">
<table>
<?php
foreach ($_['Events'] as $event) {
    $start = $event['object']['startdate'];
    $end   = $event['object']['enddate'];
    $brief = $event['object']['summary'];
    echo "<TR><TD>$start</TD><TD>$end</TD><TD>$brief</TD></TR>\n";
}
?>
</table>
</form>
<form id="newevent_form">
  <input id="projectId" type="hidden"  name="ProjectName"  value="<?php echo $_['ProjectName']; ?>" />
  <input id="projectId" type="hidden"  name="ProjectId"    value="<?php echo $_['ProjectId']; ?>" />
  <input id="concert"   class="submit" name="concerts"     type="button" value="<?php echo $l->t('Add Concert'); ?>" />
  <input id="rehearsal" class="submit" name="rehearsals"   type="button" value="<?php echo $l->t('Add Rehearsal') ?>" />
  <input id="other"     class="submit" name="other"        type="button" value="<?php echo $l->t('Add Other Event') ?>" /> 
  <div id="debug"></div>
</form>
</div>

