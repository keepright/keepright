<?php
    /**
    * A datagrid class. The appearance can be customised with CSS
    * See the example file for how.
    *
    * TODO
    *  o cellpadding doesn't seem to have any effect
    *
    * CHANGES
    *
    * 17th August 2008
    * ================
    *  o Added support for array based data sources, as well as MySQL result sets
    *  o Seperated the MySQL/Array handling code out into seperate classes
    *  o Fixed bug with empty result sets
    *
    * 6th May 2008
    * ============
    *  o Allowed disabling of ordering (completely)
    *  o Paging now has the "paging" CSS class (the next/prev links only). The bit that tells you how many rows
    *    there (x of y results) has the "paging_results" CSS class
    *  o Order by links now preserve existing GET variables - thanks Tom
    *  o Fixed hard coded perPage - thanks David
    *  o Added NoSort() method which, unsurprisingly, disables sorting for a given column (or columns)
    *
    * 20th April 2008
    * ===============
    *  o Column headers no longer have htmlspecialchars() applied to them, the same as
    *    actual column data
    *  o Added a factory method that creates a Datagrid object with the correct ordering
    *
    * 12th April 2008
    * ===============
    *  o Added column ordering support TODO: 1) Allow user to disable sorting
    *    2) Allow configuration of the sort indicator
    *
    * 28th March 2008
    * ===============
    *  o Added example3.php and example4.php
    *
    * 24th March 2008
    * ===============
    *  o Added example2.php which is styled to look like a Windows datagrid
    *
    * xth March 2008
    * ==============
    *  o Changed default colors to be a bit more "jazzy", or less "lame"
    *  o Added header and footer capability
    *  o Table headers - <th> tags now have a col_x class
    *
    * 5th March 2008
    * ==============
    *  o Added a few new methods: GetPageCount(), GetRowCount() and SetPerPage()
    *  o Made sure you can (if you want to) call DisplaY() multiple times on the same page
    *
    * 29th February 2008
    * ==================
    *  o Initial release
    */
    class Datagrid
    {
        /**
        * Holds the order by information
        */
        private static $orderby;
        private static $orderdir;

        /**
        * Properties. I really can't be bothered right now to document each one,
        * so they're all lumped together here
        */
        public $allowSorting;
        public $showHeaders;
        public $headerHTML;
        public $cellpadding;
        public $cellspacing;
        public $numresults;
        public $startnum;
        public $perPage;
        public $colnum;
        public $noSpecialChars;
        public $colnames;
        public $rowcallback;
        public $headers;

        private $noSort;
        private $initialcols;
        private $connection;
        private $resultset;
        private $hiddenColumns;

        /**
        * Creates a datagrid from an array. Similar to a MySQL datasrc
        * 
        * @param array $data The data (array)
        */
        public static function CreateFromArray($array)
        {
            /**
            * Order by
            */
            if (isset($_GET['orderDir']) AND !empty($_GET['orderBy'])) {

                // Store it so the direction indicators appear
                Datagrid::$orderby['column']    = $_GET['orderBy'];
                Datagrid::$orderby['direction'] = $_GET['orderDir'];

                // FIXME - implement sorting
                uasort($array, array('Datagrid', '_sortArray'));
           }

            $grid =  new Datagrid($array);

            return $grid;
        }

        /**
        * Creates Datagrid object for you and returns it
        *
        * @param resource   $connection The connection to the database. This can also be an array
        *                               containing host/user/pass/dbas parameters to connect to the
        *                               database. This can also be used to create a datagrid from
        *                               an array data source by supplying an array instead of the
        *                               database connection, eg: $grid = Datagrid::Create($myArray);
        * @param string     $sql        The SQL query with or without the ORDER BY clause
        */
        public static function Create($connection, $select = null, $from = null, $perPage = null)
        {
            /**
            * Creates an array based datagrid if the first arg is an array
            */
            if (is_array($connection) AND is_null($select) AND is_null($from)) {
                return Datagrid::CreateFromArray($connection);
            }

            // Connect if need be
            if (is_array($connection)) {
                $host = $connection['hostname'];
                $user = $connection['username'];
                $pass = $connection['password'];
                $dbas = $connection['database'];

                $connection = mysql_connect($host, $user, $pass) OR die('<span style="color: red">Failed to connect: ' . mysql_connect_error() . '</span>');

                mysql_select_db($dbas);
            }

            /**
            * Order by
            */
            if (isset($_GET['orderDir']) AND !empty($_GET['orderBy'])) {

                // Store it so the direction indicators appear
                Datagrid::$orderby['column']    = $_GET['orderBy'];
                Datagrid::$orderby['direction'] = $_GET['orderDir'];

                $orderby = 'ORDER BY ' . $_GET['orderBy'] . ' ' . ($_GET['orderDir'] ? 'ASC' : 'DESC');
                $sql = preg_replace('/ORDER\s+BY.*(ASC|DESC)/is', $orderby, "$select $from");
            } else $sql = "$select $from";

            /**
            * Perform the reduced query to get the number of rows
            */
            $resultset = mysql_query("SELECT COUNT(*) AS N $from", $connection);

            if ($resultset && $row = mysql_fetch_array($resultset, MYSQL_ASSOC)) {
                $numrows = $row['N'];
		mysql_free_result($resultset);
		}
            else 
                $numrows = 0;



            $startnum = @(int)$_GET['start'];
            // Don't allow startnum to be lower than zero
            if ($startnum < 0) {
                $startnum = 0;
            }

            // Don't allow startnum to be greater than the number of rows in the result set,
            // well, one less to allow for zero indexing
            if ($startnum >= $numrows) {
                $startnum = 0;
            }

            /**
            * Perform the query to get the result set
            */
            $resultset = mysql_query("$sql LIMIT $perPage OFFSET $startnum", $connection);
            $grid = new Datagrid($connection, $resultset, $numrows, $perPage, $startnum);

            // If the query doesn't have an ORDER BY, then disable ordering
            if (strpos($sql, 'ORDER BY') === false) {
                $grid->allowSorting = false;
            }
            return $grid;
        }

        /**
        * The constructor
        * 
        * @param mixed    $connection This can be either a MySQL connection resource or an array
        * @param resource $resultset Only used for MySQL based datagrids - the MySQL result.
        */
        public function __construct($connection, $resultset = null, $numresults = null, $perPage = null, $startnum = null)
        {
            $this->noSort         = array();
            $this->allowSorting   = true;
            $this->showHeaders    = true;
            $this->headerHTML     = '';
            $this->cellpadding    = 0;
            $this->cellspacing    = 0;
            $this->connection     = $connection;
            $this->resultset      = $resultset;
            $this->numresults     = $numresults ? $numresults : (is_array($connection) ?  count($connection) : mysql_num_rows($resultset));
            $this->startnum       = $startnum ? $startnum : 0;
            $this->perPage        = $perPage ? $perPage : 20;
            $this->hiddenColumns  = array();
            $this->colnum         = is_array($connection) ? count($connection[0]) : mysql_num_fields($this->resultset);
            $this->noSpecialChars = array();

            // Don't allow startnum to be lower than zero
            if ($this->startnum < 0) {
                $this->startnum = 0;
            }

            // Don't allow startnum to be greater than the number of rows in the result set,
            // well, one less to allow for zero indexing
            if ($this->startnum >= $this->numresults) {
                $this->startnum = 0;
            }
/*
            // Check the MySQL connection is valid
            if (!is_resource($connection) AND !is_array($connection)) {
                die('<p /><span style="color: red">Error - the MySQL connection you have passed to the datagrid constructor is not valid</span>');
            }*/
        }

        /**
        * Sets the displayed header names for the columns
        *
        * @param array $cols The column names
        */
        public function SetDisplayNames($cols)
        {
            $this->colnames = $cols;
        }

        /**
        * Hides a particular column, or multiple columns
        *
        * @param ... strings One or more column names
        */
        public function HideColumn()
        {
            $this->hiddenColumns = array_unique(func_get_args());
        }

        /**
        * Sets the column names (not using the display names) that
        * don't get htmlspecialchars() applied to them
        *
        * @param string ... One or more column names
        */
        public function NoSpecialChars()
        {
            $this->noSpecialChars = func_get_args();
        }

        /**
        * This method allows you to specify one or more columns that cannot be sorted by
        * 
        * @param string ... The column name (s). You can specify one or more.
        */
        public function NoSort()
        {
            $args = func_get_args();

            foreach ($args as $v) {
                $this->noSort[] = $v;
            }

            // Should do this before running the query, but hey ho.
            if (in_array(Datagrid::$orderby['column'], $this->noSort)) {
                die('<span style="color: red">You are not allowed to sort by that column</span>');
            }
        }

        /**
        * Returns the number of pages in the datagrid
        *
        * @return int The number of pages in the datagrid
        */
        public function GetPageCount()
        {
            $count = is_resource($this->connection) ? mysql_num_rows($this->resultset) : count($this->connection);

            return  ceil($count / $this->perPage);
        }

        /**
        * Returns the number of rows in the result set.
        *
        * @return int The number of rows
        */
        public function GetRowCount()
        {
            return $this->numresults;
        }

        /**
        * I can't see the need for this, but you may. Simply returns the MySQL result set.
        *
        * @return resource The MySQL result set
        */
        public function GetResultset()
        {
            if (is_array($this->connection)) {
                die('<span style="color: red">Cannot get the result set - data source is an array</span>');
            }

            return $this->resultset;
        }


        /**
        * Returns the MySQL connection
        *
        * @return resource The MySQL resouce
        */
        public function GetConnection()
        {
            if (is_array($this->connection)) {
                die('<span style="color: red">Cannot get the connection - data source is an array</span>');
            }

            return $this->connection;
        }

        /**
        * Sets the header HTML./ This is NOT related to the table
        * column headers. This is here purely for decorative purposes.
        *
        * @param string $html The HTML to set
        */
        public function SetHeaderHTML($html)
        {
            $this->headerHTML = $html;
        }

        /**
        * Sets the MySQL connection
        *
        * @param resource $connection The MySQL connection resouce
        */
        public function SetConnection($connection)
        {
            if (is_array($this->connection)) {
                die('<span style="color: red">Cannot set the connection - data source is an array</span>');
            }

            $this->connection = $connection;
        }

        /**
        * This function sets the amount of rows to display
        * per page
        *
        * @param int $perPage How many rows to show per page
        */
        public function SetPerPage($perPage)
        {
            $this->perPage = $perPage;
        }

        /**
        * For whatever reason you can use this to set the MySQL
        * result set
        *
        * @param resource $result The MySQL result set. If you do use this method, it
        *                          should come before the call to Display
        */
        public function SetResultset($resultset)
        {
            if (is_array($this->connection)) {
                die('<span style="color: red">Cannot set the result set - data source is an array</span>');
            }

            $this->resultset  = $resultset;
            $this->numresults = mysql_num_rows($this->resultset);
            $this->colnum     = mysql_num_fields($this->resultset) - count($this->hiddenColumns);
        }

        /**
        * Adds a rowcallback function which gets called just before each row is going
        * to be displayed
        *
        * @param string &$row The function name that is the callback function.
        */
        public function AddCallback($callback)
        {
            $this->rowcallback = $callback;
        }

        /**
        * Shows the datagrid.
        */
        function Display()
        {
            /**
            * Seek to the correct place in the result set
            */
            if (is_array($this->connection)) {
                $this->orig_array = $this->connection;
                $this->connection = array_slice($this->connection, $this->startnum, $this->perPage);
            } /*else {
                if (mysql_num_rows($this->resultset)) {
                    mysql_data_seek($this->resultset, $this->startnum);
                }
            }*/

            /**
            * Initialise the row number
            */
            $rownum = 0;

            /**
            * Get the headers from the first row, then seek back to zero
            */
            $row = is_array($this->connection) ? $this->connection[0] : mysql_fetch_array($this->resultset, MYSQL_ASSOC);
            $this->headers     = !empty($row) ? array_keys($row) : array();
            $this->initialcols = count($row);
            $this->colnum = (is_array($this->connection) ? count($row) : mysql_num_fields($this->resultset)) - count($this->hiddenColumns);
            //is_array($this->connection) || mysql_num_rows($this->resultset) == 0 ? null : mysql_data_seek($this->resultset, $this->startnum);
            $rowcount = 0;

            ?>
<script language="javascript" type="text/javascript">
<!--
    /**
    * The row mouseover function
    */
    function MouseOver(rownum)
    {
        var tags = document.getElementsByTagName('td')

        for (var i=0; i<tags.length; i++) {
            if(tags[i].className.indexOf('row_' + rownum + ' ') != -1) {
                tags[i].className = tags[i].className += ' mouseover';
            };
        }
    }

    /**
    * the row mouseout function
    */
    function MouseOut(rownum)
    {
        var tags = document.getElementsByTagName('td')

        for (var i=0; i<tags.length; i++) {
            if(tags[i].className.indexOf('row_' + rownum) != -1) {
                tags[i].className = tags[i].className.replace(/ mouseover/, '');
            };
        }
    }
// -->
</script>
<table border="0" cellspacing="<?=$this->cellspacing?>" cellpadding="<?=$this->cellpadding?>" class="datagrid">
    <thead>
        <?if($this->headerHTML):?>
            <tr>
                <th id="header" colspan="<?=$this->colnum?>">
                    <?=$this->headerHTML?>
                </th>
            </tr>
        <?endif?>

        <?if($this->showHeaders):?>
		<tr>
		<td colspan="<?=$this->colnum?>" class="paging">
			<?if(@$this->startnum > 0):?>
			<span style="float: left">
				<a href="<?=$this->getQueryString(intval($this->startnum) - $this->perPage)?>">
				&laquo; Prev
				</a>
			</span>
			<?endif?>
	
	
			<?if($this->numresults > (@$this->startnum + $this->perPage)):?>
			<span style="float: right">
				<a href="<?=$this->getQueryString(intval($this->startnum) + $this->perPage)?>">
				Next &raquo;
				</a>
			</span>
			<?endif?>
		</td>
		</tr>

                <tr>
                    <?foreach($this->headers as $k => $h):?>
                        <?if(in_array($h, $this->hiddenColumns)) continue?>

                        <th class="col_<?=$k?>" title="<?=($printable = !empty($this->colnames[$h]) ? $this->colnames[$h] : $h)?>">

                            <?if($this->allowSorting AND !in_array($h, $this->noSort)):?>
                                <a href="<?=$this->getQueryString()?>&orderBy=<?=$h?>&orderDir=<?=(!empty($_GET['orderDir']) && $_GET['orderBy'] == $h ? 0 : 1)?>">
                                    <?=$printable?>
                                </a>

                            <?else:?>
                                <?=$printable?>
                            <?endif?>

                            <?if($this->allowSorting):?>
                                <!-- The order indicator -->

                                <?if($h == Datagrid::$orderby['column']):?>
                                    <span style="font-family: WebDings">
                                        <?=(!empty(Datagrid::$orderby['direction']) && trim(Datagrid::$orderby['direction']) == 1 ? 5 : 6)?>
                                    </span>
                                <?endif?>
                            <?endif?>
                        </th>
                    <?endforeach?>
                </tr>
        <?endif?>
    </thead>

    <tbody>
        <?while($row = (is_array($this->connection) ? current($this->connection) : mysql_fetch_array($this->resultset, MYSQL_ASSOC))):?>

            <?$colnum = 0; @$rowcount++?>

            <?if($this->rowcallback):?>
                <?call_user_func($this->rowcallback, &$row)?>
            <?endif?>

            <tr onmouseover="MouseOver(<?=intval($rownum)?>)" onmouseout="MouseOut(<?=intval($rownum)?>)">
                <?foreach($row as $k => $v):?>

                    <?if(in_array($k, $this->hiddenColumns)) continue?>

                    <td class="row_<?=intval($rownum)?> col_<?=(!empty($colnum) ? $colnum : 0)?> <?if($rownum % 2 == 1):?>altrow<?endif?>  <?if($colnum % 2 == 1):?>altcol<?endif?>">
                        <?=(in_array($k, $this->noSpecialChars) ? $v : htmlspecialchars($v))?>
                    </td>

                    <?$colnum++?>
                <?endforeach?>
            </tr>

            <?if($rownum++ == ($this->perPage - 1) ) break?>
            <?if(is_array($this->connection)): next($this->connection); endif?>
        <?endwhile?>
    </tbody>

    <tfoot>
        <tr>
            <td colspan="<?=$this->colnum?>" class="paging">
                <?if(@$this->startnum > 0):?>
                    <span style="float: left">
                        <a href="<?=$this->getQueryString(intval($this->startnum) - $this->perPage)?>">
                            &laquo; Prev
                        </a>
                    </span>
                <?endif?>


                <?if($this->numresults > (@$this->startnum + $this->perPage)):?>
                    <span style="float: right">
                        <a href="<?=$this->getQueryString(intval($this->startnum) + $this->perPage)?>">
                            Next &raquo;
                        </a>
                    </span>
                <?endif?>
            </td>
        </tr>

        <tr>
            <td align="center" colspan="<?=$this->colnum?>" class="paging_results">
                <?=($this->numresults > 0 ? intval($this->startnum) + 1 : 0)?>-<?=(intval($this->startnum) + $rowcount)?> of <?=intval($this->numresults)?> results
            </td>
        </tr>
    </tfoot>
</table>
            <?php
        }

        /**
        * A private method used to build the query string
        *
        * @param  int    The starting number
        * @return string The query string
        */
        private function getQueryString($startnum = null)
        {
            if ($startnum === null) {
                $startnum = !empty($_GET['start']) ? $_GET['start'] : 0;
            }

            $_GET['start'] = $startnum;

            $qs = '?';
            foreach ($_GET as $k => $v) {
                $qs .= urlencode($k) . '=' . urlencode($v)  . '&';
            }

            // If the query string is just a question mark, lose it
            if ($qs == '?') {
                $qs = '';
            }

            return preg_replace('/&$/', '', $qs);
        }

        /**
        * Sort an array based datagrid
        */
        private function _sortArray($a, $b)
        {
            if (empty(Datagrid::$orderby)) {
                Datagrid::$orderby  = key($a);
                Datagrid::$orderdir = 1; // Ascending
            }

            // Ascending
            if (Datagrid::$orderby['direction']) {
                if ($a[Datagrid::$orderby['column']] > $b[Datagrid::$orderby['column']]) {
                    return 1;
                } elseif ($a[Datagrid::$orderby['column']] < $b[Datagrid::$orderby['column']]) {
                    return -1;
                } else {
                    return 0;
                }

            // Descending
            } else {

                if ($a[Datagrid::$orderby['column']] > $b[Datagrid::$orderby['column']]) {
                    return -1;
                } elseif ($a[Datagrid::$orderby['column']] < $b[Datagrid::$orderby['column']]) {
                    return 1;
                } else {
                    return 0;
                }
            }
        }
    }
?>