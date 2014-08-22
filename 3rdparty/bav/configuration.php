<?php

namespace malkusch\bav;

$configuration = new DefaultConfiguration();

$dbType = \OC_Config::getValue('dbtype', 'mysql');
$dbHost = \OC_Config::getValue('dbhost', 'localhost');
$dbName = \OC_Config::getValue('dbname', false);
$dbUser = \OC_Config::getValue('dbuser', false);
$dbPass = \OC_Config::getValue('dbpassword', false);

$dbURI = $dbType.':'.'host='.$dbHost.';dbname='.$dbName;

$pdo = new \PDO($dbURI, $dbUser, $dbPass);
$configuration->setDataBackendContainer(new PDODataBackendContainer($pdo));

$configuration->setUpdatePlan(new AutomaticUpdatePlan());

return $configuration;

?>