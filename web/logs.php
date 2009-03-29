<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN">
<html>
  <head>
    <title>data consistency checks for OSM</title>
  </head>
  <body>
<a href="/"><img border=0 src="keepright.png" alt="keep-right logo"></a>
data consistency checks for <a href="http://www.openstreetmap.org">OSM</a><hr>

<h2>historic logs</h2>

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

