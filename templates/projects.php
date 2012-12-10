<?php
echo '<div id="controls">'."\n";
echo CAFEVDB\Navigation::button('all');
echo CAFEVDB\Navigation::button('email');
echo '</div>'."\n";
?>
<div class="cafevdb-general">
   <?php CAFEVDB\Projects::display(); ?>
</div>
