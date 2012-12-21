<script type="text/javascript">
  <?php echo $_['jsscript']; ?>
</script>
<div id="controls">
<?php $table = new CAFEVDB\Musicians(true);
CAFEVDB\Navigation::setTranslation($l);
echo CAFEVDB\Navigation::button('projectlabel', $table->project, $table->projectId);
echo CAFEVDB\Navigation::button('projects');
echo CAFEVDB\Navigation::button('projectinstruments', $table->project, $table->projectId);
echo CAFEVDB\Navigation::button('instruments', $table->project, $table->projectId);
echo CAFEVDB\Navigation::button('brief', $table->project, $table->projectId);
//echo CAFEVDB\Navigation::button('detailed', $table->project, $table->projectId);
?>
<form id="personalsettings">
  <?php echo CAFEVDB\Navigation::button($_['settingscontrols']); ?>
</form>
</div>
<div class="cafevdb-general" id="cafevdb-general">
   <?php $table->display(); ?>
</div>
<div id="dialog_holder"></div>
<div id="appsettings" class="popup topright hidden"></div>

