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

namespace OCA\CAFEVDB\Tests\Unit\Controller;

use DOMDocument;
use DateTimeImmutable;
use ReflectionClass;
use ReflectionMethod;
use ReflectionProperty;

use PHPUnit\Framework\Attributes;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

use OCP\AppFramework\Http;
use OCP\Files\File;
use OCP\Files\Folder;
use OCP\Files\IRootFolder;
use OCP\Files\Node;
use OCP\Http\Client\IClient as HttpClient;
use OCP\Http\Client\IClientService as HttpClientFactory;
use OCP\Http\Client\IResponse as HttpClientResponse;
use OCP\IL10N;
use OCP\IRequest;
use OCP\IURLGenerator;
use OCP\Security\ISecureRandom;

use OCA\CAFEVDB\Common\TimeFactory;
use OCA\CAFEVDB\Common\Util;
use OCA\CAFEVDB\Constants;
use OCA\CAFEVDB\Controller;
use OCA\CAFEVDB\Controller\DTO;
use OCA\CAFEVDB\Controller\EmailFormController as TestedController;
use OCA\CAFEVDB\Database\Doctrine\DBAL\Types;
use OCA\CAFEVDB\Database\Doctrine\ORM\Entities;
use OCA\CAFEVDB\Database\Doctrine\ORM\Repositories;
use OCA\CAFEVDB\Database\Legacy\PME\DefaultOptions as PMEOptions;
use OCA\CAFEVDB\Database\Legacy\PME\PHPMyEdit;
use OCA\CAFEVDB\EmailForm;
use OCA\CAFEVDB\PageRenderer\PersistentCGIKeys;
use OCA\CAFEVDB\PageRenderer\Util\Navigation as PageNavigation;
use OCA\CAFEVDB\Service;
use OCA\CAFEVDB\Settings\ConfigConstants;
use OCA\CAFEVDB\Storage;
use OCA\CAFEVDB\Tests\MockProvider;
use OCA\CAFEVDB\Tests\Unit\Service\SetupEventsServiceTrait;
use OCA\CAFEVDB\Toolkit;
use OCA\CAFeVDBMembers\Service\ProjectGroupService;

#[Attributes\CoversClass(DTO\EmailFormComposerRequestData::class)]
#[Attributes\CoversClass(DTO\EmailFormComposerRequestDataTypes\ElementData::class)]
#[Attributes\CoversClass(DTO\EmailFormComposerRequestDataTypes\ElementDataEventAttachments::class)]
#[Attributes\CoversClass(DTO\EmailFormComposerRequestDataTypes\ElementDataFileAttachments::class)]
#[Attributes\CoversClass(DTO\EmailFormComposerResponse::class)]
#[Attributes\CoversClass(DTO\EmailFormListContactsResponse::class)]
#[Attributes\CoversClass(DTO\EmailFormRecipientsFilterResponse::class)]
#[Attributes\CoversClass(DTO\EmailWebFormResponse::class)]
#[Attributes\CoversClass(EmailForm\Composer::class)]
#[Attributes\CoversClass(EmailForm\EnumSubstitutionNamespace::class)]
#[Attributes\CoversClass(EmailForm\RecipientsFilter::class)]
#[Attributes\CoversClass(TestedController::class)]
/** Test the ProjectsController class. */
#[Attributes\UsesClass(\OCA\CAFEVDB\AppInfo\Application::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Common\Html2Text::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Common\PHPMailer::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Common\RationalNumber::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Common\TimeFactory::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Common\Util::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Common\Uuid::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Controller\DTO\EmailFormRecipientsFilterHistory::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Controller\DTO\EmailFormRecipientsFilterReloadResponse::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Controller\DTO\EmailFormRecipientsFilterSnapshotResponse::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Controller\ProjectEventsController::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Crypto\HaliteCryptoFactory::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Crypto\HaliteSymmetricStreamCryptor::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Database\Doctrine\ORM\Entities\EmailTemplate::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Database\Doctrine\ORM\Entities\Instrument::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Database\Doctrine\ORM\Entities\InstrumentFamily::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Database\Doctrine\ORM\Entities\Musician::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Database\Doctrine\ORM\Entities\MusicianEmailAddress::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Database\Doctrine\ORM\Entities\MusicianInstrument::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Database\Doctrine\ORM\Entities\Project::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Database\Doctrine\ORM\Entities\ProjectEvent::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Database\Doctrine\ORM\Entities\ProjectInstrument::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Database\Doctrine\ORM\Entities\ProjectInstrumentationNumber::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Database\Doctrine\ORM\Entities\ProjectParticipant::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Database\Doctrine\ORM\Entities\SepaBankAccount::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Database\Doctrine\ORM\Repositories\ProjectsRepository::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Database\Legacy\PME\DefaultOptions::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Database\Legacy\PME\PHPMyEdit::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Events\EncryptionServiceBound::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Legacy\Calendar\OC_Calendar_Object::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Legacy\PhpMyEdit\PhpMyEdit::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\PageRenderer\PME\Config::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\PageRenderer\Registration::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\PageRenderer\Util\Navigation::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Service\CalDavService::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Service\ConfigService::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Service\DTO\EventMatrixEvent::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Service\DTO\EventMatrixRow::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Service\DTO\EventTimes::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Service\DTO\HumanDateTime::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Service\EmailAddressService::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Service\EncryptionService::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Service\EventsService::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Service\InstrumentationService::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Service\L10N\AppL10N::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Service\L10N\L10NFactory::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Service\MailingListsService::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Service\OrganizationalRolesService::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Service\ProjectParticipantFieldsService::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Service\ProjectService::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Service\Registration::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Service\ToolTipsDataService::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Service\ToolTipsService::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Service\VCalendarService::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Toolkit\DTO\AbstractResponseDTO::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Toolkit\Response\HttpStatus::class)]
#[Attributes\UsesClass(\OCA\CAFEVDB\Toolkit\Response\PreRenderedTemplateResponse::class)]
#[Attributes\UsesTrait(\OCA\CAFEVDB\Database\Doctrine\ORM\Traits\ArrayTrait::class)]
#[Attributes\UsesTrait(\OCA\CAFEVDB\Database\Doctrine\ORM\Traits\AutoIncrementTrait::class)]
#[Attributes\UsesTrait(\OCA\CAFEVDB\Database\Doctrine\ORM\Traits\CreatedAt::class)]
#[Attributes\UsesTrait(\OCA\CAFEVDB\Database\Doctrine\ORM\Traits\DateTimeTrait::class)]
#[Attributes\UsesTrait(\OCA\CAFEVDB\Database\Doctrine\ORM\Traits\FactoryTrait::class)]
#[Attributes\UsesTrait(\OCA\CAFEVDB\Database\Doctrine\ORM\Traits\SoftDeleteableEntity::class)]
#[Attributes\UsesTrait(\OCA\CAFEVDB\Database\Doctrine\ORM\Traits\UpdatedAt::class)]
#[Attributes\UsesTrait(\OCA\CAFEVDB\Database\Doctrine\ORM\Traits\UuidTrait::class)]
#[Attributes\UsesTrait(\OCA\CAFEVDB\Toolkit\Doctrine\ORM\FindLikeTrait::class)]
#[Attributes\UsesTrait(\OCA\CAFEVDB\Toolkit\Traits\BackedEnumTrait::class)]
#[Attributes\UsesTrait(\OCA\CAFEVDB\Toolkit\Traits\CamelCaseToDashesTrait::class)]
#[Attributes\UsesTrait(\OCA\CAFEVDB\Toolkit\Traits\DateTimeTrait::class)]
#[Attributes\UsesTrait(\OCA\CAFEVDB\Toolkit\Traits\TranslatableEnumTrait::class)]
#[Attributes\UsesTrait(\OCA\CAFEVDB\Traits\AppConfigTrait::class)]
#[Attributes\UsesTrait(\OCA\CAFEVDB\Traits\EntityManagerTrait::class)]
#[Attributes\UsesTrait(\OCA\CAFEVDB\Traits\UserPreferencesTrait::class)]
class EmailFormControllerTest extends TestCase
{
  use TestRoutesAreDefinedTrait;
  use \OCA\CAFEVDB\Tests\Unit\Database\Doctrine\ORM\Entities\MockEmailTemplatesRepositoryTrait;
  use \OCA\CAFEVDB\Tests\Unit\Database\Doctrine\ORM\Entities\MockInstrumentsRepositoryTrait;
  use \OCA\CAFEVDB\Tests\Unit\Database\Doctrine\ORM\Entities\MockMusiciansRepositoryTrait;
  use \OCA\CAFEVDB\Tests\Unit\Service\SetupEventsServiceTrait;

