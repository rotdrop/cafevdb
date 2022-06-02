<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine
 * @copyright 2022 Claus-Justus Heine <himself@claus-justus-heine.de>
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

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Question\Question;

class FilesScan extends \OCA\Files\Command\Scan
{
  /** @var string */
  private $appName;

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
    parent::__construct($userManager);
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
    parent::configure();
    $this->setName('cafevdb:files-scan');
  }

  protected function execute(InputInterface $input, OutputInterface $output): int
  {
    $helper = $this->getHelper('question');
    $question = new Question('User: ', '');
    $userId = $helper->ask($input, $output, $question);
    $question = (new Question('Password: ', ''))->setHidden(true);
    $password = $helper->ask($input, $output, $question);

    // $output->writeln($this->l->t('Your Answers: "%s:%s"', [ $userId, $password ]));
    $user = $this->userManager->get($userId);
    $this->userSession->setUser($user);

    // Login event-handler binds encryption-service and entity-manager
    if ($this->userSession->login($userId, $password)) {
      $output->writeln($this->l->t('Login succeeded.'));
    } else {
      $output->writeln($this->l->t('Login failed.'));
    }

    return parent::execute($input, $output);
  }
}
