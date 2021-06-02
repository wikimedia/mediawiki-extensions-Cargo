/*
 * ext.cargo.query.js
 *
 * Handles JavaScript functionality in the Special:CargoQuery page
 *
 * @author Ankita Mandal
 * @author Yaron Koren
 */
$(document).ready(function() {
	var query = decodeURIComponent( window.location.search.substring(1) ).replace(/\+/g, ' ');
	var queryVarStrings = query.split("&");
	var queryVars = {};
	for (var i = 0; i < queryVarStrings.length; i++) {
		var pair = queryVarStrings[i].split("=");
		queryVars[pair[0]] = pair[1];
	}
	function getInfoMessage( inputName ) {
		if ( inputName == 'tables' ) {
			return mediaWiki.msg( 'cargo-viewdata-tablestooltip', "Cities=city, Countries" );
		} else if ( inputName == 'fields' ) {
			return mediaWiki.msg( 'cargo-viewdata-fieldstooltip', "_pageName", "Cities.Population=P, Countries.Capital" );
		} else if ( inputName == 'where' ) {
			return mediaWiki.msg( 'cargo-viewdata-wheretooltip', "Country.Continent = 'North America' AND City.Population > 100000" );
		} else if ( inputName == 'join_on' ) {
			return mediaWiki.msg( 'cargo-viewdata-joinontooltip', "Cities.Country=Countries._pageName" );
		} else if ( inputName == 'group_by' ) {
			return mediaWiki.msg( 'cargo-viewdata-groupbytooltip', "Countries.Continent" );
		} else if ( inputName == 'having' ) {
			return mediaWiki.msg( 'cargo-viewdata-havingtooltip', "COUNT(*) > 10" );
		} else if ( inputName == 'order_by' ) {
			return mediaWiki.msg( 'cargo-viewdata-orderbytooltip' );
		} else if ( inputName == 'limit' ) {
			return mediaWiki.msg( 'cargo-viewdata-limittooltip', mediaWiki.config.get('wgCargoDefaultQueryLimit') );
		} else if ( inputName == 'offset' ) {
			return mediaWiki.msg( 'cargo-viewdata-offsettooltip', "0" );
		} else if ( inputName == 'format' ) {
			return mediaWiki.msg( 'cargo-viewdata-formattooltip' );
		}
	}
	popupMessage = {
		padded: true,
		align: 'force-right',
	}
	infoBox = {
		icon: 'info',
		framed: false,
		label: 'More information',
		invisibleLabel: true,
		popup: popupMessage
	}

	var fieldName = [ 'tables', 'fields', 'where', 'join_on','group_by', 'having', 'limit', 'offset', 'format' ];
	fieldName.forEach( function(item) {
		popupMessage.$content = $( '<p align=left>' + getInfoMessage( item ) + '</p>' );
		infoBox.popup = popupMessage;
		var info = new OO.ui.PopupButtonWidget(infoBox);
		$('tr.ext-cargo-tr-'+item+' td:eq(0)').append(info.$element);
		if (item == 'where' || item == 'join_on' || item == 'having') {
			$('tr.ext-cargo-tr-' + item+ ' td:eq(1)').find('textarea').attr('id',item);
			classes = 'form-control cargo-query-textarea';
		} else if (item !== 'format') {
			$('tr.ext-cargo-tr-' + item + ' td:eq(1)').find('input').attr('id',item);
			classes = 'form-control cargo-query-input';
		}
		$( '#' + item ).addClass( classes );
		$( '#' + item ).attr( {'name': item, 'multiple': true } );
	});

	$('table.cargoQueryTable').css( 'border-spacing', '0 5px' );
	// handling for name='order_by'

	var firstOrderByRow = $('.orderByRow').first();
	var orderByFirstRowNum = parseInt(firstOrderByRow.attr('data-order-by-num'));
	var lastOrderByRow = $('.orderByRow').last();
	var orderBylastRowNum = parseInt(lastOrderByRow.attr('data-order-by-num'));
	adjustSizeOfOrderBy( orderByFirstRowNum, orderBylastRowNum, 'form-control order_by' );

	//adding the styles
	$('.ext-cargo-tables').css( 'width', '600px' );
	$('.ext-cargo-fields').css( 'width', '600px' );
	$('.ext-cargo-where').css( 'width', '600px' );
	$('.ext-cargo-join_on').css( 'width', '600px' );
	$('.ext-cargo-group_by').css( 'width', '600px' );
	$('.ext-cargo-having').css( 'width', '600px' );
	$('.ext-cargo-limit').css( 'width', '100px' )
	$('.ext-cargo-offset').css( 'width', '100px' );
	$('#format').css( 'width', '200px' );
	function split( val ) {
		return val.split(/,\s*/);
	}
	function extractLast( term ) {
		return split( term ).pop();
	}
	var my_server = mw.config.get( 'wgScriptPath' ) + "/api.php";

	// Function for Fields, Group_By, Order_By
	$.fn.autocompleteOnSearchString = function ( joinString ) {
		$(this).click(function() {
			$(this).autocomplete( "search", "" );
		});
		var selectOpts = "";
		if ( joinString != "" ) {
			selectOpts = function( event, ui ) {
				var terms = split( this.value );
				// remove the current input
				terms.pop();
				// add the selected item
				terms.push( ui.item.value );
				// add placeholder to get the comma-and-space at the end
				terms.push("");
				this.value = terms.join(joinString);
				return false;
			};
		}
		$(this).autocomplete({
			minLength: 0,
			source: function( request, response ) {

				var searchText = extractLast( request.term );
				$.ajax({
					url: my_server + "?action=cargoqueryautocomplete&format=json&tables=" + $('#tables').val().replace(/\s/g, ''),
					type: 'get',
					dataType: "json",
					data: {
						search: searchText
					},

					success: function (data) {
						var transformed = $.map(data.cargoqueryautocomplete, function ( el ) {
							return {
								label: el,
								id: el
							};
						});
						response(transformed);
					},
					error: function () {
						response([]);
					}
				});
			},

			select: selectOpts

		}).data( "autocomplete" )._renderItem = function( ul, item ) {
			var delim = joinString;
			var term;
			if ( delim === "" ) {
				term = this.term;
			} else {
				term = this.term.split( delim ).pop();
			}
			var re = new RegExp("(?![^&;]+;)(?!<[^<>]*)(" +
				term.replace(/([\^\$\(\)\[\]\{\}\*\.\+\?\|\\])/gi, "\\$1") +
				")(?![^<>]*>)(?![^&;]+;)", "gi");
			// HTML-encode the value's label.
			var itemLabel = $('<div/>').text(item.label).html();
			var loc = itemLabel.search(re);
			var t;
			if (loc >= 0) {
				t = itemLabel.substr(0, loc) +
					'<strong>' + itemLabel.substr(loc, term.length) + '</strong>' +
					itemLabel.substr(loc + term.length);
			} else {
				t = itemLabel;
			}
			return $( "<li></li>" )
				.data( "item.autocomplete", item )
				.append( " <a>" + t + "</a>" )
				.appendTo( ul );
		};
	}
	// Enable autocomplete on tables
	$('#tables').click(function() { $(this).autocomplete( "search", "" ); });
	$('#tables').autocomplete({
		minLength: 0,
		source: function( request, response ) {

			var searchText = extractLast(request.term);
			$.ajax({
				url: my_server + "?action=cargoqueryautocomplete&format=json",
				type: 'get',
				dataType: "json",
				data: {
					search: searchText
				},

				success: function (data) {
					var transformed = $.map(data.cargoqueryautocomplete, function (el) {
						return {
							label: el.main_table,
							id: el.main_table
						};
					});
					response(transformed);
				},
				error: function () {
					response([]);
				}
			});
		},

		select: function( event, ui ) {
			var terms = split( this.value );
			// remove the current input
			terms.pop();
			// add the selected item
			terms.push( ui.item.value );
			// add placeholder to get the comma-and-space at the end
			terms.push("");
			this.value = terms.join(", ");
			return false;
		},

	}).data( "autocomplete" )._renderItem = function( ul, item ) {
		var term = this.term.split( ", " ).pop();
		var re = new RegExp("(?![^&;]+;)(?!<[^<>]*)(" +
			term.replace(/([\^\$\(\)\[\]\{\}\*\.\+\?\|\\])/gi, "\\$1") +
			")(?![^<>]*>)(?![^&;]+;)", "gi");
		// HTML-encode the value's label.
		var itemLabel = $('<div/>').text(item.label).html();
		var loc = itemLabel.search(re);
		var t;
		if (loc >= 0) {
			t = itemLabel.substr(0, loc) +
				'<strong>' + itemLabel.substr(loc, term.length) + '</strong>' +
				itemLabel.substr(loc + term.length);
		} else {
			t = itemLabel;
		}
		return $( "<li></li>" )
			.data( "item.autocomplete", item )
			.append( " <a>" + t + "</a>" )
			.appendTo( ul );
	};

	// Enable autocomplete on fields
	$('#fields').autocompleteOnSearchString(", ");

	// Enable autocomplete on group_by
	$('#group_by').autocompleteOnSearchString(", ");

	// Enable autocomplete on order_by
	$('.order_by').autocompleteOnSearchString("");

	// Display row for 'having' input only if 'group by' has been entered
	$('#group_by').on('autocompletechange', function(){
		if ($(this).val().length == 0 ) {
			$('#having').parents('tr').hide();
		} else {
			$('#having').parents('tr').show();
		}
	});
	if ( $('#group_by').val().length == 0 ) {
		$('#having').parents('tr').hide();
	}

	// Code to handle multiple "order by" rows
	$('#addButton').on('click', function(){
		var lastRow = $('.orderByRow').last();
		var orderByNum = parseInt(lastRow.attr('data-order-by-num')) + 1;
		var orderByInput = new OO.ui.TextInputWidget( {
			id: 'order_by[' + orderByNum + ']'
		} );
		var directionSelect = new OO.ui.DropdownInputWidget( {
			options: [
				{
					data: 'ASC',
					label: 'ASC'
				},
				{
					data: 'DESC',
					label: 'DESC'
				}
			],
			name: 'order_by_options[' + orderByNum + ']'
		} );
		var button = new OO.ui.ButtonWidget( {
			id: 'deleteButton',
			icon: 'subtract'
		} );
		var orderByRow = new OO.ui.HorizontalLayout( {
			items: [ orderByInput, directionSelect, button ]
		} );
		var newRow = $('<tr data-order-by-num=' + orderByNum + '><td></td>' +
			'<td></td></tr>');
		newRow.insertAfter(lastRow);
		$("tr[data-order-by-num='"+ orderByNum +"'] td:eq(1)").append(orderByRow.$element)
		classes = 'form-control order_by';
		adjustSizeOfOrderBy( orderByNum, orderByNum, classes );		
		$("tr[data-order-by-num='"+ orderByNum +"']").find("input").autocompleteOnSearchString("");
	});

	$("#cargoQueryTable").on("click", "#deleteButton", function() {
		$(this).closest("tr").remove();
	});

	function printCargoQueryInput( paramName, paramLabel ) {
		var value = '';
		if ( queryVars.hasOwnProperty(paramName) ) {
			value = queryVars[paramName];
		}
		var text = new OO.ui.TextInputWidget( {
			classes: [ 'ext-cargo-div-' + paramLabel.slice(0,-1).replace(/\s+/g, '') ],
			value: value,
			name: paramName
		} );
		return text;
	}
	function adjustSizeOfOrderBy( orderByFirstRowNum, orderBylastRowNum, classes ) {

		// giving negative margin-bottom to each div of OOUI-HorizontalLayout used in 'Order by' row.
		$('.oo-ui-horizontalLayout').css( 'margin-bottom', '-8px' );
		for ( i=orderByFirstRowNum; i<=orderBylastRowNum; i++ ) {
			if ( i==0 ) {
				popupMessage.$content = $( '<p align=left>' + getInfoMessage( 'order_by' ) + '</p>' );
				infoBox.popup = popupMessage;
				var info = new OO.ui.PopupButtonWidget(infoBox);
				$("tr.orderByRow td:eq(0)").append(info.$element);
			}
			var name = $("tr[data-order-by-num='"+ i +"']").find("div.oo-ui-textInputWidget").attr('id');
			$("tr[data-order-by-num='"+ i +"']").find("div.oo-ui-textInputWidget").css('width', '300px');
			$("tr[data-order-by-num='"+ i +"']").find("div.oo-ui-dropdownInputWidget").css('width', '100px');
			$("tr[data-order-by-num='"+ i +"']").find("div.oo-ui-textInputWidget input").attr('name', name);
			$("tr[data-order-by-num='"+ i +"']").find("div.oo-ui-textInputWidget input").addClass(classes);
		}
	}

	$.fn.addCargoQueryInput = function( paramName, paramAttrs ) {
		if ( paramAttrs.hasOwnProperty('label') ) {
			var paramLabel = paramAttrs.label;
		} else {
			var paramLabel = paramName.charAt(0).toUpperCase() + paramName.slice(1) + ":";
		}
		var classForFormatParamRow = paramLabel.slice(0,-1).replace(/\s+/g, '');
		if ( paramAttrs.hasOwnProperty('values') ) {
			var options = [],
				value = '',
				size = '';
			for ( i in paramAttrs['values'] ) {
				var curValue = paramAttrs['values'][i];
				options.push( {
					data: curValue,
					label: curValue
				} );
				if ( queryVars.hasOwnProperty(paramName) && queryVars[paramName] == curValue ) {
					value = curValue;
				}
				inputHTML += '>' + curValue + '</option>';
			}
			var inputHTML = new OO.ui.DropdownInputWidget( {
				options: options,
				classes: [ 'ext-cargo-div-' + classForFormatParamRow ],
				name: paramName,
				value: value
			} );
			size = '150px';
		} else if ( paramAttrs.type == 'string' ) {
			var inputHTML = printCargoQueryInput( paramName, paramLabel );
			size = '200px'
		} else if ( paramAttrs.type == 'int' ) {
			var inputHTML = printCargoQueryInput( paramName, paramLabel );
			size = '50px';
		} else if ( paramAttrs.type == 'date' ) {
			// Put a date or datetime input here?
			var inputHTML = printCargoQueryInput( paramName, paramLabel );
			size = '100px';
		} else if ( paramAttrs.type == 'boolean' ) {
			var selected = false;
			if ( queryVars.hasOwnProperty(paramName) ) {
				selected = true;
			}
			var inputHTML = new OO.ui.CheckboxInputWidget( {
				name: paramName,
				selected: selected,
				value: "yes"
			} );
		} else {
			var inputHTML = printCargoQueryInput( paramName, paramLabel );
			size = '200px'
		}

		var rowHTML = '<tr class="formatParam ext-cargo-formatParam-'+classForFormatParamRow+'"><td class="mw-label">' + paramLabel + '&nbsp;&nbsp;</td>' +
			'<td></td></tr>';
		$(this).append(rowHTML);
		$('tr.ext-cargo-formatParam-'+classForFormatParamRow+' td:eq(1)').append(inputHTML.$element);
		$('.ext-cargo-div-'+classForFormatParamRow).css('width', size);
	}

	$.fn.showInputsForFormat = function() {
		$('.formatParam').remove();
		var formatDropdown = $(this);
		var selectedFormat = formatDropdown.val();
		if ( selectedFormat == '' ) {
			return $(this);
		}

		$.ajax({
			url: my_server + "?action=cargoformatparams&format=json&queryformat=" + selectedFormat,
			type: 'get',
			dataType: "json",
			success: function (data) {
				var params = data.cargoformatparams;
				var formTable = formatDropdown.parents('#cargoQueryTable');
				for ( var paramName in params ) {
					formTable.addCargoQueryInput( paramName, params[paramName] );
				}
			},
			error: function () {
				response([]);
			}
		});
		return $(this);
	}

	$('select[name="format"]').showInputsForFormat();
	$('select[name="format"]').change(function(){
		$(this).showInputsForFormat();
	});

	// Form validations
	$.fn.addErrorMessage = function( className, errorMsg ) {
		errorWidget = new OO.ui.MessageWidget( {
			type: 'error',
			inline: true,
			label: errorMsg
		} )
		$(this).after('<tr class="' + className + '"><td></td>' +
			'<td></td></tr>');
		$('tr.' + className +' td:eq(1)').append(errorWidget.$element);
	}

	$('form#queryform').on('submit', function (e) {
		// Validate if at least one table name has been entered in the Table(s) field
		if (!$('#tables').val()) {
			if ($(".tablesErrorMessage").length == 0) { // only add if not added
				$("#tables").closest('tr').addErrorMessage( 'tablesErrorMessage', mw.msg( 'cargo-viewdata-tablesrequired' ) );
			}
			e.preventDefault(); // prevent form from submitting
			$('#tables').focus();
		} else {
			$(".tablesErrorMessage").remove();
		}

		// Validate if the Join on value has been entered when multiple tables are there
		var tableval = $('#tables').val().replace(/\s+/g, " ").replace(/^\s|\s$/g, "");
		var lastChar = tableval.slice(-1);
		if (lastChar == ',') {
			tableval = tableval.slice(0, -1);
		}
		if ( ( tableval.includes(',') ) && (!$("#join_on").val()) ) {
			if ($(".joinOnErrorMessage").length == 0) { // only add if not added
				$("#join_on").closest('tr').addErrorMessage( 'joinOnErrorMessage', mw.msg( 'cargo-viewdata-joinonrequired' ) );
			}
			e.preventDefault(); // prevent form from submitting
			$('#join_on').focus();
		} else {
			$(".joinOnErrorMessage").remove();
		}
	});

	$('.specialCargoQuery-extraPane').hide();
	$('.specialCargoQuery-extraPane-toggle').click( function(e) {
		e.preventDefault();
		$(this).closest('div').find('.specialCargoQuery-extraPane').toggle();
	});

});
