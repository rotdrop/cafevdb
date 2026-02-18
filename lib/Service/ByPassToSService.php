<?php
declare(strict_types=1);
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine <himself@claus-justus-heine.de>
 * @copyright 2026 Claus-Justus Heine
 * @license AGPL-3.0-or-later
 *
 * This file based on ldap_contacts_backend, copyright 2020 Arthur Schiwon
 * <blizzz@arthur-schiwon.de>
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
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */

namespace OCA\CAFEVDB\Service;

use OC\CapabilitiesManager;

use OCP\App\IAppManager;
use OCP\ISession;
use OCP\IRequest;
use OCP\Share\IShare;
use Psr\Log\LoggerInterface;

use OCA\TermsOfService\AppInfo\Application as TermsOfServiceApp;

use OCA\CAFEVDB\Database\Cloud\Mapper\TOSExceptionMapper;

/**
 * The tos-app is desirable something like this is probably needed for legal
 * reasons but it has only very limited support to define exception. But it
 * opens one possibility to by-pass its checks: if the PHP session-variable
 * "term_uuid" contains the configured app-config value term_uuid then all
 * checks are bypassed. One use case is to host mailing-list messages in the
 * cloud and just publically share those with the mailing list server.
 *
 * So the idea is to maintain an enhanced set of exceptions and if remote ip
 * and or access path / service matches an exception then just place the
 * term_uuid in the PHP session and be good.
 */
class ByPassToSService
{
  use \OCA\CAFEVDB\Toolkit\Traits\LoggerTrait;

  private const TERM_UUID_KEY = 'term_uuid';

  private const SCRIPT = '/public.php';

  private const PATH_PREFIX = '/dav/files/';

  private const DEFAULT_TOS_APP_NAME = 'terms_of_service';

  private ?string $termUUID;

  /** {@inheritdoc} */
  public function __construct(
    private DomainNameService $domainNameService,
    private IAppManager $appManager,
    private IRequest $request,
    private ISession $phpSession,
    private TOSExceptionMapper $mapper,
    protected LoggerInterface $logger,
  ) {
  }

  /**
   * Add a terms of service exception for the given share and hostname. The
   * hostname is resolved to its ip-addresses.
   *
   * @param IShare $share
   *
   * @param string $hostname
   *
   * @param bool $exclusive
   *
   * @return bool Result status. In particular \false is returned if the
   * hostname could not be resolved.
   */
  public function addExceptionForHostname(IShare $share, string $hostname, bool $exclusive = false): bool
  {
    $ips = $this->domainNameService->resolveHostname($hostname);
    $ips = array_merge(...$ips);
    if (empty($ips)) {
      return false;
    }
    $token = $share->getToken();
    $this->mapper->addToSException($token, $ips, $exclusive);

    return true;
  }

  /**
   * Check if the current request is allowed to by-pass the ToS checks and if
   * so tweak the session s.t. the terms_of_serice app is satisified.
   *
   * We only meddle with known public DAV shares in order, e.g., to host
   * mailing list responses in the cloud file-system. The ToS app would hinder
   * all public downloads via simple GET requests.
   *
   * @return void
   */
  public function checkForToSByPass(): void
  {
    $script = $this->request->getScriptName();
    $path = $this->request->getPathInfo();
    if ($script != self::SCRIPT
        || !str_starts_with($path, self::PATH_PREFIX)
        || !$this->request->getMethod() == 'GET'
    ) {
      return;
    }
    $tosAppName = class_exists(TermsOfServiceApp::class) ? TermsOfServiceApp::APPNAME : self::DEFAULT_TOS_APP_NAME;
    if (!$this->appManager->isEnabledForAnyone($tosAppName)) {
      // nothing to do
      return;
    }
    try {
      /** @var CapabilitiesManager $capabilitiesManager */
      $capabilitiesManager = \OCP\Server::get(CapabilitiesManager::class);
      $capabilities = $capabilitiesManager->getCapabilities(public: true);
      $this->termUUID = $capabilities[$tosAppName][self::TERM_UUID_KEY] ?? null;
      if ($this->termUUID === null) {
        $this->logger->error('ToS-App seems to be enabled but the term-uuid cannot be determined. ' . print_r($capabilities, true));
      }
    } catch (Throwable $t) {
      $this->logger->info('ToS-App probably not enabled', ['exception' => $t]);
      $this->termUUID = null;
    }
    if ($this->termUUID === null) {
      return;
    }
    if ($this->isAllowedRequest($path)) {
      $this->logger->info('SET TERM UUID IN SESSION ' . $this->termUUID);
      $this->phpSession->set(self::TERM_UUID_KEY, $this->termUUID);
    }
  }

  /**
   * @param string $path
   *
   * @return bool
   */
  private function isAllowedRequest(string $path): bool
  {
    $shareToken = strstr(substr($path, strlen(self::PATH_PREFIX)), '/', true);
    $exceptions = $this->mapper->getToSExeptions($shareToken);
    $remoteIP = $this->request->getRemoteAddress();
    // $json = json_encode($exceptions, JSON_PRETTY_PRINT);
    // $this->logger->info('SHARE TOKEN ' . $shareToken . ' ' . $remoteIP . ' ' . $json);
    foreach ($exceptions as $exception) {
      $ipRanges = explode(',', $exception->getIpRanges());
      foreach ($ipRanges as $ipRange) {
        if ($this->matchCIDR($remoteIP, $ipRange)) {
          return true;
        }
      }
    }
    return false;
  }

  /**
   * @param string $ip
   *
   * @param string $range
   *
   * @return bool
   *
   * @copyright https://stackoverflow.com/questions/594112/matching-an-ip-to-a-cidr-mask-in-php-5/594134#594134
   * @copyright (IPv4) https://stackoverflow.com/questions/594112/matching-an-ip-to-a-cidr-mask-in-php-5/594134#594134
   * @copyright (IPv6) MW. https://stackoverflow.com/questions/7951061/matching-ipv6-address-to-a-cidr-subnet via
   */
  private function matchCIDR(string $ip, string $range): bool
  {
    list($subnet, $bits) = array_pad(explode('/', $range), 2, null);
    $subnetIsIPv6 = $this->domainNameService->isIPv6($subnet);
    if ($bits === null) {
      $bits = $subnetIsIPv6 ? 128 : 32;
    }
    $bits = (int)$bits;

    if ($this->domainNameService->isIpv4($ip) && !$subnetIsIPv6) {
      $mask = -1 << (32 - $bits);

      $ip = ip2long($ip);
      $subnet = ip2long($subnet);
      $subnet &= $mask;
      return ($ip & $mask) === $subnet;
    }

    if ($this->domainNameService->isIpv6($ip) && $subnetIsIPv6) {
      $subnet = inet_pton($subnet);
      $ip = inet_pton($ip);

      $binMask = str_repeat("f", (int)($bits / 4));
      switch ($bits % 4) {
        case 0:
          break;
        case 1:
          $binMask .= "8";
          break;
        case 2:
          $binMask .= "c";
          break;
        case 3:
          $binMask .= "e";
          break;
      }

      $binMask = str_pad($binMask, 32, '0');
      $binMask = pack("H*", $binMask);

      if (($ip & $binMask) === $subnet) {
        return true;
      }
    }
    return false;
  }
}
