<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine <himself@claus-justus-heine.de>
 * @copyright 2025, 2026 Claus-Justus Heine <himself@claus-justus-heine.de>
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

namespace OCA\CAFEVDB\Tests\Unit\Database\Doctrine\ORM\Entities;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes;
use PHPUnit\Framework\MockObject\MockObject;

use OCA\CAFEVDB\Database\Doctrine\ORM\Entities;
use OCA\CAFEVDB\Database\Doctrine\ORM\Repositories\EmailTemplatesRepository;
use OCA\CAFEVDB\Tests\MockProvider;

/** Provide a mock for the instruments repository. */
trait MockEmailTemplatesRepositoryTrait
{
  private const MAIL_MERGE_PREFIX = '00';
  private const MAIL_MERGE_BASE = 'BeispielSerienBrief';
  private const MAIL_MERGE_TAG = self::MAIL_MERGE_PREFIX . '-' . self::MAIL_MERGE_BASE;

  private static array $templates = [];

  /** @return Entities\EmailTemplate */
  private function generateMailMergeTemplate(): Entities\EmailTemplate
  {
    if (!(self::$templates[self::MAIL_MERGE_TAG] ?? null)) {
      $contents = file_get_contents(__DIR__ . '/mail-merge-template.txt');
      self::$templates[self::MAIL_MERGE_TAG] = new Entities\EmailTemplate()
        ->setTag(self::MAIL_MERGE_TAG)
        ->setSubject('Beispiel Serienbrief mit Ersetzungen')
        ->setContents($contents)
        ->setCreated('2010-01-01 00:00:00')
        ->setUpdated('2010-01-01 00:00:00')
        ->setCreatedBy(MockProvider::EXECUTIVE_BOARD_UID)
        ->setUpdatedBy(MockProvider::EXECUTIVE_BOARD_UID)
        ->setId(Constants::FAKED_ENTITY_ID);
    }
    return self::$templates[self::MAIL_MERGE_TAG];
  }

  /** @return void Register the notification templates for invoices and donation receipts. */
  private function generateNotificationTemplates(): void
  {
    $templates = [
      Entities\Invoice::NOTIFICATION_EMAIL_TEMPLATE => ['Invoice notification', 'please find the invoice attached.'],
      Entities\DonationReceipt::NOTIFICATION_EMAIL_TEMPLATE => ['Donation receipt notification', 'please find the donation receipt attached.'],
    ];
    $id = Constants::FAKED_ENTITY_ID;
    foreach ($templates as $tag => [$subject, $text]) {
      ++$id;
      if (self::$templates[$tag] ?? null) {
        continue;
      }
      self::$templates[$tag] = new Entities\EmailTemplate()
        ->setTag($tag)
        ->setSubject($subject)
        ->setContents('<p>Dear {MUSICIAN_FIRST_NAME},</p><p>' . $text . '</p>')
        ->setCreated('2010-01-01 00:00:00')
        ->setUpdated('2010-01-01 00:00:00')
        ->setCreatedBy(MockProvider::EXECUTIVE_BOARD_UID)
        ->setUpdatedBy(MockProvider::EXECUTIVE_BOARD_UID)
        ->setId($id);
    }
  }

  /** @return InstrumentsRepository */
  public function getEmailTemplatesRepositoryMock(): EmailTemplatesRepository
  {
    $repository = $this->getMockBuilder(EmailTemplatesRepository:: class)
      ->disableOriginalConstructor()
      ->getMock();
    $repository->method('find')->willReturnCallback(
      fn(int $id) => $id === Constants::FAKED_ENTITY_ID ? $this->generateMailMergeTemplate() : null,
    );
    $repository->method('findOneBy')->willReturnCallback(function(array $criteria) {
      $this->assertEquals(1, count($criteria));
      $this->generateNotificationTemplates();
      $key = array_keys($criteria)[0];
      $search = $criteria[$key];
      $this->assertStringStartsWith('tag', $key);
      if ($key == 'tag') {
        if ($search == self::MAIL_MERGE_TAG) {
          return $this->generateMailMergeTemplate();
        }
        if (self::$templates[$search] ?? null) {
          return self::$templates[$search];
        }
        if (isset($this->entities[Entities\EmailTemplate::class])) {
          return $this->entities[Entities\EmailTemplate::class]->findFirst(
            fn(int $key, Entities\EmailTemplate $entity) => $entity->getTag() == $search,
          );
        }
        return null;
      }
      if (str_starts_with($key, 'tag#REGEXP')) {
        if (str_contains($search, self::MAIL_MERGE_BASE)) {
          return $this->generateMailMergeTemplate();
        }
        $simplify = fn(string $tag) => preg_replace('/[^a-z0-9]/', '', strtolower($tag));
        $search = $key;
        // the composer searches for the (possibly translated) normalized name
        $simpleSearch = str_replace(
          ['rechnung', 'benachrichtigung', 'spende', 'quittung'],
          ['invoice', 'notification', 'donation', 'receipt'],
          $simplify($search),
        );
        foreach (self::$templates as $tag => $entity) {
          if (str_contains($search, $tag) || str_contains($simpleSearch, $simplify($tag))) {
            return $entity;
          }
        }
        if (isset($this->entities[Entities\EmailTemplate::class])) {
          return $this->entities[Entities\EmailTemplate::class]->findFirst(
            fn(int $key, Entities\EmailTemplate $entity) => str_contains($search, $entity->getTag())
              || str_contains($simpleSearch, $simplify($entity->getTag())),
          );
        }
        return null;
      }
      return null;
    });
    $repository->method('list')->willReturnCallback(function() {
      $result = array_map(fn(Entities\EmailTemplate $template) => [
        'id' => $template->getId(),
        'name' => $template->getTag(),
        'updated' => $template->getUpdated(),
        'created' => $template->getCreated(),
        'updatedBy' => $template->getUpdatedBy(),
        'createdBy' => $template->getCreatedBy(),
      ], self::$templates);
      usort($result, function(array $a, array $b) {
        $result = $a['name'] <=> $b['name'];
        if ($result) {
          return $result;
        }
        return -($a['updated'] <=> $b['updated']);
      });
      return $result;
    });

    $repository->expects($this->never())->method('createQueryBuilder');

    return $repository;
  }
}
