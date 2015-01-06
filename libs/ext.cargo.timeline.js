
// Do some necessary initializion - these lines are copied from
// SimileTimeline's timeline-api.js, which is otherwise unneeded.
var Timeline = new Object();
Timeline.Platform = new Object();
Timeline.urlPrefix = mw.config.get( 'wgScriptPath') + "/extensions/Cargo/libs/SimileTimeline/";

$(document).ready(function() {

	$('.cargoTimeline').each( function() {
		var dataURL = decodeURI( $(this).attr('dataurl') );
		var eventSource = new Timeline.DefaultEventSource();
		var curElement = jQuery(this)[0];
		Timeline.loadJSON( dataURL, function(json, url) {
			eventSource.loadJSON(json, url);

			// Find the median date - this will be the center of
			// the timeline.
			var events = json['events'];
			var numEvents = events.length;
			if ( numEvents == 0 ) {
				return;
			}

			if ( numEvents % 2 == 1 ) {
				medianEventIndex = ( numEvents - 1 ) / 2;
			} else {
				medianEventIndex = ( numEvents - 2 ) / 2;
			}
			var medianDate = events[medianEventIndex]['start'];

			// Now set the timeline bands - there will usually
			// be two, one for the real display and a
			// small lower one for navigation.
			// The time period for the upper band (days, months,
			// etc.) is based on the "density" of the points,
			// which is calculated simply by dividing the number
			// of events by the time distance between the earliest
			// and latest dates.
			// The time period for the lower, navigation band is
			// based on the time distance alone.
			// If the two bands end up having the same time
			// period, the lower band does not get displayed.
			// It's not a perfect system, and the numbers used to
			// decide the time periods are rather arbitrary,
			// but in practice it seems to work out fairly well.
			var earliestDateStr = events[0]['start'];
			var latestDateStr = events[numEvents - 1]['start'];
			var earliestDate = Date.parse( earliestDateStr );
			var latestDate = Date.parse( latestDateStr );
			var daysDifference = ( latestDate - earliestDate ) / ( 1000 * 60 * 60 * 24 );
			var eventsPerDay = numEvents / daysDifference;

			var bandType1 = Timeline.DateTime.DAY;
			if ( eventsPerDay > .2 ) {
				// Keep the default.
			} else if ( eventsPerDay > .03 ) {
				bandType1 = Timeline.DateTime.WEEK;
			} else if ( eventsPerDay > .0075 ) {
				bandType1 = Timeline.DateTime.MONTH;
			} else {
				bandType1 = Timeline.DateTime.YEAR;
			}

			var bandType2 = Timeline.DateTime.DAY;
			if ( daysDifference <= 14 ) {
				// Keep the default.
			} else if ( daysDifference <= 50 ) {
				bandType2 = Timeline.DateTime.WEEK;
			} else if ( daysDifference <= 200 ) {
				bandType2 = Timeline.DateTime.MONTH;
			} else if ( daysDifference <= 2400 ) {
				bandType2 = Timeline.DateTime.YEAR;
			} else {
				bandType2 = Timeline.DateTime.DECADE;
			}

			var bandWidth1 = "100%";
			if ( bandType1 != bandType2 ) {
				bandWidth1 = "80%";
			}

			var bandInfos = [
				Timeline.createBandInfo({
					eventSource: eventSource,
					width: bandWidth1,
					date: medianDate,
					intervalUnit: bandType1,
					intervalPixels: 100
				})
			];

			if ( bandType1 != bandType2 ) {
				var band2 = Timeline.createBandInfo({
					// "showEventText" was replaced with
					// "overview" at some point - currently,
					// we are using the "showEventText"
					// version of the SimileTimeline code.
					overview: true,
					showEventText: false,
					eventSource: eventSource,
					width: "20%",
					date: medianDate,
					intervalUnit: bandType2,
					intervalPixels: 100
				})
				bandInfos.push(band2);
				bandInfos[1].syncWith = 0;
				bandInfos[1].highlight = true;
			}

			tl = Timeline.create(curElement, bandInfos);
		});

	});

});
