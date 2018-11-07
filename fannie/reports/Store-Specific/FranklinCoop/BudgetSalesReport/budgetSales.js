
var budgetSales = (function ($) {

    var mod = {};

    var round = function(val) {
        return Math.round(val * 100) / 100;
    };

    mod.totals = function(totalCol) {
    	var dailyLabels = ["Budget","CY","PY"];
    	var totals = totalsChart;
    	console.log(totalsChart);
    	var daily = [];
    	var xLabels = totalsChart[0];
    	var borderDash = [[10,5],[],[2,1]];
    	for (var i=1; i<totalCol; i++) {
        	var line = totalsChart[i];
        	console.log(line);
        	//xLabels = line[0];
        	daily.push(line);
        }
        //var yData
        $('#reportTable1').after('<div class="col-sm-6 col-sm-offset-3"><canvas id="dailyCanvas"></canvas></div>');
    	CoreChart.lineChart('dailyCanvas', xLabels, daily, dailyLabels, borderDash);
    };

    mod.chartAll = function(totalCol) {
    	var dailyLabels = ["Budget","This Year","Last Year"];
    	//console.log(deptCharts);
    	for (var i = deptCharts.length - 1; i >= 0; i--) {
    		var chart = deptCharts[i];
    		console.log(chart);
    		var daily = [];
    		var xLabels = chart[0];
    		var borderDash = [[10,5],[],[2,1]];
    		for (var j=1; j<totalCol; j++) {
        		var line = chart[j];
        		console.log(line);
        		//xLabels = line[0];
        		daily.push(line);
        	}
        	//var yData
        	console.log(deptCanvases[i-1]);
        	var canvas = deptCanvases[i-1];
        	var printUnder = canvasPos[i];
        	$(`#reportTable${printUnder}`).after(`<div class="col-sm-6 col-sm-offset-3"><canvas id="${canvas}"></canvas></div>`);
    		CoreChart.lineChart(`${canvas}`, xLabels, daily, dailyLabels, borderDash);
    	}
    }


    return mod;

}(jQuery));

