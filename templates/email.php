<script type="text/javascript">
  <?php echo $_['jsscript']; ?>
</script>
<div id="controls">
<?php
CAFEVDB\Navigation::setTranslation($l);
$project = CAFEVDB\Util::cgiValue('Project');
$projectId = CAFEVDB\Util::cgiValue('ProjectId');
if ($projectId >= 0) {
  echo CAFEVDB\Navigation::button('projectlabel', $project, $projectId);
  echo CAFEVDB\Navigation::button('projects');
  echo CAFEVDB\Navigation::button('emailhistory', $project, $projectId);
  echo CAFEVDB\Navigation::button('detailed', $project, $projectId);
} else {
  echo CAFEVDB\Navigation::button('projects');
  echo CAFEVDB\Navigation::button('emailhistory');
  echo CAFEVDB\Navigation::button('all');
}
?>
<form id="personalsettings">
  <?php echo CAFEVDB\Navigation::button($_['settingscontrols']); ?>
</form>
</div>
<div class="cafevdb-general">
   <?php CAFEVDB\Email::display(OCP\USER::getUser()); ?>
</div>
<div id="dialog_holder"></div>
<div id="appsettings" class="popup topright hidden"></div>
