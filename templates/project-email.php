<?php
$project = CAFEVDB\Util::cgiValue('Project');
$projectId = CAFEVDB\Util::cgiValue('ProjectId');
echo '<div id="controls">'."\n";
echo CAFEVDB\Navigation::button('projectlabel', $project, $projectId);
echo CAFEVDB\Navigation::button('projects');
echo CAFEVDB\Navigation::button('brief', $project, $projectId);
echo CAFEVDB\Navigation::button('detailed', $project, $projectId);
echo CAFEVDB\Navigation::button('emailhistory', $project, $projectId);
echo '</div>'."\n";
?>
<div class="cafevdb-general">
   <?php CAFEVDB\Email::display(); ?>
</div>
