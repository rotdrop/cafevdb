<?php

include_once('functions.php.inc');
include('config.php.inc');

// Check for misc (email) button
$op = CAFEVcgiValue('PME_sys_operation');
$lang = CAFEVcgiValue('PME_sys_translations');
$trans = array();
if (file_exists($lang)) {
  $trans = include($lang);
}
$miscphp   = $opts['miscphp'];
$misclabel = $opts['labels']['Misc'];
$miscop    = isset($trans[$misclabel]) ? $trans[$misclabel] : $misclabel;
if ($op == $miscop) {
  include($miscphp);
  exit;
}

?>

<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN"
		"http://www.w3.org/TR/html4/loose.dtd">
<html>
<head>
  <meta http-equiv="Content-Type" content="text/html; charset=utf-8">
  <title>CAFEV Projektdatenbank</title>
  <link rel="stylesheet" type="text/css" href="pme-blue.css" />
  <?php require_once("favicon.php"); ?>
  <?php require ("pme/enablejscal.html"); ?>
  <?php require ("pme/enabletinymce.html"); ?>
</head>
<body>

<?php
// Navigation-Buttons need ProjektId etc.
$cafevclass = 'cafev-nav-top';
include('NavigationButtons.php');

$cafevclass = 'cafev-pme';
echo '<div class="'.$cafevclass.'">'."\n";
include('ProjektBesetzungKern.php');
echo '</div>
';

$cafevclass = 'cafev-nav-bottom';
include('NavigationButtons.php');

?>

</body>
</html>
