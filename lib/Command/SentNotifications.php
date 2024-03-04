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
use Psr\Log\LoggerInterface as ILogger;
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
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Question\Question;

use OCA\Mail\Model\IMAPMessage;

use OCA\CAFEVDB\Database\Doctrine\ORM\Entities;
use OCA\CAFEVDB\Service\IMAPService;

/** Database (and non-database) migration management. */
class SentNotifications extends Command
{
  use AuthenticatedCommandTrait;

  private const ACTION_LIST_MISSING = 'list-missing';
  private const ACTION_ADD_MISSING = 'add-missing';
  private const OPTION_DRY_RUN = 'dry';
  private const OPTION_IMAP_URI = 'imap-uri';
  private const OPTION_MESSAGE_ID = 'message-id';

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
    protected ILogger $logger,
    protected IUserManager $userManager,
    protected IUserSession $userSession,
    protected IAppContainer $appContainer,
    protected IMAPService $imapService,
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
        ),
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
          ]),
      )
      ->addOption(
        self::OPTION_IMAP_URI,
        null,
        InputOption::VALUE_REQUIRED,
        $this->l->t(
          'Override the configured imap-host. Simple URI-like hosts are support, e.g. "tls:://USER:PASSWORD@example.com:143".'
          . ' If a user is given but no password the command will prompt for the password.',
        ),
      )
      ->addOption(
        self::OPTION_MESSAGE_ID,
        null,
        InputOption::VALUE_REQUIRED|InputOption::VALUE_IS_ARRAY,
        $this->l->t(
          'Restrict the operation to the given message ids. The option can be given more than once in order to support multiple message ids.'
        ),
      )
      ;
  }

  /** {@inheritdoc} */
  protected function execute(InputInterface $input, OutputInterface $output): int
  {
    $this->dry = $input->getOption(self::OPTION_DRY_RUN);

    $imapUri = $input->getOption(self::OPTION_IMAP_URI);
    if ($imapUri) {
      $imapOptions = parse_url($imapUri);
    }

    $result = $this->authenticate($input, $output);
    if ($result != 0) {
      return $result;
    }

    if (!empty($imapOptions)) {
      if (isset($imapOptions['user']) && !isset($imapOptions['pass'])) {
        $helper = $this->getHelper('question');
        $question = (new Question($this->l->t('IMAP Password') . ': ', ''))->setHidden(true);
        $imapOptions['pass'] = $helper->ask($input, $output, $question);
      }
    }

    $this->imapService->setAccount(
      host: $imapOptions['host'] ?? null,
      port: $imapOptions['port'] ?? null,
      security: $imapOptions['scheme'] ?? null,
      user: $imapOptions['user'] ?? null,
      password: $imapOptions['pass'] ?? null,
    );

    if ($input->getOption(self::ACTION_LIST_MISSING)) {
      $sentEmailsRepository = $this->getDatabaseRepository(Entities\SentEmail::class);
      $payments = $this->getDatabaseRepository(Entities\CompositePayment::class)->findBy(
        [
          [ '!notificationMessageId' => null, ],
          [ '!notificationMessageId' => '', ],
        ],
        orderBy: [
          'dateOfReceipt' => 'ASC',
        ],
      );
      $rows = [];
      /** @var Entities\CompositePayment $payment */
      foreach ($payments as $payment) {
        $notificationMessageId = $payment->getNotificationMessageId();
        /** @var Entities\SentEmail $sentEmail */
        $sentEmail = $sentEmailsRepository->find([ 'messageId' => $notificationMessageId, ]);
        if (!empty($sentEmail)) {
          continue;
        }
        $rows[$notificationMessageId] = [
          $notificationMessageId,
          $payment->getDateOfReceipt()->format('Y-m-d'),
          '',
        ];
      }
      $messageIdOptions = $input->getOption(self::OPTION_MESSAGE_ID);
      if (!empty($messageIdOptions)) {
        $rows = array_filter($rows, fn(array $row) => in_array($row[0], $messageIdOptions));
      }

      $messageIds = array_map(fn(array $row) => $row[0], $rows);
      $imapMessages = $this->imapService->searchMessageId($messageIds);
      $output->writeln(
        '<info>'
          . $this->l->t(
            'Found %1$d of %2$d messages on the server.',
            [
              count($imapMessages),
              count($messageIds),
            ]
          )
          . '</info>');

      /** @var IMAPMessage $imapMessage */
      foreach ($imapMessages as $imapMessage) {
        $messageId = $imapMessage->getMessageId();
        $subject = $imapMessage->getSubject();
        if (mb_strlen($subject) > 20) {
          $subject = mb_substr($subject, 0, 8) . '...' . mb_substr($subject, -9);
        }
        $rows[$messageId][2] = $subject;
        $rows[$messageId][3] = $this->l->t('found');
      }

      $headers = [
        $this->l->t('Missing in DB'),
        $this->l->t('Date of Receipt'),
        $this->l->t('Subject'),
        $this->l->t('IMAP Server'),
      ];
      (new Table($output))
        ->setHeaders($headers)
        ->setRows($rows)
        ->render();
    }

    return 0;
  }
}
