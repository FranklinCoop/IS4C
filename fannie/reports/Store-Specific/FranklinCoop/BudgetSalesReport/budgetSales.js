
var budgetSales = (function ($) {

    var mod = {};

    var round = function(val) {
        return Math.round(val * 100) / 100;
    };

    mod.chartBaskets = function() {
        var dailyLabels = ["This Year","Last Year"];
        //console.log(totalsChart);
        var custCountData = [];
        var basketData = [];
        var xLabels = custChart[0];
        var borderDash = [[],[2,1]];
        for (var i=1; i<3; i++) {
            var line = custChart[i];
            var line2 = basketChart[i];
            //console.log(line);
            custCountData.push(line);
            basketData.push(line2);
        }
        var title1 = {
                display: true,
                text: 'Customer Count',
                padding: 2,
                fontStyle: 'bold',
                fontSize: 14,
                fontColor: '#000000'
            };
        var title2 = {
                display: true,
                text: 'Baskets',
                padding: 2,
                fontStyle: 'bold',
                fontSize: 14,
                fontColor: '#000000'
            };
        //console.log(title);
        $('#dailyCanvas').after('<div class="col-md-6"><canvas id="custCanvas"></canvas></div>');
        CoreChart.fullLineChart('custCanvas', xLabels, custCountData, dailyLabels, borderDash, title1);
        $('#dailyCanvas').after('<div class="col-md-6"><canvas id="basketCanvas"></canvas></div>');
        CoreChart.fullLineChart('basketCanvas', xLabels, basketData, dailyLabels, borderDash, title2);
    }

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
        		padding: 2,
                fontStyle: 'bold',
                fontSize: 14,
                fontColor: '#000000'
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
        		padding: 2,
                fontStyle: 'bold',
                fontSize: 14,
                fontColor: '#000000'
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