  private const EXPECTED_ROUTES = [
    'webform',
    'composer',
    'recipientsfilter',
    'contacts',
    'attachment',
  ];

  private const CONTROLLER_CLASS = TestedController::class;

  private TestedController $testedController;

  private PHPMyEdit $pme;

  private IURLGenerator $urlGenerator;

  private IL10N $l10n;

  private Service\ProjectService $projectService;

  private Toolkit\Service\SimpleSharingService $simpleSharingService;

  private Storage\UserStorage $userStorage;

  private ProjectGroupService $projectGroupService;

  private array $postData = [];

  private array $emailContacts = [];

  private array $fileNodes = [];

  private array $linkShares = [];

  private array $linkSharesByPath = [];

  private int $shareId = 1;

  private int $nodeId = 1;

  /** {@inheritdoc} */
  public function setup(): void
  {
    $this->generateEventsService();

    $this->generateInstruments();

    $this->entityManager->expects($this->never())->method('recryptEncryptedProperties');

    $this->entityRepositories[Entities\Instrument::class] = $this->getInstrumentsRepositoryMock();
    $this->entityRepositories[Entities\Musician::class] = $this->getMusiciansRepositoryMock();
    $this->entityRepositories[Entities\EmailTemplate::class] = $this->getEmailTemplatesRepositoryMock();

    $repository = $this->getMockBuilder(Repositories\EmailDraftsRepository::class)
      ->disableOriginalConstructor()
      ->getMock();
    $repository->method('list')->willReturn([]);
    $this->entityRepositories[Entities\EmailDraft::class] = $repository;

    $repository = $this->getMockBuilder(Repositories\ProjectParticipantsRepository::class)
      ->disableOriginalConstructor()
      ->getMock();
    $repository->method('fetchParticipantNames')->willReturn([['nickName' => 'John'], ['nickName' => 'Jane']]);
    $this->entityRepositories[Entities\ProjectParticipant::class] = $repository;

    foreach ($this->entityRepositories as $repository) {
      $repository->expects($this->never())?->method('createQueryBuilder');
    }

    /** @var MockProvider $mockProvider */
    $mockProvider = $this->mockProvider ?? MockProvider::create($this);

    // $this->mockProvider->registerClassInstance(EventsService::class, $this->eventsService);

    /** @var IRequest $request */
    $this->request = $this->createStub(IRequest::class);
    $this->request->method('getParam')->willReturnCallback(
      function(string $key, mixed $default = null) {
        return $this->postData[$key] ?? $default;
      },
    );
    $this->request->method('getParams')->willReturnCallback(fn() => $this->postData);
    $this->request->method('getServerProtocol')->willReturn('https');
    $this->request->method('getServerHost')->willReturn('cloud.tld');
    $mockProvider->registerClassInstance(IRequest::class, $this->request, global: true);

    /** @var PHPMyEdit $pme */
    $this->pme = $this->createStub(PHPMyEdit::class);
    $pmeOptions = new PMEOptions([]);
    foreach ([PHPMyEdit::CGI_SYS_KEY, PHPMyEdit::CGI_DATA_KEY, PHPMyEdit::CGI_OPERATION_KEY] as $key) {
      $this->pme->cgi[PHPMyEdit::CGI_PREFIX_KEY][$key] = $pmeOptions['cgi'][PHPMyEdit::CGI_PREFIX_KEY][$key];
    }
    $this->pme->method('cgiSysName')->willReturnCallback(
      fn(string $suffix = ''): string
      =>
      $this->pme->cgi[PHPMyEdit::CGI_PREFIX_KEY][PHPMyEdit::CGI_SYS_KEY] . $suffix,
    );

    /** @var ProjectParticipantFieldsService $projectParticipantFieldsService */
    $projectParticipantFieldsService = $this->createStub(Service\ProjectParticipantFieldsService::class);

    /** @var MusicianService $musiciansService */
    $musiciansService = $this->createStub(Service\MusicianService::class);

    $this->configService->setConfigValue(ConfigConstants::SHARED_FOLDER, 'orchestra');
    $this->configService->setConfigValue(ConfigConstants::PROJECTS_FOLDER, 'projects',);
    $this->configService->setConfigValue(ConfigConstants::FINANCE_FOLDER, 'finance');
    $this->configService->setConfigValue(ConfigConstants::PROJECT_PARTICIPANTS_FOLDER, 'participants');
    $this->configService->setConfigValue(ConfigConstants::PROJECT_POSTERS_FOLDER, 'posters');
    $this->configService->setConfigValue(ConfigConstants::PROJECT_PUBLIC_DOWNLOADS_FOLDER, 'downloads');
    $this->configService->setConfigValue(ConfigConstants::BALANCES_FOLDER, 'balances');

    /** @var UserStorage $this->userStorage */
    $this->userStorage = $this->createStub(Storage\UserStorage::class);
    $mockProvider->registerClassInstance(Storage\UserStorage::class, $this->userStorage, global: true);

    $this->userStorage->method('ensureFolderChain')->willReturn($this->createStub(Folder::class));
    $this->userStorage->method('copyTree')->willReturn($this->createStub(Folder::class));
    $this->userStorage->method('get')->willReturnCallback(function(string $path) {
      if ($this->fileNodes[$path] ?? null) {
        return $this->fileNodes[$path];
      }
      if ($path == '/' || $path == '') {
        $node = $this->createStub(IRootFolder::class);
        $this->assertInstanceOf(IRootFolder::class, $node);
      } else {
        $node = $this->createStub(Folder::class);
      }
      $node->method('getType')->willReturn(Node::TYPE_FOLDER);
      $node->method('getPath')->willReturn($path);
      $node->method('getName')->willReturn(basename($path));
      $parent = dirname($path);
      if ($parent != $path) {
        // echo 'PARENT ' . $parent . PHP_EOL;
        $parent = $this->userStorage->get($parent);
        $node->method('getParent')->willReturn($parent);
      }
      $this->fileNodes[$path] = $node;
      $node->method('getId')->willReturn($this->nodeId++);

      return $node;
    });
    $this->userStorage->method('putContent')->willReturnCallback(
      function(string $path, string $content): File {
        $node = $this->userStorage->get($path);
        $parent = $node->getParent();
        $file = $this->createStub(File::class);
        $file->method('getParent')->willReturn($parent);
        $file->method('getPath')->willReturn($path);
        $file->method('getName')->willReturn(basename($path));
        $file->method('getContent')->willReturn($content);
        $file->method('getType')->willReturn(Node::TYPE_FILE);
        $file->method('getId')->willReturn($node->getId());

        $this->fileNodes[$path] = $file;

        return $file;
      }
    );
    $this->userStorage->method('folderWalk')->willReturnCallback(
      function(mixed $folder) {
        $path = is_string($folder) ? $folder : $folder->getPath();
        $path .= Constants::PATH_SEP;
        $entries = array_filter(array_keys($this->fileNodes), fn(string $nodePath) => str_starts_with($nodePath, $path));
        return count($entries);
      }
    );
    $this->userStorage->method('getFilesAppLink')->willReturnCallback(function(string|Node $pathOrNode) {
      if (is_string($pathOrNode)) {
        $nodePath = $pathOrNode;
      } else {
        $nodePath = $pathOrNode->getPath();
      }
      return $this->urlGenerator->linkToRoute('files.view.index', [ 'dir' => $nodePath ]);
    });

    $this->urlGenerator = $this->appContainer->get(IURLGenerator::class);
    $this->l10n = $this->appContainer->get(IL10N::class);

    /** @var Toolkit\Service\SimpleSharingService $simpleSharingService */
    $this->simpleSharingService = $this->createStub(Toolkit\Service\SimpleSharingService::class);
    $mockProvider->registerClassInstance(Toolkit\Service\SimpleSharingService::class, $this->simpleSharingService, global: true);

    $this->simpleSharingService->method('linkShare')->willReturnCallback(
      function(
        Node $folder,
        ?string $shareOwner = null,
        int $sharePerms = \OCP\Constants::PERMISSION_CREATE,
        mixed $expirationDate = null,
        ?string $password = null,
        bool $noCreate = false,
        ?string $newShareOwner = null,
      ) {
        $filesSharing = $this->linkSharesByPath[$folder->getPath()] ?? null;
        if ($filesSharing) {
          $share = $this->linkShares[$filesSharing]['share'];
          $dav = $this->linkShares[$filesSharing]['dav'];
        } else {
          $token = $this->appContainer->get(ISecureRandom::class)->generate(\OC\Share\Helper::DEFAULT_TOKEN_LENGTH, ISecureRandom::CHAR_HUMAN_READABLE);
          $filesSharing = $this->urlGenerator->linkToRouteAbsolute('files_sharing.sharecontroller.showShare', ['token' => $token]);
          $dav = $this->urlGenerator->getAbsoluteURL('/public.php/dav/files/' . $token);
          $share = $this->createStub(\OCP\Share\IShare::class);
          $share->method('getNode')->willReturn($folder);
          $share->method('getId')->willReturn($this->shareId++);
          $share->method('getPassword')->willReturn($password);
          $share->method('getExpirationDate')->willReturn($expirationDate);
          $this->linkSharesByPath[$folder->getPath()] = $filesSharing;
          $this->linkShares[$filesSharing] = [
            'token' => $token,
            'node' => $folder,
            'files_sharing' => $filesSharing,
            'share' => $share,
            'dav' => $dav,
            'password' => $password,
          ];
        }
        return ['files_sharing' => $filesSharing, 'share' => $share, 'dav' => $dav];
      },
    );
    $this->simpleSharingService->method('getLinkExpirationDate')->willReturn(DateTimeImmutable::createFromFormat('Y-m-d', '2099-01-01'));
    $this->simpleSharingService->method('getShareFromUrl')->willReturnCallback(function(string $url) {
      return $this->linkShares[$url]['share'] ?? null;
    });

    $this->projectGroupService = $this->createStub(ProjectGroupService::class);
    $mockProvider->registerClassInstance(ProjectGroupService::class, $this->projectGroupService, global: true);
    $this->projectGroupService->method('getProjectFolderLinkShare')->willReturnCallback(
      function(int $projectId) {
        $project = $this->entityManager->getRepository(Entities\Project::class)->find(['id' => $projectId]);
        if (!$project) {
          return null;
        }
        $path = '/orchestra-members/projects/' . $project->getYear() . '/' . $project->getName();
        $node = $this->userStorage->get($path);
        $result = $this->simpleSharingService->linkShare($node, password: $project->getName());
        $result['mount_point'] = $path;
        return $result;
      }
    );

    // Needed by e.g. OCA\CAFEVDB\EmailForm\Composer via app-container
    $this->projectService = new Service\ProjectService(
      configService: $this->configService,
      entityManager: $this->entityManager,
      userStorage: $this->userStorage,
      participantFieldsService: $projectParticipantFieldsService,
      musicianService: $musiciansService,
      eventDispatcher: $this->mockProvider->getEventDispatcher(),
    );
    $mockProvider->registerClassInstance(Service\ProjectService::class, $this->projectService);

    $contactsService = $this->createStub(Service\ContactsService::class);
    $contactsService->method('emailContacts')->willReturn($this->emailContacts);
    $contactsService->method('addEmailContact')->willReturnCallback(
      function(array $emailContact, ?string $addressBookKey = null): ?array {
        foreach (['email', 'name'] as $key) {
          $this->assertArrayHasKey($key, $emailContact);
        }
        $this->emailContacts[] = $emailContact;
        // should return contact from contacts-manager, but the EmailFormController only checks for non-null.
        return $emailContact;
      },
    );

    $this->configService->setConfigValue(ConfigConstants::EMAIL_FROM_NAME_KEY, 'EmailFromNameValue');
    $this->configService->setConfigValue(ConfigConstants::EMAIL_FROM_ADDRESS_KEY, 'EmailFromAddressValue');

    $this->testedController = new TestedController(
      appName: $this->mockProvider->appName,
      request: $this->request,
      contactsService: $contactsService,
      emailAddressService: $this->appContainer->get(Service\EmailAddressService::class),
      urlGenerator: $this->urlGenerator,
      pme: $this->pme,
      pageNavigation: $this->appContainer->get(PageNavigation::class),
      configService: $this->configService,
      appContainer: $this->appContainer,
    );

    ini_set('display_errors', 1);
    ini_set('display_startup_errors', 1);
    error_reporting(E_ALL);
  }

