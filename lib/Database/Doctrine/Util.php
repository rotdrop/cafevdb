<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine
 * @copyright 2020, 2021 Claus-Justus Heine <himself@claus-justus-heine.de>
 *
 * This library se Doctrine\ORM\Tools\Setup;is free software; you can redistribute it and/or
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
   *
   * @param array $arrayCriteria Array of FIELD => VALUE pairs. FIELD
   * support a negation operation '!'. If VALUE is an array it is
   * interpreted as a list of acceptable values. Otherwise equality is tested.
   *
   * @return Collections\Criteria
   */
  public static function criteriaWhere(array $arrayCriteria):Collections\Criteria
  {
    $criteria = self::criteria();
    $expr = self::criteriaExpr();
    // unfortunately, andWhere() does not work if there is no condition already.
    foreach ($arrayCriteria as $key => $value) {
      if ($key[0] == '!') {
        $key = substr($key, 1);
        $comp = is_array($value) ? $expr->notIn($key, $value) : $expr->neq($key, $value);
      } else {
        $comp = is_array($value) ? $expr->in($key, $value) : $expr->eq($key, $value);
      }
      $criteria->andWhere($comp);
    }
    return $criteria;
  }
}
