<div id="controls">
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
<div class="cafevdb-general">
   <?php CAFEVDB\Projects::display(); ?>
</div>
