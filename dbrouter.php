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

	/**
	 * @var データベースアクセスに利用するPHP Data Objects
	 * @link http://php.net/manual/ja/book.pdo.php PDOのマニュアル。
	 */
	public $pdo = null;

	/**
	 * 新しくルーターを作る。
	 * @base_path 基準パスを登録する。指定しなかった場合基準パスはルートパスとなる。
	 */
	public function __construct($base_path = '') {

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
	 * ルーターにgetリクエスト時の動作を登録する。
	 * @param string $route ルーターに登録するルート条件。
	 * @param string|function|array $query 条件に一致した場合に実行する処理内容。
	 * @param bool $end 条件に一致してクエリが終了した後に処理を終了する場合はtrue、終了しない場合はfalseを指定する。初期値はtrue。
	 * @return bool 条件に一致してクエリが終了したら最後に実行したクエリの戻り値、条件に一致しなかったたらfalseを返す。
	 */
	public function get($route,$query,$end = true) {

		//getリクエストでない場合は失敗
		if(strtolower($_SERVER['REQUEST_METHOD']) !== 'get') return false;

		$params = $this->resolution(INPUT_GET,$route);
		if($params === false) return false;

		//クエリを実行する
		return $this->execute($query, $params, $end);
	}

	/**
	 * ルーターにPOSTリクエスト時の動作を登録する。
	 * @param string $route ルーターに登録するルート条件。
	 * @param string|function|array $query 条件に一致した場合に実行する処理内容。
	 * @param bool $end 条件に一致してクエリが終了した後に処理を終了する場合はtrue、終了しない場合はfalseを指定する。初期値はtrue。
	 * @return bool 条件に一致してクエリが終了したら最後に実行したクエリの戻り値、条件に一致しなかったたらfalseを返す。
	 */
	public function post($route,$query,$end = true) {

		//getリクエストでない場合は失敗
		if(strtolower($_SERVER['REQUEST_METHOD']) !== 'post') return false;

		$params = $this->resolution(INPUT_POST,$route);
		if($params === false) return false;

		//クエリを実行する
		return $this->execute($query, $params, $end);
	}

	/**
	 * タイプを追加する。
	 * @param string $name 登録するタイプ名。
	 * @param function $valid 成功したらtrue、失敗したらfalseを返す関数。
	 * @param string $parent 親とするバリデーション名。初期値は'str'。
	 * @return bool 成功したらtrue、失敗したらfalseを返す。
	 */
	public function type($name, $valid, $parent = 'str') {

		// 登録済みのタイプ名もしくは空文字の場合は失敗。
		if(array_key_exists($name, $this->valid) || $name === '') return false;

		// 親タイプ名が存在しない場合は失敗
		if(!array_key_exists($parent, $this->valid)) return false;

		// バリデーションが関数でない場合は失敗
		if(!is_callable($valid)) return false;
		$this->valid[$name] = array($valid,$parent);
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
		for(;$next = $this->valid[$type][1] !== '';$type = $next);
		return $type;
	}


	private function resolution($method,$route) {
		$cond = explode('?',$route);
		$step = explode('/',$cond[0]);
		$para = explode('&',$cond[1]);
		$params = [];

		//そもそもパス数が一致しない場合は失敗
		if(count($step)!==count($this->request)) return false;

		//各ディレクトリ名が条件に一致するか検査
		for($i=0;$i<=count($step);$i++){
			if(preg_match('/^\[(.*):(.+)\]$/',$step[$i],$matches)){
				if($matches[1]==='') $matches[1] = 'str';
				if($matches[1]==='session'||$matches[1]==='server'||$matches[1]==='cookie'||$matches[1]==='files'||$matches[1]==='env'||$matches[1]==='get') return false;

				// バリデーション
				if($this->run_valid($matches[1], $this->request[$i]))
					$params[$matches[2]] = array('type'=>$matches[1], 'value'=>$this->request[$i]);
				else return false;
			}else{
				if($step[$i]!==$this->request[$i]) return false;
			}
		}

		//クエリパラメータの条件に一致するか検査
		foreach($para as $q) {
			if($q==='') continue;
			if(preg_match('/^\[(.*):([_a-zA-Z][_a-zA-Z0-9]*)(=(.*))?\]$/',$q,$matches)){
				switch(base_type($matches[1])) {
					case 'session':
						$value = $_SESSION[$matches[2]];
						break;
					case 'server':
						$value = filter_input(INPUT_SERVER,$matches[2]);
						break;
					case 'cookie':
						$value = filter_input(INPUT_COOKIE,$matches[2]);
						break;
					case 'env':
						$value = filter_input(INPUT_ENV,$matches[2]);
						break;
					case 'get':
						$value = filter_input(INPUT_GET,$matches[2]);
						break;
					case '':
						$matches[1] = 'str';
					default:
						$value = filter_input($method,$matches[2]);
						break;
				}
				if($value==null)
					if($matches[3]) $value = $matches[4];
					else return false; //必須パラメータ

				// バリデーション
				if($this->run_valid($matches[1], $value))
					$params[$matches[2]] = array('type'=>$matches[1], 'value'=>$value);
				else return false;
			}else{
				return false; //記載エラー
			}
		}

		return $params;
	}

	private function execute($queries, $params, $end) {
		if(!is_array($queries)) $queries = [$queries];

		foreach($queries as $query) {
			$param = $this->single_execute($query,$params);
		}

		if($end){
			if(!is_callable(end($queries)))
				echo json_encode($param,JSON_UNESCAPED_UNICODE);
			exit;
		}

		return $param;
	}
	private function single_execute($query,$params) {

		//関数のとき
		if(is_callable($query)){
			return $query($params);
		}

		//DBクエリのとき
		$stmt = $this->pdo->prepare($query);
		preg_match_all('/:([a-zA-Z][a-zA-Z0-9]*)/', $query, $matches);
		foreach($matches[1] as $name) {
			if(!array_key_exists($name, $params)) throw new Exception($name.' param is not found.');
			switch(base_type($params[$name]['type'])){
				case 'int':
					$stmt->bindparam(':'.$key,$parames[$name]['value'],PDO::PARAM_INT);
					break;
				case 'session':
				case 'server':
				case 'cookie':
				case 'get':
				case 'str':
					$stmt->bindparam(':'.$key,$params[$name]['value'],PDO::PARAM_STR);
					break;
			}
		}
		$stmt->execute();
		return $stmt->fetchAll(PDO::FETCH_ASSOC);
	}
}