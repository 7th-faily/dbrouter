# DBRouter ![version alpha](https://img.shields.io/badge/alpha-0.3.0-red.svg) ![licence MIT](https://img.shields.io/badge/licence-MIT-blue.svg)
DBRouterはシングルファイル構成のPHP5.3+用ルーターです。PDOを利用してデータベースに直接アクセスするJSON APIを作ることができます。

## 導入
PHP5.3以上の環境が必要です。
1. ルーターを使いたいディレクトリへdbrouter.phpを設置します。
2. Apacheサーバーの場合、当該ディレクトリの.htaccessに下記を記述します。

```apache
RewriteEngine on
RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d
RewriteRule . index.php [L]
```
3. index.phpファイルにルーティング規則を記述していきます。

## 例
MySQLでユーザー情報を取得する。
```php
require_once 'dbrouter.php';

$pdo = new PDO('mysql:dbname=db1;host=localhost','account','password');

$router = new DBRouter('/api', $pdo);

// Request: /api/user/12
// Return: [{"id":12;"name":"Paul"}]
$router->get('/user/[:id]', 'SELECT * FROM user WHERE id = :id');

exit;
```

## ルーターの初期化

DBRouterを利用するにはまず、インスタンスを生成します。このとき、パラメータにルーティング基準ととなるパスと、PDOインスタンスを渡すことができます。

#### [例] ルートディレクトリを基準とする場合
```php
$router = new DBRouter();
```

#### [例] apiディレクトリを基準とする場合
```php
$router = new DBRouter('/api');
```

#### [例] ルートディレクトリを基準としてPDOを用いる場合
```php
$router = new DBRouter(new PDO('...'));
```

## PDOの設定
DBRouterでデータベースアクセス機能を利用する場合、コンストラクタもしくはpdoメソッドでPDOインスタンスを設定します。データベースアクセス機能を利用しない場合はこの作業は必要ありません。
```php
$pdo = new PDO('...');
$router->pdo($pdo);
```

## ルーティング
DBRouterでルーティングを行うには、getもしくはpostメソッドを利用します。getとpostの両方のリクエストを受け付けたい場合は同じパラメータ2行メソッドを書きます。

