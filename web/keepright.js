// compress using http://marijnhaverbeke.nl//uglifyjs



///////////////////////////////////////////////////////////////////////////////////////////
// start of file myPermalink.js
// derived OpenLayers Class for custom permalink including keepright parameters
///////////////////////////////////////////////////////////////////////////////////////////


/* Copyright (c) 2006-2008 MetaCarta, Inc., published under the Clear BSD
 * license.  See http://svn.openlayers.org/trunk/openlayers/license.txt for the
 * full text of the license. */


/**
 * @requires OpenLayers/Control.js
 * @requires OpenLayers/Control/ArgParser.js
 */

/**
 * Class: OpenLayers.Control.Permalink
 *
 * Inherits from:
 *  - <OpenLayers.Control>
 */
OpenLayers.Control.myPermalink = OpenLayers.Class(OpenLayers.Control, {

    /**
     * APIProperty: argParserClass
     * {Class} The ArgParser control class (not instance) to use with this
     *     control.
     */
    argParserClass: OpenLayers.Control.ArgParser,

    /**
     * Property: element
     * {DOMElement}
     */
    element: null,

    /**
     * APIProperty: base
     * {String}
     */
    base: '',

    /**
     * APIProperty: displayProjection
     * {<OpenLayers.Projection>} Requires proj4js support.  Projection used
     *     when creating the coordinates in the link. This will reproject the
     *     map coordinates into display coordinates. If you are using this
     *     functionality, the permalink which is last added to the map will
     *     determine the coordinate type which is read from the URL, which
     *     means you should not add permalinks with different
     *     displayProjections to the same map.
     */
    displayProjection: null,

    /**
     * Constructor: OpenLayers.Control.Permalink
     *
     * Parameters:
     * element - {DOMElement}
     * base - {String}
     * options - {Object} options to the control.
     */
    initialize: function(element, base, options) {
        OpenLayers.Control.prototype.initialize.apply(this, [options]);
        this.element = OpenLayers.Util.getElement(element);
        this.base = base || document.location.href;
    },

    /**
     * APIMethod: destroy
     */
    destroy: function()  {
        if (this.element.parentNode == this.div) {
            this.div.removeChild(this.element);
        }
        this.element = null;

        this.map.events.unregister('moveend', this, this.updateLink);

        OpenLayers.Control.prototype.destroy.apply(this, arguments);
    },

    /**
     * Method: setMap
     * Set the map property for the control.
     *
     * Parameters:
     * map - {<OpenLayers.Map>}
     */
    setMap: function(map) {
        OpenLayers.Control.prototype.setMap.apply(this, arguments);

        //make sure we have an arg parser attached
        for(var i=0, len=this.map.controls.length; i<len; i++) {
            var control = this.map.controls[i];
            if (control.CLASS_NAME == this.argParserClass.CLASS_NAME) {

                // If a permalink is added to the map, and an ArgParser already
                // exists, we override the displayProjection to be the one
                // on the permalink.
                if (control.displayProjection != this.displayProjection) {
                    this.displayProjection = control.displayProjection;
                }

                break;
            }
        }
        if (i == this.map.controls.length) {
            this.map.addControl(new this.argParserClass(
                { 'displayProjection': this.displayProjection }));
        }

    },

    /**
     * Method: draw
     *
     * Returns:
     * {DOMElement}
     */
    draw: function() {
        OpenLayers.Control.prototype.draw.apply(this, arguments);

        if (!this.element) {
            this.div.className = this.displayClass;
            this.element = document.createElement("a");
            this.element.innerHTML = OpenLayers.i18n("permalink");
            this.element.href="";
            this.div.appendChild(this.element);
        }
        this.map.events.on({
            'moveend': this.updateLink,
            'changelayer': this.updateLink,
            'changebaselayer': this.updateLink,
            scope: this
        });

        // Make it so there is at least a link even though the map may not have
        // moved yet.
        this.updateLink();

        return this.div;
    },

    /**
     * Method: updateLink 
     */
    updateLink: function() {
        var href = this.base;
        if (href.indexOf('?') != -1) {
            href = href.substring( 0, href.indexOf('?') );
        }

        href += '?' + OpenLayers.Util.getParameterString(this.createParams());
        this.element.href = href;

    },

    /**
     * APIMethod: createParams
     * Creates the parameters that need to be encoded into the permalink url.
     *
     * Parameters:
     * center - {<OpenLayers.LonLat>} center to encode in the permalink.
     *     Defaults to the current map center.
     * zoom - {Integer} zoom level to encode in the permalink. Defaults to the
     *     current map zoom level.
     * layers - {Array(<OpenLayers.Layer>)} layers to encode in the permalink.
     *     Defaults to the current map layers.
     *
     * Returns:
     * {Object} Hash of parameters that will be url-encoded into the
     * permalink.
     */
    createParams: function(center, zoom, layers) {
        center = center || this.map.getCenter();

        var params = OpenLayers.Util.getParameters(this.base);

        // If there's still no center, map is not initialized yet.
        // Break out of this function, and simply return the params from the
        // base link.
        if (center) {

            //zoom
            params.zoom = zoom || this.map.getZoom();

            //lon,lat
            var lat = center.lat;
            var lon = center.lon;

            if (this.displayProjection) {
                var mapPosition = OpenLayers.Projection.transform(
                  { x: lon, y: lat },
                  this.map.getProjectionObject(),
                  this.displayProjection );
                lon = mapPosition.x;
                lat = mapPosition.y;
            }
            params.lat = Math.round(lat*100000)/100000;
            params.lon = Math.round(lon*100000)/100000;

            //layers
            layers = layers || this.map.layers;
            params.layers = '';
            for (var i=0, len=layers.length; i<len; i++) {
                var layer = layers[i];

                if (layer.isBaseLayer) {
                    params.layers += (layer == this.map.baseLayer) ? "B" : "0";
                } else {
                    params.layers += (layer.getVisibility()) ? "T" : "F";
                }
            }
        }

	params["ch"]=getURL_checkboxes(false);	// see file report_map.php

	// append checkboxes for hiding ignored errors/temp.ignored errors
	params["show_ign"] = document.myform.show_ign.checked ? 1 : 0;
	params["show_tmpign"] = document.myform.show_tmpign.checked ? 1 : 0;

        return params;
    },

    CLASS_NAME: "OpenLayers.Control.myPermalink"
});





///////////////////////////////////////////////////////////////////////////////////////////
// start of file myTextFormat.js
// derived OpenLayers Class for parsing keepright error file format
///////////////////////////////////////////////////////////////////////////////////////////

