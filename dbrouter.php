<?php
/*

MIT License

Copyright (c) 2018 7th faily

Permission is hereby granted, free of charge, to any person obtaining a copy
of this software and associated documentation files (the "Software"), to deal
in the Software without restriction, including without limitation the rights
to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
copies of the Software, and to permit persons to whom the Software is
furnished to do so, subject to the following conditions:

The above copyright notice and this permission notice shall be included in all
copies or substantial portions of the Software.

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
SOFTWARE.

*/



class DBRouter {
	private $request = array();
	private $valid = array();
	private $db = null;

	/**
	 * 新しくルーターを作る。
	 * @param mix1 使用するPDOインスタンスを指定する。
	 * @param pdo 使用するPDOインスタンスを指定する。
	 */
	public function __construct($mix1 = null, $pdo = null) {
		if(is_string($mix1)) $base_path = $mix1;
		else {
			$this->db = $mix1;
			$base_path = '';
		}
		if($pdo) $this->db = $pdo;

		//ルートパスを基準パスとする
		$url = explode('?',$_SERVER['REQUEST_URI']);
		$this->request = explode('/',substr($url[0],strlen($base_path)));

		//デフォルトバリデーションを登録
		$this->valid[''] = array(function($value){return true;},'');
		$this->valid['str'] = array($this->valid[''][0],'');
		$this->valid['int'] = array(function($value){return ctype_digit($value);},'');
		$this->valid['session'] = array($this->valid[''][0],'');
		$this->valid['cookie'] = array($this->valid[''][0],'');
		$this->valid['server'] = array($this->valid[''][0],'');
		$this->valid['files'] = array($this->valid[''][0],'');
		$this->valid['env'] = array($this->valid[''][0],'');
		$this->valid['get'] = array($this->valid[''][0],'');
	}

	/**
	 * PDOインスタンスを登録する。
	 * @link http://php.net/manual/ja/book.pdo.php PDOのマニュアル。
	 * @param pdo PDOインスタンス。
	 * @return bool 呼び出したインスタンスを返す。pdoパラメータに何も指定しない場合、設定しているPDOインスタンスを返す。
	 */
	public function pdo($pdo = null) {
		if($pdo!==null){
			$this->db = $pdo;
			return $this;
		}else{
			return $this->db;
		}
	}

	/**
	 * 基準パスを登録する。
	 * @param base_path 基準パス。
	 * @return bool 呼び出したインスタンスを返す。
	 */
	public function base($base_path) {
		$url = explode('?',$_SERVER['REQUEST_URI']);
		$this->request = explode('/',substr($url[0],strlen($base_path)));
		return $this;
	}

	/**
	 * ルーターにgetリクエスト時の動作を登録する。
	 * @param string $route ルーターに登録するルート条件。
	 * @param string|function|array $query 条件に一致した場合に実行する処理内容。
	 * @return bool 呼び出したインスタンスを返す。
	 */
	public function get($route,$query) {

		//getリクエストでない場合は失敗
		if(strtolower($_SERVER['REQUEST_METHOD']) !== 'get') return false;

		$params = $this->resolution(INPUT_GET,$route);
		if($params !== false)
			//クエリを実行する
			$this->execute($query, $params);

		return $this;
	}

	/**
	 * ルーターにPOSTリクエスト時の動作を登録する。
	 * @param string $route ルーターに登録するルート条件。
	 * @param string|function|array $query 条件に一致した場合に実行する処理内容。
	 * @return bool 呼び出したインスタンスを返す。
	 */
	public function post($route,$query) {

		//getリクエストでない場合は失敗
		if(strtolower($_SERVER['REQUEST_METHOD']) !== 'post') return false;

		$params = $this->resolution(INPUT_POST,$route);
		if($params !== false)
			//クエリを実行する
			$this->execute($query, $params);

		return $this;
	}

	/**
	 * タイプを追加する。
	 * @param string $name 登録するタイプ名。
	 * @param function $valid 成功したらtrue、失敗したらfalseを返す関数。
	 * @param string $parent 親とするバリデーション名。初期値は'str'。
	 * @return bool 呼び出したインスタンスを返す。
	 */
	public function type($name, $valid, $parent = 'str') {

		// 登録済みのタイプ名もしくは空文字の場合は失敗。
		if(array_key_exists($name, $this->valid) || $name === '') return false;

		// 親タイプ名が存在しない場合は失敗
		if(!array_key_exists($parent, $this->valid)) return false;

		// バリデーションが関数でない場合は失敗
		if(!is_callable($valid)) return false;

		$this->valid[$name] = array($valid,$parent);
		return $this;
	}

