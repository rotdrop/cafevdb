<?php
use CAFEVDB\L;
use CAFEVDB\Config;
?>
<script type="text/javascript">
$(document).ready(function(){
    // $('.projectevents-sendmail').attr("disabled", true);
})
</script>
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
      <input id="concert"   class="submit" name="concerts"   type="button" value="<?php echo L::t('Add Concert'); ?>"    title="<?php echo L::t(Config::toolTips('projectevents-newconcert')); ?>" />
      <input id="rehearsal" class="submit" name="rehearsals" type="button" value="<?php echo L::t('Add Rehearsal') ?>"   title="<?php echo L::t(Config::toolTips('projectevents-newrehearsal')); ?>"/>
      <input id="other"     class="submit" name="other"      type="button" value="<?php echo L::t('Add Other Event') ?>" title="<?php echo L::t(Config::toolTips('projectevents-newother')); ?>" />
      <input id="management"     class="submit" name="management"      type="button" value="<?php echo L::t('Management Event') ?>" title="<?php echo L::t(Config::toolTips('projectevents-newmanagement')); ?>" />
    </td></tr>
    <tr><td>
      <span class="<?php echo $class; ?>-email">
        <input type="button" class="<?php echo $class; ?>-sendmail" name="sendmail" value="Em@il" title="<?php echo L::t(Config::toolTips('projectevents-sendmail')); ?>" /><input type="button" class="<?php echo $class; ?>-sendmail-select" name="select" value="+" title="<?php echo L::t(Config::toolTips('projectevents-select')); ?>" /><input type="button" class="<?php echo $class; ?>-sendmail-deselect" name="deselect" value="-" title="<?php echo L::t(Config::toolTips('projectevents-deselect')); ?>" />
      </span>
      <span class="<?php echo $class; ?>-download">
        <input type="button" class="<?php echo $class; ?>-download" name="download" value="<?php echo L::t('Download'); ?>" title="<?php echo L::t(Config::toolTips('projectevents-download')); ?>" />
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

