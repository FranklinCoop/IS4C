
var priceCheckTablet = (function ($) {

    var mod = {};

    mod.showDefault = function() {
        $('#pc-results').html('<div class="alert alert-info h1">Please Scan Item</div>');
    };

    var timeout;

    mod.search = function() {
        $.ajax({
            url: 'PriceCheckTabletPage.php',
            data: $('#pc-upc').serialize()
        }).done(function (resp) {
            $('#pc-results').html(resp);
        }).always(function() {
            $('#pc-upc').val('');
            $('#pc-upc').focus();
            if (timeout) {
                clearTimeout(timeout);
            }
            timeout = setTimeout(mod.showDefault, 15000);
        });
    };

    return mod;

}(jQuery));
// this will return the focus to the input for any keyboard even so users can't unselect the field.
document.onkeydown = function(evt) {
    $('#pc-upc').focus();    
};
