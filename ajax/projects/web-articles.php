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

  $action      = Util::cgiValue('Action', '');
  $projectId   = Util::cgiValue('ProjectId', -1);
  $articleId   = Util::cgiValue('ArticleId', -1);
  $articleData = Util::cgiValue('ArticleData', array());

  if ($projectId < 0) {
    $debugText .= ob_get_contents();
    @ob_end_clean();
    OC_JSON::error(
      array("data" => array(
              "message" => L::t("Invalid or unset project-id: `%s'", array($projectId)),
              "debug" => $debugText)));
    return false;
  }

  if (count($articleData) > 0 && $articleId >= 0 &&
      $articleData['ArticleId'] != $articleId) {
    $debugText .= ob_get_contents();
    @ob_end_clean();
    OC_JSON::error(
      array("data" => array(
              "message" => L::t("Submitted article id %d ".
                                "does not match the id %d stored in the article-data.",
                                  array($articleId, $articleData['ArticleId'])),
              "debug" => $debugText)));
    return false;
  }

  $message = $debugText;
  $errorMessage = false;

  if ($action != 'add') {
    // require both, ArticleId and ArticleData
    if ($articleId < 0) {
      $debugText .= ob_get_contents();
      @ob_end_clean();
      OC_JSON::error(
        array("data" => array(
                "message" => L::t("Invalid or unset article-id: `%s'", array($articleId)),
                "debug" => $debugText)));
      return false;
    }
    if (count($articleData) == 0) {
      $debugText .= ob_get_contents();
      @ob_end_clean();
      OC_JSON::error(
        array("data" => array(
                "message" => L::t("Cannot perform action `%s' with article with id %d, project with id %d: ".
                                  "missing article-data.",
                                  array($action, $articleId, $projectId)),
                "debug" => $debugText)));
      return false;
    }
  }

  switch ($action) {
  case 'delete':
    if (Projects::deleteProjectWebPage($projectId, $articleId)) {
      $message = L::t("Removed public web page %s (id %d) from the project with id %d.",
                      array($articleData['ArticleName'], $articleId, $projectId));
    } else {
      $errorMessage = L::t("Failed to remove public web page %s (id %d) from the project with id %d.",
                           array($articleData['ArticleName'], $articleId, $projectId));
    }
    break;
  case 'add':
    // This simply means: create a new page for the project.
    $article = Projects::createProjectWebPage($projectId);
    if ($article === false) {
      $errorMmessage = L::t("Failed to create a new public web page for the project with id %d.",
                            array($projectId));
    } else {
      $message = L::t("Created a new public web page with name %s and id %d ".
                      "for project the with id %d",
                      array($article['ArticleName'], $article['ArticleId'], $projectId));
    }
    break;
  case 'link':
    if (Projects::attachProjectWebPage($projectId, $articleData) === false) {
      $errorMmessage = L::t("Failed to add the article %s (id = %d) to the project with id %d.",
                            array($articleData['ArticleName'], $articleId, $projectId));
    } else {
      $message = L::t("Linked the existing public web-article %s (id %d) to the project with id %d",
                        array($articleData['ArticleName'], $articleId, $projectId));
      // If this was a trash-bin article, then move it to the preview category.
      $trashCategory = Config::getValue('redaxoTrashbin');
      if ($articleData['CategoryId'] == $trashCategory) {
        
      }
    }
    break;
  case 'unlink':
    if (Projects::detachProjectWebPage($projectId, $articleId) === false) {
      $errorMmessage = L::t("Failed to detach the article %s (id = %d) from the project with id %d.",
                            array($articleData['ArticleName'], $articleId, $projectId));
    } else {
      $message = L::t("Detached the public web-article %s (id %d) from the project with id %d",
                      array($articleData['ArticleName'], $articleId, $projectId));
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
