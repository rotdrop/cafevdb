<?php
$project = CAFEVDB\Util::cgiValue('Project');
$projectId = CAFEVDB\Util::cgiValue('ProjectId',-1);
if ($projectId >= 0) {
  echo '<div id="controls">'."\n";
  echo CAFEVDB\Navigation::button('projectlabel', $project, $projectId);
  echo CAFEVDB\Navigation::button('projects');
  echo CAFEVDB\Navigation::button('email', $project, $projectId);
  echo CAFEVDB\Navigation::button('brief', $project, $projectId);
  echo CAFEVDB\Navigation::button('detailed', $project, $projectId);
  echo '</div>'."\n";
} else {
  echo '<div id="controls">'."\n";
  echo CAFEVDB\Navigation::button('projects');
  echo CAFEVDB\Navigation::button('email');
  echo CAFEVDB\Navigation::button('all');
  echo '</div>'."\n";
}
?>
<div class="cafevdb-general">
   <?php CAFEVDB\Email::displayHistory(); ?>
</div>
