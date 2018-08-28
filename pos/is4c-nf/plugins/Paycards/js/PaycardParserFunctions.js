    var formSubmitted = false;
    var urlStem = '../';
    setUrlStem = function(stem) {
        urlStem = '<?php echo $this->page_url; ?>';
    };
    var url = '';
    function setUrl(newURL) {
        url = newURL;
    }
/*var old_init = init;
init = function() {
  old_init.apply(this, arguments);
  doSomethingHereToo();
};*/

        function runParser(input_str,rel_prefix){
            //$('#reginput').val(input_str + '');
            window.location = url+input_str;
            CORE_JS_PREFIX = rel_prefix;
            $.ajax({
                url: CORE_JS_PREFIX+'ajax/AjaxParser.php',
                type: 'GET',
                data: "input="+input_str,
                dataType: "json",
                cache: false
            }).fail(parserError);
            return
        }
function parserError(xhr, statusText, err)
{
    errorLog.show(xhr, statusText, err);
}
        function submitWrapper(){
            var str = $('#reginput').val();
            $('#reginput').val('');
            runParser(str,urlStem);
            window.location = '<?php echo $url; ?>'+input_str;
            return false;
        }

        function scalePollSuccess(data){
    if (data){
        if (data.scale){
            $('#scaleBottom').html(data.scale); 
        }

        if (false){
            // data from the cc terminal
            // run directly; don't include user input
            if (typeof runParser === 'function')
                runParser(encodeURI(data.scans), SCALE_REL_PRE);
        }
        else if ($('#reginput').length !== 0 && data.scans){
            // barcode scan input
            var v = $('#reginput').val();
            var url = document.URL;
            data.scans += ''; // convert to string
            // only add prefix when working on the main page
            // other pages that use scans (e.g., barcode as password)
            // may not be expecting this behavior
            // For efficiency, scale weight response include a UPC if there
            // is a pending item waiting for a weight. In this case the prefix
            // is not added. Filtering out scans while the scale is waiting
            // for a weight uses the prefix, so once the scale is ready
            // a UPC has to go through w/o prefix
            if (!data.scans && url.substring(url.length - 8) === 'pos2.php' && data.scans.substring(0, 3) !== 'OXA') {
                data.scans = '0XA' + data.scans;
            }
            // pos2 parseWrapper is adding current input
            parseWrapper(data.scans);
            //return; // why is this here? scale needs to keep polling...
        }
    }
    rePoll();
}