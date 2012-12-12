<div id="controls">
<?php
echo CAFEVDB\Navigation::button('projects');
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
  <div class="cafevdb-pme-header-box">
    <div class="cafevdb-pme-header">
      <H1>Hello World!</H1>
    </div>
  </div>
</div>

