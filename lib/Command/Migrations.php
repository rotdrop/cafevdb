<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine <himself@claus-justus-heine.de>
 * @copyright 2011-2024 Claus-Justus Heine
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

use OCP\AppFramework\IAppContainer;
use OCP\IL10N;
use OCP\ISession;
use OCP\IUserSession;
use OCP\IUserManager;

use Symfony\Component\Console\Terminal;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\DescriptorHelper;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\Question;

use OCA\CAFEVDB\Maintenance\IMigration;
use OCA\CAFEVDB\Service\EncryptionService;
use OCA\CAFEVDB\Service\MigrationsService;

/** Database (and non-database) migration management. */
class Migrations extends Command
{
  use AuthenticatedCommandTrait;

  private const ACTION_NONE = 'none';
  private const ACTION_LIST = 'list';
  private const ACTION_APPLY = 'apply';

  private const OPTION_DRY_RUN = 'dry-run';
  private const OPTION_FORCE = 'force';

  private const STATUS_PENDING = 'pending';
  private const STATUS_APPLIED = 'applied';

  private const SORT_ASCENDING = 'ascending';
  private const SORT_DESCENDING = 'descending';
  private const SORT_DEFAULT = self::SORT_ASCENDING;

  private const APPLY_SINGLE = 'single';
  private const APPLY_AUTO = 'auto';
  private const APPLY_DEFAULT = self::APPLY_AUTO;

  /**
   * @var bool
   *
   * Run in simulation mode.
   */
  private bool $dry;

  /** @var bool
   *
   * Run in force mode.
   */
  private bool $force;

  /** @var MigrationsService */
  private MigrationsService $migrationsService;

  /** {@inheritdoc} */
  public function __construct(
    protected string $appName,
    protected IL10N $l,
    protected IUserManager $userManager,
    protected IUserSession $userSession,
    protected ISession $session,
    protected IAppContainer $appContainer,
  ) {
    parent::__construct();
  }

  /** {@inheritdoc} */
  protected function configure()
  {
    $this
      ->setName('cafevdb:migrations')
      ->setDescription($this->l->t('Manage migrations of the "%s" private database.', $this->appName))
      ->addOption(
        self::ACTION_LIST,
        'l',
        InputOption::VALUE_OPTIONAL,
        $this->l->t('List all migrations with their status (applied vs. pending).'
                    . ' The option takes an optional argument "%s" or "s" in order to specify the sort direction of the migration listing (defualt: "%s")', [
          $this->l->t(self::SORT_ASCENDING),
          $this->l->t(self::SORT_DESCENDING),
          $this->l->t(self::SORT_DEFAULT),
        ]),
        false,
      )
      ->addOption(
        self::ACTION_APPLY,
        'a',
        InputOption::VALUE_OPTIONAL,
        $this->l->t(
          'Apply the given or all pending migrations.'
          . ' If the specified migration has already been applied or is unapplied but out of order (not the oldest unapplied migration or if any later migration has already been applied)'
          . ' then it is also necessary to specify the "--force/-f" parameter in order to forcibly try to apply the migration.'
          . ' The argument to this option can either be a specific version'
          . ' (see "--list" to get a  list of available migrations)'
          . ' or "%s" in order to apply the oldest unapplied migration but no more,'
          . ' or "%s" in order to apply all yet unapplied migrations one after another.'
          . ' The default is "%s", i.e. apply all yet unapplied migrations.', [
          $this->l->t(self::APPLY_SINGLE),
          $this->l->t(self::APPLY_AUTO),
          $this->l->t(self::APPLY_DEFAULT),
          ]),
        false,
      )
      ->addOption(
        self::OPTION_FORCE,
        'f',
        InputOption::VALUE_NONE,
        $this->l->t('Forcibly apply the specified migration, disregarding the position of the migration in the given ordering of all migrations and disregarding the status of the migration.'),
      )
      ->addOption(
        self::OPTION_DRY_RUN,
        'd',
        InputOption::VALUE_NONE,
        $this->l->t(
          'If "--apply / -a" has also been specified, then simply print the list of migration that would have been applied, but do not actually apply them.'
          . ' Note that "--dry" beats "--force".'
        ),
      )
      ;
  }

