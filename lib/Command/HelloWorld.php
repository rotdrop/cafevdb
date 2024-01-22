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

use OCP\IL10N;
use OCP\IUserSession;
use OCP\IUserManager;
use OCP\AppFramework\IAppContainer;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\DescriptorHelper;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\Question;

use OCA\CAFEVDB\Service\EncryptionService;

/** Test-command in order to see if the abstract framework is functional. */
class HelloWorld extends Command
{
  use AuthenticatedCommandTrait;

  /** {@inheritdoc} */
  public function __construct(
    protected string $appName,
    protected IL10N $l,
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
      ->setName('cafevdb:hello-world')
      ->setDescription($this->l->t('Say "Hello!" to the world!'))
      ->addOption(
        'only-hello',
        'o',
        InputOption::VALUE_NONE,
        $this->l->t('Outputs only hello, not world.'),
      )
      ->addOption(
        'authenticated',
        'a',
        InputOption::VALUE_NONE,
        $this->l->t('Try to authenticate with the cloud.'),
      )
      ->addOption(
        'failure',
        'f',
        InputOption::VALUE_NONE,
        $this->l->t('Specifying this option with result in failure.'),
      )
      ;
  }

  /** {@inheritdoc} */
  protected function execute(InputInterface $input, OutputInterface $output): int
  {
    $optionCheck = true;

    if ($input->getOption('failure')) {
      $output->writeln('<error>' . $this->l->t('Generating an artificial failure as requested.') . '</error>');
      $optionCheck = false;
    }

    if (!$optionCheck) {
      $output->writeln('');
      $output->writeln('<error>' . $this->l->t('Command failed, please have a look at the error messages above.') . '</error>');
      $output->writeln('');
      (new DescriptorHelper)->describe($output, $this);
      return 1;
    }

    if ($input->getOption('authenticated')) {
      $result = $this->authenticate($input, $output);
      if ($result != 0) {
        return $result;
      }
    }
    if ($input->getOption('only-hello')) {
      $output->writeln($this->l->t('Hello!'));
    } else {
      $output->writeln($this->l->t('Hello World!'));
    }
    return 0;
  }
}
