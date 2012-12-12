<div id="controls">
<?php
CAFEVDB\Navigation::setTranslation($l);
$table = new CAFEVDB\ProjectInstruments();
$project = $table->project;
$projectId = $table->projectId;
if ($projectId >= 0) {
  echo CAFEVDB\Navigation::button('projectlabel', $project, $projectId);
  echo CAFEVDB\Navigation::button('projects');
  echo CAFEVDB\Navigation::button('instruments', $project, $projectId);  
  echo CAFEVDB\Navigation::button('add', $project, $projectId);  
  echo CAFEVDB\Navigation::button('brief', $project, $projectId);
} else {
  echo CAFEVDB\Navigation::button('projects');
  echo CAFEVDB\Navigation::button('instruments');
  echo CAFEVDB\Navigation::button('all');
}
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
