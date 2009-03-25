// License: GPL. Copyright 2007-2008 by Brett Henderson and other contributors.



import java.io.File;
//import java.Math;
//import mercator;


import com.bretth.osmosis.core.container.v0_5.BoundContainer;
import com.bretth.osmosis.core.container.v0_5.EntityContainer;
import com.bretth.osmosis.core.container.v0_5.EntityProcessor;
import com.bretth.osmosis.core.container.v0_5.NodeContainer;
import com.bretth.osmosis.core.container.v0_5.RelationContainer;
import com.bretth.osmosis.core.container.v0_5.WayContainer;
import com.bretth.osmosis.core.domain.v0_5.EntityType;
import com.bretth.osmosis.core.domain.v0_5.Node;
import com.bretth.osmosis.core.domain.v0_5.Relation;
import com.bretth.osmosis.core.domain.v0_5.RelationMember;
import com.bretth.osmosis.core.domain.v0_5.Tag;
import com.bretth.osmosis.core.domain.v0_5.Way;
import com.bretth.osmosis.core.domain.v0_5.WayNode;
//import com.bretth.osmosis.core.pgsql.common.CopyFileWriter;
import com.bretth.osmosis.core.pgsql.common.PointBuilder;
import com.bretth.osmosis.core.task.v0_5.Sink;


/**
 * An OSM data sink for storing all data to a database dump file. This task is
 * intended for populating an empty database.
 * 
 * @author Brett Henderson
 */
public class PostgreSqlMyDatasetDumpWriter implements Sink, EntityProcessor {
	
