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
  <!-- Upload support via Ajax/jsscript magic. -->
  <form class="float" id="file_upload_form" action="<?php echo OCP\Util::linkTo('cafevdb', 'ajax/email/uploadattachment.php'); ?>" method="post" enctype="multipart/form-data" target="file_upload_target">
    <input type="hidden" name="requesttoken" value="<?php echo $_['requesttoken'] ?>">
    <input type="hidden" name="id" value="<?php echo $_['id'] ?>">
    <input type="hidden" name="MAX_FILE_SIZE" value="<?php echo $_['uploadMaxFilesize'] ?>" id="max_upload">
    <input type="hidden" class="max_human_file_size" value="(max <?php echo $_['uploadMaxHumanFilesize']; ?>)">
    <input id="file_upload_start" type="file" name="fileAttach" />
  </form>
  <iframe name="file_upload_target" id='file_upload_target' src=""></iframe>
</div>
<div id="dialog_holder"></div>
<div id="appsettings" class="popup topright hidden"></div>

