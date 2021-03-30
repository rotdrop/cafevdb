<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine
 * @copyright 2021 Claus-Justus Heine <himself@claus-justus-heine.de>
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

namespace OCA\CAFEVDB\Service\Finance;

use OCP\AppFramework\IAppContainer;
use OCP\IL10N;
use OCP\ILogger;

use Ramsey\Uuid\Uuid;
use OCA\CAFEVDB\Database\Doctrine\ORM\Entities;
use OCA\CAFEVDB\Database\Doctrine\DBAL\Types\EnumExtraFieldMultiplicity as Multiplicity;
use OCA\CAFEVDB\Database\Doctrine\DBAL\Types\EnumExtraFieldDataType as FieldDataType;

class ReceivablesGeneratorFactory
{
  use \OCA\CAFEVDB\Traits\LoggerTrait;

  /** @var IAppContainer */
  private $appContainer;

  public function __constructor(
    IAppContainer $appContainer
    , ILogger $logger
    , IL10N $l10n
  ) {
    $this->appContainer = $appContainer;
    $this->logger = $logger;
    $this->l = $l10n;
  }

  /**
   * Construct the generator for the given entity. The
   * generator class-name is stored in the data-option with Uuid::NIL.
   *
   * @param Entities\ProjectExtraField $serviceFeeField
   *
   * @todo This is too complicated.
   */
  public function getGenerator(Entities\ProjectExtraField $serviceFeeField)
  {
    if ($serviceFeeField->getMultiplicity() != Multiplicity::RECURRING()
        || $serviceFeeField->getDataType() != FieldDataType::SERVICE_FEE()) {
      throw new \RuntimeException(
        $this->l->t(
          'Can only auto-generate receivables for recurring service-fee -entities, multiplicity/data-type = %s/%s',
          [
            (string)$serviceFeeField->getMultiplicity(),
            (string)$serviceFeeField->getDataType(),
          ]));
    }

    // the generator is coded in the data-option with nil-uuid
    $nilOptions = $serviceFeeField->getDataOptions()->matching([
      'key' => Uuid::NIL,
    ]);
    if (count($nilOptions) !== 1) {
      throw new \RuntimeException($this->l->t('Did not find exactly one data-option with nil-uuid.'));
    }
    /** @var Entities\ProjectExtraFieldDataOption */
    $generatorOption = $nilOptions->first();

    // try to construct the generator
    $label = $generatorOption->getLabel();
    $class = $generatorOption->getData();
    if ($label !== 'generator') {
      throw new \RuntimeException($this->l->t('Option label should be "generator", got "%s".', $label));
    }

    $generatorInstance = $this->appContainer->get($class);

    if (empty($generatorInstance)) {
      throw new \RuntimeException($this->l->t('Unable to construct generator class "%s".', $class));
    }

    $generatorInstance->bind($serviceFeeField);

    return $generatorInstance;
  }
}
