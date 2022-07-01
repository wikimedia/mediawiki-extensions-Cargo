/*
 * Copied from file ext.flexdiagrams.gantt.js in the Flex Diagrams extension.
 */

$(document).ready(function() {

	gantt.config.date_format = "%Y-%m-%d %H:%i";
	gantt.config.readonly = true;
	// Remove the 'add'/'+' column.
	for ( var i = gantt.config.columns.length - 1; i >= 0; i-- ) {
		if ( gantt.config.columns[i].name == 'add' ) {
			gantt.config.columns.splice( i, 1 );
		}
	}

	$('.cargoGantt').each( function() {
        gantt.init("ganttid");
	if ( $(this).attr('dataurl') !== undefined ) {
		var dataURL = decodeURI( $(this).attr('dataurl') );
		gantt.load(dataURL);
	} else {
		var dataFull = decodeURI( $(this).attr('datafull') );
		gantt.parse(dataFull);
	}
	// @todo - add support for values other than ''.
	if ( $(this).attr('data-columns') === '' ) {
		gantt.config.columns = [];
	}

        function setGanttZoom( evt ) {
            switch (evt.data) {
                case "hours":
                    gantt.config.scales = [
                        {unit: "day", step: 1, format: "%d %F"},
                        {unit: "hour", step: 1, format: "%h"}
                    ];
                    break;
                case "days":
                    gantt.config.scales = [
                        {unit: "day", step: 1, format: "%d %M"}
                    ];
                    break;
                case "months":
                    gantt.config.scales = [
                        {unit: "month", step: 1, format: "%M %Y"}
                    ];
                    break;
                case "years":
                    gantt.config.scales = [
                        {unit: "year", step: 1, format: "%Y"}
                    ];
                    break;
            }
            gantt.init('ganttid');
        }

        var zoomLevels = [ 'hours', 'days', 'months', 'years' ];
        var zoomLevelButtons = [];
        for ( var i = 0; i < zoomLevels.length; i++ ) {
            var zoomLevel = zoomLevels[i];
            zoomLevelButtons.push( new OO.ui.ButtonOptionWidget( {
                data: zoomLevel,
                label: mw.message( 'cargo-gantt-' + zoomLevel ).text(),
                selected: ( zoomLevel == 'days' )
            }) );
        }

        var buttonSelect = new OO.ui.ButtonSelectWidget( {
            items: zoomLevelButtons
        } );

        buttonSelect.on('select', setGanttZoom );

        var zoomLayout = new OO.ui.FieldLayout( buttonSelect, {
            align: 'top',
            label: mw.message( 'cargo-gantt-zoomlevel' ).text()
        } );

        $('#ganttid').after( '<div id="ganttZoomInput"></div><br style="clear: both;" />' );
        $('#ganttZoomInput').append( zoomLayout.$element );

	// @hack - we need to use the "onGanttRender" event in order to set
	// the zoom correctly, whether the Gantt chart is loaded or passed.
	// (For loading, there's the "onLoadEnd" event, but there doesn't seem
	// to be a usable equvalent for parsing.) Unfortunately, "onGanttRender"
	// is called a lot - not just when the chart is first rendered. So, in
	// order to make sure the zoom is only set once, we add a "zoom set"
	// attribute once it's set, then escape every time afterwards.
	// There's probably a better way to do this.
        gantt.attachEvent("onGanttRender", function() {
		if ( $(this).attr('data-zoom-set') !== undefined ) {
			return;
		}

            var earliestDate = gantt.getSubtaskDates().start_date;
            var latestDate = gantt.getSubtaskDates().end_date;
            // Duration in milliseconds.
            var duration = latestDate - earliestDate;
            var numDays = Math.floor(duration / 86400000);
            var selectedZoom = 'years';
            if ( numDays < 4 ) {
                selectedZoom = 'hours';
            } else if ( numDays < 90 ) {
                return;
            } else if ( numDays < 730 ) {
                selectedZoom = 'months';
            }

            buttonSelect.selectItemByData(selectedZoom);
		$(this).attr('data-zoom-set', true);
        });

	});

});
