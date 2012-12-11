<?php
echo '<div id="controls">'."\n";
echo CAFEVDB\Navigation::button('projectinstruments');
echo CAFEVDB\Navigation::button('instruments');
echo CAFEVDB\Navigation::button('all');
echo '</div>'."\n";
?>
<div class="cafevdb-general">
   <?php CAFEVDB\Projects::display(); ?>
</div>
