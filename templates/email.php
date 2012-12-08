<div id="controls">
   <input type="hidden" id="cafevdb_test_id" value="0" />
   <input type="text" id="cafevdb_add_blah" placeholder="<?php echo $l->t('Blah'); ?>" class="cafevdb_input" />
   <input type="submit" value="<?php echo $l->t('Save Blah'); ?>" id="cafevdb_add_submit" />
</div>
<div class="cafevdb-general">
   <?php CAFEVDB\Email::display(); ?>
</div>
