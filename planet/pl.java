

import java.util.HashMap;
import java.util.Map;

import org.openstreetmap.osmosis.core.pipeline.common.TaskManagerFactory;
import org.openstreetmap.osmosis.core.plugin.PluginLoader;


/*

osmosis-plugin for exporting planet files into tab-separated text files
suitable for postgres COPY commands. This is a modified version of the
Postgresql-Dataset-Dump-Writer task.

some of the changes include:
* special formatting of node ids needed for joining files with bash's JOIN command
* more redundand output of columns (eg. nodes' lat/lon plus x/y coordinates)
* output of nodes' geometry is non-standard: coordinates are in meters, not lat/lon!
* output first and last node for each way


compile this with classpath pointing to osmosis.jar, postgis.jar and postgresql.jar:

cd /home/haraldk/OSM/keepright/planet

javac -cp "/home/haraldk/OSM/osmosis/package/lib/runtime/osmosis-core-0.37-SNAPSHOT.jar:/home/haraldk/OSM/osmosis/package/lib/runtime/postgis-jdbc-1.3.3.jar:/home/haraldk/OSM/osmosis/package/lib/runtime/postgresql-8.4-701.jdbc4.jar:/home/haraldk/OSM/osmosis/package/lib/runtime/osmosis-pgsnapshot-0.37-SNAPSHOT.jar:." *.java

copy resulting .class files into osmosis.jar using your favourite zip program (!)

run with
 ~/OSM/osmosis-0.36/bin/osmosis -p pl --read-xml file=planet.osm --pl

*/

public class pl implements PluginLoader {

	public Map<String, TaskManagerFactory> loadTaskFactories() {

		PostgreSqlMyDatasetDumpWriterFactory factory;
		HashMap<String, TaskManagerFactory> mymap;

		factory = new PostgreSqlMyDatasetDumpWriterFactory();
		mymap = new HashMap<String, TaskManagerFactory>();

		mymap.put("pl", factory);
		return mymap;
	}

}

