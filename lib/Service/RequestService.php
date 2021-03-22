<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine
 * @copyright 2011-2016, 2021 Claus-Justus Heine <himself@claus-justus-heine.de>
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

namespace OCA\CAFEVDB\Service;

use OCP\IURLGenerator;
use OCP\ILogger;
use OCP\IL10N;

class RequestService
{
  use \OCA\CAFEVDB\Traits\LoggerTrait;

  /** @var IURLGenerator */
  private $urlGenerator;

  /** @var ILogger */
  private $logger;

  /** @var IL10N */
  private $l;

  public function __construct(
    IURLGenerator $urlGenerator
    , ILogger $logger
    , IL10N $l10n
  ) {
    $this->urlGenerator = $urlGenerator;
    $this->logger = $logger;
    $this->l = $l10n;
  }

  /**
   * Post to a Cloud route.
   *
   * @param string $route Route name (i.e.: not the URL)
   *
   * @param array $routeParams Parameters built in to the URL (despite
   * the fact that we use POST)
   *
   * @param array $postData Stuff passed by the POST method.
   *
   * @param string $type How $postData is encoded. Can be 'json' or
   * 'urlencoded'. Default is 'json'.
   */
  public function postToRoute(string $route,
                              array $routeParams = [],
                              array $postData = [],
                              string $type = 'json')
  {
    $url = $this->urlGenerator->linkToRouteAbsolute($route, $routeParams);

    $requestToken = \OCP\Util::callRegister();
    $postData['requesttoken'] = $requestToken;
    $url .= '?requesttoken='.urlencode($requestToken);

    switch ($type) {
    case 'json':
      if (is_array($postData)) {
        $postData = \OC_JSON::encode($postData);
      }
      $httpHeader = 'Content-Type: application/json'."\r\n".
                  'Content-Length: '.strlen($postData);
      break;
    case 'urlencoded':
      if (is_array($postData)) {
        $postData = http_build_query($postData, '', '&');
      }
      $httpHeader = 'Content-Type: application/x-www-form-urlencoded'."\r\n".
                  'Content-Length: '.strlen($postData);
      break;
    default:
      throw new \InvalidArgumentException($this->l->t("Supported data formats are JSON and URLENCODED"));
      break;
    }

    if (function_exists('curl_version')) {
      $cookies = array();
      foreach($_COOKIE as $name => $value) {
        $cookies[] = "$name=".urlencode($value);
      }
      session_write_close(); // avoid deadlock
      $c = curl_init($url);
      curl_setopt($c, CURLOPT_VERBOSE, 0);
      curl_setopt($c, CURLOPT_POST, 1);
      curl_setopt($c, CURLOPT_POSTFIELDS, $postData);
      if (count($cookies) > 0) {
        curl_setopt($c, CURLOPT_COOKIE, join("; ", $cookies));
      }
      curl_setopt($c, CURLOPT_RETURNTRANSFER, 1);
      $result = curl_exec($c);
      curl_close($c);
      session_start(); // restart it
    } else {
      $cookies = array();
      foreach($_COOKIE as $name => $value) {
        $cookies[] = "$name=".urlencode($value);
      }
      $cookies = (count($cookies) > 0) ? "Cookie: " . join("; ", $cookies) . "\r\n" : '';

      $context = stream_context_create([
        'http' => [
          'method' => 'post',
          'header' => $httpHeader."\r\n".$cookies,
          'content' => $postData,
          'follow_location' => 1,
        ],
      ]);

      // session_write_close(); // avoid deadlock

      $fp = fopen($url, 'rb', false, $context);

      if ($fp === false) {
        throw new \RunTimeException($this->l->t("Unable to post to route %s (%s)", array($route, $url)));
      }

      $result = stream_get_contents($fp);
      fclose($fp);

      // session_start(); // restart it
    }

    $data = json_decode($result, true);
    if (!is_array($data) || (count($data) > 0 && !isset($data['data']))) {
      throw new \RunTimeException(
        $this->l->t("Invalid response from API call: %s", print_r($result, true)));
    }

    if (isset($data['data'])) {
      return $data['data'];
    } else {
      return $data;
    }
  }
}
