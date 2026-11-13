<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine <himself@claus-justus-heine.de>
 * @copyright 2025, 2026 Claus-Justus Heine
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

use Psr\Container\ContainerInterface;
use OCP\App\IAppManager;
use OCP\IL10N;
use OCP\IURLGenerator;
use OCP\IUser;
use OCP\IUserSession;
use OCP\Mail\IEMailTemplate;
use OCP\Mail\IMailer;
use OCP\Mail\IMessage;
use OCP\Security\RateLimiting\ILimiter;
use OCP\ServerVersion;

use OCA\CAFEVDB\Common\Html2Text;
use OCA\CAFEVDB\Exceptions;
use OCA\CAFEVDB\Settings\Admin as AdminSettings;
use OCA\CAFEVDB\Settings\ConfigConstants;

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
  use \OCA\CAFEVDB\Wrapped\OCA\RotDrop\Toolkit\Traits\LoggerTrait;

  public const REPORT_METHOD_EMAIL = 'email';
  public const REPORT_METHOD_GITHUB = 'github';

  /** At most this many GitHub reports per user and period. */
  public const GITHUB_RATE_LIMIT = 5;
  public const GITHUB_RATE_PERIOD = 3600;

  /** GitHub limits issue bodies to 65536 characters. */
  private const GITHUB_BODY_LIMIT = 60000;
  private const GITHUB_COMMENT_LIMIT = 4000;
  private const PSEUDONYM_LENGTH = 12;

  /** {@inheritdoc} */
  public function __construct(
    protected $appName,
    protected ContainerInterface $appContainer,
    protected IL10N $l,
    protected IMailer $mailer,
    protected LoggerInterface $logger,
    protected EncryptionService $encryptionService,
    protected IURLGenerator $urlGenerator,
    protected IUserSession $userSession,
    protected IAppManager $appManager,
    protected ServerVersion $serverVersion,
    protected ILimiter $rateLimiter,
    protected ProblemReportSanitizer $sanitizer,
    protected ProblemReportGithubService $githubService,
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
   * @param null|string $errorHtml Relevant parts of the Nextcloud exception page.
   *
   * @param null|string $userComment Optional comment submitted alongside the user. May be markdown.
   *
   * @return ?array Notification messages which the frontend should present
   * to the user in order to inform the person of where the problem record has
   * been submitted.
   *
   * @throws Exceptions\EnduserNotificationException
   */
  public function submit(
    array $userData,
    array $errorData,
    ?string $errorHtml,
    ?string $userComment,
  ): ?array {

    $this->logInfo(
      'Requested problem report: '
      . 'USER "' . print_r($userData, true)
      . '" DATA "' . (int)!empty($errorData)
      . '" HTML "' . (int)!empty($errorHtml)
      . '" COMMENT "' . $userComment . '".'
    );

    $channels = [];
    if (!empty($this->getEmailRecipient())) {
      $channels[self::REPORT_METHOD_EMAIL] = $this->submitViaEmail(...);
    }
    if ($this->githubService->isConfigured()) {
      $channels[self::REPORT_METHOD_GITHUB] = $this->submitViaGithub(...);
    }
    if (empty($channels)) {
      throw new Exceptions\EnduserNotificationException(
        $this->l->t('There is no channel configured for problem reports.'),
      );
    }

    $notifications = [];
    $exceptions = [];
    foreach ($channels as $channel => $submitter) {
      try {
        $notifications[$channel] = $submitter($userData, $errorData, $errorHtml, $userComment);
      } catch (Throwable $e) {
        $this->logException($e, 'Problem report submission via "' . $channel . '" failed.');
        $exceptions[$channel] = $e;
      }
    }

    if (count($exceptions) === count($channels)) {
      throw new Exceptions\EnduserNotificationException(
        $this->l->t('All possibilities (i.e. %s) to submit your problem report have failed. Please use other communication channels to submit it.', implode(', ', array_keys($exceptions))),
        previous: count($exceptions) == 1 ? array_values($exceptions)[0] : null,
      );
    }

    foreach ($exceptions as $channel => $exception) {
      $notifications[$channel] = $this->l->t(
        'Submitting the problem report via "%1$s" has failed: %2$s',
        [ $channel, $exception->getMessage() ],
      );
    }

    return array_values($notifications);
  }

  /** @return string The configured email recipient, may be empty. */
  private function getEmailRecipient(): string
  {
    return (string)$this->encryptionService->getAppValue(AdminSettings::PROBLEM_REPORT_EMAIL_RECIPIENT_KEY, '');
  }

  /**
   * Compute a stable pseudonym for the given user id. It allows to correlate
   * reports of the same user without revealing the identity, e.g. in public
   * GitHub issues. The administrator can map it back to the user as the
   * pseudonym is also contained in the email reports.
   *
   * @param string $uid
   *
   * @return string
   */
  public function pseudonymize(string $uid): string
  {
    $salt = (string)$this->encryptionService->getAppValue(AdminSettings::PROBLEM_REPORT_PSEUDONYM_SALT_KEY, '');
    if (empty($salt)) {
      $salt = bin2hex(random_bytes(32));
      $this->encryptionService->setAppValue(AdminSettings::PROBLEM_REPORT_PSEUDONYM_SALT_KEY, $salt);
    }
    return substr(hash_hmac('sha256', $uid, $salt), 0, self::PSEUDONYM_LENGTH);
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

    $orchestraName = $this->encryptionService->getConfigValue(ConfigConstants::ORCHESTRA_NAME_KEY, $this->l->t('Unknown Orchestra'));
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
   * @param null|string $errorHtml Relevant parts of the Nextcloud exception page.
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
    ?string $errorHtml,
    ?string $userComment,
  ):?string {

    $recipient = $this->getEmailRecipient();
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

    $pseudonym = $this->pseudonymize((string)$userData['uid']);
    $pseudonymText = $this->l->t('Pseudonym of the reporting user (as used in public reports): %s', $pseudonym);
    $emailTemplate->addBodyText($pseudonymText, $pseudonymText);

    $emailTemplate->addHeading($this->l->t('System Error Report'));
    if (!empty($errorHtml)) {
      $errorHtmlText = Html2Text::convert($errorHtml);
      $emailTemplate->addBodyText($errorHtml, $errorHtmlText);
    }
    if (!empty($errorData)) {
      $errorDataString = json_encode(
        $errorData,
        JSON_PRETTY_PRINT
        |JSON_UNESCAPED_SLASHES
        |JSON_PARTIAL_OUTPUT_ON_ERROR
      );
      $errorDataAttachment = $this->mailer->createAttachment(
        $errorDataString,
        // TRANSLATORS: file name for error report attchment
        $this->l->t('SystemErrorReport') . '.json',
        'application/json',
      );
    }
    if (empty($errorData) && empty($errorHtml)) {
      $bodyText = $this->l->t('No stack-trace or other data was submitted together with the problem report.');
      $emailTemplate->addBodyText($bodyText, $bodyText);
      $errorDataAttachment = null;
    }

    $emailTemplate->addFooter();

    try {
      // ... do not assume decryption works at this point ...
      $orchestraName = $this->encryptionService->getConfigValue(ConfigConstants::ORCHESTRA_NAME_KEY, $this->l->t('Unknown Orchestra'));
      // TRANSLATORS: %s refers to the orchestra name, the configured name of
      // TRANSLATORS: the organization using the app. The entire string is the
      // TRANSLATORS: clear-test part of an email-address.
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
      $this->l->t('Your problem report has been submitted by email to "%s".', $recipientString),
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

  /**
   * Submit a problem report as issue to the configured GitHub
   * repository. All data is sanitized before it leaves the server, the user
   * is only identified by a pseudonym.
   *
   * @param array $userData The user reporting the error [ 'uid' => UID, 'displayName' => DISPLAY_NAME ].
   *
   * @param array $errorData The raw error data caught by the frontend code.
   *
   * @param null|string $errorHtml Relevant parts of the Nextcloud exception page.
   *
   * @param null|string $userComment Optional comment submitted alongside the user. May be markdown.
   *
   * @return ?string A notification message for the frontend.
   */
  public function submitViaGithub(
    array $userData,
    array $errorData,
    ?string $errorHtml,
    ?string $userComment,
  ): ?string {
    if (!$this->githubService->isConfigured()) {
      throw new Exceptions\EnduserNotificationException(
        $this->l->t('GitHub is not configured as receiver for problem reports.'),
      );
    }

    /** @var IUser $user */
    $user = $this->userSession->getUser();
    if (empty($user) || $user->getUID() !== ($userData['uid'] ?? null)) {
      throw new Exceptions\EnduserNotificationException(
        $this->l->t('The submitted user-id differs from what the server thinks is the currently logged in user.'),
      );
    }
    try {
      $this->rateLimiter->registerUserRequest(
        $this->appName . '-problem-report-github',
        self::GITHUB_RATE_LIMIT,
        self::GITHUB_RATE_PERIOD,
        $user,
      );
    } catch (Throwable $t) {
      throw new Exceptions\EnduserNotificationException(
        $this->l->t('Too many problem reports, please try again later.'),
        0,
        $t,
      );
    }

    $report = $this->prepareGithubReport($user, $userData, $errorData, $errorHtml, $userComment);

    $result = $this->githubService->report($report['fingerprint'], $report['title'], $report['body'], $report['comment']);

    return $result['created']
      ? $this->l->t('Your problem report has been submitted as GitHub issue %s.', $result['url'])
      : $this->l->t('The problem has already been reported, your report has been added to the existing GitHub issue %s.', $result['url']);
  }

  /**
   * Generate the markdown which would be published at GitHub for the given
   * problem report, without submitting anything.
   *
   * @param array $userData The user reporting the error [ 'uid' => UID, 'displayName' => DISPLAY_NAME ].
   *
   * @param array $errorData The raw error data caught by the frontend code.
   *
   * @param null|string $errorHtml Relevant parts of the Nextcloud exception page.
   *
   * @param null|string $userComment Optional comment submitted alongside the user. May be markdown.
   *
   * @return null|string The markdown preview or null if GitHub is not configured.
   */
  public function previewGithub(
    array $userData,
    array $errorData,
    ?string $errorHtml,
    ?string $userComment,
  ): ?string {
    if (!$this->githubService->isConfigured()) {
      return null;
    }
    /** @var IUser $user */
    $user = $this->userSession->getUser();
    if (empty($user) || $user->getUID() !== ($userData['uid'] ?? null)) {
      throw new Exceptions\EnduserNotificationException(
        $this->l->t('The submitted user-id differs from what the server thinks is the currently logged in user.'),
      );
    }
    $report = $this->prepareGithubReport($user, $userData, $errorData, $errorHtml, $userComment);
    return '# ' . $report['title'] . "\n\n" . $report['body']
      . "\n\n---\n\n**" . $this->l->t('Comment') . "**\n\n" . $report['comment'];
  }

  /**
   * @param IUser $user
   *
   * @param array $userData
   *
   * @param array $errorData
   *
   * @param null|string $errorHtml
   *
   * @param null|string $userComment
   *
   * @return array{title:string, body:string, comment:string, fingerprint:string}
   */
  private function prepareGithubReport(
    IUser $user,
    array $userData,
    array $errorData,
    ?string $errorHtml,
    ?string $userComment,
  ): array {
    $uid = (string)$userData['uid'];
    $pseudonym = $this->pseudonymize($uid);
    $literals = $this->sanitizationLiterals($user, $userData, $pseudonym);

    /** @var array $cleanData */
    $cleanData = $this->sanitizer->sanitizeData($errorData, $literals);
    $cleanText = empty($errorHtml) ? '' : $this->sanitizer->sanitizeText(Html2Text::convert($errorHtml), $literals);

    $summary = $this->errorSummary($cleanData, $cleanText);
    $title = $this->sanitizer->neutralizeMarkdown(
      $this->sanitizer->singleLine('[' . $this->l->t('Problem Report') . '] ' . $summary['message'], 100)
    );
    $fingerprint = $this->fingerprint($cleanData, $summary);

    $body = $this->githubIssueBody($cleanData, $cleanText);

    $comment = $this->l->t('Reported by user `%s` (pseudonym).', $pseudonym);
    if (!empty($userComment)) {
      $cleanComment = $this->sanitizer->truncate(
        $this->sanitizer->neutralizeMarkdown($this->sanitizer->sanitizeText($userComment, $literals)),
        self::GITHUB_COMMENT_LIMIT,
      );
      $comment .= "\n\n" . $this->sanitizer->quote($cleanComment);
    }

    return [ 'title' => $title, 'body' => $body, 'comment' => $comment, 'fingerprint' => $fingerprint ];
  }

  /**
   * @param IUser $user
   *
   * @param array $userData
   *
   * @param string $pseudonym
   *
   * @return array<string, string>
   */
  private function sanitizationLiterals(IUser $user, array $userData, string $pseudonym): array
  {
    $literals = [
      (string)$userData['uid'] => 'user-' . $pseudonym,
      $user->getUID() => 'user-' . $pseudonym,
      (string)($userData['displayName'] ?? '') => '[user]',
      $user->getDisplayName() => '[user]',
    ];
    $baseUrl = $this->urlGenerator->getBaseUrl();
    $literals[$baseUrl] = '[nextcloud-url]';
    $host = parse_url($baseUrl, PHP_URL_HOST);
    if (!empty($host)) {
      $literals[$host] = '[host]';
    }
    $appRoot = dirname(__DIR__, 2);
    $literals[$appRoot] = '<' . $this->appName . '>';
    if (class_exists(\OC::class) && !empty(\OC::$SERVERROOT)) {
      $literals[\OC::$SERVERROOT] = '<nextcloud>';
    }
    return $literals;
  }

  /**
   * @param array $cleanData
   *
   * @param string $cleanText
   *
   * @return array{class:string, message:string}
   */
  private function errorSummary(array $cleanData, string $cleanText): array
  {
    $exception = is_array($cleanData['exception'] ?? null) ? $cleanData['exception'] : [];
    $class = (string)($exception['Exception'] ?? $exception['class'] ?? '');
    $message = $cleanData['message'] ?? $exception['Message'] ?? null;
    if (!is_string($message) || $message === '') {
      $message = strtok(trim($cleanText), "\n") ?: $this->l->t('Unspecified error');
    }
    return [ 'class' => $class, 'message' => $message ];
  }

  /**
   * Fingerprint for de-duplication: exception class, message with volatile
   * numbers removed and the top stack frames. Everything has been sanitized
   * already, so the fingerprint does not depend on user specific data.
   *
   * @param array $cleanData
   *
   * @param array{class:string, message:string} $summary
   *
   * @return string
   */
  private function fingerprint(array $cleanData, array $summary): string
  {
    $frames = [];
    $trace = $cleanData['exception']['Trace'] ?? $cleanData['trace'] ?? [];
    if (is_array($trace)) {
      foreach (array_slice($trace, 0, 5) as $frame) {
        if (is_array($frame)) {
          $frames[] = ($frame['file'] ?? '') . ':' . ($frame['line'] ?? '') . ':' . ($frame['class'] ?? '') . ($frame['function'] ?? '');
        } elseif (is_string($frame)) {
          $frames[] = preg_replace('/\d+/', '#', $frame);
        }
      }
    }
    $normalizedMessage = preg_replace('/\d+/', '#', $summary['message']);
    return hash('sha256', json_encode([ $this->appName, $summary['class'], $normalizedMessage, $frames ]));
  }

  /**
   * @param array $cleanData
   *
   * @param string $cleanText
   *
   * @return string
   */
  private function githubIssueBody(array $cleanData, string $cleanText): string
  {
    $parts = [
      '_' . $this->l->t('This issue has been created automatically from an end-user problem report.') . '_',
      '',
      '| | |',
      '|-|-|',
      '| ' . $this->appName . ' | ' . $this->appManager->getAppVersion($this->appName) . ' |',
      '| Nextcloud | ' . $this->serverVersion->getVersionString() . ' |',
      '| PHP | ' . PHP_VERSION . ' |',
    ];
    $header = implode("\n", $parts);
    $budget = self::GITHUB_BODY_LIMIT - strlen($header) - 400;

    $sections = [];
    if (!empty($cleanText)) {
      $sections[] = [ '## ' . $this->l->t('Error page'), $cleanText, '' ];
    }
    if (!empty($cleanData)) {
      $json = json_encode($cleanData, JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE|JSON_PARTIAL_OUTPUT_ON_ERROR);
      $sections[] = [ '## ' . $this->l->t('Error data'), (string)$json, 'json' ];
    }
    if (empty($sections)) {
      $parts[] = '';
      $parts[] = $this->l->t('No stack-trace or other data was submitted together with the problem report.');
    }
    $share = empty($sections) ? 0 : intdiv($budget, count($sections));
    foreach ($sections as [ $heading, $content, $language ]) {
      $parts[] = '';
      $parts[] = $heading;
      $parts[] = $this->sanitizer->fence($this->sanitizer->truncate($content, $share), $language);
    }
    return implode("\n", $parts);
  }
}
