/*
 * ext.cargo.query.js
 *
 * Handles Javascript functionalities on the Special:CargoQuery page
 *
 * @author Ankita Mandal
 *
 */
$(document).ready(function() {
	var indx; //Global variable for first order by input field
	function split( val ) {
		return val.split(/,\s*/);
	}
	function extractLast( term ) {
		return split( term ).pop();
	}
	var my_server = mw.config.get( 'wgScriptPath' ) + "/api.php";

	// Function for Fields, Group_By, Order_By
	$.fn.autocompleteOnSearchString = function ( joinString ) {
		$(this).click(function() { $(this).autocomplete( "search", "" ); });
		var selectOpts = "";
		if( joinString != "") {
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
					url: my_server + "?action=cargoqueryautocomplete&format=json&tables=" +  $('#tables').val().replace(/\s/g, ''),
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
	$( '#tables' ).autocomplete({
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

		var delim = ", ";
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

	// Enable autocomplete on fields
	$('#fields').autocompleteOnSearchString(", ");

	// Enable autocomplete on group_by
	$('#group_by').autocompleteOnSearchString(", ");

	// Enable autocomplete on order_by
	$('.order_by').autocompleteOnSearchString("");

	// Load 'having' field when 'group by' has been entered
	$('#group_by').on('change', function(){
		// If value is empty
		if ($(this).val().length == 0 ) {
			// Hide the element
			$('.showAfterGroupBy').hide();
		} else {
			// Otherwise show it
			$('.showAfterGroupBy').show();
		}
	}).keyup();
	if ( $('#group_by').val().length > 0 ) {
		// Hide the element
		$('.showAfterGroupBy').show();
	}

	indx = $( ".first_order_by" ).index();
	// Code to handle multiple order by rows
	$('#add_more').on('click', function(){
		var newRow = $('<tr class = "mw-htmlform-field-HTMLTextField order_by_class"><td></td><td class = "mw-input"><input id = "order_by" class = "form-control  order_by"  size = "50 !important" name = "order_by[]"/>' +
			'&nbsp&nbsp<select name = "order_by_options[]" id = "order_by_options" style = "width: 60px; white-space:pre-wrap;">' +
			'\t\t\t\t\t\t\t\t  <option value = "ASC">ASC</option>\n' +
			'\t\t\t\t\t\t\t\t  <option value = "DESC">DESC</option>\n' +
			'\t\t\t\t</select>&nbsp&nbsp<button class="deleteButton" name="delete" id ="delete" type="button"></button></td></tr>');
		newRow.insertAfter($('#cargoQueryTable tbody tr:nth('+indx+')'));
		indx++;
		var elem = $(newRow.find("input"));
		elem.autocompleteOnSearchString("");
	});

	// Remove a Order By Row when respective Delete button is pressed
	$("#cargoQueryTable").on("click", ".deleteButton", function() {
		$(this).closest("tr").remove();
		indx--;
	});

	// Form validations
	$('form').on('submit', function (e) {
		var focusSet = false;

		// Validating if at least one Tablename has been entered in the Table(s) field
		if (!$('#tables').val()) {
			if ($(".tablevalidation").length == 0) { // only add if not added
				$("#tables").closest('tr').after("<tr class = 'mw-htmlform-field-HTMLTextField tablevalidation'><td></td>" +
					"<td class = 'mw-label tablevalidation' style='color:red;margin-bottom: 20px;text-align: left'>" +
					mw.msg( 'cargo-viewdata-tablesrequired' ) + "</td></tr>");
				indx++;
			}
			e.preventDefault(); // prevent form from POST to server
			$('#tables').focus();
			focusSet = true;
		} else {
			$(".tablevalidation").closest('tr').remove(); // remove it
		}

		//	Validating if the Join on value has been entered when multiple tables are there
		var tableval = $('#tables').val().replace(/\s+/g, " ").replace(/^\s|\s$/g, "");
		var lastChar = tableval.slice(-1);
		if (lastChar == ',') {
			tableval = tableval.slice(0, -1);
		}
		if ( ( tableval.includes(',') ) &&  (!$("#join_on").val()) ) {
			if ($(".joinonvalidation").length == 0) { // only add if not added
				$("#join_on").closest('tr').after("<tr class = 'mw-htmlform-field-HTMLTextField joinonvalidation'><td></td>" +
					"<td class = 'mw-label joinonvalidation' style='color:red;margin-bottom: 20px;text-align: left'>" +
					mw.msg( 'cargo-viewdata-joinonrequired' ) + "</td></tr>");
				indx++;
			}
			e.preventDefault(); // prevent form from POST to server
			$('#join_on').focus();
			focusSet = true;
		} else {
			$(".joinonvalidation").closest('tr').remove(); // remove it
		}


	});
});
