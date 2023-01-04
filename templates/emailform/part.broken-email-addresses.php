<?php
/**
 * Orchestra member, musicion and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine <himself@claus-justus-heine.de>
 * @copyright 2011-2016, 2020, 2021, 2022, 2023 Claus-Justus Heine
 * @license GNU AGPL version 3 or any later version
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
 * You should have received a copy of the GNU Affero General Public
 * License along with this library.  If not, see <http://www.gnu.org/licenses/>.
 */

/**
 * @param array $missingEmailAddresses
 *
 * @file PHP snippet to render the list of broken emails.
 */

$following = false;
foreach ($missingEmailAddresses as $id => $missingInfo) {
  $isParticipant = $missingInfo['participant'];
  $label = $missingInfo['label'];
  if ($following) { ?>
  <span class="separator">, </span>
  <?php } ?>
  <a href="#"
     class="missing-email-addresses personal-record"
     data-musician-id="<?php p($id); ?>"
     data-is-participant="<?php p((int)$isParticipant); ?>"
  >
    <?php echo $label; ?>
  </a>
  <?php
  $following = true;
}
?>