/* Copyright (c) 2006-2008 MetaCarta, Inc., published under a modified BSD license.
 * See http://svn.openlayers.org/trunk/openlayers/repository-license.txt 
 * for the full text of the license. */


/*
 * modified by Harald Kleiner, 2009-02-05
 * expanded by more columns
 */

/**
 * @requires OpenLayers/Feature/Vector.js
 * @requires OpenLayers/Geometry/Point.js
 */

/**
 * Class: OpenLayers.Format.Text
 * Read Text format. Create a new instance with the <OpenLayers.Format.Text>
 *     constructor. This reads text which is formatted like CSV text, using
 *     tabs as the seperator by default. It provides parsing of data originally
 *     used in the MapViewerService, described on the wiki. This Format is used
 *     by the <OpenLayers.Layer.Text> class.
 *
 * Inherits from:
 *  - <OpenLayers.Format>
 */
OpenLayers.Format.myTextFormat = OpenLayers.Class(OpenLayers.Format, {
    
    /**
     * Constructor: OpenLayers.Format.Text
     * Create a new parser for TSV Text.
     *
     * Parameters:
     * options - {Object} An optional object whose properties will be set on
     *     this instance.
     */
    initialize: function(options) {
        OpenLayers.Format.prototype.initialize.apply(this, [options]);
    }, 

    /**
     * APIMethod: read
     * Return a list of features from a Tab Seperated Values text string.
     * 
     * Parameters:
     * data - {String} 
     *
     * Returns:
     * An Array of <OpenLayers.Feature.Vector>s
     */
    read: function(text) {
        var lines = text.split('\n');
        var features = [];
        // length - 1 to allow for trailing new line
        for (var lcv = 1; lcv < (lines.length - 1); lcv++) {
            var currLine = lines[lcv].replace(/^\s*/,'').replace(/\s*$/,'');

            if (currLine.charAt(0) != '#') { /* not a comment */

		var vals = currLine.split('\t');
		var geometry = new OpenLayers.Geometry.Point(0,0);
		var attributes = {};
		var style = {};
		var icon, iconSize, iconOffset;
				
		geometry.y = parseFloat(vals[0]);
		attributes['lat'] = geometry.y;
			
		geometry.x = parseFloat(vals[1]);
		attributes['lon'] = geometry.x;	
			
		attributes['error_name'] = vals[2];
		
		attributes['error_type'] = vals[3];
		attributes['object_type'] = vals[4];
		attributes['object_type_EN'] = vals[5];
		attributes['object_id'] = vals[6];
		attributes['object_timestamp'] = vals[7];
		attributes['user_name'] = vals[8];
		attributes['schema'] = vals[9];
		attributes['error_id'] = vals[10];
		attributes['description'] = vals[11];
		attributes['comment'] = vals[12];
		attributes['state'] = vals[13];
		style['externalGraphic'] = vals[14];
		
		var size = vals[15].split(',');				// icon size
		style['graphicWidth'] = parseFloat(size[0]);
		style['graphicHeight'] = parseFloat(size[1]);
		
		var offset = vals[16].split(',');			// icon offset
		style['graphicXOffset'] = parseFloat(offset[0]);
		style['graphicYOffset'] = parseFloat(offset[1]);
			
		attributes['partner_objects'] = vals[17];
		
		if (vals.length>1) {
			if (this.internalProjection && this.externalProjection) {
				geometry.transform(this.externalProjection, 
						this.internalProjection); 
			}
			var feature = new OpenLayers.Feature.Vector(geometry, attributes, style);
			features.push(feature);
		}
            }
        }
        return features;
    },

    CLASS_NAME: "OpenLayers.Format.myTextFormat" 
});





///////////////////////////////////////////////////////////////////////////////////////////
// start of file myText.js
// derived OpenLayers Class for keepright error bubble
///////////////////////////////////////////////////////////////////////////////////////////

/* Copyright (c) 2006-2008 MetaCarta, Inc., published under the Clear BSD
 * license.	See http://svn.openlayers.org/trunk/openlayers/license.txt for the
 * full text of the license. */


/*
 * modified by Harald Kleiner, 2009-02-05
 * expanded text format with new columns specific to keepright
 * created special content inside the bubbles
 */


/**
 * @requires OpenLayers/Layer/Markers.js
 * @requires OpenLayers/Request/XMLHttpRequest.js
 */

/**
 * Class: OpenLayers.Layer.Text
 * This layer creates markers given data in a text file.	The <location>
 *	 property of the layer (specified as a property of the options argument
 *	 in the <OpenLayers.Layer.Text> constructor) points to a tab delimited
 *	 file with data used to create markers.
 *
 * The first row of the data file should be a header line with the column names
 *	 of the data. Each column should be delimited by a tab space. The
 *	 possible columns are:
 *		- *point* lat,lon of the point where a marker is to be placed
 *		- *lat*	Latitude of the point where a marker is to be placed
 *		- *lon*	Longitude of the point where a marker is to be placed
 *		- *icon* or *image* URL of marker icon to use.
 *		- *iconSize* Size of Icon to use.
 *		- *iconOffset* Where the top-left corner of the icon is to be placed
 *			relative to the latitude and longitude of the point.
 *		- *title* The text of the 'title' is placed inside an 'h2' marker
 *			inside a popup, which opens when the marker is clicked.
 *		- *description* The text of the 'description' is placed below the h2
 *			in the popup. this can be plain text or HTML.
 *
 * Example text file:
 * (code)
 * lat	lon	title	description	iconSize	iconOffset	icon
 * 10	20	title	description	21,25	-10,-25	http://www.openlayers.org/dev/img/marker.png
 * (end)
 *
 * Inherits from:
 *	- <OpenLayers.Layer.Markers>
 */
