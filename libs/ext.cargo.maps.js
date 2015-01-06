/**
 * ext.cargo.maps.js
 *
 * Defines the CargoMap JS "class", used for displaying maps.
 *
 * @author Yaron Koren
 *
 * Mostly copied from
 * https://github.com/yaronkoren/miga/blob/master/MigaDV.js
 * (also written by Yaron Koren)
 */

function CargoMap( allItemValues, divID, zoomLevel ) {
	this.allItemValues = allItemValues;
	this.divID = divID;
	this.zoomLevel = zoomLevel;

	// Calculate center, and bounds, of map
	var numItems = allItemValues.length;
	var totalLatitude = 0;
	var totalLongitude = 0;
	for ( i = 0; i < numItems; i++ ) {
		totalLatitude += allItemValues[i]['lat'];
		totalLongitude += allItemValues[i]['lon'];
	}
	this.averageLatitude = totalLatitude / numItems;
	this.averageLongitude = totalLongitude / numItems;

	var furthestDistanceEast = 0;
	var furthestDistanceWest = 0;
	var furthestDistanceNorth = 0;
	var furthestDistanceSouth = 0;
	for ( i = 0; i < numItems; i++ ) {
		var latitudeDiff = allItemValues[i]['lat'] - this.averageLatitude;
		var longitudeDiff = allItemValues[i]['lon'] - this.averageLongitude;
		if ( latitudeDiff > furthestDistanceNorth ) {
			furthestDistanceNorth = latitudeDiff;
		} else if ( latitudeDiff < furthestDistanceSouth ) {
			furthestDistanceSouth = latitudeDiff;
		}
		if ( longitudeDiff > furthestDistanceEast ) {
			furthestDistanceEast = longitudeDiff;
		} else if ( longitudeDiff < furthestDistanceWest ) {
			furthestDistanceWest = longitudeDiff;
		}
	}

	// In case there was only one point (or all points have the same
	// coordinates), add in some reasonable padding.
	if ( furthestDistanceNorth == 0 && furthestDistanceSouth == 0 && furthestDistanceEast == 0 && furthestDistanceWest == 0 ) {
		furthestDistanceNorth = 0.0015;
		furthestDistanceSouth = -0.0015;
		furthestDistanceEast = 0.0015;
		furthestDistanceWest = -0.0015;
	}
	this.northLatitude = this.averageLatitude + furthestDistanceNorth;
	this.southLatitude = this.averageLatitude + furthestDistanceSouth;
	this.eastLongitude = this.averageLongitude + furthestDistanceEast;
	this.westLongitude = this.averageLongitude + furthestDistanceWest;
}

CargoMap.createPopupHTMLForRow = function( row ) {
	var html = "<div style=\"min-width: 120px; min-height: 50px; padding-bottom: 30px;\"><h2>" + row['title'] + "</h2><p>";
	for ( var fieldName in row['otherValues'] ) {
		html += "<strong>" + fieldName + "</strong>: ";
		html += row['otherValues'][fieldName] + "<br />";
	}
	html += "</p></div>\n";
	return html;
}

CargoMap.prototype.display = function( mapService, doMarkerClustering ) {
	if ( mapService == 'Google Maps' ) {
		this.displayWithGoogleMaps( doMarkerClustering );
	} else { // default is OpenLayers
		this.displayWithOpenLayers();
	}
}

