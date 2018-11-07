
var budgetSales = (function ($) {

    var mod = {};

    var round = function(val) {
        return Math.round(val * 100) / 100;
    };

    mod.totals = function(totalCol) {
    	var dailyLabels = ["Budget","CY","PY"];
    	var totals = totalsChart;
    	//console.log(totalsChart);
    	var daily = [];
    	var xLabels = totalsChart[0];
    	var borderDash = [[10,5],[],[2,1]];
    	for (var i=1; i<totalCol; i++) {
        	var line = totalsChart[i];
        	//console.log(line);
        	daily.push(line);
        }
        var title = {
        		display: true,
        		text: 'Store Totals',
        		padding: 2
        	};
        //console.log(title);
        $('#reportTable1').after('<div class="col-lg-6"><canvas id="dailyCanvas"></canvas></div>');
    	CoreChart.fullLineChart('dailyCanvas', xLabels, daily, dailyLabels, borderDash, title);
    };

    mod.chartAll = function(totalCol) {
    	var dailyLabels = ["Budget","This Year","Last Year"];
    	//console.log(deptCharts);
    	for (var i = deptCharts.length - 1; i >= 0; i--) {
    		var chart = deptCharts[i];
    		//console.log(chart);
    		var daily = [];
    		var xLabels = chart[0];
    		var borderDash = [[10,5],[],[2,1]];
    		for (var j=1; j<totalCol; j++) {
        		var line = chart[j];
        		//console.log(line);
        		daily.push(line);
        	}
        	var title = {
        		display: true,
        		text: chartTitles[i+1],
        		padding: 2
        	};
        	//console.log(deptCanvases[i-1]);
        	var canvas = deptCanvases[i-1];
        	var printUnder = canvasPos[i];
        	$(`#reportTable${printUnder}`).after(`<div class="col-lg-6"><canvas id="${canvas}"> </canvas></div>`);
    		CoreChart.fullLineChart(`${canvas}`, xLabels, daily, dailyLabels, borderDash,title);
    	}
    }


    return mod;

}(jQuery));

