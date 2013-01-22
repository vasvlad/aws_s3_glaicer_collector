<?php
class Connect_mysql
{
    public $databaseName ;
    
    public $host ;
    
    public $user ;
    
    public $password ;
    
    public function __construct()
    {
        //$dir = dirname(__FILE__);
        
        //$dir = eregi_replace("connect","inifiles",$dir);
        
        $arrFile = parse_ini_file("base.conf",true);
        
        $this->host = $arrFile["dbmysql"]["host"];
        
        $this->user = $arrFile["dbmysql"]["user"];

        $this->password = $arrFile["dbmysql"]["password"];
        
        $this->databaseName = $arrFile["dbmysql"]["database"];
        
    }
}
?> 
    
     
