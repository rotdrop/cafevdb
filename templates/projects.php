<script type="text/javascript">
  <?php print $_['jsscript']; ?>
</script>
<div id="controls">
<?php
use CAFEVDB\Navigation;
use CAFEVDB\Projects;
echo Navigation::button('projectinstruments');
echo Navigation::button('instruments');
echo Navigation::button('all');
?>
<form id="personalsettings">
  <?php echo Navigation::button($_['settingscontrols']); ?>
</form>
</div>
<div class="cafevdb-general" id="cafevdb-general">
  <div class="<?php echo Projects::CSS_PREFIX; ?>-header-box">
    <div class="<?php echo Projects::CSS_PREFIX; ?>-header">
      <?php echo Projects::headerText(); ?>
    </div>
    <?php echo Navigation::button($_['viewtoggle']); ?>
  </div>
   <?php Projects::display(); ?>
</div>
<div id="fullcalendar"></div>
<div id="dialog_holder"></div>
<div id="appsettings" class="popup topright hidden"></div>

