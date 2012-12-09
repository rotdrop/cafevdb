<div id="controls">
  <form name="cafevdb-form-projects" method="post">
    <input type="submit" name="" value="View all Projects"/>
    <input type="hidden" name="Action" value="-1"/>
    <input type="hidden" name="Template" value="projects"/>
  </form>
  <form name="cafevdb-form-projects" method="post">
    <input type="submit" name="" value="View all Projects"/>
    <input type="hidden" name="Action" value="DetailedInstrumentation"/>
    <input type="hidden" name="Template" value="detailed-instrumentation"/>
  </form>
</div>
<div class="cafevdb-general">
  <?php CAFEVDB\BriefInstrumentation::display(); ?>
</div>
