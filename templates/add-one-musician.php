<?php $table = new CAFEVDB\Musicians(true);
echo '<div id="controls">'."\n";
echo CAFEVDB\Navigation::button('projectlabel', $table->project, $table->projectId);
echo CAFEVDB\Navigation::button('projects');
echo CAFEVDB\Navigation::button('add', $table->project, $table->projectId);
echo CAFEVDB\Navigation::button('brief', $table->project, $table->projectId);
echo CAFEVDB\Navigation::button('detailed', $table->project, $table->projectId);
echo CAFEVDB\Navigation::button('instruments', $table->project, $table->projectId);
echo '</div>'."\n";
?>
<div class="cafevdb-general">
  <?php $table->displayAddChangeOne(); ?>
</div>
