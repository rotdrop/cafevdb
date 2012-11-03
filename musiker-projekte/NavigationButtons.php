<?php

require_once('config.php.inc');
require_once('functions.php.inc');

$CAFEV_action = CAFEVcgiValue('Action');
$CAFEV_subaction = CAFEVcgiValue('Subaction');
$ProjektId = CAFEVcgiValue('ProjektId');
$Projekt = CAFEVcgiValue('Projekt');

if (!isset($cafevclass)) {
  $cafevclass = 'cafev-nav';
}

echo '<div class="'.$cafevclass.'">';
echo '<div class="'.$cafevclass.'-inner">';
echo '<HR/><CENTER><H4>Press the buttons below to navigate to other pages</H4>
';

if ($CAFEV_action == "TODO" || $CAFEV_action == "DisplayProjectsNeeds") {

// We link back to the project page where we came from or to the
// detailed views.

  if ($Projekt != '') {

    echo '<TABLE>
  <TR>
    <TD ALIGN="CENTER">
      <form name="CAFEV_form_besetzung" method="post" action="ProjektBesetzung.php">
        <input type="submit" name="" value="Detailed Display for'.$Projekt.'">
        <input type="hidden" name="Action" value="DisplayProjectMusicians">
        <input type="hidden" name="Projekt" value="'.$Projekt.'" />
        <input type="hidden" name="ProjektId" value="'.$ProjektId.'" />
      </form>
    </TD>
    <TD WIDTH="50"></TD>
    <TD ALIGN="CENTER">
      <form name="CAFEV_form_besetzung" method="post" action="ProjektBesetzung.php">
        <input type="submit" name="" value="Short Display for '.$Projekt.'">
        <input type="hidden" name="Action" value="ShortDisplayProjectMusicians">
        <input type="hidden" name="Projekt" value="'.$Projekt.'" />
        <input type="hidden" name="ProjektId" value="'.$ProjektId.'" />
      </form>
    </TD>
    <TD WIDTH="50"></TD>
    <TD ALIGN="CENTER">
      <form name="CAFEV_form_besetzung" method="post" action="Projekte.php">
        <input type="submit" name="" value="View All Projects">
      </form>
    </TD>
    <TD WIDTH="50"></TD>
    <TD ALIGN="CENTER">
      <form name="CAFEV_form_besetzung" method="post" action="ProjektBesetzung.php">
        <input type="submit" name="" value="Add New Instruments">
        <input type="hidden" name="Action" value="AddInstruments">
      </form>
    </TD>
    <TD WIDTH="50"></TD>
    <TD ALIGN="CENTER">
      <form name="CAFEV_form_besetzung" method="post" action="ProjektBesetzung.php">
        <input type="submit" name="" value="Display all Musicians">
        <input type="hidden" name="Action" value="DisplayMusicians">
      </form>
    </TD>
  </TR>
  <TR>
    <TD ALIGN="CENTER">Extended Display <B>Without</B> Delete</TD>
    <TD WIDTH="50"></TD>
    <TD ALIGN="CENTER">Short Display <B>With</B> Delete</TD>
    <TD WIDTH="50"></TD>
    <TD ALIGN="CENTER">Overview over all Projects</TD>
    <TD WIDTH="50"></TD>
    <TD ALIGN="CENTER">Add New Instruments (without Delete)</TD>
    <TD WIDTH="50"></TD>
    <TD ALIGN="CENTER">Detailed Overview over Musicians</TD>
  </TR>
</TABLE>';

  } else {
    echo '<TABLE>
  <TR>
     <TD ALIGN="CENTER">
       <form name="CAFEV_form_besetzung" method="post" action="Projekte.php">
         <input type="submit" name="" value="View All Projects">
       </form>
     </TD>
     <TD WIDTH="50"></TD>
     <TD ALIGN="CENTER">
     <form name="CAFEV_form_besetzung" method="post" action="ProjektBesetzung.php">
       <input type="submit" name="" value="View all Musicians">
     </form>
     </TD>
    <TD WIDTH="50"></TD>
    <TD ALIGN="CENTER">
      <form name="CAFEV_form_besetzung" method="post" action="ProjektBesetzung.php">
        <input type="submit" name="" value="Add New Instruments">
        <input type="hidden" name="Action" value="AddInstruments">
      </form>
    </TD>
    <TD WIDTH="50"></TD>
    <TD ALIGN="CENTER">
      <form name="CAFEV_form_besetzung" method="post" action="'.$opts['phpmyadmin'].'">
        <input type="submit" name="" value="Go to PHPMyAdmin">
      </form>
    </TD>
  </TR>
  <TR>
    <TD ALIGN="CENTER">Overview over all Projects</TD>
    <TD WIDTH="50"></TD>
    <TD ALIGN="CENTER">Detailed Overview over all Musicians</TD>
    <TD WIDTH="50"></TD>
    <TD ALIGN="CENTER">Add New Instruments (without Delete)</TD>
    <TD WIDTH="50"></TD>
    <TD ALIGN="CENTER">Advanced Manipulation ... (Try it ;)</TD>
  </TR>
</TABLE>
';
  }

} else if ($CAFEV_action == "AddInstruments") {
  // show known instruments
  // Link to:
  // * all musicians
  // * all projects
  // * phpmyadmin

  echo '<TABLE>
  <TR>
    <TD ALIGN="CENTER">
      <form name="CAFEV_form_besetzung" method="post" action="Projekte.php">
        <input type="submit" name="" value="View All Projects">
      </form>
    </TD>
    <TD WIDTH="50"></TD>
    <TD ALIGN="CENTER">
      <form name="CAFEV_form_besetzung" method="post" action="ProjektBesetzung.php">
        <input type="submit" name="" value="Display all Musicians">
        <input type="hidden" name="Action" value="DisplayMusicians">
      </form>
    </TD>
    <TD WIDTH="50"></TD>
    <TD ALIGN="CENTER">
      <form name="CAFEV_form_besetzung" method="post" action="ProjektBesetzung.php">
        <input type="submit" name="" value="TODO platzieren">
        <input type="hidden" name="Action" value="TODO">
      </form>
    </TD>
    <TD WIDTH="50"></TD>
    <TD ALIGN="CENTER">
      <form name="CAFEV_form_besetzung" method="post" action="'.$opts['phpmyadmin'].'">
        <input type="submit" name="" value="Go to PHPMyAdmin">
      </form>
    </TD>
  </TR>
  <TR>
    <TD ALIGN="CENTER">Overview over all Projects</TD>
    <TD WIDTH="50"></TD>
    <TD ALIGN="CENTER">Detailed Overview over Musicians</TD>
    <TD WIDTH="50"></TD>
    <TD ALIGN="CENTER">&Auml;nderungsw&uuml;nsche f&uuml;r Formulare</TD>
    <TD WIDTH="50"></TD>
    <TD ALIGN="CENTER">Advanced Manipulation ... (Try it ;)</TD>
  </TR>
</TABLE>';


} else if ($CAFEV_action == "DisplayMusicians") {
  // Show all musicians
  // Show links to
  // * all projects
  // * instruments
  // * TODO
  // * phpmyadmin

  echo '<TABLE>
  <TR>
    <TD ALIGN="CENTER">
      <form name="CAFEV_form_besetzung" method="post" action="Projekte.php">
        <input type="submit" name="" value="View All Projects">
      </form>
    </TD>
    <TD WIDTH="50"></TD>
    <TD ALIGN="CENTER">
      <form name="CAFEV_form_besetzung" method="post" action="ProjektBesetzung.php">
        <input type="submit" name="" value="Add New Instruments">
        <input type="hidden" name="Action" value="AddInstruments">
      </form>
    </TD>
    <TD WIDTH="50"></TD>
    <TD ALIGN="CENTER">
      <form name="CAFEV_form_besetzung" method="post" action="ProjektBesetzung.php">
        <input type="submit" name="" value="TODO platzieren">
        <input type="hidden" name="Action" value="TODO">
      </form>
    </TD>
    <TD WIDTH="50"></TD>
    <TD ALIGN="CENTER">
      <form name="CAFEV_form_besetzung" method="post" action="'.$opts['phpmyadmin'].'">
        <input type="submit" name="" value="Go to PHPMyAdmin">
      </form>
    </TD>
  </TR>
  <TR>
    <TD ALIGN="CENTER">Overview over all Projects</TD>
    <TD WIDTH="50"></TD>
    <TD ALIGN="CENTER">Add New Instruments (without Delete)</TD>
    <TD WIDTH="50"></TD>
    <TD ALIGN="CENTER">&Auml;nderungsw&uuml;nsche f&uuml;r Formulare</TD>
    <TD WIDTH="50"></TD>
    <TD ALIGN="CENTER">Advanced Manipulation ... (Try it ;)</TD>
  </TR>
</TABLE>';

} else if ($CAFEV_action == "DisplayProjectMusicians") {
  // Detailed list, show the PROJEKTView table
  // Link to
  // * "add script": displays all musicians not already subscribed
  // * short project list
  // * overview over all projects
  // * instrument table
  // * all musicians
  // * email form

  echo '<TABLE>
  <TR>
    <TD ALIGN="CENTER">
      <form name="CAFEV_form_besetzung" method="post" action="ProjektBesetzung.php">
        <input type="submit" name="" value="Add more Musicians to '.$Projekt.'">
        <input type="hidden" name="Action" value="AddMusicians">
        <input type="hidden" name="Projekt" value="'.$Projekt.'" />
        <input type="hidden" name="ProjektId" value="'.$ProjektId.'" />
      </form>
    </TD>
    <TD WIDTH="50"></TD>
    <TD ALIGN="CENTER">
      <form name="CAFEV_form_besetzung" method="post" action="ProjektBesetzung.php">
        <input type="submit" name="" value="Short Display for '.$Projekt.'">
        <input type="hidden" name="Action" value="ShortDisplayProjectMusicians">
        <input type="hidden" name="Projekt" value="'.$Projekt.'" />
        <input type="hidden" name="ProjektId" value="'.$ProjektId.'" />
      </form>
    </TD>
    <TD WIDTH="50"></TD>
    <TD ALIGN="CENTER">
      <form name="CAFEV_form_besetzung" method="post" action="ProjektBesetzung.php">
        <input type="submit" name="" value="Instrumentation for '.$Projekt.'">
        <input type="hidden" name="Action" value="DisplayProjectsNeeds">
        <input type="hidden" name="Projekt" value="'.$Projekt.'" />
        <input type="hidden" name="ProjektId" value="'.$ProjektId.'" />
      </form>
    </TD>
    <TD WIDTH="50"></TD>
    <TD ALIGN="CENTER">
      <form name="CAFEV_form_besetzung" method="post" action="Projekte.php">
        <input type="submit" name="" value="View All Projects">
      </form>
    </TD>
    <TD WIDTH="50"></TD>
    <TD ALIGN="CENTER">
      <form name="CAFEV_form_besetzung" method="post" action="ProjektBesetzung.php">
        <input type="submit" name="" value="Add New Instruments">
        <input type="hidden" name="Action" value="AddInstruments">
      </form>
    </TD>
  </TR>
  <TR>
    <TD ALIGN="CENTER">Choose from Database</TD>
    <TD WIDTH="50"></TD>
    <TD ALIGN="CENTER">Short Display <B>With</B> Delete</TD>
    <TD WIDTH="50"></TD>
    <TD ALIGN="CENTER">View the desired Instrumentation for '.$Projekt.'</TD>
    <TD WIDTH="50"></TD>
    <TD ALIGN="CENTER">Overview over all Projects</TD>
    <TD WIDTH="50"></TD>
    <TD ALIGN="CENTER">Add New Instruments (without Delete)</TD>
  </TR>
</TABLE>';


} else if ($CAFEV_action == "ShortDisplayProjectMusicians") {
  // Short project list, may delete people or change instruments
  // Link to
  // * add-script
  // * detailed view
  // * email form
  // * list over all projects
  // * instruments
  // * all musicians

  echo '<TABLE>
  <TR>
    <TD ALIGN="CENTER">
      <form name="CAFEV_form_besetzung" method="post" action="ProjektBesetzung.php">
        <input type="submit" name="" value="Add more Musicians to '.$Projekt.'">
        <input type="hidden" name="Action" value="AddMusicians">
        <input type="hidden" name="Projekt" value="'.$Projekt.'" />
        <input type="hidden" name="ProjektId" value="'.$ProjektId.'" />
      </form>
    </TD>
    <TD WIDTH="50"></TD>
    <TD ALIGN="CENTER">
      <form name="CAFEV_form_besetzung" method="post" action="ProjektBesetzung.php">
        <input type="submit" name="" value="Detailed Display for '.$Projekt.'">
        <input type="hidden" name="Action" value="DisplayProjectMusicians">
        <input type="hidden" name="Projekt" value="'.$Projekt.'" />
        <input type="hidden" name="ProjektId" value="'.$ProjektId.'" />
      </form>
    </TD>
    <TD WIDTH="50">
    </TD><TD ALIGN="CENTER">
      <form name="CAFEV_form_besetzung" method="post" action="ProjektBesetzung.php">
        <input type="submit" name="" value="Instrumentation for '.$Projekt.'">
        <input type="hidden" name="Action" value="DisplayProjectsNeeds">
        <input type="hidden" name="Projekt" value="'.$Projekt.'" />
        <input type="hidden" name="ProjektId" value="'.$ProjektId.'" />
      </form>
    </TD>
    <TD WIDTH="50"></TD>
    <TD ALIGN="CENTER">
      <form name="CAFEV_form_besetzung" method="post" action="Projekte.php">
        <input type="submit" name="" value="View All Projects">
      </form>
    </TD>
    <TD WIDTH="50"></TD>
    <TD ALIGN="CENTER">
      <form name="CAFEV_form_besetzung" method="post" action="ProjektBesetzung.php">
        <input type="submit" name="" value="Add New Instruments">
        <input type="hidden" name="Action" value="AddInstruments">
      </form>
    </TD>
  </TR>
  <TR>
    <TD ALIGN="CENTER">Choose from Database</TD>
    <TD WIDTH="50"></TD>
    <TD ALIGN="CENTER">Extended Display <B>Without</B> Delete</TD>
    <TD WIDTH="50"></TD>
    <TD ALIGN="CENTER">View the desired Instrumentation for '.$Projekt.'</TD>
    <TD WIDTH="50"></TD>
    <TD ALIGN="CENTER">Overview over all Projects</TD>
    <TD WIDTH="50"></TD>
    <TD ALIGN="CENTER">Add New Instruments (without Delete)</TD>
  </TR>
</TABLE>';

} else if ($CAFEV_action == "AddOneMusician" || $CAFEV_action == "ChangeOneMusician") {
  // Acting on a single victim.

  echo '<TABLE>
  <TR>
    <TD ALIGN="CENTER">
      <form name="CAFEV_form_besetzung" method="post" action="ProjektBesetzung.php">
        <input type="submit" name="" value="Add more Musicians to '.$Projekt.'">
        <input type="hidden" name="Action" value="AddMusicians">
        <input type="hidden" name="Projekt" value="'.$Projekt.'" />
        <input type="hidden" name="ProjektId" value="'.$ProjektId.'" />
      </form>
    </TD>
    <TD WIDTH="50"></TD><TD ALIGN="CENTER">
      <form name="CAFEV_form_besetzung" method="post" action="ProjektBesetzung.php">
        <input type="submit" name="" value="Detailed Display for '.$Projekt.'">
        <input type="hidden" name="Action" value="DisplayProjectMusicians">
        <input type="hidden" name="Projekt" value="'.$Projekt.'" />
        <input type="hidden" name="ProjektId" value="'.$ProjektId.'" />
      </form>
    </TD>
    <TD WIDTH="50"></TD><TD ALIGN="CENTER">
      <form name="CAFEV_form_besetzung" method="post" action="ProjektBesetzung.php">
        <input type="submit" name="" value="Short Display for '.$Projekt.'">
        <input type="hidden" name="Action" value="ShortDisplayProjectMusicians">
        <input type="hidden" name="Projekt" value="'.$Projekt.'" />
        <input type="hidden" name="ProjektId" value="'.$ProjektId.'" />
      </form>
    </TD>
    <TD WIDTH="50"></TD><TD ALIGN="CENTER">
      <form name="CAFEV_form_besetzung" method="post" action="Projekte.php">
        <input type="submit" name="" value="View All Projects">
      </form>
    </TD>
    <TD WIDTH="50"></TD>
    <TD ALIGN="CENTER">
      <form name="CAFEV_form_besetzung" method="post" action="ProjektBesetzung.php">
        <input type="submit" name="" value="Add New Instruments">
        <input type="hidden" name="Action" value="AddInstruments">
      </form>
    </TD>
    <TD WIDTH="50"></TD>
    <TD ALIGN="CENTER">
      <form name="CAFEV_form_besetzung" method="post" action="ProjektBesetzung.php">
        <input type="submit" name="" value="Display all Musicians">
        <input type="hidden" name="Action" value="DisplayMusicians">
      </form>
    </TD>
  </TR>
  <TR>
    <TD ALIGN="CENTER">Choose from Database</TD>
    <TD WIDTH="50"></TD>
    <TD ALIGN="CENTER">Extended Display <B>Without</B> Delete</TD>
    <TD WIDTH="50"></TD>
    <TD ALIGN="CENTER">Short Display <B>With</B> Delete</TD>
    <TD WIDTH="50"></TD>
    <TD ALIGN="CENTER">Overview over all Projects</TD>
    <TD WIDTH="50"></TD>
    <TD ALIGN="CENTER">Add New Instruments (without Delete)</TD>
    <TD WIDTH="50"></TD>
    <TD ALIGN="CENTER">Detailed Overview over Musicians</TD>
  </TR>
</TABLE>';

} else if ($CAFEV_action == "AddMusicians") {
  // Table with all musicians not in a project, with ability to send
  // to them
  // Link to:
  // * Detailed view for this project
  // * Short view for this project
  // * All projects
  // * instruments
  // * all musicians


  // Choose from the database
  echo '<TABLE>
  <TR>
    </TD><TD ALIGN="CENTER">
      <form name="CAFEV_form_besetzung" method="post" action="ProjektBesetzung.php">
        <input type="submit" name="" value="Detailed Display for '.$Projekt.'">
        <input type="hidden" name="Action" value="DisplayProjectMusicians">
        <input type="hidden" name="Projekt" value="'.$Projekt.'" />
        <input type="hidden" name="ProjektId" value="'.$ProjektId.'" />
      </form>
    </TD>
    <TD WIDTH="50"></TD>
    <TD ALIGN="CENTER">
      <form name="CAFEV_form_besetzung" method="post" action="ProjektBesetzung.php">
        <input type="submit" name="" value="Short Display for '.$Projekt.'">
        <input type="hidden" name="Action" value="ShortDisplayProjectMusicians">
        <input type="hidden" name="Projekt" value="'.$Projekt.'" />
        <input type="hidden" name="ProjektId" value="'.$ProjektId.'" />
      </form>
    </TD>
    <TD WIDTH="50"></TD>
    <TD ALIGN="CENTER">
      <form name="CAFEV_form_besetzung" method="post" action="Projekte.php">
        <input type="submit" name="" value="View All Projects">
      </form>
    </TD>
    <TD WIDTH="50"></TD>
    <TD ALIGN="CENTER">
      <form name="CAFEV_form_besetzung" method="post" action="ProjektBesetzung.php">
        <input type="submit" name="" value="Add New Instruments">
        <input type="hidden" name="Action" value="AddInstruments">
      </form>
    </TD>
    <TD WIDTH="50"></TD>
    <TD ALIGN="CENTER">
      <form name="CAFEV_form_besetzung" method="post" action="ProjektBesetzung.php">
        <input type="submit" name="" value="Display all Musicians">
        <input type="hidden" name="Action" value="DisplayMusicians">
      </form>
    </TD>
  </TR>
  <TR>
    <TD ALIGN="CENTER">Extended Display <B>Without</B> Delete</TD>
    <TD WIDTH="50"></TD>
    <TD ALIGN="CENTER">Short Display <B>With</B> Delete</TD>
    <TD WIDTH="50"></TD>
    <TD ALIGN="CENTER">Overview over all Projects</TD>
    <TD WIDTH="50"></TD>
    <TD ALIGN="CENTER">Add New Instruments (without Delete)</TD>
    <TD WIDTH="50"></TD>
    <TD ALIGN="CENTER">Detailed Overview over Musicians</TD>
  </TR>
</TABLE>';

}

echo '</CENTER>
<HR/></div></div>
';

?>    