	/**
	 * バリデーションを実行する。
	 * @param string $type 実行するバリデーション名。
	 * @param string $value 検査する値。
	 * @return bool バリデーションに通ったらtrue、通らなかったらfalseを返す。
	 */
	public function run_valid($type, $value) {

		// バリデーション名が存在しない場合は失敗
		if(!array_key_exists($type, $this->valid)) return false;

		// バリデーション名から値を検査
		while($type != '') {
			$valids[] = $this->valid[$type][0];
			$type =  $this->valid[$type][1];
		}
		while($valid = array_pop($valids)) {
			if(!$valid($value)) return false;
		}
		return true;
	}
	private function base_type($type) {
		for(;($next = $this->valid[$type][1]) !== '';$type = $next);
		return $type;
	}

	private function name_param($q) {
		if(preg_match('/^\[([_a-zA-Z][_a-zA-Z0-9]*)(=(.*))?\]$/',$q,$matches)){
			$sec = array('type'=>$matches[1], 'name'=>$matches[1], 'init'=>$matches[2], 'data'=>$matches[3]);
		}else if(preg_match('/^\[(.*):([_a-zA-Z][_a-zA-Z0-9]*)(=(.*))?\]$/',$q,$matches) || preg_match('/^(.*):([_a-zA-Z][_a-zA-Z0-9]*)(=(.*))?$/',$q,$matches)){
			if($matches[1]==='') $matches[1] = 'str';
			$sec = array('type'=>$matches[1], 'name'=>$matches[2], 'init'=>$matches[3], 'data'=>$matches[4]);
		}
		return isset($sec)?$sec:false;
	}

	private function resolution($method,$route) {
		$cond = explode('?',$route);
		$step = explode('/',$cond[0]);
		$para = explode('&',$cond[1]);
		$params = [];

		//そもそもパス数が一致しない場合は失敗
		if(count($step)!==count($this->request)) return false;

		//各ディレクトリ名が条件に一致するか検査
		for($i=0;$i<count($step);$i++){
			if($sec = $this->name_param($step[$i])){
				if($sec['init']) return false; //初期値は無し
				$base = $this->base_type($sec['type']);
				if($base==='session'||$base==='server'||$base==='cookie'||$base==='files'||$base==='env'||$base==='get') return false;

				// バリデーション
				if($this->run_valid($matches[1], $this->request[$i]))
					$params[$sec['name']] = array('type'=>$sec['type'], 'value'=>$this->request[$i]);
				else return false;
			}else{
				if($step[$i]!==$this->request[$i]) return false;
			}
		}

		//クエリパラメータの条件に一致するか検査
		foreach($para as $q) {
			if($q==='') continue;
			if($sec = $this->name_param($q)){
				switch($this->base_type($sec['type'])) {
					case 'session':
						$value = $_SESSION[$sec['name']];
						break;
					case 'server':
						$value = filter_input(INPUT_SERVER,$sec['name']);
						break;
					case 'cookie':
						$value = filter_input(INPUT_COOKIE,$sec['name']);
						break;
					case 'env':
						$value = filter_input(INPUT_ENV,$sec['name']);
						break;
					case 'get':
						$value = filter_input(INPUT_GET,$sec['name']);
						break;
					default:
						$value = filter_input($method,$sec['name']);
						break;
				}
				if($value==null)
					if($sec['init']) $value = $sec['data'];
					else return false; //必須パラメータ

				// バリデーション
				if($this->run_valid($sec['type'], $value))
					$params[$sec['name']] = array('type'=>$sec['type'], 'value'=>$value);
				else return false;
			}else{
				return false; //記載エラー
			}
		}

		return $params;
	}

	private function execute($queries, $params) {
		if(!is_array($queries)) $queries = [$queries];

		foreach($queries as $query) {
			$params = $this->single_execute($query,$params);
		}

		if(!is_callable(end($queries))){
			echo json_encode($params,JSON_UNESCAPED_UNICODE);
			exit;
		}
	}
	private function single_execute($query,$params) {

		//関数のとき
		if(is_callable($query)){
			return $query($params);
		}

		//DBクエリのとき
		$stmt = $this->db->prepare($query);
		preg_match_all('/:([a-zA-Z][a-zA-Z0-9]*)/', $query, $matches);
		foreach($matches[1] as $name) {
			if(!array_key_exists($name, $params)) throw new Exception($name.' param is not found.');
			switch($this->base_type($params[$name]['type'])){
				case 'int':
					$stmt->bindparam(':'.$name,$parames[$name]['value'],PDO::PARAM_INT);
					break;
				case 'session':
				case 'server':
				case 'cookie':
				case 'get':
				case 'str':
					$stmt->bindparam(':'.$name,$params[$name]['value'],PDO::PARAM_STR);
					break;
			}
		}
		$stmt->execute();
		return $stmt->fetchAll(PDO::FETCH_ASSOC);
	}
}