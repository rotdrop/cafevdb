<?php

global $Projekt;
global $ProjektId;
global $CAFEV_action;
global $MusikerId;

if (!isset($cafevclass)) {
  $cafevclass = 'cafev-nav-email';
}

echo '<div class="'.$cafevclass.'">';
echo '<div class="'.$cafevclass.'-inner">';
echo '<HR/><CENTER><H4>Press the buttons below to navigate to other pages</H4>
';

// We link back to the project page where we came from or to the
// detailed views.

if ($Projekt != '') {

  echo '<TABLE>
  <TR>
    </TD><TD ALIGN="CENTER">
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
    <TD WIDTH="50"></TD>
    <TD ALIGN="CENTER">
      <form name="CAFEV_form_besetzung" method="post" action="ProjektBesetzung.php">
        <input type="submit" name="" value="TODO platzieren">
        <input type="hidden" name="Action" value="TODO">
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
    <TD WIDTH="50"></TD>
    <TD ALIGN="CENTER">&Auml;nderungsw&uuml;nsche f&uuml;r Formulare</TD>
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
    <TD ALIGN="CENTER">Detailed Overview over all Musicians</TD>
    <TD WIDTH="50"></TD>
    <TD ALIGN="CENTER">Add New Instruments (without Delete)</TD>
    <TD WIDTH="50"></TD>
    <TD ALIGN="CENTER">&Auml;nderungsw&uuml;nsche f&uuml;r Formulare</TD>
    <TD WIDTH="50"></TD>
    <TD ALIGN="CENTER">Advanced Manipulation ... (Try it ;)</TD>
  </TR>
</TABLE>
';
}

echo "</CENTER>
<HR/></div></div>
";

?>

