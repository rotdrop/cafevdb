<script type="text/javascript">
  <?php echo $_['jsscript']; ?>
</script>
<div id="controls">
<?php
  CAFEVDB\Navigation::setTranslation($l);
  echo CAFEVDB\Navigation::button('projects');
  echo CAFEVDB\Navigation::button('projectinstruments');
  echo CAFEVDB\Navigation::button('instruments');
?>
<form id="personalsettings">
  <?php echo CAFEVDB\Navigation::button($_['settingscontrols']); ?>
</form>
</div>
<div class="cafevdb-general">
   <?php $table = new CAFEVDB\Musicians(); $table->display(); ?>
</div>
<div id="dialog_holder"></div>
<div id="appsettings" class="popup topright hidden"></div>

