<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine <himself@claus-justus-heine.de>
 * @copyright 2026 Claus-Justus Heine
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

declare(strict_types=1);

namespace OCA\CAFEVDB\Maintenance\Migrations;

use OCA\CAFEVDB\Database\Doctrine\Migrations\AbstractTransactionalMigration;
use OCA\CAFEVDB\Service\EmailTemplateDefaultsService;
use OCA\CAFEVDB\Wrapped\Doctrine\DBAL\Schema\Schema;

/** Install two reference templates listing all available email substitutions. */
final class Version20261101120000 extends AbstractTransactionalMigration
{
  /** {@inheritdoc} */
  public function getDescription(): string
  {
    return $this->l->t(
      'Create email templates listing all available substitutions with English and German variable names.'
    );
  }

  /** {@inheritdoc} */
  public function preUp(Schema $schema): void
  {
    $templates = $this->defaultTemplates();
    $connection = $this->entityManager->getConnection();

    foreach ($templates as $tag => $templateData) {
      $connection->executeStatement(
        'INSERT INTO EmailTemplates (tag, subject, contents)
         SELECT ?, ?, ?
         WHERE NOT EXISTS (SELECT 1 FROM EmailTemplates WHERE tag = ?)',
        [ $tag, $templateData['subject'], $templateData['contents'], $tag ],
      );
    }
  }

  /** {@inheritdoc} */
  public function postDown(Schema $schema): void
  {
    $templates = $this->defaultTemplates();

    foreach ($templates as $tag => $templateData) {
      $this->entityManager->getConnection()->executeStatement(
        'DELETE FROM EmailTemplates WHERE tag = ? AND subject = ? AND contents = ?',
        [ $tag, $templateData['subject'], $templateData['contents'] ],
      );
    }
  }

  /** @return array<string, array{subject: string, contents: string}> */
  private function defaultTemplates(): array
  {
    /** @var EmailTemplateDefaultsService $service */
    $service = $this->appContainer->get(EmailTemplateDefaultsService::class);
    return $service->generateSubstitutionsReferenceTemplates();
  }
}
