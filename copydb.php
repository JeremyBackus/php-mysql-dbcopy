<?php 



// operations
$KeepExistingTables = true;
$TruncateExistingTables = true;
$MaxRowsPerChunk = 5;
$MaxChunksPerImport = 20; // then stop
$WaitBetweenChunks = 1; // seconds

// restrictions
//$DoNotInsertRows = true;
//$DoNotCreateTargetTables = true;

$source_server = "db2.simplewebs.com";
$source_db = "db2";
$source_table = "test_datax";

$source_db_user = "admin";
$source_db_pass = "abc#12345";


$target_server="db2.simplewebs.com";
$target_db = "db1_temp";
$target_table = $source_table;

$target_db_user = $source_db_user;
$target_db_pass = $source_db_pass;





$SqlInsertTmpFilename = "tmp_sql_insert"; // .sql

///////////////////////////////////////////////////////////

session_start();
?><style>
body { font-family: sans-serif; }
pre { font-size:90%; }
</style><pre><?php

$source_conn=mysql_connect($source_server, $source_db_user, $source_db_pass) or die('cannot connect to source db');
mysql_select_db($source_db,$source_conn);

$target_conn=mysql_connect($target_server, $target_db_user, $target_db_pass) or die('cannot connect to target db');
mysql_select_db($target_db,$target_conn);


$dbinfo = mysql_fetch_array(mysql_query("SHOW CREATE DATABASE ".$source_db,$source_conn));
$tableinfo = mysql_fetch_array(mysql_query("SHOW CREATE TABLE ".$source_table,$source_conn)); 




/* reset session */
if (!isset($_GET["prepare"]) && !isset($_GET["copy"])){ 
  session_unset();
  session_destroy();
  
  echo '
  -- source db info
  '.str_replace("CREATE DATABASE","SOURCE DATABASE INFO:\n",$dbinfo[1]).'
  
  -- source table info
  '.str_replace("CREATE TABLE","SOURCE TABLE INFO:\n",$tableinfo[1]).'
  
  ----
  ';  
  
  echo '
<a href="?prepare">Prepare</a>
  ';
  
  die;
} else if (isset($_GET["prepare"])){ 
  echo '
<a href="?copy">Copy</a>
  ';
} else if (isset($_GET["copy"])){ 
  if ($_SESSION["chunk"]){ $_GET["chunk"] = $_SESSION["chunk"]; }
  echo '
<a href="?done">Done</a>
  ';
}


/* truncate target table */
$tmp = $mysqli->query("SHOW TABLES LIKE '".$table."'");
if ($tmp->num_rows != 1)
if($KeepExistingTables && $TruncateExistingTables && isset($_GET["prepare"]) && !isset($_GET["chunk"])){
  $sql_truncate_table = '
  -- truncate target table  
  TRUNCATE TABLE `'.$target_table.'`;
  ';
  echo $sql_truncate_table;
  mysql_query($sql_truncate_table, $target_conn);
}

/* drop existing target table */
if(!$KeepExistingTables && isset($_GET["prepare"]) && !isset($_GET["chunk"])){ 
  $sql_drop_table = '
  -- drop target table 
  DROP `'.$target_table.'`;
  ';
  echo $sql_drop_table;
  mysql_query($sql_drop_table, $target_conn);
}

/* create target table */
$tmp = $mysqli->query("SHOW TABLES LIKE '".$table."'");
if ($tmp->num_rows != 1)
if(!$DoNotCreateTargetTables  && isset($_GET["prepare"]) && !isset($_GET["chunk"])){
  $sql_create_table = '
  -- create target table
  '.str_replace("CREATE TABLE `".$source_table."`","CREATE TABLE `".$target_table."`",$tableinfo[1]).';
  ';
  echo $sql_create_table;
  mysql_query($sql_create_table, $target_conn);  
}


/* copy rows */
if(mysql_num_rows(mysql_query("SHOW TABLES LIKE '".$target_table."'"))==1 && !$DoNotInsertRows && isset($_GET["copy"])){
  $result = mysql_query("SELECT * FROM ".$source_table,$source_conn);
  $full_row_count = mysql_num_rows($result);
  
  $sql_insert = '';
  $row_count = 0;
  $insert_count = 0;
  $start_row = 0;
  $end_row = 0;
  
  if(!$_GET["chunk"]){ 
    $_GET["chunk"] = 1;
    $_SESSION["chunk"] = $_GET["chunk"];
    
    echo '
    
Refresh to begin import.
    
    ';
    
    
  } else{
    
    $start_row = $_GET["chunk"]*$MaxRowsPerChunk;
    $end_row = ($start_row+$MaxRowsPerChunk);
  
    while ($row = mysql_fetch_array($result, MYSQL_ASSOC)){
        $row_count++;
        if ($row_count >= $start_row && $row_count < $end_row){
          $sql_insert = "INSERT INTO $target_table (".implode(", ",array_keys($row)).") VALUES ('".implode("', '",str_replace("'","",array_values($row)))."'); \n";
          mysql_query($sql_insert, $target_conn);
          $insert_count++;
        }
    }
  
    $total_inserts_completed = (($_GET["chunk"]-1)*$MaxRowsPerChunk)+$insert_count;
    //Chunk: '.$_SESSION["chunk"].'
    echo '
    
  Progress: '.$total_inserts_completed.' of '.$full_row_count.'
  
    
    ';
    
    //echo $sql_insert;
    
    if ($insert_count == $MaxRowsPerChunk && $_SESSION["chunk"] < $MaxChunksPerImport){
      $_SESSION["chunk"] = $_SESSION["chunk"]+1;
      echo '<meta http-equiv="refresh" content="'.$WaitBetweenChunks.'">';
    } else {
      unset($_SESSION["chunk"]);
    }
  
  }
 

}

mysql_close($source_conn);
mysql_close($target_conn);
?>