OpenLayers.Layer.myText = OpenLayers.Class(OpenLayers.Layer.Markers, {

/**
	* APIProperty: location 
	* {String} URL of text file.	Must be specified in the "options" argument
	*	 of the constructor. Can not be changed once passed in. 
	*/
location:null,

/** 
	* Property: features
	* {Array(<OpenLayers.Feature>)} 
	*/
features: null,

/**
	* APIProperty: formatOptions
	* {Object} Hash of options which should be passed to the format when it is
	* created. Must be passed in the constructor.
	*/
formatOptions: null, 

/** 
	* Property: selectedFeature
	* {<OpenLayers.Feature>}
	*/
selectedFeature: null,



activePopup: null,
activeFeature: null,
clicked: false,

error_ids: {},

/**
	* Constructor: OpenLayers.Layer.Text
	* Create a text layer.
	* 
	* Parameters:
	* name - {String} 
	* options - {Object} Object with properties to be set on the layer.
	*	 Must include <location> property.
	*/
initialize: function(name, options) {
	OpenLayers.Layer.Markers.prototype.initialize.apply(this, arguments);
	this.features = new Array();
},

/**
	* APIMethod: destroy 
	*/
destroy: function() {
	// Warning: Layer.Markers.destroy() must be called prior to calling
	// clearFeatures() here, otherwise we leak memory. Indeed, if
	// Layer.Markers.destroy() is called after clearFeatures(), it won't be
	// able to remove the marker image elements from the layer's div since
	// the markers will have been destroyed by clearFeatures().
	OpenLayers.Layer.Markers.prototype.destroy.apply(this, arguments);
	this.clearFeatures();
	this.features = null;
},


/**
	* Method: loadText
	* Start the load of the Text data. Don't do this when we first add the layer,
	* since we may not be visible at any point, and it would therefore be a waste.
	*/
loadText: function() {

	if (this.location != null) {
		// rebuild the link for downloading points text file according to current form settings
		var loc="points.php?lat="+document.myform.lat.value+
			"&lon="+document.myform.lon.value+
			"&zoom="+document.myform.zoom.value+
			"&show_ign="+ (document.myform.show_ign.checked ? 1 : 0)+
			"&show_tmpign="+ (document.myform.show_tmpign.checked ? 1 : 0)+
			"&lang="+document.myform.lang.value+
			"&"+getURL_checkboxes();


		var onFail = function(e) {
			this.events.triggerEvent("loadend");
		};

		this.events.triggerEvent("loadstart");
		OpenLayers.Request.GET({
			url: loc,
			success: this.parseData,
			failure: onFail,
			scope: this
		});
		this.loaded = true;
	}
},

/**
	* Method: moveTo
	* If layer is visible and Text has not been loaded, load Text. 
	* 
	* Parameters:
	* bounds - {Object} 
	* zoomChanged - {Object} 
	* minor - {Object} 
	*/
moveTo:function(bounds, zoomChanged, minor) {
	OpenLayers.Layer.Markers.prototype.moveTo.apply(this, arguments);
	if(this.visibility && !this.loaded){
		this.loadText();
	}
},

/**
	* Method: parseData
	*
	* Parameters:
	* ajaxRequest - {<OpenLayers.Request.XMLHttpRequest>} 
	*/
parseData: function(ajaxRequest) {

	function create_errorbubble_feature(thisObject,feature) {
		var data = {};
		var location;
		var iconSize, iconOffset;

		location = new OpenLayers.LonLat(feature.geometry.x, 							feature.geometry.y);

		if (feature.style.graphicWidth 
			&& feature.style.graphicHeight) {
			iconSize = new OpenLayers.Size(
				feature.style.graphicWidth,
				feature.style.graphicHeight);
		}

		// FIXME: At the moment, we only use this if we have an 
		// externalGraphic, because icon has no setOffset API Method.
		/**
		* FIXME FIRST!!
		* The Text format does all sorts of parseFloating
		* The result of a parseFloat for a bogus string is NaN.	That
		* means the three possible values here are undefined, NaN, or a
		* number.	The previous check was an identity check for null.	This
		* means it was failing for all undefined or NaN.	A slightly better
		* check is for undefined.	An even better check is to see if the
		* value is a number (see #1441).
		*/
		if (feature.style.graphicXOffset !== undefined
			&& feature.style.graphicYOffset !== undefined) {
			iconOffset = new OpenLayers.Pixel(
				feature.style.graphicXOffset, 
				feature.style.graphicYOffset);
		}

		if (feature.style.externalGraphic != null) {
			data.icon = new OpenLayers.Icon(feature.style.externalGraphic, iconSize, iconOffset);
		} else {
			data.icon = OpenLayers.Marker.defaultIcon();

			//allows for the case where the image url is not 
			// specified but the size is. use a default icon
			// but change the size
			if (iconSize != null) {
				data.icon.setSize(iconSize);
			}
		}


		if (feature.attributes.comment == null) feature.attributes.comment="";
		if (feature.attributes.error_id != null) {

			var error_name=feature.attributes.error_name;
			var error_type=feature.attributes.error_type;
			var schema=feature.attributes.schema;
			var error_id=feature.attributes.error_id;
			var object_type=feature.attributes.object_type;
			var object_type_EN=feature.attributes.object_type_EN;
			var object_id=feature.attributes.object_id;
			var object_timestamp=feature.attributes.object_timestamp;
			var user_name=feature.attributes.user_name;
			var description=feature.attributes.description;
			var comment=feature.attributes.comment.replace(/<br>/g, "\n");
			var state=feature.attributes.state;
			var lat=feature.attributes.lat;
			var lon=feature.attributes.lon;
			var partner_objects=feature.attributes.partner_objects;

			if (typeof partner_objects == 'undefined')
				partner_objects='';
			else
				partner_objects=',' + partner_objects;

			data['popupContentHTML'] ='<h5>'+error_name+', '+object_type+' <a href="http://www.openstreetmap.org/browse/'+object_type_EN+'/'+object_id+'" target="_blank">'+object_id+'</a></h5>'+
			'<p class="p1">'+description+'</p>'+

			'<p class="p2">'+txt4+' <a href="http://localhost:8111/load_and_zoom?left=' + (lon-0.001) + '&right=' + (lon-(-0.001)) + '&top=' + (lat-(-0.001)) + '&bottom=' + (lat-0.001) + '&select=' + object_type_EN + object_id + partner_objects +'&zoom_mode=download" target="hiddenIframe" title="'+txt6+'">'+txt5+'</a> ' +	

			'<a href="http://www.openstreetmap.org/edit?lat=' + lat + '&lon=' + lon + '&zoom=18" target="_blank">'+txt7+'</a> ' +

			'<a href="http://www.openstreetmap.org/edit?editor=id&lat=' + lat + '&lon=' + lon + '&zoom=18&' +object_type_EN + '=' + object_id + '" target="_blank">'+txt16+'</a> ' +

			'<a href="http://level0.osmz.ru/?url=' + object_type_EN + object_id + '" target="_blank">'+'Level0'+'</a></p>' +

			''+
			'<form class="p3" name="errfrm_'+schema+'_'+error_id+'" target="hiddenIframe" method="get" action="comment.php">' +
			'<input type="radio" id="st_'+schema+'_'+error_id+'_n" '+(state!='ignore_t' && state!='ignore' ? 'checked="checked"' :'')+' name="st" value="">'+
			'<label for="st_'+schema+'_'+error_id+'_n">'+txt8+'</label><br>'+
			'<input type="radio" id="st_'+schema+'_'+error_id+'_t" '+(state=='ignore_t' ? 'checked="checked"' :'')+' name="st" value="ignore_t">'+
			'<label for="st_'+schema+'_'+error_id+'_t">'+txt9+'</label><br>'+
			'<input type="radio" id="st_'+schema+'_'+error_id+'_i" '+(state=='ignore' ? 'checked="checked"' :'')+' name="st" value="ignore">'+
			'<label for="st_'+schema+'_'+error_id+'_i">'+txt10+'</label><br>'+
			'<span style="white-space:nowrap;"><textarea cols="25" rows="2" name="co">'+comment+'</textarea>'+
			'<input type="hidden" name="schema" value="'+schema+'">'+
			'<input type="hidden" name="id" value="'+error_id+'">'+
			'<input type="button" value="'+txt11+'" onClick="javascript:saveComment('+schema+', '+error_id+', '+error_type+');">' +
			'<input type="button" value="'+txt12+'" onClick="javascript:closeBubble('+schema+', '+error_id+');">' +
			'</form><small><br>'+txt13+'</span>' +
			txt14 + '<a href="report_map.php?schema='+schema+'&error='+error_id+'">'+error_id+'</a><br>' + txt15 + ' ' + object_type + ': <a href="http://www.openstreetmap.org/user/' + user_name + '" target="_blank">' + user_name + '</a> ' + object_timestamp + '</small>';
		}


		data['overflow'] = feature.attributes.overflow || "auto";

		var markerFeature = new OpenLayers.Feature(thisObject, location, data);
		markerFeature.popupClass=OpenLayers.Popup.FramedCloud;


		thisObject.features.push(markerFeature);
		var marker = markerFeature.createMarker();
		if (feature.attributes.error_id != null) {
			marker.events.register("mousedown",markerFeature,thisObject.onClickHandler);
			marker.events.register("mouseover",markerFeature,thisObject.onHOverHandler);
			marker.events.register("mouseout",markerFeature,thisObject.onOutHandler);
		}
		thisObject.addMarker(marker);

		// open error bubble if it is to highlight
		if (schema==document.myform.highlight_schema.value && error_id==document.myform.highlight_error_id.value)
			marker.events.triggerEvent("mousedown");

		return markerFeature.id;
	}




	var text = ajaxRequest.responseText;

	var options = {};

	OpenLayers.Util.extend(options, this.formatOptions);

	if (this.map && !this.projection.equals(this.map.getProjectionObject())) {
		options.externalProjection = this.projection;
		options.internalProjection = this.map.getProjectionObject();
	}

	var parser = new OpenLayers.Format.myTextFormat(options);
	var features = parser.read(text);
	var newfeatures = {};
	var error_id;
	var schema;
	for (var i=0, len=features.length; i<len; i++) {
		error_id=features[i].attributes.error_id;
		schema=features[i].attributes.schema;
		if (error_id != undefined && error_id != null) {
			if (this.error_ids[schema]==undefined) this.error_ids[schema]={};
			// create it only if it doesn't already exist
			if (!this.error_ids[schema][error_id]) {
				this.error_ids[schema][error_id]=create_errorbubble_feature(this, features[i]);
			}
			if (newfeatures[schema]==undefined) newfeatures[schema]={};
			newfeatures[schema][error_id]=true;
		}
	}


	// now remove features not needed any more
	var feature_id = null;
	for (var sch in this.error_ids) {
		for (var errid in this.error_ids[sch]) {
			if (newfeatures[sch]==undefined || !newfeatures[sch][errid]) {
				//console.log("dropping error id " + sch + "." + " + errid + " " + this.error_ids[sch][errid]);
				feature_id=this.error_ids[sch][errid];
				var featureToDestroy = null;
				var j=0;
				var len=this.features.length;
				while (j<len && featureToDestroy==null) {
					if (this.features[j].id == feature_id) {
						featureToDestroy=this.features[j];
					}
					j++;
				}
				if (featureToDestroy != null) {
					OpenLayers.Util.removeItem(this.features, featureToDestroy);

					// the marker associated to the feature has to be removed from map.markers manually
					var markerToDestroy = null;
					var k=0;
					var len=this.markers.length;
					while (k<len && markerToDestroy==null) {
						if (this.markers[k].events.element.id == featureToDestroy.marker.events.element.id) {
							markerToDestroy=this.markers[k];
						}
						k++;
					}
					OpenLayers.Util.removeItem(this.markers, markerToDestroy);

					featureToDestroy.destroy();
					featureToDestroy=null;
				}
				delete this.error_ids[sch][errid];
			}
		}
	}
	this.events.triggerEvent("loadend");
},



// declare event handlers for showing and hiding popups
onClickHandler: function (evt) {
	this.activeFeature=this;

	if (this.clicked && this.activePopup==this.popup) {
		this.activePopup.hide();
		this.clicked=false;
	} else if ((this.clicked && !this.activePopup==this.popup) || !this.clicked) {

		if (this.activePopup!=null) {
			this.activePopup.hide();
		}
		if (this.popup==null) {
			this.popup=this.createPopup();
			this.popup.autoSize=false;
			this.popup.panMapIfOutOfView=false;//document.myform.autopan.checked;
			this.popup.setSize(new OpenLayers.Size(380, 380));
			map.addPopup(this.popup);
		} else {
			this.popup.toggle();
		}
		this.activePopup=this.popup;
		this.clicked=true;
	}
	OpenLayers.Event.stop(evt);
},

onHOverHandler: function (evt) {
	if (!this.clicked) {
		if (this.activePopup!=null) {
			this.activePopup.hide();
		}
		if (this.popup==null) {
			this.popup=this.createPopup();
			this.popup.autoSize=false;
			this.popup.panMapIfOutOfView=false;//document.myform.autopan.checked;
			this.popup.setSize(new OpenLayers.Size(380, 380));
			map.addPopup(this.popup);
		} else {
			this.popup.toggle();
		}
		this.activePopup=this.popup;
	}
	OpenLayers.Event.stop(evt);
},

onOutHandler: function (evt) {
	if (!this.clicked && this.activePopup!=null) this.activePopup.hide();
	OpenLayers.Event.stop(evt);
},




/**
* Method: clearFeatures
*/
clearFeatures: function() {
	if (this.features != null) {
		while(this.features.length > 0) {
			var feature = this.features[0];
			OpenLayers.Util.removeItem(this.features, feature);
			feature.destroy();
		}
	}
},

	CLASS_NAME: "OpenLayers.Layer.myText"
});







