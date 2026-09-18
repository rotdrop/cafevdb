<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine <himself@claus-justus-heine.de>
 * @copyright 2011-2026 Claus-Justus Heine
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

use Exception;

use OCP\Console\Attribute\Argument;
use OCP\Console\Attribute\AsCommand;
use OCP\Console\Attribute\Option;
use OCP\Console\ExitCode;
use OCP\Console\IInput;
use OCP\Console\IOutput;
use OCP\IL10N;
use OCP\IUserManager;
use OCP\IUserSession;
use Psr\Container\ContainerInterface;

/** Test-command in order to see if the abstract framework is functional. */
#[AsCommand(
  name: 'cafevdb:hello-world',
  description: 'Say "Hello!" to the world!',
)]
class HelloWorld
{
  use AuthenticatedCommandTrait;

  /** {@inheritdoc} */
  public function __construct(
    protected string $appName,
    protected IL10N $l,
    protected IUserManager $userManager,
    protected IUserSession $userSession,
    protected ContainerInterface $appContainer,
  ) {
  }

  /** {@inheritdoc} */
  public function __invoke(
    IInput $input,
    IOutput $output,
    #[Option(
      name: 'only-hello',
      shortcut: 'o',
      description: 'Outputs only hello, not world.',
    )]
    bool $onlyHello = false,
    #[Option(
      name: 'authenticated',
      shortcut: 'a',
      description: 'Try to authenticate with the cloud.',
    )]
    bool $authenticated = false,
    #[Option(
      name: 'failure',
      shortcut: 'f',
      description: 'Specifying this option will result in failure.',
    )]
    bool $failure = false,
    #[Option(
      name: 'exception',
      shortcut: 'e',
      description: 'Specifying this option will throw an exception.',
    )]
    bool $exception = false,
  ): ExitCode {

    $optionCheck = true;

    if ($failure) {
      $output->writeln('<error>' . $this->l->t('Generating an artificial failure as requested.') . '</error>');
      $optionCheck = false;
    }

    if ($exception) {
      $output->writeln('<error>' . $this->l->t('Generating an artificial exception as requested.') . '</error>');
      throw new Exception($this->l->t('This is an serious Exception!'));
    }

    if (!$optionCheck) {
      $output->writeln('');
      $output->writeln('<error>' . $this->l->t('Command failed, please have a look at the error messages above.') . '</error>');
      $output->writeln('');

      return ExitCode::Failure;
    }

    if ($authenticated) {
      $result = $this->authenticate($input, $output);
      if ($result != ExitCode::Success) {
        return $result;
      }
    }
    if ($onlyHello) {
      $output->writeln($this->l->t('Hello!'));
    } else {
      $output->writeln($this->l->t('Hello World!'));
    }
    return ExitCode::Success;
  }
}
