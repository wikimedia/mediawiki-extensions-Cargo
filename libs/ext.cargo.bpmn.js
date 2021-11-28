/*
 * Copied from file ext.flexdiagrams.bpmn.js in the Flex Diagrams extension.
 */
$(document).ready(function() {

  var gLinkedPages = {};

	$('.cargoBPMN').each( function() {
    var dataURL = decodeURI( $(this).attr('dataurl') );
    // Code for extracting the XML from the URL as a string
    var xmlCode = "";
    var request = new XMLHttpRequest();
    request.open("GET", dataURL, false);
    request.send();
    xmlCode = request.responseText;
    // Code for auto-layouting the BPMN-XML
    var AutoLayout = require('bpmn-auto-layout');
    var autoLayout = new AutoLayout();
    (async () => {
      var layoutedDiagramXML = await autoLayout.layoutProcess(xmlCode);
      var bpmnJS = new BpmnJS({
        container: '#canvas',
        keyboard: {
          bindTo: window
        }
      });  
      bpmnJS.importXML(layoutedDiagramXML, function(err) {
        if (err) {
            return console.error('could not import BPMN 2.0 diagram', err);
        }
        // access modeler components
        var canvas = bpmnJS.get('canvas');
        // zoom to fit full viewport
        canvas.zoom('fit-viewport');
        applyLinks();
      });
    })();
    
    /**
     * Go through the gLinkedPages array and turn each element there into
     * a link to its respective wiki page. Also add graphical elements to
     * each such element to make it more obvious, like making the shapes
     * blue.
     */
    applyLinks = function() {
      var self = this;

      for ( var elementID in gLinkedPages ) {
        $('g[data-element-id="' + elementID + '"').each( function() {
          var linkedPage = gLinkedPages[elementID];
          $(this).click( function( evt ) {
            var newURL = mw.config.get('wgServer') +
              mw.config.get('wgScript') +
              '?title=' + linkedPage;
            if ( evt.ctrlKey ) {
              // ctrl+click opens a new tab.
              window.open( newURL, '_blank' );
            } else {
              window.location.href = newURL;
            }
          } );
          $(this).css('cursor', 'pointer');
          self.setShapeColors( $(this), '#0000EE', '#E9E9FB' );
          $(this).mouseenter( function() {
            //self.setShapeColors( $(this), '#0000BB', '#D9D9F5' );
            self.setShapeColors( $(this), '#0000FF', '#F5F5FF' );
          } );
          $(this).mouseleave( function() {
            self.setShapeColors( $(this), '#0000EE', '#E9E9FB' );
          } );
        });
      }
    }
  
    setShapeColors = function( $shape, strokeColor, fillColor ) {
      $shape.find('g.djs-visual').each( function() {
        $(this).children('rect,circle,polygon').css('fill', fillColor)
          .css('stroke', strokeColor);
        $(this).children('text,path').css('fill', strokeColor);
        $(this).children('path').css('stroke', strokeColor);
      } );
    }

    // Add a zoom in/zoom out interface, similar to the one found on
    // https://demo.bpmn.io, for viewing.
    $('.djs-container').append('<div class="fd-djs-zoom djs-palette" style="left: auto; right: 20px;">' +
      '<div class="entry fd-djs-zoom-in">+</div><hr class="separator" /><div class="entry fd-djs-zoom-out">-</div></div>');
    $('.fd-djs-zoom-in').click( function() {
      bpmnJS.get('zoomScroll').stepZoom(1);
    } );
    $('.fd-djs-zoom-out').click( function() {
      bpmnJS.get('zoomScroll').stepZoom(-1);
    } );

	});

});

