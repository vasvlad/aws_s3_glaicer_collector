<?php
require_once("connect.inc");
class bs3s
{
	private $operations = array(
		'eq' => "= '[val]'",
		'ne' => "!= '[val]'",
		'lt' => "< '[val]'",
		'le' => "<= '[val]'",
		'gt' => "'> '[val]'",
		'ge' => ">= '[val]'",
		'bw' => "like '[val]%'",
		'ew' => "like '%[val]'",
		'cn' => "like '%[val]%'"
	);
	private $query = array(
		'limit' => '',
		'where' => '',
		'order' => ''
	);
	private $result = array(
		'total' => 0,
		'page' => 1,
		'records' => 0,
		'rows' => array()
	);
	private $db;
	
	public function __construct() 
	{
		// Connect to database 
		$con=new Connect_mysql();
		try { 
		$this->db = new PDO("mysql:host=$con->host;dbname=$con->databaseName", $con->user, $con->password);
		}  
		catch(PDOException $e) {  
			echo $e->getMessage(); 
			die ("\nYou don't have connection to database\n");
		}
	}
	
	function execute()
	{
		//Получаем информацию о том, нужно ли выполнять фильтрацию данных
		$this->buildjqGridWhere();
		//Подсчитываем число строк, которые будут выбраны (с учетом фильтрации)
		//А так же добавляем ограничение выборки (если таковое есть в запросе)
		$this->calculateLimit();
		//Добавляем к рещультирующему запросу порядок сортировки (order by)
		$this->setupOrder();
		//Выбираем записи с учетом фильтрации и ограничения
		$this->fetchRecords();
		//Выводим результат
		$this->printResult();
	}
	/**
	 * Теперь подряд опишем функции
	 */
	
	private function calculateLimit()
	{
		//Подсчитываем число записей
		$count = $this->countRecords();
		
		//Получаем номер страницы, которую нужно вывести 
		//и число записей на странице
		$page = $this->get('page',1);
		$limit = $this->get('rows',-1);
		//Если число записей в выборке и число записей на странице выдачи больше 0
		//То посчитаем количество страниц в выборке
		//Иначе количество страниц в выборке равно 1
		if($count > 0 && $limit > 0)
			$totalPages = ceil($count/$limit);
		else
			$totalPages = 1;
		
		//Если страница, которую запросил пользователь не существует,
		//то установим в качестве страницы выдачи последнюю страницу
		if($page > $totalPages)
			$page = $totalPages;
		
		//Запись в выборке, с которой начинается выдача
		$start = $limit*$page - $limit;
		
		//Проверим номер первой записи и скорректируем если необходимо
		if($start < 0)
			$start = 0;
		
		//Если количество записей на страницу указано, 
		//то установим лимит выдачи
		if($limit > 1)	
			$this->query['limit'] = "limit {$limit} offset {$start} ";
		//Заполним необходимые поля для вывода последующего вывода данных
		$this->result['total'] = $totalPages;
		$this->result['page'] = $page;
		$this->result['records'] = $count;
	}
	
	/**
	 * Выборка записей из базы данных
	 */
	private function fetchRecords()
	{
	//echo " SELECT id, title, id_parent,size,folder FROM s3objects WHERE ".$this->buildQuery();
		$result = $this->db->query(" SELECT id, title, id_parent,size,folder FROM s3objects WHERE ".$this->buildQuery());
		$i =0;
	
		foreach($result as $row) {
			if(!$row['id_parent']) $parent = 'NULL';
			else $parent = $row['id_parent'];
			$this->result['rows'][$i]['id']=$row['id'];
			$id = $row['id'];
			$selr = $this->db->query(" SELECT count(*) as count_c FROM s3objects WHERE id_parent=$id ");
			$cou = 0;
			foreach($selr as $row1) {
				$cou = $row1['count_c'];
			}
			if ($cou) {
				$is_leaf =false;
			} else {
				$is_leaf =true;
			}
			$level=$this->SearchParent($id);
			$indent='';
			for ($j=0;$j<$level;$j++){
			    $indent.='&nbsp;&nbsp;&nbsp;&nbsp;';
			}
			$this->result['rows'][$i]['cell'] = array($row['id'], $row['title'],$row['size'],$level,$row['folder']);
			$i++;
		}
	}
	
	
	/**
	 * Функция подсчитывает число записей, которые будут выбраны из базы
	 * с учетом пользовательского фильтра. И возвращает их количество
	 * @return int
	 */
	private function countRecords()
	{
		$qer="select count(*) as count_c from s3objects where ".$this->buildQuery();
		$result = $this->db->query($qer);
		foreach($result as $row) {
			$count = $row["count_c"];
		}
		return $count;
	}
	/**
	 * Устанавливает порядок сортировки, если таковой задан пользователем
	 */
	private function setupOrder()
	{
		/*
		 * если указан столбец для сортировки выдачи,
		 * то добавим оператор order by
		 */ 
		if($sidx = $this->get('sidx','id'))
		{
			$direction = $this->get('sord','asc');
			$this->query['order'] = "order by $sidx $direction";
		}
	}
	
