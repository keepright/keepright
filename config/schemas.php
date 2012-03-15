<?php

// ###########################################
// Cutting parameters for splitting the planet
// ###########################################

// please refer to config/planet.odg for an overview

// additional space around the borders (in meters)
// used for overlapping the pieces.
// errors in the margin space are discarded
$config['cutting_margin']=20000;

// schema boundaries are always given _without_ margin
// i.e. margin is added automatically


// schema names are varchar(8) in errors-table and error_view-table


// testing schema Mödling, Austria
$schemas['md']['left']=16.20;
$schemas['md']['right']=16.35;
$schemas['md']['top']=48.08;
$schemas['md']['bottom']=48.06;
$schemas['md']['user']='testMD';

// testing schema Austria
$schemas['at']['left']=9.30;
$schemas['at']['right']=17.30;
$schemas['at']['top']=49.00;
$schemas['at']['bottom']=46.20;
$schemas['at']['user']='testAT';


$schemas['2']['left']=1.8;
$schemas['2']['right']=12;
$schemas['2']['top']=90;
$schemas['2']['bottom']=55;
$schemas['2']['user']='cetest';

$schemas['4']['left']=7.5;
$schemas['4']['right']=10;
$schemas['4']['top']=55;
$schemas['4']['bottom']=49.3;
$schemas['4']['user']='pc9';

$schemas['7']['left']=3.5;
$schemas['7']['right']=6.7;
$schemas['7']['top']=49.3;
$schemas['7']['bottom']=45;
$schemas['7']['user']='ceserver';

$schemas['15']['left']=18.3;
$schemas['15']['right']=51.4;
$schemas['15']['top']=45;
$schemas['15']['bottom']=35;
$schemas['15']['user']='cetest';

$schemas['17']['left']=-30;
$schemas['17']['right']=51.4;
$schemas['17']['top']=35;
$schemas['17']['bottom']=-90;
$schemas['17']['user']='pc9';

$schemas['18']['left']=12;
$schemas['18']['right']=23;
$schemas['18']['top']=90;
$schemas['18']['bottom']=55;
$schemas['18']['user']='pc9';

$schemas['19']['left']=23;
$schemas['19']['right']=51.4;
$schemas['19']['top']=90;
$schemas['19']['bottom']=55;
$schemas['19']['user']='pc9';

$schemas['20']['left']=-180;
$schemas['20']['right']=-30;
$schemas['20']['top']=90;
$schemas['20']['bottom']=50;
$schemas['20']['user']='ceserver';

$schemas['21']['left']=-180;
$schemas['21']['right']=-116;
$schemas['21']['top']=50;
$schemas['21']['bottom']=46;
$schemas['21']['user']='cetest';

$schemas['22']['left']=-116;
$schemas['22']['right']=-99;
$schemas['22']['top']=50;
$schemas['22']['bottom']=46;
$schemas['22']['user']='cetest';

$schemas['23']['left']=-99;
$schemas['23']['right']=-30;
$schemas['23']['top']=50;
$schemas['23']['bottom']=46;
$schemas['23']['user']='pc9';

$schemas['24']['left']=-117.3;
$schemas['24']['right']=-111.6;
$schemas['24']['top']=46;
$schemas['24']['bottom']=42;
$schemas['24']['user']='cetest';

$schemas['25']['left']=-77.4;
$schemas['25']['right']=-72;
$schemas['25']['top']=46;
$schemas['25']['bottom']=42;
$schemas['25']['user']='cetest';

$schemas['26']['left']=-180;
$schemas['26']['right']=-117.3;
$schemas['26']['top']=46;
$schemas['26']['bottom']=42;
$schemas['26']['user']='cetest';

$schemas['27']['left']=-111.6;
$schemas['27']['right']=-100.3;
$schemas['27']['top']=46;
$schemas['27']['bottom']=42;
$schemas['27']['user']='cetest';

$schemas['28']['left']=-100.3;
$schemas['28']['right']=-88.9;
$schemas['28']['top']=46;
$schemas['28']['bottom']=42;
$schemas['28']['user']='ceserver';

$schemas['29']['left']=-88.9;
$schemas['29']['right']=-77.4;
$schemas['29']['top']=46;
$schemas['29']['bottom']=42;
$schemas['29']['user']='ceserver';

$schemas['30']['left']=-72;
$schemas['30']['right']=-30;
$schemas['30']['top']=46;
$schemas['30']['bottom']=42;
$schemas['30']['user']='cetest';

