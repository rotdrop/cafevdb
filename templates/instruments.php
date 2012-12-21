<script type="text/javascript">
  <?php echo $_['jsscript']; ?>
</script>
<div id="controls">
<?php
CAFEVDB\Navigation::setTranslation($l);
$table = new CAFEVDB\Instruments();
$project = $table->project;
$projectId = $table->projectId;
if ($projectId >= 0) {
  echo CAFEVDB\Navigation::button('projectlabel', $project, $projectId);
  echo CAFEVDB\Navigation::button('projects');
  echo CAFEVDB\Navigation::button('projectinstruments', $project, $projectId);
  echo CAFEVDB\Navigation::button('brief', $project, $projectId);
  echo CAFEVDB\Navigation::button('detailed', $project, $projectId);
} else {
  echo CAFEVDB\Navigation::button('projects');
  echo CAFEVDB\Navigation::button('projectinstruments');
  echo CAFEVDB\Navigation::button('all');
}
?>
<form id="personalsettings">
  <?php echo CAFEVDB\Navigation::button($_['settingscontrols']); ?>
</form>
</div>
<div class="cafevdb-general" id="cafevdb-general">
   <?php $table->display(); ?>
</div>
<div id="dialog_holder"></div>
<div id="appsettings" class="popup topright hidden"></div>
