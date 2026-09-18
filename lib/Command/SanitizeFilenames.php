<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine <himself@claus-justus-heine.de>
 * @copyright 2022, 2023, 2024-2026 Claus-Justus Heine
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

use OCP\AppFramework\Services\IAppConfig;
use OCP\Console\Attribute\Argument;
use OCP\Console\Attribute\AsCommand;
use OCP\Console\Attribute\Option;
use OCP\Console\ExitCode;
use OCP\Console\IInput;
use OCP\Console\IOutput;
use OCP\Files\IRootFolder;
use OCP\IL10N;
use OCP\IUserManager;
use OCP\IUserSession;
use OCP\L10N\IFactory as IL10NFactory;
use OC\Files\FilenameValidator;
use Psr\Container\ContainerInterface;

use OCA\Files\Service\SettingsService;
use OCA\Files\Command\SanitizeFilenames as FilesSanitizeFilenames;

use OCA\CAFEVDB\Database\Doctrine\ORM\Entities;
use OCA\CAFEVDB\Exceptions;

/** Authenticated sanitize-filenames which is thus also able to scan the database-backed mounts */
#[AsCommand(
  name: 'cafevdb:sanitize-filenames',
  description: 'Renames files to match naming constraints',
)]
class SanitizeFilenames
{
  use AuthenticatedCommandTrait;
  use \OCA\CAFEVDB\Toolkit\Traits\SanitizeFilenameTrait;

  // phpcs:disable Squiz.Commenting.FunctionComment.Missing
  public function __construct(
    protected string $appName,
    protected ContainerInterface $appContainer,
    protected IL10N $l,
    protected IUserManager $userManager,
    protected IUserSession $userSession,
    protected FilesSanitizeFilenames $filesCommand,
  ) {
  }
  // phpcs:enable

  /** {@inheritdoc} */
  public function __invoke(
    IInput $input,
    IOutput $output,
    #[Argument(
      name: 'user_id',
      description: 'will only rename files the given user(s) have access to',
    )]
    array $userIds = [],
    #[Option(
      name: 'dry-run',
      description: 'Do not actually rename any files but just check filenames.',
    )]
    bool $dryRun = false,
    #[Option(
      name: 'char-replacement',
      description: 'Replacement for invalid character (by default space, underscore or dash is used)',
      shortcut: 'c',
    )]
    ?string $charReplacement = null,
  ): ExitCode {

    $result = $this->authenticate($input, $output);
    if ($result != ExitCode::Success) {
      return $result;
    }

    $this->entityManager->beginTransaction();
    try {
      // Handle the database storage separately as not all parts of it are
      // writable through the file-system.
      $dirEntries = $this->findAll(Entities\DatabaseStorageDirEntry::class);
      /** @var Entities\DatabaseStorageDirEntry $dirEntry */
      foreach ($dirEntries as $dirEntry) {
        $oldName = $dirEntry->getName();
        $newName = $this->sanitizeFilename($oldName, $dirEntry->getMimeType());
        if ($oldName !== $newName) {
          $oldPath = $dirEntry->getPathName();
          $newPath = substr($oldPath, 0, -strlen($oldName)) . $newName;
          $storage = '[' . $dirEntry->getStorage()->getStorageId() . ']';
          if ($dryRun) {
            $output->writeln(
              '<info>'
              . $this->l->t('Would rename "%1$s" to "%2$s" (dry-run).', [
                $storage . $oldPath, $storage . $newPath
              ])
              . '</>',
            );
          } else {
            $output->writeln(
              '<info>'
              . $this->l->t('Renaming "%1$s" to "%2$s".', [
                $storage . $oldPath, $storage . $newPath
              ])
              . '</>',
            );
            $dirEntry->setName($newName);
          }
        }
      }
      $this->flush();
      $this->entityManager->commit();
    } catch (Throwable $t) {
      if ($this->entityManager->isTransactionActive()) {
        $this->entityManager->rollback();
      }
      throw new Exceptions\EnduserNotificationException(
        $this->l->t('Unable to sanitize filenames, caught an exception.'),
        previous: $t,
      );
    }

    return $this->filesCommand->__invoke($output, $dryRun, $charReplacement);
  }
}
