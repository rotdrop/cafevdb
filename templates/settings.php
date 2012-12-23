<?php use CAFEVDB\L; ?>
<script type="text/javascript">
  <?php echo $_['jsscript']; ?>
</script>
<?php
$tooltipstitle = "Control the display of tooltips. Warning: this works globally for all OwnCloud applications.";
$experttitle   = "Show a second button which leads to a dialog with ``advanced'' settings";
$debugtitle    = "Show a certain amount of debug information, normally not needed.";
?>
<div class="personalblock">
  <form id="cafevdb">
    <strong>Personal Settings for Camerata DB</strong><br />
    <input id="tooltips" type="checkbox" name="tooltips" <?php echo $_['tooltips'] == 'on' ? 'checked="checked"' : ''; ?> id="tooltips" title="<?php echo L::t($tooltipstitle) ?>"/>
    <label for="tooltips" title="<?php echo L::t($tooltipstitle) ?>"><?php echo L::t('Tool-Tips') ?></label>
    <br />
    <input id="expertmode" type="checkbox" name="expertmode" <?php echo $_['expertmode'] == 'on' ? 'checked="checked"' : ''; ?> id="expertmode" title="<?php echo L::t($experttitle) ?>"/>
    <label for="expertmode" title="<?php echo L::t($experttitle) ?>"><?php echo L::t('Expert-Mode') ?></label>
    <br />
    <input id="debugmode" type="checkbox" name="debugmode" <?php echo $_['debugmode'] == 'on' ? 'checked="checked"' : ''; ?> id="debugmode" title="<?php echo L::t($debugtitle) ?>"/>
    <label for="debugmode" title="<?php echo L::t($experttitle) ?>"><?php echo L::t('Debug-Mode') ?></label>
    <br />
    <label for="exampletext" title="<?php echo L::t('Example Text') ?>"><?php echo L::t('Example') ?></label>
    <input type="text" name="exampletext" id="exampletext"
      value="<?php echo $_['exampletext'] ?>"
      placeholder="<?php echo L::t('Example Text') ?>"
      title="<?php echo L::t('Example Text') ?>" />
    <br />
    <input type="text" style="display:none;width:0%;float: left;" name="dummy" id="dummy" value="dummy" placeholder="dummy" title="<?php echo L::t('Dummy') ?>/>
    <span class="msg"></span>
  </form>
  <form id="cafevdbuserkey">
    <div id="changed"><?php echo L::t('The encryption key has been set successfully.');?></div>
    <div id="error"><?php echo L::t('Unable to set the encryption key.');?></div>
    <input type="password" id="password" name="password" placeholder="<?php echo L::t('Own Password');?>" />
    <input type="password" id="encryptionkey" name="encryptionkey" value="<?php echo (true ? '' : $_['encryptionkey']); ?>" placeholder="<?php echo L::t('Encryption Key');?>" data-typetoggle="#cafevdbkey #show" />
    <input type="checkbox" id="show" name="show" /><label for="show"><?php echo L::t('show');?></label>
    <input id="button" type="button" value="<?php echo L::t('Set Encryption Key');?>" />
  </form>
</div>
