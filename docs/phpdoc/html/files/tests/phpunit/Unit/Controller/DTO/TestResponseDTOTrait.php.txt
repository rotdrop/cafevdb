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

namespace OCA\CAFEVDB\Tests\Unit\Controller\DTO;

use OCP\AppFramework\Http;

/**
 * Just test in order to avoid typos. The consuming test has to define the
 * constant self::DTO_CLASS to the class name of the DTO that should be
 * tested.
 */
trait TestResponseDTOTrait
{
  use TestDTOTrait;

  /** @return void */
  public function testResponse(): void
  {
    $httpStatus = Http::STATUS_BANDWIDTH_LIMIT_EXCEEDED;
    $response = $this->dto->response($httpStatus);
    $this->assertInstanceOf(Http\JSONResponse::class, $response);
    $this->assertEquals($httpStatus, $response->getStatus());
    $this->assertInstanceOf(static::DTO_CLASS, $response->getData());
  }
}
