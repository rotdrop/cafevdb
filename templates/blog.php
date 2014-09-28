<?php
/* Orchestra member, musician and project management application.
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
use CAFEVDB\Blog;
use CAFEVDB\Navigation;
use CAFEVDB\Util;

$css_pfx = 'cafevdb-page';
$hdr_vis = $_['headervisibility'];

$nav = '';
$nav .= Navigation::button('projects');
$nav .= Navigation::button('all');
$nav .= Navigation::button('projectinstruments');
$nav .= Navigation::button('instruments');

$header = ''
  .'<div class="'.$css_pfx.'-blog" id="'.$css_pfx.'-blog-header">
'.L::t('Camerata DB start page - the data-base operations can be accessed
through the respective navigation buttons at the top of the page. The
page below may be used as a bulletin-board. Please click on the
login-name (top right) to reach the pull-down menu for logout and
configuration options. The applications menu is hidden under the name
of the current application (top left besides the cloud-icon).')
  .'</div>
';


echo $this->inc('part.common.header',
                array('css-prefix' => $css_pfx,
                      'navigationcontrols' => $nav,
                      'header' => $header));

?>

<div id="blogframe">
  <form id="blogform" method="post">
    <input type="hidden" name="app" value="<?php echo Config::APP_NAME; ?>" />
    <input type="hidden" name="headervisibility" value="<?php echo $hdr_vis; ?>" />
    <input
      type="submit"
      title="<?php echo Config::toolTips('blog-newentry');?>"
      value="<?php echo L::t('New note'); ?>"
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
echo $this->inc('part.common.footer', array('css-prefix' => $css_pfx));

?>