CargoMap.prototype.displayWithGoogleMaps = function( doMarkerClustering ) {
	var centerLatLng = new google.maps.LatLng( this.averageLatitude, this.averageLongitude );
	var northEastLatLng = new google.maps.LatLng( this.northLatitude, this.eastLongitude );
	var southWestLatLng = new google.maps.LatLng( this.southLatitude, this.westLongitude );
	var mapBounds = new google.maps.LatLngBounds( southWestLatLng, northEastLatLng );

	var mapOptions = {
		center: centerLatLng,
		mapTypeId: google.maps.MapTypeId.ROADMAP
	}
	if ( this.zoomLevel != null ) {
		mapOptions['zoom'] = parseInt( this.zoomLevel );
	}
	var map = new google.maps.Map(document.getElementById(this.divID), mapOptions);
	if ( this.zoomLevel == null ) {
		map.fitBounds( mapBounds );
	}

	var infoWindows = [];
	var numItems = this.allItemValues.length;
	for ( i = 0; i < numItems; i++ ) {
		infoWindows[i] = new google.maps.InfoWindow({
			content: CargoMap.createPopupHTMLForRow( this.allItemValues[i] )
		});
	}

	if ( doMarkerClustering ) {
		var markers = [];
	}
	for ( i = 0; i < numItems; i++ ) {
		var curItem = this.allItemValues[i];
		var curLatLng = new google.maps.LatLng( curItem['lat'], curItem['lon'] );
		var markerOptions = {
			position: curLatLng,
			map: map,
			title: curItem['name'],
			itemNum: i // Cargo-specific
		};
		if ( curItem.hasOwnProperty('icon') ) {
			markerOptions.icon = curItem.icon;
		}
		var marker = new google.maps.Marker( markerOptions );
		if ( doMarkerClustering ) {
			markers.push( marker );
		}

		google.maps.event.addListener(marker, 'click', function() {
			for ( i = 0; i < numItems; i++ ) {
				infoWindows[i].close();
			}
			infoWindows[this.itemNum].open(map,this);
		});
	}
	if ( doMarkerClustering ) {
		var mc = new MarkerClusterer( map, markers );
	}
}

CargoMap.toOpenLayersLonLat = function( map, lat, lon ) {
	return new OpenLayers.LonLat( lon, lat ).transform(
		new OpenLayers.Projection("EPSG:4326"), // transform from WGS 1984
		map.getProjectionObject() // to Spherical Mercator Projection
	);
}

CargoMap.prototype.displayWithOpenLayers = function() {
	var map = new OpenLayers.Map( this.divID );
	map.addLayer( new OpenLayers.Layer.OSM() );

	// Center coordinates are not used by OpenLayers.
	var southWestLonLat = CargoMap.toOpenLayersLonLat( map, this.southLatitude, this.westLongitude );
	var northEastLonLat = CargoMap.toOpenLayersLonLat( map, this.northLatitude, this.eastLongitude );
	var mapBounds = new OpenLayers.Bounds();
	mapBounds.extend( southWestLonLat );
	mapBounds.extend( northEastLonLat );
	map.zoomToExtent( mapBounds );
	if ( this.zoomLevel != null ) {
		map.zoomTo( this.zoomLevel );
	}

	var markers = new OpenLayers.Layer.Markers( "Markers" );
	map.addLayer( markers );

	var popupClass = OpenLayers.Class(OpenLayers.Popup.FramedCloud, {
		"autoSize": true,
		"minSize": new OpenLayers.Size(300, 50),
		"maxSize": new OpenLayers.Size(500, 300),
		"keepInMap": true
	});

	var numItems = this.allItemValues.length;
	for ( i = 0; i < numItems; i++ ) {
		var curItem = this.allItemValues[i];
		var curLonLat = CargoMap.toOpenLayersLonLat( map, curItem['lat'], curItem['lon'] );
		var feature = new OpenLayers.Feature( markers, curLonLat );
		feature.closeBox = true;
		feature.popupClass = popupClass;

		feature.data.popupContentHTML = CargoMap.createPopupHTMLForRow( curItem )

		if ( curItem.hasOwnProperty( 'icon' ) ) {
			var icon = new OpenLayers.Icon( curItem.icon );
			var marker = new OpenLayers.Marker( curLonLat, icon );
		} else {
			var marker = new OpenLayers.Marker( curLonLat );
		}
		markers.addMarker( marker );

		marker.events.register( 'mousedown', feature, function(evt) {
			if (this.popup == null ) {
				this.popup = this.createPopup( true );
				map.addPopup( this.popup );
				this.popup.show();
			} else {
				this.popup.toggle();
			}
			currentPopup = this.popup;
			OpenLayers.Event.stop( evt );
		});
	}
}

jQuery(document).ready( function() {
	jQuery(".mapCanvas").each( function() {
		var mapDataText = $(this).find(".cargoMapData").text();
		var valuesForMap = jQuery.parseJSON(mapDataText);
		var mappingService = $(this).find(".cargoMapData").attr('mappingService');
		var zoomLevel = $(this).find(".cargoMapData").attr('zoom');
		var doMarkerClustering = valuesForMap.length >= wgCargoMapClusteringMinimum;
		var cargoMap = new CargoMap( valuesForMap, $(this).attr('id'), zoomLevel );
		cargoMap.display( mappingService, doMarkerClustering );
	});
});
