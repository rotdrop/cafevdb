<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine <himself@claus-justus-heine.de>
 * @copyright 2025 Claus-Justus Heine <himself@claus-justus-heine.de>
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

namespace OCA\CAFEVDB\Tests\Unit\Exceptions;

use PHPUnit\Framework\Attributes;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

use OCP\AppFramework\Http;

use OCA\CAFEVDB\Exceptions;

/**
 * Test the Exceptions\DatabaseEntityException class and some of its child
 * classes.
 */
#[Attributes\CoversClass(Exceptions\EnduserNotificationException::class)]
class EnduserNotificationExceptionTest extends TestCase
{
  private const MESSAGE = 'MESSAGE';
  private const CODE = 666;
  private const PREVIOUS = null;
  private const HTTP_STATUS = Http::STATUS_IM_A_TEAPOT;
  private const CONTEXT = [ 'content' => 'CONTENT' ];

  /** @return void */
  public function testEnduserNotificationException(): void
  {
    try {
      throw new Exceptions\EnduserNotificationException(
        message: self::MESSAGE,
        code: self::CODE,
        previous: self::PREVIOUS,
        httpStatusCode: self::HTTP_STATUS,
        context: self::CONTEXT,
      );
    } catch (Exceptions\EnduserNotificationException $e) {
      $this->assertEquals(self::MESSAGE, $e->getMessage());
      $this->assertEquals(self::CODE, $e->getCode());
      $this->assertEquals(self::PREVIOUS, $e->getPrevious());
      $this->assertEquals(self::HTTP_STATUS, $e->getHttpStatusCode());
      $this->assertEquals(self::CONTEXT, $e->getContext());

      $e->setHttpStatusCode(-1)->setContext(null);
      $this->assertEquals(-1, $e->getHttpStatusCode());
      $this->assertEquals(null, $e->getContext());

      $e->setContext(null)->setHttpStatusCode(-1);
      $this->assertEquals(-1, $e->getHttpStatusCode());
      $this->assertEquals(null, $e->getContext());
    }
  }
}