  /**
   * Stub or mock the HttpClient.
   *
   * @param array $badRequests By default all requests are server with status
   * Http::HTTP_OK, this array may specify URLs as keys and status codes as
   * values to map specific URLs to specific HTTP status codes.
   *
   * @return void
   */
  private function mockHttpClient(array $badRequests = []): void
  {
    $factory = $this->createStub(HttpClientFactory::class);
    $this->mockProvider->registerClassInstance(HttpClientFactory::class, $factory, global: true);
    if (empty($badRequests)) {
      $factory->method('newClient')->willReturnCallback(
        function() {
          $mockedClient = $this->getMockBuilder(HttpClient::class)
            ->disableOriginalConstructor()
            ->getMock();
          $mockedClient->expects($this->never())->method('patch');
          $mockedClient->method('head')->willReturnCallback(
            function(string $url) {
              $mockedResponse = $this->getMockBuilder(HttpClientResponse::class)
                ->disableOriginalConstructor()
                ->getMock();
              $mockedResponse->expects($this->atLeastOnce())->method('getStatusCode')->willReturnCallback(
                function() use ($url) {
                  if (!empty($this->linkShares[$url]['password'])) {
                    return HTTP::STATUS_MOVED_PERMANENTLY;
                  }
                  return Http::STATUS_OK;
                }
              );
              return $mockedResponse;
            }
          );
          return $mockedClient;
        },
      );
    } else {
      $factory->method('newClient')->willReturn(
        $this->createStub(HttpClient::class)->method('head')->willReturnFunction(
          function(string $url) use ($badRequests) {
            $status = $badRequests[$url] ?? Http::STATUS_OK;
            $this->createStub(HttpClientResponse::class)->method('getStatusCode')->willReturn(
              $status,
            );
          },
        ),
      );
    }
  }

  /** @return void */
  public function testSetup(): void
  {
  }

  /**
   * @param array $furtherParameters
   *
   * @return void
   */
  private function generateWebFormParameters(array $furtherParameters = []): void
  {
    $idx = 10;
    $this->postData[PersistentCGIKeys::INSTRUMENTS_FDD_INDEX] = $idx;
    // echo 'SYSCGI' .  $this->pme->cgiSysName('qf' . $idx . '_idx') . PHP_EOL;
    $this->postData[$this->pme->cgiSysName('qf' . $idx . '_idx')] = [
      // 1, 2 ,3
    ];
    $idx = 11;
    $this->postData[PersistentCGIKeys::PARTICIPATION_STATUS_FDD_INDEX] = $idx;
    $this->postData[$this->pme->cgiSysName('qf' . $idx . '_idx')] = [
      // Types\EnumParticipationStatus::REGULAR,
    ];
    $this->postData = Util::arrayMergeRecursive($this->postData, $furtherParameters);
  }

