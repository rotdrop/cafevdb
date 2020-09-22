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

namespace OCA\CAFEVDB;

$appInfo = \OCP\App::getAppInfo($appName);

$codeName = trim($appInfo['codename']);
$licence = trim($appInfo['licence']);

$wikiCodeName = str_replace(' ', '_', ucfirst(strtolower($codeName)));
$codeLink = '<a target="_wikipedia" href="http://www.wikipedia.org/w/index.php?title='.$wikiCodeName.'">'.$codeName.'</a>';

$wikiLicence = str_replace(' ', '_', ucfirst(strtolower($licence)));
$licenceLink = '<a target="_wikipedia" href="http://www.wikipedia.org/w/index.php?title='.$wikiLicence.'">'.$licence.'</a>';

?>
<div id="tabs-<?php echo $_['tabNr']; ?>" class="cafevdb about personalblock <?php if ($_['adminsettings']) echo 'admin'; ?>">

  <div class="product name">
  <?php echo $appInfo['name'].' v'.$appInfo['version'].' AKA "'.$codeLink.'"'; ?>
  </div>
  <div class="product description">
    <?php echo $appInfo['description']; ?>
  </div>
  <div class="product author">
    &copy; 2012-2014 <?php echo $appInfo['author']; ?>
  </div>
  <div class="product licence">
    Licensed under the <?php echo $licenceLink; ?>
  </div>
  <div class="product credits">
    <div class="product credits intro">
      There is a long list of
      <a target="_wikipedia" href="http://en.wikipedia.org/wiki/Free_software">free software</a>
      projects this application depends on, coded in the spare-time of
      an amazingly long list of volunteers. We just randomly choose
      five projects and display them below. The list refreshes
      "itself" every 30 seconds.
    </div>
    <div class="product credits list">
      <?php echo $this->inc('creditslist', array('credits' => $appInfo['credits']['item'])); ?>
    </div>
  </div>
</div>
