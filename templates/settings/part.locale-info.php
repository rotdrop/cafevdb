<?php
/**
 * Orchestra member, musicion and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine <himself@claus-justus-heine.de>
 * @copyright 2022, 2023 Claus-Justus Heine
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

/**
 * @param \DateTimeZone $infoDateTimeZone
   @param string $infoLocaleSymbol
   @param string $infoCurrencySymbol
   @param string $infoCurrencyCode
   @param \OCP\IL10N $infoL10n
   @param \OCP\IDatetimeFormatter $dateTimeFormatter
   @param string $localeScope
 */

$l10n = $l;

foreach (['localeSymbol', 'currencySymbol', 'currencyCode', 'l10n', 'dateTimeZone'] as $key) {
  $infoKey = 'info' . ucfirst($key);
  ${$key} = ${$infoKey} ?? ${$key};
}

list($localeSymbol,) = explode('.', $localeSymbol, 2);

/** @var \OCP\IDateTimeFormatter $dateTimeFormatter */
$time = $dateTimeFormatter->formatDateTime(null, 'medium', 'medium', $dateTimeZone, $l10n);
$timezone = $dateTimeZone->getName();

$localeScope = $localeScope ?? 'personal';

?>

<div class="locale information" data-scope="<?php p($localeScope); ?>">
  <span class="locale heading"><?php p($l->t('Locale Information:')); ?></span>
  <span class="locale time"><?php echo p($time); ?></span>
  <span class="locale timezone"><?php echo p($timezone); ?></span>
  <span class="locale thelocale"><?php echo p($localeSymbol); ?></span>
  <span class="locale currency">
    <span class="locale currency-symbol"><?php p($currencySymbol); ?> </span>
    <?php if ($currencyCode != $currencySymbol) { ?>
      <span class="locale currency-code"><?php p($currencyCode); ?> </span>
    <?php } ?>
  </span>
</div>
