<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine <himself@claus-justus-heine.de>
 * @copyright 2011-2016, 2020, 2021, 2022, 2023 Claus-Justus Heine
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

use OCA\CAFEVDB\Common\Util;
use OCA\CAFEVDB\Wrapped\Carbon\CarbonImmutable as DateTime;
use OCA\CAFEVDB\Database\Doctrine\ORM\Entities;

$locale = $l->getLocaleCode();

/** @var Entities\SentEmail $first */
/** @var Entities\SentEmail $last */
$first = reset($sentEmails);
$last = end($sentEmails);

$groupByYear = false;
if (!empty($first) && !empty($last)) {
  $firstYear = $first->getCreated()->format('Y');
  $lastYear = $last->getCreated()->format('Y');
  $groupByYear = $firstYear != $lastYear;
}

if ($groupByYear) {
  $currentYear = $firstYear;
  $lastYear = $currentYear;
  ?>
  <optgroup label="<?php p($currentYear); ?>">
  <?php
}

/** @var Entities\SentEmail $sentEmail */
foreach ($sentEmails as $sentEmail) {
  $createdAt = ($sentEmail->getCreated()??(new DateTime)->setTimestamp(0))
    ->locale($locale)
    ->setTimezone($dateTimeZone);
  $currentYear = $createdAt->format('Y');
  $title = $l->t('Subject: %s<br/>'
                 . 'Sent by %s on %s<br/>'
                 . 'Sent to %s', [
                   $sentEmail->getSubject(),
                   $sentEmail->getCreatedBy()??$l->t('Anonymous'),
                   $createdAt->isoFormat('lll'),
                   $sentEmail->getBulkRecipients(),
                 ]);
  $name = $createdAt->isoFormat('L LT').': '.$sentEmail->getSubject();
  if ($currentYear != $lastYear) {
    $lastYear = $currentYear;
    ?>
</optgroup><optgroup label="<?php p($currentYear); ?>">
    <?php
  }
  ?>
  <option value="<?php p($sentEmail->getMessageId()); ?>"
          title="<?php echo Util::htmlEscape($title); ?>"
  >
    <?php p($name); ?>
  </option>
<?php } if ($groupByYear) { ?>
  </optgroup>
<?php }
