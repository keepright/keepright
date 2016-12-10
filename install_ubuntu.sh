# demo install script for use on plain ubuntu 16.04 distro


# install packages
sudo apt install default-jre php php-mysql php-pgsql php-xml php-curl subversion wput postgresql postgresql-client postgis

# create a postgres superuser
sudo -u postgres createuser -s -P -d osm

# create a keepright directory
cd
mkdir keepright
cd keepright

# get osmosis in desired version
wget http://bretth.dev.openstreetmap.org/osmosis-build/osmosis-0.39.tgz
tar xvfz osmosis-0.39.tgz
chmod a+x osmosis-0.39/bin/osmosis

# get keepright sources
svn co svn://svn.code.sf.net/p/keepright/code/ keepright

# get a planet file
wget -O keepright/planet/at.pbf http://download.geofabrik.de/europe/austria-latest.osm.pbf

# create a config file
cp keepright/config/keepright.config.template ~/.keepright

# force user to edit config file
nano ~/.keepright

echo "start by running 'php ~/keepright/keepright/checks/process_schema.php at'"
