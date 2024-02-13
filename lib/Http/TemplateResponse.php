<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine <himself@claus-justus-heine.de>
 * @copyright 2024 Claus-Justus Heine
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
/**
 * @file Expose tooltips as AJAY controllers, fetching them by their key.
 */

namespace OCA\CAFEVDB\Http;

use OCP\AppFramework\Http\TemplateResponse as BaseClass;

/**
 * Simple wrapper class in order to always inject some standard template
 * parameters.
 */
class TemplateResponse extends BaseClass
{
  public const APPNAME_PREFIX = 'app-';

  /** {@inheritdoc} */
  public function __construct(
    string $appName,
    string $templateName,
    array $params,
    string $renderAs,
  ) {
    parent::__construct(
      $appName,
      $templateName,
      array_merge(
        [
          'appName' => $appName,
          'appNameTag' => self::APPNAME_PREFIX . $appName,
        ],
        $params,
      ),
      $renderAs,
    );
  }
}
