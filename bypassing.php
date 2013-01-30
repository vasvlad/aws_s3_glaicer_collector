<?php
ini_set('display_errors',1);
require("connect.inc");
require 'vendor/autoload.php';
use Aws\Common\Aws;

class passing
{
  /**
  * Connecting to database 
  */
  private $db;
  private $id_user;
  private $name_user;
  /**
      Try to connect to database after creation
	 */
	public function __construct($name_us)
	{
		$this->name_user = $name_us;
		$con=new Connect_mysql();
		try { 
			$this->db = new PDO("mysql:host=$con->host;dbname=$con->databaseName", $con->user, $con->password);
		}  
		catch(PDOException $e) {  
		    echo $e->getMessage(); 
		    die ("\nYou don't have connection to database\n");
		}
	}
	/**
	* Find all objects in Amazon S3
	*
	*/
	function execute()
	{
		/* */
        /* Select id of customer */
		$sel="SELECT * FROM customers  WHERE name=\"".addslashes($this->name_user)."\"";
		
		$result = $this->ExecQuery($sel);
		$arr = $result->fetchAll(PDO::FETCH_ASSOC);
		if (!$arr) {
		    $str_v="\nUser ".$this->name_user." not exists\n";
		    die ($str_v);
		}
		
		$key =0;
		$secret = 0;
		foreach ($arr as $row)  {
			$key= $row['key'] ;
			$this->id_user = $row['id'];
			$secret= $row['secret'] ;
		}

		$aws = Aws::factory(array('key'=>$key,'secret'=>$secret,'region'=>'us-east-1'));
		$s3 = $aws->get('s3');
		
		/* */
		$sel="SELECT id FROM s3objects WHERE id_user=".$this->id_user." AND id_parent=0 AND actual=0";
		$result = $this->db->query($sel);
		$num=$result->fetchColumn();
		if ($num == 0) {
			$insert = "INSERT INTO s3objects (id_parent,id_user, folder,size,timepassing,actual) VALUES (0,".$this->id_user.",1,0,unix_timestamp(),0)";
			$this->ExecQuery($insert);
		}
		$id_userparent = 0; /*id в таблице s3 object с id_user = $this->id_user*/
		$result = $this->ExecQuery($sel);
		foreach($result as $row) {
			$id_userparent= $row['id'] ;
		}
        /* Get all Buckets */
		foreach ($s3->getIterator('ListBuckets') as $bucket) {
			$sel="SELECT id FROM s3objects WHERE title=\"".addslashes($bucket['Name'])."\" AND id_parent=".$id_userparent." and id_user=".$this->id_user." AND actual=0";
			$result = $this->ExecQuery($sel);
			$num=$result->fetchColumn();
			if ($num == 0) {
                /* Insert new Bucket */
				$insert = "INSERT INTO s3objects (id_parent,id_user,title,folder,size,timepassing,actual) VALUES (".$id_userparent.",".$this->id_user.",\"".addslashes($bucket['Name'])."\",1,0,unix_timestamp(),0)";
				$this->ExecQuery($insert);
			}
			$result = $this->db->query($sel);
			foreach($result as $row) {
				$id_bucket= $row['id'] ;
			}
            /* Get all objects in Bucket */
			foreach ($s3->getIterator('ListObjects', array('Bucket' => $bucket['Name'])) as $object) {
				$arr=split('/',$object['Key']);
				$arr1=array_slice($arr,0,count($arr)-1);
				if (count($arr)==1) {
				    $id_parents_list=$id_bucket;
				} else {
				    $id_parents_list=$this->CreateParent($id_bucket,$arr1);
				}
				
#                print_r ($arr); /* For debug only */
				$c=count($arr);
				if ($arr[$c-1]) {
					$size=$object['Size'];
					$title=$arr[$c-1];
					$arr_id='';
					if (preg_match("/,/i",$id_parents_list)) {
						$arr_id=split(',',$id_parents_list);
					} else {
						$arr_id[0]=$id_parents_list;
					}
					$id_parent = $arr_id[count($arr_id)-1];
                    /* Check this object */
					$sel="SELECT id FROM s3objects WHERE title=\"".addslashes($title)."\" AND id_parent=$id_parent AND actual=0 and id_user=".$this->id_user;
					$result = $this->ExecQuery($sel);
					$num=$result->fetchColumn();
					if ($num == 0) {
                        /* Add the new object(file) to databse */
						$insert = "INSERT INTO s3objects (id_parent,id_user,title,folder,size,timepassing,actual) VALUES ($id_parent,".$this->id_user.",\"".addslashes($title)."\",0,$size,unix_timestamp(),0)";
						$this->ExecQuery($insert);
					}
                    /* Update size of each of  objects related with current object */
					$id_parents_list.=",".$id_userparent;
					$insert = "UPDATE s3objects SET size=size+$size WHERE id in ($id_parents_list)";
					$this->ExecQuery($insert);
				}
			}
		}
        /* Switch to current (actual) dataset */
		$sel = "DELETE FROM s3objects WHERE actual=1 and id_user=".$this->id_user;
		$this->ExecQuery($sel);
		$sel = "UPDATE s3objects SET actual=1 where  actual=0 and id_user=".$this->id_user;
		$this->ExecQuery($sel); 
	}

