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

/**
 * Simple wrapper class around a domain name resolver. We currently just use
 * the stock PHP dns_get_record() function. In principle all functions here
 * could be static, but then it would be more difficult to mock them for
 * PHPUnit.
 */
class DomainNameService
{
  public const IN_A = 'A';
  public const IN_AAAA = 'AAAA';

  /**
   * Try to resolve the given hostname and return an array with its IPv4 and
   * IPv6 addresses, if any.
   *
   * @param string $hostname
   *
   * @return array
   */
  public function resolveHostname(string $hostname): array
  {
    // in principle "localhost" should not be there in DNS ...
    if ($hostname == 'localhost') {
      return [
        self::IN_A => ['127.0.0.1'],
        self::IN_AAAA => ['::1'],
      ];
    }
    $records = dns_get_record($hostname, DNS_A | DNS_AAAA);
    if ($records === false) {
      return [
      ];
    }
    $inA = [];
    $inAAAA = [];
    foreach ($records as $record) {
      if ($record['class'] != 'IN') {
        continue;
      }
      switch ($record['type']) {
        case 'A':
          $ip = $record['ip'];
          if ($this->isIpv4($ip)) {
            $inA[] = $ip;
          }
          break;
        case 'AAAA':
          $ip = $record['ipv6'];
          if ($this->isIpv6($ip)) {
            $inAAAA[] = $ip;
          }
          break;
      }
    }
    return [
      self::IN_A => $inA,
      self::IN_AAAA => $inAAAA,
    ];
  }

  /**
   * A wrapper around filter_var(). Note that the netmask-bits must not be
   * specified, just the address part.
   *
   * @param string $ip
   *
   * @return bool
   */
  public function isIpv4(string $ip): bool
  {
    return false !== filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4);
  }

  /**
   * A wrapper around filter_var(). Note that the netmask-bits must not be
   * specified, just the address part.
   *
   * @param string $ip
   *
   * @return bool
   */
  public function isIpv6(string $ip): bool
  {
    return false !== filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6);
  }
}
