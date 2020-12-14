/* Orchestra member, musicion and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine
 * @copyright 2011-2016, 2020 Claus-Justus Heine <himself@claus-justus-heine.de>
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

var CAFEVDB = CAFEVDB || {};

(function(window, $, CAFEVDB, undefined) {
  'use strict';

  CAFEVDB.print_r = function(array, return_val, max_depth) {
    if (max_depth == undefined) {
      max_depth = 5;
    }
    // discuss at: http://phpjs.org/functions/print_r/
    // original by: Michael White (http://getsprink.com)
    // improved by: Ben Bryan
    // improved by: Brett Zamir (http://brett-zamir.me)
    // improved by: Kevin van Zonneveld (http://kevin.vanzonneveld.net)
    // input by: Brett Zamir (http://brett-zamir.me)
    // depends on: echo
    // example 1: print_r(1, true);
    // returns 1: 1
    var output = '',
        pad_char = ' ',
        pad_val = 4,
        d = window.document,
        getFuncName = function (fn) {
          var name = (/\W*function\s+([\w\$]+)\s*\(/)
                     .exec(fn);
          if (!name) {
            return '(Anonymous)';
          }
          return name[1];
        };
    var repeat_char = function (len, pad_char) {
      var str = '';
      for (var i = 0; i < len; i++) {
        str += pad_char;
      }
      return str;
    };
    var formatArray = function (obj, cur_depth, pad_val, pad_char, max_depth) {
      if (cur_depth > 0) {
        cur_depth++;
      }
      var base_pad = repeat_char(pad_val * cur_depth, pad_char);
      var thick_pad = repeat_char(pad_val * (cur_depth + 1), pad_char);
      var str = '';
      if (typeof obj === 'object' && obj !== null && obj.constructor && getFuncName(obj.constructor) !==
          'PHPJS_Resource') {
	const type = Object.prototype.toString.call(obj);
	if (type === '[object Array]') {
          str += 'Array\n';
	} else /* if (type == '[object Object]') */ {
          str += 'Object\n';
	}
        str += base_pad + '(\n';
        for (var key in obj) {
	  const fieldType = Object.prototype.toString.call(obj[key]);
	  if (cur_depth > max_depth) {
            str += thick_pad + '[' + key + '] => ' + obj[key] + '\n';
	  } else if (fieldType === '[object Array]') {
            str += thick_pad + '[' + key + '] => ' + formatArray(obj[key], cur_depth + 1, pad_val, pad_char, max_depth);
	  } else if (fieldType === '[object Object]') {
            str += thick_pad + '[' + key + '] => ' + formatArray(obj[key], cur_depth + 1, pad_val, pad_char, max_depth);
          } else {
            str += thick_pad + '[' + key + '] => ' + obj[key] + '\n';
          }
        }
        str += base_pad + ')\n';
      } else if (obj === null || obj === undefined) {
        str = '';
      } else {
        // for our "resource" class
        str = obj.toString();
      }
      return str;
    };
    output = formatArray(array, 0, pad_val, pad_char);
    if (return_val !== true) {
      if (d.body) {
        window.echo(output);
      } else {
        try {
          // We're in XUL, so appending as plain text won't work; trigger an error out of XUL
          d = XULDocument;
          window.echo('<pre xmlns="http://www.w3.org/1999/xhtml" style="white-space:pre;">' + output + '</pre>');
        } catch (e) {
          // Outputting as plain text may work in some plain XML
          window.echo(output);
        }
      }
      return true;
    }
    return output;
  };

})(window, jQuery, CAFEVDB);

// Local Variables: ***
// js-indent-level: 2 ***
// End: ***