  /**
   * @param array $furtherParameters
   *
   * @return void
   */
  private function generateProjectWebFormParameters(array $furtherParameters = []): void
  {
    $this->generateWebFormParameters(
      Util::arrayMergeRecursive([
        'projectName' => $this->project->getName(),
        'projectId' => $this->project->getId(),
      ], $furtherParameters),
    );
  }

  /** @return void */
  public function testGenerateFormWithoutProject(): void
  {
    $this->generateWebFormParameters();
    $response = $this->testedController->webForm();
    $this->assertInstanceOf(Http\JSONResponse::class, $response);
    $this->assertEquals(Http::STATUS_OK, $response->getStatus());
    $data = $response->getData();
    $this->assertInstanceOf(DTO\EmailWebFormResponse::class, $data);
    $domDoc = new DOMDocument('1.0', 'UTF-8');
    $domDoc->encoding = 'UTF-8';
    $this->assertEquals(true, $domDoc->loadHTML($data->contents, LIBXML_PEDANTIC));
    $this->assertEquals(0, $data->projectId);
    $this->assertEquals(null, $data->projectName);
    $this->assertInstanceOf(DTO\EmailFormRecipientsFilterHistory::class, $data->filterHistory);
    $this->assertEquals(0, $data->filterHistory->historyPosition);
    $this->assertEquals(1, $data->filterHistory->historySize);
  }

  /** @return void */
  #[Attributes\Depends('testGenerateFormWithoutProject')]
  public function testGenerateFormWithProject(): void
  {
    $this->generateProjectWebFormParameters();
    $response = $this->testedController->webForm(
      projectName: $this->project->getName(),
      projectId: $this->project->getId(),
    );
    $this->assertInstanceOf(Http\JSONResponse::class, $response);
    $this->assertEquals(Http::STATUS_OK, $response->getStatus());
    $data = $response->getData();
    $this->assertInstanceOf(DTO\EmailWebFormResponse::class, $data);
    $domDoc = new DOMDocument('1.0', 'UTF-8');
    $domDoc->encoding = 'UTF-8';
    $this->assertEquals(true, $domDoc->loadHTML($data->contents, LIBXML_PEDANTIC));
    $this->assertEquals($this->project->getId(), $data->projectId);
    $this->assertEquals($this->project->getName(), $data->projectName);
    $this->assertInstanceOf(DTO\EmailFormRecipientsFilterHistory::class, $data->filterHistory);
    $this->assertEquals(0, $data->filterHistory->historyPosition);
    $this->assertEquals(1, $data->filterHistory->historySize);
  }

  private const DEFAULT_PROJECT_USER_BASE = [
    EmailForm\EnumPostTag::RECIPIENTS_FILTER->value => [
      EmailForm\RecipientsFilterCgiKeys::BASIC_RECIPIENTS_SET => [
        EmailForm\RecipientsFilterCgiKeys::FROM_PROJECT_PRELIMINARY,
        EmailForm\RecipientsFilterCgiKeys::FROM_PROJECT_CONFIRMED,
      ],
    ],
  ];

  private const MAILING_LIST_USER_BASE = [
    EmailForm\EnumPostTag::RECIPIENTS_FILTER->value => [
      EmailForm\RecipientsFilterCgiKeys::BASIC_RECIPIENTS_SET => [
        EmailForm\RecipientsFilterCgiKeys::ANNOUNCEMENTS_MAILING_LIST,
      ],
    ],
  ];

  /** @return void */
  #[Attributes\Depends('testGenerateFormWithProject')]
  public function testRecipientsFilterHistorySnapshot(): void
  {
    $this->generateWebFormParameters([
      EmailForm\EnumPostTag::RECIPIENTS_FILTER->value => [
        EmailForm\RecipientsFilterCgiKeys::INSTRUMENTS_FILTER => [1,2,3],
        EmailForm\RecipientsFilterCgiKeys::HISTORY_SNAPSHOT => 'whatever',
        EmailForm\RecipientsFilterCgiKeys::FORM_STATUS => EmailForm\EnumFormStatus::SUBMITTED->value,
      ],
    ]);
    $response = $this->testedController->recipientsFilter(
      projectName: $this->project->getName(),
      projectId: $this->project->getId(),
      bulkTransactionId: null,
    );
    $this->assertInstanceOf(Http\JSONResponse::class, $response);
    $data = $response->getData();
    $this->assertInstanceOf(DTO\EmailFormRecipientsFilterSnapshotResponse::class, $data);
    $this->assertNotInstanceOf(DTO\EmailFormRecipientsFilterResponse::class, $data);
    $this->assertInstanceOf(DTO\EmailFormRecipientsFilterHistory::class, $data->filterHistory);
    $this->assertEquals(0, $data->filterHistory->historyPosition);
    $this->assertEquals(2, $data->filterHistory->historySize);
  }

  /** @return void */
  #[Attributes\Depends('testGenerateFormWithProject')]
  public function testRecipientsFilterHistoryResetFilter(): void
  {
    $this->generateProjectWebFormParameters(
      Util::arrayMergeRecursive(
        self::DEFAULT_PROJECT_USER_BASE,
        [
          EmailForm\EnumPostTag::RECIPIENTS_FILTER->value => [
            EmailForm\RecipientsFilterCgiKeys::RESET_INSTRUMENTS_FILTER => true,
            EmailForm\RecipientsFilterCgiKeys::FORM_STATUS => EmailForm\EnumFormStatus::SUBMITTED->value,
          ],
        ],
      ));
    $response = $this->testedController->recipientsFilter(
      projectName: $this->project->getName(),
      projectId: $this->project->getId(),
      bulkTransactionId: null,
    );
    $this->assertInstanceOf(Http\JSONResponse::class, $response);
    $data = $response->getData();
    $this->assertInstanceOf(DTO\EmailFormRecipientsFilterReloadResponse::class, $data);
    $domDoc = new DOMDocument('1.0', 'UTF-8');
    $domDoc->encoding = 'UTF-8';
    $this->assertEquals(true, $domDoc->loadHTML($data->contents, LIBXML_PEDANTIC));
    $this->assertEquals(0, $data->filterHistory->historyPosition);
    $this->assertEquals(1, $data->filterHistory->historySize);
  }

