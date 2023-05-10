<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine <himself@claus-justus-heine.de>
 * @copyright 2011-2014, 2016, 2020, 2021, 2022, 2023 Claus-Justus Heine
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

namespace OCA\CAFEVDB\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputOption;

use OCP\IL10N;
use OCP\IUserSession;
use OCP\IUserManager;
use OCP\AppFramework\IAppContainer;
use OCP\IUser;
use OCP\Accounts\IAccountManager;
use OCP\Accounts\IAccountProperty;
use OCP\Accounts\PropertyDoesNotExistException;
use OC\Security\SecureRandom;
use OC\Authentication\TwoFactorAuth\ProviderLoader as TFAProviderLoader;
use OCP\Authentication\TwoFactorAuth\IProvider as ITFAProvider;
use OCP\Authentication\TwoFactorAuth\IRegistry as ITFARegistry;
use OCP\Authentication\TwoFactorAuth\IActivatableByAdmin as ITFAActivatableByAdmin;

use OCA\CAFEVDB\Service\ConfigService;
use OCA\CAFEVDB\Service\ProjectService;
use OCA\CAFEVDB\Service\MailingListsService;
use OCA\CAFEVDB\Database\Doctrine\ORM\Entities;
use OCA\CAFEVDB\Database\Doctrine\DBAL\Types\EnumMemberStatus;
use OCA\CAFEVDB\Events\PostPersistMusicianEmail;

/**
 * Sanitize some stuff for the members of the management board:
 *
 * - see that they all have the configured orchestra email set
 * - make sure they are members of the mailing-list
 * - register Signal as 2nd factor for authentication, as well as email
 */
class ExecutiveBoard extends Command
{
  use AuthenticatedCommandTrait;

  private const BOARD_USER_BACKEND = 'LDAP';

  private const TWO_FACTOR_PREFERENCES = [
    // email notification, needed as fallback
    [
      'appid' => 'twofactor_email',
      'providers' => [ 'email', ],
      'phone' => false,
      'values' => [
        'verified' => 'true',
      ],
    ],
    // signal messenger
    [
      'appid' => 'twofactor_gateway',
      'providers' => [ 'gateway_signal', /* 'gateway_sms', */ ],
      'phone' => true,
      'values' => [
        'signal_identifier' => '{PHONE}',
        'signal_verified' => 'true',
        // 'sms_identifier' => '{PHONE}',
        // 'sms_verified' => 'true',
      ],
    ],
    // notifications
    [
      'appid' => 'twofactor_nextcloud_notification',
      'providers' => [ 'twofactor_nextcloud_notification', ],
      'phone' => false,
      'values' => [
        'enabled' => '1',
      ],
    ],
  ];

    // phpcs:disable Squiz.Commenting.FunctionComment.Missing
  public function __construct(
    string $appName,
    IL10N $l10n,
    IUserManager $userManager,
    IUserSession $userSession,
    IAppContainer $appContainer,
  ) {
    parent::__construct();
    $this->appName = $appName;
    $this->l = $l10n;
    $this->userManager = $userManager;
    $this->userSession = $userSession;
    $this->appContainer = $appContainer;
  }
  // phpcs:enable

  /** {@inheritdoc} */
  protected function configure()
  {
    $this
      ->setName('cafevdb:executiveboard')
      ->setDescription('Ensure the members of the executive board have the necessary access rights.')
      ->addOption(
        'dry',
        null,
        InputOption::VALUE_NONE,
        'Just simulate, do not change anything.',
      )
      ->addOption(
        'member',
        'm',
        InputOption::VALUE_REQUIRED,
        'Just examine and fix things for the given user. If not given, all executive board members are examined.',
      )
      ;
  }

