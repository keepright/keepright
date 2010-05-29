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
        var columns;
        var features = [];
        // length - 1 to allow for trailing new line
        for (var lcv = 0; lcv < (lines.length - 1); lcv++) {
            var currLine = lines[lcv].replace(/^\s*/,'').replace(/\s*$/,'');

            if (currLine.charAt(0) != '#') { /* not a comment */

                if (!columns) {
                    //First line is columns
                    columns = currLine.split('\t');
                } else {
                    var vals = currLine.split('\t');
                    var geometry = new OpenLayers.Geometry.Point(0,0);
                    var attributes = {};
                    var style = {};
                    var icon, iconSize, iconOffset, overflow;
                    var set = false;
                    for (var valIndex = 0; valIndex < vals.length; valIndex++) {
                        if (vals[valIndex]) {
                            if (columns[valIndex] == 'lat') {
                                geometry.y = parseFloat(vals[valIndex]);
				attributes['lat'] = geometry.y;
                                set = true;
                            } else if (columns[valIndex] == 'lon') {
                                geometry.x = parseFloat(vals[valIndex]);
				attributes['lon'] = geometry.x;
                                set = true;
                            } else if (columns[valIndex] == 'title')
                                attributes['title'] = vals[valIndex];
                            else if (columns[valIndex] == 'image' ||
                                     columns[valIndex] == 'icon')
                                style['externalGraphic'] = vals[valIndex];
                            else if (columns[valIndex] == 'iconSize') {
                                var size = vals[valIndex].split(',');
                                style['graphicWidth'] = parseFloat(size[0]);
                                style['graphicHeight'] = parseFloat(size[1]);
                            } else if (columns[valIndex] == 'iconOffset') {
                                var offset = vals[valIndex].split(',');
                                style['graphicXOffset'] = parseFloat(offset[0]);
                                style['graphicYOffset'] = parseFloat(offset[1]);
                            } else if (columns[valIndex] == 'description')
                                attributes['description'] = vals[valIndex];
                             else if (columns[valIndex] == 'overflow')
                                attributes['overflow'] = vals[valIndex];
                             else if (columns[valIndex] == 'error_name')
                                attributes['error_name'] = vals[valIndex];
                             else if (columns[valIndex] == 'error_type')
                                attributes['error_type'] = vals[valIndex];
                              else if (columns[valIndex] == 'schema')
                                attributes['schema'] = vals[valIndex];
                            else if (columns[valIndex] == 'error_id')
                                attributes['error_id'] = vals[valIndex];
                             else if (columns[valIndex] == 'object_timestamp')
                                attributes['object_timestamp'] = vals[valIndex];
                             else if (columns[valIndex] == 'object_type')
                                attributes['object_type'] = vals[valIndex];
                             else if (columns[valIndex] == 'object_type_EN')
                                attributes['object_type_EN'] = vals[valIndex];
                             else if (columns[valIndex] == 'object_id')
                                attributes['object_id'] = vals[valIndex];
                             else if (columns[valIndex] == 'comment')
                                attributes['comment'] = vals[valIndex];
                             else if (columns[valIndex] == 'state')
                                attributes['state'] = vals[valIndex];
			}
                    }
                    if (set) {
                      if (this.internalProjection && this.externalProjection) {
                          geometry.transform(this.externalProjection, 
                                             this.internalProjection); 
                      }
                      var feature = new OpenLayers.Feature.Vector(geometry, attributes, style);
                      features.push(feature);
                    }
                }
            }
        }
        return features;
    },

    CLASS_NAME: "OpenLayers.Format.myTextFormat" 
});