///////////////////////////////////////////////////////////////////////////////////////////
// start of file keepright.js
// custom code for keepright user interface
///////////////////////////////////////////////////////////////////////////////////////////



//Initialise the 'map' object
function init() {
	map = new OpenLayers.Map ("map", {
		controls:[
			new OpenLayers.Control.Navigation(),
			new OpenLayers.Control.PanZoomBar(),
			new OpenLayers.Control.LayerSwitcher(),
			new OpenLayers.Control.Attribution()],

		maxExtent: new OpenLayers.Bounds(-20037508,-20037508,20037508,20037508),
		maxResolution: 156543,

		numZoomLevels: 20,
		units: 'm',
		projection: new OpenLayers.Projection("EPSG:900913"),
		displayProjection: new OpenLayers.Projection("EPSG:4326")
	} );

	// add the mapnik layer
	var layerMapnik = new OpenLayers.Layer.OSM.Mapnik("Mapnik", {'attribution': '&copy; <a href="http://www.openstreetmap.org">OpenStreetMap</a> contributors'});
	map.addLayer(layerMapnik);

	// add the open cycle map layer
	var layerCycle = new OpenLayers.Layer.OSM.CycleMap("OSM Cycle Map", {'attribution': '&copy; <a href="http://www.openstreetmap.org">OpenStreetMap</a> contributors'});
	map.addLayer(layerCycle);

	// add point markers layer. This is not the standard text layer but a derived version!
	pois = new OpenLayers.Layer.myText("Errors on Nodes", { location:poisURL, projection: new OpenLayers.Projection("EPSG:4326")} );
	map.addLayer(pois);


	// move map center to lat/lon
	var lonLat = new OpenLayers.LonLat(lon, lat).transform(new OpenLayers.Projection("EPSG:4326"), map.getProjectionObject());
	map.setCenter(lonLat, zoom);

	// add permalink feature. This is not the standard text layer but a derived version!
	plnk = new OpenLayers.Control.myPermalink();
	plnk.displayClass="olControlPermalink";
	map.addControl(plnk);

	// add mouse position lat/lon display feature
//	mp = new OpenLayers.Control.MousePosition();
//	map.addControl(mp);


	// register event that records new lon/lat coordinates in form fields after panning
	map.events.register("moveend", map, function() {
		var pos = this.getCenter().clone();
		var lonlat = pos.transform(this.getProjectionObject(), new OpenLayers.Projection("EPSG:4326"));

		// remember position in hidden form parameters
		document.myform.lat.value=lonlat.lat
		document.myform.lon.value=lonlat.lon
		document.myform.zoom.value=this.getZoom();

		updateCookie();
		updateLinks();

		// reload the error table
		pois.loadText();
	});


	updateCookie();
	updateLinks();
}


