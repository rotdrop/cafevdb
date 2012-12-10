<?php
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
  <form class="cafevdb-control" id="emailhistorycontrol" method="post" action="?app=cafevdb">
   <input type="submit" name="" value="Email History"/>
   <input type="hidden" name="Action" value="Email History"/>
   <input type="hidden" name="Template" value="email-history"/>
  </form>
</div>
__EOT__;
?>
<div class="cafevdb-general">
   <?php CAFEVDB\Email::display(); ?>
</div>
