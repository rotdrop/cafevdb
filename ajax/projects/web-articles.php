<?php
/**Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine
 * @copyright 2011-2014 Claus-Justus Heine <himself@claus-justus-heine.de>
 *
 * This library is free software; you can redistribute it and/or
 * modify it under the terms of the GNU GENERAL PUBLIC LICENSE
 * License as published by the Free Software Foundation; either
 * version 3 of the License, or any later version.
 *
 * This library is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU AFFERO GENERAL PUBLIC LICENSE for more details.
 *
 * You should have received a copy of the GNU Lesser General Public
 * License along with this library.  If not, see <http://www.gnu.org/licenses/>.
 */

use CAFEVDB\L;
use CAFEVDB\Config;
use CAFEVDB\Util;
use CAFEVDB\Error;
use CAFEVDB\Projects;

if(!OCP\User::isLoggedIn()) {
	die('<script type="text/javascript">document.location = oc_webroot;</script>');
}

OCP\JSON::checkAppEnabled(Config::APP_NAME);

$debugText = print_r($_POST, true);

try {

  ob_start();

  Error::exceptions(true);
  
  Config::init();

  $action = Util::cgiValue('Action', '');
  $projectId = Util::cgiValue('ProjectId', -1);
  $articleId = Util::cgiValue('ArticleId', -1);

  if ($projectId < 0) {
    $debugText .= ob_get_contents();
    @ob_end_clean();
    OC_JSON::error(
      array("data" => array(
              "message" => L::t("Invalid or unset project-id: `%s'", array($projectId)),
              "debug" => $debugText)));
    return false;
  }

  $message = $debugText;
  $errorMessage = false;

  switch ($action) {
  case 'delete':
    if ($articleId < 0) {
      $debugText .= ob_get_contents();
      @ob_end_clean();
      OC_JSON::error(
        array("data" => array(
                "message" => L::t("Invalid or unset article-id: `%s'", array($articleId)),
                "debug" => $debugText)));
      return false;
    }
    if (Projects::deleteProjectWebPage($projectId, $articleId)) {
      $message = L::t("Removed public web page with id %d from project with id %d.",
                      array($articleId, $projectId));
    } else {
      $errorMessage = L::t("Failed to remove public web page with id %d from project with id %d.",
                           array($articleId, $projectId));
    }
    break;
  case 'add':
    if ($articleId < 0) {
      // This simply means: create a new page for the project.
      $article = Projects::createProjectWebPage($projectId);
      if ($article === false) {
        $errorMmessage = L::t("Failed to create a new public web page for project with id %d.",
                              array($projectId));
      } else {
        $message = L::t("Created a new public web page with name %s and id %d ".
                        "for project with id %d",
                        array($article['ArticleName'], $article['ArticleId'], $projectId));
      }
    }
    break;
  default:
    $debugText .= ob_get_contents();
    @ob_end_clean();
    OC_JSON::error(
      array("data" => array(
              "message" => L::t("Unhandled or empty request: `%s'", array($action)),
              "debug" => $debugText)));
    return false;
  }

  $debugText .= ob_get_contents();
  @ob_end_clean();

  if ($errorMessage === false) {
    OCP\JSON::success(
      array('data' => array('message' => $message,
                            'debug' => $debugText)));
    
    return true;
  } else {
    OC_JSON::error(
      array("data" => array(
              "message" => L::t("Error: ").$errorMessage,
              "debug" => $debugText)));
    return false;
  }

} catch (\Exception $e) {

  $debugText .= ob_get_contents();
  @ob_end_clean();

  // For whatever reason we need to entify quotes, otherwise jquery throws an error.
  OCP\JSON::error(
    array(
      'data' => array(
        'error' => 'exception',
        'exception' =>  $e->getMessage(),
        'trace' => $e->getTraceAsString(),
        'message' => Util::entifyString(L::t('Error, caught an exception')),
        'debug' => $debugText)));

  return false;
}

?>
