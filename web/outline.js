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
