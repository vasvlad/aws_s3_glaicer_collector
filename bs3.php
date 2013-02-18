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
		$folder     =  $this->get('f',0); //get id folder
		$level      =  $this->get('n_level',0); //get id folder
		if ($folder>0) {
			$level = $level+1;
			$result = $this->db->query(" SELECT id, title, id_parent,size FROM s3objects WHERE ".$this->buildQuery());
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
				$this->result['rows'][$i]['cell'] = array($row['id'], $row['title'],$row['size'],$level,$parent , $is_leaf, FALSE);
				$i++;
			}
		} else {
			$result = $this->db->query(" SELECT id, title, id_parent,size FROM s3objects WHERE ".$this->buildQuery());
//echo "qu="." SELECT id, title, id_parent,size FROM s3objects WHERE ".$this->buildQuery();
			$i=0; 
			foreach($result as $myrow) {
				$this->result['rows'][$i]['id']=$myrow['id'];
				$this->result['rows'][$i]['cell']=array($myrow['id'],$myrow['title'],$myrow['size']); 
				$i+=1;
			}
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
		$folder     =  $this->get('f',0); //get id folder
		$search = $this->get('_search','false');
		if ('true' == strtolower($search)) 
		{
			
			$searchData = json_decode(stripslashes($_GET['filters']));
			
			$firstElem = true;
			
			$qWhere = " id_parent=$id and folder =$folder AND ";
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
			if ($folder==1) {
				$node = $this->get('nodeid',0);
				if($node > 0) {
					$this->query['where'] = 'id_parent ='.$node.' AND folder=1';
				} else {
					$this->query['where'] = 'id_parent=0';   // Select Root 
				} 
			} else {
				$this->query['where'] = " id_parent=$id and folder =$folder ";	
			}
			
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
}

$command = new bs3s();
$command->execute();
   
?>
