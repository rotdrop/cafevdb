<?php

require_once __DIR__ . '/../../../tests/bootstrap.php';

require_once __DIR__ . "/../vendor/autoload.php";
require_once __DIR__ . "/../vendor-wrapped/autoload.php";

// otherwise Redaxo4Embedded's InstalledVersions is pulled in by autoload
$installedVersions = __DIR__ . "/../vendor/composer/InstalledVersions.php";
if (file_exists($installedVersions)) {
  require_once $installedVersions;
}
