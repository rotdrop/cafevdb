<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine
 * @copyright 2011-2016, 2020, 2021 Claus-Justus Heine <himself@claus-justus-heine.de>
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

// use OCP\AppFramework\App;
use PHPUnit\Framework\TestCase;

use OCA\CAFEVDB\Common\Crypto\OpenSSLAsymmetricCryptor;

class OpenSSLAsymmetricCryptorTest extends TestCase
{
  /** @var string */
  private const ENCRYPTION_KEY = '12345678';

  /** @var string */
  private const DATA_BYTES = 'This is a unicode ääöüß string';

  /** @var string */
  private const ENCRYPTED_BYTES = 'abcd';

  /**

  /** @var OpenSSLAsymmetricKey */
  private $sslKey;

  /** @var string */
  private $privKey;

  /** @var string */
  private $pubKey;

  public function setup():void
  {
    parent::setup();
    $this->sslKey = openssl_pkey_new();
    openssl_pkey_export($this->sslKey, $this->privKey, self::ENCRYPTION_KEY);
    $details = openssl_pkey_get_details($this->sslKey);
    $this->pubKey = $details['key'];
  }

  public function testConstruction()
  {
    $cryptor = new OpenSSLAsymmetricCryptor();
    $this->assertInstanceOf(OpenSSLAsymmetricCryptor::class, $cryptor);

    $cryptor = new OpenSSLAsymmetricCryptor($this->privKey, self::ENCRYPTION_KEY);
    $this->assertInstanceOf(OpenSSLAsymmetricCryptor::class, $cryptor);
  }

  public function testEncryptWrapping()
  {
    $cryptor = (new OpenSSLAsymmetricCryptor())
      ->setPrivateKey($this->privKey, self::ENCRYPTION_KEY)
      ->setPublicKey($this->pubKey);

    $this->assertEquals(self::DATA_BYTES, $cryptor->decrypt($cryptor->encrypt(self::DATA_BYTES)));
  }
}
