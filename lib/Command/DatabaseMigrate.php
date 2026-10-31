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

use Throwable;

use Psr\Container\ContainerInterface;
use OCP\IL10N;
use OCP\IUserManager;
use OCP\IUserSession;
use Psr\Log\LoggerInterface as ILogger;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Terminal;

use OCA\CAFEVDB\Database\Doctrine\ORM\Entities\DoctrineMigrationsVersion;
use OCA\CAFEVDB\Database\EntityManager;
use OCA\CAFEVDB\Service\MigrationsServiceInterface;

/**
 * Apply all unapplied migrations one by one. In contrast to the stock
 * "migrations:migrate" command this does not use "all-or-nothing" for the
 * complete set, as structural (non-transactional) and content (transactional)
 * migrations must not be mixed in one run.
 *
 * Output depends on the verbosity:
 * - quiet: nothing
 * - normal: one line per applied migration, errors
 * - verbose (-v): additionally a summary table after the run
 * - very verbose (-vv): additionally a summary table before the run
 */
class DatabaseMigrate extends Command
{
  use AuthenticatedCommandTrait {
    AuthenticatedCommandTrait::authenticate as interactiveAuthenticate;
  }

  public const SUCCESS = Command::SUCCESS;
  public const ERROR = Command::FAILURE;

  private const DEFAULT_WIDTH = 132;
  // 14 (version) + 8 (status) + 10 (date) + 4 borders + 8 padding
  private const FIXED_COLUMNS_WIDTH = 44;
  private const MIN_DESCRIPTION_WIDTH = 15;

  /** {@inheritdoc} */
  public function __construct(
    protected string $appName,
    protected IL10N $l,
    protected ILogger $logger,
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
      ->setName($this->appName . ':database:migrate')
      ->setDescription('Apply all unapplied database migrations, one by one in version order.')
      ->addOption(
        'list',
        'l',
        InputOption::VALUE_NONE,
        'Only show the table of migrations and their status, do not execute anything.',
      )
      ->addOption(
        'dry-run',
        null,
        InputOption::VALUE_NONE,
        'Only state which migrations would be applied, do not execute anything.',
      )
      ->addOption(
        'user',
        'u',
        InputOption::VALUE_REQUIRED,
        'Cloud user to authenticate with. Together with "--password-file" this avoids interactive prompts.',
      )
      ->addOption(
        'password-file',
        null,
        InputOption::VALUE_REQUIRED,
        'File holding the password of the cloud user given by "--user".',
      );
  }

  /**
   * Authenticate non-interactively if "--user" and "--password-file" are
   * given, otherwise prompt on the console.
   *
   * @param InputInterface $input
   *
   * @param OutputInterface $output
   *
   * @return int
   */
  protected function authenticate(InputInterface $input, OutputInterface $output): int
  {
    $userId = $input->getOption('user');
    $passwordFile = $input->getOption('password-file');
    if (empty($userId) || empty($passwordFile)) {
      return $this->interactiveAuthenticate($input, $output);
    }

    $password = is_readable($passwordFile) ? file_get_contents($passwordFile) : false;
    $user = $this->userManager->get($userId);
    if ($password === false || $password === '' || $user === null) {
      // TRANSLATORS: Error message being unable to validate the given userId
      // TRANSLATORS: or get the password for that person. The userId is
      // TRANSLATORS: sustutued for %s.
      $output->writeln('<error>' . $this->l->t('Unable to obtain the credentials for "%s".', $userId) . '</error>');
      return self::ERROR;
    }
    $this->userSession->setUser($user);
    // The login event-handler binds the encryption-service and the entity-manager.
    if (!$this->userSession->login($userId, $password)) {
      $output->writeln('<error>' . $this->l->t('Login failed.') . '</error>');
      return self::ERROR;
    }
    $this->userId = $userId;
    $this->userPassword = $password;
    $this->entityManager = $this->appContainer->get(EntityManager::class);
    return self::SUCCESS;
  }

