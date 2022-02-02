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

namespace OCA\CAFEVDB\Tests\Unit\Crypto;

use PHPUnit\Framework\TestCase;

use OCP\Security\ICrypto;

use OCA\CAFEVDB\Crypto\CloudSymmetricCryptor;

class CloudSymmetricCryptorTest extends TestCase
{
  /** @var string */
  private const ENCRYPTION_KEY = '12345678';

  /** @var string */
  private const DATA_BYTES = 'This is a unicode ääöüß string';

  /** @var string */
  private const ENCRYPTED_BYTES = 'abcd|3';

  /** @var CloudSymmetricCryptor */
  private $cryptor;

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
    $cryptor = new CloudSymmetricCryptor($this->cloudCryptor, self::ENCRYPTION_KEY);

    $this->assertInstanceOf(CloudSymmetricCryptor::class, $cryptor);
  }

  public function testEncryptWrapping()
  {
    $this->cloudCryptor
      ->expects($this->once())
      ->method('encrypt')
      ->with(self::DATA_BYTES, self::ENCRYPTION_KEY)
      ->willReturn(self::ENCRYPTED_BYTES);

    $cryptor = new CloudSymmetricCryptor($this->cloudCryptor, self::ENCRYPTION_KEY);
    $this->assertEquals(self::ENCRYPTED_BYTES, $cryptor->encrypt(self::DATA_BYTES));
  }

  public function testDecryptWrapping()
  {
    $this->cloudCryptor
      ->expects($this->once())
      ->method('decrypt')
      ->with(self::ENCRYPTED_BYTES, self::ENCRYPTION_KEY)
      ->willReturn(self::DATA_BYTES);

    $cryptor = new CloudSymmetricCryptor($this->cloudCryptor, self::ENCRYPTION_KEY);
    $this->assertEquals(self::DATA_BYTES, $cryptor->decrypt(self::ENCRYPTED_BYTES));
  }
}
