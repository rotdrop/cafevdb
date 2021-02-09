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
 * Decode an url-encoded query string.
 *
 * @param {String} str The query string.
 *
 * @returns {String} The decoded query string.
 */
const urlDecode = function(str) {
  // http://kevin.vanzonneveld.net
  // +   original by: Philip Peterson
  // +   improved by: Kevin van Zonneveld (http://kevin.vanzonneveld.net)
  // +      input by: AJ
  // +   improved by: Kevin van Zonneveld (http://kevin.vanzonneveld.net)
  // +   improved by: Brett Zamir (http://brett-zamir.me)
  // +      input by: travc
  // +      input by: Brett Zamir (http://brett-zamir.me)
  // +   bugfixed by: Kevin van Zonneveld (http://kevin.vanzonneveld.net)
  // +   improved by: Lars Fischer
  // +      input by: Ratheous
  // +   improved by: Orlando
  // +   reimplemented by: Brett Zamir (http://brett-zamir.me)
  // +      bugfixed by: Rob
  // +      input by: e-mike
  // +   improved by: Brett Zamir (http://brett-zamir.me)
  // +      input by: lovio
  // +   improved by: Brett Zamir (http://brett-zamir.me)
  // %        note 1: info on what encoding functions to use from: http://xkr.us/articles/javascript/encode-compare/
  // %        note 2: Please be aware that this function expects to decode from UTF-8 encoded strings, as found on
  // %        note 2: pages served as UTF-8
  // *     example 1: urldecode('Kevin+van+Zonneveld%21');
  // *     returns 1: 'Kevin van Zonneveld!'
  // *     example 2: urldecode('http%3A%2F%2Fkevin.vanzonneveld.net%2F');
  // *     returns 2: 'http://kevin.vanzonneveld.net/'
  // *     example 3: urldecode('http%3A%2F%2Fwww.google.nl%2Fsearch%3Fq%3Dphp.js%26ie%3Dutf-8%26oe%3Dutf-8%26aq%3Dt%26rls%3Dcom.ubuntu%3Aen-US%3Aunofficial%26client%3Dfirefox-a');
  // *     returns 3: 'http://www.google.nl/search?q=php.js&ie=utf-8&oe=utf-8&aq=t&rls=com.ubuntu:en-US:unofficial&client=firefox-a'
  // *     example 4: urldecode('%E5%A5%BD%3_4');
  // *     returns 4: '\u597d%3_4'
  return decodeURIComponent((str + '').replace(/%(?![\da-f]{2})/gi, function() {
    // PHP tolerates poorly formed escape sequences
    return '%25';
  }).replace(/\+/g, '%20'));
};

/**
 * Encode a query string.
 *
 * @param {String} str The query string.
 *
 * @returns {String} The encoded query string.
 */
const urlEncode = function(str) {
  // http://kevin.vanzonneveld.net
  // + original by: Philip Peterson
  // + improved by: Kevin van Zonneveld (http://kevin.vanzonneveld.net)
  // + input by: AJ
  // + improved by: Kevin van Zonneveld (http://kevin.vanzonneveld.net)
  // + improved by: Brett Zamir (http://brett-zamir.me)
  // + bugfixed by: Kevin van Zonneveld (http://kevin.vanzonneveld.net)
  // + input by: travc
  // + input by: Brett Zamir (http://brett-zamir.me)
  // + bugfixed by: Kevin van Zonneveld (http://kevin.vanzonneveld.net)
  // + improved by: Lars Fischer
  // + input by: Ratheous
  // + reimplemented by: Brett Zamir (http://brett-zamir.me)
  // + bugfixed by: Joris
  // + reimplemented by: Brett Zamir (http://brett-zamir.me)
  // % note 1: This reflects PHP 5.3/6.0+ behavior
  // % note 2: Please be aware that this function expects to encode into UTF-8 encoded strings, as found on
  // % note 2: pages served as UTF-8
  // * example 1: urlencode('Kevin van Zonneveld!');
  // * returns 1: 'Kevin+van+Zonneveld%21'
  // * example 2: urlencode('http://kevin.vanzonneveld.net/');
  // * returns 2: 'http%3A%2F%2Fkevin.vanzonneveld.net%2F'
  // * example 3: urlencode('http://www.google.nl/search?q=php.js&ie=utf-8&oe=utf-8&aq=t&rls=com.ubuntu:en-US:unofficial&client=firefox-a');
  // * returns 3: 'http%3A%2F%2Fwww.google.nl%2Fsearch%3Fq%3Dphp.js%26ie%3Dutf-8%26oe%3Dutf-8%26aq%3Dt%26rls%3Dcom.ubuntu%3Aen-US%3Aunofficial%26client%3Dfirefox-a'
  str = (str + '').toString();

  // Tilde should be allowed unescaped in future versions of PHP (as reflected below), but if you want to reflect current
  // PHP behavior, you would need to add ".replace(/~/g, '%7E');" to the following.
  return encodeURIComponent(str).replace(/!/g, '%21').replace(/'/g, '%27').replace(/\(/g, '%28')
    .replace(/\)/g, '%29').replace(/\*/g, '%2A').replace(/%20/g, '+');
};

export {
  urlEncode,
  urlDecode,
};

// Local Variables: ***
// js-indent-level: 2 ***
// indent-tabs-mode: nil ***
// End: ***
