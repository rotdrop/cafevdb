<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine <himself@claus-justus-heine.de>
 * @copyright 2022-2024, 2026 Claus-Justus Heine
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

use OCP\Console\Attribute\Argument;
use OCP\Console\Attribute\AsCommand;
use OCP\Console\Attribute\Option;
use OCP\Console\ExitCode;
use OCP\Console\IInput;
use OCP\Console\IOutput;
use OCP\EventDispatcher\IEventDispatcher;
use OCP\Files\IRootFolder;
use OCP\IL10N;
use OCP\IUserManager;
use OCP\IUserSession;
use OC\FilesMetadata\FilesMetadataManager;
use OC\Files\SetupManager;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;

/** Authenticated files-scan which is thus also able to scan the database-backed mounts */
#[AsCommand(
  name: 'cafevdb:files-scan',
  description: 'rescan filesystem',
  supportsOutputFormat: true,
)]
class FilesScan
{
  use AuthenticatedCommandTrait;

  /** {@inheritdoc} */
  public function __construct(
    protected ContainerInterface $appContainer,
    protected IL10N $l,
    protected IUserManager $userManager,
    protected IUserSession $userSession,
    protected string $appName,
    protected \OCA\Files\Command\Scan $filesScan,
  ) {
  }

  /** {@inheritdoc} */
  public function __invoke(
    IInput $input,
    IOutput $output,
    ISignalHandler $signalHandler,
    #[Argument(name: 'user_id', description: 'will rescan all files of the given user(s)')]
    array $userIds = [],
    #[Option(description: 'limit rescan to this path, eg. --path="/alice/files/Music", the user_id is determined by the path and the user_id parameter and --all are ignored', shortcut: 'p')]
    ?string $path = null,
    #[Option(name: 'generate-metadata', description: 'Generate metadata for all scanned files; if specified only generate for named value')]
    string|bool $generateMetadata = false,
    #[Option(description: 'will rescan all files of all known users')]
    bool $all = false,
    #[Option(description: 'only scan files which are marked as not fully scanned')]
    bool $unscanned = false,
    #[Option(description: 'do not scan folders recursively')]
    bool $shallow = false,
    #[Option(name: 'home-only', description: 'only scan the home storage, ignoring any mounted external storage or share')]
    bool $homeOnly = false,
  ): ExitCode {
    $result = $this->authenticate($input, $output);
    if ($result != ExitCode::Success) {
      return $result;
    }

    return $this->filesScan->__invoke(
      output: $output,
      signalHandler: $signalHandler,
      userIds: $userIds,
      path: $path,
      generateMetadata: $generateMetadata,
      all: $all,
      unscanned: $unscanned,
      shallow: $shallow,
      homeOnly: $homeOnly,
    );
  }
}
