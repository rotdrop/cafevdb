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

use DateTimeInterface;

use OCA\CAFEVDB\Wrapped\Doctrine\Common\Collections\Collection;
use OCA\CAFEVDB\Database\Doctrine\ORM\Entities;
use OCA\CAFEVDB\Common\IProgressStatus;
use \DateTimeImmutable as DateTime;

/**
 * Generate recurring receivables and manifest them as recurring
 * Entities\ProjectParticipantField entity. The two prominent examples are
 * membership fees and instrument insurances.
 */
interface IRecurringReceivablesGenerator
{
  /**
   * @var string
   *
   * Label for the "generator option", i.e. the
   * participant-field-option which stores the generator class.
   */
  const GENERATOR_LABEL = Entities\ProjectParticipantFieldDataOption::GENERATOR_LABEL;

  /**
   * @var string
   *
   * During update of receivables just replace any old value by the
   * newly computed value.
   */
  const UPDATE_STRATEGY_REPLACE = 'replace';

  /**
   * @var string
   *
   * During update of receivables skip the update of existing records
   * and record inconsistencies for later processing.
   */
  const UPDATE_STRATEGY_SKIP = 'skip';

  /**
   * @var string
   *
   * During update of receivables compare with the newly computed
   * value and throw an exception if the values differ. This is the
   * default.
   */
  const UPDATE_STRATEGY_EXCEPTION = 'exception';

  const UPDATE_STRATEGIES = [
    self::UPDATE_STRATEGY_REPLACE,
    self::UPDATE_STRATEGY_SKIP,
    self::UPDATE_STRATEGY_EXCEPTION,
  ];

  const OPERATION_OPTION_REGENERATE = 'option-regenerate';
  const OPERATION_OPTION_REGENERATE_ALL = 'option-regenerate-all';
  const OPERATION_GENERATOR_REGENERATE = 'generator-regenerate';
  const OPERATION_GENERATOR_RUN = 'generator-run';

  const OPERATION_SLUGS = [
    self::OPERATION_OPTION_REGENERATE,
    self::OPERATION_OPTION_REGENERATE_ALL,
    self::OPERATION_GENERATOR_RUN,
    self::OPERATION_GENERATOR_REGENERATE,
  ];

  /**
   * @var int
   *
   * The option label may be edited in the per-musician view
   */
  const UI_EDITABLE_LABEL = (1 << 0);

  /**
   * @var int
   *
   * The option value may be edited in the per-musician view
   */
  const UI_EDITABLE_VALUE = (1 << 1);

  /**
   * @var int
   *
   * The option label should be protected in the per-musician view
   */
  const UI_PROTECTED_LABEL = (1 << 2);

  /**
   * @var int
   *
   * The option value should be protected in the per-musician view
   */
  const UI_PROTECTED_VALUE = (1 << 3);

  /**
   * @var int
   *
   * Only bulk-updates without progress bar
   */
  const UI_NO_PROGRESS = (1 << 4);

  /**
   * @var array flags controlling the user interaction
   */
  const UI_FLAGS = [
    self::UI_EDITABLE_LABEL,
    self::UI_EDITABLE_VALUE,
    self::UI_PROTECTED_LABEL,
    self::UI_PROTECTED_VALUE,
  ];

  /**
   * Flags controlling the intended user interaction in the per-musician view.
   *
   * @return int
   */
  public static function uiFlags():int;

  /**
   * A unique short slug which can be used to identify the generator.
   *
   * @return string
   */
  public static function slug():string;

  /**
   * An array of possible conflict resolutions for conflicting data-items.
   *
   * @return array
   */
  public static function updateStrategyChoices():array;

  /**
   * Return an array of button-labels (or just one label), indexed by
   * operation slug. If a label is literal false the corresponding UI element
   * can be hidden, if true a default element will be used. For
   * labelled control elements a string can be returned.
   *
   * @param null|string $slug Must be either null or one of the elements of
   * self::SLUGS. If null all labels will be return, otherwise just the
   * requested one. If $slug is a string but is not contained in
   * self::OPERATION_SLUGS then null is returned.
   *
   * @return null|string|arra
   */
  public static function operationLabels(?string $slug = null);

