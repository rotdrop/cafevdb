<div id="controls">
<?php
CAFEVDB\Navigation::setTranslation($l);
$table = new CAFEVDB\DetailedInstrumentation();
echo CAFEVDB\Navigation::button('projectlabel', $table->project, $table->projectId);
echo CAFEVDB\Navigation::button('projects');
echo CAFEVDB\Navigation::button('brief', $table->project, $table->projectId);
echo CAFEVDB\Navigation::button('add', $table->project, $table->projectId);
echo CAFEVDB\Navigation::button('projectinstruments', $table->project, $table->projectId);
?>
<form id="personalsettings">
  <?php echo CAFEVDB\Navigation::button($_['settingscontrols']); ?>
</form>
</div>
<div class="cafevdb-general">
   <?php $table->display(); ?>
</div>
<div id="dialog_holder"></div>
<div id="appsettings" class="popup topright hidden"></div>

