<div id="events" title="<?php echo $l->t('Events for').' '.$_['ProjectName'];?>">
<?php
$prjId   = $_['ProjectId'];
$prjName = $_['ProjectName'];
$class   = $_['CSSClass'];
?>
<form id="eventlistform" class="<?php echo $class; ?>">
  <input type="hidden" name="ProjectId"   value="<?php echo $prjId; ?>" />
  <input type="hidden" name="ProjectName" value="<?php echo $prjName; ?>" />
  <div class="topbuttons"><table class="nostyle topbuttons">
    <tr><td class="topbuttons">
      <span class="<?php echo $class; ?>-email">
        <input type="button" class="<?php echo $class; ?>-sendmail" name="sendmail" value="Em@il" title="<?php echo $l->t(CAFEVDB\Config::toolTips('projectevents-sendmail')); ?>" /><input type="button" class="<?php echo $class; ?>-sendmail-select" name="select" value="+" title="<?php echo $l->t(CAFEVDB\Config::toolTips('projectevents-select')); ?>" /><input type="button" class="<?php echo $class; ?>-sendmail-deselect" name="deselect" value="-" title="<?php echo $l->t(CAFEVDB\Config::toolTips('projectevents-deselect')); ?>" />
      </span>
      <input id="concert"   class="submit" name="concerts"   type="button" value="<?php echo $l->t('Add Concert'); ?>"    title="<?php echo $l->t(CAFEVDB\Config::toolTips('projectevents-newconcert')); ?>" />
      <input id="rehearsal" class="submit" name="rehearsals" type="button" value="<?php echo $l->t('Add Rehearsal') ?>"   title="<?php echo $l->t(CAFEVDB\Config::toolTips('projectevents-newrehearsal')); ?>"/>
      <input id="other"     class="submit" name="other"      type="button" value="<?php echo $l->t('Add Other Event') ?>" title="<?php echo $l->t(CAFEVDB\Config::toolTips('projectevents-newother')); ?>" />
    </td></tr>
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

