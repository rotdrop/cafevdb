<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine <himself@claus-justus-heine.de>
 * @copyright 2026 Claus-Justus Heine
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

use OCP\IL10N;
use OCP\IUserManager;
use OCP\IUserSession;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Formatter\OutputFormatter;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

use OCA\CAFEVDB\Service\UserEncryptionService;

/** Force recryption of encrypted database entries for cloud users. */
class UserRecrypt extends Command
{
  use AuthenticatedCommandTrait;

  /** {@inheritdoc} */
  public function __construct(
    protected string $appName,
    protected IL10N $l,
    protected LoggerInterface $logger,
    protected IUserManager $userManager,
    protected IUserSession $userSession,
    protected ContainerInterface $appContainer,
  ) {
    parent::__construct();
  }

  /** {@inheritdoc} */
  protected function configure()
  {
    $this
      ->setName($this->appName . ':users:recrypt')
      ->setDescription('Unconditionally recrypt encrypted database entries for one or all cloud users.')
      ->setHelp('Authenticates interactively with an account that can decrypt the app database. '
        . 'No pending recryption request is required. Use --verbose to list entity classes and IDs.')
      ->addOption('user', 'u', InputOption::VALUE_REQUIRED, 'Cloud user ID to recrypt (not the authenticating user).')
      ->addOption('all', 'a', InputOption::VALUE_NONE, 'Recrypt all cloud users.')
      ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Enumerate entities without changing tokens, keys or database entries.');
  }

  /** {@inheritdoc} */
  protected function execute(InputInterface $input, OutputInterface $output): int
  {
    $userId = $input->getOption('user');
    $all = (bool)$input->getOption('all');
    if (($userId !== null) === $all || $userId === '') {
      $output->writeln('<error>Specify either --user=USER or --all.</error>');
      return self::INVALID;
    }
    if (!$all && $this->userManager->get($userId) === null) {
      $output->writeln('<error>Unknown cloud user: ' . OutputFormatter::escape($userId) . '</error>');
      return self::INVALID;
    }

    $result = $this->authenticate($input, $output);
    if ($result !== self::SUCCESS) {
      return $result;
    }

    /** @var UserEncryptionService $service */
    $service = $this->appContainer->get(UserEncryptionService::class);
    $userIds = $all
      ? array_map(fn($user) => $user->getUID(), $this->userManager->searchDisplayName(''))
      : [$userId];
    $dryRun = (bool)$input->getOption('dry-run');
    $entityCount = 0;
    $onEntity = function(object $entity) use ($output, $dryRun, &$entityCount): void {
      ++$entityCount;
      if (!$output->isVerbose()) {
        return;
      }
      $metadata = $this->entityManager->getClassMetadata(get_class($entity));
      $output->writeln(OutputFormatter::escape(sprintf(
        '  %s %s %s',
        $dryRun ? 'Would recrypt' : 'Recrypting',
        $metadata->getName(),
        json_encode($this->entityIdentifier($entity), JSON_THROW_ON_ERROR),
      )));
    };
    foreach ($userIds as $targetUserId) {
      $output->writeln(OutputFormatter::escape(
        ($dryRun ? 'Would recrypt user ' : 'Recrypting user ') . $targetUserId,
      ));
      $service->recrypt($targetUserId, dryRun: $dryRun, onEntity: $onEntity);
    }
    $output->writeln(sprintf(
      $dryRun ? 'Dry run: %d user(s), %d encrypted entity/entities.' : 'Recrypted %d user(s), %d encrypted entity/entities.',
      count($userIds),
      $entityCount,
    ));
    return self::SUCCESS;
  }

  /**
   * @param object $entity
   *
   * @return array Only identifier fields, including those of associated entities.
   */
  private function entityIdentifier(object $entity): array
  {
    $metadata = $this->entityManager->getClassMetadata(get_class($entity));
    return array_map(
      fn($value) => is_object($value) ? $this->entityIdentifier($value) : $value,
      $metadata->getIdentifierValues($entity),
    );
  }
}
