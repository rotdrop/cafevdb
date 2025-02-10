<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine <himself@claus-justus-heine.de>
 * @copyright 2025 Claus-Justus Heine
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

namespace OCA\CAFEVDB\Service;

use Throwable;

use League\CommonMark\GithubFlavoredMarkdownConverter;
use Psr\Log\LoggerInterface;

use OCP\AppFramework\IAppContainer;
use OCP\IL10N;
use OCP\IURLGenerator;
use OCP\IUser;
use OCP\IUserSession;
use OCP\Mail\IEMailTemplate;
use OCP\Mail\IMailer;
use OCP\Mail\IMessage;

use OCA\CAFEVDB\Settings\Admin as AdminSettings;
use OCA\CAFEVDB\Exceptions;
use OCA\CAFEVDB\Service\EncryptionService;

/**
 * Collect problem reports and forward them to configured targets (e.g. submit
 * an issue to Gitlab or Github, or just send an email to a configured email
 * address).
 *
 * Why? Unfortunately one cannot assume the everybody has a configured
 * standard email client, so the previous idea of just presenting an email
 * link in the frontend does not work. Instead, present a button in the
 * frontend to the end-user which just automagically submits a problem report
 * to configurable locations.
 */
class ProblemReportService
{
  use \OCA\CAFEVDB\Toolkit\Traits\LoggerTrait;

  public const REPORT_METHOD_EMAIL = 'email';

  /** {@inheritdoc} */
  public function __construct(
    protected $appName,
    protected IAppContainer $appContainer,
    protected IL10N $l,
    protected IMailer $mailer,
    protected LoggerInterface $logger,
    protected EncryptionService $encryptionService,
    protected IURLGenerator $urlGenerator,
    protected IUserSession $userSession,
  ) {
  }

  /**
   * Submit a problem report.
   *
   * @param array $userData The user reporting the error [ 'uid' => UID, 'displayName' => DISPLAY_NAME ].
   *
   * @param array $errorData The raw error data caught by the frontend
   * code. Ideally, this is data in the format of Nextcloud log entries, but this is not guaranteed.
   *
   * @param null|string $userComment Optional comment submitted alongside the user. May be markdown.
   *
   * @return ?array Notification messages which the frontend should present
   * to the user in order to inform the person of where the problem record has
   * been submitted.
   */
  public function submit(
    array $userData,
    array $errorData,
    ?string $userComment,
  ):?array {

    $this->logInfo('Requested problem report: USER "' .  print_r($userData, true) . '" DATA "' . (int)!empty($errorData) . '" COMMENT "' . $userComment . '".');

    // slightly more complicated than neccessary in case we want to add
    // further communication channels like Github or Gilab issues in the
    // future.
    $notifications = [];
    $exceptions = [];
    try {
      $notifications[self::REPORT_METHOD_EMAIL] = $this->submitViaEmail($userData, $errorData, $userComment);
    } catch (Throwable $e) {
      $exceptions[self::REPORT_METHOD_EMAIL] = $e;
    }

    if (!empty($exceptions)) {
      if (count($exceptions) == 1) {
        throw reset($exceptions);
      }
      throw new Exceptions\EnduserNotificationException(
        $this->l->t('All possibilities (i.e. %s) to submit your problem report have failed. Please use other communication channels to submit it.', implode(', ', array_keys($exceptions))),
      );
    }

    return array_values($notifications);
  }

  /**
   * Send an email verification message to the given sender notifying the
   * recipient about the token.
   *
   * @param string $address
   *
   * @param string $token
   *
   * @return void
   */
  public function sendEmailVerificationChallenge(string $address, string $token)
  {
    $this->logInfo('Try send code "' . $token . '" to "' . $address . '".');

    $orchestraName = $this->encryptionService->getConfigValue(ConfigService::ORCHESTRA_NAME_KEY, $this->l->t('Unknown Orchestra'));
    $baseUrl = $this->urlGenerator->getBaseUrl();
    $settingsUrl = $this->urlGenerator->linkToRouteAbsolute('settings.AdminSettings.index', [ 'section' => $this->appName ]);

    /** @var IEMailTemplate $emailTemplate */
    $emailTemplate = $this->mailer->createEMailTemplate('settings.TestEmail'); // parameter is ignored?
    $emailTemplate->setSubject($this->l->t('%1$s: Problem Report Email Verification Request', $this->appName));
    $emailTemplate->addHeader();
    $emailTemplate->addHeading($this->l->t('Explanations'));
    $explanations = $this->l->t(
      'Someone -- perhaps you -- tried to register this email address for receiving enduser problem reports '
      . 'from the "%1$s"-app of the Nextcloud-instance at "%2$s". '
      . 'In order to finish the registration please head over to "%3$s" and enter the code below into the appropriate field in the "Problem Reports" section.',
      [
        $this->appName,
        '<a href="' . $baseUrl . '">' . $baseUrl . '</a>',
        '<a href="' . $settingsUrl . '">' . $settingsUrl . '</a>',
      ]
    );
    $emailTemplate->addBodyText($explanations, $explanations);
    $emailTemplate->addHeading($this->l->t('Verificaton Code'));
    $challengeText = '<div style="text-align:center;"><span style="font-weight:bold;">' . $token . '</span></div>';
    $emailTemplate->addBodyText($challengeText, $token);
    $emailTemplate->addFooter();
    /** @var IMessage $message */
    $message = $this->mailer->createMessage();
    $message->setTo([ $address => $this->l->t('%s Problem Report Receiver', $orchestraName) ]);
    $message->useTemplate($emailTemplate);
    try {
      $this->mailer->send($message);
    } catch (Throwable $t) {
      $this->logException($t, 'Unable to send email address challenge for problem reports: ' . $token);
      throw new Exceptions\EnduserNotificationException(
        $this->l->t('Unable to send email address challenge for problem reports.'),
        0,
        $t,
      );
    }
  }

