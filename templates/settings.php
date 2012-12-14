<script type="text/javascript">
  <?php echo $_['jsscript']; ?>
</script>
<?php
$experttitle   = "Show a second button which leads to a dialog with ``advanced'' settings";
$tooltipstitle = "Control the display of tooltips. Warning: this works globally for all OwnCloud applications.";
?>
<div id="cafevdb">
  <fieldset class="personalblock">
    <strong>Camerata DB</strong><br />
    <input id="expertmode" type="checkbox" name="expertmode" <?php echo $_['expertmode'] == 'on' ? 'checked="checked"' : ''; ?> id="expertmode" title="<?php echo $l->t($experttitle) ?>"/>
    <label for="expertmode" title="<?php echo $l->t($experttitle) ?>"><?php echo $l->t('Expert-Mode') ?></label>
    <br />
    <input id="tooltips" type="checkbox" name="tooltips" <?php echo $_['tooltips'] == 'on' ? 'checked="checked"' : ''; ?> id="tooltips" title="<?php echo $l->t($tooltipstitle) ?>"/>
    <label for="tooltips" title="<?php echo $l->t($tooltipstitle) ?>"><?php echo $l->t('Tool-Tips') ?></label>
    <br />
    <div id="databasekey">
      <div id="passwordchanged"><?php echo $l->t('The encryption key was changed');?></div>
      <div id="passworderror"><?php echo $l->t('Unable to change the encryption key');?></div>
      <input type="password" id="pass1" name="olddbkey" placeholder="<?php echo $l->t('Current Key');?>" title="<?php echo $l->t('De-/Encryption key for the data-base, leave empty if the data-base is not encrypted.') ?>" />
      <input type="password" id="CAFEVkey" name="encryptionkey" placeholder="<?php echo $l->t('New Key');?>" placeholder="<?php echo $l->t('DB Encryption Key');?>" data-typetoggle="#show" title="<?php echo $l->t('De-/Encryption key for the data-base, leave empty if the data-base is not encrypted.') ?>" />
      <input type="checkbox" id="show" name="show" /><label for="show"><?php echo $l->t('show');?></label>
      <input id="passwordbutton" type="submit" value="<?php echo $l->t('Change Encryption Key');?>" title="<?php echo $l->t('De-/Encryption key for the data-base, leave empty if the data-base is not encrypted.') ?>" />
    </div>
    <br />
    <label for="exampletext" title="<?php echo $l->t('Example Text') ?>"><?php echo $l->t('Example') ?></label>
    <input type="text" name="exampletext" id="exampletext"
      value="<?php echo $_['exampletext'] ?>"
      placeholder="<?php echo $l->t('Example Text') ?>"
      title="<?php echo $l->t('Example Text') ?>" />
    <br />
    <input type="text" style="display:none;width:0%;float: left;" name="dummy" id="dummy" value="dummy" placeholder="dummy" title="<?php echo $l->t('Dummy') ?>/>
    <span class="msg"></span>
  </fieldset>
</div>
