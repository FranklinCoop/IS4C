
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
    	var line1 = [];
    	var line2 = [];
    	var line3 = [];
    	for (var i=1; i<totalCol; i++) {
        	var line = totalsChart[i];
        	console.log(line);
        	//xLabels = line[0];
        	daily.push(line);
        }
        //var yData
        $('#reportTable1').after('<div class="col-sm-6 col-sm-offset-3"><canvas id="dailyCanvas"></canvas></div>');
    	CoreChart.lineChart('dailyCanvas', xLabels, daily, dailyLabels);
    };

    mod.chartAll = function(totalCol) {
    	var dailyLabels = ["Budget","CY","PY"];
    	console.log(deptCharts);
    	for (var i = deptCharts.length - 1; i >= 0; i--) {
    		var chart = deptCharts[i];
    		console.log(chart);
    		var daily = [];
    		var xLabels = chart[0];
    		var line1 = [];
    		var line2 = [];
    		var line3 = [];
    		for (var j=1; j<totalCol; j++) {
        		var line = chart[j];
        		console.log(line);
        		//xLabels = line[0];
        		daily.push(line);
        	}
        	//var yData
        	$(`#reportTable${i+2}`).after(`<div class="col-sm-6 col-sm-offset-3"><canvas id="canvas${i}"></canvas></div>`);
    		CoreChart.lineChart(`canvas${i}`, xLabels, daily, dailyLabels);

    	}
    }

    return mod;

}(jQuery));

