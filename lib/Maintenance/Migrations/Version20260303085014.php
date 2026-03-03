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

use OCA\CAFEVDB\Wrapped\Doctrine\DBAL\Schema\Schema;
use OCA\CAFEVDB\Database\Doctrine\Migrations\AbstractStructuralMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260303085014 extends AbstractStructuralMigration
{
  /** {@inheritdoc} */
  public function getDescription(): string
  {
    return $this->l->t('Fetch the untranslated valid of certain translatable fields by means of a
"generated field", which is provided by the underlying database engine.');
  }

  /** {@inheritdoc} */
  public function up(Schema $schema): void
  {
    // this up() migration is auto-generated, please modify it to your needs
    $this->addSql('ALTER TABLE EmailTemplates ADD untranslated_tag VARCHAR(128) GENERATED ALWAYS AS (tag) VIRTUAL');
    $this->addSql('CREATE UNIQUE INDEX UNIQ_51BDDDC86953633 ON EmailTemplates (untranslated_tag)');
    $this->addSql('ALTER TABLE InstrumentFamilies ADD untranslated_family VARCHAR(128) GENERATED ALWAYS AS (family) VIRTUAL');
    $this->addSql('CREATE UNIQUE INDEX UNIQ_31147B7656212A8B ON InstrumentFamilies (untranslated_family)');
    $this->addSql('ALTER TABLE Instruments ADD untranslated_name VARCHAR(128) GENERATED ALWAYS AS (name) VIRTUAL');
    $this->addSql('CREATE UNIQUE INDEX UNIQ_65CC51DC95C7D10B ON Instruments (untranslated_name)');
    $this->addSql('ALTER TABLE ProjectParticipantFields ADD untranslated_name VARCHAR(128) GENERATED ALWAYS AS (name) VIRTUAL, ADD untranslated_tab VARCHAR(128) GENERATED ALWAYS AS (tab) VIRTUAL');
    $this->addSql('ALTER TABLE ProjectParticipantFieldsDataOptions ADD untranslated_label VARCHAR(128) GENERATED ALWAYS AS (label) VIRTUAL');
  }

  /** {@inheritdoc} */
  public function down(Schema $schema): void
  {
    // this down() migration is auto-generated, please modify it to your needs
    $this->addSql('DROP INDEX UNIQ_51BDDDC86953633 ON EmailTemplates');
    $this->addSql('ALTER TABLE EmailTemplates DROP untranslated_tag');
    $this->addSql('DROP INDEX UNIQ_31147B7656212A8B ON InstrumentFamilies');
    $this->addSql('ALTER TABLE InstrumentFamilies DROP untranslated_family');
    $this->addSql('DROP INDEX UNIQ_65CC51DC95C7D10B ON Instruments');
    $this->addSql('ALTER TABLE Instruments DROP untranslated_name');
    $this->addSql('ALTER TABLE ProjectParticipantFields DROP untranslated_name, DROP untranslated_tab');
    $this->addSql('ALTER TABLE ProjectParticipantFieldsDataOptions DROP untranslated_label');
  }
}
