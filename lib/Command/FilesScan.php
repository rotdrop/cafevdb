<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine <himself@claus-justus-heine.de>
 * @copyright 2022, 2023, 2024 Claus-Justus Heine
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
use OCP\IUserSession;
use OCP\IUserManager;
use OCP\AppFramework\IAppContainer;
use OCP\Files\IRootFolder;
use OC\FilesMetadata\FilesMetadataManager;
use OCP\EventDispatcher\IEventDispatcher;
use Psr\Log\LoggerInterface;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Question\Question;

/** Authenticated files-scan which is thus also able to scan the database-backed mounts */
class FilesScan extends \OCA\Files\Command\Scan
{
  use AuthenticatedCommandTrait;

  /**
   * @param string $appName Needed by AuthenticatedCommandTrait.
   * @param IL10N $l Needed by AuthenticatedCommandTrait.
   * @param IUserManager $userManager Trait and parent.
   * @param IUserSession $userSession Trait.
   * @param IAppContainer $appContainer Trait.
   * @param IRootFolder $rootFolder Parent.
   * @param FilesMetadataManager $filesMetadataManager Parent.
   * @param IEventDispatcher $eventDispatcher Parent.
   * @parma LoggerInterface $logger Parent.
   */
  public function __construct(
    protected string $appName,
    protected IL10N $l,
    protected IUserManager $userManager,
    protected IUserSession $userSession,
    protected IAppContainer $appContainer,
    IRootFolder $rootFolder,
    FilesMetadataManager $filesMetadataManager,
    IEventDispatcher $eventDispatcher,
    LoggerInterface $logger,
  ) {
    parent::__construct(
      userManager: $userManager,
      rootFolder: $rootFolder,
      filesMetadataManager: $filesMetadataManager,
      eventDispatcher: $eventDispatcher,
      logger: $logger,
    );
  }

  /** {@inheritdoc} */
  protected function configure():void
  {
    parent::configure();
    $this->setName('cafevdb:files-scan');
  }

  /** {@inheritdoc} */
  protected function execute(InputInterface $input, OutputInterface $output):int
  {
    $result = $this->authenticate($input, $output);
    if ($result != 0) {
      return $result;
    }

    return parent::execute($input, $output);
  }
}
