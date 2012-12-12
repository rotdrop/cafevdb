<div id="cafevdb">
 <fieldset class="personalblock">
   <strong>Camerata DB</strong><br />
  <input id="expertmode" type="checkbox" name="expertmode" <?php echo $_['expertmode'] == 'on' ? 'checked="checked"' : ''; ?> id="expertmode"/>
  <label for="expertmode"><?php echo $l->t('Expert Mode') ?></label>
  <br />
  <label for="exampletext"><?php echo $l->t('Example') ?></label>
  <input type="text" name="exampletext" id="exampletext"
    value="<?php echo $_['exampletext'] ?>"
    placeholder="<?php echo $l->t('Example Text') ?>" />
  <br />
  <input type="text" style="display:none;width:0%;float: left;" name="dummy" id="dummy" value="dummy" placeholder="dummy" />
  <span class="msg"></span>
  </fieldset>
</div>
