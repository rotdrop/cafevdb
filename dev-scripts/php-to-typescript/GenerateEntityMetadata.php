#!/usr/bin/env php
<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine <himself@claus-justus-heine.de>
 * @copyright 2020, 2021, 2022, 2023, 2024, 2025 Claus-Justus Heine
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

namespace OCA\CAFEVDB\DevScripts\PhpToTypeScript;

use ReflectionClass;

use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Output\ConsoleSectionOutput;
use Symfony\Component\Process\Process;
use Symfony\Component\Process\Exception as ProcessExceptions;
use Symfony\Component\Console\Helper\ProgressBar;

use OCA\CAFEVDB\Common\Util;

/**
 * Generate enough meta-data for TypeScript to be able to define getters for
 * to-one and iterators for to-many associations.
 */
class GenerateEntityMetadata
{
  private array $entityMetaInfo = [];

  const FIELD_TYPE_OWNED = 'owned';
  const FIELD_TYPE_TO_ONE = 'to-one';
  const FIELD_TYPE_TO_MANY = 'to-many';

  const FIELD_TYPES = [
    self::FIELD_TYPE_OWNED,
    self::FIELD_TYPE_TO_MANY,
    self::FIELD_TYPE_TO_ONE,
  ];

  const META_DATA_NAME = 'EntityMetadata';

  /**
   * CTOR.
   *
   * @param string $phpNameSpacePrefix PHP-Namespace prefix to strip from the
   * start. Output will go to subdirectories according to the
   * remaining namespace.
   *
   * @param string $outputPrefix Output will start at this directory into a
   * directory formed by the full class-name of each entity (after stripping
   * the PHP namespace prefix).
   *
   * @param OutputInterface|ConsoleSectionOutput $output
   *
   * @param ?ProgressBar $progressBar
   *
   * @param string $ormCliCmd The full path the ORM cli command which is used to
   * obtain the list of known databse entities and the meta-data information.
   */
  public function __construct(
    private string $phpNameSpacePrefix,
    private string $outputPrefix,
    private OutputInterface|ConsoleSectionOutput $output,
    private ?ProgressBar $progressBar = null,
    private string $ormCliCmd = __DIR__ . '/../orm-cmd.php',
  ) {
  }

  /**
   * Run the ORM cli commands and form an array of meta-data information.
   *
   * @return array
   */
  public function generateSparseMetadata(): array
  {
    $nameSpacePrefix = 'OCA\\CAFEVDB\\Database\\Doctrine\\ORM\\Entities\\';

    $entityNames = [];
    $this->entityMetaInfo = [];

    // --format=json not understood here.
    $ormCliProcess = new Process([
      $this->ormCliCmd,
      'orm:info',
    ]);

    $ormCliProcess->run();
    $rawEntities = $ormCliProcess->getOutput();
    $ormFile = fopen('php://temp', 'r+');
    fputs($ormFile, $rawEntities);
    rewind($ormFile);
    /* discard */ fgets($ormFile, 4096);
    $line = fgets($ormFile, 4096);
    while ($line !== false) {
      [,$line] = Util::explode(' ', $line, Util::OMIT_EMPTY_FIELDS|Util::TRIM);
      $entity = array_pop(explode('\\', trim($line)));
      if (!empty($entity)) {
        $entityNames[] = $entity;
      }
      $line = fgets($ormFile, 4096);
    }

    // Ok, now fetch the meta data. For implementing kind-of entity-repositories
    // (read-only) in the frontend we just need the information if a field is an
    // association, and if this is the case, whether it is to-on -- this yields a
    // simple getter -- or if it is to-may -- this yields an iterator.
    foreach ($entityNames as $entityName) {
      $ormCliProcess = new Process([
        $this->ormCliCmd,
        '--format=json',
        'orm:mapping:describe',
        $entityName,
      ]);
      $ormCliProcess->run();
      $metaDataJson = $ormCliProcess->getOutput();
      $metaData = json_decode($metaDataJson, true);
      $entityName = $metaData['name'];
      if (!str_starts_with($entityName, $this->phpNameSpacePrefix)
          || str_contains($entityName, 'Wrapped')) {
        continue;
      }
      $entityName = new ReflectionClass($entityName)->getShortName();
      // echo $entityName . PHP_EOL;
      // print_r($metaData);
      $metaInfo = [];
      foreach ($metaData['fieldMappings'] as $fieldName => $ownField) {
        $metaInfo[$fieldName] = [
          'fieldName' => $fieldName,
          'type' => $ownField['type'],
          'id' => !!$ownField['id'],
          'mapping' => self::FIELD_TYPE_OWNED,
        ];
      }
      foreach ($metaData['associationMappings'] as $fieldName => $associationField) {
        $multiplicity = str_contains($associationField['class'], 'ToOne')
          ? self::FIELD_TYPE_TO_ONE
          : self::FIELD_TYPE_TO_MANY;
        $metaInfo[$fieldName] = [
          'fieldName' => $fieldName,
          'id' => !!$associationField['id'],
          'mapping' => $multiplicity,
        ];
      }
      $this->entityMetaInfo[$entityName] = $metaInfo;
      if ($this->output->getVerbosity() >= OutputInterface::VERBOSITY_VERBOSE) {
        $text = explode(PHP_EOL, $entityName . ': ' . print_r($metaInfo, true));
        foreach ($text as $line) {
          $this->output->writeln($line, options: OutputInterface::VERBOSITY_VERBOSE);
        }
      } else {
        $this->output->writeln($entityName);
      }
    }

    return $this->entityMetaInfo;
  }
}
