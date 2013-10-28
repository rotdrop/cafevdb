CAFEVDB.PME = CAFEVDB.PME || {};

(function(window, $, CAFEVDB, undefined) {
    'use strict';
    var PME = function() {};
    PME.prototype.transposeMainTable = function(selector) {
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
	if (false) {
            $(table)
		.find('tbody tr:eq(0)')
		.detach()
		.appendTo( table.find('thead') )
		.children()
		.each(function(){
                    $(this).replaceWith('<th scope="col">'+$(this).html()+'</th>');
		});
	} else {
            sortinfo.appendTo(table.find('thead'));
            queryinfo.prependTo(table.find('tbody'));
	}

	$(table)
            .find('tbody tr th:first-child')
            .each(function(){
		$(this).replaceWith('<td scope="row">'+$(this).html()+'</td>');
            });
	table.show();
    };
    PME.prototype.maybeTranspose = function(transpose) {
	var pageitems = t('cafevdb', '#rows');
	if (transpose) {
	    $('.tipsy').remove();
	    this.transposePmeMain('table.pme-main');
	    pageitems = t('cafevdb', '#columns');
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
    if(typeof $('#pme-transpose.pme-transposed') !== typeof undefined) {
	CAFEVDB.PME.maybeTranspose(true);
    } else {
	CAFEVDB.PME.maybeTranspose(false); // needed?
    }
});
