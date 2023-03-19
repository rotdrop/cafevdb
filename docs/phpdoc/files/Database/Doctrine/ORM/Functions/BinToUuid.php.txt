<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine <himself@claus-justus-heine.de>
 * @copyright 2022 Claus-Justus Heine
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

namespace OCA\CAFEVDB\Database\Doctrine\ORM\Functions;

use OCA\CAFEVDB\Wrapped\Doctrine\ORM\Query\AST\Functions\FunctionNode;
use OCA\CAFEVDB\Wrapped\Doctrine\ORM\Query\SqlWalker;
use OCA\CAFEVDB\Wrapped\Doctrine\ORM\Query\Parser;
use OCA\CAFEVDB\Wrapped\Doctrine\ORM\Query\Lexer;

/**
 * Convert binary UUID to string. It requires a user-defined function
 * BIN_TO_UUID(ARG) on the SQL database side.
 */
class BinToUuid extends FunctionNode
{
  public $binaryUuid;

  /** {@inheritdoc} */
  public function getSql(SqlWalker $sqlWalker)
  {
    return 'BIN2UUID(' . $sqlWalker->walkArithmeticPrimary($this->binaryUuid) . ')';
  }

  /** {@inheritdoc} */
  public function parse(Parser $parser)
  {
    $parser->match(Lexer::T_IDENTIFIER);
    $parser->match(Lexer::T_OPEN_PARENTHESIS);

    $this->binaryUuid = $parser->ArithmeticPrimary();

    $parser->match(Lexer::T_CLOSE_PARENTHESIS);
  }
}
