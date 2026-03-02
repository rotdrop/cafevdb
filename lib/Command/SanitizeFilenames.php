<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine <himself@claus-justus-heine.de>
 * @copyright 2022, 2023, 2024, 2025 Claus-Justus Heine
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
use OCP\AppFramework\Services\IAppConfig;
use OCP\Files\IRootFolder;
use OCP\IL10N;
use OCP\IUserManager;
use OCP\IUserSession;
use OCP\L10N\IFactory as IL10NFactory;
use OC\Files\FilenameValidator;

use OCA\Files\Service\SettingsService;
use OCA\Files\Command\SanitizeFilenames as FilesSanitizeFilenames;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

use OCA\CAFEVDB\Database\Doctrine\ORM\Entities;
use OCA\CAFEVDB\Exceptions;

/** Authenticated sanitize-filenames which is thus also able to scan the database-backed mounts */
class SanitizeFilenames extends FilesSanitizeFilenames
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
    FilenameValidator $filenameValidator,
    IAppConfig $appConfig,
    IL10NFactory $l10nFactory,
    IRootFolder $rootFolder,
    SettingsService $settingsService,
  ) {
    parent::__construct(
      userManager: $this->userManager,
      rootFolder: $rootFolder,
      session: $this->userSession,
      l10nFactory: $l10nFactory,
      filenameValidator: $filenameValidator,
      service: $settingsService,
      appConfig: $appConfig,
    );
  }
  // phpcs:enable

  /** {@inheritdoc} */
  protected function configure():void
  {
    parent::configure();
    $this->setName($this->appName . ':sanitize-filenames');
  }

  /** {@inheritdoc} */
  protected function execute(InputInterface $input, OutputInterface $output):int
  {
    $result = $this->authenticate($input, $output);
    if ($result != 0) {
      return $result;
    }

    $dryRun = $input->getOption('dry-run');

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

    return parent::execute($input, $output);
  }
}
