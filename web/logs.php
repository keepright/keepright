<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN">
<html>
  <head>
    <title>data consistency checks for OSM</title>
  </head>
  <body>
<a href="/"><img border=0 src="keepright.png" alt="keep-right logo"></a>
data consistency checks for <a href="http://www.openstreetmap.org">OSM</a><hr>

<h2>historic logs</h2>

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