  /**
   * Bind this instance to the given entity. The idea is to have a
   * constructor which allowes for dependency injection. This,
   * however, means that the DB entities must not be passed through
   * the constructor.
   *
   * @param Entities\ProjectParticipantField $serviceFeeField
   *
   * @param null|IProgressStatus $progressStatus Optional progress
   * status class in order to give feedback during long running updates.
   *
   * @return void
   */
  public function bind(Entities\ProjectParticipantField $serviceFeeField, ?IProgressStatus $progressStatus = null):void;

  /**
   * Update the list of receivables for the bound service-fee field,
   * for example by generating fields up to the current date. The link
   * to the actual Entities\ProjectParticipantField entity is
   * established by a prior call to $this->bind($serviceFeeField).
   * The generated receivables may not yet have been persisted.
   *
   * @return Collection Collection of
   * Entities\ProjectParticipantFieldDataOption entities covering all
   * relevant receivables.
   */
  public function generateReceivables():Collection;

  /**
   * Update the amount to invoice for bound service-fee field.
   *
   * @param Entities\ProjectParticipantFieldDataOption $receivable
   *   The option to update/recompute.
   *
   * @param null|Entities\ProjectParticipant $participant
   *   The musician to update the service claim for. If null, the
   *   values for all affected musicians have to be recomputed.
   *
   * @param string $updateStrategy
   *
   * @return array<string, int>
   * ```
   * [
   *   'added' => #ADDED,
   *   'removed' => #REMOVED,
   *   'changed' => #CHANGED,
   *   'skipped' => #SKIPPED,
   *   'notices' => [ MSG1, MSG2, ... ],
   * ]
   * ```
   * where the numbers reflect the respective actions performed on the
   * field-data for the given option.
   */
  public function updateReceivable(
    Entities\ProjectParticipantFieldDataOption $receivable,
    ?Entities\ProjectParticipant $participant = null,
    string $updateStrategy = self::UPDATE_STRATEGY_EXCEPTION,
  ):array;

  /**
   * Update the amount to invoice for the bound service-fee field.
   *
   * @param Entities\ProjectParticipant $participant
   *   The musician to update the service claim for.
   *
   * @param null|Entities\ProjectParticipantFieldDataOption $receivable
   *   The option to update/recompute. If null all options have to be updated.
   *
   * @param string $updateStrategy
   *
   * @return array<string, int>
   * ```
   * [
   *   'added' => #ADDED,
   *   'removed' => #REMOVED,
   *   'changed' => #CHANGED,
   *   'skipped' => #SKIPPED,
   *   'notices' => [ MSG1, MSG2, ... ],
   * ]
   * ```
   * where the numbers reflect the respective actions performed on the
   * field-data for the participant.
   */
  public function updateParticipant(
    Entities\ProjectParticipant $participant,
    ?Entities\ProjectParticipantFieldDataOption $receivable,
    string $updateStrategy = self::UPDATE_STRATEGY_EXCEPTION,
  ):array;

  /**
   * Compute the amounts to invoice for all relevant musicians and
   * existing receivables. New receivables are not added.
   *
   * @param string $updateStrategy
   *
   * @return array<string, int>
   * ```
   * [
   *   'added' => #ADDED,
   *   'removed' => #REMOVED,
   *   'changed' => #CHANGED,
   *   'skipped' => #SKIPPED,
   *   'notices' => [ MSG1, MSG2, ... ],
   * ]
   * ```
   * where the number reflect the respective actions performed on the
   * field-data for all participants.
   */
  public function updateAll(string $updateStrategy = self::UPDATE_STRATEGY_EXCEPTION):array;

  /**
   * Fetch the due-date for the given receivable or the maximum of all due-dates.
   *
   * @param null|Entities\ProjectParticipantFieldDataOption $receivable
   *
   * @return DateTimeInterface
   */
  public function dueDate(?Entities\ProjectParticipantFieldDataOption $receivable = null):?DateTimeInterface;
}
