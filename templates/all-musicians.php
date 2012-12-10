<?php
  echo '<div id="controls">'."\n";
  echo CAFEVDB\Navigation::button('projects');
  echo '</div>'."\n";
?>
<div class="cafevdb-general">
   <?php $table = new CAFEVDB\Musicians(); $table->display(); ?>
</div>