function saveComment(schema, error_id, error_type) {
	var myfrm = document['errfrm_'+schema+'_'+error_id];
	repaintIcon(schema, error_id, myfrm.st, error_type);
	myfrm.submit();
	closeBubble(schema, error_id);
}


function repaintIcon(schema, error_id, state, error_type) {
// state is a reference to the option group inside the bubble's form;
// state[0].checked==true means state==none
// state[1].checked==true means state==ignore temporarily
// state[2].checked==true means state==ignore

	var feature_id = pois.error_ids[schema][error_id];
	var i=0;
	var len=pois.features.length;
	var feature=null;
	// find feature's id in list of features
	while (i<len && feature==null) {
		if (pois.features[i].id == feature_id) feature=pois.features[i];
		i++;
	}

	if (state[0].checked) feature.marker.icon.setUrl("img/zap" + error_type + ".png")
	else if (state[1].checked) feature.marker.icon.setUrl("img/zapangel.png")
	else if (state[2].checked) feature.marker.icon.setUrl("img/zapdevil.png");
}

// called as event handler on the cancel button on the bubble
function closeBubble(schema, error_id) {
	var feature_id = pois.error_ids[schema][error_id];

	var i=0;
	var len=pois.features.length;
	var feature=null;
	// find feature's id in list of features
	while (i<len && feature==null) {
		if (pois.features[i].id == feature_id) feature=pois.features[i];
		i++;
	}
	// call event handler as if one had clicked the icon
	feature.marker.events.triggerEvent("mousedown");
}

// check/uncheck all checkboxes for error type selection
function set_checkboxes(new_value) {
        if(new_value == 0 || new_value == 9) {
		// update all the checkboxes
		for (var i = 0; i < document.myform.elements.length; ++i) {
			var el=document.myform.elements[i];
			if (el.type == "checkbox" && el.name.match(/ch[0-9]+/) != null) {
				el.checked=new_value?true:false;
				}
			}
		}
	else {
		if(new_value == 5) {
			displayedTypes = new Array('ch40','ch70','ch90','ch130','ch191','ch195','ch196',
						   'ch201','ch202','ch203','ch204','ch205','ch206','ch207',
						   'ch208','ch231','ch232','ch291','ch292','ch293','ch294',
						   'ch311','ch312','ch313','ch380','ch401','ch402');
			}
		for (var i = 0; i < document.myform.elements.length; ++i) {
			var el=document.myform.elements[i];
			if (el.type == "checkbox" && el.name.match(/ch[0-9]+/) != null ) {
				el.checked=(displayedTypes.indexOf(el.name) != -1)?true:false;
				}
			}
		}

	// update the images that look like tristated checkboxes
	// this was stolen from tristate.js, function onCheckboxClick()

	for (i = 1; i <= document.myform.number_of_tristate_checkboxes.value; i++) {
		var imageId = 'tristateBox' + i + '.Img';
		var fieldAndContainerIds = getFieldAndContainerIds(imageId);
		var allTheCheckboxes = getAllCheckboxesInContainer(fieldAndContainerIds[1]);

		var triStateBoxField = document.getElementById(fieldAndContainerIds[0]);
		updateStateAndImage(allTheCheckboxes, triStateBoxField, imageId);
	}

	plnk.updateLink();
}


function updateCookie() {
	var pos = map.getCenter().clone();
	var lonlat = pos.transform(map.getProjectionObject(),
		new OpenLayers.Projection("EPSG:4326"));

	setCookie(lonlat.lon, lonlat.lat, map.getZoom(), 
		getURL_checkboxes(false, false), document.myform.lang.value)
}

function setCookie(lon, lat, zoom, hiddenChecks, lang) {
	var expiry = new Date();
	expiry.setYear(expiry.getFullYear() + 10);

	document.cookie = 'keepright_cookie=' +
		lon + '|' +
		lat + '|' +
		zoom + '|' +
		hiddenChecks + '|' +
		lang +
		'; expires=' + expiry.toGMTString();
}



