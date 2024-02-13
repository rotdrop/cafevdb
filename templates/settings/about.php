<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine <himself@claus-justus-heine.de>
 * @copyright 2011-2014, 2020, 2021, 2022, 2023, 2024 Claus-Justus Heine
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

use Composer\Spdx\SpdxLicenses;

$codeName = trim($appInfo['codename']);
$licence = trim($appInfo['licence']);
$longLicence = (new SpdxLicenses)->getLicenseByIdentifier($licence);

$wikiCodeName = str_replace(' ', '_', ucfirst(strtolower($codeName)));
$codeLink = '<a target="_wikipedia" href="http://www.wikipedia.org/w/index.php?title='.$wikiCodeName.'">' . $codeName . '</a>';

$wikiLicence = str_replace(' ', '_', $longLicence[0]);
$licenceLink = '<a target="_wikipedia" href="http://www.wikipedia.org/w/index.php?title=' . $wikiLicence . '">' . $licence . '</a>';

$author = $appInfo['author']['@value'];
$authorEmail = $appInfo['author']['@attributes']['mail'];
$homePage = $appInfo['author']['@attributes']['homepage'];

?>
<div id="tabs-<?php echo $_['tabNr']; ?>" class="app-cafevdb about personalblock <?php $_['adminsettings'] && p('admin'); ?>">

  <div class="product name">
  <?php echo $appInfo['name'].' v'.$appInfo['version'].' AKA "'.$codeLink.'"'; ?>
  </div>
  <div class="product description">
    <?php p($l->t($appInfo['description'])); ?>
  </div>
  <div class="product author">
    <span class="text">&copy; <?php p($appInfo['lifetime']); ?></span>
    <span class="hspace"></span>
    <a href="mailto:<?php echo rawurlencode($author . '<' . $authorEmail . '>') . '?subject=[cafevdb] '; ?>">
      <?php p($author); ?> &langle;<?php p($authorEmail); ?> &rangle;
    </a>
  </div>
  <div class="sourcecode">
    <span class="text"><?php p($l->t('Source Code:')); ?></span>
    <span class="hspace"></span>
    <a target="<?php p($appInfo['id'] . '-source'); ?>" href="<?php echo $homePage; ?>">
      <?php p($homePage); ?>
    </a>
  </div>
  <div class="product licence">
    <?php echo $l->t('Licenced under the %s', $licenceLink); ?>
  </div>
  <div class="product credits">
    <div class="product credits intro">
      <?php echo $l->t(
        'There is a long list of free software(%1$s)
projects this application depends on, coded in the spare-time of
an amazingly long list of volunteers. We just randomly choose
five projects and display them below. The list refreshes
"itself" every %d seconds.',
        [
          '<a target="_wikipedia" href="http://en.wikipedia.org/wiki/Free_software">?</a>',
          30,
        ]
      );
      ?>

    </div>
    <div class="product credits list">
      <?php echo $this->inc('settings/creditslist', ['credits' => $appInfo['credits']['item']]); ?>
    </div>
  </div>
</div>
