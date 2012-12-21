<script type="text/javascript">
  <?php print $_['jsscript']; ?>
</script>
<div id="controls">
<?php
CAFEVDB\Navigation::setTranslation($l);
echo CAFEVDB\Navigation::button('projectinstruments');
echo CAFEVDB\Navigation::button('instruments');
echo CAFEVDB\Navigation::button('all');
?>
<form id="personalsettings">
  <?php echo CAFEVDB\Navigation::button($_['settingscontrols']); ?>
</form>
</div>
<div class="cafevdb-general" id="cafevdb-general">
   <?php CAFEVDB\Projects::display(); ?>
</div>
<div id="fullcalendar"></div>
<div id="dialog_holder"></div>
<div id="appsettings" class="popup topright hidden"></div>

