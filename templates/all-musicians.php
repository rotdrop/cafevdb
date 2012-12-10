<div id="controls">
  <form class="cafevdb-control" id="projectscontrol" method="post">
    <input type="submit" name="" value="View all Projects"/>
    <input type="hidden" name="Action" value="-1"/>
    <input type="hidden" name="Template" value="projects"/>
  </form>
</div>
<div class="cafevdb-general">
   <?php $table = new CAFEVDB\Musicians(); $table->display(); ?>
</div>