	/**
	 * Функция проверяет, установлен ли пользовательский фильтр. 
	 * Если да, до добавляет условие where к запросу.
	 */
	private function buildjqGridWhere()
	{
		$id     =  $this->get('id',0); //get id folder
		$search = $this->get('_search','false');
		if ('true' == strtolower($search)) {
			$searchData = json_decode(stripslashes($_GET['filters']));
			$firstElem = true;
			$qWhere = " 1 AND ";
	//			print_r($searchData);
			 //объединяем все полученные условия
			foreach ($searchData->rules as $rule) {
				$field = $rule->field;
				$value = $rule->data;
				$operation = $rule->op;
				if (!$firstElem) {
			         //объединяем условия (с помощью AND или OR)
					$qWhere .= ' '.$searchData->groupOp.' ';
				} else {
					$firstElem = false;
				}
				$qWhere.= $field.' '.$this->buildOperator($operation,$value);
			}
			$this->query['where']= $qWhere;
		} else {
				$this->query['where'] = " 1 "; 
		}
			
	}
	
	/**
	 * Безопасное получение переменных запроса.
	 * Если переменная не существует, то возвращается значение по умолчанию.
	 * Иначе значение переменной декодируется и возвращается/
	 * @param string $name
	 * @param string $defaultvalue
	 * @return string
	 */
	private function get($name, $defaultvalue = false)
	{
		return (isset($_REQUEST[$name]))?htmlspecialchars(urldecode($_REQUEST[$name])):$defaultvalue;
	}
	
	
	/**
	 * Функция получает на вход текстовое значение оператора сравнения.
	 * Затем выполняет поиск в массиве операторов сравнения.
	 * И если строка стравнения была найдена, то в нее подставлется 
	 * значение переменной и строка возвращается пользователю.
	 * @param string $operation
	 * @param string $value
	 * @return string
	 */
	private function buildOperator($operation,$value)
	{
		if(!array_key_exists($operation,$this->operations))
			$operation = 'eq';
		return str_replace('[val]',$value,$this->operations[$operation]);
	}
	
	private function buildQuery()
	{
		
		$result = $this->query['where'].' ';
		$result .= $this->query['order'].' ';
		$result .= $this->query['limit'];
		return $result;
	}
	
	
	private function printResult()
	{
		echo json_encode($this->result);
	}
	    /* Search all parents of this object */    
	private function SearchParent($id)
	{
		$id_parent="";
		$level=0;
		$sel="SELECT id_parent FROM s3objects WHERE id=$id AND id_parent=0 AND actual=1;";
		$result = $this->db->query($sel);
		$num=$result->fetchColumn();
		if ($num == 0) {
			$idp=$id;
			while ($idp>0){
				$sel="SELECT * FROM s3objects WHERE id=$idp AND actual=1;";
				$result = $this->db->query($sel);
				foreach($result as $row) {
					$id_parent.=$row['id']."," ;
					$idp=$row['id_parent'] ;
					$level++;
				}
			} 
				$id_parent=substr($id_parent,0,strlen($id_parent)-1);
		}
		return $level;
	}

}

$command = new bs3s();
$command->execute();
   
?>
