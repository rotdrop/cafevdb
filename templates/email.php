<?php use CAFEVDB\L; ?>
<?php use CAFEVDB\Email; ?>
<?php use CAFEVDB\Util; ?>
<?php use CAFEVDB\Navigation; ?>
<script type="text/javascript">
  <?php echo $_['jsscript']; ?>
</script>
<div id="controls">
<?php
$project = Util::cgiValue('Project');
$projectId = Util::cgiValue('ProjectId');
if ($projectId >= 0) {
  echo Navigation::button('projectlabel', $project, $projectId);
  echo Navigation::button('projects');
  echo Navigation::button('emailhistory', $project, $projectId);
  echo Navigation::button('detailed', $project, $projectId);
} else {
  echo Navigation::button('projects');
  echo Navigation::button('emailhistory');
  echo Navigation::button('all');
}
?>
<form id="personalsettings">
  <?php echo Navigation::button($_['settingscontrols']); ?>
</form>
</div>
<div class="cafevdb-general" id="cafevdb-general">
  <div class="<?php echo Email::CSS_PREFIX; ?>-header-box">
    <div class="<?php echo Email::CSS_PREFIX; ?>-header">
      <?php echo Email::headerText(); ?>
    </div>
    <?php echo Navigation::button($_['viewtoggle']); ?>
  </div>
  <div class="<?php echo Email::CSS_PREFIX; ?>-body">
    <?php Email::display(OCP\USER::getUser()); ?>
  </div>
</div>
<div id="dialog_holder"></div>
<div id="appsettings" class="popup topright hidden"></div>

