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

use OCP\AppFramework\IAppContainer;
use OCP\EventDispatcher\IEventDispatcher;
use OCP\Files\IRootFolder;
use OCP\IL10N;
use OCP\IUserManager;
use OCP\IUserSession;
use OCP\L10N\IFactory as IL10NFactory;
use OC\Files\FilenameValidator;
use Psr\Log\LoggerInterface;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\Question;

/** Authenticated sanitize-filenames which is thus also able to scan the database-backed mounts */
class SanitizeFilenames extends \OCA\Files\Command\SanitizeFilenames
{
  use AuthenticatedCommandTrait;

  // phpcs:disable Squiz.Commenting.FunctionComment.Missing
  public function __construct(
    protected string $appName,
    protected IL10N $l,
    protected IUserManager $userManager,
    protected IUserSession $userSession,
    protected IAppContainer $appContainer,
    //
    // private IUserManager $userManager,
    IRootFolder $rootFolder,
    // IUserSession $session,
    IL10NFactory $l10nFactory,
    FilenameValidator $filenameValidator,
  ) {
    parent::__construct(
      userManager: $this->userManager,
      rootFolder: $rootFolder,
      session: $this->userSession,
      l10nFactory: $l10nFactory,
      filenameValidator: $filenameValidator,
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

    return parent::execute($input, $output);
  }
}
