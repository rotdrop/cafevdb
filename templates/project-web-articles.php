

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
 * supposed to be arrays which at least carry a 'name' and a 'id'
 * field. Everything else is abstract.
 *
 * The template also expects
 *
 * $_['cmsURLTemplate']
 *
 * to be a vsprintf template accepts the elements of
 * $_['projectArticles'] in order to generate the finale iframe source
 * URL. In this respect the template is independent from the actual
 * CMS system (but still we only support Redaxo ATM).
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

  $redaxoLocation = \OCP\Config::GetAppValue('redaxo', 'redaxolocation', '');
  $rex = new \Redaxo\RPC($redaxoLocation);

  $edit = false;
  $articles = $_['projectArticles'];
  $cnt = count($articles);
  if ($cnt > 1) {
    echo '<ul id="cmsarticletabs">
';
    for ($nr = 1; $nr <= $cnt; ++$nr) 
      echo '  <li><a href="#cmsarticle-'.$nr.'">'.$articles[$nr]['name'].'</a></li>
';
  }
  $nr = 1;
  foreach ($articles as $article) {
    echo '<div id="projectArticle-'.$nr.'" class="cmsarticleframe cafev">
  <iframe scrolling="no"
          src="'.$rex->getURL($article['id'], $edit).'"
          id="cmsarticle-'.$nr.'"
          name="cmsarticle-'.$nr.'"
          style="width:auto;height:auto;overflow:hidden;"></iframe>
</div>';
    ++$nr;
  }

} catch (\Exception $e) {
  throw $e;
}

?>