  /** @return void */
  #[Attributes\Depends('testGenerateFormWithProject')]
  public function testRecipientsFilterHistoryUndoRedo(): void
  {
    $this->testRecipientsFilterHistorySnapshot(); // generates an additional history record
    unset($this->postData[EmailForm\EnumPostTag::RECIPIENTS_FILTER->value][EmailForm\RecipientsFilterCgiKeys::HISTORY_SNAPSHOT]);
    $this->postData[EmailForm\EnumPostTag::RECIPIENTS_FILTER->value][EmailForm\RecipientsFilterCgiKeys::UNDO_INSTRUMENTS_FILTER] = true;
    // print_r($this->postData);
    /** @var EmailForm\RecipientsFilte $recipientsFilter */
    $recipientsFilter = $this->appContainer->get(EmailForm\RecipientsFilter::class);
    $filterHistory = $recipientsFilter->filterHistory();
    $this->assertEquals(2, $filterHistory->historySize);
    $this->assertEquals(0, $filterHistory->historyPosition);

    // Forciblly unbind
    new ReflectionProperty($recipientsFilter, 'requestParameters')->setValue($recipientsFilter, null);

    $response = $this->testedController->recipientsFilter(
      projectName: $this->project->getName(),
      projectId: $this->project->getId(),
      bulkTransactionId: null,
      );
    $this->assertInstanceOf(Http\JSONResponse::class, $response);
    $data = $response->getData();
    $this->assertInstanceOf(DTO\EmailFormRecipientsFilterReloadResponse::class, $data);
    $domDoc = new DOMDocument('1.0', 'UTF-8');
    $domDoc->encoding = 'UTF-8';
    $this->assertEquals(true, $domDoc->loadHTML($data->contents, LIBXML_PEDANTIC));
    $this->assertEquals(2, $data->filterHistory->historySize);
    $this->assertEquals(1, $data->filterHistory->historyPosition);

    $onlyInstruments = array_map(
      fn(array $optionInfo) => $optionInfo['value'],
      array_filter(
        $recipientsFilter->instrumentsFilter(),
        fn(array $optionInfo) => $optionInfo['flags'] & PageNavigation::SELECTED,
      ),
    );
    $this->assertEmpty($onlyInstruments); // initial state without filtered instruments.

    unset($this->postData[EmailForm\EnumPostTag::RECIPIENTS_FILTER->value][EmailForm\RecipientsFilterCgiKeys::UNDO_INSTRUMENTS_FILTER]);
    $this->postData[EmailForm\EnumPostTag::RECIPIENTS_FILTER->value][EmailForm\RecipientsFilterCgiKeys::REDO_INSTRUMENTS_FILTER] = true;

    // Forciblly unbind
    new ReflectionProperty($recipientsFilter, 'requestParameters')->setValue($recipientsFilter, null);

    $response = $this->testedController->recipientsFilter(
      projectName: $this->project->getName(),
      projectId: $this->project->getId(),
      bulkTransactionId: null,
      );
    $this->assertInstanceOf(Http\JSONResponse::class, $response);
    $data = $response->getData();
    $this->assertInstanceOf(DTO\EmailFormRecipientsFilterReloadResponse::class, $data);
    $domDoc = new DOMDocument('1.0', 'UTF-8');
    $domDoc->encoding = 'UTF-8';
    $this->assertEquals(true, $domDoc->loadHTML($data->contents, LIBXML_PEDANTIC));
    $this->assertEquals(2, $data->filterHistory->historySize);
    $this->assertEquals(0, $data->filterHistory->historyPosition);

    $onlyInstruments = array_map(
      fn(array $optionInfo) => $optionInfo['value'],
      array_filter(
        $recipientsFilter->instrumentsFilter(),
        fn(array $optionInfo) => $optionInfo['flags'] & PageNavigation::SELECTED,
      ),
    );
    $this->assertEqualsCanonicalizing(
      $this->postData[EmailForm\EnumPostTag::RECIPIENTS_FILTER->value][EmailForm\RecipientsFilterCgiKeys::INSTRUMENTS_FILTER],
      $onlyInstruments,
    );
  }

  /** @return void */
  public function testRecipientsFilterResponse(): void
  {
    $this->generateProjectWebFormParameters(
      Util::arrayMergeRecursive(
        self::DEFAULT_PROJECT_USER_BASE,
        [
          EmailForm\EnumPostTag::RECIPIENTS_FILTER->value => [
            EmailForm\RecipientsFilterCgiKeys::FORM_STATUS => EmailForm\EnumFormStatus::SUBMITTED->value,
          ],
        ],
      ),
    );
    $response = $this->testedController->recipientsFilter(
      projectName: $this->project->getName(),
      projectId: $this->project->getId(),
      bulkTransactionId: null,
    );
    // print_r($response->getData());
    $this->assertInstanceOf(Http\JSONResponse::class, $response);
    $data = $response->getData();
    $this->assertInstanceOf(DTO\EmailFormRecipientsFilterResponse::class, $data);
    /** @var DTO\EmailFormRecipientsFilterResponse $data */
    $domDoc = new DOMDocument('1.0', 'UTF-8');
    $domDoc->encoding = 'UTF-8';
    $this->assertEquals(true, $domDoc->loadHTML($data->instrumentsFilter, LIBXML_PEDANTIC));
    $this->assertEquals(true, $domDoc->loadHTML($data->participationStatusFilter, LIBXML_PEDANTIC));
    $this->assertNotEmpty($data->recipientsOptions);
    $this->assertEquals(true, $domDoc->loadHTML($data->recipientsOptions, LIBXML_PEDANTIC));
    // print_r($data->recipientsOptions);
  }

  /** @return void */
  public function testComposerLoadTemplate(): void
  {
    $this->generateProjectWebFormParameters([
      EmailForm\EnumPostTag::COMPOSER->value => [
        EmailForm\RecipientsFilterCgiKeys::FORM_STATUS => EmailForm\EnumFormStatus::SUBMITTED->value,
        EmailForm\ComposerCgiKeys::SUBJECT_TAG => $this->project->getName(),
        EmailForm\ComposerCgiKeys::FROM_TAG => EmailForm\EnumFromTag::ORCHESTRA,
        // projectId is also fetched without namespace
        // projectName is also fetched without namespace
        EmailForm\ComposerCgiKeys::OPERATION => Controller\EnumEmailFormComposerOperation::LOAD,
        EmailForm\ComposerCgiKeys::TOPIC => Controller\EnumEmailFormComposerTopic::TEMPLATE,
        EmailForm\ComposerCgiKeys::TEMPLATE_MESSAGES_SELECTOR => self::MAIL_MERGE_TAG,
      ],
    ]);
    $response = $this->testedController->composer(
      operation: Controller\EnumEmailFormComposerOperation::LOAD->value,
      topic: Controller\EnumEmailFormComposerTopic::TEMPLATE->value,
      projectId: $this->project->getId(),
      projectName: $this->project->getName(),
    );
    $this->assertInstanceOf(Http\JSONResponse::class, $response);
    $this->assertEquals(Http::STATUS_OK, $response->getStatus());
    $data = $response->getData();
    $this->assertInstanceOf(DTO\EmailFormComposerResponse::class, $data);
    /** @var DTO\EmailFormComposerResponse::class $data */
    $this->assertEquals(self::$templates[self::MAIL_MERGE_TAG]->getContents(), $data->requestData->messageText);
    $this->assertEquals(self::$templates[self::MAIL_MERGE_TAG]->getSubject(), $data->requestData->subject);
  }

  /** @return void */
  private function composerPreviewSetup(bool $fillDownloads = true): void
  {
    $this->mockHttpClient();
    $publicDownloads = $this->projectService->ensureDownloadsFolder($this->project->getId(), dry: true);
    if ($fillDownloads) {
      $this->userStorage->putContent($publicDownloads . '/entry.md', '# Hello World!');
    }
    $this->assertEquals((int)$fillDownloads, $this->userStorage->folderWalk($publicDownloads));
    /** @var Entities\EmailTemplate */
    $mailMergeTemplate = $this->generateMailMergeTemplate();
    $this->generateProjectWebFormParameters([
      EmailForm\EnumPostTag::COMPOSER->value => [
        EmailForm\RecipientsFilterCgiKeys::FORM_STATUS => EmailForm\EnumFormStatus::SUBMITTED->value,
        EmailForm\ComposerCgiKeys::SUBJECT_TAG => $this->project->getName(),
        EmailForm\ComposerCgiKeys::FROM_TAG => EmailForm\EnumFromTag::ORCHESTRA,
        // projectId is also fetched without namespace
        // projectName is also fetched without namespace
        EmailForm\ComposerCgiKeys::OPERATION => Controller\EnumEmailFormComposerOperation::PREVIEW,
        EmailForm\ComposerCgiKeys::TOPIC => Controller\EnumEmailFormComposerTopic::UNSPECIFIC,
        ///
        EmailForm\ComposerCgiKeys::SUBJECT => $mailMergeTemplate->getSubject(),
        EmailForm\ComposerCgiKeys::MESSAGE_TEXT => $mailMergeTemplate->getContents(),
      ],
    ]);
  }

  /**
   * Generate the preview for the all-substitutions template. This should just
   * work, setup the environment s.t. this can work. Following test will establish tests for error handling.
   *
   * @return void
   */
  public function testComposerPreview(): void
  {
    $this->composerPreviewSetup();
    $response = $this->testedController->composer(
      operation: Controller\EnumEmailFormComposerOperation::PREVIEW->value,
      topic: Controller\EnumEmailFormComposerTopic::UNSPECIFIC->value,
      projectId: $this->project->getId(),
      projectName: $this->project->getName(),
    );
    // print_r($response);
    $this->assertInstanceOf(Http\JSONResponse::class, $response);
    $this->assertEquals(Http::STATUS_OK, $response->getStatus());
    $data = $response->getData();
    $this->assertInstanceOf(DTO\EmailFormComposerResponse::class, $data);
    /** @var DTO\EmailFormComposerResponse $data */
    $requestData = $data->requestData;
    $this->assertNotEmpty($requestData->previewData);
    /** @var DTO\EmailFormComposerResponse $data */
    $domDoc = new DOMDocument('1.0', 'UTF-8');
    $domDoc->encoding = 'UTF-8';
    $this->assertEquals(true, $domDoc->loadHTML($requestData->previewData, LIBXML_PEDANTIC));
  }


