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
