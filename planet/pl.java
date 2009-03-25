


import java.util.HashMap;
import java.util.Map;

import com.bretth.osmosis.core.plugin.PluginLoader;
import com.bretth.osmosis.core.pipeline.common.TaskManagerFactory;


/*

compile this with classpath pointing to osmosis.jar, postgis.jar and postgresql.jar:
javac -cp "/home/haraldk/OSM/osmosis-0.29/osmosis.jar:/home/haraldk/OSM/osmosis-0.29/lib/postgis_1.3.2.jar:/home/haraldk/OSM/osmosis-0.29/lib/postgresql-8.3-603.jdbc4.jar:." *.java

copy resulting .class files into osmosis.jar (!)

run with
java -jar osmosis.jar -p pl --read-xml austria.osm --pl


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