<?php
/**
 * Alerts js file.
 *
 * Handles javascript stuff related  to alerts function
 *
 * PHP version 5
 * LICENSE: This source file is subject to LGPL license 
 * that is available through the world-wide-web at the following URI:
 * http://www.gnu.org/copyleft/lesser.html
 * @author     Ushahidi Team <team@ushahidi.com> 
 * @package    Ushahidi - http://source.ushahididev.com
 * @module     API Controller
 * @copyright  Ushahidi - http://www.ushahidi.com
 * @license    http://www.gnu.org/copyleft/lesser.html GNU Lesser General Public License (LGPL) 
 */
?>
<?php require_once(APPPATH.'views/map_common_js.php'); ?>
		
		var proj_4326 = new OpenLayers.Projection('EPSG:4326');
		var proj_900913 = new OpenLayers.Projection('EPSG:900913');
		var regionVLayer;
		var radiusmap;
		var radiusLayer;
    <?php print $geometries_hash; ?>
		jQuery(function($) {
			
			$(window).load(function(){
			<?php 
				
				/*OpenLayers uses IE's VML for vector graphics.
					We need to wait for IE's engine to finish loading all namespaces (document.namespaces) for VML.
					jQuery.ready is executing too soon for IE to complete it's loading process.
				 */
			?>
			
			// Create the radiusmap
			var latitude = <?php echo $latitude; ?>;
			var longitude = <?php echo $longitude; ?>;

      // Create the radius map
      
			radiusmap = createMap('radiusDivMap', latitude, longitude, 14);
			
			// Add the radius layer
			addRadiusLayer(radiusmap, latitude, longitude);
			
      // Draw circle around point
      drawCircle(radiusmap, latitude, longitude, $("#alert_radius").val() * 1000);

      // Create the region map
      regionmap = createMap('regionDivMap', latitude, longitude, 14);

      // create a vector layer for the region map to place the regions on
			style1 = new OpenLayers.Style({
				pointRadius: "8",
				fillColor: "#ffcc66",
				fillOpacity: "0.7",
				strokeColor: "#CC0000",
				strokeWidth: 2.5,
				graphicZIndex: 1,
				externalGraphic: "<?php echo url::file_loc('img').'media/img/openlayers/marker.png' ;?>",
				graphicOpacity: 1,
				graphicWidth: 21,
				graphicHeight: 25,
				graphicXOffset: -14,
				graphicYOffset: -27
			});
			var vlayerStyles = new OpenLayers.StyleMap({ "default": style1 });
			regionVLayer = new OpenLayers.Layer.Vector( "Editable", {
				styleMap: vlayerStyles,
				rendererOptions: {zIndexing: true}
			});
			regionmap.addLayer(regionVLayer);

      // Apply accordian effects to the map selectors, By default we prioritize
      // the region selector
      $('#alert_radius_view').hide();
      $('a#select_by_region').hide();
      $('input#radius').val(0);
      $('a.select_area').click(function() {
        // hide the region link and the radius view
        $('div#alert_radius_view').slideToggle(1000);
        $('div#alert_region_view').slideToggle(1000);

        var val = $('input#radius').val();
        if (val == 0) {
          val = 1;
        } else {
          val = 0;
        }

        $('input#radius').val(val);
        $('a.select_area').toggle();
      });
     
      // Insert Saved Geometries
      var geometry_id = $('select#sectors option:selected').val();
      createWKTFeature(geometry_id);
      $('#geometries #sectors').change(function() {
        // clear the previous gemoetry
        regionVLayer.removeFeatures(regionVLayer.features);

        // place the new geometry
        geometry_id = $('#geometries #sectors option:selected').val();
        createWKTFeature(geometry_id);
      });
			
			// Detect Map Clicks
			radiusmap.events.register("click", radiusmap, function(e){
				var lonlat = radiusmap.getLonLatFromViewPortPx(e.xy);
				var lonlat2 = radiusmap.getLonLatFromViewPortPx(e.xy);
			    m = new OpenLayers.Marker(lonlat);
				markers.clearMarkers();
		    	markers.addMarker(m);
		
				currRadius = $("#alert_radius").val();
				radius = currRadius * 1000
				
				lonlat2.transform(proj_900913, proj_4326);
				drawCircle(radiusmap, lonlat2.lat, lonlat2.lon, radius);
							
				// Update form values (jQuery)
				$("#alert_lat").attr("value", lonlat2.lat);
				$("#alert_lon").attr("value", lonlat2.lon);
				
				// Looking up country name using reverse geocoding					
				reverseGeocode(lonlat2.lat, lonlat2.lon);
			});
			
			/* 
			Google GeoCoder
			TODO - Add Yahoo and Bing Geocoding Services
			 */
			$('.btn_find').live('click', function () {
				geoCode();
			});
			
			
			// Alerts Slider
			$("select#alert_radius").selectToUISlider({
				labels: 6,
				labelSrc: 'text',
				sliderOptions: {
					change: function(e, ui) {
						var newRadius = $("#alert_radius").val();
						
						// Convert to Meters
						radius = newRadius * 1000;	
						
						// Redraw Circle
						currLon = $("#alert_lon").val();
						currLat = $("#alert_lat").val();
						drawCircle(radiusmap, currLat, currLon, radius);
					}
				}
			}).hide();
			
			
			// Some Default Values		
			$("#alert_mobile").focus(function() {
				$("#alert_mobile_yes").attr("checked",true);
			}).blur(function() {
				if( !this.value.length ) {
					$("#alert_mobile_yes").attr("checked",false);
				}
			});
			
			$("#alert_email").focus(function() {
				$("#alert_email_yes").attr("checked",true);
			}).blur(function() {
				if( !this.value.length ) {
					$("#alert_email_yes").attr("checked",false);
				}
			});
		
		
			// Category treeview
		    $("#category-column-1,#category-column-2").treeview({
		      persist: "location",
			  collapsed: true,
			  unique: false
			  });
			});
      });

		/* Clear the list of selected features */
		function clearSelected(feature) {
		    selectedFeatures = [];
			$('#geometry_label').val("");
			$('#geometry_comment').val("");
			$('#geometry_color').val("");
			$('#geometry_lat').val("");
			$('#geometry_lon').val("");
			selectCtrl.deactivate();
			selectCtrl.activate();
			$('#geometry_color').ColorPickerHide();
		}
		
    /* create a WKT feature */
    function createWKTFeature(geometry_id) {
      wkt = new OpenLayers.Format.WKT();
      var geometry = geometries[geometry_id];
      wktFeature = wkt.read(geometry['geometry']);
      wktFeature.geometry.transform(proj_4326,proj_900913);
      wktFeature.label = geometry['label'];
      wktFeature.comment = geometry['comment'];
      wktFeature.color = geometry['color'];
      wktFeature.strokewidth = geometry['strokewidth'];
      regionVLayer.addFeatures(wktFeature);
      var color = geometry['color'];
      var strokewidth = geometry['strokewidth'];
      updateFeature(wktFeature, color, strokewidth);
    }

		function updateFeature(feature, color, strokeWidth){
		
			// Create a symbolizer from exiting stylemap
			var symbolizer = feature.layer.styleMap.createSymbolizer(feature);
			
			// Color available?
			if (color) {
				symbolizer['fillColor'] = "#"+color;
				symbolizer['strokeColor'] = "#"+color;
				symbolizer['fillOpacity'] = "0.7";
			} else {
				if ( typeof(feature.color) != 'undefined' && feature.color != '' ) {
					symbolizer['fillColor'] = "#"+feature.color;
					symbolizer['strokeColor'] = "#"+feature.color;
					symbolizer['fillOpacity'] = "0.7";
				}
			}
			
			// Stroke available?
			if (parseFloat(strokeWidth)) {
				symbolizer['strokeWidth'] = parseFloat(strokeWidth);
			} else if ( typeof(feature.strokewidth) != 'undefined' && feature.strokewidth !='' ) {
				symbolizer['strokeWidth'] = feature.strokewidth;
			} else {
				symbolizer['strokeWidth'] = "2.5";
			}
			
			// Set the unique style to the feature
			feature.style = symbolizer;

			// Redraw the feature with its new style
			feature.layer.drawFeature(feature);
		}
		
		/**
		 * Google GeoCoder
		 */
		function geoCode()
		{
			$('#find_loading').html('<img src="<?php echo url::file_loc('img')."media/img/loading_g.gif"; ?>">');
			address = $("#location_find").val();
			$.post("<?php echo url::site() . 'reports/geocode/' ?>", { address: address },
				function(data){
					if (data.status == 'success'){
						var lonlat = new OpenLayers.LonLat(data.message[1], data.message[0]);
						lonlat.transform(proj_4326,proj_900913);
					
						m = new OpenLayers.Marker(lonlat);
						markers.clearMarkers();
				    	markers.addMarker(m);
						radiusmap.setCenter(lonlat, 9);
					
						newRadius = $("#alert_radius").val();
						radius = newRadius * 1000

						drawCircle(data.message[1],data.message[0], radius);
						
						// Looking up country name using reverse geocoding					
						reverseGeocode(data.message[0], data.message[1]);
					
						// Update form values (jQuery)
						$("#alert_lat").attr("value", data.message[0]);
						$("#alert_lon").attr("value", data.message[1]);
					} else {
						alert(address + " not found!\n\n***************************\nEnter more details like city, town, country\nor find a city or town close by and zoom in\nto find your precise location");
					}
					$('#find_loading').html('');
				}, "json");
			return false;
		}	
