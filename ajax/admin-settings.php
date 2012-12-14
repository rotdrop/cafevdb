<?php
/**
 * Copyright (c) 2011, Frank Karlitschek <karlitschek@kde.org>
 * Copyright (c) 2012, Florian Hülsmann <fh@cbix.de>
 * This file is licensed under the Affero General Public License version 3 or later.
 * See the COPYING-README file.
 */

OCP\User::checkAdminUser();
OCP\JSON::callCheck();

if (isset($_POST['CAFEVgroup'])) {
  $value = $_POST['CAFEVgroup'];
  OCP\Config::setSystemValue( 'CAFEVgroup', $value);
  echo "group: $value";
  return;
}
if (isset($_POST['CAFEVdbserver'])) {
  $value = $_POST['CAFEVdbserver'];
  OCP\Config::setSystemValue( 'CAFEVdbserver', $value);
  echo "dbserver: $value";
  return;
}
if (isset($_POST['CAFEVdbname'])) {
  $value = $_POST['CAFEVdbname'];
  OCP\Config::setSystemValue( 'CAFEVdbname', $value);
  echo "dbname: $value";
  return;
}
if (isset($_POST['CAFEVdbuser'])) {
  $value = $_POST['CAFEVdbuser'];
  OCP\Config::setSystemValue( 'CAFEVdbuser', $value);
  echo "dbuser: $value";
  return;
}
if (isset($_POST['CAFEVdbpasswd'])) {
  $value = $_POST['CAFEVdbpasswd'];
  OCP\Config::setSystemValue( 'CAFEVdbpasswd', $value);
  echo "dbpasswd";
  return;
}

echo 'false';
