<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine
 * @copyright 2021, 2022 Claus-Justus Heine <himself@claus-justus-heine.de>
 */

namespace OCA\CAFEVDB\Common;

use OCP\AppFramework\IAppContainer;

/**
 * Simplistic do-undo interface in order to be stacked into a
 * do-undo-list.
 */
interface IUndoable
{
  /** Do something. */
  public function do();

  /** Undo that what was done by do(). */
  public function undo();

  /** Reset initial state */
  public function reset();

  public function initialize(IAppContainer $appContainer);
}

// Local Variables: ***
// c-basic-offset: 2 ***
// indent-tabs-mode: nil ***
// End: ***
