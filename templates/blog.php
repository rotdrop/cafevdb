<?php
/* Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine
 * @copyright 2011-2020 Claus-Justus Heine <himself@claus-justus-heine.de>
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

use OCA\CAFEVDB\Common\Navigation;

$css_pfx = 'cafevdb-page';
$css_class = 'blog-page';
unset($this->vars['css-class']);

$nav = '';
$nav .= Navigation::pageControlElement('projects');
$nav .= Navigation::pageControlElement('all');
//$nav .= Navigation::pageControlElement('projectinstruments');
$nav .= Navigation::pageControlElement('instruments');

$header = ''
        .'<div class="'.$css_pfx.'-blog" id="'.$css_pfx.'-blog-header">
'.$l->t('Camerata DB start page - the data-base operations can be accessed
through the menu-button %s (left top). Please click on the login-name
(top right) for logout and configuration options. Click right of the
cloud-icon (top left) to reach the app-menu.',
       array('<div class="icon-menu inline"></div>')
)
        .'</div>
';

echo $this->inc('part.common.header',
                ['css-prefix' => $css_pfx,
                 'css-class' => $css_class,
                 'navigationcontrols' => $nav,
                 'header' => $header]);
?>

<div id="blogframe">
  <form id="blogform" method="post">
    <input type="hidden" name="app" value="<?php echo $appName; ?>" />
    <input
      type="submit"
      title="<?php echo $toolTips['blog-newentry'];?>"
      value="<?php echo $l->t('New note'); ?>"
      id="blognewentry"
      />
  </form>

  <div id="blogthreads" class="cafevdb-blogthread">
    <?php
     echo $this->inc('blogthreads');
    ?>
  </div>
</div>
<?php
// Close some still opened divs
echo $this->inc('part.common.footer', ['css-prefix' => $css_pfx]);
