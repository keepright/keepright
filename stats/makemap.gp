set terminal png size 2300,1500
set output '../results/heatmap.png'

set palette defined (0 "black", 0.001 "#000088", .3 "blue", .9 "green", 1.4 "yellow", 2 "red", 2.2 "#ff66ff");
set size ratio 0.775
set cbrange [-1:]


plot [-1800:1800][-800:800] 'nodesmap.dat' using 1:2:3  with image

#Correct projection, but bad look
#R(z)=z/10/180*3.1415
#M(x)=.5 * log((1+sin(R(x)))/(1-sin(R(x))))*573
#set view map
#splot  [-1800:1800][-1396:1396] 'nodesmap.dat' using 1:2:3  with pm3d