  /** {@inheritdoc} */
  protected function execute(InputInterface $input, OutputInterface $output): int
  {
    $result = $this->authenticate($input, $output);
    if ($result != 0) {
      return self::ERROR;
    }

    $dryRun = false;
    try {
      /** @var MigrationsServiceInterface $migrationsService */
      $migrationsService = $this->appContainer->get(MigrationsServiceInterface::class);

      if ($input->getOption('list')) {
        $this->renderSummary($output, $migrationsService);
        return self::SUCCESS;
      }

      $unapplied = $migrationsService->getUnapplied();
      if (empty($unapplied)) {
        $output->writeln($this->l->t('No unapplied migrations.'));
        return self::SUCCESS;
      }

      if ($output->isVeryVerbose()) {
        $this->renderSummary($output, $migrationsService);
      }

      $dryRun = (bool)$input->getOption('dry-run');

      foreach ($unapplied as $version => $description) {
        if ($dryRun) {
          $output->writeln($this->l->t('Would apply migration %1$s: %2$s', [ $version, $description ]));
          continue;
        }
        // TRANSLATORS: %1$s is version number for a database migration, %2$
        // TRANSLATORS: its description.
        $output->writeln($this->l->t('Applying migration %1$s: %2$s', [ $version, $description ]));
        $migrationsService->apply($version);
      }
    } catch (Throwable $t) {
      $output->writeln('<error>' . $this->l->t('Migration failed: %s', $t->getMessage()) . '</error>');
      if ($output->isVerbose()) {
        $output->writeln($t->getTraceAsString());
      }
      return self::ERROR;
    }

    if ($dryRun) {
      $output->writeln($this->l->t('Dry run: %d migration(s) would be applied.', count($unapplied)));
      return self::SUCCESS;
    }

    $output->writeln($this->l->t('Applied %d migration(s).', count($unapplied)));

    if ($output->isVerbose()) {
      $migrationsService->clearCache();
      $this->renderSummary($output, $migrationsService);
    }

    return self::SUCCESS;
  }

  /**
   * Render a table of all known migrations with status and execution date. The table is fitted to the terminal width, long descriptions are
   * wrapped into taller cells.
   *
   * @param OutputInterface $output
   *
   * @param MigrationsServiceInterface $migrationsService
   *
   * @return void
   */
  private function renderSummary(OutputInterface $output, MigrationsServiceInterface $migrationsService): void
  {
    $applied = $migrationsService->getApplied();
    $unapplied = $migrationsService->getUnapplied();

    $executed = [];
    /** @var DoctrineMigrationsVersion $entry */
    foreach ($this->entityManager->getRepository(DoctrineMigrationsVersion::class)->findAll() as $entry) {
      $executed[substr($entry->getVersion(), -14)] = $entry;
    }

    // Symfony silently falls back to 80 columns, so only trust it if the
    // width can really be determined.
    $terminalWidth = self::DEFAULT_WIDTH;
    if (getenv('COLUMNS') !== false || (defined('STDOUT') && stream_isatty(STDOUT))) {
      $terminalWidth = (new Terminal)->getWidth() ?: self::DEFAULT_WIDTH;
    }
    $descriptionWidth = max(self::MIN_DESCRIPTION_WIDTH, $terminalWidth - self::FIXED_COLUMNS_WIDTH);

    $migrations = $applied + $unapplied;
    ksort($migrations);

    $rows = [];
    foreach ($migrations as $version => $description) {
      $entry = $executed[$version] ?? null;
      $rows[] = [
        $version,
        isset($applied[$version]) || $entry !== null ? 'migrated' : 'new',
        $entry?->getExecutedAt()?->format('Y-m-d') ?? '',
        wordwrap((string)$description, $descriptionWidth, "\n", true),
      ];
    }

    $table = new Table($output);
    $table
      ->setHeaders([ 'Version', 'Status', 'Migrated At', 'Description' ])
      ->setRows($rows);
    $table->render();
  }
}
