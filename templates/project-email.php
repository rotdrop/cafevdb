<?php
$project = CAFEVDB\Util::cgiValue('Project');
$projectId = CAFEVDB\Util::cgiValue('ProjectId');
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
    <input type="hidden" name="Project" value="$project"/>
    <input type="hidden" name="ProjectId" value="$projectId"/>
  </form>
  <form class="cafevdb-control" id="detailedcontrol" method="post" action="?app=cafevdb">
    <input type="submit" name="" value="Detailed Instrumentation"/>
    <input type="hidden" name="Action" value="DetailedInstrumentation"/>
    <input type="hidden" name="Template" value="detailed-instrumentation"/>
    <input type="hidden" name="Project" value="$project"/>
    <input type="hidden" name="ProjectId" value="$projectId"/>
  </form>
  <form class="cafevdb-control" id="emailhistorycontrol" method="post" action="?app=cafevdb">
    <input type="submit" name="" value="Email History"/>
    <input type="hidden" name="Action" value="Email History"/>
    <input type="hidden" name="Template" value="email-history"/>
    <input type="hidden" name="Project" value="$project"/>
    <input type="hidden" name="ProjectId" value="$projectId"/>
  </form>
</div>
__EOT__;
?>
<div class="cafevdb-general">
   <?php CAFEVDB\Email::display(); ?>
</div>
