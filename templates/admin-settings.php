<?php use CAFEVDB\L; ?>
<div class="personalblock">
  <strong>Camerata DB</strong><br />
  <form id="cafevdbadmin">
    <input type="text" name="CAFEVgroup" id="CAFEVgroup" value="<?php echo $_['usergroup']; ?>" placeholder="<?php echo L::t('Group');?>" />
    <label for="CAFEVgroup"><?php echo L::t('User Group');?></label>
    <br/>
    <span class="msg"></span>
  </form>
</div>
