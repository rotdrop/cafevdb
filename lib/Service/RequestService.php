<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine <himself@claus-justus-heine.de>
 * @copyright 2011-2016, 2021, 2022 Claus-Justus Heine
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

use InvalidArgumentException;
use RuntimeException;

use OCP\IRequest;
use OCP\IURLGenerator;
use OCP\ISession;
use OCP\ILogger;
use OCP\IL10N;

use OCA\CAFEVDB\Exceptions;

/** Place server-to-server AJAX calls. */
class RequestService
{
  use \OCA\CAFEVDB\Traits\LoggerTrait;

  const JSON = 'json';
  const URL_ENCODED = 'urlencoded';

  /** @var IRequest */
  private $request;

  /** @var IURLGenerator */
  private $urlGenerator;

  /** @var ISession */
  private $session;

  /** @var IL10N */
  private $l;

  /**
   * @var bool
   *
   * Close the php-session if it is still open, just before actually
   * posting to a route on the same server. The default is to close
   * the session automatically if needed.
   */
  private $closeSession;

  // phpcs:disable Squiz.Commenting.FunctionComment.Missing
  public function __construct(
    IRequest $request,
    IURLGenerator $urlGenerator,
    ISession $session,
    ILogger $logger,
    IL10N $l10n,
    bool $closeSession = true,
  ) {
    $this->request = $request;
    $this->urlGenerator = $urlGenerator;
    $this->session = $session;
    $this->logger = $logger;
    $this->l = $l10n;
    $this->closeSession = $closeSession;
  }
  // phpcs:enable

  /**
   * Post to a Cloud route.
   *
   * @param string $route Route name (i.e.: not the URL).
   *
   * @param array $routeParams Parameters built in to the URL (despite
   * the fact that we use POST).
   *
   * @param array $postData Stuff passed by the POST method.
   *
   * @param string $type How $postData is encoded. Can be 'json' or
   * 'urlencoded'. Default is 'json'.
   *
   * @return array
   */
  public function postToRoute(
    string $route,
    array $routeParams = [],
    array $postData = [],
    string $type = self::JSON,
  ) {
    $url = $this->urlGenerator->linkToRouteAbsolute($route, $routeParams);

    return $this->postToInternalUrl($url, $postData, $type);
  }

  /**
   * Post to a Cloud url
   *
   * @param string $url Just the URL.
   *
   * @param array $postData Stuff passed by the POST method.
   *
   * @param string $type How $postData is encoded. Can be 'json' or
   * 'urlencoded'. Default is 'json'.
   *
   * @return array
   */
  public function postToInternalUrl(
    string $url,
    array $postData = [],
    string $type = self::JSON,
  ) {
    if (!$this->session->isClosed()) {
      if ($this->closeSession) {
        $this->session->close();
      } else {
        throw new Exceptions\SessionStillOpenException(
          $this->l->t('Cannot call internal route while the session is open.'),
          session: $this->session
        );
      }
    }

    $requestToken = \OCP\Util::callRegister();
    $postData['requesttoken'] = $requestToken;
    $url .= '?requesttoken='.urlencode($requestToken);

    switch ($type) {
      case self::JSON:
        if (is_array($postData)) {
          $postData = \OC_JSON::encode($postData);
        }
        break;
      case self::URL_ENCODED:
        if (is_array($postData)) {
          $postData = http_build_query($postData, '', '&');
        }
        break;
      default:
        throw new InvalidArgumentException(
          $this->l->t('Supported data formats are "%1$s" and "%2$s".', [
            self::JSON, self::URL_ENCODED,
          ]));
        break;
    }

    $cookies = array();
    foreach ($this->request->cookies as $name => $value) {
      $cookies[] = "$name=" . urlencode($value);
    }

    $c = curl_init($url);
    curl_setopt($c, CURLOPT_VERBOSE, 0);
    curl_setopt($c, CURLOPT_POST, 1);
    curl_setopt($c, CURLOPT_POSTFIELDS, $postData);
    if (count($cookies) > 0) {
      curl_setopt($c, CURLOPT_COOKIE, join("; ", $cookies));
    }
    curl_setopt($c, CURLOPT_RETURNTRANSFER, 1);
    // this is internal, so there is no point in verifying certs:
    curl_setopt($c, CURLOPT_SSL_VERIFYHOST, 0);
    curl_setopt($c, CURLOPT_SSL_VERIFYPEER, 0);

    $result = curl_exec($c);
    curl_close($c);


    $data = json_decode($result, true);
    if (!is_array($data) || (count($data) > 0 && !isset($data['data']))) {
      throw new RunTimeException(
        $this->l->t('Invalid response from API call: "%s"', print_r($result, true)));
    }

    // Some apps still return HTTP_STATUS_OK and code errors and success in
    // the old way ...
    if (($data['status']??null) != 'success' && isset($data['data'])) {
      throw new RuntimeException(
        $this->l->t('Error response from call to internal route "%1$s": %2$s', [
          $url, $data['data']['message']??print_r($data, true)
        ]));
    }

    if (isset($data['data'])) {
      return $data['data'];
    } else {
      return $data;
    }
  }

  /**
   * Post to a Cloud url
   *
   * @param string $url Just the URL.
   *
   * @param array $requestData Stuff passed by the GET method.
   *
   * @return array
   */
  public function getFromInternalUrl(string $url, array $requestData = [],)
  {
    if (!$this->session->isClosed()) {
      if ($this->closeSession) {
        $this->session->close();
      } else {
        throw new Exceptions\SessionStillOpenException(
          $this->l->t('Cannot call internal route while the session is open.'),
          session: $this->session
        );
      }
    }

    $appendSep = strrchr($url, '?') === false ? '?' : '&';

    $requestToken = \OCP\Util::callRegister();
    $url .= $appendSep . 'requesttoken='.urlencode($requestToken);

    $requestData = http_build_query($requestData, '', '&');
    $url .= '&' . $requestData;

    $cookies = array();
    foreach ($this->request->cookies as $name => $value) {
      $cookies[] = "$name=" . urlencode($value);
    }

    $c = curl_init($url);
    curl_setopt($c, CURLOPT_VERBOSE, 0);
    curl_setopt($c, CURLOPT_POST, 0);
    if (count($cookies) > 0) {
      curl_setopt($c, CURLOPT_COOKIE, join("; ", $cookies));
    }
    curl_setopt($c, CURLOPT_RETURNTRANSFER, 1);
    // this is internal, so there is no point in verifying certs:
    curl_setopt($c, CURLOPT_SSL_VERIFYHOST, 0);
    curl_setopt($c, CURLOPT_SSL_VERIFYPEER, 0);

    $result = curl_exec($c);
    curl_close($c);

    return $result;
  }
}
