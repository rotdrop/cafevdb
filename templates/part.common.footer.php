<?php
use CAFEVDB\L;
$css_pfx = $_['css-prefix'];
?>

  </div>
  <?php echo isset($_['footer']) ? $_['footer'] : ''; ?>
  <div class="debug" id="<?php echo $css_pfx; ?>-general-debug"></div>
</div> <!-- cafevdb-general -->
<div id="dialog_holder"></div>
<div id="appsettings" class="popup topright hidden"></div>

