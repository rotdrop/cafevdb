<div id="controls">
   <input type="hidden" id="cafevdb_test_id" value="0" />
   <input type="text" id="cafevdb_add_blah" placeholder="<?php echo $l->t('Blah'); ?>" class="cafevdb_input" />
   <input type="submit" value="<?php echo $l->t('Save Blah'); ?>" id="cafevdb_add_submit" />
   </div>
   <div class="cafevdb_blah">
   Blah Blah Blah.
</div>
   <div class="cafevdb_foobar">
   <h1>This is an example app template</h1>
   <?php echo $l->t('Some Setting');?>: "<?php echo $_['somesetting']; ?>"
   
   <?php CAFEVDB_Projects::display(); ?>
</div>
