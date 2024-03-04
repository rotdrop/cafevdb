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

use Throwable;

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

use OCA\CAFEVDB\Service\IMAP\IMAPMessage;
use OCA\Mail\Address;

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
   * @var array<string, IMAPMessage>
   *
   * A cache of the IMAP-messages retrieved in this run.
   */
  private array $imapMessages = [];

  /**
   * @var array<sting, Entities\SentEmail>
   *
   * The SentEmail entities retrieved so far.
   */
  private array $sentEmails = [];

  /**
   * @var array<string, Entities\CompositePayment
   *
   * Payments with broken SentEmail links.
   */
  private array $payments = [];

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

    if ($input->getOption(self::ACTION_LIST_MISSING) || $input->getOption(self::ACTION_ADD_MISSING)) {
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
          $this->sentEmails[$notificationMessageId] = $sentEmail; // remember for later
          continue;
        }
        $this->payments[$notificationMessageId] = $payment; // remember in order to reconstruct the SentEmail link.
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

      if (count($rows) == 0) {
        $output->writeln('<info>' . $this->l->t('All email notifications seem to be recorded in the database.') . '</info>');
        return 0;
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

      $messageIds = array_map(fn(array $row) => $row[0], $rows);
      $imapMessages = $this->imapService->searchMessageId($messageIds);
      /** @var IMAPMessage $imapMessage */
      foreach ($imapMessages as $key => $imapMessage) {
        unset($imapMessages[$key]);
        $imapMessages[$imapMessage->getMessageId()] = $imapMessage;
      }
      array_merge($this->imapMessages, $imapMessages);

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
    }

    if ($input->getOption(self::ACTION_LIST_MISSING)) {
      /** @var IMAPMessage $imapMessage */
      foreach ($imapMessages as $messageId => $imapMessage) {
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

    if ($input->getOption(self::ACTION_ADD_MISSING)) {
      foreach ($imapMessages as $messageId => $imapMessage) {
        $sentEmail = $this->reconstructSentEmailEntity($imapMessage);
        $this->entityManager->beginTransaction();
        try {
          /** @var Entities\CompositePayment $payment */
          $payment = $this->payments[$messageId];
          $payment->setPreNotificationEmail($sentEmail);
          $sentEmail->setCompositePayment($payment);

          $sepaTransaction  = $payment->getSepaTransaction();
          if (!empty($sepaTransaction)) {
            $sentEmail->setSepaBulkTransaction($sepaTransaction);
          }

          $this->persist($sentEmail);
          $this->flush();

          $this->entityManager->commit();
        } catch (Throwable $t) {
          $this->entityManager->rollback();
          $output->writeln(
            '<error>'
            . $this->l->t(
              'Reconstruction of "%1$s" failed: %2$s.',
              [
                $messageId,
                $t->getMessage(),
              ],
            )
            . '</error>');
          throw $t;
        }
      }
    }

    return 0;
  }

  /**
   * Try to reconstruct a missing SentEmail entity from the given
   * IMAP-message. Note that this may involve fetching further data-base
   * and/or IMAP messages in order to get the references right.
   *
   * @param IMAPMessage $imapMessage
   *
   * @return Entities\SentEmail
   */
  protected function reconstructSentEmailEntity(IMAPMessage $imapMessage):Entities\SentEmail
  {
    $bulkRecipients = [];
    /** @var Address $address */
    foreach ($imapMessage->getTo() as $address) {
      $bulkRecipients[] = $address->getLabel() . '<' . $address->getEmail() . '>';
    }
    foreach ($imapMessage->getBCC() as $address) {
      $bulkRecipients[] = $address->getLabel() . '<' . $address->getEmail() . '>';
    }
    $carbonCopy = [];
    foreach ($imapMessage->getCC() as $address) {
      $carbonCopy[] = $address->getLabel() . '<' . $address->getEmail() . '>';
    }

    /** @var Entities\SentEmail $sentEmail */
    $sentEmail = (new Entities\SentEmail);
    $sentEmail
      ->setSubject($imapMessage->getSubject())
      ->setBulkRecipients(implode(';', $bulkRecipients))
      ->setCc(implode(';', $carbonCopy))
      ->setMessageId($imapMessage->getMessageId())
      ->setHtmlBody($imapMessage->htmlMessage)
      ->setBulkRecipientsHash(hash('md5', $sentEmail->getBulkRecipients()))
      ->setSubjectHash(hash('md5', $sentEmail->getSubject()))
      ->setHtmlBodyHash(hash('md5', $sentEmail->getHtmlBody()))
      ;
    // $references = $imapMessage->getRawReferences();

    return $sentEmail;
  }
}
