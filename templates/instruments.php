<script type="text/javascript">
  <?php echo $_['jsscript']; ?>
</script>
<div id="controls">
<?php
use CAFEVDB\Instruments;
use CAFEVDB\Navigation;
$csspfx = Instruments::CSS_PREFIX;
$table = new Instruments();
$project = $table->project;
$projectId = $table->projectId;
if ($projectId >= 0) {
  echo Navigation::button('projectlabel', $project, $projectId);
  echo Navigation::button('projects');
  echo Navigation::button('projectinstruments', $project, $projectId);
  echo Navigation::button('brief', $project, $projectId);
  echo Navigation::button('detailed', $project, $projectId);
} else {
  echo Navigation::button('projects');
  echo Navigation::button('projectinstruments');
  echo Navigation::button('all');
}
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
