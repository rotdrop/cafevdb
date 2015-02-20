<?php

$routesFile = __DIR__.'/../routeslist.txt';

if(file_exists($routesFile)) {
  $routes = preg_split('/\s+/', trim(file_get_contents($routesFile)));
} else {
  echo "Routes-file\n\n".$routesFiles."\n\ndoes not seem to exist.\n";
}

/* Generate route entries of this form:
 *

$this->create(UNIQUE_ROUTE_ID, NAME_OF_FILE_RELATVE_TO_APP)
  ->actionInclude(APP_NAME.NAME_OF_FILE_RELATIVE_TO_APP)

 * In principle, the route path could even be different but this way
 * things just work as before with OC7
 */

$appName = 'cafevdb';

$routeString = '';

foreach($routes as $route) {
  $fullPath = $appName.'/'.$route;
  $routeId = str_replace('/', '_', $fullPath);
  $routeString .= '$this->create("'.$routeId.'", "'.$route.'")
  ->actionInclude("'.$fullPath.'");

';
}

$routeString = '<?php
'.$routeString.'
?>
';

file_put_contents(__DIR__.'/../appinfo/autoroutes.php', $routeString);

?>
