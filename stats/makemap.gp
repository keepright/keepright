set terminal png size 2300,2000
set output '../results/heatmap.png'


min(x,y)=(x<y)?x:y
max(x,y)=(x>y)?x:y
R(x)=x/10/180*3.1415
M(x)=.5 * log((1+sin(R(x)))/(1-sin(R(x))))*573

plot [-1800:1800][-1396:1396] 'nodesmap.dat' using 1:(M($2)):3  with image
