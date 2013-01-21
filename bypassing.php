<?php
ini_set('display_errors',1);
require("connect/connect.inc");
require 'vendor/autoload.php';
use Aws\Common\Aws;

class passing
{
  /**
  * Connecting to database 
  */
  private $db;
  private $id_user;
  /**
      Try to connect to database after creation
	 */
	public function __construct($id_us)
	{
		$this->id_user = $id_us;
		$con=new Connect_mysql();
		$this->db = new PDO("mysql:host=$con->host;dbname=$con->databaseName", $con->user, $con->password);  
		$this->db->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION); 
		/**
		* Create database if it requered  
		*/
		$this->db->exec("CREATE TABLE IF NOT EXISTS s3objects (id bigint NOT NULL auto_increment,id_parent bigint,id_user int(11),
		title varchar(200),folder tinyint(1),size bigint,timepassing timestamp,actual tinyint(1), PRIMARY KEY  (`id`))");
	}
	/**
	* Find all objects in Amazon S3
	*
	*/
	function execute() 
	{
		echo "run";
		$ar = '/var/www/config.php';
		print_r($ar);
		$s3 = Aws::factory('/var/www/config.php')->get('s3');
		/* */
		$sel="SELECT id FROM s3objects WHERE id_user='".$this->id_user."' AND id_parent=0 AND actual=0";
		$result = $this->db->query($sel);
		$num=$result->fetchColumn();
		if ($num == 0) {
			$insert = "INSERT INTO s3objects (id_parent,id_user, folder,size,timepassing,actual) VALUES (0,".$this->id_user.",1,0,unix_timestamp(),0)";
			$this->db->exec($insert);
		}
		$id_userparent = 0; /*id в таблице s3 object с id_user = $this->id_user*/
		$result = $this->db->query($sel);
		foreach($result as $row) {
			$id_userparent= $row['id'] ;
		}
        /* Get all Buckets */
		foreach ($s3->getIterator('ListBuckets') as $bucket) {
			$sel="SELECT id FROM s3objects WHERE title='".$bucket['Name']."' AND id_parent=".$id_userparent." and id_user=".$this->id_user." AND actual=0";
			$result = $this->db->query($sel);
			$num=$result->fetchColumn();
			if ($num == 0) {
                /* Insert new Bucket */
				$insert = "INSERT INTO s3objects (id_parent,id_user,title,folder,size,timepassing,actual) VALUES (".$id_userparent.",".$this->id_user.",'".$bucket['Name']."',1,0,unix_timestamp(),0)";
				$this->db->exec($insert);
			}
			$result = $this->db->query($sel);
			foreach($result as $row) {
				$id_bucket= $row['id'] ;
			}
            /* Get all objects in Bucket */
			foreach ($s3->getIterator('ListObjects', array('Bucket' => $bucket['Name'])) as $object) {
				$arr=split('/',$object['Key']);
				$c=count($arr);
				if ($arr[$c-1]) {
					$size=$object['Size'];
					$title=$arr[$c-1];
					if ($c < 2) {
						$id_parent=$id_bucket;
					} else {
                        /* Look up parent of this object */
						$sel="SELECT id FROM s3objects WHERE title='".$arr[$c-2]."' AND actual=0 and id_user=".$this->id_user." ORDER BY id  desc LIMIT 1";
						$res = $this->db->query($sel);
						foreach($res as $r) {
							$id_parent= $r['id'] ;
						}
					}
                    /* Check this object */
					$sel="SELECT id FROM s3objects WHERE title='$title' AND id_parent=$id_parent AND actual=0 and id_user=".$this->id_user;
					$result = $this->db->query($sel);
					$num=$result->fetchColumn();
                    if ($num == 0) {
                        /* Add the new object(file) to databse */
						$insert = "INSERT INTO s3objects (id_parent,id_user,title,folder,size,timepassing,actual) VALUES ($id_parent,".$this->id_user.",'$title',0,$size,unix_timestamp(),0)";
						$this->db->exec($insert);
					}
					$upd=$this->SearchParent($id_parent);
                    /* Update size of each of  objects related with current object */
					$insert = "UPDATE s3objects SET size=size+$size WHERE id in ($upd)";
					$this->db->exec($insert);
				} else {
					$title=$arr[$c-2];
					if ($c < 3) {
						$id_parent=$id_bucket;
					} else {
                        /* Check folder */
						$sel="SELECT id FROM s3objects WHERE title='".$arr[$c-3]."' AND actual=0 and id_user=".$this->id_user." ORDER BY title DESC LIMIT 1";
						$res = $this->db->query($sel);
						foreach($res as $r) {
							$id_parent= $r['id'] ;
						}
					}
					$sel="SELECT id FROM s3objects WHERE title='$title' AND id_parent=$id_parent AND actual=0 and id_user=".$this->id_user;
					$result = $this->db->query($sel);
					$num=$result->fetchColumn();
					if ($num == 0) {
                        /* Add the new object(folder) to databse */
						$insert = "INSERT INTO s3objects (id_parent,id_user,title, folder,size,timepassing,actual) VALUES ($id_parent,".$this->id_user.",'$title',1,0,unix_timestamp(),0)";
						$this->db->exec($insert);
					}
					$result = $this->db->query($sel);
					foreach($result as $row) {
						$id_folder= $row['id'] ;
					}
				} 
			}
		}
        /* Switch to current (actual) dataset */
		$sel = "DELETE FROM s3objects WHERE actual=1 and id_user=".$this->id_user;
		$this->db->exec($sel);
		$sel = "UPDATE s3objects SET actual=1 where  actual=0 and id_user=".$this->id_user;
		$this->db->exec($sel); 
		echo "dun";
	}

    /* Search all parents of this object */    
	private function SearchParent($id) 	
	{
		$id_parent="";
		$sel="SELECT id_parent FROM s3objects WHERE id=$id AND id_parent=0 AND actual=0;";
		$result = $this->db->query($sel);
		$num=$result->fetchColumn();
		if ($num == 0) {
			$idp=$id;
			while ($idp>0){
				$sel="SELECT * FROM s3objects WHERE id=$idp AND actual=0;";
				$result = $this->db->query($sel);
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
}
 $id_user=$argv[1] ;
 $command = new passing($id_user);
 $command->execute();

?>
