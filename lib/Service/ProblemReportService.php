<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine <himself@claus-justus-heine.de>
 * @copyright 2025 Claus-Justus Heine
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

namespace OCA\CAFEVDB\Service;

use Psr\Log\LoggerInterface;

/**
 * Collect problem reports and forward them to configured targets (e.g. submit
 * an issue to Gitlab or Github, or just send an email to a configured email
 * address).
 *
 * Why? Unfortunately one cannot assume the everybody has a configured
 * standard email client, so the previous idea of just presenting an email
 * link in the frontend does not work. Instead, present a button in the
 * frontend to the end-user which just automagically submits a problem report
 * to configurable locations.
 */
class ProblemReportService
{
  use \OCA\CAFEVDB\Toolkit\Traits\LoggerTrait;

  /** {@inheritdoc} */
  public function __construct(
    protected LoggerInterface $logger,
  ) {
  }

  /**
   * Submit a problem report.
   *
   * @param string $userId The user id of the user reporint the error.
   *
   * @param array $errorData The raw error data caught by the frontend
   * code. Ideally, this is data in the format of Nextcloud log entries, but this is not guaranteed.
   *
   * @param null|string $userComment Optional comment submitted alongside the user. May be markdown.
   *
   * @return ?string A notification message which the frontend should present
   * to the user in order to inform the person of where the problem record has
   * been submitted.
   */
  public function submit(
    string $userId,
    array $errorData,
    ?string $userComment,
  ):?string {

    $this->logInfo('Requested problem report: USER "' . $userId . '" DATA "' . print_r($errorData, true) . '" COMMENT "' . $userComment . '".');

    return null;
  }
}
