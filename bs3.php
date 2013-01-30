<?php
require("connect.inc");
// Connect to database 
    $con=new Connect_mysql();
	try { 
		$db = new PDO("mysql:host=$con->host;dbname=$con->databaseName", $con->user, $con->password);
	}  
	catch(PDOException $e) {  
	    echo $e->getMessage(); 
	    die ("\nYou don't have connection to database\n");
	}
// Select all subdirectories (leafs)
    $sel = "SELECT t1.id FROM s3objects AS t1 LEFT JOIN s3objects AS t2 ON t1.id = t2.id_parent WHERE t2.id IS NULL";
    $result = $db->query($sel);
    foreach($result as $row) {
       $leafs[] = $row['id']; // Array for storage of "leafs"
    }
    unset($row);

    $level  = $_POST['n_level'];
    $node   = $_POST['nodeid'];
    
// Preapare condions 
    if($node > 0) {
       $level = $level + 1;            // needing to select next level
       $WHERE = 'id_parent ='.$node;
    } else {
       $WHERE = 'id_parent=0';   // Select Root 
    } 

// Select subdirectories
    $sel  = "SELECT id, title, id_parent,size FROM s3objects WHERE ".$WHERE;

    $result = $db->query($sel);
///o $sel;
// Create result 
    $response->page      = 1;
    $response->total     = 1;
    $response->records   = 1;

    $i = 0;
    //while($row = mysql_fetch_assoc($result)) {
    foreach($result as $row) {
       //-------------------------------------
       // Определяем ID родителя этого узла
       if(!$row['id_parent'])
           $parent = 'NULL';
       else
          $parent = $row['id_parent'];
       //-------------------------------------
       // Check is it  leaf 
       if(in_array($row['id'], $leafs))
           $is_leaf = true;
       else
           $is_leaf = false;
       //--------------------------------------
       $response->rows[$i]['cell'] = array($row['id'], $row['title'],$row['size'],$level,$parent , $is_leaf, FALSE);

       $i++;
    }

// Show result 
    header("Content-type: text/html;charset=utf-8");
    echo json_encode($response);
?>
