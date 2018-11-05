
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

    	CoreChart.lineChart('dailyCanvas', xLabels, daily, dailyLabels);
    };

    return mod;

}(jQuery));

