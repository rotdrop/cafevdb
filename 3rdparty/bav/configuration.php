<?php

namespace malkusch\bav;

$configuration = new DefaultConfiguration();

$pdo = new \PDO("mysql:host=localhost;dbname=bav_data", "bav", "ZrPHHEFUKE3uX5p6");
$configuration->setDataBackendContainer(new PDODataBackendContainer($pdo));

$configuration->setUpdatePlan(new AutomaticUpdatePlan());

return $configuration;

?>