```php
public function DBRouter::get(string $route, mixed $query)
```
```php
public function DBRouter::post(string $route, mixed $query)
```
### $route引数（ルート）
$routeにはリクエストを受け付けるスラッシュ区切りのパスとアンパサンド区切りのクエリパラメータを指定します。ここでは文字列だけでなく [...] で囲まれた[名前付きパラメータ](#名前付きパラメータ)を受け入れることができます。[名前付きパラメータ](#名前付きパラメータ)については後述します。ここではは変数と捉えてください。

#### 例
```php
'/user/[:id]/profile'
```
$routeには、パスだけでなく受け取るクエリパラメータ（POSTデータ）を指定できます。例えば下記のルートでは、kindパラメータを付けていないリクエストにはマッチしません。必須ではないパラメータを受け入れたい場合は[デフォルト値](##デフォルト値)を参照してください。
```php
'/product/search?[:kind]'
```

### $query引数（クエリ）
$queryはSQL文、関数もしくはそれらを組み合わせた配列を指定できます。

### SQL文
SQL文については[PDO::prepare](http://php.net/manual/ja/pdo.prepare.php)の記述方法に準じます。$routeで指定した[名前付きパラメータ](#名前付きパラメータ)を利用できます。
$queryにSQL文を指定するか、配列の最後をSQL文にした場合、get/postメソッドはSQL文を実行した結果をjsonデータで出力して処理を終了します。

#### 例
```php
$router->get('/user/[:id]','SELECT * FROM user WHERE id = :id');
```

### 関数
無名関数もしくは可変関数を利用できます。関数はDBRouterインスタンスにpdoプロパティを設定していなくても利用できるので、データベースにこだわらない自由なルーティングが可能です。関数は[名前付きパラメータ](#名前付きパラメータ)を$param配列で受け取ります。
#### [例] 無名関数
```php
$router->get('/user/[:id]',function($param){
	echo $param['id']->value;
	exit;
});
```
#### [例] 可変関数
```php
$router->get('/user/[:id]','get_profile');

function get_profile($param) {
	echo $param['id']->value;
	exit;
}
```

### 配列
SQL文と関数を組み合わせた配列を$queryに指定できます。例えば以下の例では企業IDとして受け取った文字列から先頭の2文字を削除してからSQL文を実行します。
```php
$router->get('/company/[:cid]',array('cid_filter','SELECT * FROM company WHERE cid = :cid'));

function cid_filter($param) {
	$param['cid']->value = substr($param['cid']->value, 2);
	return $param;
}
```

### 戻り値
getおよびpostメソッドはルートにマッチして最後のクエリ実行結果がSQL文の場合、結果をJSON形式で出力して処理を終了します。
ルートにマッチしなかった場合、もしくは最後のクエリがSQL文でない場合、メソッドを実行したDBRouterインスタンスを返します。これによりメソッドチェーンを実現します。

## 名前付きパラメータ
ルートを記述する際に用いる [...] で囲まれた部分を**名前付きパラメータ**と呼びます。
名前の由来はPDOの名前付きパラメータです。
名前付きパラメータはリクエストから受け取ったパラメータをSQLに受け渡す役割を持ちます。入力値バリデーションとデフォルト値の設定が可能です。セッション値やCookie値を受け取る記法があります。

### 名前付きパラメータの構成
名前付きパラメータはコロンから始まる**ネーム部**とコロンよりも前に記述する**タイプ部**とイコールから始まる**デフォルト部**から成ります。
ただし、パスを定義するときはデフォルト値は利用できません。
```php
'[type:name=default]'
```

### ネーム部
コロンから始まる部分をネーム部と呼びます。名前付きパラメータにおいてこの部分は必須です。
リクエストから受け入れてPDOのSQL文に渡す名前付きパラメータの名前を定義します。

### タイプ部
コロンよりも前に記述する部分をタイプ部と呼びます。リクエストから受け入れる入力値のタイプを決めます。
タイプは入力値をどこから受け取るか、どんな値を受け取るかを表します。
入力値はクエリに渡される前にあらかじめ定義したバリデーションが適応され、タイプに合わない値が渡されるとルートにマッチしません。

#### 定義済みタイプ
|タイプ |効果 |
|---|---|
|str|どんな入力値でも受けとります。タイプ部を省略した場合、このタイプとなります。|
|int|整数値のみ受け取ります。|
|session|入力値をリクエストからではなく$_SESSIONから受け取ります。|
|cookie|入力値をリクエストからではなく$_COOKIEから受け取ります。|
|server|入力値をリクエストからではなく$_SERVERから受け取ります。|
|~~files~~ *予約|~~入力値をリクエストからではなく$_FILESから受け取ります。~~|
|env|入力値をリクエストからではなく$_ENVから受け取ります。|
|get|入力値をPOSTリクエストの場合もリクエストパラメータから受け取ります。|

#### タイプ定義
タイプはtypeメソッドを用いて新しく作ることができます。
```php
public function DBRouter::type(string $name, function $valid [, string $parent = 'str'])
```
第一引数には新しく定義するタイプ名、第二引数には入力値をとりバリデーション結果を返す関数、第三引数には基となるタイプを指定します。
戻り値はDBRouterインスタンスを返します。これによりメソッドチェーンを実現します。

### デフォルト部
イコールから始まる部分をデフォルト部と呼びます。
デフォルト部を省略した場合、その名前付きパラメータは入力値が必須となり、入力値が無いときはルートにマッチしません。
逆にデフォルト部を記述した場合、入力値が無くてもルートにマッチして、イコールよりも後ろに記述した値がクエリに渡されます。
また、イコールよりも後ろの値を省略してイコールのみ記述した場合、デフォルト値は空文字となります。

### 省略記法
ネーム部とタイプ部は同じ名称になることがあります。
`[id:id]`や`[name:name=]`など。
これらは省略して以下のように記述できます。
`[id]`  `[name=]`


## ライセンス

MIT License

Copyright (c) 2018 7th faily

Permission is hereby granted, free of charge, to any person obtaining a copy of this software and associated documentation files (the "Software"), to deal in the Software without restriction, including without limitation the rights to use, copy, modify, merge, publish, distribute, sublicense, and/or sell copies of the Software, and to permit persons to whom the Software is furnished to do so, subject to the following conditions:

The above copyright notice and this permission notice shall be included in all copies or substantial portions of the Software.

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM, OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE SOFTWARE.