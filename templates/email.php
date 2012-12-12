<div id="controls">
<?php
$project = CAFEVDB\Util::cgiValue('Project');
$projectId = CAFEVDB\Util::cgiValue('ProjectId');
if ($projectId >= 0) {
  echo CAFEVDB\Navigation::button('projectlabel', $project, $projectId);
  echo CAFEVDB\Navigation::button('projects');
  echo CAFEVDB\Navigation::button('emailhistory', $project, $projectId);
  //echo CAFEVDB\Navigation::button('brief', $project, $projectId);
  echo CAFEVDB\Navigation::button('detailed', $project, $projectId);
  //echo CAFEVDB\Navigation::button('projectinstruments', $project, $projectId);
  //echo CAFEVDB\Navigation::button('instruments', $project, $projectId);
} else {
  echo CAFEVDB\Navigation::button('projects');
  echo CAFEVDB\Navigation::button('emailhistory');
  //echo CAFEVDB\Navigation::button('projectinstruments');
  //echo CAFEVDB\Navigation::button('instruments');
  echo CAFEVDB\Navigation::button('all');
}
?>
<form id="personalsettings">
  <?php echo CAFEVDB\Navigation::button($_['settingscontrols']); ?>
</form>
</div>
<div class="cafevdb-general">
   <?php CAFEVDB\Email::display(); ?>
</div>
<div id="dialog_holder"></div>
<div id="appsettings" class="popup topright hidden"></div>
