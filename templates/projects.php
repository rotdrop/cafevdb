<div id="controls">
<?php
echo CAFEVDB\Navigation::button('projectinstruments');
echo CAFEVDB\Navigation::button('instruments');
echo CAFEVDB\Navigation::button('all');
?>
<form id="personalsettings">
  <?php echo CAFEVDB\Navigation::button($_['settingscontrols']); ?>
</form>
</div>
<div class="cafevdb-general">
   <?php CAFEVDB\Projects::display(); ?>
</div>
<div id="dialog_holder"></div>
<div id="appsettings" class="popup topright hidden"></div>

