<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine
 * @copyright 2011-2016, 2020, 2021, 2022 Claus-Justus Heine <himself@claus-justus-heine.de>
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
 * License along with this library.  If not, see <http://www.gnu.org/licenses/>.
 */

namespace OCA\CAFEVDB\Notifications;

use OCP\Notification\INotification;
use OCP\IURLGenerator;
use OCP\L10N\IFactory as IL10NFactory;
use OCP\ILogger;
use OCP\AppFramework\IAppContainer;

use OCA\CAFEVDB\Service\AuthorizationService;
use OCA\CAFEVDB\Service\OrganizationalRolesService;

class Notifier implements \OCP\Notification\INotifier
{
  use \OCA\CAFEVDB\Traits\LoggerTrait;

  const RECRYPT_USER_SUBJECT = 'recrypt_user';
  const RECRYPT_USER_DENIED_SUBJECT = 'recrypt_user_denied';
  const RECRYPT_USER_HANDLED_SUBJECT = 'recrypt_user_handled';
  const ACCEPT_ACTION = 'accept';
  const DECLINE_ACTION = 'decline';
  const PROTEST_ACTION = 'protest';

  /** @var string */
  protected $appName;

  /** @var IAppContainer */
  protected $appContainer;

  /** @var IL10NFactory */
  protected $l10nFactory;

  /** @var  IURLGenerator */
  protected $urlGenerator;

  public function __construct(
    string $appName
    , IAppContainer $appContainer
    , IL10NFactory $factory
    , IURLGenerator $urlGenerator
    , ILogger $logger
  ) {
    $this->appName = $appName;
    $this->appContainer = $appContainer;
    $this->l10nFactory = $factory;
    $this->urlGenerator = $urlGenerator;
    $this->logger = $logger;
  }

  /**
   * Identifier of the notifier, only use [a-z0-9_]
   * @return string
   */
  public function getID(): string
  {
    return $this->appName;
  }

  /**
   * Human readable name describing the notifier
   * @return string
   */
  public function getName(): string {
    return $this->l10nFactory->get($this->appName)->t('Orchestra Management');
  }

  /**
   * @param INotification $notification
   * @param string $languageCode The code of the language that should be used to prepare the notification
   */
  public function prepare(INotification $notification, string $languageCode): INotification
  {
    if ($notification->getApp() !== $this->appName) {
      // Not my app => throw
      throw new \InvalidArgumentException();
    }

    // Read the language from the notification
    $l = $this->l10nFactory->get($this->appName, $languageCode);

    $notification
      ->setIcon($this->urlGenerator->getAbsoluteURL($this->urlGenerator->imagePath($this->appName, 'app.svg')));

    //https://localhost/nextcloud-git/index.php/settings/admin/cafevdb
    switch ($notification->getSubject()) {
      case self::RECRYPT_USER_SUBJECT:

        $notification->setLink($this->urlGenerator->linkToRouteAbsolute('settings.AdminSettings.index', [ 'section' => $this->appName ]));

        // $parameters = $notification->getSubjectParameters();
        $notification->setRichSubject(
          $l->t('User Recryption Request for {fancy}'), [
            'fancy' => [
              'type' => 'highlight',
              'id' => $notification->getObjectId(),
              'name' => $notification->getObjectId(),
              'link' => $notification->getLink(),
            ]
          ]);

        // Deal with the actions for a known subject
        foreach ($notification->getActions() as $action) {
          switch ($action->getLabel()) {
            case self::ACCEPT_ACTION:
              $url = $this->urlGenerator->linkToOCSRouteAbsolute($this->appName . '.encryption.handleRecryptRequest', [ 'apiVersion' => 'v1', 'userId' => $notification->getObjectId() ]);
              $action->setParsedLabel($l->t('Accept'))
                ->setLink($url, 'POST');
              break;
            case self::DECLINE_ACTION:
              $url = $this->urlGenerator->linkToOCSRouteAbsolute($this->appName . '.encryption.deleteRecryptRequest', [ 'apiVersion' => 'v1', 'userId' => $notification->getObjectId() ]);
              $action->setParsedLabel($l->t('Decline'))
                ->setLink($url, 'DELETE');
              break;
          }
          $notification->addParsedAction($action);
        }

        break;

      case self::RECRYPT_USER_HANDLED_SUBJECT:
        // $parameters = $notification->getSubjectParameters();

        $userId = $notification->getObjectId();

        $subjectString = [ $l->t('Recryption Request handled for {userId}.'), ];
        $subjectParameters = [
          'userId' => [
            'type' => 'highlight',
            'id' => $userId,
            'name' => $userId,
          ]
        ];

        /** @var AuthorizationService $authorizationService */
        $authorizationService = $this->appContainer->get(AuthorizationService::class);
        if ($authorizationService->authorized($userId)) {
          $subjectString[] = $l->t('You may now open the {adminApp}-app and continue with your administrative work.');
          $subjectParameters['adminApp'] = [
            'type' => 'highlight',
            'id' => $this->appName,
            'name' => $this->appName,
            'link' => $this->urlGenerator->linkToRoute($this->appName . '.page.index'),
          ];
        }

        /** @var OrganizationalRolesService $rolesService */
        $rolesService = $this->appContainer->get(OrganizationalRolesService::class);
        if ($rolesService->isClubMember($userId)) {
          $membersApp = $this->appName . 'members';
          $subjectString[] = $l->t('You may now open the {memberApp}-app and inspect your personal data, including a list of your instrument-insurances.');
          $subjectParameters['memberApp'] = [
            'type' => 'highlight',
            'id' => $membersApp,
            'name' => $membersApp,
            'link' => $this->urlGenerator->linkToRoute($membersApp . '.page.index'),
          ];
        }

        $notification->setRichSubject(implode(' ', $subjectString), $subjectParameters);
        break;

      case self::RECRYPT_USER_DENIED_SUBJECT:
        // $parameters = $notification->getSubjectParameters();

        $notification->setRichSubject(
          $l->t('User Recryption Request Denied for {fancy}'), [
            'fancy' => [
              'type' => 'highlight',
              'id' => $notification->getObjectId(),
              'name' => $notification->getObjectId(),
              'link' => $notification->getLink(),
            ]
          ]);

        // Deal with the actions for a known subject
        foreach ($notification->getActions() as $action) {
          switch ($action->getLabel()) {
            case self::PROTEST_ACTION:
              $url = $this->urlGenerator->linkToOCSRouteAbsolute($this->appName . '.encryption.putRecryptRequest', [ 'apiVersion' => 'v1', 'userId' => $notification->getObjectId() ]);
              $action->setParsedLabel($l->t('Protest'))
                ->setLink($url, 'PUT');
              break;
          }
          $notification->addParsedAction($action);
        }
        break;

      default:
        // Unknown subject => Unknown notification => throw
        throw new \InvalidArgumentException();
    }

    // Set the plain text subject automatically
    $this->setParsedSubjectFromRichSubject($notification);

    return $notification;
  }

  // This is a little helper function which automatically sets the simple parsed subject
  // based on the rich subject you set.
  protected function setParsedSubjectFromRichSubject(INotification $notification)
  {
    $placeholders = $replacements = [];
    foreach ($notification->getRichSubjectParameters() as $placeholder => $parameter) {
      $placeholders[] = '{' . $placeholder . '}';
      $replacements[] = $parameter['name'];
    }
    $notification->setParsedSubject(str_replace($placeholders, $replacements, $notification->getRichSubject()));
  }

}
