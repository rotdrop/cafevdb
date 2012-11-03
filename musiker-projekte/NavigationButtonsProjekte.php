<?php

if (!isset($cafevclass)) {
  $cafevclass = 'cafev-nav-proj';
}

echo '<div class="'.$cafevclass.'">';
echo '<div class="'.$cafevclass.'-inner">';
echo '<HR/><CENTER>
<H4>Press the buttons below to navigate to other pages</H4>
<TABLE>
  <TR>
    <TD ALIGN="CENTER">
      <form name="CAFEV_form_besetzung" method="post" action="ProjektBesetzung.php">
        <input type="submit" name="" value="Instrumentation">
        <input type="hidden" name="Action" value="DisplayProjectsNeeds">
        <input type="hidden" name="Projekt" value="">
        <input type="hidden" name="ProjektId" value="-1">
      </form>
    </TD>
    <TD WIDTH="50"></TD>
    <TD ALIGN="CENTER">
     <form name="CAFEV_form_besetzung" method="post" action="ProjektBesetzung.php">
       <input type="submit" name="" value="View all Musicians">
       <input type="hidden" name="Projekt" value="">
       <input type="hidden" name="ProjektId" value="-1">
     </form>
     </TD>
    <TD WIDTH="50"></TD>
    <TD ALIGN="CENTER">
      <form name="CAFEV_form_besetzung" method="post" action="EMail.php">
        <input type="submit" name="" value="Email Form">
        <input type="hidden" name="Table" value="Musiker">
        <input type="hidden" name="Projekt" value="">
        <input type="hidden" name="ProjektId" value="-1">
      </form>
    </TD>
    <TD WIDTH="50"></TD>
    <TD ALIGN="CENTER">
      <form name="CAFEV_form_besetzung" method="post" action="ProjektBesetzung.php">
        <input type="submit" name="" value="Add New Instruments">
        <input type="hidden" name="Action" value="AddInstruments">
        <input type="hidden" name="Projekt" value="">
        <input type="hidden" name="ProjektId" value="-1">
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
    <TD ALIGN="CENTER">Overview over the Instrumenations</TD>
    <TD WIDTH="50"></TD>
    <TD ALIGN="CENTER">Detailed Overview over all Musicians</TD>
    <TD WIDTH="50"></TD>
    <TD ALIGN="CENTER">Export or send Emails</TD>
    <TD WIDTH="50"></TD>
    <TD ALIGN="CENTER">Add New Instruments (without Delete)</TD>
    <TD WIDTH="50"></TD>
    <TD ALIGN="CENTER">&Auml;nderungsw&uuml;nsche f&uuml;r Formulare</TD>
    <TD WIDTH="50"></TD>
    <TD ALIGN="CENTER">Advanced Manipulation ... (Try it ;)</TD>
  </TR>
</TABLE>
</CENTER><HR/></div></div>';

?>

