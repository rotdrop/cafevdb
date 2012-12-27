function transposePmeMain(selector) {
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
}
