<?php
$project = CAFEVDB\Util::cgiValue('Project');
$projectId = CAFEVDB\Util::cgiValue('ProjectId');
if ($projectId >= 0) {
  echo '<div id="controls">'."\n";
  echo CAFEVDB\Navigation::button('projectlabel', $project, $projectId);
  echo CAFEVDB\Navigation::button('projects');
  echo CAFEVDB\Navigation::button('brief', $project, $projectId);
  echo CAFEVDB\Navigation::button('detailed', $project, $projectId);
  echo CAFEVDB\Navigation::button('instruments', $project, $projectId);
  echo CAFEVDB\Navigation::button('emailhistory', $project, $projectId);
  echo '</div>'."\n";
} else {
  echo '<div id="controls">'."\n";
  echo CAFEVDB\Navigation::button('projects');
  echo CAFEVDB\Navigation::button('instruments');
  echo CAFEVDB\Navigation::button('all');
  echo CAFEVDB\Navigation::button('emailhistory');
  echo '</div>'."\n";
}
?>
<div class="cafevdb-general">
   <?php CAFEVDB\Email::display(); ?>
</div>
