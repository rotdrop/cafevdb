<?php
use CAFEVDB\L;
use CAFEVDB\Config;
?>
<div class="personalblock">
  <form id="cafevdbadmin">
    <legend>
      <img class="svg cafevdblogo" src="<?php echo OCP\Util::imagePath(Config::APP_NAME, 'logo-greyf.svg'); ?>" >
      <strong>Camerata DB</strong><br />
    </legend>
    <input type="text" name="CAFEVgroup" id="CAFEVgroup" value="<?php echo $_['usergroup']; ?>" placeholder="<?php echo L::t('Group');?>" />
    <label for="CAFEVgroup"><?php echo L::t('User Group');?></label>
    <br/>
    <span class="msg"></span>
  </form>
</div>
