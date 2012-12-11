<?php
$table = new CAFEVDB\ProjectInstruments();
$project = $table->project;
$projectId = $table->projectId;
if ($projectId >= 0) {
  echo '<div id="controls">'."\n";
  echo CAFEVDB\Navigation::button('projectlabel', $project, $projectId);
  echo CAFEVDB\Navigation::button('projects');
  echo CAFEVDB\Navigation::button('instruments', $project, $projectId);  
  echo CAFEVDB\Navigation::button('brief', $project, $projectId);
  echo CAFEVDB\Navigation::button('detailed', $project, $projectId);  
  echo '</div>'."\n";
} else {
  echo '<div id="controls">'."\n";
  echo CAFEVDB\Navigation::button('projects');
  echo CAFEVDB\Navigation::button('instruments');
  echo CAFEVDB\Navigation::button('all');
  echo '</div>'."\n";
}
?>
<div class="cafevdb-general">
   <?php $table->display(); ?>
</div>
