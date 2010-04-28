

-- Add the primary keys and indexes back again (except the way bbox index).
ALTER TABLE ONLY schema_info ADD CONSTRAINT pk_schema_info PRIMARY KEY (version);
ALTER TABLE ONLY nodes ADD CONSTRAINT pk_nodes PRIMARY KEY (id);
ALTER TABLE ONLY ways ADD CONSTRAINT pk_ways PRIMARY KEY (id);
ALTER TABLE ONLY way_nodes ADD CONSTRAINT pk_way_nodes PRIMARY KEY (way_id, sequence_id);
ALTER TABLE ONLY relations ADD CONSTRAINT pk_relations PRIMARY KEY (id);

CREATE INDEX idx_node_tags_node_id ON node_tags USING btree (node_id);
CREATE INDEX idx_nodes_geom ON nodes USING gist (geom);

CREATE INDEX idx_way_tags_way_id ON way_tags USING btree (way_id);
CREATE INDEX idx_way_nodes_node_id ON way_nodes USING btree (node_id);

CREATE INDEX idx_relation_tags_relation_id ON relation_tags USING btree (relation_id);
CREATE INDEX idx_relations_member_id ON relation_members USING btree (member_id);
CREATE INDEX idx_relations_member_role ON relation_members USING btree (member_role);
CREATE INDEX idx_relations_member_type ON relation_members USING btree (member_type);


-- Index for keys and values
DELETE FROM way_tags WHERE v IS NULL;
DELETE FROM node_tags WHERE v IS NULL;

CREATE INDEX idx_node_tags_k ON node_tags (k);
CREATE INDEX idx_node_tags_v ON node_tags (v);

CREATE INDEX idx_way_tags_k ON way_tags (k);
CREATE INDEX idx_way_tags_v ON way_tags (v);

CREATE INDEX idx_relation_tags_k ON relation_tags (k);
CREATE INDEX idx_relation_tags_v ON relation_tags (v);

