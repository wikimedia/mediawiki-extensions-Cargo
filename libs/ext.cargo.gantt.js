/*
 * Copied from file ext.flexdiagrams.gantt.js in the Flex Diagrams extension.
 */

$(document).ready(function() {

	$('.cargoGantt').each( function() {
        var dataURL = decodeURI( $(this).attr('dataurl') );
        gantt.config.date_format = "%Y-%m-%d %H:%i";
        gantt.config.readonly = true;
        gantt.init("ganttid");
        gantt.load(dataURL);

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

        gantt.attachEvent("onLoadEnd", function() {
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
            } else if ( numDays < 1080 ) {
                selectedZoom = 'months';
            }

            buttonSelect.selectItemByData(selectedZoom);
        });

	});

});