$schemas['31']['left']=-180;
$schemas['31']['right']=-120.6;
$schemas['31']['top']=42;
$schemas['31']['bottom']=38;
$schemas['31']['user']='cetest';

$schemas['32']['left']=-113.7;
$schemas['32']['right']=-102.3;
$schemas['32']['top']=42;
$schemas['32']['bottom']=38;
$schemas['32']['user']='cetest';

$schemas['33']['left']=-102.3;
$schemas['33']['right']=-92;
$schemas['33']['top']=42;
$schemas['33']['bottom']=40;
$schemas['33']['user']='cetest';

$schemas['34']['left']=-92;
$schemas['34']['right']=-84.5;
$schemas['34']['top']=42;
$schemas['34']['bottom']=40;
$schemas['34']['user']='cetest';

$schemas['35']['left']=-79.4;
$schemas['35']['right']=-75;
$schemas['35']['top']=42;
$schemas['35']['bottom']=40;
$schemas['35']['user']='cetest';

$schemas['36']['left']=-180;
$schemas['36']['right']=-116;
$schemas['36']['top']=38;
$schemas['36']['bottom']=34;
$schemas['36']['user']='ceserver';

$schemas['37']['left']=-116;
$schemas['37']['right']=-100.3;
$schemas['37']['top']=38;
$schemas['37']['bottom']=34;
$schemas['37']['user']='cetest';

$schemas['38']['left']=-100.3;
$schemas['38']['right']=-94.6;
$schemas['38']['top']=38;
$schemas['38']['bottom']=34;
$schemas['38']['user']='cetest';

$schemas['39']['left']=-88.9;
$schemas['39']['right']=-85;
$schemas['39']['top']=38;
$schemas['39']['bottom']=36.4;
$schemas['39']['user']='cetest';

$schemas['40']['left']=-77.6;
$schemas['40']['right']=-30;
$schemas['40']['top']=38;
$schemas['40']['bottom']=34;
$schemas['40']['user']='cetest';

$schemas['41']['left']=-180;
$schemas['41']['right']=-108.3;
$schemas['41']['top']=34;
$schemas['41']['bottom']=20;
$schemas['41']['user']='pc9';

$schemas['42']['left']=-108.3;
$schemas['42']['right']=-97.5;
$schemas['42']['top']=34;
$schemas['42']['bottom']=30;
$schemas['42']['user']='cetest';

$schemas['43']['left']=-97.5;
$schemas['43']['right']=-90.9;
$schemas['43']['top']=34;
$schemas['43']['bottom']=30;
$schemas['43']['user']='cetest';

$schemas['44']['left']=-90.9;
$schemas['44']['right']=-85.7;
$schemas['44']['top']=34;
$schemas['44']['bottom']=30;
$schemas['44']['user']='cetest';

$schemas['45']['left']=-83.2;
$schemas['45']['right']=-30;
$schemas['45']['top']=34;
$schemas['45']['bottom']=30;
$schemas['45']['user']='cetest';

$schemas['46']['left']=-180;
$schemas['46']['right']=-95;
$schemas['46']['top']=30;
$schemas['46']['bottom']=14;
$schemas['46']['user']='cetest';

$schemas['47']['left']=-180;
$schemas['47']['right']=-30;
$schemas['47']['top']=14;
$schemas['47']['bottom']=-90;
$schemas['47']['user']='pc9';

$schemas['48']['left']=51.4;
$schemas['48']['right']=110;
$schemas['48']['top']=90;
$schemas['48']['bottom']=-90;
$schemas['48']['user']='pc9';

$schemas['50']['left']=110;
$schemas['50']['right']=180;
$schemas['50']['top']=-10;
$schemas['50']['bottom']=-90;
$schemas['50']['user']='australia';

$schemas['51']['left']=-120.6;
$schemas['51']['right']=-113.7;
$schemas['51']['top']=42;
$schemas['51']['bottom']=38;
$schemas['51']['user']='cetest';

$schemas['52']['left']=-102.3;
$schemas['52']['right']=-90.9;
$schemas['52']['top']=40;
$schemas['52']['bottom']=38;
$schemas['52']['user']='cetest';

$schemas['53']['left']=-84.5;
$schemas['53']['right']=-79.4;
$schemas['53']['top']=42;
$schemas['53']['bottom']=40;
$schemas['53']['user']='cetest';

