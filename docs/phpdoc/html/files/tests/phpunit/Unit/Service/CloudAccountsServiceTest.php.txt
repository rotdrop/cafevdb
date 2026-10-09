<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine <himself@claus-justus-heine.de>
 * @copyright 2025, 2026 Claus-Justus Heine <himself@claus-justus-heine.de>
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

namespace OCA\CAFEVDB\Tests\Unit\Service;

use Throwable;

use PHPUnit\Framework\Attributes;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

use OCA\Settings\Mailer\NewUserMailHelper;
use OCP\Group\ISubAdmin as ISubAdminManager;
use OCP\IAvatarManager;
use OCP\IGroupManager;
use OCP\IUser;
use OCP\IUserManager;
use OCP\Mail\IEMailTemplate;
use OCP\Security\ISecureRandom;
use OCP\UserInterface;
use OCP\User\Backend\ABackend;

use OCA\CAFEVDB\Database\EntityManager;
use OCA\CAFEVDB\Service;
use OCA\CAFEVDB\Service\CloudAccountsService as TestedService;
use OCA\CAFEVDB\Service\ProjectService;
use OCA\CAFEVDB\Tests\MockProvider;
use OCA\RotDrop\Tests\DeprecationException;

/** Test aspects of the CloudAccountsService class. */
#[Attributes\CoversClass(TestedService::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Service\L10N\L10NFactory::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Service\Registration::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Toolkit\AppInfo\AbstractApplication::class)]
class CloudAccountsServiceTest extends TestCase
{
  private TestedService $service;

  private MockProvider $mockProvider;

  private IUserManager $userManager;

  private NewUserMailHelper $newUserMailHelper;

  /** {@inheritdoc} */
  public function setup(): void
  {
    DeprecationException::throwOnDeprecations(exclude: '/OCP\\\\IConfig\\:\\:(get|set|delete)AppValue/');

    $this->mockProvider = $this->mockProvider ?? MockProvider::create($this);

    $appContainer = $this->mockProvider->getAppContainer();

    $this->userManager = $this->createStub(IUserManager::class);

    $this->newUserMailHelper = $this->createStub(NewUserMailHelper::class);

    $this->service = new TestedService(
      appName: $this->mockProvider->appName,
      logger: $this->mockProvider->getLoggerInterface(),
      cloudConfig: $this->mockProvider->getCloudConfig(),
      avatarManager: $this->createStub(IAvatarManager::class),
      eventDispatcher: $this->mockProvider->getEventDispatcher(),
      secureRandom: $appContainer->get(ISecureRandom::class),
      userManager: $this->userManager,
      newUserMailHelper: $this->newUserMailHelper,
      groupManager: $this->createStub(IGroupManager::class),
      subAdminManager: $this->createStub(ISubAdminManager::class),
      entityManager: $this->createStub(EntityManager::class),
      projectService: $this->createStub(ProjectService::class),
    );
  }

  /** @return void */
  public function tearDown(): void
  {
    restore_error_handler();
  }

  /** @return void */
  public function testConstruction(): void
  {
  }

  private const OLD_USER_BACKEND_NAME = 'OldBackend';

  private const NEW_USER_BACKEND_NAME = 'NewBackend';

  /** @return void */
  public function testAddUserToBackend(): void
  {
    $user = $this->mockProvider->getUser();

    $oldBackend = $this->createStub(ABackend::class);
    $oldBackend->method('getBackendName')->willReturn(self::OLD_USER_BACKEND_NAME);
    $newBackend = $this->createStub(ABackend::class);
    $newBackend->method('getBackendName')->willReturn(self::NEW_USER_BACKEND_NAME);
    $this->userManager->method('getBackends')->willReturn([$newBackend, $oldBackend]);
    $this->userManager->method('createUserFromBackend')->willReturnCallback(
      function(string $userId, string $password, UserInterface $backend) use ($user) {
        $newUser = $this->createStub(IUser::class);
        $newUser->method('getBackend')->willReturn($backend);
        $newUser->method('getUID')->willReturn($userId);
        $newUser->method('getSystemEMailAddress')->willReturnCallback(fn() => $user->getSystemEMailAddress());
        $newUser->method('getEMailAddress')->willReturnCallback(fn() => $user->getEMailAddress());
        return $newUser;
      });

    $result = $this->service->addUserToBackend($user, self::NEW_USER_BACKEND_NAME);
    $this->assertFalse($result);
    $user->method('getBackend')->willReturn($oldBackend);
    $result = $this->service->addUserToBackend($user, self::NEW_USER_BACKEND_NAME);
    $this->assertFalse($result);

    // now a run which should succeed ...
    $user->method('getEMailAddress')->willReturn('john.doe@nowhere.tld');
    $user->method('getSystemEMailAddress')->willReturn('john.doe@nowhere.tld');
    $user->method('getDisplayName')->willReturn('John Doe');
    $user->method('getQuota')->willReturn('none');

    $this->newUserMailHelper->method('generateTemplate')->willReturn($this->createStub(IEMailTemplate::class));

    $result = $this->service->addUserToBackend($user, self::NEW_USER_BACKEND_NAME);
    $this->assertTrue($result);
  }
}
