<?php
  echo '<div id="controls">'."\n";
  echo CAFEVDB\Navigation::button('projects');
  echo CAFEVDB\Navigation::button('projectinstruments');
  echo CAFEVDB\Navigation::button('instruments');
  echo '</div>'."\n";
?>
<div class="cafevdb-general">
   <?php $table = new CAFEVDB\Musicians(); $table->display(); ?>
</div>
