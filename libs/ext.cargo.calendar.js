/* global moment */

$(document).ready(function() {

	// page is now ready, initialize the calendar...
	$('.cargoCalendar').each( function() {
		var dataURL = decodeURI( $(this).attr('dataurl') );
		var startView = $(this).attr('startview');
		var startDate = moment( $(this).attr('startdate') );
		var calendarSettings = {
			events: dataURL,
			header: {
				left: 'today prev,next',
				center: 'title',
				right: 'month,basicWeek,basicDay'
			},
			defaultView: startView,
			defaultDate: startDate,
			// Add event description to 'title' attribute, for
			// mouseover.
			eventMouseover: function(event, jsEvent, view) {
				if (view.name !== 'agendaDay') {
					// JS lacks an "HTML decode" function,
					// so we use this jQuery hack.
					// Copied from http://stackoverflow.com/a/10715834
					var decodedDescription = $('<div/>').html(event.description).text();
					$(jsEvent.target).attr('title', decodedDescription);
				}
			}
		};

		// Do some validation on the 'height' input.
		var height = $(this).attr('height');
		var numHeight = Number(height);
		if ( numHeight > 0 ) {
			calendarSettings.height = numHeight;
		} else if ( height == "auto" ) {
			calendarSettings.height = height;
		}

		$(this).fullCalendar( calendarSettings );
	});

});
