var filters = {
    owner: "",
    store: "",
    name: "",
    date: "",
};

function saveNotes(value,t_id) {
    var elem = $(this);
    var orig = this.defaultValue;
    $.ajax({url:'OverShortSettlementPage.php',
        cache: false,
        type: 'post',
        dataType: 'json',
        data: 'id='+t_id+'&notes='+value
    }).done(function(data){
        showBootstrapPopover(elem, orig, data.msg);
    });
}

function saveTotal(value,t_id) {
    var elem = $(this);
    var orig = this.defaultValue;
    $.ajax({url:'OverShortSettlementPage.php',
        cache: false,
        type: 'post',
        dataType: 'json',
        data: 'id='+t_id+'&total='+value
    }).done(function(data){
        var secID = data.secID;
        var grandID = data.grandTotalID;
        var diffID = data.diffID;
        var secTotal = data.secTotal;

        $("#diff"+t_id).attr("data-value",data.diff);
        $("#count"+t_id).attr("data-value",value);
        $("#diff"+t_id).empty().append(data.diff);

        $("#total"+secID).attr("data-value",data.secTotal);
        $("#diff"+secID).attr("data-value",data.secDiff);
        $("#diff"+secID).empty().append(data.secDiff);

        
        $("#total"+grandID).attr("data-value",data.grandTotal);
        $("#total"+grandID).empty().append(data.grandTotal);


        showBootstrapPopover(elem, orig, data.msg);
    });
}

function saveValue(value,t_id){
    var elem = $(this);
    var orig = this.defaultValue;
    $.ajax({url:'OverShortSettlementPage.php',
        cache: false,
        type: 'post',
        dataType: 'json',
        data: 'id='+t_id+'&value='+value
    }).done(function(data){
        var secID = data.secID;
        var grandID = data.grandTotalID;
        var diffID = data.diffID;
        var secTotal = data.secTotal;

        $("#diff"+t_id).attr("data-value",data.diff);
        $("#count"+t_id).attr("data-value",value);
        $("#diff"+t_id).empty().append(data.diff);

        $("#total"+secID).attr("data-value",data.secTotal);
        $("#diff"+secID).attr("data-value",data.secDiff);
        $("#total"+diffID).attr("data-value",data.grandDiff);
        $("#total"+secID).empty().append(data.secTotal);
        $("#diff"+secID).empty().append(data.secDiff);
        $("#total"+diffID).empty().append(data.grandDiff);

        
        $("#total"+grandID).attr("data-value",data.grandTotal);
        $("#total"+grandID).empty().append(data.grandTotal);


        showBootstrapPopover(elem, orig, data.msg);
    });
}

function reCalc()
{
    console.log('RECALC');
    var setdate = $('#date').val();
    var store = $('#storeID').val();
    var action = $('#recalc').val();
    var data = 'recalc='+action+'&date='+setdate+'&store='+store;
    $('#loading-bar').show();
    $('#displayarea').html('');
    $.ajax({
        url: 'OverShortSettlementPage.php',
        type: 'post',
        dataType: 'json',
        data: data
    }).done(function(data) {
        if(data.msg) {
            showBootstrapPopover(elem, orig, data.msg);
        } else {
            for(var i = 0; i < data.length; i++) {
                var obj = data[i];

                var lineNo = obj.lineNo;
                $("#diff"+lineNo).attr("data-value",obj.diff);
                $("#count"+lineNo).attr("obj-value",value);
                $("#total"+lineNo).empty().append(obj.total);

                //console.log(obj.id);
                showBootstrapPopover(elem, orig, obj.msg);
            }
        }

        
    });
}

function selectDay() {
    var setdate = $('#date').val();
    var store = $('#storeID').val();
    var data = 'date='+setdate+'&store='+store;
    $('#loading-bar').show();
    $.ajax({
        url: 'OverShortSettlementPage.php',
        type: 'post',
        data: data
    }).done(function(resp) {
        $('#loading-bar').hide();
        $('#displayarea').html(resp);
    });
}