<?php
echo '<div id="controls">'."\n";
echo CAFEVDB\Navigation::button('projects');
echo CAFEVDB\Navigation::button('all');
echo CAFEVDB\Navigation::button('emailhistory');
echo '</div>'."\n";
?>
<div class="cafevdb-general">
   <?php CAFEVDB\Email::display(); ?>
</div>
