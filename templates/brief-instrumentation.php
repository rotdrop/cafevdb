<script type="text/javascript">
  <?php echo $_['jsscript']; ?>
</script>
<div id="controls">
<?php
use CAFEVDB\BriefInstrumentation;
use CAFEVDB\Navigation;
$csspfx = BriefInstrumentation::CSS_PREFIX;
$table = new BriefInstrumentation();
echo Navigation::button('projectlabel', $table->project, $table->projectId);
echo Navigation::button('projects');
echo Navigation::button('detailed', $table->project, $table->projectId);
echo Navigation::button('add', $table->project, $table->projectId);
echo Navigation::button('projectinstruments', $table->project, $table->projectId);
?>
<form id="personalsettings">
  <?php echo Navigation::button($_['settingscontrols']); ?>
</form>
</div>
<div class="cafevdb-general" id="cafevdb-general">
  <div class="<?php echo $csspfx; ?>-header-box">
    <div class="<?php echo $csspfx; ?>-header">
      <?php echo $table->headerText(); ?>
    </div>
    <?php echo Navigation::button($_['viewtoggle']); ?>
  </div>
  <?php $table->display(); ?>
</div>
<div id="dialog_holder"></div>
<div id="appsettings" class="popup topright hidden"></div>

