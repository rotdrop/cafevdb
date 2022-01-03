<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine
 * @copyright 2011-2016, 2020, 2021, 2022 Claus-Justus Heine <himself@claus-justus-heine.de>
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

namespace OCA\CAFEVDB\Tests\Unit\Common\Crypto;

use PHPUnit\Framework\TestCase;

use OCP\Security\ICrypto;

use OCA\CAFEVDB\Common\Crypto\ICryptor;
use OCA\CAFEVDB\Common\Crypto\SealService;

class SealServiceTest extends TestCase
{
  /** @var string */
  private const ENCRYPTION_KEY = '12345678';

  /** @var string */
  private const DATA_BYTES = 'This is a unicode ääöüß string';

  /** @var string */
  private const ENCRYPTED_BYTES = 'abcd';

  private const USER_A = 'user.a';

  private const USER_B = 'user.b';

  /** @var \PHPUnit\Framework\MockObject\MockObject|ICrypto */
  private $cloudCryptor;

  public function setup():void
  {
    parent::setup();
    $this->cloudCryptor = $this->getMockBuilder(ICrypto::class)
      ->disableOriginalConstructor()
      ->getMock();
  }

  public function testConstruction()
  {
    $sealService = new SealService($this->cloudCryptor);
    $this->assertInstanceOf(SealService::class, $sealService);
  }

  public function testSealing()
  {
    $this->cloudCryptor
      ->expects($this->any())
      ->method('encrypt')
      ->willReturn(self::ENCRYPTED_BYTES);
    $this->cloudCryptor
      ->expects($this->any())
      ->method('decrypt')
      ->willReturn(self::DATA_BYTES);

    $keyCryptor = $this->getMockBuilder(ICryptor::class)
      ->disableOriginalConstructor()
      ->getMock();
    $keyCryptor
      ->expects($this->any())
      ->method('encrypt')
      ->willReturn(self::ENCRYPTED_BYTES);
    $keyCryptor
      ->expects($this->any())
      ->method('decrypt')
      ->willReturn(self::DATA_BYTES);

    $sealService = new SealService($this->cloudCryptor);
    $sealedData = $sealService->seal(self::DATA_BYTES, [ self::USER_A => $keyCryptor, self::USER_B => $keyCryptor ]);

    $unsealed = $sealService->unseal($sealedData, self::USER_A, $keyCryptor);
    $this->assertEquals($unsealed, self::DATA_BYTES);

    $unsealed = $sealService->unseal($sealedData, self::USER_B, $keyCryptor);
    $this->assertEquals($unsealed, self::DATA_BYTES);
  }

  public function testSealValidation()
  {
    $this->cloudCryptor
      ->expects($this->any())
      ->method('encrypt')
      ->willReturn(self::ENCRYPTED_BYTES);

    $keyCryptor = $this->getMockBuilder(ICryptor::class)
      ->disableOriginalConstructor()
      ->getMock();
    $keyCryptor
      ->expects($this->any())
      ->method('encrypt')
      ->willReturn(self::ENCRYPTED_BYTES);
    $keyCryptor
      ->expects($this->any())
      ->method('decrypt')
      ->willReturn(self::DATA_BYTES);

    $sealService = new SealService($this->cloudCryptor);
    $sealedData = $sealService->seal(self::DATA_BYTES, [ self::USER_A => $keyCryptor, self::USER_B => $keyCryptor ]);

    $this->assertEquals(true, $sealService->isSealedData($sealedData));
  }
}
