<!-- <form id="cafevdb" method="post" action="?app=cafevdb"> -->
<!-- <form id="cafevdb"> -->
   <fieldset class="personalblock">
     <strong>Camerata DB</strong><br />
   <input id="expertmode" type="checkbox" name="expertmode" <?php echo $_['expertmode'] == 'on' ? 'checked="checked"' : ''; ?> id="expertmode"/>
   <label for="expertmode"><?php echo $l->t('Expert Mode') ?></label>
   <br />
   <input type="text" name="example" id="example" value="example" placeholder="example" />
   <br />
   <input type="text" style="display:none;width:0%;float: left;" name="dummy" id="dummy" value="dummy" placeholder="dummy" />
   <span class="msg"></span>
   </fieldset>
<!--   <input style="float:left;" type="submit" value="<?php echo $l->t('Save') ?>"/> -->
<!-- </form> -->
