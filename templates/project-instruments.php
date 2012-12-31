<?php use CAFEVDB\L; ?>
<script type="text/javascript">
  <?php echo $_['jsscript']; ?>
</script>
<div id="controls">
<?php
use CAFEVDB\ProjectInstruments;
use CAFEVDB\Navigation;
$csspfx = ProjectInstruments::CSS_PREFIX;
$table = new ProjectInstruments();
$project = $table->project;
$projectId = $table->projectId;
if ($projectId >= 0) {
  echo Navigation::button('projectlabel', $project, $projectId);
  echo Navigation::button('projects');
  echo Navigation::button('instruments', $project, $projectId);  
  echo Navigation::button('add', $project, $projectId);  
  echo Navigation::button('brief', $project, $projectId);
} else {
  echo Navigation::button('projects');
  echo Navigation::button('instruments');
  echo Navigation::button('all');
}
?>
<form id="personalsettings">
  <?php echo Navigation::button($_['settingscontrols']); ?>
</form>
</div>
<?php
if ($projectId >= 0) {
  $xferStatus = $table->transferInstruments();
  $xferStatus = $xferStatus ? L::t('Success!') : '';
  $xferButton = '
<div>
  <br/>
  <table id="transfer-instruments">
    <TR>
      <TD>'
.Navigation::button('transfer-instruments', $project, $projectId).
     '</TD>
      <TD><span>'.$xferStatus.'</span></TD
    </TR>
  </TABLE>
</div>';
} else {
  $xferButton = '';
}
?>
<div class="cafevdb-general" id="cafevdb-general">
  <div class="<?php echo $csspfx; ?>-header-box">
    <div class="<?php echo $csspfx; ?>-header">
      <?php echo $table->headerText(); ?>
      <?php echo $xferButton; ?>
    </div>
    <?php echo Navigation::button($_['viewtoggle']); ?>
  </div>
  <?php $table->display(); ?>
</div>
<div id="dialog_holder"></div>
<div id="appsettings" class="popup topright hidden"></div>
