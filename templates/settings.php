<form id="cafevdb" method="post" action="?app=cafevdb">
   <fieldset class="personalblock">
     <strong>Camerata DB</strong><br />
   <input id="expertmode" type="checkbox" name="expertmode" <?php echo $_['expertmode'] == 'on' ? 'checked="checked"' : ''; ?> id="expertmode"/>
   <label for="expertmode"><?php echo $l->t('Expert Mode') ?></label>
<!--   <input type="text" name="expertmode" id="expertmode" value="<?php echo $_['expertmode']; ?>" placeholder="<?php echo $l->t('Expert Mode');?>" /> -->
   <br />
   <span class="msg"></span>
   </fieldset>
<!--   <input style="float:left;" type="submit" value="<?php echo $l->t('Save') ?>"/> -->
 </form>