  /**
   * Test whether an empty downloads folder ist properly reported if the
   * template contains the substitution key for the downloads share.
   *
   * @return void
   */
  public function testComposerPreviewCatchEmptyDownloads(): void
  {
    $this->composerPreviewSetup(fillDownloads: false);
    $response = $this->testedController->composer(
      operation: Controller\EnumEmailFormComposerOperation::PREVIEW->value,
      topic: Controller\EnumEmailFormComposerTopic::UNSPECIFIC->value,
      projectId: $this->project->getId(),
      projectName: $this->project->getName(),
    );
    // print_r($response);
    $this->assertInstanceOf(Http\JSONResponse::class, $response);
    $this->assertEquals(Http::STATUS_BAD_REQUEST, $response->getStatus());
    $data = $response->getData();
    $this->assertInstanceOf(DTO\EmailFormComposerResponse::class, $data);
    /** @var DTO\EmailFormComposerResponse $data */
    $requestData = $data->requestData;
    $this->assertNotEmpty($requestData->previewData);
    /** @var DTO\EmailFormComposerResponse $data */
    $domDoc = new DOMDocument('1.0', 'UTF-8');
    $domDoc->encoding = 'UTF-8';
    $this->assertEquals(true, $domDoc->loadHTML($requestData->previewData, LIBXML_PEDANTIC));
    $diagnostics = $requestData->diagnostics[EmailForm\Composer::DIAGNOSTICS_SHARE_LINK_VALIDATION][EmailForm\EnumGlobalSubstitutionKey::PROJECT_MUSIC_SHEETS_SHARE->value];
    $this->assertEquals(false, $diagnostics['status']);
    $this->assertEquals(0, $diagnostics['filesCount']);
    $this->assertEquals(Http::STATUS_OK, $diagnostics['httpStatusCode']);
    $publicDownloads = $this->projectService->ensureDownloadsFolder($this->project->getId(), dry: true);
    $this->assertEquals($publicDownloads, $diagnostics['folder']);
  }

  /**
   * Generate a message template with faulty external links and test whether
   * these are found and properly reported.
   *
   * @return void
   */
  public function testComposerPreviewCatchIllegalDownloadShares(): void
  {
    $this->composerPreviewSetup();
    $publicDownloads = $this->projectService->ensureDownloadsFolder($this->project->getId(), dry: true);
    [ 'mount_point' => $postProjectMedia ] = $this->projectGroupService->getProjectFolderLinkShare($this->project->getId());
    $subFolderTests = [
      'PROJECT_MUSIC_SHEETS_SHARE' => $publicDownloads,
      'POST_PROJECT_MEDIA_SHARE' => $postProjectMedia,
    ];
    $expectations = [];
    $messageText = '';
    foreach ($subFolderTests as $replacementKey => $path) {
      $subFolder = $this->userStorage->get($path . '/subFolder');
      [ 'files_sharing' => $subFolderUri ] = $this->simpleSharingService->linkShare($subFolder);
      $this->assertStringStartsWith(
        $this->request->getServerProtocol() . '://' . $this->request->getServerHost(),
        $subFolderUri,
      );
      $subFolderText = 'links to ' . $subFolderUri;
      $messageText .= '<a href="' . $subFolderUri . '">' . $subFolderText . '</a>
';
      $expectations['${GLOBAL::' . $this->l10n->t($replacementKey) . '}?dir=/subFolder'] = $subFolderUri;

      $file = $this->userStorage->putContent($subFolder->getPath() . '/Anleitung.md', '# Hello World!');
      [ 'files_sharing' => $fileUri ] = $this->simpleSharingService->linkShare($file);
      $fileText = 'links to ' . $fileUri;
      $messageText .= '<a href="' . $fileUri . '">' . $fileText . '</a>
';
      $expectations['${GLOBAL::' . $this->l10n->t($replacementKey) . ':/subFolder/Anleitung.md}'] = $fileUri;

      $oldUri = $this->linkSharesByPath[$path] ?? null;
      unset($this->linkSharesByPath[$path]);
      $folder = $this->userStorage->get($path);
      [ 'files_sharing' => $folderUri ] = $this->simpleSharingService->linkShare($folder);
      $expectations['${GLOBAL::' . $this->l10n->t($replacementKey) . '}'] = $folderUri;
      unset($this->linkSharesByPath[$path]);
      if ($oldUri) {
        $this->linkSharesByPath[$path] = $oldUri;
      }
      $folderText = 'links to ' . $folderUri;
      $messageText .= '<a href="' . $folderUri . '">' . $folderText . '</a>
';
    }
    $this->postData[
      EmailForm\EnumPostTag::COMPOSER->value
    ][
      EmailForm\ComposerCgiKeys::MESSAGE_TEXT
    ] = $messageText;
    // echo 'SUBFOLDER ' . $subFolderUri . PHP_EOL;
    // EmailForm\ComposerCgiKeys::MESSAGE_TEXT => $mailMergeTemplate->getContents(),
    $response = $this->testedController->composer(
      operation: Controller\EnumEmailFormComposerOperation::PREVIEW->value,
      topic: Controller\EnumEmailFormComposerTopic::UNSPECIFIC->value,
      projectId: $this->project->getId(),
      projectName: $this->project->getName(),
    );
    // print_r($response);
    $this->assertInstanceOf(Http\JSONResponse::class, $response);
    $this->assertEquals(Http::STATUS_OK, $response->getStatus());
    $data = $response->getData();
    $this->assertInstanceOf(DTO\EmailFormComposerResponse::class, $data);
    /** @var DTO\EmailFormComposerResponse $data */
    $requestData = $data->requestData;
    $previewData = $requestData->previewData;
    $messageText = $requestData->messageText;
    $this->assertNotEmpty($previewData);
    $domDoc = new DOMDocument('1.0', 'UTF-8');
    $domDoc->encoding = 'UTF-8';
    $this->assertEquals(true, $domDoc->loadHTML($previewData, LIBXML_PEDANTIC));
    $this->assertEquals(true, $domDoc->loadHTML($messageText, LIBXML_PEDANTIC));
    $diagnostics = $requestData->diagnostics;
    $validationData = $diagnostics['ExternalLinkValidation'] ?? null;
    // print_r($validationData);
    $this->assertNotEmpty($validationData);
    $this->assertTrue($validationData['status']);
    $this->assertEquals(6, count($validationData['good']));
    $this->assertEquals(6, count($validationData['bad']));
    $this->assertEquals(12, count($validationData['all']));
    // print_r($data);
    $index = 0;
    foreach ($expectations as $replacement => $uri) {
      $this->assertEquals($uri, $validationData['bad'][$index]['url']);
      $this->assertEquals('links to ' . $uri, $validationData['bad'][$index]['text']);
      $this->assertNotEmpty($validationData['bad'][$index]['explanations']);
      $this->assertEquals($replacement, $validationData['bad'][$index]['replacements'][$uri]);
      // $this->assertStringNotContainsString($uri, $previewData);
      $this->assertStringNotContainsString($uri, $messageText);
      ++$index;
    }
  }

