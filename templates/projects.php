<div id="controls">
<?php
echo CAFEVDB\Navigation::button('projectinstruments');
echo CAFEVDB\Navigation::button('instruments');
echo CAFEVDB\Navigation::button('all');
?>
<form id="personalsettings">
  <button class="settings generalsettings" title="<?php echo $l->t('Settings'); ?>"><img class="svg" src="<?php echo OCP\Util::imagePath('core', 'actions/settings.svg'); ?>" alt="<?php echo $l->t('Settings'); ?>" /></button>
</form>
</div>
<div id="appsettings" class="popup topright hidden"></div>
<div class="cafevdb-general">
   <?php CAFEVDB\Projects::display(); ?>
</div>

