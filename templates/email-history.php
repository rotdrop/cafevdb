<script type="text/javascript">
  <?php echo $_['jsscript']; ?>
</script>
<div id="controls">
<?php
CAFEVDB\Navigation::setTranslation($l);
$project = CAFEVDB\Util::cgiValue('Project');
$projectId = CAFEVDB\Util::cgiValue('ProjectId',-1);
if ($projectId >= 0) {
  echo CAFEVDB\Navigation::button('projectlabel', $project, $projectId);
  echo CAFEVDB\Navigation::button('projects');
  echo CAFEVDB\Navigation::button('email', $project, $projectId);
  echo CAFEVDB\Navigation::button('brief', $project, $projectId);
  echo CAFEVDB\Navigation::button('projectinstruments', $project, $projectId);
  echo CAFEVDB\Navigation::button('instruments', $project, $projectId); 
} else {
  echo CAFEVDB\Navigation::button('projects');
  echo CAFEVDB\Navigation::button('email');
  echo CAFEVDB\Navigation::button('all');
  echo CAFEVDB\Navigation::button('projectinstruments');
  echo CAFEVDB\Navigation::button('instruments');
}
?>
<form id="personalsettings">
  <?php echo CAFEVDB\Navigation::button($_['settingscontrols']); ?>
</form>
</div>
<div class="cafevdb-general" id="cafevdb-general">
   <?php CAFEVDB\Email::displayHistory(); ?>
</div>
<div id="dialog_holder"></div>
<div id="appsettings" class="popup topright hidden"></div>