  /**
   * Test some more substitution errors which should just be reported in
   * preview mode.
   *
   * @return void
   */
  public function testComposerPreviewCatchSubstitutionError(): void
  {
    $this->composerPreviewSetup();
    $this->postData = Util::arrayMergeRecursive(
      $this->postData,
      self::DEFAULT_PROJECT_USER_BASE,
      [
        EmailForm\EnumPostTag::RECIPIENTS_FILTER->value => [
          EmailForm\RecipientsFilterCgiKeys::FORM_STATUS => EmailForm\EnumFormStatus::SUBMITTED->value,
        ],
      ],
    );
    $messageText = '${FAULTYNAMESPACE::SOMETHING}
';
    $messageText .= '${' . EmailForm\EnumSubstitutionNamespace::GLOBAL->value . '::SOMETHING_NOT_EXISTING}
';
    $messageText .= '${' . EmailForm\EnumSubstitutionNamespace::MEMBER->value . '::SOMETHING_NOT_EXISTING}
';
    $this->postData[
      EmailForm\EnumPostTag::COMPOSER->value
    ][
      EmailForm\ComposerCgiKeys::MESSAGE_TEXT
    ] = $messageText;
    $response = $this->testedController->composer(
      operation: Controller\EnumEmailFormComposerOperation::PREVIEW->value,
      topic: Controller\EnumEmailFormComposerTopic::UNSPECIFIC->value,
      projectId: $this->project->getId(),
      projectName: $this->project->getName(),
    );
    // print_r($response);
    $this->assertInstanceOf(Http\JSONResponse::class, $response);
    $this->assertEquals(Http::STATUS_BAD_REQUEST, $response->getStatus());
    $data = $response->getData();
    $this->assertInstanceOf(DTO\EmailFormComposerResponse::class, $data);
    /** @var DTO\EmailFormComposerResponse $data */
    $requestData = $data->requestData;
    $previewData = $requestData->previewData;
    $messageText = $requestData->messageText;
    $this->assertNotEmpty($previewData);
    $domDoc = new DOMDocument('1.0', 'UTF-8');
    $domDoc->encoding = 'UTF-8';
    $this->assertEquals(true, $domDoc->loadHTML($previewData, LIBXML_PEDANTIC));
    $this->assertEquals(true, $domDoc->loadHTML($messageText, LIBXML_PEDANTIC));
    $diagnostics = $requestData->diagnostics;
    // print_r($diagnostics);
    $this->assertIsArray($diagnostics[EmailForm\Composer::DIAGNOSTICS_TEMPLATE_VALIDATION]);
    $templateValidation = $diagnostics[EmailForm\Composer::DIAGNOSTICS_TEMPLATE_VALIDATION];
    foreach (EmailForm\Composer::TEMPLATE_VALIDATION_ERRORS as $key) {
      if ($key == EmailForm\Composer::TEMPLATE_VALIDATION_PRECONDITION_ERRORS) {
        $this->assertArrayNotHasKey($key, $templateValidation);
      } else {
        $this->assertArrayHasKey($key, $templateValidation);
      }
    }
  }

  /**
   * Test that templates with personal substitution keys are not sent to the
   * mailing lists.
   *
   * @return void
   */
  public function testComposerPreviewCatchSubstitutionPreconditionError(): void
  {
    $this->composerPreviewSetup();
    $this->postData = Util::arrayMergeRecursive(
      $this->postData,
      self::MAILING_LIST_USER_BASE,
      [
        EmailForm\EnumPostTag::RECIPIENTS_FILTER->value => [
          EmailForm\RecipientsFilterCgiKeys::FORM_STATUS => EmailForm\EnumFormStatus::SUBMITTED->value,
        ],
      ],
    );
    $messageText = '${' . EmailForm\EnumSubstitutionNamespace::MEMBER->value . '::SOMETHING}
';
    $this->postData[
      EmailForm\EnumPostTag::COMPOSER->value
    ][
      EmailForm\ComposerCgiKeys::MESSAGE_TEXT
    ] = $messageText;
    $response = $this->testedController->composer(
      operation: Controller\EnumEmailFormComposerOperation::PREVIEW->value,
      topic: Controller\EnumEmailFormComposerTopic::UNSPECIFIC->value,
      projectId: $this->project->getId(),
      projectName: $this->project->getName(),
    );
    // print_r($response);
    $this->assertInstanceOf(Http\JSONResponse::class, $response);
    $this->assertEquals(Http::STATUS_BAD_REQUEST, $response->getStatus());
    $data = $response->getData();
    $this->assertInstanceOf(DTO\EmailFormComposerResponse::class, $data);
    /** @var DTO\EmailFormComposerResponse $data */
    $this->assertNotEmpty($data->messages);
    $requestData = $data->requestData;
    $previewData = $requestData->previewData;
    $messageText = $requestData->messageText;
    $this->assertNotEmpty($previewData);
    $domDoc = new DOMDocument('1.0', 'UTF-8');
    $domDoc->encoding = 'UTF-8';
    $this->assertEquals(true, $domDoc->loadHTML($previewData, LIBXML_PEDANTIC));
    $this->assertEquals(true, $domDoc->loadHTML($messageText, LIBXML_PEDANTIC));
    $this->assertEquals(true, $domDoc->loadHTML(implode($data->messages), LIBXML_PEDANTIC));
    $diagnostics = $requestData->diagnostics;
    // print_r($diagnostics);
    $this->assertIsArray($diagnostics[EmailForm\Composer::DIAGNOSTICS_TEMPLATE_VALIDATION]);
    $templateValidation = $diagnostics[EmailForm\Composer::DIAGNOSTICS_TEMPLATE_VALIDATION];
    $this->assertArrayHasKey(EmailForm\Composer::TEMPLATE_VALIDATION_PRECONDITION_ERRORS, $templateValidation);
  }

  /** @return void */
  private function composerUpdateElementSetup(): void
  {
    /** @var Entities\EmailTemplate */
    $this->generateProjectWebFormParameters(
      Util::arrayMergeRecursive(
        self::DEFAULT_PROJECT_USER_BASE,
        [
          EmailForm\EnumPostTag::COMPOSER->value => [
            EmailForm\RecipientsFilterCgiKeys::FORM_STATUS => EmailForm\EnumFormStatus::SUBMITTED->value,
            EmailForm\ComposerCgiKeys::SUBJECT_TAG => $this->project->getName(),
            EmailForm\ComposerCgiKeys::FROM_TAG => EmailForm\EnumFromTag::ORCHESTRA,
            // projectId is also fetched without namespace
            // projectName is also fetched without namespace
            EmailForm\ComposerCgiKeys::OPERATION => Controller\EnumEmailFormComposerOperation::UPDATE,
            EmailForm\ComposerCgiKeys::TOPIC => Controller\EnumEmailFormComposerTopic::ELEMENT,
            //
            EmailForm\ComposerCgiKeys::SUBJECT => 'Subject',
            EmailForm\ComposerCgiKeys::MESSAGE_TEXT => 'Message Text',
            //
            EmailForm\ComposerCgiKeys::FORM_ELEMENTS => Controller\EnumEmailFormComposerElement::cases(),
          ],
          EmailForm\EnumPostTag::RECIPIENTS_FILTER->value => [
            EmailForm\RecipientsFilterCgiKeys::SELECTED_RECIPIENTS => [$this->musician->getId()],
            EmailForm\RecipientsFilterCgiKeys::FORM_STATUS => EmailForm\EnumFormStatus::SUBMITTED->value,
          ],
        ],
      ),
    );
  }

