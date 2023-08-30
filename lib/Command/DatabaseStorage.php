<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine <himself@claus-justus-heine.de>
 * @copyright 2022, 2023 Claus-Justus Heine
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

use \RuntimeException;

use OCP\IL10N;
use Psr\Log\LoggerInterface as ILogger;
use OCP\IUserSession;
use OCP\IUserManager;
use OCP\AppFramework\IAppContainer;
use OCP\IDateTimeFormatter;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Question\Question;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Helper\DescriptorHelper;

use OCA\CAFEVDB\Database\Doctrine\ORM\Entities;

/** Maintenance for the database file-system backend. */
class DatabaseStorage extends Command
{
  use AuthenticatedCommandTrait;

  /** {@inheritdoc} */
  public function __construct(
    protected string $appName,
    protected IL10N $l,
    protected ILogger $logger,
    protected IUserManager $userManager,
    protected IUserSession $userSession,
    protected IAppContainer $appContainer,
  ) {
    parent::__construct();
  }

  /** {@inheritdoc} */
  protected function configure()
  {
    $this
      ->setName('cafevdb:storage:database')
      ->setDescription('Maintenance for DB File-System Backend')
      ->addOption(
        'list-storages',
        'l',
        InputOption::VALUE_OPTIONAL,
        'List all storages. The optional value is a regular expression matching the storage id.',
        false,
      )
      ->addOption(
        'find-orphans',
        'f',
        InputOption::VALUE_NONE,
        'Find orphaned file-nodes, i.e. those not connected to a directory entry.',
      )
      ->addOption(
        'remove-orphans',
        'r',
        InputOption::VALUE_NONE,
        'Remove orphaned file-node, i.e. those not connected to a directory entry.'
      )
      ;
  }

  /** {@inheritdoc} */
  protected function execute(InputInterface $input, OutputInterface $output):int
  {
    $result = $this->authenticate($input, $output);
    if ($result != 0) {
      return $result;
    }
    $storageWildcard = $input->getOption('list-storages');
    if ($storageWildcard !== false) {
      if ($storageWildcard !== null) {
        $storages = $this->getDatabaseRepository(Entities\DatabaseStorage::class)->findBy([
          "storageId#REGEXP(%s, '" . $storageWildcard . "')" => 1,
        ]);
      } else {
        $storages = $this->getDatabaseRepository(Entities\DatabaseStorage::class)->findAll();
      }
      $output->writeln('Registered Storages: ' . count($storages));
      $output->writeln('');
      $storages = array_map(fn(Entities\DatabaseStorage $storage) => [ 'storageId' => $storage->getStorageId(), 'root' => $storage->getRoot() ], $storages);
      $storages = array_column($storages, 'root', 'storageId');
      ksort($storages);
      foreach (array_keys($storages) as $storageId) {
        $output->writeln($storageId);
      }
      $output->writeln('');
    } elseif ($input->getOption('find-orphans')) {
      $files = $this->getDatabaseRepository(Entities\EncryptedFile::class)->findAll();

      $orphans = [];

      /** @var Entities\EncryptedFile $file */
      foreach ($files as $file) {
        if ($file->getDatabaseStorageDirEntries()->count() == 0) {
          $orphans[] = $file;
        }
      }

      if (count($orphans) == 0) {
        $output->writeln('No orphans found.');
      } else {
        $output->writeln(sprintf('Found %s orphans.', count($orphans)));
        if ($output->getVerbosity() >= OutputInterface::VERBOSITY_VERBOSE) {
          /** @var IDateTimeFormatter $formatter */
          $formatter = $this->appContainer->get(IDateTimeFormatter::class);
          foreach ($orphans as $file) {
            $output->writeln(
              implode(', ', [
                $file->getId(),
                $file->getFileName(),
                'size ' . $file->getSize() . ' bytes',
                $file->getMimeType(),
                'last modified ' . $formatter->formatDate($file->getUpdated()),
                'created at ' . $formatter->formatDate($file->getCreated()),
              ])
            );
          }
        }
      }
    } elseif ($input->getOption('remove-orphans')) {
      $files = $this->getDatabaseRepository(Entities\EncryptedFile::class)->findAll();

      $orphans = [];

      /** @var Entities\EncryptedFile $file */
      foreach ($files as $file) {
        if ($file->getDatabaseStorageDirEntries()->count() == 0) {
          $orphans[] = $file;
        }
      }

      if (count($orphans) == 0) {
        $output->writeln('No orphans found.');
        return 0;
      }

      foreach ($orphans as $file) {
        $fileId = $file->getId();
        $this->remove($file, flush: true);
        if ($output->getVerbosity() >= OutputInterface::VERBOSITY_VERBOSE) {
          $output->writeln(sprintf('Removed file "%s" with id "%d".', $file->getFilename(), $fileId));
        }
      }
      $output->writeln('Removed ' . count($orphans) . ' orphans.');
    }
    return 0;
  }
}
