/**
 * Orchestra member, musicion and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine
 * @copyright 2011-2016, 2020, 2021 Claus-Justus Heine <himself@claus-justus-heine.de>
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

/**
 * QueryData.js
 *
 * A function to parse data from a query string
 *
 * Created by Stephen Morley - http://code.stephenmorley.org/ - and released under
 * the terms of the CC0 1.0 Universal legal code:
 *
 * http://creativecommons.org/publicdomain/zero/1.0/legalcode
 *
 * Creates an object containing data parsed from the specified query string. The
 * parameters are:
 *
 * @param {String} queryString The query string to parse. The query
 *     string may start with a question mark, spaces may be encoded
 *     either as plus signs or the escape sequence '%20', and both
 *     ampersands and semicolons are permitted as separators.  This
 *     optional parameter defaults to query string from the page URL.
 *
 * @param {bool} preserveDuplicates true if duplicate values should be
 *     preserved by storing an array of values, and false if
 *     duplicates should overwrite earler occurrences. This optional
 *     parameter defaults to false.
 *
 * @returns {Object}
 */
const queryData = function(queryString, preserveDuplicates) {

  const result = {};

  // if a query string wasn't specified, use the query string from the URL
  if (queryString === undefined) {
    queryString = location.search ? location.search : '';
  }

  // remove the leading question mark from the query string if it is present
  if (queryString.charAt(0) === '?') queryString = queryString.substring(1);

  // check whether the query string is empty
  if (queryString.length > 0) {

    // replace plus signs in the query string with spaces
    queryString = queryString.replace(/\+/g, ' ');

    // split the query string around ampersands and semicolons
    const queryComponents = queryString.split(/[&;]/g);

    // loop over the query string components
    for (let index = 0; index < queryComponents.length; index++) {
      // extract this component's key-value pair
      const keyValuePair = queryComponents[index].split('=');
      const key = decodeURIComponent(keyValuePair[0]);
      const value = keyValuePair.length > 1
        ? decodeURIComponent(keyValuePair[1])
        : '';
      // check whether duplicates should be preserved
      if (preserveDuplicates) {
        // create the value array if necessary and store the value
        if (!(key in result)) result[key] = [];
        result[key].push(value);
      } else {
        // store the value
        result[key] = value;
      }
    }
  }
  return result;
};

export default queryData;

// Local Variables: ***
// js-indent-level: 2 ***
// indent-tabs-mode: nil ***
// End: ***
