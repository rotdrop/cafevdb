

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

CAFEVDB\Error::exceptions(true);

use CAFEVDB\Projects;
use CAFEVDB\L;

/* For each public web-page related to the project (usually just one)
 * generate an iframe to load it into and use tabs in case of multiple
 * pages. This makes a nice do-it-in-one-place update possibility.
 *
 * This template fragment expects
 *
 * $_['projectArticles']
 *
 * to be an array which contains informations about the public
 * web-pages related to the project. The elements of the array are
 * supposed to be arrays which at least carry a 'ArticleName' and a 'ArticleId'
 * field. Everything else is abstract.
 *
 * The template also expects
 *
 * $_['cmsURLTemplate']
 *
 * with %KEY% value substitutions where KEY may be any key present the
 * in article arrays. After substitution this forms the final URL for
 * the iframe source. In this respect the template is independent
 * from the actual CMS system (but still we only support Redaxo ATM).
 *
 * $_['detachedArticles'] may contain articles present in the CMS but
 * (not yet) linked to the project. We generate a select box in order
 * to add those to the project.
 *
 * $_['action'] should be one of 'add', 'display' or 'change' in order
 * to indicate the action the user is trying to perform.
 *
 */
try {

  $edit = false;
  $articles = $_['projectArticles'];
  $cnt = count($articles);
  echo '<div id="projectWebArticles">
';
  if ($cnt > 1) {
    echo '  <ul id="cmsarticletabs">
';
    for ($nr = 1; $nr <= $cnt; ++$nr) {
      echo '    <li><a href="#projectArticle-'.$nr.'">'.$articles[$nr-1]['ArticleName'].'</a></li>
';
    }
    echo '  </ul>
';
  }
  $nr = 1;
  foreach ($articles as $article) {
    $url = $_['cmsURLTemplate'];
    foreach ($article as $key => $value) {
      $url = str_replace('%'.$key.'%', $value, $url);
    }
    echo '  <div id="projectArticle-'.$nr.'" class="cmsarticleframe cafev">
    <iframe scrolling="no"
          src="'.$url.'"
          class="cmsarticle '.$_['action'].'"
          id="cmsarticle-'.$nr.'"
          name="cmsarticle-'.$nr.'"
          style="width:auto;height:auto;overflow:hidden;"></iframe>
  </div>';
    ++$nr;
  }
  if ($cnt == 0) {
    echo '<span id="noWebArticlesFound">'.L::t("No public web pages registered for this project").'</span>
';    
  }
  echo '</div>
';

} catch (\Exception $e) {
  throw $e;
}

?>
