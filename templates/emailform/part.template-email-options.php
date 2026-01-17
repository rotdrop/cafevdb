<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine <himself@claus-justus-heine.de>
 * @copyright 2011-2016, 2020, 2021, 2023, 2025, 2026 Claus-Justus Heine
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
use OCA\CAFEVDB\Controller\EmailFormController;
use OCA\CAFEVDB\Wrapped\Carbon\CarbonImmutable as DateTime;

/**
 * Expected template parameters.
 *
 * @var array $templateEmails
 * @var \OCP\IDateTimeFormatter $dateTimeFormatter
 * @var \OCP\IDateTimeZone $dateTimeZone
 * @var string $currentTemplate
 * @var \OCP\IL10N $l
 */

$locale = $l->getLocaleCode();

foreach ($_[EmailFormController::TEMPLATE_EMAILS] as $template) {
  $createdAt = ($template['created']??(new DateTime)->setTimestamp(0))->locale($locale);
  $updatedAt = ($template['updated']??(new DateTime)->setTimestamp(0))->locale($locale);
  $title = $l->t(
    "Name: %s<br/>"
    . "Created by %s on %s<br/>"
    . "Updated by %s on %s",
    [ $template['name'],
      $template['createdBy']??$l->t('Anonymous'),
                   $createdAt->isoFormat('lll'),
      $template['updatedBy']??$l->t('Anonymous'),
      $updatedAt->isoFormat('lll'), ]);
  $name = Util::htmlEscape($template['name']);
  ?>
  <option value="<?= $name ?>"
          class="tooltip-auto"
          title="<?php p($title); ?>"
          <?php ($_[EmailFormController::EMAIL_TEMPLATE_NAME] ?? null) == $template['name'] && print('selected="selected"') ?>
  >
    <?= $name ?>
  </option>
<?php } ?>
