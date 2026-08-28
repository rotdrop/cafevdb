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

namespace OCA\CAFEVDB\Tests\Unit\Crypto;

use PHPUnit\Framework\Attributes;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

use OCA\CAFEVDB\Crypto;
use OCA\CAFEVDB\Crypto\AsymmetricKeyService;
use OCA\CAFEVDB\Events;
use OCA\CAFEVDB\Tests\MockProvider;
use OCA\RotDrop\Tests\DeprecationException;

/** Test aspects of the EncryptionController. */
#[Attributes\CoversClass(Crypto\AsymmetricKeyService::class)]
#[Attributes\CoversClass(Crypto\CloudAsymmetricKeyStorage::class)]
#[Attributes\CoversClass(Crypto\HaliteAsymmetricCryptor::class)]
#[Attributes\CoversClass(Crypto\HaliteAsymmetricKeyStorage::class)]
#[Attributes\CoversClass(Crypto\HaliteCryptoFactory::class)]
#[Attributes\CoversClass(Crypto\HaliteSymmetricStreamCryptor::class)]
#[Attributes\CoversClass(Crypto\Registration::class)]
#[Attributes\CoversClass(Events\AfterEncryptionKeyPairChanged::class)]
#[Attributes\CoversClass(Events\BeforeEncryptionKeyPairChanged::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Toolkit\AppInfo\AbstractApplication::class)]
class AsymmetricKeyServiceTest extends TestCase
{
  private MockProvider $mockProvider;

  private AsymmetricKeyService $service;

  /** {@inheritdoc} */
  public function setup(): void
  {
    DeprecationException::throwOnDeprecations();

    $this->mockProvider = $mockProvider = $this->mockProvider ?? MockProvider::create($this);

    $appContainer = $this->mockProvider->getAppContainer();

    $this->service = new AsymmetricKeyService(
      appName: $this->mockProvider->appName,
      appContainer: $appContainer,
      userSession: $mockProvider->getUserSession(),
      credentialsStore: $mockProvider->getCredentialsStore(),
      cloudUserConfig: $mockProvider->getCloudUserConfig(),
      eventDispatcher: $mockProvider->getEventDispatcher(),
      l: $mockProvider->getL10N(),
      logger: $mockProvider->getLoggerInterface(),
      keyStorage: $appContainer->get(Crypto\AsymmetricKeyStorageInterface::class),
      cryptorPrototype: $appContainer->get(Crypto\AsymmetricCryptorInterface::class),
    );
  }

  /** @return void */
  public function tearDown(): void
  {
    restore_error_handler();
  }

  /** @return void */
  public function testSetup(): void
  {
  }

  /** @return void */
  public function testSharedPrivateValues(): void
  {
    $ownerId = MockProvider::CLOUD_USER_UID;
    $key = 'aKey';
    $value = 'aValue';
    $this->service->setSharedPrivateValue(
      ownerId: $ownerId,
      key: $key,
      value: $value,
    );

    $result = $this->service->getSharedPrivateValue(
      ownerId: $ownerId,
      key: $key,
    );
    $this->assertEquals($value, $result);

    $result = $this->service->getSharedPrivateData(ownerId: $ownerId);
    $this->assertEquals([ $key => $value ], $result);

    $key2 = 'aSecondKey';
    $value2 = 'aSecondValue';
    $this->service->setSharedPrivateValue(
      ownerId: $ownerId,
      key: $key2,
      value: $value2,
    );
    $result = $this->service->getSharedPrivateValue(
      ownerId: $ownerId,
      key: $key2
    );
    $this->assertEquals($value2, $result);

    $this->service->deleteSharedPrivateValue(
      ownerId: $ownerId,
      key: $key,
    );

    $result = $this->service->getSharedPrivateData(ownerId: $ownerId);
    $this->assertEquals([ $key2 => $value2 ], $result);

    $this->service->removeSharedPrivateData(ownerId: $ownerId);

    $result = $this->service->getSharedPrivateData(ownerId: $ownerId);
    $this->assertEmpty($result);
  }
}
