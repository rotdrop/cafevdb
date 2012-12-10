<?php $table = new CAFEVDB\BriefInstrumentation();
echo <<<__EOT__
<div id="controls">
  <form class="cafevdb-control" id="projectscontrol" method="post" action="?app=cafevdb">
    <input type="submit" name="" value="View all Projects"/>
    <input type="hidden" name="Action" value="-1"/>
    <input type="hidden" name="Template" value="projects"/>
  </form>
  <form class="cafevdb-control" id="briefcontrol" method="post" action="?app=cafevdb">
    <input type="submit" name="" value="Brief Instrumentation"/>
    <input type="hidden" name="Action" value="BriefInstrumentation"/>
    <input type="hidden" name="Template" value="brief-instrumentation"/>
    <input type="hidden" name="Project" value="$table->project"/>
    <input type="hidden" name="ProjectId" value="$table->projectId"/>
  </form>
  <form class="cafevdb-control" id="addcontrol" method="post" action="?app=cafevdb">
    <input type="submit" name="" value="Add more Musicians"/>
    <input type="hidden" name="Action" value="AddMusicians"/>
    <input type="hidden" name="Template" value="add-musicians"/>
    <input type="hidden" name="Project" value="$table->project"/>
    <input type="hidden" name="ProjectId" value="$table->projectId"/>
  </form>
</div>
__EOT__;
?>
<div class="cafevdb-general">
   <?php $table->display(); ?>
</div>