  /** {@inheritdoc} */
  protected function execute(InputInterface $input, OutputInterface $output): int
  {
    $dry = $input->getOption('dry');

    $onlyMember = $input->getOption('member');

    $result = $this->authenticate($input, $output);
    if ($result != 0) {
      return $result;
    }

    /** @var TFAProviderLoader $tfaProviderLoader */
    $tfaProviderLoader = $this->appContainer->get(TFAProviderLoader::class);

    /**@var ITFARegistry $tfaRegistry */
    $tfaRegistry = $this->appContainer->get(ITFARegistry::class);

    /** @var ConfigService $configService */
    $configService = $this->appContainer->get(ConfigService::class);

    /** @var ProjectService $projectService */
    $projectService = $this->appContainer->get(ProjectService::class);

    $group = $configService->getGroup();
    $groupAdmins = $configService->getGroupSubAdmins();

    if (!empty($group)) {
      $output->writeln($this->l->t('Orchestra group: %1$s (%2$s)', [ $group->getDisplayName(), $group->getGID() ]));
    } else {
      $output->writeln('<error>' . $this->l->t('Error: The orchestra group is not configured.') . '</error>');
      return 1;
    }
    if (empty($groupAdmins)) {
      $output->writeln('<error>' . $this->l->t('Error: The orchestra group has no administrators.') . '</error>');
      return 1;
    }
    $groupAdmins = array_map(fn(IUser $admin) => $admin->getDisplayName() . ' (' . $admin->getUID() . ')', $groupAdmins);
    $output->writeln($this->l->t('Group admins are: %s.', implode(', ', $groupAdmins)));

    /** @var IAccountManager $accountManager */
    $accountManager = $this->appContainer->get(IAccountManager::class);

    /** @var SecureRandom $secureRandom */
    $secureRandom = $this->appContainer->get(SecureRandom::class);

    $boardUserBackend = null;
    foreach ($this->userManager->getBackends() as $backend) {
      if ($backend->getBackendName() == self::BOARD_USER_BACKEND) {
        $boardUserBackend = $backend;
      }
    }
    if (empty($boardUserBackend)) {
      $output->writeln('<error>' . $this->l->t('"%s"-backend is not active, bailing out.', self::BOARD_USER_BACKEND) . '</error>');
      return 1;
    }

    list(, $emailFromDomain) = array_pad(explode('@', $configService->getConfigValue(ConfigService::EMAIL_FORM_ADDRESS_KEY)), null, 2);
    if (empty($emailFromDomain)) {
      $output->writeln('<error>' . $this->l->t('Error: Orchestra email contact address is not configured.') . '</error>');
    } else {
      $output->writeln($this->l->t('Orchestra email domain is "%1$s".', $emailFromDomain));
    }

    $repository = $this->getDatabaseRepository(Entities\Project::class);
    $executiveBoardProjectId = $configService->getConfigValue(ConfigService::EXECUTIVE_BOARD_PROJECT_ID_KEY);
    /** @var Entities\Project $executiveBoardProject */
    $executiveBoardProject = $repository->find($executiveBoardProjectId);

    $boardMembers = $executiveBoardProject->getParticipants();

    /** @var MailingListsService $listsService */
    $listsService = $this->appContainer->get(MailingListsService::class);
    if (!$listsService->isConfigured() || !$listsService->isReachable()) {
      if ($listsService->isConfigured()) {
        $output->writeln('<error>' . $this->l->t('The mailing-lists service is configured but not reachable.') . '</error>');
      }
      $listsService = null;
    } else {
      $listId = $executiveBoardProject->getMailingListId();
    }

    $output->writeln('');

    if (!empty($onlyMember)) {
      $boardUser = $configService->getUser($onlyMember);
    }

    $numFound = 0;

    /** @var Entities\ProjectParticipant $boardMember */
    foreach ($boardMembers as $boardMember) {
      $problems = 0;
      $fixed = [];

      $musician = $boardMember->getMusician();
      $userId = $musician->getUserIdSlug();

      if (!$boardMember->getRegistration()) {
        $output->writeln($this->l->t('Skipping user "%1$s" as its participation in the executive board is not confirmed.', [ $userId ]), OutputInterface::VERBOSITY_VERBOSE);
        $output->writeln('', OutputInterface::VERBOSITY_VERBOSE);
        continue;
      }

      if (!empty($boardMember->getDeleted())) {
        $output->writeln(
          $this->l->t(
            'Skipping user "%1$s" as its participation in the executive board has been deleted on %2$s.', [
              $userId,
              $this->l->l('date', $boardMember->getDeleted(), [ 'width' => 'medium' ]),
            ],
          ),
          OutputInterface::VERBOSITY_VERBOSE,
        );
        $output->writeln('', OutputInterface::VERBOSITY_VERBOSE);
        continue;
      }

      $publicName = $musician->getPublicName(firstNameFirst: true);

      if (!empty($onlyMember) && $onlyMember != $userId) {
        $output->writeln($this->l->t('Skipping user "%1$s" (operation on only "%2$s was requested)".', [ $userId, $onlyMember ]), OutputInterface::VERBOSITY_VERBOSE);
        continue;
      }

      ++$numFound;

      $output->writeln($publicName . ' (' . $userId . ')');
      $indent = '  ';
      $boardUser = $configService->getUser($userId);
      if (empty($boardUser)) {
        ++$problems;
        $output->writeln('<error>' . $this->l->t('Error: "%1$s" is not a cloud-user.', $userId) . '</error>');
        if ($dry) {
          $output->writeln($indent . $this->l->t('Would add "%s" as cloud-user (dry-run).', $userId));
        } else {
          $output->writeln($indent . $this->l->t('Adding "%s" as cloud-user.', $userId));
          $boardUser = $this->userManager->createUserFromBackend($userId, $secureRandom->generate(16), $boardUserBackend);
          if (empty($boardUser)) {
            $output->writeln('<error>' . $this->l->t('Error: Unable to add "%1$s" as cloud-user.', $userId) . '</error>');
            continue;
          }
        }
        $fixed[] = 'cloud user';
      }

      $cloudUserBackend = $boardUser->getBackend()->getBackendName();

      $output->writeln($indent . $this->l->t('User backend: %s', $cloudUserBackend), OutputInterface::VERBOSITY_VERBOSE);
      if ($cloudUserBackend != self::BOARD_USER_BACKEND) {
        $output->writeln('<error>' . $this->l->t('User backend should be "%1$s", but is "%2$s".', [
          self::BOARD_USER_BACKEND, $cloudUserBackend,
        ]) . '</error>');
        ++$problems;
        if ($dry) {
          $output->writeln($indent . $this->l->t('Would add "%1$s" to the "%2$s" user backend. (dry-run)', [ $userId, self::BOARD_USER_BACKEND ]));
        } else {
          $output->writeln($indent . $this->l->t('Adding "%1$s" to the "%2$s" user backend.', [ $userId, self::BOARD_USER_BACKEND ]));
          $boardUser = $this->userManager->createUserFromBackend($userId, $secureRandom->generate(16), $boardUserBackend);
          if (empty($boardUser)) {
            $output->writeln('<error>' . $this->l->t('Error: Unable to add "%1$s" to the "%2$s" user backend.', [
              $userId,
              self::BOARD_USER_BACKEND
            ]) . '</error>');
            continue;
          }
        }
        $fixed[] = 'user backend';
      }

      if (!$configService->inGroup($userId)) {
        ++$problems;
        $output->writeln('<error>' . $this->l->t('Error: "%1$s" is not a member of the orchestra group "%2$s".', [ $userId, $group->getGID() ]) . '</error>');
        if ($dry) {
          $output->writeln($indent . $this->l->t('Would add "%1$s" to "%2$s" (dry-run).', [ $userId, $group->getGID() ]), OutputInterface::VERBOSITY_VERBOSE);
        } else {
          $output->writeln($indent . $this->l->t('Adding "%1$s" to "%2$s".', [ $userId, $group->getGID() ]), OutputInterface::VERBOSITY_VERBOSE);
          $group->addUser($boardUser);
        }
        $fixed[] = 'group membership';
      }

      if ($publicName != $boardUser->getDisplayName()) {
        ++$problems;
        $output->writeln('<error>' . $this->l->t('Error: display-name "%1$s" differs from database display name "%2$s".', [ $boardUser->getDisplayName(), $publicName ]) . '</error>');
        if ($dry) {
          $output->writeln($indent . $this->l->t('Would set the display-name to "%s" (dry-run).', $publicName));
        } else {
          $output->writeln($indent . $this->l->t('Setting the display-name to "%s".', $publicName));
          $boardUser->setDisplayName($publicName);
        }
        $fixed[] = 'display name';
      }

      $emails = [
        $boardUser->getEMailAddress(),
        $boardUser->getPrimaryEMailAddress(),
      ];
      $account = $accountManager->getAccount($boardUser);
      $cloudFurtherEmails = $account->getPropertyCollection(IAccountManager::COLLECTION_EMAIL);
      /** @var IAccountProperty $emailProperty */
      foreach ($cloudFurtherEmails->getProperties() as $emailProperty) {
        $emails[] = $emailProperty->getValue();
      }
      $emails = array_filter($emails);
      $output->writeln($indent . $this->l->t('Emails: %s', implode(', ', $emails)), OutputInterface::VERBOSITY_VERBOSE);
      $personalOrchestraEmail = $userId . '@' . $emailFromDomain;
      if (!in_array($personalOrchestraEmail, $emails)) {
        ++$problems;
        $output->writeln('<error>' . $this->l->t('Error: Personalized orchestra email "%s" is not configured.', $personalOrchestraEmail) . '</error>');
        if ($dry) {
          $output->writeln($indent . $this->l->t('Would add "%s" to the account settings (dry-run).', $personalOrchestraEmail));
        } else {
          $output->writeln($indent . $this->l->t('Adding "%s" to the account settings.', $personalOrchestraEmail));
          $cloudFurtherEmails->addPropertyWithDefaults($personalOrchestraEmail);
          $emailProperty = $cloudFurtherEmails->getPropertyByValue($personalOrchestraEmail);
          $emailProperty->setLocallyVerified(IAccountManager::VERIFIED);
          $emailProperty->setVerified(IAccountManager::VERIFIED);
          $accountManager->updateAccount($account);
        }
        $fixed[] = 'personal email address';
        $emails[] = $personalOrchestraEmail;
      }
      sort($emails);

      $dbEmails = array_map(fn(Entities\MusicianEmailAddress $emailAddress) => $emailAddress->getAddress(), $musician->getEmailAddresses()->toArray());
      sort($dbEmails);

      $missingDbEmails = array_diff($emails, $dbEmails);
      if (!empty($missingDbEmails)) {
        ++$problems;
        $output->writeln('<error>' . $this->l->t('Cloud emails not present in the data-base: %s', implode(', ', $missingDbEmails)) . '</error>');
        if ($dry) {
          $output->writeln($indent . $this->l->t('Would add to the orchestra db: %s (dry-run)', implode(', ', $missingDbEmails)));
        } else {
          $output->writeln($indent . $this->l->t('Adding to the orchestra db: %s', implode(', ', $missingDbEmails)));
          foreach ($missingDbEmails as $cloudEmail) {
            $musician->addEmailAddress($cloudEmail);
          }
          $this->flush();
        }
        $fixed[] = 'email addresses';
      }

      $missingCloudEmails = array_diff($dbEmails, $emails);
      if (!empty($missingCloudEmails)) {
        ++$problems;
        $output->writeln('<error>' . $this->l->t('Db emails not present in the cloud account settings: %s', implode(', ', $missingCloudEmails)) . '</error>');
        if ($dry) {
          $output->writeln($indent . $this->l->t('Would add to the account settings: %s (dry-run)', implode(', ', $missingCloudEmails)));
        } else {
          $output->writeln($indent . $this->l->t('Adding to the account settings: %s', implode(', ', $missingCloudEmails)));
          foreach ($missingCloudEmails as $dbEmail) {
            $cloudFurtherEmails->addPropertyWithDefaults($dbEmail);
            $emailProperty = $cloudFurtherEmails->getPropertyByValue($dbEmail);
            $emailProperty->setLocallyVerified(IAccountManager::VERIFIED);
            $emailProperty->setVerified(IAccountManager::VERIFIED);
            $accountManager->updateAccount($account);
            $emails[] = $dbEmail;
          }
        }
        $fixed[] = 'email addresses';
      }

      // above code in principle should already have configured the mailing-list membership
      if (!empty($listsService) && !empty($listId)) {
        foreach ($emails as $email) {
          $subscription = $listsService->getSubscription($listId, $email);
          if (empty($subscription)) {
            ++$problems;
            $output->writeln('<error>' . $this->l->t('Error: email "%1$s" is not subscribed to the mailing list "%2$s".', [
              $email, $listId
            ]) . '</error>');
            if ($dry) {
              $output->writeln($indent . $this->l->t('Would subscribe "%1$s" to "%2$s" (dry-run).', [
                $email, $listId
              ]));
            } else {
              $output->writeln($indent . $this->l->t('Subscribing "%1$s" to "%2$s".', [
                $email, $listId
              ]));
            }
            $fixed[] = 'mailing list subscription';
          }
        }
        if (!$dry) {
          $projectService->ensureMailingListSubscription($boardMember);
        }
      }

      // phone number sync

      try {
        $phoneProperty = $account->getProperty(IAccountManager::PROPERTY_PHONE);
        $cloudPhone = $phoneProperty->getValue();
      } catch (PropertyDoesNotExistException $e) {
        $cloudPhone = null;
      }
      $dbPhone = str_replace(' ', '', $musician->getMobilePhone());
      if (empty($cloudPhone)) {
        $output->writeln('<error>' . $this->l->t('The cloud user account has no phone number configured.') . '</error>');
        ++$problems;
      }
      if (empty($dbPhone)) {
        $output->writeln('<error>' . $this->l->t('The board member has no mobile phone number configured.') . '</error>');
        ++$problems;
      }
      if (!empty($cloudPhone) && !empty($dbPhone) && $cloudPhone != $dbPhone) {
        $output->writeln('<error>' . $this->l->t('The board member has inconsistent phone numbers: %1$s vs. %2$s.', [ $cloudPhone, $dbPhone ]) . '</error>');
        ++$problems;
      }
      if (!empty($dbPhone) && $dbPhone != $cloudPhone) {
        if ($dry) {
          $output->writeln($indent . $this->l->t('Would set the cloud account phone to %s (dry-run).', $dbPhone), OutputInterface::VERBOSITY_VERBOSE);
        } else {
          $output->writeln($indent . $this->l->t('Setting the cloud account phone to %s.', $dbPhone), OutputInterface::VERBOSITY_VERBOSE);
          if ($phoneProperty) {
            $phoneProperty->setValue($dbPhone);
          } else {
            $account->setProperty(IAccountManager::PROPERTY_PHONE, $dbPhone, IAccountManager::SCOPE_PRIVATE, IAccountManager::VERIFIED);
          }
          $accountManager->updateAccount($account);
        }
        $fixed[] = 'mobile phone';
      }
      $output->writeln($indent . $this->l->t('Phone number: %s', $dbPhone));

      // fetch all TFA provider states.
      $tfaRegistryStates = $tfaRegistry->getProviderStates($boardUser);

      // fetch all TFA providers
      $tfaProviders = $tfaProviderLoader->getProviders($boardUser);

      // enable some standard two-factor things and set them to verified
      $cloudConfig = $configService->getCloudConfig();
      foreach (self::TWO_FACTOR_PREFERENCES as $configItem) {
        $appName = $configItem['appid'];
        if ($configItem['phone'] && empty($dbPhone)) {
          continue;
        }
        foreach ($configItem['values'] as $configKey => $configValue) {
          if ($configValue == '{PHONE}') {
            $configValue = $dbPhone;
          }
          $configuredValue = $cloudConfig->getUserValue($userId, $appName, $configKey);
          if ($configuredValue !== $configValue) {
            ++$problems;
            $output->writeln(
              '<error>'
              . $this->l->t('User setting "%1$s" (%2$s) is "%3$s" but should be "%4$s".', [
                $configKey, $appName, $configuredValue, $configValue
              ])
              . '</error>'
            );
            if ($dry) {
              $output->writeln($indent . $this->l->t('Would set "%1$s" (%2$s) to "%3$s" (dry-run).', [ $configKey, $appName, $configValue ]));
            } else {
              $output->writeln($indent . $this->l->t('Setting "%1$s" (%2$s) to "%3$s".', [ $configKey, $appName, $configValue ]));
              $cloudConfig->setUserValue($userId, $appName, $configKey, $configValue);
            }
            $fixed[] = 'twofactor configuration';
          } else {
            $output->writeln($indent . $this->l->t('Configuration "%1$s" (%2$s) is "%3$s".', [ $configKey, $appName, $configValue ]), OutputInterface::VERBOSITY_VERBOSE);
          }
        }
        foreach ($configItem['providers'] as $providerId) {
          if (empty($tfaRegistryStates[$providerId])) {
            ++$problems;
            $output->writeln(
              '<error>'
              . $this->l->t('TFA-Provider "%1$s" is not enabled.', $providerId)
              . '</error>'
            );
            if ($dry) {
              $output->writeln($indent . $this->l->t('Would enable TFA-Provider "%1$s" (dry-run).', $providerId));
            } else {
              $output->writeln($indent . $this->l->t('Enabling TFA-Provider "%1$s".', $providerId));
              /** @var ITFAProvider $provider */
              $provider = $tfaProviders[$providerId];
              if (!$provider->isTwoFactorAuthEnabledForUser($boardUser) && $provider instanceof ITFAActivatableByAdmin) {
                $provider->enableFor($boardUser);
              }
              $tfaRegistry->enableProviderFor($provider, $boardUser);
            }
          } else {
            $output->writeln($indent . $this->l->t('TFA-Provider "%1$s" is enabled.', $providerId), OutputInterface::VERBOSITY_VERBOSE);
          }
        }
      }

      if ($problems > 0) {
        $output->writeln('<error>' . $this->l->n('Error: found %n problem.', 'Error: found %n problems.', $problems) . '</error>');
      } else {
        $output->writeln($indent . 'OK');
      }
      $output->writeln('');
    }

    if (!empty($onlyMember) && $numFound == 0) {
      $output->writeln(
        '<error>' .  $this->l->t('Error: user with uid "%1$s" is not part of the executive board.
This has to be fixed first by adding that person to the executive board project "%2$s".', [
            $onlyMember, $executiveBoardProject->getName()
          ])
        . '</error>'
      );
    }

    return 0;
  }
}
