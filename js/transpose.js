/**Orchestra member, musicion and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine
 * @copyright 2011-2013 Claus-Justus Heine <himself@claus-justus-heine.de>
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
    var PME = function() {};
    PME.transposeMainTable = function(selector) {
	var table = $(selector);
	var headerRow = table.find('thead tr');
	headerRow.detach();
	if (headerRow.length > 0) {
            headerRow.prependTo( table.find('tbody') );
	}
	var t = table.find('tbody').eq(0);
	var sortinfo  = t.find('tr.pme-sortinfo');
	var queryinfo = t.find('tr.pme-queryinfo');
	// These are huge cells spanning the entire table, move them on
	// top of the transposed table afterwards.
	sortinfo.detach();
	queryinfo.detach();
	var r = t.find('tr');
	var cols= r.length;
	var rows= r.eq(0).find('td,th').length;
	var cell, next, tem, i = 0;
	var tb= $('<tbody></tbody>');

	while(i<rows){
            cell= 0;
            tem= $('<tr></tr>');
            while(cell<cols){
		next= r.eq(cell++).find('td,th').eq(0);
		tem.append(next);
            }
            tb.append(tem);
            ++i;
	}
	table.find('tbody').remove();
	$(tb).appendTo(table);
	if (table.find('thead').length > 0) {
            $(table)
		.find('tbody tr:eq(0)')
		.detach()
		.appendTo( table.find('thead') )
		.children()
		.each(function(){
                    var tdclass = $(this).attr('class');
                    if (tdclass.length > 0) {
                        tdclass = ' class="'+tdclass+'"';
                    } else {
                        tdclass = "";
                    }
                    $(this).replaceWith('<th'+tdclass+' scope="col">'+$(this).html()+'</th>');
		});
	}
        queryinfo.prependTo(table.find('tbody'));
        sortinfo.prependTo(table.find('tbody'));

        if (true) {
	    $(table)
                .find('tbody tr th:first-child')
                .each(function(){
                    var thclass = $(this).attr('class');
                    if (thclass.length > 0) {
                        thclass = ' class="'+thclass+'"';
                    } else {
                        thclass = "";
                    }
		    $(this).replaceWith('<td'+thclass+' scope="row">'+$(this).html()+'</td>');
                });
        }
	table.show();
    };
    PME.maybeTranspose = function(transpose) {
	var pageitems;
	if (transpose) {
	    $('.tipsy').remove();
	    this.transposeMainTable('table.pme-main');
	    pageitems = t('cafevdb', '#columns');

            $('input[name="Transpose"]').val('transposed');
            $('#pme-transpose-up').removeClass('pme-untransposed').addClass('pme-transposed');
            $('#pme-transpose-down').removeClass('pme-untransposed').addClass('pme-transposed');
            $('#pme-transpose').removeClass('pme-untransposed').addClass('pme-transposed');
	} else {
	    $('.tipsy').remove();
	    this.transposeMainTable('table.pme-main');
	    pageitems = t('cafevdb', '#rows');

            $('input[name="Transpose"]').val('untransposed');
            $('#pme-transpose-up').removeClass('pme-transposed').addClass('pme-untransposed');
            $('#pme-transpose-down').removeClass('pme-transposed').addClass('pme-untransposed');
            $('#pme-transpose').removeClass('pme-transposed').addClass('pme-untransposed');
        }
	$('input.pme-pagerows').val(pageitems);

	$('input').tipsy({gravity:'w', fade:true});
	$('button').tipsy({gravity:'w', fade:true});
	$('input.cafevdb-control').tipsy({gravity:'nw', fade:true});
	$('#controls button').tipsy({gravity:'nw', fade:true});
	$('.pme-sort').tipsy({gravity: 'n', fade:true});
	$('.pme-email-check').tipsy({gravity: 'nw', fade:true});
	$('.pme-bulkcommit-check').tipsy({gravity: 'nw', fade:true});

	if (CAFEVDB.toolTips) {
	    $.fn.tipsy.enable();
	} else {
	    $.fn.tipsy.disable();
	}
    };
    CAFEVDB.PME = PME;
})(window, jQuery, CAFEVDB);

$(document).ready(function() {
    // Transpose or not: if there is a transpose button
    // #pme-transpose.pme-transposed, then we transpose the table, otherwise not.
    
    // Lookup how to do this properly
    if ($('input[name="InhibitInitialTranspose"]').val() != 'true' &&
        ($('input[name="Transpose"]').val() == 'transposed' ||
         $('#pme-transpose-up').hasClass('pme-transposed') ||
         $('#pme-transpose-down').hasClass('pme-transposed') ||
         $('#pme-transpose').hasClass('pme-transposed'))) {
	CAFEVDB.PME.maybeTranspose(true);
    } else {
        // Initially the tabel _is_ untransposed
	//CAFEVDB.PME.maybeTranspose(false); // needed?
    }
});
