<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine <himself@claus-justus-heine.de>
 * @copyright 2026 Claus-Justus Heine <himself@claus-justus-heine.de>
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

namespace OCA\CAFEVDB\Tests\Unit\Controller;

use PHPUnit\Framework\Attributes;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

use OCA\CAFEVDB\Controller;
use OCA\CAFEVDB\Tests\MockProvider;

/** Test aspects of the EncryptionController. */
#[Attributes\CoversClass(Controller\EncryptionController::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Service\L10N\L10NFactory::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Service\Registration::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Toolkit\AppInfo\AbstractApplication::class)]
class EncryptionControllerTest extends TestCase
{
  use TestRoutesAreDefinedTrait;

  private const CONTROLLER_CLASS = Controller\EncryptionController::class;
  private const EXPECTED_ROUTES = [
    'ocs' => [
      'bulkrecryptionrequest',
      'deleterecryptrequest',
      'getrecryptrequests',
      'handlerecryptrequest',
      'putrecryptrequest',
      'revokecloudaccess',
    ],
  ];

  private MockProvider $mockProvider;

  /** {@inheritdoc} */
  public function setup(): void
  {
    $this->mockProvider = $this->mockProvider ?? MockProvider::create($this);
  }
}
