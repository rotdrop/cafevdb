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

  const Ajax = function() {};

  Ajax.httpStatus = {
    '200': t(CAFEVDB.appName, 'OK'),
    '201': t(CAFEVDB.appName, 'Created'),
    '202': t(CAFEVDB.appName, 'Accepted'),
    '203': t(CAFEVDB.appName, 'Non-Authoritative Information'),
    '204': t(CAFEVDB.appName, 'No Content'),
    '205': t(CAFEVDB.appName, 'Reset Content'),
    '206': t(CAFEVDB.appName, 'Partial Content'),
    '207': t(CAFEVDB.appName, 'Multi-Status (WebDAV)'),
    '208': t(CAFEVDB.appName, 'Already Reported (WebDAV)'),
    '226': t(CAFEVDB.appName, 'IM Used'),
    '300': t(CAFEVDB.appName, 'Multiple Choices'),
    '301': t(CAFEVDB.appName, 'Moved Permanently'),
    '302': t(CAFEVDB.appName, 'Found'),
    '303': t(CAFEVDB.appName, 'See Other'),
    '304': t(CAFEVDB.appName, 'Not Modified'),
    '305': t(CAFEVDB.appName, 'Use Proxy'),
    '306': t(CAFEVDB.appName, '(Unused)'),
    '307': t(CAFEVDB.appName, 'Temporary Redirect'),
    '308': t(CAFEVDB.appName, 'Permanent Redirect (experimental)'),
    '400': t(CAFEVDB.appName, 'Bad Request'),
    '401': t(CAFEVDB.appName, 'Unauthorized'),
    '402': t(CAFEVDB.appName, 'Payment Required'),
    '403': t(CAFEVDB.appName, 'Forbidden'),
    '404': t(CAFEVDB.appName, 'Not Found'),
    '405': t(CAFEVDB.appName, 'Method Not Allowed'),
    '406': t(CAFEVDB.appName, 'Not Acceptable'),
    '407': t(CAFEVDB.appName, 'Proxy Authentication Required'),
    '408': t(CAFEVDB.appName, 'Request Timeout'),
    '409': t(CAFEVDB.appName, 'Conflict'),
    '410': t(CAFEVDB.appName, 'Gone'),
    '411': t(CAFEVDB.appName, 'Length Required'),
    '412': t(CAFEVDB.appName, 'Precondition Failed'),
    '413': t(CAFEVDB.appName, 'Request Entity Too Large'),
    '414': t(CAFEVDB.appName, 'Request-URI Too Long'),
    '415': t(CAFEVDB.appName, 'Unsupported Media Type'),
    '416': t(CAFEVDB.appName, 'Requested Range Not Satisfiable'),
    '417': t(CAFEVDB.appName, 'Expectation Failed'),
    '418': t(CAFEVDB.appName, 'I\'m a teapot (RFC 2324)'),
    '420': t(CAFEVDB.appName, 'Enhance Your Calm (Twitter)'),
    '422': t(CAFEVDB.appName, 'Unprocessable Entity (WebDAV)'),
    '423': t(CAFEVDB.appName, 'Locked (WebDAV)'),
    '424': t(CAFEVDB.appName, 'Failed Dependency (WebDAV)'),
    '425': t(CAFEVDB.appName, 'Reserved for WebDAV'),
    '426': t(CAFEVDB.appName, 'Upgrade Required'),
    '428': t(CAFEVDB.appName, 'Precondition Required'),
    '429': t(CAFEVDB.appName, 'Too Many Requests'),
    '431': t(CAFEVDB.appName, 'Request Header Fields Too Large'),
    '444': t(CAFEVDB.appName, 'No Response (Nginx)'),
    '449': t(CAFEVDB.appName, 'Retry With (Microsoft)'),
    '450': t(CAFEVDB.appName, 'Blocked by Windows Parental Controls (Microsoft)'),
    '451': t(CAFEVDB.appName, 'Unavailable For Legal Reasons'),
    '499': t(CAFEVDB.appName, 'Client Closed Request (Nginx)'),
    '500': t(CAFEVDB.appName, 'Internal Server Error'),
    '501': t(CAFEVDB.appName, 'Not Implemented'),
    '502': t(CAFEVDB.appName, 'Bad Gateway'),
    '503': t(CAFEVDB.appName, 'Service Unavailable'),
    '504': t(CAFEVDB.appName, 'Gateway Timeout'),
    '505': t(CAFEVDB.appName, 'HTTP Version Not Supported'),
    '506': t(CAFEVDB.appName, 'Variant Also Negotiates (Experimental)'),
    '507': t(CAFEVDB.appName, 'Insufficient Storage (WebDAV)'),
    '508': t(CAFEVDB.appName, 'Loop Detected (WebDAV)'),
    '509': t(CAFEVDB.appName, 'Bandwidth Limit Exceeded (Apache)'),
    '510': t(CAFEVDB.appName, 'Not Extended'),
    '511': t(CAFEVDB.appName, 'Network Authentication Required'),
    '598': t(CAFEVDB.appName, 'Network read timeout error'),
    '599': t(CAFEVDB.appName, 'Network connect timeout error'),

    // Seemingly Nextcloud always ever only returns one of these:
    'OK': 200,
    'BAD_REQUEST': 400,
    'UNAUTHORIZED': 401,
    'NOT_FOUND': 404,
    'CONFLICT': 409,
    'INTERNAL_SERVER_ERROR': 500
  };

  /**Generate some diagnostic output, mostly needed during application
   * development. This is intended to be called from the fail()
   * callback.
   */
  Ajax.handleError = function(xhr, textStatus, errorThrown, errorCB) {

    if (typeof errorCB == 'undefined') {
      errorCB = function () {}
    }

    const failData = Ajax.failData(xhr, textStatus, errorThrown);
    console.debug("AJAX failure data", failData);

    switch (textStatus) {
    case 'notmodified':
    case 'nocontent':
    case 'error':
    case 'timeout':
    case 'abort':
    case 'parsererror':
    case 'success': // this should not happen here
    default:
    }

    const caption = t(CAFEVDB.appName, 'Error');
    var info = '<span class="http-status error">' + Ajax.httpStatus[xhr.status] + '</span>';
    //console.info(xhr.status, info, errorThrown, textStatus);

    var autoReport = '<a href="mailto:'
          + CAFEVDB.adminEmail
          + '?subject=' + '[CAFEVDB Error] Error Feedback'
          + '&body=' + encodeURIComponent(
	    'JavaScript User Agent:'
              + "\n"
              + navigator.userAgent
              + "\n"
              + "\n"
              + 'PHP User Agent:'
              + "\n"
              + CAFEVDB.phpUserAgent
              + "\n"
              + "\n"
              + 'Error Code: ' +  Ajax.httpStatus[xhr.status]
              + "\n"
              + "\n"
	      + 'Error Data: ' + CAFEVDB.print_r(failData, true)
              + "\n")
          + '">'
          + CAFEVDB.adminName
          + '</a>';

    switch (xhr.status) {
    case Ajax.httpStatus.OK:
    case Ajax.httpStatus.BAD_REQUEST:
    case Ajax.httpStatus.NOT_FOUND:
    case Ajax.httpStatus.CONFLICT:
    case Ajax.httpStatus.INTERNAL_SERVER_ERROR:
      if (failData.error) {
	info += ': ' + '<span class="bold error toastify name">' + failData.error + '</span>';
      }
      if (failData.message) {
        info += '<div class="'+CAFEVDB.appName+' error toastify">' + failData.message + '</div>';
      }
      info += '<div class="error toastify feedback-link">'
            + t(CAFEVDB.appName, 'Feedback email: {AutoReport}', { AutoReport: autoReport }, -1, { escape: false })
            + '</div>';
      autoReport = '';
      var exceptionData = failData;
      if (exceptionData.exception !== undefined) {
        info += '<div class="exception error name"><pre>'+exceptionData.exception+'</pre></div>'
	  + '<div class="exception error trace"><pre>'+exceptionData.trace+'</pre></div>';
	while ((exceptionData = exceptionData.previous) != null) {
	  info += '<div class="bold error toastify">' + exceptionData.message + '</div>';
          info += '<div class="exception error name"><pre>'+exceptionData.exception+'</pre></div>'
	  + '<div class="exception error trace"><pre>'+exceptionData.trace+'</pre></div>';
	}
      }
      if (failData.info) {
        info += '<div class="'+CAFEVDB.appName+' error-page">'+failData.info+'</div>';
      }
      break;
    case Ajax.httpStatus.UNAUTHORIZED:
      // no point in continuing, direct the user to the login page
      errorCB = function() {
        if(OC.webroot !== '') {
          window.location.replace(OC.webroot);
        } else {
          window.location.replace('/');
        }
      };

      var generalHint = t(CAFEVDB.appName, 'Something went wrong.');
      generalHint += '<br/>'
                   + t(CAFEVDB.appName, 'If it should be the case that you are already '
                                + 'logged in for a long time without interacting '
                                + 'with the web-app, then the reason for this '
                                + 'error is probably a simple timeout.');
      generalHint += '<br/>'
                   + t(CAFEVDB.appName, 'I any case it may help to logoff and logon again, as a '
                                + 'temporary work-around. You will be redirected to the '
                                + 'log-in page when you close this window.');
      info += '<div class="error general">'+generalHint+'</div>';
      // info += '<div class="error toastify feedback-link">'
      //       + t(CAFEVDB.appName, 'Feedback email: {AutoReport}', { AutoReport: autoReport }, -1, { escape: false })
      //       + '</div>';
      break;
    }

    //console.info(info);
    CAFEVDB.Dialogs.alert(
      info, caption, function() { errorCB(failData); }, true, true);
    return failData;
  };

  /**Generate some diagnostic output, mostly needed during
   * application development. This is intended to be called from the
   * done() callback after a successful AJAX call.
   *
   * @param data The data passed to the callback to $.post()
   *
   * @param required List of required fields in data.data.
   *
   */
  Ajax.validateResponse = function(data, required, errorCB)
  {
    if (typeof data.data != 'undefined' && typeof data.data.status != 'undefined') {
      console.error('********** Success handler called as error handler ************');
      if (data.data.status != 'success') {
        Ajax.handleError(null, data, null);
        return false;
      } else {
        data = data.data;
      }
    }
    if (typeof errorCB == 'undefined') {
      errorCB = function() {};
    }
    // error handling
    if (typeof data == 'undefined' || !data) {
      CAFEVDB.Dialogs.alert(t(CAFEVDB.appName, 'Unrecoverable unknown internal error, '+
                              'no further information available, sorry.'),
			    t(CAFEVDB.appName, 'Internal Error'), errorCB, true);
      return false;
    }
    var missing = '';
    var idx;
    for (idx = 0; idx < required.length; ++idx) {
      if (typeof data[required[idx]] == 'undefined') {
        missing += t(CAFEVDB.appName, 'Field {RequiredField} not present in AJAX response.',
                     { RequiredField: required[idx] })+"<br>";
      }
    }
    if (missing.length > 0) {
      var info = '';
      if (typeof data.message != 'undefined') {
	info += data.message;
      }
      if (missing.length > 0) {
        info += t(CAFEVDB.appName, 'Missing data');
      }
      // Add missing fields only if no exception or setup-error was
      // caught as in this case no regular data-fields have been
      // constructed
      info += '<div class="missing error">'+missing+'</div>';

      // Display additional debug info if any
      CAFEVDB.debugPopup(data);

      var caption = data.caption;
      if (typeof caption == 'undefined' || caption == '') {
        caption = t(CAFEVDB.appName, 'Error');
        data.caption = caption;
      }
      CAFEVDB.Dialogs.alert(info, caption, errorCB, true, true);
      return false;
    }
    return true;
  };

  /**Fetch data from an error response.
   *
   * @param xhr jqXHR, see fail() method of jQuery ajax.
   *
   * @param status from jQuery, see fail() method of jQuery ajax.
   *
   * @param errorThrown, see fail() method of jQuery ajax.
   */
  Ajax.failData = function(xhr, status, errorThrown) {
    const ct = xhr.getResponseHeader("content-type") || "";
    var data = {
      'error': errorThrown,
      'status': status,
      'message': t(CAFEVDB.appName, 'Unknown JSON error response to AJAX call: {status} / {error}')
    };
    if (ct.indexOf('html') > -1) {
      console.debug('html response', xhr, status, errorThrown);
      console.debug(xhr.status);
      data.message = t(CAFEVDB.appName, 'HTTP error response to AJAX call: {code} / {error}',
                       {'code': xhr.status, 'error': errorThrown});
      data.info = $(xhr.responseText).find('main').html();
    } else if (ct.indexOf('json') > -1) {
      const response = JSON.parse(xhr.responseText);
      //console.info('XHR response text', xhr.responseText);
      //console.log('JSON response', response);
      data = {...data, ...response };
    } else {
      console.log('unknown response');
    }
    //console.info(data);
    return data;
  };

  /**Generate some diagnostic output, mostly needed during application
   * development.
   *
   * @param xhr jqXHR, see fail() method of jQuery ajax.
   *
   * @param status from jQuery, see fail() method of jQuery ajax.
   *
   * @param errorThrown, see fail() method of jQuery ajax.
   */
  Ajax.failMessage = function(xhr, status, errorThrown) {
    return Ajax.failData(xhr, status, errorThrown).message;
  };


  CAFEVDB.Ajax = Ajax;
})(window, jQuery, CAFEVDB);

// Local Variables: ***
// js-indent-level: 2 ***
// End: ***
