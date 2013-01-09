<?php use CAFEVDB\L; ?>
<?php use CAFEVDB\Config; ?>
<div id="tabs-5" class="personalblock admin">
  <form id="develsettings">
    <fieldset id="devlinks"><legend><?php echo L::t('Links');?></legend>
      <input type="button" class="devlinktest" id="testphpmyadmin" name="testphpmyadmin" value="<?php echo L::t('Test Link'); ?>" title="<?php Config::toolTips('test-linktarget'); ?>"  />
      <input type="text" class="devlink" id="phpmyadmin" name="phpmyadmin" placeholder="<?php echo L::t('Link to %s', array('phpMyAdmin')); ?>" value="<?php echo $_['phpmyadmin']; ?>" title="<?php Config::toolTips('phpmyadmin-link'); ?>" />
      <label for="phpmyadmin"><?php echo L::t('Link to %s', array('phpMyAdmin')); ?></label>
      <br/>
      <input type="button" class="devlinktest" id="testsourcecode" name="testsourcecode" value="<?php echo L::t('Test Link'); ?>" title="<?php Config::toolTips('test-linktarget'); ?>"  />
  <input type="text" class="devlink" id="sourcecode" name="sourcecode" placeholder="<?php echo L::t('Link to the source-code'); ?>" value="<?php echo $_['sourcecode']; ?>" title="<?php Config::toolTips('sourcecode-link'); ?>" />
      <label for="phpmyadmin"><?php echo L::t('Link to the source-code'); ?></label>
      <br/>
      <input type="button" class="devlinktest" id="testsourcedocs" name="testsourcedocs" value="<?php echo L::t('Test Link'); ?>" title="<?php Config::toolTips('test-linktarget'); ?>"  />
      <input type="text" class="devlink" id="sourcedocs" name="sourcedocs" placeholder="<?php echo L::t('Link to the source-code documentation'); ?>" value="<?php echo $_['sourcedocs']; ?>" title="<?php Config::toolTips('sourcedocs-link'); ?>"/>
      <label for="phpmyadmin"><?php echo L::t('Link to the source-code documentation'); ?></label>
      <br/>
      <input type="button" class="devlinktest" id="testownclouddev" name="testownclouddev" value="<?php echo L::t('Test Link'); ?>" title="<?php Config::toolTips('test-linktarget'); ?>"  />
      <input type="text" class="devlink" id="ownclouddev"
         name="ownclouddev"
         placeholder="<?php echo L::t('Link to Owncloud Developer Information'); ?>"
         value="<?php echo $_['ownclouddev']; ?>"
         title="<?php Config::toolTips('ownclouddev-link'); ?>"/>
      <label for="phpmyadmin"><?php echo L::t('Owncloud developer documentation'); ?></label>
    </fieldset>
    <div class="statusmessage" id="msg"></span>  
  </form>
</div>
