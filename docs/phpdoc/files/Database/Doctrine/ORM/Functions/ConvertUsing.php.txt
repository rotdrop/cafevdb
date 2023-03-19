<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine <himself@claus-justus-heine.de>
 * @copyright 2022, 2023 Claus-Justus Heine
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
 * "CONVERT" "(" ArithmeticPrimary AliasResultVariable AliasResultVariable ")".
 *
 * Taken from @link https://gist.github.com/liverbool/6345800.
 *
 * More info:
 * http://dev.mysql.com/doc/refman/5.0/en/cast-functions.html#function_convert
 *
 * _@_category    Entities
 * _@_package     Entities\meta\Functions
 * @author      ツ Liverbool <nukboon@gmail.com>
 * @license     MIT License
 */
class ConvertUsing extends FunctionNode
{
  public $field;
  public $using;
  public $charset;

  /** {@inheritdoc} */
  public function getSql(SqlWalker $sqlWalker)
  {
    return sprintf(
      'CONVERT(%s USING %s)',
      $sqlWalker->walkArithmeticPrimary($this->field),
      //$sqlWalker->walkSimpleArithmeticExpression($this->using), // or remove USING and uncomment this
      $sqlWalker->walkSimpleArithmeticExpression($this->charset)
    );
  }

  /** {@inheritdoc} */
  public function parse(Parser $parser)
  {
    $parser->match(Lexer::T_IDENTIFIER);
    $parser->match(Lexer::T_OPEN_PARENTHESIS);

    $this->field   = $parser->ArithmeticPrimary();
    // adopt use bypass validate variable of parse by using AliasResultVariable ...!!
    $this->using   = $parser->AliasResultVariable();
    $this->charset = $parser->AliasResultVariable();

    $parser->match(Lexer::T_CLOSE_PARENTHESIS);
  }
}
