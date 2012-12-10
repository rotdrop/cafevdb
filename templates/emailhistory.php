<div id="controls">
$project = CAFEVDB\Util::cgiValue('Project');
$projectId = CAFEVDB\Util::cgiValue('ProjectId',-1);
if ($projectId < 0) {
  echo <<<__EOT__
<div id="controls">
  <form class="cafevdb-control" id="projectscontrol" method="post" action="?app=cafevdb">
    <input type="submit" name="" value="View all Projects"/>
    <input type="hidden" name="Action" value="-1"/>
    <input type="hidden" name="Template" value="projects"/>
  </form>
  <form class="cafevdb-control" id="allcontrol" method="post" action="?app=cafevdb">
   <input type="submit" name="" value="Display all Musicians"/>
   <input type="hidden" name="Action" value="DisplayAllMusicians"/>
   <input type="hidden" name="Template" value="all-musicians"/>
  </form>
  <form class="cafevdb-control" id="emailcontrol" method="post" action="?app=cafevdb">
   <input type="submit" name="" value="Em@il"/>
   <input type="hidden" name="Action" value="Email"/>
   <input type="hidden" name="Template" value="email"/>
  </form>
</div>
__EOT__;
} else {
<div id="controls">
  <form class="cafevdb-control" id="projectscontrol" method="post" action="?app=cafevdb">
    <input type="submit" name="" value="View all Projects"/>
    <input type="hidden" name="Action" value="-1"/>
    <input type="hidden" name="Template" value="projects"/>
  </form>
  <form class="cafevdb-control" id="emailcontrol" method="post" action="?app=cafevdb">
    <input type="submit" name="" value="Em@il"/>
    <input type="hidden" name="Action" value="Email"/>
    <input type="hidden" name="Template" value="email"/>
    <input type="hidden" name="Project" value="$project"/>
    <input type="hidden" name="ProjectId" value="$projectId"/>
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
}
?>
<div class="cafevdb-general">
   <?php CAFEVDB\Email::displayHistory(); ?>
</div>
