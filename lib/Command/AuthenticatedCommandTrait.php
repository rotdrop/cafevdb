<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine <himself@claus-justus-heine.de>
 * @copyright 2022 Claus-Justus Heine
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
use OCP\IUserSession;
use OCP\IUserManager;
use OCP\AppFramework\IAppContainer;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Question\Question;
use Symfony\Component\Console\Helper\ProgressBar;

use OCA\CAFEVDB\Service\EncryptionService;
use OCA\CAFEVDB\Database\EntityManager;
use OCA\CAFEVDB\Database\Doctrine\ORM\Entities;

/** Trait in order to handle authentication with the cloud */
trait AuthenticatedCommandTrait
{
  use \OCA\CAFEVDB\Traits\LoggerTrait;
  use \OCA\CAFEVDB\Traits\EntityManagerTrait;

  /** @var IL10N */
  protected $l;

  /** @var IUserManager */
  protected $userManager;

  /** @var IUserSession */
  protected $userSession;

  /** @var IAppContainer */
  protected $appContainer;

  /** @var string */
  protected string $userId;

  /** @var string */
  protected string $userPassword;

  /**
   * {@inheritdoc}
   *
   * @see execute()
   */
  protected function authenticate(InputInterface $input, OutputInterface $output):int
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
      return 1;
    }

    $this->userId = $userId;
    $this->userPassword = $password;

    $this->entityManager = $this->appContainer->get(EntityManager::class);

    return 0;
  }
}
