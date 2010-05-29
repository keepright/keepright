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
			var description=feature.attributes.description;
			var comment=feature.attributes.comment.replace(/<br>/g, "\n");
			var state=feature.attributes.state;
			var lat=feature.attributes.lat;
			var lon=feature.attributes.lon;

			data['popupContentHTML'] ='<h5>'+error_name+', '+object_type+' <a href="http://www.openstreetmap.org/browse/'+object_type_EN+'/'+object_id+'" target="_blank">'+object_id+'</a></h5>'+
			'<p class="p1">'+description+'</p>'+

			'<p class="p2">'+txt4+' <a href="http://localhost:8111/load_and_zoom?left=' + (lon-0.001) + '&right=' + (lon-(-0.001)) + '&top=' + (lat-(-0.001)) + '&bottom=' + (lat-0.001) + '&select=' + object_type_EN + object_id + '" target="hiddenIframe" title="'+txt6+'">'+txt5+'</a> ' +

			'<a href="http://www.openstreetmap.org/edit?lat=' + lat + '&lon=' + lon + '&zoom=18&' + object_type_EN +'='+ object_id + '" target="_blank">'+txt7+'</a></p>' +

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
			txt14 + '<a href="report_map.php?schema='+schema+'&error='+error_id+'">'+error_id+'</a><br>' + txt15 + ' ' + object_type + ': ' + object_timestamp + '</small>';
		}


		data['overflow'] = feature.attributes.overflow || "auto";

		var markerFeature = new OpenLayers.Feature(thisObject, location, data);
		markerFeature.popupClass=OpenLayers.Class(OpenLayers.Popup.FramedCloud);


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
