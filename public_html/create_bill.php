<?php
#共通関数のインクルード
include 'common_functions.php';

#エラーを画面に表示させない処理
ini_set("display_errors", "off");
#reviser呼び出し
require_once('reviser_lite.php');
#データベース接続処理
#db接続データの参照
$path = parse_ini_file("../rms.cnf");		
foreach($path as $key => $db_path) {
	$configs = parse_ini_file($db_path);
}
foreach($configs as $key => $value) {
	switch ($key) {
		case 'db_cdr':
			$db_cdr = $value;
			break;
		case 'db_request':
			$db_request = $value;
			break;
		case 'db_wordpress':
			$db_wordpress = $value;
			break;
		case 'host':
			$host = $value;
			break;
		case 'name':
			$name = $value;
			break;
		case 'pass':
			$pass = $value;
			break;
	}
}

#グローバル変数
$pdo_request = null;
$pdo_cdr = null;
$pdo_wordpress = null;

#cdrへの接続
$dsn_cdr ="mysql:dbname=$db_cdr;host=$host";
try {	
	$pdo_cdr = new PDO($dsn_cdr, $name, $pass);
} catch (PDOException $e) {
	exit('接続ミス'.$e->getMessage());
}
$stmt = $pdo_cdr->query('SET NAMES utf8');
if (!$stmt) {
	$info=$pdo_cdr->errorinfo();
	exit($info[2]);
}

#smk_request_dataへの接続
$dsn_request ="mysql:dbname=$db_request;host=$host";
try {	
	$pdo_request = new PDO($dsn_request,$name,$pass);
} catch (PDOException $e) {
	exit('接続ミス'.$e->getMessage());
}
$stmt = $pdo_request->query('SET NAMES utf8');
if(!$stmt) {
	$info=$pdo_request->errorinfo();
	exit($info[2]);
}

#wordpressへの接続
$dsn_wordpress = "mysql:dbname=$db_wordpress;host=$host";
try {	
	$pdo_wordpress = new PDO($dsn_wordpress,$name,$pass);
} catch(PDOException $e) {
	exit('接続ミス'.$e->getMessage());
}
$stmt=$pdo_wordpress->query('SET NAMES utf8');
if(!$stmt) {
	$info = $pdo_wordpress->errorinfo();
	exit($info[2]);
}

#フォームからの請求先IDの受け取り
$bill_payer_id = $_POST['bill_payer_id'];
#フォームからの年月の受け取り
$year = $_POST['year'];
$month = $_POST['month'];
if (empty($year)|| empty($month)) {
	print('<!DOCTYPE html>');
	print('<html lang="ja">');
	print('<head>');
	print('<meta charset="UTF-8">');
	print('<title>作成できません</title>');
	print('</head>');
	print('<body>');
	print('<a href="senmonka-RMS.php">戻る</a>');
	print("<br>");
	print("年月が未指定です。");
	print('</body>');
	print('</html>');
	die();
}
#一桁の月に"0"を付加
$month = sprintf("%02d",$month);
$year_month = "$year"."$month";
#請求有効件数が0であった場合には出力しない

//一括ダウンロードかどうか
$pack = $_POST['pack'];

if (!empty($bill_payer_id)) {
	#チェック関数を呼び出し、nullで無ければExcelに書き出す
	$call_check = check_valid_call($bill_payer_id,$year,$month);
	$mail_check = check_valid_mail($bill_payer_id,$year,$month);
	if (!empty($call_check)|| !empty($mail_check)) {
		$reviser = NEW Excel_Reviser;
		$reviser->setInternalCharset('utf-8');	
		get_each_ad_data($reviser, $bill_payer_id, $year, $month, $year_month);
	}
	else if (empty($call_check) && empty($mail_check)) {
		print('<!DOCTYPE html>');
		print('<html lang="ja">');
		print('<head>');
		print('<meta charset="UTF-8">');
		print('<title>作成できません</title>');
		print('</head>');
		print('<body>');
		print('<a href="senmonka-RMS.php">戻る</a>');
		print("<br>");
		print("この年月では、この事務所は有効電話数とメール数が０件です");
		print('</body>');
		print('</html>');
		die();
	}
}
else if (!empty($pack) && $pack == "true") {
	$ids = get_billing_ids($year, $month);
	$path = "../tmp/$year_month";
	if (is_dir($path)) {
		remove_directory($path);
	}
	if (is_file("$path.zip")) {
		unlink("$path.zip");
	}
	if (!mkdir($path, 0777, true)) {
		die("Failed to create dirs.");
	};
	foreach ($ids as $id) {
		$reviser = NEW Excel_Reviser;
		$reviser->setInternalCharset('utf-8');	
		get_each_ad_data($reviser, $id, $year, $month, $year_month, $path);
	}
	try {
		all_zip("../tmp/$year_month", "../tmp/$year_month.zip");
		remove_directory($path);
		header('Pragma: public');
		header("Content-Type: application/octet-stream");
		header("Content-Disposition: attachment; filename=$year_month.zip");
		readfile("../tmp/$year_month.zip");
	} catch (Exception $e) {
		die("Failed to create zip.");
	}
}
else {
		print('<!DOCTYPE html>');
		print('<html lang="ja">');
		print('<head>');
		print('<meta charset="UTF-8">');
		print('<title>作成できません</title>');
		print('</head>');
		print('<body>');
		print('<a href="senmonka-RMS.php">戻る</a>');
		print("<br>");
		print("事務所が指定されていません");
		print('</body>');
		print('</html>');
		die();
}

//--------------------------------------------------------------------------
// ディレクトリZIP圧縮
//--------------------------------------------------------------------------
function all_zip( $dir_path, $new_dir )
{
 $zip = new ZipArchive();
 if( $zip->open( $new_dir, ZipArchive::OVERWRITE ) === true ){
  add_zip( $zip, $dir_path, "" );
  $zip->close();
 }
 else{
  throw new Exception('It does not make a zip file');
 }
}
 
//--------------------------------------------------------------------------
// 再起的にディレクトリかファイルを判断し、ストリームに追加する
//--------------------------------------------------------------------------
function add_zip( $zip, $dir_path, $new_dir )
{
 if( ! is_dir( $new_dir ) ){
  $zip->addEmptyDir( $new_dir );
 }
 
 foreach( get_inner_path_of_directory( $dir_path ) as $file ){
  if( is_dir( $dir_path . "/" . $file ) ){
   add_zip( $zip, $dir_path . "/" . $file, $new_dir . "/" . $file );
  }
  else{
   $zip->addFile( $dir_path . "/" . $file, $new_dir . "/" . mb_convert_encoding($file, "SJIS-WIN", "UTF-8") ) ;
  }
 }
}
 
//--------------------------------------------------------------------------
// ディレクトリ内の一覧を取得する
//--------------------------------------------------------------------------
function get_inner_path_of_directory( $dir_path )
{
 $file_array = array();
 if( is_dir( $dir_path ) ){
  if( $dh = opendir( $dir_path ) ){
   while( ( $file = readdir( $dh ) ) !== false ){
    if( $file == "." || $file == ".." ){
     continue;
    }
    $file_array[] = $file;
   }
   closedir( $dh );
  }
 }
 sort( $file_array );
 return $file_array;
}

function remove_directory($dir) {
	if ($handle = opendir("$dir")) {
		while (false !== ($item = readdir($handle))) {
			if ($item != "." && $item != "..") {
				if (is_dir("$dir/$item")) {
					remove_directory("$dir/$item");
				}
				else {
					unlink("$dir/$item");
					//echo " removing $dir/$item<br>\n";
				}
			}
		}
		closedir($handle);
		rmdir($dir);
		//echo "removing $dir<br>\n";
	}
}

