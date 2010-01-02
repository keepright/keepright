

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
javac -cp "/home/haraldk/OSM/osmosis-0.32.1/osmosis.jar:/home/haraldk/OSM/osmosis-0.32.1/lib/compile/postgis-1.3.2.jar:/home/haraldk/OSM/osmosis-0.32.1/lib/compile/postgresql-8.3-603.jdbc4.jar:." *.java


copy resulting .class files into osmosis.jar using your favourite zip program (!)

run with
 ~/OSM/osmosis-0.32.1/bin/osmosis -p pl --read-xml file=planet.osm --pl

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

