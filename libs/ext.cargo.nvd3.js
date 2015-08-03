/**
 * Code for dealing with the NVD3 JavaScript library.
 *
 * @ingroup Cargo
 * @author Yaron Koren
 */
$(document).ready(function() {

	$('.cargoBarChart').each( function() {

		var dataURL = decodeURI( $(this).attr('dataurl') );
		var innerSVG = $(this).find('svg');

		d3.json( dataURL, function(data) {

			var maxLabelSize = 0;
			var numbersIncludeDecimalPoints = false;
			for ( var i in data ) {
				for ( var j in data[i]['values'] ) {
					var curLabel = data[i]['values'][j]['label'];
					maxLabelSize = Math.max( maxLabelSize, curLabel.length );
					if ( !numbersIncludeDecimalPoints ) {
						var curValue = data[i]['values'][j]['value'];
						if ( curValue.toString().indexOf( '.' ) >= 0 ) {
							numbersIncludeDecimalPoints = true;
						}
					}
				}
			}
			var labelsWidth = Math.round( ( maxLabelSize + 1) * 7 );

			nv.addGraph(function() {
				if ( innerSVG.height() == 1 ) {
					var numLabels = data.length * data[0]['values'].length;
					var graphHeight = ( numLabels + 2 ) * 22;
					innerSVG.height( graphHeight );
				}

				var chart = nv.models.multiBarHorizontalChart()
					.x(function(d) { return d.label })
					.y(function(d) { return d.value })
					.margin({top: 0, right: 0, bottom: 0, left: labelsWidth })
					.showValues(true)           //Show bar value next to each bar.
					.tooltips(false)             //Show tooltips on hover.
					.duration(350)
					.showControls(false);        //Allow user to switch between "Grouped" and "Stacked" mode.

				if ( !numbersIncludeDecimalPoints ) {
					// These are all integers - don't
					// show decimal points in the chart.
					chart.yAxis.tickFormat(d3.format(',f'));
					chart.valueFormat(d3.format('d'));
				}

				d3.selectAll(innerSVG)
					.datum(data)
					.call(chart);

				nv.utils.windowResize(chart.update);

				return chart;
			});
		});

	});

});
