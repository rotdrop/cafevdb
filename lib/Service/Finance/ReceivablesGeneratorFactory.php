<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine <himself@claus-justus-heine.de>
 * @copyright 2021, 2022, 2023 Claus-Justus Heine
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

namespace OCA\CAFEVDB\Service\Finance;

use RuntimeException;
use RegexIterator;
use RecursiveIteratorIterator;
use RecursiveDirectoryIterator;
use FilesystemIterator;

use OCP\AppFramework\IAppContainer;
use OCP\IL10N;
use Psr\Log\LoggerInterface as ILogger;

use OCA\CAFEVDB\Service\ProjectParticipantFieldsService;
use OCA\CAFEVDB\Database\Doctrine\ORM\Entities;
use OCA\CAFEVDB\Database\EntityManager;
use OCA\CAFEVDB\Database\Doctrine\DBAL\Types\EnumParticipantFieldMultiplicity as Multiplicity;
use OCA\CAFEVDB\Database\Doctrine\DBAL\Types\EnumParticipantFieldDataType as FieldDataType;
use OCA\CAFEVDB\Common\IProgressStatus;

/** Factory for receivables generators. */
class ReceivablesGeneratorFactory
{
  use \OCA\CAFEVDB\Traits\EntityManagerTrait;
  use \OCA\CAFEVDB\Toolkit\Traits\LoggerTrait;

  const GENERATORS_FOLDER = __DIR__ . '/../../';
  const GENERATOR_LABEL = IRecurringReceivablesGenerator::GENERATOR_LABEL;

  /** @var IAppContainer */
  private $appContainer;

  /**
   * @var array
   * List of known generators.
   */
  private $generators;

  // phpcs:disable Squiz.Commenting.FunctionComment.Missing
  public function __construct(
    IAppContainer $appContainer,
    ILogger $logger,
    IL10N $l10n,
  ) {
    $this->appContainer = $appContainer;
    $this->logger = $logger;
    $this->l = $l10n;
  }
  // phpcs:enable

  /**
   * Construct the generator for the given entity. The
   * generator class-name is stored in the data-option with Uuid::NIL.
   *
   * @param Entities\ProjectParticipantField $serviceFeeField
   *
   * @param null|IProgressStatus $progressStatus Optional progress
   * status class in order to give feedback during long running updates.
   *
   * @return IRecurringReceivablesGenerator
   *
   * @todo This is too complicated.
   */
  public function getGenerator(
    Entities\ProjectParticipantField $serviceFeeField,
    ?IProgressStatus $progressStatus = null,
  ):IRecurringReceivablesGenerator {
    $multiplicity = $serviceFeeField->getMultiplicity();
    $dataType = $serviceFeeField->getDataType();
    if (!ProjectParticipantFieldsService::isSupportedType($multiplicity, $dataType)) {
      throw new RuntimeException(
        $this->l->t(
          'Auto-generating receivables or options for the combination of multiplicity/data-type = %s/%s is unsupported', [ $multiplicity, $dataType, ])
      );
    }

    // the generator is coded in the data-option with nil-uuid
    /** @var Entities\ProjectParticipantFieldDataOption $generatorOption */
    $generatorOption = $serviceFeeField->getManagementOption();
    if (empty($generatorOption)) {
      throw new RuntimeException($this->l->t('Unable to find the management option.'));
    }

    // try to construct the generator
    $label = $generatorOption->getLabel();
    $class = $generatorOption->getData();
    if ($label !== self::GENERATOR_LABEL) {
      throw new RuntimeException($this->l->t(
        'Option label should be "%s", got "%s".', [ self::GENERATOR_LABEL, $label, ]));
    }

    $generatorInstance = $this->appContainer->get($class);

    if (empty($generatorInstance)) {
      throw new RuntimeException($this->l->t('Unable to construct generator class "%s".', $class));
    }

    $generatorInstance->bind($serviceFeeField, $progressStatus);

    return $generatorInstance;
  }

  /**
   * @param string $directory
   *
   * @return array
   */
  public function findGenerators(string $directory = self::GENERATORS_FOLDER):array
  {
    $directory = realpath($directory);
    if ($directory === false || !file_exists($directory) || !is_dir($directory)) {
      return [];
    }
    if (!empty($this->generators[$directory])) {
      return $this->generators[$directory];
    }

    $iterator = new RegexIterator(
      new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::LEAVES_ONLY
      ),
      '#^.+\\/[^/]+ReceivablesGenerator\\.php$#i',
      RegexIterator::GET_MATCH);

    $files = array_keys(iterator_to_array($iterator));

    $generators = [];
    foreach ($files as $file) {
      try {
        include_once $file;
      } catch (\Throwable $t) {
        // ignore
      }
    }
    foreach (get_declared_classes() as $className) {
      if (is_subclass_of($className, IRecurringReceivablesGenerator::class)) {
        try {
          $generators[$className::slug()] = $className;
        } catch (\Throwable $t) {
          // ignore
        }
      }
    }
    asort($generators);

    $this->generators[$directory] = $generators;

    return $this->generators[$directory];
  }
}
