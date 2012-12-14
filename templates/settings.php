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
    <label for="exampletext" title="<?php echo $l->t('Example') ?>"><?php echo $l->t('Example') ?></label>
    <input type="text" name="exampletext" id="exampletext"
      value="<?php echo $_['exampletext'] ?>"
      placeholder="<?php echo $l->t('Example Text') ?>"
      title="<?php echo $l->t('Example Text') ?>" />
    <br />
    <input type="text" style="display:none;width:0%;float: left;" name="dummy" id="dummy" value="dummy" placeholder="dummy" title="<?php echo $l->t('Dummy') ?>/>
    <span class="msg"></span>
  </fieldset>
</div>
