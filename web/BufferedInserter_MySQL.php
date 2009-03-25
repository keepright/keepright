<?php


/*
Issuing many SQL "INSERT INTO ... VALUES()" statements is rather expensive because of query parsing overhead. This little helper class will buffer data you want to insert into one table. When a limit is reached one multi-VALUE statement will be issued which is processed much faster than x single-VALUE statements
*/

/*
use BufferedInserter like this:

$bi=new BufferedInserter('INSERT INTO X(A,B,C)', $db);

$bi->insert("1,'sepp',1");
$bi->insert("2,'fritz',2");
$bi->insert("3,'hans',3");
$bi->flush_buffer();
*/

class BufferedInserter {
	private $records;
	private $buffer_length;
	private $rowcount;
	private $db;
	private $insert_prefix;

	function __construct($insert_prefix, $db, $buffer_length = 1000) {
		$this->insert_prefix=$insert_prefix;
		$this->db=$db;
		$this->buffer_length=$buffer_length;
		$this->records='';
		$this->rowcount=0;
	}

	function __destruct() {
		$this->flush_buffer();
	}

	function insert($record) {
		if ($this->rowcount > 0) $this->records.=', ';
		$this->records.="($record)";
		if ($this->rowcount++ > $this->buffer_length) $this->flush_buffer();
	}

	function flush_buffer() {
		if ($this->rowcount>0) query($this->insert_prefix . ' VALUES ' . $this->records, $this->db, false);
		$this->records='';
		$this->rowcount=0;
	}
}

?>