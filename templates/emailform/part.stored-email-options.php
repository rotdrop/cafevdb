<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine
 * @copyright 2011-2016, 2020, 2021 Claus-Justus Heine <himself@claus-justus-heine.de>
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

use OCA\CAFEVDB\Wrapped\Carbon\CarbonImmutable as DateTime;

$locale = $l->getLocaleCode();

echo '
            <optgroup label="' . $l->t('Drafts') . '">
';
foreach ($storedEmails['drafts'] as $draft) {
  $createdAt = ($draft['created']??(new DateTime)->setTimestamp(0))
    ->locale($locale)
    ->setTimezone($dateTimeZone);
  $updatedAt = ($draft['updated']??(new DateTime)->setTimestamp(0))
    ->locale($locale)
    ->setTimezone($dateTimeZone);
  $title = $l->t("Subject: %s<br/>"
                ."Created by %s on %s<br/>"
                ."Updated by %s on %s",
                 [ $draft['name'],
                   $draft['createdBy']??$l->t('Anonymous'),
                   $createdAt->isoFormat('lll'),
                   $draft['updatedBy']??$l->t('Anonymous'),
                               $updatedAt->isoFormat('lll'), ]);
  $name = $updatedAt->isoFormat('L LT').': '.$draft['name'];
  echo '
              <option value="__draft-'.$draft['id'].'" title="'.$title.'">'.$name.'</option>
              ';
}
echo '
            </optgroup>
            <optgroup label="' . $l->t('Templates') . '">
';
foreach ($storedEmails['templates'] as $template) {
  $createdAt = ($template['created']??(new DateTime)->setTimestamp(0))->locale($locale);
  $updatedAt = ($template['updated']??(new DateTime)->setTimestamp(0))->locale($locale);
  $title = $l->t("Name: %s<br/>"
                ."Created by %s on %s<br/>"
                ."Updated by %s on %s",
                             [ $template['name'],
                               $template['createdBy']??$l->t('Anonymous'),
                               $createdAt->isoFormat('lll'),
                               $template['updatedBy']??$l->t('Anonymous'),
                               $updatedAt->isoFormat('lll'), ]);
              echo '
              <option value="'.$template['id'].'" title="'.$title.'">'.$template['name'].'</option>
              ';
}
echo '
            </optgroup>';
