<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine
 * @copyright 2011-2014, 2016, 2020, 2021 Claus-Justus Heine <himself@claus-justus-heine.de>
 *
 * This library is free software; you can redistribute it and/or
 * modify it under the terms of the GNU GENERAL PUBLIC LICENSE
 * License as published by the Free Software Foundation; either
 * version 3 of the License, or any later version.
 *
 * This library is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU AFFERO GENERAL PUBLIC LICENSE for more details.
 *
 * You should have received a copy of the GNU Lesser General Public
 * License along with this library.  If not, see <http://www.gnu.org/licenses/>.
 */

namespace OCA\CAFEVDB\Command;

use OCP\IL10N;
use OCP\IUserSession;
use OCP\IUserManager;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Question\Question;

use OCA\CAFEVDB\Service\EncryptionService;

class HelloWorld extends Command
{
  /** @var IL10N */
  private $l;

  /** @var IUserManager */
  private $userManager;

  /** @var IUserSession */
  private $userSession;

  public function __construct(
    $appName
    , IL10N $l10n
    , IUserManager $userManager
    , IUserSession $userSession
  ) {
    parent::__construct();
    $this->l = $l10n;
    $this->userManager = $userManager;
    $this->userSession = $userSession;
    $this->appName = $appName;
    if (empty($l10n)) {
      throw new \RuntimeException('No IL10N :(');
    }
  }

  protected function configure()
  {
    $this
      ->setName('cafevdb:hello-world')
      ->setDescription('Say "Hello!" to the world!')
      ->addOption(
        'only-hello',
        'o',
        InputOption::VALUE_NONE,
        'outputs hello, not world',
      )
      ;
  }

  protected function execute(InputInterface $input, OutputInterface $output): int
  {
    if ($input->getOption('only-hello')) {
      $output->writeln($this->l->t('Hello!'));
    } else {
      $output->writeln($this->l->t('Hello World!'));
    }
    $helper = $this->getHelper('question');
    $question = new Question('User: ', '');
    $userId = $helper->ask($input, $output, $question);
    $question = (new Question('Password: ', ''))->setHidden(true);
    $password = $helper->ask($input, $output, $question);

    // $output->writeln($this->l->t('Your Answers: "%s:%s"', [ $userId, $password ]));
    $user = $this->userManager->get($userId);
    $this->userSession->setUser($user);

    if ($this->userSession->login($userId, $password)) {
      $output->writeln($this->l->t('Login succeeded.'));
    } else {
      $output->writeln($this->l->t('Login failed.'));
    }

    /** @var EncryptionService $encryptionService */
    $encryptionService = \OC::$server->query(EncryptionService::class);
    $encryptionService->bind($userId, $password);
    $encryptionService->initAppEncryptionKey();

    $output->writeln('DB SERVER: ' . $encryptionService->getConfigValue('dbserver'));

    return 0;
  }
}
