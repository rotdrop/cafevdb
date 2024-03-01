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

use Symfony\Component\Console\Terminal;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\DescriptorHelper;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\Question;

use OCA\CAFEVDB\Database\Doctrine\ORM\Entities;

/** Database (and non-database) migration management. */
class SentNotifications extends Command
{
  use AuthenticatedCommandTrait;

  private const ACTION_LIST_MISSING = 'list-missing';
  private const ACTION_ADD_MISSING = 'add-missing';
  private const OPTION_DRY_RUN = 'dry';

  /**
   * @var bool
   *
   * Run in simulation mode.
   */
  private bool $dry;

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
      ->setName('cafevdb:emails')
      ->setDescription($this->l->t('Consistency checks for sent emails for bank transactions and donation receipts.'))
      ->addOption(
        self::ACTION_LIST_MISSING,
        'l',
        InputOption::VALUE_NONE,
        $this->l->t(
          'List message-ids referenced in the payment and donation entities'
          . ' which are not recorded in the "SentEmails" table'
          . ' and ask the IMAP server whether it has a message with this id.'),
      )
      ->addOption(
        self::ACTION_ADD_MISSING,
        'a',
        InputOption::VALUE_NONE,
        $this->l->t(
          'Add any missing emails which are referenced in the payments and donation tables'
          . ' but are missing in the SentEmails table back to the SentEmails table if a'
          . ' corresponding message can be found on the IMAP server.'
        )
      )
      ->addOption(
        self::OPTION_DRY_RUN,
        'd',
        InputOption::VALUE_NONE,
        $this->l->t(
          'If "%s / %s" has also been specified, then try to recreate the missing SentEmail entities but do not'
          . ' flush them to the database.',
          [
            '--' . self::ACTION_ADD_MISSING, '-a',
          ])
      )
      ;
  }

  /** {@inheritdoc} */
  protected function execute(InputInterface $input, OutputInterface $output): int
  {
    $this->dry = $input->getOption(self::OPTION_DRY_RUN);

    $result = $this->authenticate($input, $output);
    if ($result != 0) {
      return $result;
    }

    if ($input->getOption(self::ACTION_LIST_MISSING)) {
      $sentEmailsRepository = $this->getDatabaseRepository(Entities\SentEmail::class);
      $payments = $this->getDatabaseRepository(Entities\CompositePayment::class)->findBy([
        [ '!notificationMessageId' => null, ],
        [ '!notificationMessageId' => '', ],
      ]);
      $rows = [];
      /** @var Entities\CompositePayment $payment */
      foreach ($payments as $payment) {
        $notificationMessageId = $payment->getNotificationMessageId();
        /** @var Entities\SentEmail $sentEmail */
        $sentEmail = $sentEmailsRepository->find([ 'messageId' => $notificationMessageId, ]);
        if (!empty($sentEmail)) {
          continue;
        }
        $rows[] = [
          $notificationMessageId,
          '',
        ];
      }
      $headers = [$this->l->t('Missing in DB'), $this->l->t('IMAP Server')];
      (new Table($output))
        ->setHeaders($headers)
        ->setRows($rows)
        ->render();
    }

    return 0;
  }
}
