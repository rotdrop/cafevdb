<?php
/**
 * Orchestra member, musicion and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine <himself@claus-justus-heine.de>
 * @copyright 2024 Claus-Justus Heine
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
 * License alogng with this library.  If not, see <http://www.gnu.org/licenses/>.
 */

namespace OCA\CAFEVDB;

use OCA\CAFEVDB\Common\NumberFormatter;

$numberFormatter = new NumberFormatter($appLocale);
$l10nAmount = $numberFormatter->formatCurrency($amount);

$routes = [
  'donation-receipt' => '#',
  'standard-receipt' => '#',
];

echo $this->inc('fragments/action-menu/menu', [
  'contextMenuTitle' => $compositePaymentId . ' - ' . $debitorName . ' - ' . $l10nAmount,
  'menuItemTemplate' => 'fragments/project-payments/action-items',
  'routes' => $routes,
  'menuData' => [
    'composite-payment-id' => $compositePaymentId,
    'debitor-name' => $debitorName,
    'debitor-id' => $debitorId,
    'is-donation' => $isDonation,
  ],
]);
