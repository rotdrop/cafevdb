<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine <himself@claus-justus-heine.de>
 * @copyright 2011-2024 Claus-Justus Heine
 * @license AGPL-3.0-or-later
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 */

namespace OCA\CAFEVDB;

$css_pfx = $renderer->cssPrefix();
$css_class = $renderer->cssClass();
unset($this->vars['css-class']);

$nav = '';
$nav .= $pageNavigation->pageControlElement('projects');
$nav .= $pageNavigation->pageControlElement('all');
$nav .= $pageNavigation->pageControlElement('project-instrumentation-numbers');
$nav .= $pageNavigation->pageControlElement('instruments');
$nav .= $pageNavigation->pageControlElement('instrument-families');
if ($expertMode) {
  $nav .= $pageNavigation->pageControlElement('config-check');
}

$header = ''
  . '<div class="'.$css_pfx.'-blog" id="'.$css_pfx.'-blog-header">
'
  . $l->t(
    'Camerata DB start page - the data-base operations can be accessed
through the menu-button %s (left top). Please click on the login-name
(top right) for logout and configuration options. Click right of the
cloud-icon (top left) to reach the app-menu.',
    [ '<div class="icon-menu inline"></div>'  ]
  )
        . '</div>
';

echo $this->inc(
  'part.common.header',
  [
    'css-prefix' => $css_pfx,
    'css-class' => $css_class,
    'navigationcontrols' => $nav,
    'header' => $header
  ]);
?>

<div id="blogframe">
  <form id="blogform" method="post">
    <input type="hidden" name="template" value="<?php p($template); ?>" />
    <input
      type="submit"
      title="<?php echo $toolTips['blog:newentry'];?>"
      value="<?php echo $l->t('New note'); ?>"
      id="blognewentry"
      />
  </form>

  <div id="blogthreads" class="cafevdb-blogthread">
    <?php
                             echo $this->inc('blog/blogthreads', $_);
    ?>
  </div>
</div>
<?php
// Close some still opened divs
echo $this->inc('part.common.footer', ['css-prefix' => $css_pfx]);
