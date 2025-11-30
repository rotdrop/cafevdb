<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine <himself@claus-justus-heine.de>
 * @copyright 2011-2016, 2020-2022, 2025 Claus-Justus Heine <himself@claus-justus-heine.de>
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
use PHPUnit\Framework\Attributes\CoversClass;

use OCP\Security\ICrypto;

use OCA\CAFEVDB\Crypto\CloudSymmetricCryptor;

/** Test the CloudSymmetricCryptor class. */
#[CoversClass(CloudSymmetricCryptor::class)]
class CloudSymmetricCryptorTest extends TestCase
{
  /** @var string */
  private const ENCRYPTION_KEY = '12345678';

  /** @var string */
  private const DATA_BYTES = 'This is a unicode ääöüß string';

  /** @var string */
  private const ENCRYPTED_BYTES = 'abcd|3';

  /** @var \PHPUnit\Framework\MockObject\MockObject|ICrypto */
  private $cloudCryptor;

  /**
   * {@inheritdoc}
   *
   * @return void
   */
  public function setup():void
  {
    parent::setup();

    $this->cloudCryptor = $this->getMockBuilder(ICrypto::class)
      ->disableOriginalConstructor()
      ->getMock();
  }

  /** @return void */
  public function testConstruction():void
  {
    $cryptor = new CloudSymmetricCryptor($this->cloudCryptor, self::ENCRYPTION_KEY);

    $this->assertInstanceOf(CloudSymmetricCryptor::class, $cryptor);
  }

  /** @return void */
  public function testEncryptWrapping():void
  {
    $this->cloudCryptor
      ->expects($this->once())
      ->method('encrypt')
      ->with(self::DATA_BYTES, self::ENCRYPTION_KEY)
      ->willReturn(self::ENCRYPTED_BYTES);

    $cryptor = new CloudSymmetricCryptor($this->cloudCryptor, self::ENCRYPTION_KEY);
    $this->assertEquals(self::ENCRYPTED_BYTES, $cryptor->encrypt(self::DATA_BYTES));
  }

  /** @return void */
  public function testDecryptWrapping():void
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