	private static final String NODE_SUFFIX = "nodes.txt";
	private static final String NODE_TAG_SUFFIX = "node_tags.txt";
	private static final String WAY_SUFFIX = "ways.txt";
	private static final String WAY_TAG_SUFFIX = "way_tags.txt";
	private static final String WAY_NODE_SUFFIX = "way_nodes.txt";
	private static final String RELATION_SUFFIX = "relations.txt";
	private static final String RELATION_TAG_SUFFIX = "relation_tags.txt";
	private static final String RELATION_MEMBER_SUFFIX = "relation_members.txt";
	
	
	//private CompletableContainer writerContainer;
	private MyCopyFileWriter nodeWriter;
	private MyCopyFileWriter nodeTagWriter;
	private MyCopyFileWriter wayWriter;
	private MyCopyFileWriter wayTagWriter;
	private MyCopyFileWriter wayNodeWriter;
	private MyCopyFileWriter relationWriter;
	private MyCopyFileWriter relationTagWriter;
	private MyCopyFileWriter relationMemberWriter;
	private PointBuilder pointBuilder;
	private Mercator merc;
	
	
	/**
	 * Creates a new instance.
	 * 
	 * @param filePrefix
	 *            The prefix to prepend to all generated file names.
	 */
	public PostgreSqlMyDatasetDumpWriter(File filePrefix) {
		//writerContainer = new CompletableContainer();
		
		nodeWriter = new MyCopyFileWriter(new File(filePrefix, NODE_SUFFIX));
		nodeTagWriter = new MyCopyFileWriter(new File(filePrefix, NODE_TAG_SUFFIX));
		wayWriter = new MyCopyFileWriter(new File(filePrefix, WAY_SUFFIX));
		wayTagWriter = new MyCopyFileWriter(new File(filePrefix, WAY_TAG_SUFFIX));
		wayNodeWriter = new MyCopyFileWriter(new File(filePrefix, WAY_NODE_SUFFIX));
		relationWriter = new MyCopyFileWriter(new File(filePrefix, RELATION_SUFFIX));
		relationTagWriter = new MyCopyFileWriter(new File(filePrefix, RELATION_TAG_SUFFIX));
		relationMemberWriter = new MyCopyFileWriter(new File(filePrefix, RELATION_MEMBER_SUFFIX));
		
		pointBuilder = new PointBuilder();
		merc = new Mercator();
	}
	
	
	/**
	 * {@inheritDoc}
	 */
	public void process(EntityContainer entityContainer) {
		entityContainer.process(this);
	}
	
	
	/**
	 * {@inheritDoc}
	 */
	public void process(BoundContainer boundContainer) {
		// Do nothing.
	}
	
	
	/**
	 * {@inheritDoc}
	 */
	public void process(NodeContainer nodeContainer) {
		Node node;
		double x;
		double y;
		double lat;
		double lon;
		
		node = nodeContainer.getEntity();
		
		nodeWriter.writeMyField(String.format("%010d", node.getId()));
		nodeWriter.writeMyField(node.getUser());
		nodeWriter.writeMyField(node.getTimestamp());
		lat=node.getLatitude();
		lon=node.getLongitude();
		x=merc.mercX(lon);
		y=merc.mercY(lat);
		nodeWriter.writeMyField(pointBuilder.createPoint(y, x));
		nodeWriter.writeMyField(lat);
		nodeWriter.writeMyField(lon);
		nodeWriter.writeMyField(x);
		nodeWriter.writeMyField(y);
		nodeWriter.endRecord();
		
		for (Tag tag : node.getTagList()) {
			nodeTagWriter.writeMyField(node.getId());
			nodeTagWriter.writeMyField(tag.getKey());
			nodeTagWriter.writeMyField(tag.getValue());
			nodeTagWriter.endRecord();
		}
	}
	
	
	/**
	 * {@inheritDoc}
	 */
	public void process(WayContainer wayContainer) {
		Way way;
		long sequenceId;
		long last_node_id;
		
		way = wayContainer.getEntity();
		
		// Ignore ways with a single node because they can't be loaded into postgis.
		// doesn't apply to data consistency checks!
		//if (way.getWayNodeList().size() > 1) {
			wayWriter.writeMyField(way.getId());
			wayWriter.writeMyField(way.getUser());
			wayWriter.writeMyField(way.getTimestamp());
			
			for (Tag tag : way.getTagList()) {
				wayTagWriter.writeMyField(way.getId());
				wayTagWriter.writeMyField(tag.getKey());
				wayTagWriter.writeMyField(tag.getValue());
				wayTagWriter.endRecord();
			}
			
			sequenceId = last_node_id = 0;
			for (WayNode wayNode : way.getWayNodeList()) {
				wayNodeWriter.writeMyField(way.getId());
				wayNodeWriter.writeMyField(String.format("%010d", last_node_id=wayNode.getNodeId()));
				
				if (sequenceId == 0) wayWriter.writeMyField(String.format("%010d", last_node_id));
				
				
				wayNodeWriter.writeMyField(sequenceId++);
				wayNodeWriter.endRecord();
			}
			
                        wayWriter.writeMyField(String.format("%010d", last_node_id));
                        wayWriter.writeMyField(sequenceId);
			wayWriter.endRecord();
		//}
	}
	
	
	/**
	 * {@inheritDoc}
	 */
	public void process(RelationContainer relationContainer) {
		Relation relation;
		EntityType[] entityTypes;
		
		entityTypes = EntityType.values();
		
		relation = relationContainer.getEntity();
		
		relationWriter.writeMyField(relation.getId());
		relationWriter.writeMyField(relation.getUser());
		relationWriter.writeMyField(relation.getTimestamp());
		relationWriter.endRecord();
		
		for (Tag tag : relation.getTagList()) {
			relationTagWriter.writeMyField(relation.getId());
			relationTagWriter.writeMyField(tag.getKey());
			relationTagWriter.writeMyField(tag.getValue());
			relationTagWriter.endRecord();
		}
		
		for (RelationMember member : relation.getMemberList()) {
			relationMemberWriter.writeMyField(relation.getId());
			relationMemberWriter.writeMyField(member.getMemberId());
			relationMemberWriter.writeMyField(member.getMemberRole());
			for (byte i = 0; i < entityTypes.length; i++) {
				if (entityTypes[i].equals(member.getMemberType())) {
					relationMemberWriter.writeMyField(i);
				}
			}
			relationMemberWriter.endRecord();
		}
	}
	
	
	/**
	 * Writes any buffered data to the database and commits. 
	 */
	public void complete() {
		nodeWriter.complete();
		nodeTagWriter.complete();
		wayWriter.complete();
		wayTagWriter.complete();
		wayNodeWriter.complete();
		relationWriter.complete();
		relationTagWriter.complete();
		relationMemberWriter.complete();
	}
	 
	 
	/**
	 * Releases all database resources.
	 */
	public void release() {
		//writerContainer.release();
		nodeWriter.release();
		nodeTagWriter.release();
		wayWriter.release();
		wayTagWriter.release();
		wayNodeWriter.release();
		relationWriter.release();
		relationTagWriter.release();
		relationMemberWriter.release();
	}

	// pad a string to given length with zerores
	private String nrformat(String str) {
		final String sFillStrWithWantLen = "00000000000";
		int len = str.length();
		if (len < sFillStrWithWantLen.length())
			return (sFillStrWithWantLen + str).substring(len);
		else
			return str;
	}

}