  /**
   * @param string $version
   * @param string $description
   * @param OutputInterface $output
   *
   * @return void
   */
  private function applyOne(string $version, string $description, OutputInterface $output):void
  {
    if (!$this->dry) {
      $output->writeln('<info>' . $this->l->t('Applying the following migration:') . '</info>');
      $output->writeln('<info>' . $version . '</info>: ' . $description);
      $this->migrationsService->apply($version);
    } else {
      $output->writeln('<info>' . $this->l->t('Simulation mode: would apply the following migration:') . '</info>');
      $output->writeln('<info>' . $version . '</info>: ' . $description);
    }
  }

  /** {@inheritdoc} */
  protected function execute(InputInterface $input, OutputInterface $output): int
  {
    $action = self::ACTION_NONE;
    $optionCheck = false;

    $list = $input->getOption(self::ACTION_LIST);
    if ($list !== false) {
      if ($list === null) {
        $list = self::SORT_DEFAULT;
      }
      $action = self::ACTION_LIST;
      $optionCheck = true;
      $l10nAscending = $this->l->t(self::SORT_ASCENDING);
      $l10nDescending = $this->l->t(self::SORT_DESCENDING);
      if (str_starts_with(self::SORT_ASCENDING, $list) || str_starts_with($l10nAscending, $list)) {
        $list = self::SORT_ASCENDING;
      } elseif (str_starts_with(self::SORT_DESCENDING, $list) || str_starts_with($l10nDescending, $list)) {
        $list = self::SORT_DESCENDING;
      } else {
        $optionCheck = false;
        $output->writeln(
          '<error>'
          . $this->l->t(
            'Unknown sort direction "%s" specified.'
            . ' The sort direction must be empty in order to use the default "%s",'
            . ' or "%s" in order to sort ascending or "%s" in order to sort descending.', [
              $list,
              $this->l->t(self::SORT_DEFAULT),
              $l10nAscending,
              $l10nDescending,
            ])
          . '</error>');
      }
    }

    $apply = $input->getOption(self::ACTION_APPLY);
    $version = null;
    if ($apply !== false) {
      $optionCheck = true;
      if ($apply === null) {
        $apply = self::APPLY_DEFAULT;
      }
      $l10nAuto = $this->l->t(self::APPLY_AUTO);
      $l10nSingle = $this->l->t(self::APPLY_SINGLE);
      if (str_starts_with(self::APPLY_AUTO, $apply) || str_starts_with($l10nAuto, $apply)) {
        $apply = self::APPLY_AUTO;
      } elseif (str_starts_with(self::APPLY_SINGLE, $apply) || str_starts_with($l10nSingle, $apply)) {
        $apply = self::APPLY_SINGLE;
      } elseif (preg_match(MigrationsService::VERSION_REGEXP, $apply)) {
        $version = $apply;
      } else {
        $optionCheck = false;
        $output->writeln(
          '<error>'
          . $this->l->t(
            'Unknown migration "%s" specified. The argument to "apply" must be empty in order to use the default "%s",'
            . ' or "%s" in order to automatically apply all migrations'
            . ' or "%s" in order to apply the oldest unapplied migration,'
            . ' or it must be the version of an existing migration.', [
              $apply,
              $this->l->t(self::APPLY_DEFAULT),
              $l10nAuto,
              $l10nSingle,
            ])
          . '</error>');
      }
      if ($action != self::ACTION_NONE) {
        $output->writeln('<error>' . $this->l->t('Exactly one of the two options "--list" and "--apply" has to be specified, you gave both.') . '</error>');
        $optionCheck = false;
      }
      $action = self::ACTION_APPLY;
    }

    $this->force = $input->getOption(self::OPTION_FORCE);
    $this->dry = $input->getOption(self::OPTION_DRY_RUN);
    if ($this->dry && $this->force) {
      $output->writeln(
        '<info>'
        . $this->l->t('"--%s" as well as "--%s" have been specified, "--force" will not lead to applying migrations.', [
          self::OPTION_DRY_RUN,
          self::OPTION_FORCE,
        ])
        . '</info>'
      );
    }

    if ($action == self::ACTION_LIST && ($this->force || $this->dry)) {
      $output->writeln(
        '<info>'
        . $this->l->t('"--%s" and "--%s" are ignored in list-mode', [self::OPTION_FORCE, self::OPTION_DRY_RUN])
        . '</info>'
      );
    }

    if ($action == self::ACTION_NONE || !$optionCheck) {
      $output->writeln('');
      if ($action == self::ACTION_NONE) {
        $output->writeln('<error>' . $this->l->t('You need to specify at least one action.') . '</error>');
      } else {
        $output->writeln('<error>' . $this->l->t('Command failed, please have a look at the error messages above.') . '</error>');
      }
      $output->writeln('');
      (new DescriptorHelper)->describe($output, $this);
      return 1;
    }

    $result = $this->authenticate($input, $output);
    if ($result != 0) {
      return $result;
    }

    /** @var MigrationsService $migrationsService */
    $this->migrationsService = $this->appContainer->get(MigrationsService::class);
    $migrations = $this->migrationsService->getAll();
    $pending = $this->migrationsService->getUnapplied();

    switch ($action) {
      case self::ACTION_APPLY:
        if ($this->dry) {
          $output->writeln('<info>' . $this->l->t('"--%s" has been specified, running in simulation mode, no migrations will be applied.', self::OPTION_DRY_RUN) . '</info>');
        }
        if ($version !== null) {
          if (!isset($migrations[$version])) {
            $output->writeln(
              '<error>'
              . $this->l->t('Requested migration "%s" cannot be found. Please use "--list" to display a list of available migrations.', $version)
              . '</error>'
            );
            return 1;
          }
          if (!isset($pending[$version])) {
            $output->writeln('<error>' . $this->l->t('The following migration has already been applied:') . '</error>');
            $output->writeln('<info>' . $version . '</info>: ' . $migrations[$version]);
            if ($this->force) {
              if ($this->dry) {
                $output->writeln('<info>' . $this->l->t('Simulation mode: "--%s" is in effect, would apply the migration anyway, but doing nothing.', self::OPTION_FORCE) . '</info>');
              } else {
                $output->writeln('<info>' . $this->l->t('"--%s" is in effect, applying the migration anyway.', self::OPTION_FORCE) . '</info>');
              }
            } else {
              return 1;
            }
          }
          $description = $migrations[$version];
          $this->applyOne($version, $description, $output);
          return 0;
        } elseif ($apply == self::APPLY_SINGLE) {
          ksort($pending);
          $version = reset(array_keys($pending));
          $description = $pending[$version];
          $this->applyOne($version, $description, $output);
          return 0;
        } else { // auto
          ksort($pending);
          foreach ($pending as $version => $description) {
            $this->applyOne($version, $description, $output);
          }
        }
        break;
      case self::ACTION_LIST:
        if ($this->migrationsService->needsMigration()) {
          $output->writeln('<info>' . $this->l->t('The app "%s" needs database or other migrations, please see below.', $this->appName) . '</info>');
        }

        if ($list == self::SORT_ASCENDING) {
          ksort($migrations);
        } else {
          krsort($migrations);
        }
        if (empty($migrations)) {
          $output->writeln('<info>' . $this->l->t('No migrations are registered, neither pending nor already applied migrations.') . '</info>');
          return 0;
        }
        $rows = [];
        $statusText = [
          self::STATUS_APPLIED => $this->l->t('applied'),
          self::STATUS_PENDING => $this->l->t('pending'),
        ];
        foreach ($migrations as $version => $description) {
          $status = isset($pending[$version])
            ? '<info>' . $statusText[self::STATUS_PENDING] . '</info>'
            : $statusText[self::STATUS_APPLIED];
          $rows[] = [
            $status,
            $version,
            $description,
          ];

        }
        $statusWidth = count($migrations) == count($pending)
          ? strlen($statusText[self::STATUS_PENDING])
          : (count($pending) != 0
             ? max(strlen($statusText[self::STATUS_APPLIED]), strlen($statusText[self::STATUS_PENDING]))
             : strlen($statusText[self::STATUS_APPLIED]));
        $versionWidth = strlen(MigrationsService::VERSION_FORMAT);
        $headers = [$this->l->t('Status'), $this->l->t('Version'), $this->l->t('Description')];
        $remainingWidth = (new Terminal)->getWidth()
          - (2+3+3+2) // borders
          - max(strlen($headers[0]), $statusWidth) // status column
          - max(strlen($headers[1]), $versionWidth); // version column

        (new Table($output))
          ->setColumnMaxWidth(2, $remainingWidth)
          ->setHeaders($headers)
          ->setRows($rows)
          ->render();

        break;
    }

    return 0;
  }
}
