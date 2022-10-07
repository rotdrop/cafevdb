<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine
 * @copyright 2020, 2021 Claus-Justus Heine <himself@claus-justus-heine.de>
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

namespace OCA\CAFEVDB\Database\Doctrine;

use OCA\CAFEVDB\Wrapped\Doctrine\Common\Collections;

/**
 * Some mostly static convenience stuff.
 */
class Util
{
  /**
   * Convenience function to generate Collections\Criteria
   */
  public static function criteria(): Collections\Criteria
  {
    return new Collections\Criteria();
  }

  /**
   * Convenience function to generate Collections\ExpressionBuilder
   *
   * @return Collections\ExpressionBuilder
   */
  public static function criteriaExpr(): Collections\ExpressionBuilder
  {
    return Collections\Criteria::expr();
  }

  /**
   * Convenience function. Convert an array of criteria as accepted by
   * Doctrine\ORM\EntityRepository::findBy() to an instance of Collections\Criteria.
   *
   * @todo This could be made more elaborate like
   * OCA\CAFEVDB\Database\Doctrine\ORM\Traits\FindLikeTrait::findBy().
   * @todo Teach
   * OCA\CAFEVDB\Database\Doctrine\ORM\Traits\FindLikeTrait::findBy()
   * to use grouping with parens.
   *
   * @param array $arrayCriteria Array of FIELD => VALUE pairs. FIELD support
   * a negation operation '!'. If VALUE is an array it is interpreted as a
   * list of acceptable values. Otherwise equality is tested. Criteria can be
   * prefixed by '|' and '&' to indicate the boolean junctor with the other
   * criteria. Grouping is possible by using '(' and ')'. The final closing
   * paren may be omitted. Example:
   * ```
   * [
   *   '!fieldId' => 13,       // search for field_id != 13
   *   '&(|optionValue' => ''  // with option_value the empty string ... or
   *   'optionValue' => null   // option_value is NULL
   *   ')' => IGNORED_VALUE    // close the OR-group
   * ],
   * ```
   *
   * @return Collections\Criteria
   */
  public static function criteriaWhere(array $arrayCriteria):Collections\Criteria
  {
    $criteria = self::criteria();
    $expr = self::criteriaExpr();
    $groupLevel = -1;
    $groupExpression = [];
    $expression = null;
    foreach ($arrayCriteria as $key => $value) {
      $junctor = 'andWhere';
      if ($key[0] == '|') {
        $key = substr($key, 1);
        $junctor = 'orWhere';
      } elseif ($key[0] == '&') {
        $key = substr($key, 1);
      }
      if ($key[0] == '(') {
        $key = substr($key, 1);
        $expression = [ 'junctor' => $junctor ];
        $expression['composite'] = 'andX';
        if ($key[0] == '|') {
          $expression['composite'] = 'orX';
          $key = substr($key, 1);
        } else if ($key[0] == '&') {
          $key = substr($key, 1);
        }
        $expression['components'] = [];
        $groupExpression[++$groupLevel] = $expression;
      } else if ($key[0] == ')') {
        $key = substr($key, 1);
        $expression = array_pop($groupExpression); $groupLevel--;
        $junctor = $expression['junctor'];
        $composite = $expression['composite'];
        $components = $expression['components'];
        $compositeExpr = call_user_func_array([ $expr, $composite ], $components);
        if ($groupLevel >= 0) {
          $groupExpression[$groupLevel]['components'][] = $compositeExpr;
        } else {
          $criteria->$junctor($compositeExpr);
        }
      }
      if (strlen($key) == 0) {
        // control item, e.g. parens
        continue;
      }
      if ($key[0] == '!') {
        $key = substr($key, 1);
        $compExpr = is_array($value) ? $expr->notIn($key, $value) : $expr->neq($key, $value);
      } else {
        $compExpr = is_array($value) ? $expr->in($key, $value) : $expr->eq($key, $value);
      }
      if ($groupLevel >= 0) {
        $groupExpression[$groupLevel]['components'][] = $compExpr;
      } else {
        $criteria->$junctor($compExpr);
      }
    }
    if ($groupLevel == 0) {
      // omitted closing parenthesis at end
      $expression = $groupExpression[0];
      $junctor = $expression['junctor'];
      $composite = $expression['composite'];
      $components = $expression['components'];
      $compositeExpr = call_user_func_array([ $expr, $composite ], $components);
      $criteria->$junctor($compositeExpr);
    }
    return $criteria;
  }
}