  /** @return void */
  public function testComposerUpdateElement(): void
  {
    $this->composerUpdateElementSetup();
    $response = $this->testedController->composer(
      operation: Controller\EnumEmailFormComposerOperation::UPDATE->value,
      topic: Controller\EnumEmailFormComposerTopic::ELEMENT->value,
      projectId: $this->project->getId(),
      projectName: $this->project->getName(),
    );
    $this->assertInstanceOf(Http\JSONResponse::class, $response);
    $this->assertEquals(Http::STATUS_OK, $response->getStatus());
    $data = $response->getData();
    $this->assertInstanceOf(DTO\EmailFormComposerResponse::class, $data);
    /** @var DTO\EmailFormComposerResponse $data */
    $requestData = $data->requestData;
    $this->assertEqualsCanonicalizing(
      Controller\EnumEmailFormCOmposerElement::cases(),
      $requestData->formElements,
    );
    $this->assertInstanceOf(DTO\EmailFormComposerRequestDataTypes\ElementData::class, $requestData->elementData);
    /** @var DTO\EmailFormComposerRequestDataTypes\ElementData $elementData */
    $elementData = $requestData->elementData;
    $this->assertEquals(1, count($elementData->to));
    $this->assertStringContainsString($this->musician->getEmail(), $elementData->to[0]);
    $this->assertEquals($this->project->getName(), $elementData->subjectTag);
    $this->assertEquals(0, count($elementData->fileAttachments->attachments));
    $this->assertNotEmpty($elementData->fileAttachments->options);
    $domDoc = new DOMDocument('1.0', 'UTF-8');
    $domDoc->encoding = 'UTF-8';
    $this->assertEquals(true, $domDoc->loadHTML($elementData->fileAttachments->options, LIBXML_PEDANTIC));
    $this->assertEquals(0, count($elementData->eventAttachments->attachments));
    $this->assertNotEmpty($elementData->eventAttachments->options);
    $domDoc = new DOMDocument('1.0', 'UTF-8');
    $domDoc->encoding = 'UTF-8';
    $this->assertEquals(true, $domDoc->loadHTML($elementData->eventAttachments->options, LIBXML_PEDANTIC));
  }

  private const FREE_FORM_RECIPIENTS = [
    'Holger Hase <holger@hase.tld>' => [
      'text' => 'Holger Hase <holger@hase.tld>',
      'html' => 'Holger Hase <holger@hase.tld>',
      'value' => 'holger@hase.tld',
    ],
    'bilbo@baggins.tld' => [
      'text' => 'bilbo@baggins.tld',
      'html' => 'bilbo@baggins.tld',
      'value' => 'bilbo@baggins.tld',
    ],
    'frodo@baggins.tld (Frodo Baggins)' => [
      'text' => 'Frodo Baggins <frodo@baggins.tld>',
      'html' => 'Frodo Baggins <frodo@baggins.tld>',
      'value' => 'frodo@baggins.tld',
    ],
  ];

  /** @return void */
  public function testListContacts(): void
  {
    $this->postData[Controller\EnumEmailFormContactsPostParams::FREE_FORM_RECIPIENTS->value] = implode(', ', array_keys(self::FREE_FORM_RECIPIENTS));
    $result = $this->testedController->contacts(Controller\EnumEmailFormContactsOperation::LIST->value);
    $this->assertInstanceOf(Http\JSONResponse::class, $result);
    $data = $result->getData();
    $this->assertInstanceOf(DTO\EmailFormListContactsResponse::class, $data);
    $this->assertStringStartsWith('<select', $data->contents);
    $domDoc = new DOMDocument('1.0', 'UTF-8');
    $domDoc->encoding = 'UTF-8';
    $this->assertEquals(true, $domDoc->loadHTML($data->contents, LIBXML_PEDANTIC));
  }

  /** @return void */
  public function testSaveContacts(): void
  {
    $this->postData[Controller\EnumEmailFormContactsPostParams::ADDRESS_BOOK_CANDIDATES->value] = array_values(self::FREE_FORM_RECIPIENTS);
    $result = $this->testedController->contacts(Controller\EnumEmailFormContactsOperation::SAVE->value);
    $this->assertInstanceOf(Http\Response::class, $result);
    $this->assertEquals(Http::STATUS_OK, $result->getStatus());
    foreach (self::FREE_FORM_RECIPIENTS as $recipient) {
      $saved = array_filter($this->emailContacts, fn(array $contact) => $contact['email'] ?? null === $recipient['value']);
      $this->assertTrue(count($saved) > 0);
    }
  }

  /** @return void */
  public function testEnumGlobalSubstitutionKeyConsistency(): void
  {
    $composer = $this->appContainer->get(EmailForm\Composer::class);
    new ReflectionMethod($composer, 'generateGlobalSubstitutionHandlers')->invoke($composer);
    $substitutions = new ReflectionProperty($composer, 'substitutions')->getValue($composer);
    $globalSubstitutionKeys = array_keys($substitutions[EmailForm\EnumSUbstitutionNamespace::GLOBAL()]);
    $enumSubstutionNames = EmailForm\EnumGlobalSubstitutionKey::names();
    $enumSubstutionValues = EmailForm\EnumGlobalSubstitutionKey::values();
    $enumL10NValues = EmailForm\EnumGlobalSubstitutionKey::getL10NValues($this->l10n);
    $this->assertEqualsCanonicalizing($enumSubstutionNames, $globalSubstitutionKeys);
    $this->assertEqualsCanonicalizing($enumSubstutionValues, $globalSubstitutionKeys);
    $l10nPrefix = EmailForm\EnumGlobalSubstitutionKey::l10nTag();
    $globalL10NSubstitutionKeys = array_map(
      function(string $key) use ($l10nPrefix) {
        $translation = $this->l10n->t($l10nPrefix . $key);
        $this->assertNotEquals($l10nPrefix . $key, $translation);
        return $translation == $key ? $this->l10n->t($key) : $translation;
      },
      $globalSubstitutionKeys,
    );
    $this->assertEqualsCanonicalizing($enumL10NValues, $globalL10NSubstitutionKeys);
    foreach ($enumL10NValues as $untranslated => $translated) {
      $this->assertNotEquals($untranslated, $translated);
    }
  }

  private const L10N_EXCEPTIONS = [
    'EMAIL',
  ];

  /** @return void */
  public function testEnumMemberSubstitutionKeyConsistency(): void
  {
    $composer = $this->appContainer->get(EmailForm\Composer::class);
    new ReflectionMethod($composer, 'generateSubstitutionHandlers')->invoke($composer);
    $substitutions = new ReflectionProperty($composer, 'substitutions')->getValue($composer);
    $memberSubstitutionKeys = array_keys($substitutions[EmailForm\EnumSubstitutionNamespace::MEMBER->value]);
    $this->assertEquals(2 * count(EmailForm\EnumMemberSubstitutionKey::cases()), count($memberSubstitutionKeys) + count(self::L10N_EXCEPTIONS));
    $enumSubstutionNames = EmailForm\EnumMemberSubstitutionKey::names();
    $enumSubstutionValues = EmailForm\EnumMemberSubstitutionKey::values();
    $this->assertEqualsCanonicalizing($enumSubstutionNames, $enumSubstutionValues);
    $enumL10NValues = EmailForm\EnumMemberSubstitutionKey::getL10NValues($this->l10n);
    $translatedKeys = array_diff($memberSubstitutionKeys, $enumSubstutionNames);
    $this->assertEquals(count(EmailForm\EnumMemberSubstitutionKey::cases()), count($translatedKeys) + count(self::L10N_EXCEPTIONS));
    $untranslatedKeys = array_intersect($memberSubstitutionKeys, $enumSubstutionNames);
    $this->assertEquals(count(EmailForm\EnumMemberSubstitutionKey::cases()), count($untranslatedKeys));
    $this->assertEqualsCanonicalizing($enumSubstutionNames, $untranslatedKeys);
    $this->assertEqualsCanonicalizing($enumSubstutionValues, $untranslatedKeys);
    $l10nPrefix = EmailForm\EnumMemberSubstitutionKey::l10nTag();
    $memberL10NSubstitutionKeys = array_map(
      function(string $key) use ($l10nPrefix) {
        $translation = $this->l10n->t($l10nPrefix . $key);
        $this->assertNotEquals($l10nPrefix . $key, $translation);
        return $translation == $key ? $this->l10n->t($key) : $translation;
      },
      $untranslatedKeys,
    );
    $this->assertEqualsCanonicalizing($enumL10NValues, $memberL10NSubstitutionKeys);
    foreach ($enumL10NValues as $untranslated => $translated) {
      if (!in_array($untranslated, self::L10N_EXCEPTIONS)) {
        $this->assertNotEquals($untranslated, $translated);
      }
    }
  }
}
