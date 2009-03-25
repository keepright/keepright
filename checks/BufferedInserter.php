<?php


/*
Issuing many SQL "INSERT INTO ... VALUES()" statements is rather expensive because of query parsing overhead. This little helper class will buffer data you want to insert into one table. When a limit is reached one multi-VALUE statement will be issued which is processed much faster than x single-VALUE statements
*/

/*
use BufferedInserter like this:

$bi=new BufferedInserter('tablename', $db);

$bi->insert("1\tSepp\t1");
$bi->insert("2\tFritz\t2");
$bi->insert("3\tHans\t\\N");
$bi->flush_buffer();

please note: "\\N" is the default for specifying NULL values
*/

class BufferedInserter {
	private $records;
	private $buffer_length;
	private $rowcount;
	private $db;
	private $tablename;

	function __construct($tablename, $db, $buffer_length = 1000) {
		$this->tablename=$tablename;
		$this->db=$db;
		$this->buffer_length=$buffer_length;
		$this->records=array();
		$this->rowcount=0;
	}

	function __destruct() {
		$this->flush_buffer();
	}

	function insert($record) {
		//echo "------------COPY \n$record\n------------COPY\n";
		$this->records[]="$record\n";
		if ($this->rowcount++ >= $this->buffer_length) $this->flush_buffer();
	}

	function flush_buffer() {
		if ($this->rowcount>0) pg_copy_from($this->db, $this->tablename, $this->records);
		$this->records=array();
		$this->rowcount=0;
	}
}

?>