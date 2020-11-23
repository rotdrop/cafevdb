<?php
/* Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine
 * @copyright 2011-2014, 2020 Claus-Justus Heine <himself@claus-justus-heine.de>
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

namespace OCA\CAFEVDB;

/* For each public web-page related to the project (usually just one)
 * generate an iframe to load it into and use tabs in case of multiple
 * pages. This makes a nice do-it-in-one-place update possibility.
 *
 * This template fragment expects
 *
 * $projectPages = ARTICLES
 *
 * to be an array which contains informations about the public
 * web-pages. The elements of the array are supposed to be arrays
 * which at least carry a 'ArticleName' and a 'ArticleId'
 * field.
 *
 * The template also expects
 *
 * $cmsURLTemplate
 *
 * with %KEY% value substitutions where KEY may be any key present the
 * in article arrays. After substitution this forms the final URL for
 * the iframe source. In this respect the template is independent
 * from the actual CMS system (but still we only support Redaxo ATM).
 *
 * $otherPages contains articles present in the CMS but
 * (not yet) linked to the project. We generate a select box in order
 * to add those to the project. Articles in this array have two
 * additional fields (besides ArticleName and ArticleId):
 * CategoryName, which should be used to form optiongroup tags.
 *
 * $action should be one of 'add', 'display' or 'change' in order
 * to indicate the action the user is trying to perform.
 *
 */

$cnt = count($projectPages);
echo '<div id="cmsFrameLoader"><img src="'.$urlGenerator->imagePath($appName, 'loader.gif').'"></div>
';
echo '<div id="projectWebArticles">
  <ul id="cmsarticletabs">
';
if ($cnt > 0) {
  foreach ($projectPages as $nr => $webPage) {
    echo '    <li id="cmsarticle-tab-'.$nr.'"><a href="#projectArticle-'.$nr.'">'.$webPage['ArticleName'].'</a></li>
';
  }
} else {
  echo '    <li id="cmsarticle-tab-nopage"><a href="#projectArticle-nopage">'.$l->t('nothing').'</a></li>
';
}
echo '    <li id="cmsarticle-tab-newpage" class="tip" title="'.Config::toolTips('project-web-article-add').'"><a href="#projectArticle-newpage" class="compact">'.'<span class="ui-icon ui-icon-plusthick">+</span>'.'</a></li>
';
echo '    <li id="cmsarticle-tab-linkpage" class="tip" title="'.Config::toolTips('project-web-article-linkpage').'"><a href="#projectArticle-linkpage" class="compact">'.'<span class="ui-icon cafevdb-link-icon">link</span>'.'</a></li>
';
if ($cnt > 0) {
  echo '    <li id="cmsarticle-tab-unlinkpage" class="tip" title="'.Config::toolTips('project-web-article-unlinkpage').'"><a href="#projectArticle-unlinkpage" class="compact">'.'<span class="ui-icon cafevdb-unlink-icon">link</span>'.'</a></li>
';
  echo '    <li id="cmsarticle-tab-deletepage" class="tip" title="'.Config::toolTips('project-web-article-delete').'"><a href="#projectArticle-deletepage" class="compact">'.'<span class="ui-icon ui-icon-minusthick">-</span>'.'</a></li>
';
}
echo '  </ul>
';
foreach ($webPages as $nr => $webPage) {
  $url = $cmsURLTemplate;
  foreach ($webPage as $key => $value) {
    $url = str_replace('%'.$key.'%', $value, $url);
  }
  echo '
  <div id="projectArticle-'.$nr.'"
       class="cmsarticlecontainer cafev"
       data-article-id="'.$webPage['ArticleId'].'"
       data-article="'.htmlspecialchars(json_encode($webPage)).'"
       data-project-id="'.$projectId.'">
    <iframe '.($_['action'] != 'change' ? 'scrolling="no"' : 'scrolling="no"').'
          src="'.$url.'"
          class="cmsarticleframe '.$_['action'].'"
          id="cmsarticle-'.$nr.'"
          name="cmsarticle-'.$nr.'"
          style="width:auto;height:auto;overflow:hidden;"></iframe>
  </div>';
}
if ($cnt == 0) {
  echo '  <div id="projectArticle-nopage"
       class="cmsarticlecontainer cafev"
       data-article-id="-1"
       data-project-id="'.$projectId.'">
    <div id="cmsarticle-nopage" class="cmsarticleframe '.$_['action'].'">'.$l->t("No public web pages registered for this project").'</div>
  </div>
';
}
echo '  <div id="projectArticle-newpage"
       class="cmsarticlecontainer cafev"
       data-article-id="-1"
       data-project-id="'.$projectId.'">
    <div id="cmsarticle-newpage" class="cmsarticleframe '.$action.'">'.$l->t("Create new public web page for this project.").'</div>
  </div>
';
echo '  <div id="projectArticle-linkpage" class="cmsarticlecontainer cafev">
    <div id="cmsarticle-linkpage" class="cmsarticleframe '.$action.'">';
echo '
<select size="10"
        name="cmsarticleselect"
        id="cmsarticleselect"
        class="cmsarticleselect"
        data-placeholder="'.$l->t('Attach existing Pages').'"
        title="'.$toolTips['project-web-article-linkpage-select'].'"
        data-project-id="'.$projectId.'">
  <option></option>';
if (count($otherPages) > 0) {
  $oldGroup = $otherPages[0]['CategoryName'];
  echo '
  <optgroup label="'.$oldGroup.'">';
  foreach($otherPages as $webPage) {
    $group = $webPage['CategoryName'];
    if ($group != $oldGroup) {
      echo '
  </optgroup>
  <optgroup label="'.$group.'">';
        $oldGroup = $group;
    }
    unset($webPage['CategoryName']);
    $option = [ 'type' => 'option',
                'value' => $webPage['ArticleId'],
                'name' => $webPage['ArticleName'],
                'data' => [ 'article' => json_encode($article)] ];
    if ($article['Linked']) {
      $option['selected'] = 'selected';
    }
    echo $pageNavigation->htmlTagsFromArray([ $option ]);
  }
  echo '
  </optgroup>';
}
echo '
</select>';
echo '
    </div>
  </div>
';
if ($cnt > 0) {
  echo '  <div id="projectArticle-unlinkpage" class="cmsarticlecontainer cafev">
    <div id="cmsarticle-unlinkpage" class="cmsarticleframe '.$action.'">'.$l->t("Unlink a web-article.").'</div>
  </div>
';
  echo '  <div id="projectArticle-deletepage" class="cmsarticlecontainer cafev">
    <div id="cmsarticle-deletepage" class="cmsarticleframe '.$action.'">'.$l->t("Delete a web article.").'</div>
  </div>
';
}
echo '</div>
';