$schemas['54']['left']=-90.9;
$schemas['54']['right']=-85.1;
$schemas['54']['top']=40;
$schemas['54']['bottom']=38;
$schemas['54']['user']='cetest';

$schemas['55']['left']=-85.1;
$schemas['55']['right']=-79.4;
$schemas['55']['top']=40;
$schemas['55']['bottom']=38;
$schemas['55']['user']='cetest';

$schemas['56']['left']=-75;
$schemas['56']['right']=-30;
$schemas['56']['top']=42;
$schemas['56']['bottom']=40;
$schemas['56']['user']='cetest';

$schemas['57']['left']=-79.4;
$schemas['57']['right']=-77.2;
$schemas['57']['top']=40;
$schemas['57']['bottom']=38;
$schemas['57']['user']='cetest';

$schemas['58']['left']=-77.2;
$schemas['58']['right']=-30;
$schemas['58']['top']=40;
$schemas['58']['bottom']=38;
$schemas['58']['user']='cetest';

$schemas['59']['left']=-94.6;
$schemas['59']['right']=-88.9;
$schemas['59']['top']=38;
$schemas['59']['bottom']=34;
$schemas['59']['user']='cetest';

$schemas['60']['left']=-85;
$schemas['60']['right']=-81.4;
$schemas['60']['top']=38;
$schemas['60']['bottom']=36.4;
$schemas['60']['user']='cetest';

$schemas['61']['left']=-81.4;
$schemas['61']['right']=-78.9;
$schemas['61']['top']=38;
$schemas['61']['bottom']=36.4;
$schemas['61']['user']='cetest';

$schemas['62']['left']=-88.9;
$schemas['62']['right']=-83.8;
$schemas['62']['top']=36.4;
$schemas['62']['bottom']=34;
$schemas['62']['user']='cetest';

$schemas['63']['left']=-83.8;
$schemas['63']['right']=-81.4;
$schemas['63']['top']=36.4;
$schemas['63']['bottom']=34;
$schemas['63']['user']='cetest';

$schemas['64']['left']=-81.4;
$schemas['64']['right']=-78.9;
$schemas['64']['top']=36.4;
$schemas['64']['bottom']=34;
$schemas['64']['user']='cetest';

$schemas['65']['left']=-85.7;
$schemas['65']['right']=-83.2;
$schemas['65']['top']=34;
$schemas['65']['bottom']=30;
$schemas['65']['user']='cetest';

$schemas['68']['left']=-95;
$schemas['68']['right']=-30;
$schemas['68']['top']=30;
$schemas['68']['bottom']=14;
$schemas['68']['user']='pc9';

$schemas['69']['left']=-78.9;
$schemas['69']['right']=-77.6;
$schemas['69']['top']=38;
$schemas['69']['bottom']=34;
$schemas['69']['user']='cetest';

$schemas['72']['left']=1.8;
$schemas['72']['right']=7.5;
$schemas['72']['top']=51;
$schemas['72']['bottom']=49.3;
$schemas['72']['user']='pc9';

$schemas['73']['left']=110;
$schemas['73']['right']=131;
$schemas['73']['top']=90;
$schemas['73']['bottom']=-10;
$schemas['73']['user']='cetest';

$schemas['74']['left']=131;
$schemas['74']['right']=139;
$schemas['74']['top']=90;
$schemas['74']['bottom']=-10;
$schemas['74']['user']='ceserver';

$schemas['75']['left']=139;
$schemas['75']['right']=180;
$schemas['75']['top']=90;
$schemas['75']['bottom']=-10;
$schemas['75']['user']='ceserver';

$schemas['76']['left']=-30;
$schemas['76']['right']=-1.8;
$schemas['76']['top']=49.3;
$schemas['76']['bottom']=45;
$schemas['76']['user']='cetest';

$schemas['77']['left']=-1.8;
$schemas['77']['right']=3.5;
$schemas['77']['top']=49.3;
$schemas['77']['bottom']=48.1;
$schemas['77']['user']='ceserver';

$schemas['78']['left']=-1.8;
$schemas['78']['right']=3.5;
$schemas['78']['top']=48.1;
$schemas['78']['bottom']=46.5;
$schemas['78']['user']='ceserver';

$schemas['79']['left']=-1.8;
$schemas['79']['right']=3.5;
$schemas['79']['top']=46.5;
$schemas['79']['bottom']=45;
$schemas['79']['user']='ceserver';

