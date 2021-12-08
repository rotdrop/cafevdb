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

use OCP\AppFramework\App;
use PHPUnit\Framework\TestCase;

use OCP\Security\ICrypto;

use OCA\CAFEVDB\Common\Crypto\CloudSymmetricCryptor;

class CloudSymmetricCryptorTest extends TestCase
{
  /** @var string */
  protected $appName;

  /** @var string */
  private const ENCRYPTION_KEY = '12345678';

  /** @var string */
  private const DATA_BYTES = 'This is a unicode ääöüß string';

  /** @var CloudSymmetricCryptor */
  private $cryptor;

  /** @var ICrypto */
  private $cloudCryptor;

  public function setup():void
  {
    parent::setup();
    $infoXml = new \SimpleXMLElement(file_get_contents(__DIR__ . '/../../../../appinfo/info.xml'));
    $this->appName = (string)$infoXml->id;
    // $app = new App($this->appName);
    // $container = $app->getContainer();

    // $this->cloudCryptor = $container->get(ICrypto::class);
    // $this->cryptor = $container->get(CloudSymmetricCryptor::class);
    $this->cloudCryptor = $this->getMockBuilder(ICrypto::class)
      ->disableOriginalConstructor()
      ->getMock();
  }

  public function testConstruction()
  {
    $cryptor = new CloudSymmetricCryptor($this->cloudCryptor, self::ENCRYPTION_KEY);

    $this->assertInstanceOf(CloudSymmetricCryptor::class, $cryptor);
  }
}
