<div id="controls">
  <form name="cafevdb-form-projects" method="post">
   <input type="submit" name="" value="Display all Musicians"/>
   <input type="hidden" name="Action" value="DisplayAllMusicians"/>
   <input type="hidden" name="Template" value="instrumentation"/>
  </form>
</div>
<div class="cafevdb-general">
   <?php CAFEVDB\Projects::display(); ?>
</div>
