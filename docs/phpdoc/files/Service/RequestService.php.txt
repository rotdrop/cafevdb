<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine <himself@claus-justus-heine.de>
 * @copyright 2011-2016, 2021, 2022, 2023 Claus-Justus Heine
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

use InvalidArgumentException;
use RuntimeException;

use OCP\IRequest;
use OCP\IURLGenerator;
use OCP\ISession;
use Psr\Log\LoggerInterface as ILogger;
use OCP\IL10N;

use OCA\CAFEVDB\Toolkit\Service\RequestService as ToolkitService;

/** Place server-to-server AJAX calls. */
class RequestService extends ToolkitService
{
  // phpcs:disable Squiz.Commenting.FunctionComment.Missing
  public function __construct(
    IRequest $request,
    IURLGenerator $urlGenerator,
    ISession $session,
    ILogger $logger,
    IL10N $l10n,
    bool $closeSession = true,
  ) {
    parent::__construct($request, $urlGenerator, $session, $logger, $l10n, $closeSession);
  }
  // phpcs:enable Squiz.Commenting.FunctionComment.Missing
}
