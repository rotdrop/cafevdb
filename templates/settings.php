<script type="text/javascript">
  <?php echo $_['jsscript']; ?>
</script>
<?php
$experttitle   = "Show a second button which leads to a dialog with ``advanced'' settings";
$tooltipstitle = "Control the display of tooltips. Warning: this works globally for all OwnCloud applications.";
?>
<div class="personalblock">
  <form id="cafevdb">
    <strong>Camerata DB</strong><br />
    <input id="expertmode" type="checkbox" name="expertmode" <?php echo $_['expertmode'] == 'on' ? 'checked="checked"' : ''; ?> id="expertmode" title="<?php echo $l->t($experttitle) ?>"/>
    <label for="expertmode" title="<?php echo $l->t($experttitle) ?>"><?php echo $l->t('Expert-Mode') ?></label>
    <br />
    <input id="tooltips" type="checkbox" name="tooltips" <?php echo $_['tooltips'] == 'on' ? 'checked="checked"' : ''; ?> id="tooltips" title="<?php echo $l->t($tooltipstitle) ?>"/>
    <label for="tooltips" title="<?php echo $l->t($tooltipstitle) ?>"><?php echo $l->t('Tool-Tips') ?></label>
    <br />
    <label for="exampletext" title="<?php echo $l->t('Example Text') ?>"><?php echo $l->t('Example') ?></label>
    <input type="text" name="exampletext" id="exampletext"
      value="<?php echo $_['exampletext'] ?>"
      placeholder="<?php echo $l->t('Example Text') ?>"
      title="<?php echo $l->t('Example Text') ?>" />
    <br />
    <input type="text" style="display:none;width:0%;float: left;" name="dummy" id="dummy" value="dummy" placeholder="dummy" title="<?php echo $l->t('Dummy') ?>/>
    <span class="msg"></span>
  </form>
  <form id="cafevdbkey">
    <div id="changed"><?php echo $l->t('The encryption key has been set successfully.');?></div>
    <div id="error"><?php echo $l->t('Unable to set the encryption key.');?></div>
    <input type="password" id="dbkey1" name="dbkey1" placeholder="<?php echo $l->t('Own Password');?>" />
    <input type="password" id="encryptionkey" name="encryptionkey" value="<?php echo CAFEVDB\Config::getEncryptionKey(); ?>" placeholder="<?php echo $l->t('Encryption Key');?>" data-typetoggle="#cafevdbkeyshow" />
    <input type="checkbox" id="cafevdbkeyshow" name="show" /><label for="cafevdbkeyshow"><?php echo $l->t('show');?></label>
    <input id="button" type="button" value="<?php echo $l->t('Change Encryption Key');?>" />
  </form>
</div>