// change lang parameter in cookie, leave all others untouched
function setLang(lang) {
	if (document.cookie.length>0) {
		var parts = document.cookie.split('|');
		if (parts.length>=4) {
			if (parts[4].indexOf(';')>0)
				parts[4] = lang + parts[4].substr(parts[4].indexOf(';'));
			else
				parts[4] = lang

			document.cookie = parts.join('|');
		} else {
			setCookie('', '', '', '', lang)
		}
		//alert(document.cookie);
	} else {
		setCookie('', '', '', '', lang)
	}
}

// update edit-in-potlatch-link and links for rss/gpx export
// call this after a pan and after changing checkboxes
function updateLinks() {

	var pos = map.getCenter().clone();
	var lonlat = pos.transform(map.getProjectionObject(), new OpenLayers.Projection("EPSG:4326"));

	// update edit-in-potlatch-link
	var editierlink = document.getElementById('editierlink');
	editierlink.href="http://www.openstreetmap.org/edit?lat=" + lonlat.lat + "&lon=" + lonlat.lon + "&zoom=" + map.getZoom();


	// update links for rss/gpx export
	var rsslink=document.getElementById('rsslink');
	var gpxlink=document.getElementById('gpxlink');
	var geojsonlink=document.getElementById('geojsonlink');
	var b=map.getExtent();
	var bbox = b.transform(map.getProjectionObject(), new OpenLayers.Projection("EPSG:4326"));

	var url = 'export.php?format=';
	var params = getURL_checkboxes() + '&left=' + bbox.left + '&bottom=' + bbox.bottom + '&right=' + bbox.right + '&top=' + bbox.top;

	rsslink.href = url + 'rss&' + params;
	gpxlink.href = url + 'gpx&' + params;
	geojsonlink.href = url + 'geojson&' + params;
}



// reload the error types and the permalink,
// which includes the error type selection
// after every onClick for error_type checkboxes
function checkbox_click() {
	pois.loadText();
	plnk.updateLink();
	updateCookie();
	updateLinks();
}


// build the list of error type checkbox states for use in URLs
// echo the error_type number for every active checkbox, separated with ','
// by default the var.name "ch=" is put in front of the string, this
// can be turned off with the optional boolean parameter
// setting the second parameter to false makes the function return all
// checkboxes that are _not_ checked (all hidden error types)
function getURL_checkboxes(includeVariableName, listActiveCheckboxes) {
	var loc="";

	if (includeVariableName === undefined) {
		includeVariableName = true;
	}

	if (listActiveCheckboxes === undefined) {
		listActiveCheckboxes = true;
	}

	if (includeVariableName) {
		loc="ch=0";
	} else {
		loc="0";
	}

	// append error types for any checked checkbox that is called "ch[0-9]+"
	for (var i = 0; i < document.myform.elements.length; ++i) {
		var el=document.myform.elements[i];
		if (el.type == "checkbox" && el.name.match(/ch[0-9]+/) != null) {
			if (el.checked == listActiveCheckboxes)
				loc+="," + el.name.substr(2);
		}
	}
	return loc;
}





///////////////////////////////////////////////////////////////////////////////////////////
// start of file outline.js
// expandable tree structure
///////////////////////////////////////////////////////////////////////////////////////////


// Fully accessible collapsible outlines. JavaScript code
// copyright 2007, Boutell.Com, Inc.
//
// See http://www.boutell.com/newfaq/ for more information.
//
// Permission granted to use, republish, sell and otherwise
// benefit from this code as you see fit, provided you keep
// this notice intact. You may remove comments below this line.
//
// END OF NOTICE
//
// INSTRUCTIONS: this WON'T WORK unless you do the following in the
// document that includes it:
//
// 1. In the <head> element of your page, bring in this code:
//
//    <script src="resizing_background.js">
//    </script>
//
// 2. Call outlineInit() from your onLoad handler:
//
//    <body onLoad="outlineInit()">
//
//    If you need other code in onLoad, that's fine. Just separate the
//    calls with semicolons ( ; ).
//
// 3. To create your outline, write a nested list with the 
//    <ul>... <li>...</li>...  </ul> elements as you normally would.
//    Make sure the TOP <ul> element is a member of the outline class.
//    DO NOT assign the outline class to any nested <ul> elements beneath.
//
//    THIS IS THE RIGHT WAY:
//
//    <ul class="outline">
//      <li>Item One
//        <ul> <!-- This ul does NOT get class="outline" -->
//          <li>Subitem One</li>
//        </ul> 
//      </li>
//      Et cetera...
//    </ul>
//
// 4. That's it! You're good to go. Yes, really.

var outlineItems = new Array();

function outlineInit()
{
	var elements = outlineGetTopLevelLists();
	for (var i = 0; (i < elements.length); i++) {
		outlineInitOutline(elements[i]);
	}
}

function outlineInitOutline(outline)
{
	var kids = outline.childNodes;
	for (var i = 0; (i < kids.length); i++) {
		var kid = kids[i];
		if (kid.nodeName == "LI") {
			outlineInitItem(kid);
		}
	}
}

function outlineInitItem(item)
{
	var kids = item.childNodes;
	var hasKids = false;
	var outlines = new Array();
	for (var i = 0; (i < kids.length); i++) {
		var kid = kids[i];	
		if (kid.nodeName == "UL") {
			kid.style.display = "none";
			outlineInitOutline(kid);
			hasKids = true;
			outlines[outlines.length] = kid;
		}
	}
	if (hasKids) {
		item.style.cursor = "pointer";
		var len = outlineItems.length;
		outlineItems[len] = item;
		// We can't just modify item.innerHTML, because that would
		// invalidate JavaScript objects that already refer to
		// other elements in the outlineItems array. So we use
		// the clunky DOM way of creating a span element. Then we
		// tuck the "a" element inside it so we can use
		// innerHTML for that and avoid various IE bugs.
		var span = document.createElement("span");
		span.innerHTML = "<a href='#' " +
			"onClick='outlineItemClickByOffset(" + len + 
			"); return false' " +
			"class='olink'>" +
			"<img class='oimg' alt='Open' src='img/oopen.png'></a>";
		item.insertBefore(span, kids[0]);
		item.onclick = outlineItemClick;
	}
}

function outlineGetTarget(evt)
{
	var target;
        if (!evt) {
                // Old IE
                evt = window.event;
        }
	// Prevent double event firing (sigh)
	evt.cancelBubble = true;
	if (evt.stopPropagation) {
		evt.stopPropagation();
	}
        var target = evt.target;
        if (!target) {
                // Old IE
                target = evt.srcElement;
        }
	return target;
}

function outlineItemClickByOffset(id)
{
	outlineItemClickBody(outlineItems[id]);
}

function outlineItemClick(evt)
{
	target = outlineGetTarget(evt);
	outlineItemClickBody(target);
}