    /* Search all parents of this object */    
	private function SearchParent($id)
	{
		$id_parent="";
		$sel="SELECT id_parent FROM s3objects WHERE id=$id AND id_parent=0 AND actual=0;";
		$result = $this->ExecQuery($sel);
		$num=$result->fetchColumn();
		if ($num == 0) {
			$idp=$id;
			while ($idp>0){
				$sel="SELECT * FROM s3objects WHERE id=$idp AND actual=0;";
				$result = $this->ExecQuery($sel);
				foreach($result as $row) {
					$id_parent.=$row['id']."," ;
					$idp=$row['id_parent'] ;
				}
			} 
				$id_parent=substr($id_parent,0,strlen($id_parent)-1);
		} else {
			$id_parent = $id;
		}
		return $id_parent;
	}
	
	
	private function CreateParent($id_bucket,$arr_full) /*  */
	{
		$id_res="";
		$id_parent=$id_bucket;
		$id_res.=$id_parent.",";
#$		$arr=split('/',$arr_full);
		for($i=0;$i<count($arr_full);$i++) {
			$title=$arr_full[$i];
			$sel="SELECT id FROM s3objects WHERE title=\"".addslashes($title)."\" AND id_parent=$id_parent AND actual=0 and id_user=".$this->id_user;
			$result = $this->ExecQuery($sel);
			$res=$result->fetchAll(PDO::FETCH_ASSOC);;
			if ($res) {
				foreach($res as $row) {
					$id_parent=$row['id'] ;
				}
				$id_res.=$id_parent.",";
			} else {
				$insert = "INSERT INTO s3objects (id_parent,id_user,title, folder,size,timepassing,actual) VALUES ($id_parent,".$this->id_user.",\"".addslashes($title)."\",1,0,unix_timestamp(),0)";
				$result = $this->ExecQuery($insert);
				$last_query = 'SELECT LAST_INSERT_ID() as last_id';
				$last_id = $this->ExecQuery($last_query);
				$res = $last_id->fetchAll(PDO::FETCH_ASSOC);
				foreach ($res as $row)  {
					$id_parent = $row['last_id'];
				}
				$id_res.=$id_parent.",";
			}
		}
		$id_res=substr($id_res,0,strlen($id_res)-1);
		return $id_res;
	}
/* Execution query */    
	private function ExecQuery($sql)
	{
		if (preg_match("/Select/i",$sql)) {
		    $result=$this->db->query($sql);
		} else {
		    $result=$this->db->exec($sql);
		}
		$error_array = $this->db->errorInfo();
		if($this->db->errorCode() != 0000) {
			$stro ="\nIn $sql\n";
			$stro .= "SQL error: " . $error_array[2] . "\n";
			die ($stro);
			
		} else  {
		    return $result;
		}
	}
}
	$id_user=$argv[1] ;
	$command = new passing($id_user);
	$command->execute();

?>
