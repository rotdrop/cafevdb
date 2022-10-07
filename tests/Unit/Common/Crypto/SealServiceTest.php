<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine
 * @copyright 2011-2016, 2020, 2021, 2022 Claus-Justus Heine <himself@claus-justus-heine.de>
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

namespace OCA\CAFEVDB\Tests\Unit\Crypto;

use PHPUnit\Framework\TestCase;

use OCP\Security\ICrypto;
use OCP\ILogger;

use OCA\CAFEVDB\Crypto\ICryptor;
use OCA\CAFEVDB\Crypto\CryptoFactoryInterface;
use OCA\CAFEVDB\Crypto\SealService;
use OCA\CAFEVDB\Crypto\CloudSymmetricCryptor;

class SealServiceTest extends TestCase
{
  /** @var string */
  private const ENCRYPTION_KEY = '12345678';

  /** @var string */
  private const DATA_BYTES = 'This is a unicode ääöüß string';

  /** @var string */
  private const CLOUD_ENCRYPTED_BYTES = 'abcd|Z';

  /** @var string */
  private const ENCRYPTED_BYTES = 'abcd';

  private const USER_A = 'user.a';

  private const USER_B = 'user.b';

  /** @var \PHPUnit\Framework\MockObject\MockObject|ILogger */
  private $cloudLogger;

  /** @var \PHPUnit\Framework\MockObject\MockObject|ICrypto */
  private $cloudCryptor;

  /** @var \PHPUnit\Framework\MockObject\MockObject|CryptoFactoryInterface */
  private $cryptoFactory;

  public function setup():void
  {
    parent::setup();
    $this->cryptoFactory = $this->getMockBuilder(CryptoFactoryInterface::class)
      ->disableOriginalConstructor()
      ->getMock();

    $this->cloudLogger = $this->getMockBuilder(ILogger::class)
      ->disableOriginalConstructor()
      ->getMock();

    $this->cloudCryptor = $this->getMockBuilder(ICrypto::class)
      ->disableOriginalConstructor()
      ->getMock();
  }

  public function testConstruction()
  {
    $sealService = new SealService($this->cryptoFactory, $this->cloudLogger);
    $this->assertInstanceOf(SealService::class, $sealService);
  }

  public function testSealing()
  {
    $this->cloudCryptor
      ->expects($this->any())
      ->method('decrypt')
      ->willReturn(self::DATA_BYTES);
    $this->cloudCryptor
      ->expects($this->any())
      ->method('encrypt')
      ->willReturn(self::CLOUD_ENCRYPTED_BYTES);

    $cloudSymmetricCryptor = new CloudSymmetricCryptor($this->cloudCryptor, self::ENCRYPTION_KEY);

    $this->cryptoFactory
      ->expects($this->any())
      ->method('getSymmetricCryptor')
      ->willReturn($cloudSymmetricCryptor);

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

    $sealService = new SealService($this->cryptoFactory, $this->cloudLogger);
    $sealedData = $sealService->seal(self::DATA_BYTES, [ self::USER_A => $keyCryptor, self::USER_B => $keyCryptor ]);

    $expectedSealedData = sprintf('%08x', strlen(self::CLOUD_ENCRYPTED_BYTES))
      . '|'. self::CLOUD_ENCRYPTED_BYTES
      . '|' . self::USER_A .':' . self::ENCRYPTED_BYTES
      . ';' . self::USER_B .':' . self::ENCRYPTED_BYTES;
    $this->assertEquals($sealedData, $expectedSealedData);

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

    $sealService = new SealService($this->cryptoFactory, $this->cloudLogger);
    $sealedData = $sealService->seal(self::DATA_BYTES, [ self::USER_A => $keyCryptor, self::USER_B => $keyCryptor ]);

    $this->assertEquals(true, $sealService->isSealedData($sealedData));
  }
}