$schemas['80']['left']=14;
$schemas['80']['right']=18;
$schemas['80']['top']=55;
$schemas['80']['bottom']=49.3;
$schemas['80']['user']='pc9';

$schemas['81']['left']=18;
$schemas['81']['right']=51.4;
$schemas['81']['top']=55;
$schemas['81']['bottom']=49.3;
$schemas['81']['user']='pc9';

$schemas['82']['left']=14;
$schemas['82']['right']=19;
$schemas['82']['top']=49.3;
$schemas['82']['bottom']=45;
$schemas['82']['user']='pc9';

$schemas['83']['left']=19;
$schemas['83']['right']=51.4;
$schemas['83']['top']=49.3;
$schemas['83']['bottom']=45;
$schemas['83']['user']='ceserver';

$schemas['84']['left']=-30;
$schemas['84']['right']=0;
$schemas['84']['top']=45;
$schemas['84']['bottom']=35;
$schemas['84']['user']='pc9';

$schemas['85']['left']=0;
$schemas['85']['right']=3.3;
$schemas['85']['top']=45;
$schemas['85']['bottom']=35;
$schemas['85']['user']='ceserver';

$schemas['86']['left']=-30;
$schemas['86']['right']=1.8;
$schemas['86']['top']=90;
$schemas['86']['bottom']=52.3;
$schemas['86']['user']='pc9';

$schemas['87']['left']=-30;
$schemas['87']['right']=1.8;
$schemas['87']['top']=52.3;
$schemas['87']['bottom']=49.3;
$schemas['87']['user']='pc9';

$schemas['88']['left']=3.3;
$schemas['88']['right']=6.5;
$schemas['88']['top']=45;
$schemas['88']['bottom']=35;
$schemas['88']['user']='ceserver';

$schemas['89']['left']=6.5;
$schemas['89']['right']=18.3;
$schemas['89']['top']=45;
$schemas['89']['bottom']=35;
$schemas['89']['user']='pc9';

$schemas['90']['left']=1.8;
$schemas['90']['right']=5.8;
$schemas['90']['top']=55;
$schemas['90']['bottom']=52;
$schemas['90']['user']='pc9';

$schemas['91']['left']=5.8;
$schemas['91']['right']=7.5;
$schemas['91']['top']=55;
$schemas['91']['bottom']=52;
$schemas['91']['user']='pc9';

$schemas['92']['left']=1.8;
$schemas['92']['right']=5.5;
$schemas['92']['top']=52;
$schemas['92']['bottom']=51;
$schemas['92']['user']='pc9';

$schemas['93']['left']=5.5;
$schemas['93']['right']=7.5;
$schemas['93']['top']=52;
$schemas['93']['bottom']=51;
$schemas['93']['user']='pc9';

$schemas['94']['left']=6.7;
$schemas['94']['right']=10;
$schemas['94']['top']=49.3;
$schemas['94']['bottom']=48.1;
$schemas['94']['user']='pc9';

$schemas['95']['left']=6.7;
$schemas['95']['right']=10;
$schemas['95']['top']=48.1;
$schemas['95']['bottom']=46.5;
$schemas['95']['user']='pc9';

$schemas['96']['left']=6.7;
$schemas['96']['right']=10;
$schemas['96']['top']=46.5;
$schemas['96']['bottom']=45;
$schemas['96']['user']='cetest';

$schemas['97']['left']=10;
$schemas['97']['right']=14;
$schemas['97']['top']=49.3;
$schemas['97']['bottom']=48.1;
$schemas['97']['user']='cetest';

$schemas['98']['left']=10;
$schemas['98']['right']=14;
$schemas['98']['top']=48.1;
$schemas['98']['bottom']=46.5;
$schemas['98']['user']='cetest';

$schemas['99']['left']=10;
$schemas['99']['right']=14;
$schemas['99']['top']=46.5;
$schemas['99']['bottom']=45;
$schemas['99']['user']='pc9';

$schemas['100']['left']=10;
$schemas['100']['right']=14;
$schemas['100']['top']=55;
$schemas['100']['bottom']=52.3;
$schemas['100']['user']='pc9';

$schemas['101']['left']=10;
$schemas['101']['right']=14;
$schemas['101']['top']=52.3;
$schemas['101']['bottom']=50.6;
$schemas['101']['user']='pc9';

$schemas['102']['left']=10;
$schemas['102']['right']=14;
$schemas['102']['top']=50.6;
$schemas['102']['bottom']=49.3;
$schemas['102']['user']='pc9';

?>