function get_billing_ids($year, $month) {
	global $pdo_request;
	$stmt = $pdo_request->query("
		SELECT
			bill_payer_id
		FROM
			monthly_valid_call
		WHERE
			year = $year AND
			month = $month AND
			(
				valid_call_shakkin is not null OR
				valid_call_souzoku is not null OR
				valid_call_koutsujiko is not null OR
				valid_call_ninibaikyaku is not null OR
				valid_call_meigihenkou is not null OR
				valid_call_setsuritsu is not null OR
				valid_call_keijijiken is not null OR
				valid_call_rikon is not null
			)
	");
	$ids = array();
	$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
	foreach ($rows as $row) {
		array_push($ids, $row["bill_payer_id"]);
	}

	$stmt = $pdo_request->query("
		SELECT
			bill_payer_id
		FROM
			smk_request_data.monthly_mail_num
		WHERE
			year = $year AND
			month = $month AND
			(
				mail_shakkin is not null OR
				mail_souzoku is not null OR
				mail_koutsujiko is not null OR
				mail_ninibaikyaku is not null OR
				mail_meigihenkou is not null OR
				mail_setsuritsu is not null OR
				mail_rikon is not null
			)
	");

	$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
	foreach ($rows as $row) {
		array_push($ids, $row["bill_payer_id"]);
	}

	$ids = array_unique($ids);
	asort($ids);

	return $ids;
}


#call_check関数
function check_valid_call($bill_payer_id,$year,$month){
	global $pdo_request;
	$stmt = $pdo_request->query("
		SELECT
			valid_call_shakkin,
			valid_call_souzoku,
			valid_call_koutsujiko,
			valid_call_meigihenkou,
			valid_call_setsuritsu,
			valid_call_keijijiken,
			valid_call_rikon
		FROM
			monthly_valid_call
		WHERE
			bill_payer_id = $bill_payer_id AND
			year=$year AND
			month=$month
	");
	$arr_call_check = $stmt->fetchAll(PDO::FETCH_ASSOC);
	foreach ($arr_call_check as $row) {
		$all_call_check += $row['valid_call_shakkin'];
		$all_call_check += $row['valid_call_souzoku'];
		$all_call_check += $row['valid_call_koutsujiko'];
		$all_call_check += $row['valid_call_meigihenkou'];
		$all_call_check += $row['valid_call_setsuritsu'];
		$all_call_check += $row['valid_call_keijijiken'];
		$all_call_check += $row['valid_call_rikon'];
	}
	return $all_call_check;
}
//end_of_function

#mail_check関数
function check_valid_mail($bill_payer_id,$year,$month){
	global $pdo_request;
	$stmt = $pdo_request->query("
		SELECT
			mail_shakkin,
			mail_souzoku,
			mail_koutsujiko,
			mail_meigihenkou,
			mail_setsuritsu,
			mail_rikon
		FROM
			monthly_mail_num
		WHERE
			bill_payer_id = $bill_payer_id AND
			year=$year AND
			month=$month
	");
	$arr_mail_check = $stmt->fetchAll(PDO::FETCH_ASSOC);
	foreach ($arr_mail_check as $row) {
		$all_mail_check += $row['mail_shakkin'];
		$all_mail_check += $row['mail_souzoku'];
		$all_mail_check += $row['mail_koutsujiko'];
		$all_mail_check += $row['mail_meigihenkou'];
		$all_mail_check += $row['mail_setsuritsu'];
		$all_mail_check += $row['mail_rikon'];
	}
	return $all_mail_check;
}
//end_of_function

function get_each_ad_data($reviser, $bill_payer_id, $year, $month, $year_month, $filepath = null) {
	#####有効コール請求内容データの取得
	global $pdo_request,$pdo_cdr,$pdo_wordpress;
	#無効も含めた全てのコール数
	$all_call_shakkin = null;
	$all_call_souzoku = null;
	$all_call_koutsujiko = null;
	$all_call_ninibaikyaku = null;
	$all_call_meigihenkou = null;
	$all_call_setsuritsu = null;
	$all_call_keijijiken = null;
	$all_call_rikon = null;
	#無効も含めた全てのメール数
	$all_mail_shakkin = null;
	$all_mail_souzoku = null;
	$all_mail_koutsujiko = null;
	$all_mail_ninibaikyaku = null;
	$all_mail_meigihenkou = null;
	$all_mail_setsuritsu = null;
	$all_mail_rikon = null;
	#メール日
	$shakkin_mail_dt = null;
	$souzoku_mail_dt = null;
	$koutsujiko_mail_dt = null;
	$ninibaikyaku_mail_dt = null;
	$meigihenkou_mail_dt = null;
	$setsuritsu_mail_dt = null;
	$rikon_mail_dt = null;
	$mail_dt = null;
	#無効詳細内容の取得
	$inv_shakkin = null;
	$inv_souzoku = null;
	$inv_koutsujiko = null;
	$inv_ninibaikyaku = null;
	$inv_meigihenkou = null;
	$inv_setsuritsu = null;
	$inv_keijijiken = null;
	$inv_rikon = null;
	#メディア毎有効請求
	$res_shakkin = null;
	$res_souzoku = null;
	$res_koutsujiko = null;
	$res_ninibaikyaku = null;
	$res_meigihenkou = null;
	$res_setsuritsu = null;
	$res_keijijiken = null;
	$res_rikon = null;

	#合計詳細
	$va_shakkin= null;
	$va_souzoku = null;
	$va_koutsujiko = null;
	$va_ninibaikyaku = null;
	$va_meigihenkou = null;
	$va_setsuritsu = null;
	$va_keijijiken = null;
	$va_rikon = null;
	#template文
	$all_tmp = null;
	#空の配列作成
	$arr_shakkin_mail_dt = array();
	$arr_souzoku_mail_dt = array();
	$arr_koutsujiko_mail_dt = array();
	$arr_ninibaikyaku_mail_dt = array();
	$arr_meigihenkou_mail_dt = array();
	$arr_setsuritsu_mail_dt = array();
	$arr_rikon_mail_dt = array();


	$sheet_num =0;
	$stmt = $pdo_request->query("
		SELECT
			*
		FROM
			monthly_valid_call
		WHERE
			bill_payer_id = $bill_payer_id AND
			year = $year AND
			month = $month
	");
	$req_mvc_data = $stmt->fetch(PDO::FETCH_ASSOC);
	#問題毎コール数の取得
	$shakkin_call = $req_mvc_data['valid_call_shakkin'];
	$souzoku_call = $req_mvc_data['valid_call_souzoku'];
	$koutsujiko_call = $req_mvc_data['valid_call_koutsujiko'];
	$ninibaikyaku_call = $req_mvc_data['valid_call_ninibaikyaku'];
	$meigihenkou_call = $req_mvc_data['valid_call_meigihenkou'];
	$setsuritsu_call = $req_mvc_data['valid_call_setsuritsu'];
	$keijijiken_call = $req_mvc_data['valid_call_keijijiken'];
	$rikon_call = $req_mvc_data['valid_call_rikon'];
	$call_sum = $req_mvc_data['call_sum'];
	####課金メール数請求内容データの取得
	$stmt2 = $pdo_request->query("
		SELECT
			*
		FROM
			monthly_mail_num
		WHERE
			bill_payer_id = $bill_payer_id AND
			year = $year AND
			month = $month"
	);
	$req_mail_data = $stmt2->fetch(PDO::FETCH_ASSOC);
	#問題ごとメール数の取得
	$shakkin_mail = $req_mail_data['mail_shakkin'];
	$souzoku_mail = $req_mail_data['mail_souzoku'];
	$koutsujiko_mail = $req_mail_data['mail_koutsujiko'];
	$ninibaikyaku_mail = $req_mail_data['mail_ninibaikyaku'];
	$meigihenkou_mail = $req_mail_data['mail_meigihenkou'];
	$setsuritsu_mail = $req_mail_data['mail_setsuritsu'];
	$rikon_mail = $req_mail_data['mail_rikon'];
	$mail_sum = $req_mail_data['mail_sum'];
	#請求合計数の取得
	$all_sum = $call_sum+$mail_sum;
	#######無効も含めた全コール数,メール数の取得
	#無効アリコール数
	$stmt = $pdo_request->query("
		SELECT
			ad_group_id
		FROM
			ad_group_bill_payer
		WHERE
			bill_payer_id = $bill_payer_id
	");
	$arr_ad_group_id =$stmt->fetchAll(PDO::FETCH_ASSOC);
	foreach ($arr_ad_group_id as $row) {
		$ad_group_id = $row['ad_group_id'];
		$stmt = $pdo_cdr->query("
			SELECT
				media_id
			FROM
				call_data_view
			WHERE
				ad_group_id = $ad_group_id AND
				DATE_FORMAT(date_from,'%Y%m') = $year_month
		");
		$arr_all_call_data =$stmt->fetchAll(PDO::FETCH_ASSOC);
		foreach($arr_all_call_data as $row) {
			$mi = $row['media_id'];
			if($mi == "B") {
				$all_call_souzoku++;
			}
			else if($mi == "C") {
				$all_call_koutsujiko++;
			}
			else if($mi == "D") {
				$all_call_ninibaikyaku++;
			}
			else if($mi == "E") {
				$all_call_meigihenkou++;
			}
			else if($mi == "F") {
				$all_call_setsuritsu++;
			}
			else if($mi == "G") {
				$all_call_keijijiken++;
			}	
			else if($mi == "H") {
				$all_call_rikon++;
			}	
			else {
				$all_call_shakkin++;
			}
		}
		#無効アリメール数,メール日取得
		$stmt2 = $pdo_cdr->query("
			SELECT
				mc.site_type,
				DATE_FORMAT(mc.register_dt,'%m%d') as reg_dt
			FROM
				cdr.mail_conv mc,
				wordpress.ss_advertiser_ad_group aadg
			WHERE
				aadg.advertiser_id = mc.advertiser_id AND
				DATE_FORMAT(mc.register_dt,'%Y%m') = $year_month AND
				aadg.ad_group_id = $ad_group_id
		");
		$arr_all_mail_data = $stmt2->fetchAll(PDO::FETCH_ASSOC);
		foreach($arr_all_mail_data as $row) {
			$st = $row['site_type'];
			$register_dt = $row["reg_dt"];
			$mail_month = substr($register_dt, 0, 2);
			$mail_day = substr($register_dt, 2, 2);
			$mail_day = sprintf('%01d', $mail_day);
			if ($st == 3) {
				$all_mail_souzoku++;
				array_push($arr_souzoku_mail_dt, $mail_day);
				asort($arr_souzoku_mail_dt);
			}
			else if ($st == 14) {
				$all_mail_koutsujiko++;
				array_push($arr_koutsujiko_mail_dt, $mail_day);
				asort($arr_koutsujiko_mail_dt);
			}
			else if ($st == 16) {
				$all_mail_ninibaikyaku++;
				array_push($arr_ninibaikyaku_mail_dt, $mail_day);
				asort($arr_ninibaikyaku_mail_dt);
			}
			else if ($st == 17) {
				$all_mail_meigihenkou++;
				array_push($arr_meigihenkou_mail_dt, $mail_day);
				asort($arr_meigihenkou_mail_dt);
			}
			else if ($st == 18) { 
				$all_mail_setsuritsu++;
				array_push($arr_setsuritsu_mail_dt, $mail_day);
				asort($arr_setsuritsu_mail_dt);
			}
			else if ($st == 20) { 
				$all_mail_rikon++;
				array_push($arr_rikon_mail_dt, $mail_day);
				asort($arr_rikon_mail_dt);
			}
			else {
				$all_mail_shakkin++;
				array_push($arr_shakkin_mail_dt, $mail_day);
				asort($arr_shakkin_mail_dt);
			}
		}
	}
	#配列のメール日数を変数に入れる処理
	//借金
	foreach ($arr_shakkin_mail_dt as $row) {
		$shakkin_mail_dt .= $row."日・";
	}
	$shakkin_mail_dt = rtrim($shakkin_mail_dt,'・');
	$shakkin_mail_dt = "(".$shakkin_mail_dt.")";
	//相続
	foreach ($arr_souzoku_mail_dt as $row) {
		$souzoku_mail_dt .= $row."日・";
	}
	$souzoku_mail_dt = rtrim($souzoku_mail_dt,'・');
	$souzoku_mail_dt = "(".$souzoku_mail_dt.")";
	//交通事故
	foreach ($arr_koutsujiko_mail_dt as $row) {
		$koutsujiko_mail_dt .= $row."日・";
	}
	$koutsujiko_mail_dt = rtrim($koutsujiko_mail_dt,'・');
	$koutsujiko_mail_dt = "(".$koutsujiko_mail_dt.")";
	//任意売却
	foreach ($arr_ninibaikyaku_mail_dt as $row) {
		$ninibaikyaku_mail_dt .= $row."日・";
	}
	$ninibaikyaku_mail_dt = rtrim($ninibaikyaku_mail_dt,'・');
	$ninibaikyaku_mail_dt = "(".$ninibaikyaku_mail_dt.")";
	//名義変更
	foreach ($arr_meigihenkou_mail_dt as $row) {
		$meigihenkou_mail_dt .= $row."日・";
	}
	$meigihenkou_mail_dt = rtrim($meigihenkou_mail_dt,'・');
	$meigihenkou_mail_dt = "(".$meigihenkou_mail_dt.")";
	//設立
	foreach ($arr_setsuritsu_mail_dt as $row) {
		$setsuritsu_mail_dt .= $row."日・";
	}
	$setsuritsu_mail_dt = rtrim($setsuritsu_mail_dt,'・');
	$setsuritsu_mail_dt = "(".$setsuritsu_mail_dt.")";
	//離婚
	foreach ($arr_rikon_mail_dt as $row) {
		$rikon_mail_dt .= $row."日・";
	}
	$rikon_mail_dt = rtrim($rikon_mail_dt,'・');
	$rikon_mail_dt = "(".$rikon_mail_dt.")";

	#####請求対象のの取得
	$stmt3 = $pdo_request->query("
		SELECT
			*
		FROM
			bill_payers
		WHERE
			bill_payer_id = $bill_payer_id
	");
	$bill_payer_data = $stmt3->fetch(PDO::FETCH_ASSOC);
	$bill_payer_name = $bill_payer_data['bill_payer_name'];

	#メール担当者名
	$recipient_name  = $bill_payer_data['mail_recipient'];
	#御担当者名
	$c_name  = $bill_payer_data['person_in_charge'];
	/*問題毎のメールテンプレート文*/
	#借金all_tmp
	if ($all_call_shakkin != null && $all_mail_shakkin != null) {
		$shakkin_all_tmp = "借金問題サイトで".$all_call_shakkin."件の電話と".$all_mail_shakkin."件のメール".$shakkin_mail_dt;
	}
	else if ($all_call_shakkin != null && $all_mail_shakkin == null) {
		$shakkin_all_tmp = "借金問題サイトで".$all_call_shakkin."件の電話";
	}
	else if ($all_call_shakkin == null && $all_mail_shakkin != null) {
		$shakkin_all_tmp = "借金問題サイトで".$all_mail_shakkin."件のメール".$shakkin_mail_dt;
	}
	else {
		$shakkin_all_tmp = "";
	}
	#相続all_tmp
	if ($all_call_souzoku != null && $all_mail_souzoku != null) {
		$souzoku_all_tmp = "相続問題サイトで".$all_call_souzoku."件の電話と".$all_mail_souzoku."件のメール".$souzoku_mail_dt;
	}
	else if ($all_call_souzoku != null && $all_mail_souzoku == null) {
		$souzoku_all_tmp = "相続問題サイトで".$all_call_souzoku."件の電話";
	}
	else if ($all_call_souzoku == null && $all_mail_souzoku != null) {
		$souzoku_all_tmp = "相続問題サイトで".$all_mail_souzoku."件のメール".$souzoku_mail_dt;
	}
	else{
		$souzoku_all_tmp = "";
	}
	#交通事故all_tmp
	if ($all_call_koutsujiko != null && $all_mail_koutsujiko != null) {
		$koutsujiko_all_tmp = "交通事故サイトで".$all_call_koutsujiko."件の電話と".$all_mail_koutsujiko."件のメール".$koutsujiko_mail_dt;
	}
	else if($all_call_koutsujiko != null && $all_mail_koutsujiko == null) {
		$koutsujiko_all_tmp = "交通事故サイトで".$all_call_koutsujiko."件の電話";
	}
	else if($all_call_koutsujiko == null && $all_mail_koutsujiko != null) {
		$koutsujiko_all_tmp = "交通事故サイトで".$all_mail_koutsujiko."件のメール".$koutsujiko_mail_dt;
	}
	else{
		$koutsujiko_all_tmp = "";
	}
	#任意売却all_tmp
	if ($all_call_ninibaikyaku != null && $all_mail_ninibaikyaku != null) {
		$ninibaikyaku_all_tmp = "任意売却サイトで".$all_call_ninibaikyaku."件の電話と".$all_mail_ninibaikyaku."件のメール".$ninibaikyaku_mail_dt;
	}
	else if($all_call_ninibaikyaku != null && $all_mail_ninibaikyaku == null) {
		$ninibaikyaku_all_tmp = "任意売却サイトで".$all_call_ninibaikyaku."件の電話";
	}
	else if($all_call_ninibaikyaku == null && $all_mail_ninibaikyaku != null) {
		$ninibaikyaku_all_tmp = "任意売却サイトで".$all_mail_ninibaikyaku."件のメール".$ninibaikyaku_mail_dt;
	}
	else{
		$ninibaikyaku_all_tmp = "";
	}
	#名義変更all_tmp
	if ($all_call_meigihenkou != null && $all_mail_meigihenkou != null) {
		$meigihenkou_all_tmp = "名義変更サイトで".$all_call_meigihenkou."件の電話と".$all_mail_meigihenkou."件のメール".$meigihenkou_mail_dt;
	}
	else if ($all_call_meigihenkou != null && $all_mail_meigihenkou == null) {
		$meigihenkou_all_tmp = "名義変更サイトで".$all_call_meigihenkou."件の電話";
	}
	else if ($all_call_meigihenkou == null && $all_mail_meigihenkou != null) {
		$meigihenkou_all_tmp = "名義変更サイトで".$all_mail_meigihenkou."件のメール".$meigihenkou_mail_dt;
	}
	else{
		$meigihenkou_all_tmp = "";
	}
	#会社設立all_tmp
	if ($all_call_setsuritsu != null && $all_mail_setsuritsu != null) {
		$setsuritsu_all_tmp = "会社設立サイトで".$all_call_setsuritsu."件の電話と".$all_mail_setsuritsu."件のメール".$setsuritsu_mail_dt;
	}
	else if($all_call_setsuritsu != null && $all_mail_setsuritsu == null) {
		$setsuritsu_all_tmp = "会社設立サイトで".$all_call_setsuritsu."件の電話";
	}
	else if($all_call_setsuritsu == null && $all_mail_setsuritsu != null){
		$setsuritsu_all_tmp = "会社設立サイトで".$all_mail_setsuritsu."件のメール".$setsuritsu_mail_dt;
	}
	else{
		$setsuritsu_all_tmp = "";
	}
	#刑事事件all_tmp
	if ($all_call_keijijiken != null){
		$keijijiken_all_tmp = "刑事事件サイトで".$all_call_keijijiken."件の電話";
	}
	else{
		$keijijiken_all_tmp = "";
	}
	#離婚all_tmp
	if ($all_call_rikon != null && $all_mail_rikon != null) {
		$rikon_all_tmp = "離婚問題サイトで".$all_call_rikon."件の電話と".$all_mail_rikon."件のメール".$rikon_mail_dt;
	}
	else if($all_call_rikon != null && $all_mail_rikon == null) {
		$rikon_all_tmp = "離婚問題サイトで".$all_call_rikon."件の電話";
	}
	else if($all_call_rikon == null && $all_mail_rikon != null){
		$rikon_all_tmp = "離婚問題サイトで".$all_mail_rikon."件のメール".$rikon_mail_dt;
	}
	else{
		$rikon_all_tmp = "";
	}
	#無効件数の計算
	$res_shakkin = $shakkin_call + $shakkin_mail;
	$res_souzoku = $souzoku_call + $souzoku_mail;
	$res_koutsujiko = $koutsujiko_call + $koutsujiko_mail;
	$res_ninibaikyaku = $ninibaikyaku_call + $ninibaikyaku_mail;
	$res_meigihenkou = $meigihenkou_call + $meigihenkou_mail;
	$res_setsuritsu = $setsuritsu_call + $setsuritsu_mail;
	$res_keijijiken = $keijijiken_call ;
	$res_rikon = $rikon_call + $rikon_mail;
	$inv_shakkin = $all_call_shakkin + $all_mail_shakkin - $res_shakkin;
	if ($inv_shakkin > 0) {
		$inv_tmp_shakkin = "借金問題サイトで同一電話番号の電話・メール及び60秒以内電話の".$inv_shakkin."件";
	}
	$inv_souzoku = $all_call_souzoku + $all_mail_souzoku - $res_souzoku;
	if ($inv_souzoku > 0) {
		$inv_tmp_souzoku = "相続問題サイトで同一電話番号の電話・メール及び60秒以内電話の".$inv_souzoku."件";
	}
	$inv_koutsujiko = $all_call_koutsujiko + $all_mail_koutsujiko - $res_koutsujiko;
	if ($inv_koutsujiko > 0) {
		$inv_tmp_koutsujiko = "交通事故サイトで同一電話番号の電話・メール及び60秒以内電話の".$inv_koutsujiko."件";
	}
	$inv_ninibaikyaku = $all_call_ninibaikyaku + $all_mail_ninibaikyaku - $res_ninibaikyaku;
	if ($inv_ninibaikyaku > 0) {
		$inv_tmp_ninibaikyaku = "任意売却サイトで同一電話番号の電話・メール及び60秒以内電話の".$inv_ninibaikyaku."件";
	}
	$inv_meigihenkou = $all_call_meigihenkou + $all_mail_meigihenkou - $res_meigihenkou;
	if ($inv_meigihenkou > 0) {
		$inv_tmp_meigihenkou = "名義変更サイトで同一電話番号の電話・メール及び60秒以内電話の".$inv_meigihenkou."件";
	}
	$inv_setsuritsu = $all_call_setsuritsu + $all_mail_setsuritsu - $res_setsuritsu;
	if ($inv_setsuritsu > 0) {
		$inv_tmp_setsuritsu = "会社設立サイトで同一電話番号の電話・メール及び60秒以内電話の".$inv_setsuritsu."件";
	}
	$inv_keijijiken = $all_call_keijijiken - $res_keijijiken;
	if ($inv_keijijiken > 0){
		$inv_tmp_keijijiken = "刑事事件サイトで同一電話番号の電話・メール及び60秒以内電話の".$inv_keijijiken."件";
	}
	$inv_rikon = $all_call_rikon + $all_mail_rikon - $res_rikon;
	if ($inv_rikon > 0) {
		$inv_tmp_rikon = "離婚問題サイトで同一電話番号の電話・メール及び60秒以内電話の".$inv_setsuritsu."件";
	}

	#有効件数生成
	if(!empty($res_shakkin)) {
		$va_shakkin = "借金問題".$res_shakkin."件・";
	}
	if(!empty($res_souzoku)) {
		$va_souzoku = "相続問題".$res_souzoku."件・";
	}
	if(!empty($res_koutsujiko)) {
		$va_koutsujiko = "交通事故".$res_koutsujiko."件・";
	}
	if(!empty($res_ninibaikyaku)) {
		$va_ninibaikyaku = "任意売却".$res_ninibaikyaku."件・";
	}
	if(!empty($res_meigihenkou)) {
		$va_meigihenkou = "名義変更".$res_meigihenkou."件・";
	}
	if(!empty($res_setsuritsu)) {
		$va_setsuritsu = "会社設立".$res_setsuritsu."件・";
	}
	if(!empty($res_keijijiken)) {
		$va_keijijiken = "刑事事件".$res_keijijiken."件・";
	}
	if(!empty($res_rikon)) {
		$va_rikon = "離婚問題".$res_rikon."件・";
	}
	//////////////////
	/////template文生成
	#all_tmp
	if (!empty($shakkin_all_tmp)) {
		$all_tmp = $all_tmp."
".$shakkin_all_tmp;
	}
	if (!empty($souzoku_all_tmp)) {
		$all_tmp = $all_tmp."
".$souzoku_all_tmp;
	}
	if (!empty($koutsujiko_all_tmp)) {
		$all_tmp = $all_tmp."
".$koutsujiko_all_tmp;
	}
	if (!empty($ninibaikyaku_all_tmp)) {
		$all_tmp = $all_tmp."
".$ninibaikyaku_all_tmp;
	}
	if (!empty($meigihenkou_all_tmp)) {
		$all_tmp = $all_tmp."
".$meigihenkou_all_tmp;
	}
	if (!empty($setsuritsu_all_tmp)) {
		$all_tmp = $all_tmp."
".$setsuritsu_all_tmp;
	}
	if (!empty($keijijiken_all_tmp)) {
		$all_tmp = $all_tmp."
".$keijijiken_all_tmp;
	}
	if (!empty($rikon_all_tmp)) {
		$all_tmp = $all_tmp."
".$rikon_all_tmp;
	}
	#inv_tmp
	if (!empty($inv_tmp_shakkin)) {
		$inv_tmp = $inv_tmp."
".$inv_tmp_shakkin;
	}
	if (!empty($inv_tmp_souzoku)) {
		$inv_tmp = $inv_tmp."
".$inv_tmp_souzoku;
	}
	if (!empty($inv_tmp_koutsujiko)) {
		$inv_tmp = $inv_tmp."
".$inv_tmp_koutsujiko;
	}
	if (!empty($inv_tmp_ninibaikyaku)) {
		$inv_tmp = $inv_tmp."
".$inv_tmp_ninibaikyaku;
	}
	if (!empty($inv_tmp_meigihenkou)) {
		$inv_tmp = $inv_tmp."
".$inv_tmp_meigihenkou;
	}
	if (!empty($inv_tmp_setsuritsu)) {
		$inv_tmp = $inv_tmp."
".$inv_tmp_setsuritsu;
	}
	if (!empty($inv_tmp_keijijiken)) {
		$inv_tmp = $inv_tmp."
".$inv_tmp_keijijiken;
	}
	if (!empty($inv_tmp_rikon)) {
		$inv_tmp = $inv_tmp."
".$inv_tmp_rikon;
	}
	if (!empty($inv_tmp)) {
		$inv_tmp = $inv_tmp."
"."を差し引いて";
	}

	#valid_tmp
	$va_tmp = $va_shakkin.$va_souzoku.$va_koutsujiko.$va_ninibaikyaku.$va_meigihenkou.$va_setsuritsu.$va_keijijiken.$va_rikon;
	$va_tmp = rtrim($va_tmp,'・');

	###################################
	##ここからがExcelへの記入に関するコード##
	###################################
	#monthを表示用数字に変更
	$month = sprintf('%01d', $month);
	#郵便番号
	$reviser->addString($sheet_num, 4, 2, "〒".$bill_payer_data['postal_code']);
	#住所
	$reviser->addString($sheet_num, 5, 2, $bill_payer_data['address_1']);
	$reviser->addString($sheet_num, 6, 2, " ".$bill_payer_data['address_2']);
	#貴社名/御氏名
	$reviser->addString($sheet_num, 7, 2, $bill_payer_data['bill_payer_name']);
	$reviser->addString($sheet_num, 8, 2, $c_name."　様");
	#行数の定義
	$i = 18;
	$reviser->addNumber($sheet_num, $i, 0, "1");
	#借金問題
	if ($shakkin_call > 0 || $shakkin_mail > 0) {
		#月
		$reviser->addNumber($sheet_num, $i, 1, "$month");	
		#商品名
		$reviser->addString($sheet_num, $i, 2, "月成果料金(借金問題)");
		#数量
		$reviser->addNumber($sheet_num, $i, 4, $shakkin_call + $shakkin_mail);
		#単価
		$reviser->addNumber($sheet_num, $i, 5, 10000);
		#合計金額
		$sum = ($shakkin_call + $shakkin_mail) * 10000;
		$i = $i + 1;
	}
	#相続
	if ($souzoku_call > 0 || $souzoku_mail > 0) {
		#月
		$reviser->addNumber($sheet_num, $i, 1, "$month");	
		#商品名
		$reviser->addString($sheet_num, $i, 2, "月成果料金(相続)");
		#数量
		$reviser->addNumber($sheet_num, $i, 4, $souzoku_call + $souzoku_mail);
		#単価
		$reviser->addNumber($sheet_num, $i, 5, 5000);
		#合計金額
		$sum = ($souzoku_call + $souzoku_mail) * 5000;
		$i = $i + 1;
	}
	#交通事故
	if ($koutsujiko_call > 0 || $koutsujiko_mail > 0) {
			#月
			$reviser->addNumber($sheet_num, $i, 1, "$month");	
			#商品名
			$reviser->addString($sheet_num, $i, 2, "月成果料金(交通事故)");
			#数量
			$reviser->addNumber($sheet_num, $i, 4, $koutsujiko_call + $koutsujiko_mail);
			#単価
			$reviser->addNumber($sheet_num, $i, 5, 10000);
			#合計金額
			$sum = ($koutsujiko_call + $koutsujiko_mail) * 10000;
			$i = $i + 1;
	}
	#名義変更
	if ($meigihenkou_call > 0 || $meigihenkou_mail > 0) {
		#月
		$reviser->addNumber($sheet_num, $i, 1, "$month");	
		#商品名
		$reviser->addString($sheet_num, $i, 2, "月成果料金(名義変更)");
		#数量
		$reviser->addNumber($sheet_num, $i, 4, $meigihenkou_call + $meigihenkou_mail);
		#単価
		$reviser->addNumber($sheet_num, $i, 5, 5000);
		#合計金額
		$sum = ($meigihenkou_call + $meigihenkou_mail) * 5000;
		$i = $i + 1;
	}
	#会社設立
	if ($setsuritsu_call > 0 OR $setsuritsu_mail > 0) {
		#月
		$reviser->addNumber($sheet_num, $i, 1, "$month");	
		#商品名
		$reviser->addString($sheet_num, $i, 2, "月成果料金(会社設立)");
		#数量
		$reviser->addNumber($sheet_num, $i, 4, $setsuritsu_call+$setsuritsu_mail);
		#単価
		$reviser->addNumber($sheet_num, $i, 5, 5000);
		#合計金額
		$sum =($setsuritsu_call + $setsuritsu_mail) * 5000;
		$i = $i + 1;
	}
	#刑事事件
	if ($keijijiken_call > 0) {
		#月
		$reviser->addNumber($sheet_num, $i, 1, "$month");	
		#商品名
		$reviser->addString($sheet_num, $i, 2, "月成果料金(刑事事件)");
		#数量
		$reviser->addNumber($sheet_num, $i, 4, $keijijiken_call);
		#単価
		$reviser->addNumber($sheet_num, $i, 5, 10000);
		#合計金額
		$sum = ($keijijiken_call) * 10000;
		$i = $i + 1;
	}
	#会社設立
	if ($rikon_call > 0 OR $rikon_mail > 0) {
		#月
		$reviser->addNumber($sheet_num, $i, 1, "$month");	
		#商品名
		$reviser->addString($sheet_num, $i, 2, "月成果料金(離婚)");
		#数量
		$reviser->addNumber($sheet_num, $i, 4, $rikon_call+$rikon_mail);
		#単価
		$reviser->addNumber($sheet_num, $i, 5, 7000);
		#合計金額
		$sum =($rikon_call + $rikon_mail) * 7000;
		$i = $i + 1;
	}
	$i = $i + 1;
	$reviser->addNumber($sheet_num, $i, 0, "2");
	#月
	$reviser->addNumber($sheet_num, $i, 1, "$month");	
	#フリーダイヤル料金記入
	$reviser->addString($sheet_num, $i, 2, "月フリーダイヤル通話料金");
	#単価
	$reviser->addNumber($sheet_num, $i, 5, $req_mvc_data['call_charge']);
	#合計金額
	$reviser->addNumber($sheet_num, $i, 6, $req_mvc_data['call_charge']);
	if ($req_mvc_data['count_freedial'] != null) {
		$i = $i + 1;
		#月
		$reviser->addNumber($sheet_num, $i, 1, "$month");
		#発番費用
		$reviser->addString($sheet_num, $i, 2, "月フリーダイヤル費用");
		#数量
		$reviser->addNumber($sheet_num, $i, 4, $req_mvc_data['count_freedial']);
		#単価
		$reviser->addNumber($sheet_num, $i, 5, 1000);//変更可能である可能性あり
		#合計金額
		$sum = ($req_mvc_data['count_freedial']) * 1000;
		$i = $i + 1;
	}

	//////////////////////////
	//////メールtemplate本文////
	//////////////////////////
	##ここの内容はインデント禁止！！！
	#メール担当者がいる場合はメール文面の担当者を書き換える
	if(!empty($recipient_name)){
		$c_name = $recipient_name;
	}
	$sheet_num =1;
	$reviser->addString($sheet_num, 0, 0, "
【専門家検索ドットコム】請求書（".$year."年".$month."月分）

".$c_name."様

いつもお世話になっております。
ウィズパッションの土田です。

".$month."月分の請求書を添付させていただきます。

".$month."月は、".$all_tmp."
が発生致しました。".$inv_tmp."
計".$all_sum."件(".$va_tmp.")を請求させて頂きます。

ご不明な点があればなんなりとご連絡ください。
今後ともよろしくお願い致します。"
	);

	########################
	###CRMシートに載せる内容###
	########################
	$sheet_num = 2;
	#無効も含めた全コール数
	$all_call = $all_call_shakkin + $all_call_souzoku + $all_call_koutsujiko + $all_call_ninibaikyaku + $all_call_meigihenkou + $all_call_setsuritsu + $all_call_keijijiken + $all_call_rikon;
	#無効も含めた全メール数
	$all_mail = $all_mail_shakkin + $all_mail_souzoku + $all_mail_koutsujiko + $all_mail_ninibaikyaku + $all_mail_meigihenkou + $all_mail_setsuritsu + $all_mail_keijijiken + $all_mail_rikon;
	$reviser->addString($sheet_num, 0, 0, $month."月");
	#全体コール数の出力
	$reviser->addString($sheet_num, 1, 1, "発生コール");
	#参考コール数の出力
	$reviser->addString($sheet_num, 1, 2, "参考コール");
	#有効コール数の出力
	$reviser->addString($sheet_num, 1, 3, "有効コール");
	#発番電話番号の出力
	$reviser->addString($sheet_num, 1, 4, "発番電話番号");
	#請求通話料金の出力
	$reviser->addString($sheet_num, 1, 5, "請求通話料金");
	#行数を変数に指定
	$i = 2;
	//crmシートコールデータ処理
	#発番毎の請求料金の取得

	$bp_call = array();
	$adg_call = array();
	$sgp_call = array();
	foreach ($arr_ad_group_id as $row) {
		$ad_group_id = $row['ad_group_id'];
		$stmt = $pdo_cdr->query("
			SELECT
				vv.ad_group_id,
				vv.advertiser_id,
				vv.tel_to,
				group_concat(vv.media_id) media_id,
				group_concat(vv.media_type) media_type,
				group_concat(vv.site_group) site_group,
				sum(vv.call_charge) call_charge
			FROM
			(
				SELECT
					v.ad_group_id,
					v.advertiser_id,
					v.tel_to,
					v.media_id,
					v.media_type,
					v.site_group,
					b.call_charge
				FROM
					cdr.call_data_view v
				LEFT OUTER JOIN	(
					SELECT
						*
					FROM
						cdr.bill
					WHERE
						year = $year AND
						month = $month
				) as b
				ON b.tel_to = v.tel_to
				WHERE
					DATE_FORMAT(v.date_from,'%Y%m') = $year_month AND
					v.ad_group_id = $ad_group_id
				GROUP BY
					v.advertiser_id,
					v.tel_to
			) vv
			GROUP BY
				vv.advertiser_id,
				vv.tel_to
			WITH ROLLUP
		");
		$arr_each_tel_to = $stmt->fetchAll(PDO::FETCH_ASSOC);
		foreach ($arr_each_tel_to as $row) {
			$ad_id = $row['advertiser_id'];
			$tel_to = $row['tel_to'];
			$media_id = $row['media_id'];
			$media_type = $row['media_type'];
			$site_group = $row['site_group'];
			$call_charge = $row['call_charge'];
			if ($ad_id == null) {
				if (!isset($bp_call['total']['call_charge'])) {
					$bp_call['total']['call_charge'] = 0;
				}
				$bp_call['total']['call_charge'] += $call_charge;
				$adg_call[$ad_group_id]['total']['call_charge'] = $call_charge;
			}
			else if ($tel_to == null) {
				$adg_call[$ad_group_id][$ad_id]['total']['call_charge'] = $call_charge;
			}
			else {
				$adg_call[$ad_group_id][$ad_id][$site_group]['tel_to'] = $tel_to;
				$adg_call[$ad_group_id][$ad_id][$site_group]['call_charge'] = $call_charge;

				if (!isset($sgp_call[$ad_group_id][$site_group]['call_charge'])) {
					$sgp_call[$ad_group_id][$site_group]['call_charge'] = 0;
				}
				$sgp_call[$ad_group_id][$site_group]['call_charge'] += $call_charge;

				if (!isset($bp_call[$site_group]['call_charge'])) {
					$bp_call[$site_group]['call_charge'] = 0;
				}
				$bp_call[$site_group]['call_charge'] += $call_charge;
			}
		}

		$all_call_data = get_monthly_total_calls($year_month, CALL_TYPE_ALL, $ad_group_id, $media_type);
		$sample_call_data = get_monthly_total_calls($year_month, CALL_TYPE_SAMPLE, $ad_group_id, $media_type);
		$valid_call_data = get_monthly_total_calls($year_month, CALL_TYPE_VALID, $ad_group_id, $media_type);
		append_call_counts($ad_group_id, $all_call_data, $bp_call, $adg_call, $sgp_call, CALL_TYPE_ALL);
		append_call_counts($ad_group_id, $sample_call_data, $bp_call, $adg_call, $sgp_call, CALL_TYPE_SAMPLE);
		append_call_counts($ad_group_id, $valid_call_data, $bp_call, $adg_call, $sgp_call, CALL_TYPE_VALID);
	}

	//bpは案件種別ソート
	krsort($bp_call);
	ksort($bp_call);

	//sgpはグループID, 案件種別ソート
	krsort($sgp_call);
	ksort($sgp_call);
	foreach ($sgp_call as $key => &$val) {
		ksort($val);
	}

	//adgは事務所ID, 案件種別ソート
	foreach ($adg_call as $key => &$val) {
		ksort($val);
		foreach ($val as $key2 => &$val2) {
			ksort($val2);
		}
	}

	//請求先全事務所合計
	if (count($bp_call) > 0) {
		foreach (array_keys($bp_call) as $sg_key) {
			if ($sg_key === 'total') {
				$reviser->addString($sheet_num, $i, 0, "全事務所合計");
			}
			else {
				$reviser->addString($sheet_num, $i, 0, "案件種別：$sg_key 合計");
			}
			$reviser->addString($sheet_num, $i, 1, $bp_call[$sg_key][CALL_TYPE_ALL]);
			$reviser->addString($sheet_num, $i, 2, $bp_call[$sg_key][CALL_TYPE_SAMPLE]);
			$reviser->addString($sheet_num, $i, 3, $bp_call[$sg_key][CALL_TYPE_VALID]);
			$reviser->addString($sheet_num, $i, 5, $bp_call[$sg_key]['call_charge']);
			$i++;
		}
	}
	//グループ別合計
	if (count($adg_call)) {
		foreach ($arr_ad_group_id as $row) {
			$ad_group_id = $row['ad_group_id'];
			foreach (array_keys($adg_call[$ad_group_id]) as $ad_key) {
				if ($ad_key === 'total') {
					$reviser->addString($sheet_num, $i, 0, "グループID：$ad_group_id 合計");
					$reviser->addString($sheet_num, $i, 1, $adg_call[$ad_group_id][$ad_key][CALL_TYPE_ALL]);
					$reviser->addString($sheet_num, $i, 2, $adg_call[$ad_group_id][$ad_key][CALL_TYPE_SAMPLE]);
					$reviser->addString($sheet_num, $i, 3, $adg_call[$ad_group_id][$ad_key][CALL_TYPE_VALID]);
					$reviser->addString($sheet_num, $i, 5, $adg_call[$ad_group_id][$ad_key]['call_charge']);
					$i++;
				}
			}
		}
	}
	//グループ案件別合計
	if (count($sgp_call)) {
		foreach ($arr_ad_group_id as $row) {
			$ad_group_id = $row['ad_group_id'];
			foreach (array_keys($sgp_call[$ad_group_id]) as $sg_key) {
				$reviser->addString($sheet_num, $i, 0, "グループID：$ad_group_id 案件種別：$sg_key 合計");
				$reviser->addString($sheet_num, $i, 1, $sgp_call[$ad_group_id][$sg_key][CALL_TYPE_ALL]);
				$reviser->addString($sheet_num, $i, 2, $sgp_call[$ad_group_id][$sg_key][CALL_TYPE_SAMPLE]);
				$reviser->addString($sheet_num, $i, 3, $sgp_call[$ad_group_id][$sg_key][CALL_TYPE_VALID]);
				$reviser->addString($sheet_num, $i, 5, $sgp_call[$ad_group_id][$sg_key]['call_charge']);
				$i++;
			}
		}
	}
	//事務所別合計
	if (count($adg_call)) {
		foreach ($arr_ad_group_id as $row) {
			$ad_group_id = $row['ad_group_id'];
			foreach (array_keys($adg_call[$ad_group_id]) as $ad_key) {
				if ($ad_key !== 'total') {
					foreach (array_keys($adg_call[$ad_group_id][$ad_key]) as $sg_key) {
						if ($sg_key === 'total') {
							$reviser->addString($sheet_num, $i, 0, "事務所ID：$ad_key 合計");
						}
						else {
							$reviser->addString($sheet_num, $i, 0, "事務所ID：$ad_key 案件種別：$sg_key 合計");
							if (isset($adg_call[$ad_group_id][$ad_key][$sg_key]['tel_to'])) {
								$reviser->addString($sheet_num, $i, 4, $adg_call[$ad_group_id][$ad_key][$sg_key]['tel_to']);
							}
						}
						$reviser->addString($sheet_num, $i, 1, $adg_call[$ad_group_id][$ad_key][$sg_key][CALL_TYPE_ALL]);
						$reviser->addString($sheet_num, $i, 2, $adg_call[$ad_group_id][$ad_key][$sg_key][CALL_TYPE_SAMPLE]);
						$reviser->addString($sheet_num, $i, 3, $adg_call[$ad_group_id][$ad_key][$sg_key][CALL_TYPE_VALID]);
						$reviser->addString($sheet_num, $i, 5, $adg_call[$ad_group_id][$ad_key][$sg_key]['call_charge']);
						$i++;
					}
				}
			}
		}
	}
	//var_dump($bp_call);
	//var_dump($adg_call);
	//var_dump($sgp_call);

	$i++;//一行空ける

	#全体メール数の出力
	$reviser->addString($sheet_num, $i, 1, "発生メール");
	#参考メール数の出力
	$reviser->addString($sheet_num, $i, 2, "参考メール");
	#参考メール数の出力
	$reviser->addString($sheet_num, $i, 3, "有効メール");

	$i++;

	//メールの合計情報の収集
	$bp_mail = array();
	$adg_mail = array();
	$sgp_mail = array();
	foreach ($arr_ad_group_id as $row) {
		$ad_group_id = $row['ad_group_id'];
		$all_mail_data = get_monthly_total_mails($year_month, MAIL_TYPE_ALL, $ad_group_id);
		$sample_mail_data = get_monthly_total_mails($year_month, MAIL_TYPE_SAMPLE, $ad_group_id);
		$valid_mail_data = get_monthly_total_mails($year_month, MAIL_TYPE_VALID, $ad_group_id);
		append_mail_counts($ad_group_id, $all_mail_data, $bp_mail, $adg_mail, $sgp_mail, MAIL_TYPE_ALL);
		append_mail_counts($ad_group_id, $sample_mail_data, $bp_mail, $adg_mail, $sgp_mail, MAIL_TYPE_SAMPLE);
		append_mail_counts($ad_group_id, $valid_mail_data, $bp_mail, $adg_mail, $sgp_mail, MAIL_TYPE_VALID);
	}

	//bpは案件種別ソート
	krsort($bp_mail);
	ksort($bp_mail);

	//sgpはグループID, 案件種別ソート
	krsort($sgp_mail);
	ksort($sgp_mail);
	foreach ($sgp_mail as $key => &$val) {
		ksort($val);
	}

	//adgは事務所ID, 案件種別ソート
	foreach ($adg_mail as $key => &$val) {
		ksort($val);
		foreach ($val as $key2 => &$val2) {
			ksort($val2);
		}
	}

	//請求先全事務所合計
	if (count($bp_mail) > 0) {
		foreach (array_keys($bp_mail) as $sg_key) {
			if ($sg_key === 'total') {
				$reviser->addString($sheet_num, $i, 0, "全事務所合計");
			}
			else {
				$reviser->addString($sheet_num, $i, 0, "案件種別：$sg_key 合計");
			}
			$reviser->addString($sheet_num, $i, 1, $bp_mail[$sg_key][MAIL_TYPE_ALL]);
			$reviser->addString($sheet_num, $i, 2, $bp_mail[$sg_key][MAIL_TYPE_SAMPLE]);
			$reviser->addString($sheet_num, $i, 3, $bp_mail[$sg_key][MAIL_TYPE_VALID]);
			$i++;
		}
	}
	//グループ別合計
	if (count($adg_mail)) {
		foreach ($arr_ad_group_id as $row) {
			$ad_group_id = $row['ad_group_id'];
			foreach (array_keys($adg_mail[$ad_group_id]) as $ad_key) {
				if ($ad_key === 'total') {
					$reviser->addString($sheet_num, $i, 0, "グループID：$ad_group_id 合計");
					$reviser->addString($sheet_num, $i, 1, $adg_mail[$ad_group_id][$ad_key][MAIL_TYPE_ALL]);
					$reviser->addString($sheet_num, $i, 2, $adg_mail[$ad_group_id][$ad_key][MAIL_TYPE_SAMPLE]);
					$reviser->addString($sheet_num, $i, 3, $adg_mail[$ad_group_id][$ad_key][MAIL_TYPE_VALID]);
					$i++;
				}
			}
		}
	}
	//グループ案件別合計
	if (count($sgp_mail)) {
		foreach ($arr_ad_group_id as $row) {
			$ad_group_id = $row['ad_group_id'];
			foreach (array_keys($sgp_mail[$ad_group_id]) as $sg_key) {
				$reviser->addString($sheet_num, $i, 0, "グループID：$ad_group_id 案件種別：$sg_key 合計");
				$reviser->addString($sheet_num, $i, 1, $sgp_mail[$ad_group_id][$sg_key][MAIL_TYPE_ALL]);
				$reviser->addString($sheet_num, $i, 2, $sgp_mail[$ad_group_id][$sg_key][MAIL_TYPE_SAMPLE]);
				$reviser->addString($sheet_num, $i, 3, $sgp_mail[$ad_group_id][$sg_key][MAIL_TYPE_VALID]);
				$i++;
			}
		}
	}
	//事務所別合計
	if (count($adg_mail)) {
		foreach ($arr_ad_group_id as $row) {
			$ad_group_id = $row['ad_group_id'];
			foreach (array_keys($adg_mail[$ad_group_id]) as $ad_key) {
				if ($ad_key !== 'total') {
					foreach (array_keys($adg_mail[$ad_group_id][$ad_key]) as $sg_key) {
						if ($sg_key === 'total') {
							$reviser->addString($sheet_num, $i, 0, "事務所ID：$ad_key 合計");
						}
						else {
							$reviser->addString($sheet_num, $i, 0, "事務所ID：$ad_key 案件種別：$sg_key 合計");
						}
						$reviser->addString($sheet_num, $i, 1, $adg_mail[$ad_group_id][$ad_key][$sg_key][MAIL_TYPE_ALL]);
						$reviser->addString($sheet_num, $i, 2, $adg_mail[$ad_group_id][$ad_key][$sg_key][MAIL_TYPE_SAMPLE]);
						$reviser->addString($sheet_num, $i, 3, $adg_mail[$ad_group_id][$ad_key][$sg_key][MAIL_TYPE_VALID]);
						$i++;
					}
				}
			}
		}
	}
	//var_dump($bp_mail);
	//var_dump($adg_mail);
	//var_dump($sgp_mail);

	$i++;//一行開ける
	$reviser->addString($sheet_num, $i, 0, "通話データ");
	$i++;//一行開ける

	//有効秒数等のヘッダの表示
	$reviser->addString($sheet_num, $i, 0, "事務所ID");
	$reviser->addString($sheet_num, $i, 1, "事務所名");
	$reviser->addString($sheet_num, $i, 3, "サイト種別");
	$reviser->addString($sheet_num, $i, 4, "電話番号");
	$reviser->addString($sheet_num, $i, 5, "転送先番号");
	$reviser->addString($sheet_num, $i, 6, "通話開始日時");
	$reviser->addString($sheet_num, $i, 7, "通話終了日時");
	$reviser->addString($sheet_num, $i, 8, "通話秒数");
	$reviser->addString($sheet_num, $i, 9, "発信元番号");
	$reviser->addString($sheet_num, $i, 10, "通話状態");
	$reviser->addString($sheet_num, $i, 11, "有効無効(60秒)");
	$reviser->addString($sheet_num, $i, 12, "有効無効");
	$reviser->addString($sheet_num, $i, 13, "有効秒数");

	$i++;

	#media_typesへの接続
	$stmt = $pdo_wordpress->query("
		SELECT
			*
		FROM
			ss_site_group
	");
	$arr_site_group_data = $stmt->fetchAll(PDO::FETCH_ASSOC);

	//エクセル出力用
	$output_arr = array();

	foreach ($arr_ad_group_id as $row) {
		$ad_group_id = $row['ad_group_id'];
		####通話情報の生成
		$stmt = $pdo_cdr->query("
			SELECT
				ad.ID as advertiser_id,
				ad.office_name,
				dv.*,
				pm.payment_method_id,
				pm.charge_seconds
			FROM
				wordpress.ss_advertisers ad,
				cdr.call_data_view dv,
				cdr.office_group_payment_method pm
			WHERE
				dv.advertiser_id = ad.ID AND
				dv.ad_group_id = pm.ad_group_id AND
				dv.site_group = pm.site_group AND
				CAST(dv.date_from AS DATE) BETWEEN pm.from_date AND pm.to_date AND
				DATE_FORMAT(dv.date_from,'%Y%m') = $year_month AND
				dv.ad_group_id = $ad_group_id
			ORDER BY
				dv.date_from
		");
		$arr_call_data = $stmt->fetchAll(PDO::FETCH_ASSOC);
		foreach ($arr_call_data as $row) {
			#配列の値から変数へと代入
			$advertiser_id = $row['advertiser_id'];
			$office_name = $row['office_name'];
			$media_id = $row['media_id'];
			$media_type = $row['media_type'];
			$tel_to = $row['tel_to'];
			$tel_send = $row['tel_send'];
			$date_from = $row['date_from'];
			$date_to = $row['date_to'];
			$call_minutes = $row['call_minutes'];
			$tel_from = $row['tel_from'];
			$dpl_tel_cnt = $row['dpl_tel_cnt'];
			$dpl_mail_cnt = $row['dpl_mail_cnt'];
			$redirect_status = $row['redirect_status'];
			$payment_method_id = $row['payment_method_id'];
			$charge_seconds = $row['charge_seconds'];
			$dpl_tel_cnt_for_billing = $row['dpl_tel_cnt_for_billing'];
			#media_nameの取得
			$media_name = "";
			$site_group = 0;
			foreach ($arr_site_group_data as $r) {
				if ($r['media_type'] == $media_type) {
					$media_name = $r['site_group_name'];
					$site_group = $r['site_group'];
					break;
				}
				else {
					$media_name = "借金問題";
					$site_group = "0";
				}
			}

			#電話statusの取得
			switch ($redirect_status) {
				case '21':
				case '22':
					$call_status = "正常終了";
					break;
				case '41':
				case '42':
					$call_status = "転送先応答なし";
					break;
				case '11':
				case '12':
					$call_status = "転送先呼び出し中切断";
					break;
				case '51':
				case '52':
					$call_status = "転送設定なし";
					break;
				case '61':
				case '62':
					$call_status = "番号応答なし";
					break;
				case '31':
				case '32':
					$call_status = "転送先話中";
					break;
			}

			#電話重複の確認
			$check_call_dpl = null;
			if ($call_minutes >= 60 && $dpl_tel_cnt > 0 && $dpl_mail_cnt > 0) {
				$check_call_dpl = "同一電話・メール";
			}
			else if ($call_minutes >= 60 && $dpl_tel_cnt > 0) {
				$check_call_dpl = "同一電話";
			}
			else if ($call_minutes >= 60 && $dpl_mail_cnt > 0) {
				$check_call_dpl = "同一メール";
			}
			else if ($call_minutes >= 60) {
				if ($tel_from == "anonymous" OR ($dpl_tel_cnt == 0 && $dpl_mail_cnt == 0)) {
					if ($redirect_status == 21 || $redirect_status == 22) {
						$check_call_dpl = "○";
					}
				}
			}

			#課金対象の確認
			$check_call_dpl_for_billing = null;
			if ($payment_method_id < 2) {
				if ($call_minutes >= $charge_seconds && $dpl_tel_cnt_for_billing > 0 && $dpl_mail_cnt > 0) {
					$check_call_dpl_for_billing = "同一電話・メール";
				}
				else if ($call_minutes >= $charge_seconds && $dpl_tel_cnt_for_billing > 0) {
					$check_call_dpl_for_billing = "同一電話";
				}
				else if ($call_minutes >= $charge_seconds && $dpl_mail_cnt > 0) {
					$check_call_dpl_for_billing = "同一メール";
				}
				else if ($call_minutes >= $charge_seconds) {
					if ($tel_from == "anonymous" OR ($dpl_tel_cnt_for_billing == 0 && $dpl_mail_cnt == 0)) {
						if ($redirect_status == 21 || $redirect_status == 22) {
							$check_call_dpl_for_billing = "○";
						}
					}
				}
			}

			$arr = array();
			$arr['advertiser_id'] = $advertiser_id;
			$arr['office_name'] = $office_name;
			$arr['media_name'] = $media_name;
			$arr['site_group'] = $site_group;
			$arr['tel_to'] = $tel_to;
			$arr['tel_send'] = $tel_send;
			$arr['date_from'] = $date_from;
			$arr['date_to'] = $date_to;
			$arr['call_minutes'] = $call_minutes;
			$arr['tel_from'] = $tel_from;
			$arr['call_status'] = $call_status;
			$arr['check_call_dpl'] = $check_call_dpl;
			$arr['check_call_dpl_for_billing'] = $check_call_dpl_for_billing;
			$arr['charge_seconds'] = $charge_seconds;
			array_push($output_arr, $arr);
		}
	}

	//案件種別、事務所、日付でソート
	$site_group = array();
	$advertiser_id = array();
	$date_from = array();
	foreach ($output_arr as $v) {
		$site_group[] = $v['site_group'];
		$advertiser_id[] = $v['advertiser_id'];
		$date_from[] = $v['date_from'];
	}

	array_multisort(
		$site_group, SORT_NUMERIC, SORT_ASC,
		$advertiser_id, SORT_NUMERIC, SORT_ASC,
		$date_from, SORT_ASC,
		$output_arr
	);

	foreach ($output_arr as $out)
	{
			#Excelへの記入
			$reviser->addString($sheet_num, $i, 0, $out['advertiser_id']);
			$reviser->addString($sheet_num, $i, 1, $out['office_name']);
			$reviser->addString($sheet_num, $i, 3, $out['media_name']);
			$reviser->addString($sheet_num, $i, 4, $out['tel_to']);
			$reviser->addString($sheet_num, $i, 5, $out['tel_send']);
			$reviser->addString($sheet_num, $i, 6, $out['date_from']);
			$reviser->addString($sheet_num, $i, 7, $out['date_to']);
			$reviser->addNumber($sheet_num, $i, 8, $out['call_minutes']);
			$reviser->addString($sheet_num, $i, 9, $out['tel_from']);
			$reviser->addString($sheet_num, $i, 10, $out['call_status']);
			$reviser->addString($sheet_num, $i, 11, $out['check_call_dpl']);
			$reviser->addString($sheet_num, $i, 12, $out['check_call_dpl_for_billing']);
			$reviser->addString($sheet_num, $i, 13, $out['charge_seconds']);
			$i++;
	}

	//crmシートメールデータ処理
	$reviser->addString($sheet_num, $i, 0, "メールデータ");
	$output_arr = array();
	foreach ($arr_ad_group_id as $row) {
		$ad_group_id = $row['ad_group_id'];
		#登録事務所名の取得
		$sum_crm_mail_data = array();
		$stmt = $pdo_cdr->query("
			SELECT
				ad.ID as advertiser_id,
				ad.office_name,
				c.*,
				st.site_group,
				sg.site_group_name
			FROM
				cdr.mail_conv c,
				wordpress.ss_advertisers ad,
				wordpress.ss_advertiser_ad_group aadg,
				wordpress.ss_site_type st
			LEFT OUTER JOIN wordpress.ss_site_group sg
			ON st.site_group = sg.site_group
			WHERE
				c.advertiser_id = ad.ID AND
				c.advertiser_id = aadg.advertiser_id AND
				c.site_type = st.site_type AND
				DATE_FORMAT(c.register_dt,'%Y%m') = $year_month AND
				aadg.ad_group_id = $ad_group_id
			ORDER BY
				register_dt
		");
		$arr_crm_mail_data = $stmt->fetchAll(PDO::FETCH_ASSOC);
		foreach ($arr_crm_mail_data as $r) {
			$advertiser_id = $r['advertiser_id'];
			$office_name = $r['office_name'];
			$site_type = $r['site_type'];
			$site_group = $r['site_group'];
			$site_group_name = $r['site_group_name'];
			$sender_tel = $r['sender_tel'];
			$date = $r['register_dt'];
			$dpl_tel_cnt = $r['dpl_tel_cnt'];
			$dpl_mail_cnt = $r['dpl_mail_cnt'];
			if ($dpl_tel_cnt == 0 && $dpl_mail_cnt == 0) {
				$check_mail_dpl = "○";
			}
			else if ($dpl_tel_cnt > 0 || $dpl_mail_cnt > 0) {
				$check_mail_dpl = "重複";
			}
			#事務所毎かつ、発生メール毎の情報が入る配列
			$new_crm_mail_array_data = array();
			#サイト名の入手
			$stmt = $pdo_wordpress->query("
				SELECT
					site_type_name
				FROM
					ss_site_type
				WHERE
					site_type = $site_type
			");
			$arr_st_name = $stmt->fetchAll(PDO::FETCH_ASSOC);
			foreach ($arr_st_name as $row) {
				$site_type_name = $row['site_type_name'];
			}
			array_push($new_crm_mail_array_data, $advertiser_id, $office_name, $site_group, $site_group_name, $site_type, $site_type_name, $sender_tel, $date, $check_mail_dpl);
			array_push($sum_crm_mail_data, $new_crm_mail_array_data);
		}
		#配列に代入したメールデータを出力
		foreach ($sum_crm_mail_data as $row) {
			$advertiser_id = $row['0'];
			$office_name = $row['1'];
			$site_group = $row['2'];
			$site_group_name = $row['3'];
			$site_type = $row['4'];
			$site_type_name = $row['5'];
			$sender_tel = $row['6'];
			$mail_date = $row['7'];
			$check_mail_dpl = $row['8'];

			$arr = array();
			$arr['advertiser_id'] = $advertiser_id;
			$arr['office_name'] = $office_name;
			$arr['site_group'] = $site_group;
			$arr['site_group_name'] = $site_group_name;
			$arr['site_type'] = $site_type;
			$arr['site_type_name'] = $site_type_name;
			$arr['mail_date'] = $mail_date;
			$arr['sender_tel'] = $sender_tel;
			$arr['check_mail_dpl'] = $check_mail_dpl;
			array_push($output_arr, $arr);
		}
	}

	//案件種別、事務所、日付でソート
	$site_group = array();
	$advertiser_id = array();
	$mail_date = array();
	foreach ($output_arr as $v) {
		$site_group[] = $v['site_group'];
		$advertiser_id[] = $v['advertiser_id'];
		$mail_date[] = $v['mail_date'];
	}

	array_multisort(
		$site_group, SORT_NUMERIC, SORT_ASC,
		$advertiser_id, SORT_NUMERIC, SORT_ASC,
		$mail_date, SORT_ASC,
		$output_arr
	);

	foreach ($output_arr as $out)
	{
			$i++;
			#Excelへの記入
			$reviser->addString($sheet_num, $i, 0, $out['advertiser_id']);
			$reviser->addString($sheet_num, $i, 1, $out['office_name']);
			$reviser->addString($sheet_num, $i, 3, $out['site_group_name']);
			$reviser->addString($sheet_num, $i, 4, $out['site_type_name']);
			$reviser->addString($sheet_num, $i, 7, $out['mail_date']);
			$reviser->addString($sheet_num, $i, 9, $out['sender_tel']);
			$reviser->addString($sheet_num, $i, 11, $out['check_mail_dpl']);
	}

	#シートネームを設定
	$reviser->setSheetname($sheet_num, $bill_payer_name);
	#事務所毎でのsheetの名前
	$sheet_name = "請求書（".$bill_payer_name.$year."年".$month."月分）";
	#テンプレを読み込み、出力する
	$readfile = "./template.xls";	
	$outfile = $sheet_name.".xls";
	if ($filepath != null) {
		$reviser->revisefile($readfile, $outfile, $filepath);
	}
	else {
		$reviser->revisefile($readfile, $outfile);
	}
}
#end_of_function/get_each_ad_data
?>