  /**
   * Submit a problem report via email.
   *
   * @param array $userData The user reporting the error [ 'uid' => UID, 'displayName' => DISPLAY_NAME ].
   *
   * @param array $errorData The raw error data caught by the frontend
   * code. Ideally, this is data in the format of Nextcloud log entries, but this is not guaranteed.
   *
   * @param null|string $userComment Optional comment submitted alongside the user. May be markdown.
   *
   * @return ?string A notification message which the frontend should present
   * to the user in order to inform the person of where the problem record has
   * been submitted.
   */
  public function submitViaEmail(
    array $userData,
    array $errorData,
    ?string $userComment,
  ):?string {

    $recipient = $this->encryptionService->getAppValue(AdminSettings::PROBLEM_REPORT_EMAIL_RECIPIENT_KEY);
    if (!$recipient) {
      throw new Exceptions\EnduserNotificationException(
        $this->l->t('There is no receiving email address configured for problem reports.'),
      );
    }

    $userString = $userData['uid'];
    if (!empty($userData['displayName'])) {
      $userString .= ' AKA ' . $userData['displayName'];
    }

    /** @var IEMailTemplate $emailTemplate */
    $emailTemplate = $this->mailer->createEMailTemplate('settings.TestEmail'); // parameter is ignored?
    $emailTemplate->setSubject($this->l->t('[%1$s Error] Problem  Report by %2$s', [ strtoupper($this->appName), $userString ]));
    $emailTemplate->addHeader();

    if ($userComment) {
      $emailTemplate->addHeading($this->l->t('Personal Comments by %s', $userString));
      $converter = new GithubFlavoredMarkdownConverter([
        'html_input' => 'strip',
        'allow_unsafe_links' => false,
      ]);
      $userCommentHtml = $converter->convert($userComment);
      $this->logInfo('UserComment ' . $userCommentHtml . ' <-> ' . $userComment);
      $emailTemplate->addBodyText($userCommentHtml, $userComment);
    }

    $emailTemplate->addHeading($this->l->t('System Error Report'));
    if (!empty($errorData)) {
      $errorDataString = json_encode(
        $errorData,
        JSON_PRETTY_PRINT
        |JSON_UNESCAPED_SLASHES
        |JSON_PARTIAL_OUTPUT_ON_ERROR
      );
      $errorDataAttachment = $this->mailer->createAttachment(
        $errorDataString,
        $this->l->t('SystemErrorReport') . '.json',
        'application/json',
      );
    } else {
      $bodyText = $this->l->t('No stack-trace or other data was submitted together the problem report.');
      $emailTemplate->addBodyText($bodyText, $bodyText);
      $errorDataAttachment = null;
    }

    $emailTemplate->addFooter();

    try {
      // ... do not assume decryption works at this point ...
      $orchestraName = $this->encryptionService->getConfigValue(ConfigService::ORCHESTRA_NAME_KEY, $this->l->t('Unknown Orchestra'));
      $recipientName = $this->l->t('%s Problem Report Recipient', $orchestraName);
    } catch (Throwable $t) {
      $this->logError('Unable to retrieve the orchestra name from the configuration space.');
      $recipientName = $this->l->t('%s Problem Report Recipient', $this->appNamex);
    }

    /** @var IMessage $message */
    $message = $this->mailer->createMessage();
    $message->setTo([ $recipient => $recipientName ]);
    $message->useTemplate($emailTemplate);

    if ($errorDataAttachment) {
      // $message->attachInline($errorDataString, $this->l->t('SystemErrorReport') . '.json', 'application/json');
      $message->attach($errorDataAttachment);
    }

    $recipientString = $recipientName . ' &lt;' . $recipient . '&gt;';
    $notifications = [
      $this->l->t('You problem report has been submitted by email to "%s".', $recipientString),
    ];

    /** @var IUser $user */
    $user = $this->userSession->getUser();

    if ($user->getUID() === $userData['uid']) {
      $email = $user->getEMailAddress();
      if (!empty($email)) {
        $message->setCc([ $email => $user->getDisplayName() ]);
        $notifications[] = $this->l->t(
          'A copy of the problem report has been sent to your configured email address "%1$s &lt;%2$s&gt;".',
          [ $user->getDisplayName(), $email ],
        );
      } else {
        $notifications[] = $this->l->t('Could not send a copy to you as your email address is not configured.');
      }
    } else {
      $notifications[] = $this->l->t('Strangly the submitted user-id differs from what the server thinks is the currently logged in user. Therefore no copy of the message has been sent to you.');
    }

    try {
      $this->mailer->send($message);
    } catch (Throwable $t) {
      throw new Exceptions\EnduserNotificationException(
        $this->l->t('Unable to send problem report by email to "%s".', $recipientString),
        0,
        $t,
      );
    }

    return implode(' ', $notifications);
  }
}
