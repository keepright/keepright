<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN">
<html>
  <head>
    <title>data consistency checks for OSM</title>
  </head>
  <body>
<a href="/"><img border=0 src="keepright.png" alt="keep-right logo"></a>
data consistency checks for <a href="http://www.openstreetmap.org">OSM</a><hr>

<h2>historic logs</h2>


<h4>2009-05-09</h4>
An updated errors-table went online today! Planet dump was updated as of may 5th 2009.<br><br>

<h4>2009-05-02</h4>
An updated errors-table went online today! Planet dump was updated as of april 28th 2009.<br><br>
Waterways are included by now in the checks 'intersections without junctions' and 'overlapping ways'. This will identify spots where bridges are missing.

<h4>2009-04-10</h4>
An updated errors-table went online today! Planet dump was updated as of april 7th 2009.<br><br>

<h4>2009-04-25</h4>
An updated errors-table went online today! Planet dump was updated as of april 19th 2009.<br><br>
Some checks are modified this week:<br>
* 'almost junctions' will not complain about end-nodes that are tagged as bus stop or as amenity any more. There were many errors on short linking ways connecting amenities with the nearest road (see an <a target="_blank" href="http://openstreetmap.org/?lat=53.597046&lon=9.984201&zoom=18&layers=B000FTF">example</a>). In my opinion these short ways are not necessary but it is not the purpose of this check to show them. Thank you, Sören for the tip!<br>
* 'points of interest without name' will not complain about amenity=bank where the name tag is missing any more. Instead it will require the operator tag to be set. Don't panic about numerous new error markers! Consider it just as a notice... Thank you, Hans for the tip!<br>
* 'intersections without junctions/overlapping ways' won't complain about junctions/overlappings between highways and areas. For example a street leading across a square is a valid <a target="_blank" href="http://www.openstreetmap.org/edit?lat=50.7352246&lon=7.0983218&zoom=19&way=4801445&way=4935206">exception</a> as well as two squares tagged as highway sharing a way segment on their border (<a target="_blank" href="http://www.openstreetmap.org/edit?lat=50.733607&lon=7.10003&zoom=19&way=23659367">example</a>). Thank you, Peter for the tip!

<h4>2009-04-18</h4>
An updated errors-table went online today! Planet dump was updated as of april 14th 2009.<br><br>
The first keepright partner site is now online! Please visit <a href="http://keepright.x10hosting.com" target="_blank">keepright Australia</a>

<h4>2009-04-06</h4>
Just added an updated version of "dead-ended one-ways" errors. The check now includes motorways and motorway_links, which are regarded as one-way streets implicitly. Second there is a new part that searches for colliding one-way streets (one-ways pointing to a single node that cannot be left or from a single node that cannot be reached). Thank you, Ossi, for that hint!

<h4>2009-04-05</h4>
Just added an updated version of "misspelled tags" errors. Because of user feedback I removed some false positives.

<h4>2009-04-04</h4>
An updated errors-table went online today! Planet dump was updated as of march 31th 2009.<br><br>
Please welcome a new check called "misspelled tags". This one tries to find typos in keys and values. Keys and values are split up into two groups: common (frequently used) and uncommon (infrequently used) ones. This check will complain about uncommon keys or values that differ by just one character from a common key or value. It doesn't make sense to look for differences on numbers in this way, so any combination of numbers is replaced by 0. Don't get confused by zeroes in error messages, they are just placeholders!<br><br>
Did you ever wonder why errors on relations were seen so rarely? Well, up to now not a single error on relations was shown on the map because I had no idea where to put the marker. To solve this I put the markers on the center of gravity of all nodes referenced by the relation. Although this may not be ideal for circular routes as the marker is placed in the center, it is better than nothing... Thank you, Michel, for remining me of that issue!

<h4>2009-03-29</h4>
An updated errors-table went online today! Planet dump was updated as of march 25th 2009. Because of my fault this is only a partial update. Checks floating islands, intersections without junctions and overlapping ways are still in the state of last week.<br><br>

<h4>2009-03-21</h4>
An updated errors-table went online today! Planet dump was updated as of march 17th 2009.<br><br>

<h4>2009-03-14</h4>
An updated errors-table went online today! Planet dump was updated as of march 10th 2009.<br><br>

<h4>2009-03-10</h4>
Checks "missing tags" and "highway without ref tag" were redefined and relaxed to better fit real life: "missing tags" won't complain on multipolygon ways tagged as "role inner". "highway without ref tag" does now accept motorways as correctly tagged if any of ref, nat_ref or int_ref tags exist. This change removes 11.686 errors for "missing tags" and 97.054 for "highway without ref tag". Thank you, Ulf, Dermot and Jean-Luc!
<br><br>

<h4>2009-03-07</h4>
An updated errors-table went online today! Planet dump was updated as of march 2nd 2009. Due to an error in the scripts many errors were invisible up to now. Thank you, Hans, for the hint! <br><br>

<h4>2009-02-28</h4>
An updated errors-table went online today! Planet dump was updated as of feb. 22th 2009.<br><br>

<h4>2009-02-21</h4>
An updated errors-table went online today! Planet dump was updated as of feb. 17th 2009.<br><br>
Furthermore a completely rewritten user interface is in place now. This work evolved in cooperation with Hans, who provided valuable input and beta testing feedback. Thank you!<br>
For those who want to find out how much better the new interface is: You can still use the <a href="report_map1.php">old version</a>, but you should not.<br><br>
You may now mark errors as 'temporarily ignorable' if you just corrected the error and don't want other users to loose time with this issue. These errors will be removed from the map if they are indeed closed, otherwise they will jump back to open state during the next update.<br>If you find a false positive, please mark it as 'permanently ignorable' and please give a short comment that helps me improve my check routines!

<h4>2009-02-04</h4>
An updated errors-table went online today! Planet dump was updated as of feb. 2nd 2009

<h4>2009-01-29</h4>
An updated errors-table went online today! Planet dump was updated as of jan. 23 2009

<h4>2009-01-17</h4>
An updated errors-table went online today! Planet dump was updated as of jan. 13 2009

<h4>2009-01-11</h4>
Some issues got corrected because of valuable user feedback. Thank you!
<ul>
<li>&apos;level crossings without tag&apos; did complain about footways connected to &apos;railway=platform&apos; ways. Norberts Feedback solved 885 error messages</li>
<li>&apos;level crossings without tag&apos; did just look for &apos;railway=level_crossing&apos; but ignored &apos;railway=crossing&apos;. Lars&apos; Feedback solved 2477 error messages</li>
</ul>

<h4>2009-01-07</h4>
<ul>
<li>An updated errors-table went online today! Planet dump was updated as of jan. 02 2009</li>
<li>A new check is included to find ways that include individual nodes more than once (loops). This check doesn't provide error messages, just warnings. It is common practice to map isolated parts of areas as leafs connected by a zero-width stalk using the same nodes twice -- I don't want to critisise these. But there are other cases where loops are built accidently.</li>
</ul>

<h4>2008-12-27</h4>
<ul>
<li>An updated errors-table went online today! Planet dump was updated as of dec. 20 2008</li>
<li>A new check is included to find ways that intersect or even run in the same path (overlap).</li>
</ul>


</body>
</html>

