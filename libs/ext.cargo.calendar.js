/* global moment */

$(document).ready(function() {
	// Page is now ready; initialize the calendar...
	$('.cargoCalendar').each( function() {
		var dataURL = decodeURI( $(this).attr('dataurl') );
		var startView = $(this).attr('startview');
		var startDate = moment( $(this).attr('startdate') );
		var calendarSettings = {
			events: dataURL,
			header: {
				left: 'today prev,next',
				center: 'title',
				right: 'month,agendaWeek,agendaDay,listWeek'
			},
			defaultView: startView,
			defaultDate: startDate,
			displayEventEnd: true,
			// Ideally, the translations should all move into
			// the Cargo extension, instead of staying in
			// FullCalendar, in order to support more languages -
			// but that's difficult, because FC's localization
			// also includes behavior, like which day a week
			// starts on. For now, it's much easier to just let
			// FC's locale file do all the work.
			locale: mw.config.get("wgUserLanguage"),
			// For backward compatibility, in case FC 2.9.1 is
			// being used.
			lang: mw.config.get("wgUserLanguage"),
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
	// Date Picker
	$( '.fc-left' ).each( function (index) {
		var dateInput = new mw.widgets.DateInputWidget();
		$label = $( '<p>' + mw.message( 'cargo-calendar-datepicker-label' ) + '</p>' );
		$(this).append($label, dateInput.$element);
		dateInput.on( 'change', function () {
			var date = dateInput.getValue();
			// Navigates only when a valid date is selected
			if(date) {
			var calendar = $('.cargoCalendar').eq(index);
			calendar.fullCalendar("gotoDate", date);
			}
		} );
	} );
});