function outlineItemClickBody(target)
{
	var closed = true;
	var kids = target.childNodes;
	var hasKids = false;
	for (var i = 0; (i < kids.length); i++) {
		var kid = kids[i];	
		if (kid.nodeName == "UL") {
			if (kid.style.display == "none") {
				kid.style.display = "block";
			} else {	
				kid.style.display = "none";
				closed = false;
			}
			hasKids = true;
		}
	}
	if (!hasKids) {
		// We're here because of a click on a
		// childless node. Ignore that.
		return;
	}	
	var img = outlineGetImg(target);
	if (closed) {
		// We've just opened it, show close button
		img.src = "img/oclose.png";
		img.alt = "Close";
	} else {
		img.src = "img/oopen.png";
		img.alt = "Open";
	}
}
	
function outlineGetImg(target)
{
	return outlineGetDescendantWithClassName(target, "oimg");
}

function outlineGetDescendantWithClassName(parent, cn)
{
	// Regular expression: beginning with class name, or
	// class name preceded by a space; and ending with class name, or
	// class name followed by a space. Covers the ways a single class
	// name can appear with or without others in the className attribute.
	var elements = parent.childNodes;
	var length = elements.length;
	var i;
	var regexp = new RegExp("(^| )" + cn + "( |$)");
	for (i = 0; (i < length); i++) {
		if (regexp.test(elements[i].className)) {
			return elements[i];
		}
		var result = outlineGetDescendantWithClassName(
			elements[i], cn);	
		if (result) {
			return result;
		}
	}
	return null;
}

function outlineGetTopLevelLists()
{
	// Regular expression: beginning with class name, or
	// class name preceded by a space; and ending with class name, or
	// class name followed by a space. Covers the ways a single class
	// name can appear with or without others in the className attribute.
	var cn = "outline";
	var elements = document.getElementsByTagName("ul");
	var length = elements.length;
	var i;
	var regexp = new RegExp("(^| )" + cn + "( |$)");
	var results = new Array();
	for (i = 0; (i < length); i++) {
		if (regexp.test(elements[i].className)) {
			results.push(elements[i]);
		}
	}
	return results;
}





///////////////////////////////////////////////////////////////////////////////////////////
// start of file tristate-0.9.2.js
// tristate checkboxes
///////////////////////////////////////////////////////////////////////////////////////////

/* 
 --- Tristate Checkbox ---
v 0.9.2 19th Dec 2008
By Shams Mahmood
http://shamsmi.blogspot.com 

* Changed image URLS
* added click_event parameter. This is a user defined
  function that is called as on-click handler on the tri state
  checkbox

*/

var STATE_NONE = 0;
var STATE_SOME = 1;
var STATE_ALL = 2;

var UNCHECKED_NORM = 'UNCHECKED_NORM';
var UNCHECKED_HILI = 'UNCHECKED_HILI';
var INTERMEDIATE_NORM = 'INTERMEDIATE_NORM';
var INTERMEDIATE_HILI = 'INTERMEDIATE_HILI';
var CHECKED_NORM = 'CHECKED_NORM';
var CHECKED_HILI = 'CHECKED_HILI';

var DEFAULT_CONFIG = {
	UNCHECKED_NORM : 'http://keepright.ipax.at/img/unchecked.gif',
	UNCHECKED_HILI : 'http://keepright.ipax.at/img/unchecked_highlighted.gif',
	INTERMEDIATE_NORM : 'http://keepright.ipax.at/img/intermediate.gif',
	INTERMEDIATE_HILI : 'http://keepright.ipax.at/img/intermediate_highlighted.gif',
	CHECKED_NORM : 'http://keepright.ipax.at/img/checked.gif',
	CHECKED_HILI : 'http://keepright.ipax.at/img/checked_highlighted.gif'
};

function getNextStateFromValue(theValue) {
	if (theValue == STATE_SOME) { return STATE_ALL; }
	if (theValue == STATE_ALL) { return STATE_NONE; }
	return STATE_SOME;
}
function getStateFromValue(theValue, highlightedState) {
	if (theValue == STATE_SOME) { return (!highlightedState) ? INTERMEDIATE_NORM : INTERMEDIATE_HILI; }
	if (theValue == STATE_ALL) { return (!highlightedState) ? CHECKED_NORM : CHECKED_HILI; }
	return (!highlightedState) ? UNCHECKED_NORM : UNCHECKED_HILI;
}

function getFieldAndContainerIds(imageId) {
	var triStateBoxId = imageId.substring(0, imageId.length - '.Img'.length);
	var triStateBoxFieldId = document.getElementById(triStateBoxId + '.Field').value;
	var triStateBoxContainerNode = document.getElementById(triStateBoxId + '.Container');
	var triStateBoxContainerNodeId = '';
	if (triStateBoxContainerNode) {
		triStateBoxContainerNodeId = triStateBoxContainerNode.value;
	}
	return [triStateBoxFieldId, triStateBoxContainerNodeId];
}

function getAllCheckboxesInContainer(triStateBoxContainerNodeId) {
	if (triStateBoxContainerNodeId == '') {
		return [];
	}
	var triStateBoxContainerNode = document.getElementById(triStateBoxContainerNodeId);
	var inputElements = triStateBoxContainerNode.getElementsByTagName('input');
	var checkboxes = new Array();
	var index = 0;

	for (var x = 0; x < inputElements.length; x++) {
		var loopElement = inputElements[x];
		if (loopElement.type == 'checkbox') {
			checkboxes[index++] = loopElement;
		}
	}

	return checkboxes;
}
function selectOrUnselectBoxes(checkBoxes, selectBoxes) {
	for (var x in checkBoxes) {
		checkBoxes[x].checked = selectBoxes;
	}
}
function areAllBoxesInGivenCheckedState(checkBoxes, boxesSelected) {
	var result = true;
	for (var x = 0; x < checkBoxes.length; x++) {
		if (checkBoxes[x].checked != boxesSelected) {
			result = false;
			break;
		}
	}
	return result;
}

