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
	var layerMapnik = new OpenLayers.Layer.OSM.Mapnik("Mapnik");
	map.addLayer(layerMapnik);

	// add the osmarender layer
// 	var layerOsmarender = new OpenLayers.Layer.OSM.Osmarender("Osmarender");
// 	map.addLayer(layerOsmarender);

	// add the open cycle map layer
	var layerCycle = new OpenLayers.Layer.OSM.CycleMap("OSM Cycle Map");
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

	// update all the checkboxes
	for (var i = 0; i < document.myform.elements.length; ++i) {
		var el=document.myform.elements[i];
		if (el.type == "checkbox" && el.name.match(/ch[0-9]+/) != null) {
			el.checked=new_value;
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
	var b=map.getExtent();
	var bbox = b.transform(map.getProjectionObject(), new OpenLayers.Projection("EPSG:4326"));

	var url = 'export.php?format=';
	var params = getURL_checkboxes() + '&left=' + bbox.left + '&bottom=' + bbox.bottom + '&right=' + bbox.right + '&top=' + bbox.top;

	rsslink.href = url + 'rss&' + params;
	gpxlink.href = url + 'gpx&' + params;
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