function replaceImage(imageId, imageSrc) {
	var image = document.getElementById(imageId);
	if (image.src != imageSrc) {
		image.src = imageSrc;
	}
}
function mouseOverOutOfImage(imageId, mouseOverMode) {

	var fieldAndContainerIds = getFieldAndContainerIds(imageId);
	var triStateBoxField = document.getElementById(fieldAndContainerIds[0]);

	var currentState = getStateFromValue(triStateBoxField.value, mouseOverMode);
	return DEFAULT_CONFIG[currentState];
}
function onMouseOverImage(imageId) {
	return function() {
		var imageSrc = mouseOverOutOfImage(imageId, true);
		replaceImage(imageId, imageSrc)
	};
}
function onMouseOutImage(imageId) { 
	return function() {
		var imageSrc = mouseOverOutOfImage(imageId, false);
		replaceImage(imageId, imageSrc)
	};
}
function onTristateImageClick(imageId, standAloneMode, click_event) {
	return function() {
		var fieldAndContainerIds = getFieldAndContainerIds(imageId);
		var triStateBoxField = document.getElementById(fieldAndContainerIds[0]);

		var nextState = getNextStateFromValue(triStateBoxField.value);
		if (!standAloneMode && nextState == STATE_SOME) {
			nextState = getNextStateFromValue(nextState);
		}
		triStateBoxField.value = nextState;

		if (fieldAndContainerIds[1] != '') {
			var allTheCheckboxes = getAllCheckboxesInContainer(fieldAndContainerIds[1]);
			selectOrUnselectBoxes(allTheCheckboxes, nextState == STATE_ALL);
		}

		var imageSrc = mouseOverOutOfImage(imageId, true);
		replaceImage(imageId, imageSrc);
		click_event();
	}
}
function onCheckboxClick(imageId, checkBoxId) {
	return function() {
		var fieldAndContainerIds = getFieldAndContainerIds(imageId);
		var allTheCheckboxes = getAllCheckboxesInContainer(fieldAndContainerIds[1]);

		var triStateBoxField = document.getElementById(fieldAndContainerIds[0]);
		updateStateAndImage(allTheCheckboxes, triStateBoxField, imageId);
	}
}

function updateStateAndImage(allTheCheckboxes, triStateBoxField, imageId) {
	if (allTheCheckboxes.length > 0) {
		var allBoxesSelected = areAllBoxesInGivenCheckedState(allTheCheckboxes, true);
		var allBoxesUnselected = areAllBoxesInGivenCheckedState(allTheCheckboxes, false);

		if (allBoxesSelected) {
			triStateBoxField.value = STATE_ALL;
		} else if (allBoxesUnselected) {
			triStateBoxField.value = STATE_NONE;
		} else {
			triStateBoxField.value = STATE_SOME;
		}
	}
	var imageSrc = mouseOverOutOfImage(imageId, false);
	replaceImage(imageId, imageSrc);
}
function createHiddenStateField(triStateBoxNode, triStateBoxFieldId) {
	var triStateBoxField = document.createElement('input');
	triStateBoxField.id = triStateBoxFieldId;
	triStateBoxField.type = 'hidden';
	triStateBoxField.value = STATE_NONE;
	triStateBoxNode.appendChild(triStateBoxField);
	return triStateBoxField;
}
function createTriStateImageNode(triStateBoxNode, imageNodeId, standAloneMode, click_event) {
	var imageNode = new Image();
	imageNode.id = imageNodeId;
	imageNode.src = DEFAULT_CONFIG[UNCHECKED_NORM];
	triStateBoxNode.appendChild(imageNode);
	if (triStateBoxNode.addEventListener) {
        triStateBoxNode.addEventListener('mouseover', onMouseOverImage(imageNode.id), false);
		triStateBoxNode.addEventListener('mouseout', onMouseOutImage(imageNode.id), false);
		triStateBoxNode.addEventListener('click', onTristateImageClick(imageNode.id, standAloneMode, click_event), false);
	} else if (triStateBoxNode.attachEvent) {
		triStateBoxNode.attachEvent('onmouseover', onMouseOverImage(imageNode.id));
		triStateBoxNode.attachEvent('onmouseout', onMouseOutImage(imageNode.id));
		triStateBoxNode.attachEvent('onclick', onTristateImageClick(imageNode.id, standAloneMode, click_event));
	}
}
function createFieldNameHiddenField(triStateBoxNode, fieldNameNodeId, triStateBoxFieldId) {
	var fieldNode = document.createElement('input');
	fieldNode.id = fieldNameNodeId;
	fieldNode.type = 'hidden';
	fieldNode.value = triStateBoxFieldId;
	triStateBoxNode.appendChild(fieldNode);
}
function createContainerNameHiddenField(triStateBoxNode, containerNameNodeId, triStateBoxContainerId) {
	var containerNode = document.createElement('input');
	containerNode.id = containerNameNodeId;
	containerNode.type = 'hidden';
	containerNode.value = triStateBoxContainerId;
	triStateBoxNode.appendChild(containerNode);
}
function attachOnclickEventsToDependentBoxes(triStateBoxContainerId, imageNodeId) {
	var allTheCheckboxes = getAllCheckboxesInContainer(triStateBoxContainerId);
	for (var x in allTheCheckboxes) {
		var loopCheckBox = allTheCheckboxes[x];
		if (loopCheckBox.addEventListener) {
	        loopCheckBox.addEventListener('click', onCheckboxClick(imageNodeId, loopCheckBox.id), false);
		} else if (loopCheckBox.attachEvent) {
			loopCheckBox.attachEvent('onclick', onCheckboxClick(imageNodeId, loopCheckBox.id));
		}
	}
	return allTheCheckboxes;
}
function initTriStateCheckBox(triStateBoxId, containerOrStateFieldId, standAloneMode, click_event) {

	var triStateBoxNode = document.getElementById(triStateBoxId);

	var triStateBoxContainerId = containerOrStateFieldId;
	var triStateBoxFieldId = triStateBoxId + '.Value';
	if (standAloneMode) {
		triStateBoxContainerId = '';
		triStateBoxFieldId = containerOrStateFieldId;
	}

	var triStateBoxTextNode = triStateBoxNode.childNodes[0];
	triStateBoxNode.removeChild(triStateBoxTextNode);

	var triStateBoxField = document.getElementById(triStateBoxFieldId);
	if (!standAloneMode) {
		triStateBoxField = createHiddenStateField(triStateBoxNode, triStateBoxFieldId);
	}

	var imageNodeId = triStateBoxId + '.Img';
	createTriStateImageNode(triStateBoxNode, imageNodeId, standAloneMode, click_event);

	var fieldNameNodeId = triStateBoxId + '.Field';
	createFieldNameHiddenField(triStateBoxNode, fieldNameNodeId, triStateBoxFieldId);

	if (!standAloneMode) {
		var containerNameNodeId = triStateBoxId + '.Container';
		createContainerNameHiddenField(triStateBoxNode, containerNameNodeId, triStateBoxContainerId);
	}

	triStateBoxNode.appendChild(triStateBoxTextNode);

	var allTheCheckboxes = attachOnclickEventsToDependentBoxes(triStateBoxContainerId, imageNodeId);
	updateStateAndImage(allTheCheckboxes, triStateBoxField, imageNodeId);
}
