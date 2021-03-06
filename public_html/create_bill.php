<?php

require '../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Reader\Xlsx as XlsxReader;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx as XlsxWriter;
use PhpOffice\PhpSpreadsheet\IOFactory;

#共通関数のインクルード
include 'common_functions.php';

#エラーを画面に表示させない処理
ini_set("display_errors", "off");
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
$pack = null;
if (isset($_POST['pack'])) {
	$pack = $_POST['pack'];
}

if (!empty($bill_payer_id)) {
	#チェック関数を呼び出し、nullで無ければExcelに書き出す
	#20170323 有効電話・メール０件でも請求書ダウンロード出来るようにした
	$call_check = check_valid_call($bill_payer_id,$year,$month);
	$mail_check = check_valid_mail($bill_payer_id,$year,$month);

	#シート準備
	$spreadsheet = IOFactory::load('./template.xlsx');
	get_each_ad_data($spreadsheet, $bill_payer_id, $year, $month, $year_month);

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
		$spreadsheet = IOFactory::load('./template.xlsx');
		get_each_ad_data($spreadsheet, $id, $year, $month, $year_month, $path);
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
				valid_call_rikon is not null OR
				valid_call_bgatakanen is not null OR
				valid_call_hibouchuushou is not null OR
				valid_call_jikouenyou is not null OR
				valid_call_roudou is not null OR
				valid_call_youikuhi is not null OR
				valid_call_fudousan is not null OR
				valid_call_seinenkouken is not null
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
				mail_keijijiken is not null OR
				mail_rikon is not null OR
				mail_bgatakanen is not null OR
				mail_hibouchuushou is not null OR
				mail_jikouenyou is not null OR
				mail_roudou is not null OR
				mail_youikuhi is not null OR
				mail_fudousan is not null OR
				mail_seinenkouken is not null
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
			valid_call_ninibaikyaku,
			valid_call_meigihenkou,
			valid_call_setsuritsu,
			valid_call_keijijiken,
			valid_call_rikon,
			valid_call_bgatakanen,
			valid_call_hibouchuushou,
			valid_call_jikouenyou,
			valid_call_roudou,
			valid_call_youikuhi,
			valid_call_fudousan,
			valid_call_seinenkouken
		FROM
			monthly_valid_call
		WHERE
			bill_payer_id = $bill_payer_id AND
			year=$year AND
			month=$month
	");
	$arr_call_check = $stmt->fetchAll(PDO::FETCH_ASSOC);
	$all_call_check = "";
	foreach ($arr_call_check as $row) {
		$all_call_check += $row['valid_call_shakkin'];
		$all_call_check += $row['valid_call_souzoku'];
		$all_call_check += $row['valid_call_koutsujiko'];
		$all_call_check += $row['valid_call_ninibaikyaku'];
		$all_call_check += $row['valid_call_meigihenkou'];
		$all_call_check += $row['valid_call_setsuritsu'];
		$all_call_check += $row['valid_call_keijijiken'];
		$all_call_check += $row['valid_call_rikon'];
		$all_call_check += $row['valid_call_bgatakanen'];
		$all_call_check += $row['valid_call_hibouchuushou'];
		$all_call_check += $row['valid_call_jikouenyou'];
		$all_call_check += $row['valid_call_roudou'];
		$all_call_check += $row['valid_call_youikuhi'];
		$all_call_check += $row['valid_call_fudousan'];
		$all_call_check += $row['valid_call_seinenkouken'];
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
			mail_ninibaikyaku,
			mail_meigihenkou,
			mail_setsuritsu,
			mail_keijijiken,
			mail_rikon,
			mail_bgatakanen,
			mail_hibouchuushou,
			mail_jikouenyou,
			mail_roudou,
			mail_youikuhi,
			mail_fudousan,
			mail_seinenkouken
		FROM
			monthly_mail_num
		WHERE
			bill_payer_id = $bill_payer_id AND
			year=$year AND
			month=$month
	");
	$arr_mail_check = $stmt->fetchAll(PDO::FETCH_ASSOC);
	$all_mail_check = "";
	foreach ($arr_mail_check as $row) {
		$all_mail_check += $row['mail_shakkin'];
		$all_mail_check += $row['mail_souzoku'];
		$all_mail_check += $row['mail_koutsujiko'];
		$all_mail_check += $row['mail_ninibaikyaku'];
		$all_mail_check += $row['mail_meigihenkou'];
		$all_mail_check += $row['mail_setsuritsu'];
		$all_mail_check += $row['mail_keijijiken'];
		$all_mail_check += $row['mail_rikon'];
		$all_mail_check += $row['mail_bgatakanen'];
		$all_mail_check += $row['mail_hibouchuushou'];
		$all_mail_check += $row['mail_jikouenyou'];
		$all_mail_check += $row['mail_roudou'];
		$all_mail_check += $row['mail_youikuhi'];
		$all_mail_check += $row['mail_fudousan'];
		$all_mail_check += $row['mail_seinenkouken'];
	}
	return $all_mail_check;
}
//end_of_function

function get_each_ad_data($spreadsheet, $bill_payer_id, $year, $month, $year_month, $filepath = null) {
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
	$all_call_bgatakanen = null;
	$all_call_hibouchuushou = null;
	$all_call_jikouenyou = null;
	$all_call_roudou = null;
	$all_call_youikuhi = null;
	$all_call_fudousan = null;
	$all_call_seinenkouken = null;
	#除外コール数
	$ex_call_shakkin = null;
	$ex_call_souzoku = null;
	$ex_call_koutsujiko = null;
	$ex_call_ninibaikyaku = null;
	$ex_call_meigihenkou = null;
	$ex_call_setsuritsu = null;
	$ex_call_keijijiken = null;
	$ex_call_rikon = null;
	$ex_call_bgatakanen = null;
	$ex_call_hibouchuushou = null;
	$ex_call_jikouenyou = null;
	$ex_call_roudou = null;
	$ex_call_youikuhi = null;
	$ex_call_fudousan = null;
	$ex_call_seinenkouken = null;
	#除外依頼コール数
	$req_ex_call_shakkin = null;
	$req_ex_call_souzoku = null;
	$req_ex_call_koutsujiko = null;
	$req_ex_call_ninibaikyaku = null;
	$req_ex_call_meigihenkou = null;
	$req_ex_call_setsuritsu = null;
	$req_ex_call_keijijiken = null;
	$req_ex_call_rikon = null;
	$req_ex_call_bgatakanen = null;
	$req_ex_call_hibouchuushou = null;
	$req_ex_call_jikouenyou = null;
	$req_ex_call_roudou = null;
	$req_ex_call_youikuhi = null;
	$req_ex_call_fudousan = null;
	$req_ex_call_seinenkouken = null;
	#無効も含めた全てのメール数
	$all_mail_shakkin = null;
	$all_mail_souzoku = null;
	$all_mail_koutsujiko = null;
	$all_mail_ninibaikyaku = null;
	$all_mail_meigihenkou = null;
	$all_mail_setsuritsu = null;
	$all_mail_keijijiken = null;
	$all_mail_rikon = null;
	$all_mail_bgatakanen = null;
	$all_mail_hibouchuushou = null;
	$all_mail_jikouenyou = null;
	$all_mail_roudou = null;
	$all_mail_youikuhi = null;
	$all_mail_fudousan = null;
	$all_mail_seinenkouken = null;
	#除外メール数
	$ex_mail_shakkin = null;
	$ex_mail_souzoku = null;
	$ex_mail_koutsujiko = null;
	$ex_mail_ninibaikyaku = null;
	$ex_mail_meigihenkou = null;
	$ex_mail_setsuritsu = null;
	$ex_mail_keijijiken = null;
	$ex_mail_rikon = null;
	$ex_mail_bgatakanen = null;
	$ex_mail_hibouchuushou = null;
	$ex_mail_jikouenyou = null;
	$ex_mail_roudou = null;
	$ex_mail_youikuhi = null;
	$ex_mail_fudousan = null;
	$ex_mail_seinenkouken = null;
	#除外依頼メール数
	$req_ex_mail_shakkin = null;
	$req_ex_mail_souzoku = null;
	$req_ex_mail_koutsujiko = null;
	$req_ex_mail_ninibaikyaku = null;
	$req_ex_mail_meigihenkou = null;
	$req_ex_mail_setsuritsu = null;
	$req_ex_mail_keijijiken = null;
	$req_ex_mail_rikon = null;
	$req_ex_mail_bgatakanen = null;
	$req_ex_mail_hibouchuushou = null;
	$req_ex_mail_jikouenyou = null;
	$req_ex_mail_roudou = null;
	$req_ex_mail_youikuhi = null;
	$req_ex_mail_fudousan = null;
	$req_ex_mail_seinenkouken = null;
	#メール日
	$shakkin_mail_dt = null;
	$souzoku_mail_dt = null;
	$koutsujiko_mail_dt = null;
	$ninibaikyaku_mail_dt = null;
	$meigihenkou_mail_dt = null;
	$setsuritsu_mail_dt = null;
	$keijijiken_mail_dt = null;
	$rikon_mail_dt = null;
	$bgatakanen_mail_dt = null;
	$hibouchuushou_mail_dt = null;
	$jikouenyou_mail_dt = null;
	$roudou_mail_dt = null;
	$youikuhi_mail_dt = null;
	$fudousan_mail_dt = null;
	$seinenkouken_mail_dt = null;
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
	$inv_bgatakanen = null;
	$inv_hibouchuushou = null;
	$inv_jikouenyou = null;
	$inv_roudou = null;
	$inv_youikuhi = null;
	$inv_fudousan = null;
	$inv_seinenkouken = null;
	#メディア毎有効請求
	$res_shakkin = null;
	$res_souzoku = null;
	$res_koutsujiko = null;
	$res_ninibaikyaku = null;
	$res_meigihenkou = null;
	$res_setsuritsu = null;
	$res_keijijiken = null;
	$res_rikon = null;
	$res_bgatakanen = null;
	$res_hibouchuushou = null;
	$res_jikouenyou = null;
	$res_roudou = null;
	$res_youikuhi = null;
	$res_fudousan = null;
	$res_seinenkouken = null;

	#合計詳細
	$va_shakkin= null;
	$va_souzoku = null;
	$va_koutsujiko = null;
	$va_ninibaikyaku = null;
	$va_meigihenkou = null;
	$va_setsuritsu = null;
	$va_keijijiken = null;
	$va_rikon = null;
	$va_bgatakanen = null;
	$va_hibouchuushou = null;
	$va_jikouenyou = null;
	$va_roudou = null;
	$va_youikuhi = null;
	$va_fudousan = null;
	$va_seinenkouken = null;
	#template文
	$all_tmp = null;
	$inv_tmp = null;
	#空の配列作成
	$arr_shakkin_mail_dt = array();
	$arr_souzoku_mail_dt = array();
	$arr_koutsujiko_mail_dt = array();
	$arr_ninibaikyaku_mail_dt = array();
	$arr_meigihenkou_mail_dt = array();
	$arr_setsuritsu_mail_dt = array();
	$arr_keijijiken_mail_dt = array();
	$arr_rikon_mail_dt = array();
	$arr_bgatakanen_mail_dt = array();
	$arr_hibouchuushou_mail_dt = array();
	$arr_jikouenyou_mail_dt = array();
	$arr_roudou_mail_dt = array();
	$arr_youikuhi_mail_dt = array();
	$arr_fudousan_mail_dt = array();
	$arr_seinenkouken_mail_dt = array();

	$calls = array(
		'0' => 0,
		'1' => 0,
		'2' => 0,
		'3' => 0,
		'4' => 0,
		'5' => 0,
		'6' => 0,
		'7' => 0,
		'8' => 0,
		'9' => 0,
		'10' => 0,
		'11' => 0,
		'12' => 0,
		'13' => 0,
		'14' => 0,
		'sum' => 0
	);
	$stmt = $pdo_request->query("
		SELECT
			ad_group_id	
		FROM
			ad_group_bill_payer
		WHERE
			bill_payer_id = $bill_payer_id
	");
	$ad_group_ids = $stmt->fetchAll(PDO::FETCH_ASSOC);
	foreach ($ad_group_ids as $v) {
		$ad_group_id = $v['ad_group_id'];
		$call_data = get_monthly_total_calls($year_month, CALL_TYPE_VALID, $ad_group_id);
		foreach ($call_data as $c) {
			if ($c['site_group'] == null) {
				continue;
			}
			$calls[$c['site_group']] += intval($c['tel_count']);
			$calls['sum'] += intval($c['tel_count']);
		}
	}

	#問題毎コール数の取得
	$shakkin_call = $calls['0'];
	$souzoku_call = $calls['1'];
	$koutsujiko_call = $calls['2'];
	$ninibaikyaku_call = $calls['3'];
	$meigihenkou_call = $calls['4'];
	$setsuritsu_call = $calls['5'];
	$keijijiken_call = $calls['6'];
	$rikon_call = $calls['7'];
	$bgatakanen_call = $calls['8'];
	$hibouchuushou_call = $calls['9'];
	$jikouenyou_call = $calls['10'];
	$roudou_call = $calls['11'];
	$youikuhi_call = $calls['12'];
	$fudousan_call = $calls['13'];
	$seinenkouken_call = $calls['14'];
	$call_sum = $calls['sum'];

	####課金メール数請求内容データの取得
	$mails = array(
		'0' => 0,
		'1' => 0,
		'2' => 0,
		'3' => 0,
		'4' => 0,
		'5' => 0,
		'6' => 0,
		'7' => 0,
		'8' => 0,
		'9' => 0,
		'10' => 0,
		'11' => 0,
		'12' => 0,
		'13' => 0,
		'14' => 0,
		'sum' => 0
	);

	foreach ($ad_group_ids as $v) {
		$ad_group_id = $v['ad_group_id'];
		$mail_data = get_monthly_total_mails($year_month, MAIL_TYPE_VALID, $ad_group_id);
		foreach ($mail_data as $m) {
			if ($m['site_group'] == null) {
				continue;
			}
			$mails[$m['site_group']] += intval($m['mail_count']);
			$mails['sum'] += intval($m['mail_count']);
		}
	}
	#問題ごとメール数の取得
	$shakkin_mail = $mails['0'];
	$souzoku_mail = $mails['1'];
	$koutsujiko_mail = $mails['2'];
	$ninibaikyaku_mail = $mails['3'];
	$meigihenkou_mail = $mails['4'];
	$setsuritsu_mail = $mails['5'];
	$keijijiken_mail = $mails['6'];
	$rikon_mail = $mails['7'];
	$bgatakanen_mail = $mails['8'];
	$hibouchuushou_mail = $mails['9'];
	$jikouenyou_mail = $mails['10'];
	$roudou_mail = $mails['11'];
	$youikuhi_mail = $mails['12'];
	$fudousan_mail = $mails['13'];
	$seinenkouken_mail = $mails['14'];
	$mail_sum = $mails['sum'];
	#請求合計数の取得
	$all_sum = $call_sum+$mail_sum;
	#######無効も含めた全コール数,メール数の取得
	#無効アリコール数
	$stmt = $pdo_request->query("
		SELECT
			abp.ad_group_id,
			ag.group_name
		FROM
			smk_request_data.ad_group_bill_payer abp,
			wordpress.ss_ad_groups ag
		WHERE
			abp.ad_group_id = ag.ID AND
			abp.bill_payer_id = $bill_payer_id
	");
	$arr_ad_group_id =$stmt->fetchAll(PDO::FETCH_ASSOC);
	foreach ($arr_ad_group_id as $row) {
		$ad_group_id = $row['ad_group_id'];
		$stmt = $pdo_cdr->query("
			SELECT
				media_id,
				is_exclusion,
				exclusion_is_request
			FROM
				call_data_view
			WHERE
				ad_group_id = $ad_group_id AND
				DATE_FORMAT(date_from,'%Y%m') = $year_month
		");
		$arr_all_call_data =$stmt->fetchAll(PDO::FETCH_ASSOC);
		foreach($arr_all_call_data as $row) {
			$mi = $row['media_id'];
			$ex = $row['is_exclusion'];
			$is_req = $row['exclusion_is_request'];
			if(substr($mi, 0, 1) == "A") {
				$all_call_shakkin++;
				if ($ex == 1) {
					if ($is_req == 1) {
						$req_ex_call_shakkin++;
					}
					else {
						$ex_call_shakkin++;
					}
				}
			}
			else if(substr($mi, 0, 1) == "B") {
				$all_call_souzoku++;
				if ($ex == 1) {
					if ($is_req == 1) {
						$req_ex_call_souzoku++;
					}
					else {
						$ex_call_souzoku++;
					}
				}
			}
			else if(substr($mi, 0, 1) == "C") {
				$all_call_koutsujiko++;
				if ($ex == 1) {
					if ($is_req == 1) {
						$req_ex_call_koutsujiko++;
					}
					else {
						$ex_call_koutsujiko++;
					}
				}
			}
			else if(substr($mi, 0, 1) == "D") {
				$all_call_ninibaikyaku++;
				if ($ex == 1) {
					if ($is_req == 1) {
						$req_ex_call_ninibaikyaku++;
					}
					else {
						$ex_call_ninibaikyaku++;
					}
				}
			}
			else if(substr($mi, 0, 1) == "E") {
				$all_call_meigihenkou++;
				if ($ex == 1) {
					if ($is_req == 1) {
						$req_ex_call_meigihenkou++;
					}
					else {
						$ex_call_meigihenkou++;
					}
				}
			}
			else if(substr($mi, 0, 1) == "F") {
				$all_call_setsuritsu++;
				if ($ex == 1) {
					if ($is_req == 1) {
						$req_ex_call_setsuritsu++;
					}
					else {
						$ex_call_setsuritsu++;
					}
				}
			}
			else if(substr($mi, 0, 1) == "G") {
				$all_call_keijijiken++;
				if ($ex == 1) {
					if ($is_req == 1) {
						$req_ex_call_keijijiken++;
					}
					else {
						$ex_call_keijijiken++;
					}
				}
			}	
			else if(substr($mi, 0, 1) == "H") {
				$all_call_rikon++;
				if ($ex == 1) {
					if ($is_req == 1) {
						$req_ex_call_rikon++;
					}
					else {
						$ex_call_rikon++;
					}
				}
			}	
			else if(substr($mi, 0, 1) == "I") {
				$all_call_bgatakanen++;
				if ($ex == 1) {
					if ($is_req == 1) {
						$req_ex_call_bgatakanen++;
					}
					else {
						$ex_call_bgatakanen++;
					}
				}
			}	
			else if(substr($mi, 0, 1) == "J") {
				$all_call_hibouchuushou++;
				if ($ex == 1) {
					if ($is_req == 1) {
						$req_ex_call_hibouchuushou++;
					}
					else {
						$ex_call_hibouchuushou++;
					}
				}
			}	
			else if(substr($mi, 0, 1) == "K") {
				$all_call_jikouenyou++;
				if ($ex == 1) {
					if ($is_req == 1) {
						$req_ex_call_jikouenyou++;
					}
					else {
						$ex_call_jikouenyou++;
					}
				}
			}	
			else if(substr($mi, 0, 1) == "L") {
				$all_call_roudou++;
				if ($ex == 1) {
					if ($is_req == 1) {
						$req_ex_call_roudou++;
					}
					else {
						$ex_call_roudou++;
					}
				}
			}	
			else if(substr($mi, 0, 1) == "M") {
				$all_call_youikuhi++;
				if ($ex == 1) {
					if ($is_req == 1) {
						$req_ex_call_youikuhi++;
					}
					else {
						$ex_call_youikuhi++;
					}
				}
			}	
			else if(substr($mi, 0, 1) == "N") {
				$all_call_fudousan++;
				if ($ex == 1) {
					if ($is_req == 1) {
						$req_ex_call_fudousan++;
					}
					else {
						$ex_call_fudousan++;
					}
				}
			}	
			else if(substr($mi, 0, 1) == "O") {
				$all_call_seinenkouken++;
				if ($ex == 1) {
					if ($is_req == 1) {
						$req_ex_call_seinenkouken++;
					}
					else {
						$ex_call_seinenkouken++;
					}
				}
			}	
		}
		#無効アリメール数,メール日取得
		$stmt2 = $pdo_cdr->query("
			SELECT
				mc.site_group,
				DATE_FORMAT(mc.register_dt,'%m%d') as reg_dt,
				is_exclusion,
				exclusion_is_request
			FROM
				cdr.mail_conv_view mc
			WHERE
				DATE_FORMAT(mc.register_dt,'%Y%m') = $year_month AND
				mc.ad_group_id = $ad_group_id
		");
		$arr_all_mail_data = $stmt2->fetchAll(PDO::FETCH_ASSOC);
		foreach($arr_all_mail_data as $row) {
			$sg = $row['site_group'];
			$register_dt = $row["reg_dt"];
			$ex = $row["is_exclusion"];
			$is_req = $row["exclusion_is_request"];
			$mail_month = substr($register_dt, 0, 2);
			$mail_day = substr($register_dt, 2, 2);
			$mail_day = sprintf('%01d', $mail_day);
			if ($sg == 0) {
				$all_mail_shakkin++;
				if ($ex == 1) {
					if ($is_req == 1) {
						$req_ex_mail_shakkin++;
					}
					else {
						$ex_mail_shakkin++;
					}
				}
				array_push($arr_shakkin_mail_dt, $mail_day);
				asort($arr_shakkin_mail_dt);
			}
			else if ($sg == 1) {
				$all_mail_souzoku++;
				if ($ex == 1) {
					if ($is_req == 1) {
						$req_ex_mail_souzoku++;
					}
					else {
						$ex_mail_souzoku++;
					}
				}
				array_push($arr_souzoku_mail_dt, $mail_day);
				asort($arr_souzoku_mail_dt);
			}
			else if ($sg == 2) {
				$all_mail_koutsujiko++;
				if ($ex == 1) {
					if ($is_req == 1) {
						$req_ex_mail_koutsujiko++;
					}
					else {
						$ex_mail_koutsujiko++;
					}
				}
				array_push($arr_koutsujiko_mail_dt, $mail_day);
				asort($arr_koutsujiko_mail_dt);
			}
			else if ($sg == 3) {
				$all_mail_ninibaikyaku++;
				if ($ex == 1) {
					if ($is_req == 1) {
						$req_ex_mail_ninibaikyaku++;
					}
					else {
						$ex_mail_ninibaikyaku++;
					}
				}
				array_push($arr_ninibaikyaku_mail_dt, $mail_day);
				asort($arr_ninibaikyaku_mail_dt);
			}
			else if ($sg == 4) {
				$all_mail_meigihenkou++;
				if ($ex == 1) {
					if ($is_req == 1) {
						$req_ex_mail_meigihenkou++;
					}
					else {
						$ex_mail_meigihenkou++;
					}
				}
				array_push($arr_meigihenkou_mail_dt, $mail_day);
				asort($arr_meigihenkou_mail_dt);
			}
			else if ($sg == 5) { 
				$all_mail_setsuritsu++;
				if ($ex == 1) {
					if ($is_req == 1) {
						$req_ex_mail_setsuritsu++;
					}
					else {
						$ex_mail_setsuritsu++;
					}
				}
				array_push($arr_setsuritsu_mail_dt, $mail_day);
				asort($arr_setsuritsu_mail_dt);
			}
			else if ($sg == 6) { 
				$all_mail_keijijiken++;
				if ($ex == 1) {
					if ($is_req == 1) {
						$req_ex_mail_keijijiken++;
					}
					else {
						$ex_mail_keijijiken++;
					}
				}
				array_push($arr_keijijiken_mail_dt, $mail_day);
				asort($arr_keijijiken_mail_dt);
			}
			else if ($sg == 7) { 
				$all_mail_rikon++;
				if ($ex == 1) {
					if ($is_req == 1) {
						$req_ex_mail_rikon++;
					}
					else {
						$ex_mail_rikon++;
					}
				}
				array_push($arr_rikon_mail_dt, $mail_day);
				asort($arr_rikon_mail_dt);
			}
			else if ($sg == 8) { 
				$all_mail_bgatakanen++;
				if ($ex == 1) {
					if ($is_req == 1) {
						$req_ex_mail_bgatakanen++;
					}
					else {
						$ex_mail_bgatakanen++;
					}
				}
				array_push($arr_bgatakanen_mail_dt, $mail_day);
				asort($arr_bgatakanen_mail_dt);
			}
			else if ($sg == 9) { 
				$all_mail_hibouchuushou++;
				if ($ex == 1) {
					if ($is_req == 1) {
						$req_ex_mail_hibouchuushou++;
					}
					else {
						$ex_mail_hibouchuushou++;
					}
				}
				array_push($arr_hibouchuushou_mail_dt, $mail_day);
				asort($arr_hibouchuushou_mail_dt);
			}
			else if ($sg == 10) { 
				$all_mail_jikouenyou++;
				if ($ex == 1) {
					if ($is_req == 1) {
						$req_ex_mail_jikouenyou++;
					}
					else {
						$ex_mail_jikouenyou++;
					}
				}
				array_push($arr_jikouenyou_mail_dt, $mail_day);
				asort($arr_jikouenyou_mail_dt);
			}
			else if ($sg == 11) { 
				$all_mail_roudou++;
				if ($ex == 1) {
					if ($is_req == 1) {
						$req_ex_mail_roudou++;
					}
					else {
						$ex_mail_roudou++;
					}
				}
				array_push($arr_roudou_mail_dt, $mail_day);
				asort($arr_roudou_mail_dt);
			}
			else if ($sg == 12) { 
				$all_mail_youikuhi++;
				if ($ex == 1) {
					if ($is_req == 1) {
						$req_ex_mail_youikuhi++;
					}
					else {
						$ex_mail_youikuhi++;
					}
				}
				array_push($arr_youikuhi_mail_dt, $mail_day);
				asort($arr_youikuhi_mail_dt);
			}
			else if ($sg == 13) { 
				$all_mail_fudousan++;
				if ($ex == 1) {
					if ($is_req == 1) {
						$req_ex_mail_fudousan++;
					}
					else {
						$ex_mail_fudousan++;
					}
				}
				array_push($arr_fudousan_mail_dt, $mail_day);
				asort($arr_fudousan_mail_dt);
			}
			else if ($sg == 14) { 
				$all_mail_seinenkouken++;
				if ($ex == 1) {
					if ($is_req == 1) {
						$req_ex_mail_seinenkouken++;
					}
					else {
						$ex_mail_seinenkouken++;
					}
				}
				array_push($arr_seinenkouken_mail_dt, $mail_day);
				asort($arr_seinenkouken_mail_dt);
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
	//刑事
	foreach ($arr_keijijiken_mail_dt as $row) {
		$keijijiken_mail_dt .= $row."日・";
	}
	$keijijiken_mail_dt = rtrim($keijijiken_mail_dt,'・');
	$keijijiken_mail_dt = "(".$keijijiken_mail_dt.")";
	//離婚
	foreach ($arr_rikon_mail_dt as $row) {
		$rikon_mail_dt .= $row."日・";
	}
	$rikon_mail_dt = rtrim($rikon_mail_dt,'・');
	$rikon_mail_dt = "(".$rikon_mail_dt.")";
	//Ｂ型肝炎
	foreach ($arr_bgatakanen_mail_dt as $row) {
		$bgatakanen_mail_dt .= $row."日・";
	}
	$bgatakanen_mail_dt = rtrim($bgatakanen_mail_dt,'・');
	$bgatakanen_mail_dt = "(".$bgatakanen_mail_dt.")";
	//誹謗中傷	
	foreach ($arr_hibouchuushou_mail_dt as $row) {
		$hibouchuushou_mail_dt .= $row."日・";
	}
	$hibouchuushou_mail_dt = rtrim($hibouchuushou_mail_dt,'・');
	$hibouchuushou_mail_dt = "(".$hibouchuushou_mail_dt.")";
	//時効援用	
	foreach ($arr_jikouenyou_mail_dt as $row) {
		$jikouenyou_mail_dt .= $row."日・";
	}
	$jikouenyou_mail_dt = rtrim($jikouenyou_mail_dt,'・');
	$jikouenyou_mail_dt = "(".$jikouenyou_mail_dt.")";
	//労働問題
	foreach ($arr_roudou_mail_dt as $row) {
		$roudou_mail_dt .= $row."日・";
	}
	$roudou_mail_dt = rtrim($roudou_mail_dt,'・');
	$roudou_mail_dt = "(".$roudou_mail_dt.")";
	//養育費回収
	foreach ($arr_youikuhi_mail_dt as $row) {
		$youikuhi_mail_dt .= $row."日・";
	}
	$youikuhi_mail_dt = rtrim($youikuhi_mail_dt,'・');
	$youikuhi_mail_dt = "(".$youikuhi_mail_dt.")";
	//不動産問題
	foreach ($arr_fudousan_mail_dt as $row) {
		$fudousan_mail_dt .= $row."日・";
	}
	$fudousan_mail_dt = rtrim($fudousan_mail_dt,'・');
	$fudousan_mail_dt = "(".$fudousan_mail_dt.")";
	//成年後見
	foreach ($arr_seinenkouken_mail_dt as $row) {
		$seinenkouken_mail_dt .= $row."日・";
	}
	$seinenkouken_mail_dt = rtrim($seinenkouken_mail_dt,'・');
	$seinenkouken_mail_dt = "(".$seinenkouken_mail_dt.")";

	#####請求対象の取得
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
	if ($all_call_keijijiken != null && $all_mail_keijijiken != null) {
		$keijijiken_all_tmp = "刑事事件サイトで".$all_call_keijijiken."件の電話と".$all_mail_keijijiken."件のメール".$keijijiken_mail_dt;
	}
	else if($all_call_setsuritsu != null && $all_mail_keijijiken == null) {
		$keijijiken_all_tmp = "刑事事件サイトで".$all_call_keijijiken."件の電話";
	}
	else if($all_call_keijijiken == null && $all_mail_keijijiken != null){
		$keijijiken_all_tmp = "刑事事件サイトで".$all_mail_keijijiken."件のメール".$keijijiken_mail_dt;
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
	#Ｂ型肝炎	all_tmp
	if ($all_call_bgatakanen != null && $all_mail_bgatakanen != null) {
		$bgatakanen_all_tmp = "Ｂ型肝炎サイトで".$all_call_bgatakanen."件の電話と".$all_mail_bgatakanen."件のメール".$bgatakanen_mail_dt;
	}
	else if($all_call_bgatakanen != null && $all_mail_bgatakanen == null) {
		$bgatakanen_all_tmp = "Ｂ型肝炎サイトで".$all_call_bgatakanen."件の電話";
	}
	else if($all_call_bgatakanen == null && $all_mail_bgatakanen != null){
		$bgatakanen_all_tmp = "Ｂ型肝炎サイトで".$all_mail_bgatakanen."件のメール".$bgatakanen_mail_dt;
	}
	else{
		$bgatakanen_all_tmp = "";
	}
	#誹謗中傷	all_tmp
	if ($all_call_hibouchuushou != null && $all_mail_hibouchuushou != null) {
		$hibouchuushou_all_tmp = "誹謗中傷サイトで".$all_call_hibouchuushou."件の電話と".$all_mail_hibouchuushou."件のメール".$hibouchuushou_mail_dt;
	}
	else if($all_call_hibouchuushou != null && $all_mail_hibouchuushou == null) {
		$hibouchuushou_all_tmp = "誹謗中傷サイトで".$all_call_hibouchuushou."件の電話";
	}
	else if($all_call_hibouchuushou == null && $all_mail_hibouchuushou != null){
		$hibouchuushou_all_tmp = "誹謗中傷サイトで".$all_mail_hibouchuushou."件のメール".$hibouchuushou_mail_dt;
	}
	else{
		$hibouchuushou_all_tmp = "";
	}
	#時効援用	all_tmp
	if ($all_call_jikouenyou != null && $all_mail_jikouenyou != null) {
		$jikouenyou_all_tmp = "時効援用サイトで".$all_call_jikouenyou."件の電話と".$all_mail_jikouenyou."件のメール".$jikouenyou_mail_dt;
	}
	else if($all_call_jikouenyou != null && $all_mail_jikouenyou == null) {
		$jikouenyou_all_tmp = "時効援用サイトで".$all_call_jikouenyou."件の電話";
	}
	else if($all_call_jikouenyou == null && $all_mail_jikouenyou != null){
		$jikouenyou_all_tmp = "時効援用サイトで".$all_mail_jikouenyou."件のメール".$jikouenyou_mail_dt;
	}
	else{
		$jikouenyou_all_tmp = "";
	}
	#労働問題	all_tmp
	if ($all_call_roudou != null && $all_mail_roudou != null) {
		$roudou_all_tmp = "労働問題サイトで".$all_call_roudou."件の電話と".$all_mail_roudou."件のメール".$roudou_mail_dt;
	}
	else if($all_call_roudou != null && $all_mail_roudou == null) {
		$roudou_all_tmp = "労働問題サイトで".$all_call_roudou."件の電話";
	}
	else if($all_call_roudou == null && $all_mail_roudou != null){
		$roudou_all_tmp = "労働問題サイトで".$all_mail_roudou."件のメール".$roudou_mail_dt;
	}
	else{
		$roudou_all_tmp = "";
	}
	#養育費回収	all_tmp
	if ($all_call_youikuhi != null && $all_mail_youikuhi != null) {
		$youikuhi_all_tmp = "養育費回収サイトで".$all_call_youikuhi."件の電話と".$all_mail_youikuhi."件のメール".$youikuhi_mail_dt;
	}
	else if($all_call_youikuhi != null && $all_mail_youikuhi == null) {
		$youikuhi_all_tmp = "養育費回収サイトで".$all_call_youikuhi."件の電話";
	}
	else if($all_call_youikuhi == null && $all_mail_youikuhi != null){
		$youikuhi_all_tmp = "養育費回収サイトで".$all_mail_youikuhi."件のメール".$youikuhi_mail_dt;
	}
	else{
		$youikuhi_all_tmp = "";
	}
	#不動産問題	all_tmp
	if ($all_call_fudousan != null && $all_mail_fudousan != null) {
		$fudousan_all_tmp = "不動産問題サイトで".$all_call_fudousan."件の電話と".$all_mail_fudousan."件のメール".$fudousan_mail_dt;
	}
	else if($all_call_fudousan != null && $all_mail_fudousan == null) {
		$fudousan_all_tmp = "不動産問題サイトで".$all_call_fudousan."件の電話";
	}
	else if($all_call_fudousan == null && $all_mail_fudousan != null){
		$fudousan_all_tmp = "不動産問題サイトで".$all_mail_fudousan."件のメール".$fudousan_mail_dt;
	}
	else{
		$fudousan_all_tmp = "";
	}
	#成年後見	all_tmp
	if ($all_call_seinenkouken != null && $all_mail_seinenkouken != null) {
		$seinenkouken_all_tmp = "成年後見サイトで".$all_call_seinenkouken."件の電話と".$all_mail_seinenkouken."件のメール".$seinenkouken_mail_dt;
	}
	else if($all_call_seinenkouken != null && $all_mail_seinenkouken == null) {
		$seinenkouken_all_tmp = "成年後見サイトで".$all_call_seinenkouken."件の電話";
	}
	else if($all_call_seinenkouken == null && $all_mail_seinenkouken != null){
		$seinenkouken_all_tmp = "成年後見サイトで".$all_mail_seinenkouken."件のメール".$seinenkouken_mail_dt;
	}
	else{
		$seinenkouken_all_tmp = "";
	}
	#月末時点の課金時間と価格の取得
	$payments = array(
		'0' => array(0, 0),
		'1' => array(0, 0),
		'2' => array(0, 0),
		'3' => array(0, 0),
		'4' => array(0, 0),
		'5' => array(0, 0),
		'6' => array(0, 0),
		'7' => array(0, 0),
		'8' => array(0, 0),
		'9' => array(0, 0),
		'10' => array(0, 0),
		'11' => array(0, 0),
		'12' => array(0, 0),
		'13' => array(0, 0),
		'14' => array(0, 0),
	);
	$lastdate = date('Y-m-t H:i:s', strtotime(date(($year + floor($month / 12)).'-'.(($month % 12) + 1).'-01 23:59:59') . '-1 month'));
	$stmt4 = $pdo_request->query("
		SELECT
			a.*
		FROM
			(
				SELECT
					ogpm.site_group,
					ogpm.charge_seconds,
					ogpm.unit_price
				FROM
					cdr.office_group_payment_method ogpm,
					ad_group_bill_payer agbp
				WHERE
					ogpm.ad_group_id = agbp.ad_group_id AND
					agbp.bill_payer_id = $bill_payer_id AND
					'$lastdate' BETWEEN ogpm.from_date AND ogpm.to_date
				ORDER BY from_date DESC
			) as a
		GROUP BY
			a.site_group
	");
	$payment_data = $stmt4->fetchAll(PDO::FETCH_ASSOC);

	foreach ($payment_data as $p) {
		$payments[$p['site_group']][0] = intval($p['charge_seconds']);
		$payments[$p['site_group']][1] = intval($p['unit_price']);
	}

	#無効件数の計算
	$res_shakkin = $shakkin_call + $shakkin_mail;
	$res_souzoku = $souzoku_call + $souzoku_mail;
	$res_koutsujiko = $koutsujiko_call + $koutsujiko_mail;
	$res_ninibaikyaku = $ninibaikyaku_call + $ninibaikyaku_mail;
	$res_meigihenkou = $meigihenkou_call + $meigihenkou_mail;
	$res_setsuritsu = $setsuritsu_call + $setsuritsu_mail;
	$res_keijijiken = $keijijiken_call + $keijijiken_mail;
	$res_rikon = $rikon_call + $rikon_mail;
	$res_bgatakanen = $bgatakanen_call + $bgatakanen_mail;
	$res_hibouchuushou = $hibouchuushou_call + $hibouchuushou_mail;
	$res_jikouenyou = $jikouenyou_call + $jikouenyou_mail;
	$res_roudou = $roudou_call + $roudou_mail;
	$res_youikuhi = $youikuhi_call + $youikuhi_mail;
	$res_fudousan = $fudousan_call + $fudousan_mail;
	$res_seinenkouken = $seinenkouken_call + $seinenkouken_mail;

	#除外依頼件数の計算	
	$req_ex_shakkin = $req_ex_call_shakkin + $req_ex_mail_shakkin;
	$req_ex_souzoku = $req_ex_call_souzoku + $req_ex_mail_souzoku;
	$req_ex_koutsujiko = $req_ex_call_koutsujiko + $req_ex_mail_koutsujiko;
	$req_ex_ninibaikyaku = $req_ex_call_ninibaikyaku + $req_ex_mail_ninibaikyaku;
	$req_ex_meigihenkou = $req_ex_call_meigihenkou + $req_ex_mail_meigihenkou;
	$req_ex_setsuritsu = $req_ex_call_setsuritsu + $req_ex_mail_setsuritsu;
	$req_ex_keijijiken = $req_ex_call_keijijiken + $req_ex_mail_keijijiken;
	$req_ex_rikon = $req_ex_call_rikon + $req_ex_mail_rikon;
	$req_ex_bgatakanen = $req_ex_call_bgatakanen + $req_ex_mail_bgatakanen;
	$req_ex_hibouchuushou = $req_ex_call_hibouchuushou + $req_ex_mail_hibouchuushou;
	$req_ex_jikouenyou = $req_ex_call_jikouenyou + $req_ex_mail_jikouenyou;
	$req_ex_roudou = $req_ex_call_roudou + $req_ex_mail_roudou;
	$req_ex_youikuhi = $req_ex_call_youikuhi + $req_ex_mail_youikuhi;
	$req_ex_fudousan = $req_ex_call_fudousan + $req_ex_mail_fudousan;
	$req_ex_seinenkouken = $req_ex_call_seinenkouken + $req_ex_mail_seinenkouken;

	#除外件数の計算	
	$ex_shakkin = $ex_call_shakkin + $ex_mail_shakkin;
	$ex_souzoku = $ex_call_souzoku + $ex_mail_souzoku;
	$ex_koutsujiko = $ex_call_koutsujiko + $ex_mail_koutsujiko;
	$ex_ninibaikyaku = $ex_call_ninibaikyaku + $ex_mail_ninibaikyaku;
	$ex_meigihenkou = $ex_call_meigihenkou + $ex_mail_meigihenkou;
	$ex_setsuritsu = $ex_call_setsuritsu + $ex_mail_setsuritsu;
	$ex_keijijiken = $ex_call_keijijiken + $ex_mail_keijijiken;
	$ex_rikon = $ex_call_rikon + $ex_mail_rikon;
	$ex_bgatakanen = $ex_call_bgatakanen + $ex_mail_bgatakanen;
	$ex_hibouchuushou = $ex_call_hibouchuushou + $ex_mail_hibouchuushou;
	$ex_jikouenyou = $ex_call_jikouenyou + $ex_mail_jikouenyou;
	$ex_roudou = $ex_call_roudou + $ex_mail_roudou;
	$ex_youikuhi = $ex_call_youikuhi + $ex_mail_youikuhi;
	$ex_fudousan = $ex_call_fudousan + $ex_mail_fudousan;
	$ex_seinenkouken = $ex_call_seinenkouken + $ex_mail_seinenkouken;

	$inv_tmp_shakkin = "";
	$inv_tmp_souzoku = "";
	$inv_tmp_koutsujiko = "";
	$inv_tmp_ninibaikyaku = "";
	$inv_tmp_meigihenkou = "";
	$inv_tmp_setsuritsu = "";
	$inv_tmp_keijijiken = "";
	$inv_tmp_rikon = "";
	$inv_tmp_bgatakanen = "";
	$inv_tmp_hibouchuushou = "";
	$inv_tmp_jikouenyou = "";
	$inv_tmp_roudou = "";
	$inv_tmp_youikuhi = "";
	$inv_tmp_fudousan = "";
	$inv_tmp_seinenkouken = "";

	$inv_shakkin = $all_call_shakkin + $all_mail_shakkin - $res_shakkin - $ex_shakkin - $req_ex_shakkin;
	if ($inv_shakkin > 0) {
		$inv_tmp_shakkin = "借金問題サイトで同一電話番号の電話・メール及び".$payments['0'][0]."秒未満電話の".$inv_shakkin."件";
	}
	if ($req_ex_shakkin > 0) {
		$inv_tmp_shakkin .= "\n借金問題サイトで除外依頼頂いた".$req_ex_shakkin."件";
	}
	if ($ex_shakkin > 0) {
		$inv_tmp_shakkin .= "\n借金問題サイトで弊社で除外と判断した".$ex_shakkin."件";
	}
	$inv_souzoku = $all_call_souzoku + $all_mail_souzoku - $res_souzoku - $ex_souzoku - $req_ex_souzoku;
	if ($inv_souzoku > 0) {
		$inv_tmp_souzoku = "相続問題サイトで同一電話番号の電話・メール及び".$payments['1'][0]."秒未満電話の".$inv_souzoku."件";
	}
	if ($req_ex_souzoku > 0) {
		$inv_tmp_souzoku .= "\n相続問題サイトで除外依頼頂いた".$req_ex_souzoku."件";
	}
	if ($ex_souzoku > 0) {
		$inv_tmp_souzoku .= "\n借金問題サイトで弊社で除外と判断した".$ex_souzoku."件";
	}
	$inv_koutsujiko = $all_call_koutsujiko + $all_mail_koutsujiko - $res_koutsujiko - $ex_koutsujiko - $req_ex_koutsujiko;
	if ($inv_koutsujiko > 0) {
		$inv_tmp_koutsujiko = "交通事故サイトで同一電話番号の電話・メール及び".$payments['2'][0]."秒未満電話の".$inv_koutsujiko."件";
	}
	if ($req_ex_koutsujiko > 0) {
		$inv_tmp_koutsujiko .= "\n交通事故サイトで除外依頼頂いた".$req_ex_koutsujiko."件";
	}
	if ($ex_koutsujiko > 0) {
		$inv_tmp_koutsujiko .= "\n交通事故サイトで弊社で除外と判断した".$ex_koutsujiko."件";
	}
	$inv_ninibaikyaku = $all_call_ninibaikyaku + $all_mail_ninibaikyaku - $res_ninibaikyaku - $ex_ninibaikyaku - $req_ex_ninibaikyaku;
	if ($inv_ninibaikyaku > 0) {
		$inv_tmp_ninibaikyaku = "任意売却サイトで同一電話番号の電話・メール及び".$payments['3'][0]."秒未満電話の".$inv_ninibaikyaku."件";
	}
	if ($req_ex_ninibaikyaku > 0) {
		$inv_tmp_ninibaikyaku .= "\n任意売却サイトで除外依頼頂いた".$req_ex_ninibaikyaku."件";
	}
	if ($ex_ninibaikyaku > 0) {
		$inv_tmp_ninibaikyaku .= "\n任意売却サイトで弊社で除外と判断した".$ex_ninibaikyaku."件";
	}
	$inv_meigihenkou = $all_call_meigihenkou + $all_mail_meigihenkou - $res_meigihenkou - $ex_meigihenkou - $req_ex_meigihenkou;
	if ($inv_meigihenkou > 0) {
		$inv_tmp_meigihenkou = "名義変更サイトで同一電話番号の電話・メール及び".$payments['4'][0]."秒未満電話の".$inv_meigihenkou."件";
	}
	if ($req_ex_meigihenkou > 0) {
		$inv_tmp_meigihenkou .= "\n名義変更サイトで除外依頼頂いた".$req_ex_meigihenkou."件";
	}
	if ($ex_meigihenkou > 0) {
		$inv_tmp_meigihenkou .= "\n名義変更サイトで弊社で除外と判断した".$ex_meigihenkou."件";
	}
	$inv_setsuritsu = $all_call_setsuritsu + $all_mail_setsuritsu - $res_setsuritsu - $ex_setsuritsu - $req_ex_setsuritsu;
	if ($inv_setsuritsu > 0) {
		$inv_tmp_setsuritsu = "会社設立サイトで同一電話番号の電話・メール及び".$payments['5'][0]."秒未満電話の".$inv_setsuritsu."件";
	}
	if ($req_ex_setsuritsu > 0) {
		$inv_tmp_setsuritsu .= "\n会社設立サイトで除外依頼頂いた".$req_ex_setsuritsu."件";
	}
	if ($ex_setsuritsu > 0) {
		$inv_tmp_setsuritsu .= "\n会社設立サイトで弊社で除外と判断した".$ex_setsuritsu."件";
	}
	$inv_keijijiken = $all_call_keijijiken + $all_mail_keijijiken - $res_keijijiken - $ex_keijijiken - $req_ex_keijijiken;
	if ($inv_keijijiken > 0){
		$inv_tmp_keijijiken = "刑事事件サイトで同一電話番号の電話・メール及び".$payments['6'][0]."秒未満電話の".$inv_keijijiken."件";
	}
	if ($req_ex_keijijiken > 0) {
		$inv_tmp_keijijiken .= "\n刑事事件サイトで除外依頼頂いた".$req_ex_keijijiken."件";
	}
	if ($ex_keijijiken > 0) {
		$inv_tmp_keijijiken .= "\n刑事事件サイトで弊社で除外と判断した".$ex_keijijiken."件";
	}
	$inv_rikon = $all_call_rikon + $all_mail_rikon - $res_rikon - $ex_rikon - $req_ex_rikon;
	if ($inv_rikon > 0) {
		$inv_tmp_rikon = "離婚問題サイトで同一電話番号の電話・メール及び".$payments['7'][0]."秒未満電話の".$inv_rikon."件";
	}
	if ($req_ex_rikon > 0) {
		$inv_tmp_rikon .= "\n離婚問題サイトで除外依頼頂いた".$req_ex_rikon."件";
	}
	if ($ex_rikon > 0) {
		$inv_tmp_rikon .= "\n離婚問題サイトで弊社で除外と判断した".$ex_rikon."件";
	}
	$inv_bgatakanen = $all_call_bgatakanen + $all_mail_bgatakanen - $res_bgatakanen - $ex_bgatakanen - $req_ex_bgatakanen;
	if ($inv_bgatakanen > 0) {
		$inv_tmp_bgatakanen = "Ｂ型肝炎サイトで同一電話番号の電話・メール及び".$payments['8'][0]."秒未満電話の".$inv_bgatakanen."件";
	}
	if ($req_ex_bgatakanen > 0) {
		$inv_tmp_bgatakanen .= "\nＢ型肝炎サイトで除外依頼頂いた".$req_ex_bgatakanen."件";
	}
	if ($ex_bgatakanen > 0) {
		$inv_tmp_bgatakanen .= "\nＢ型肝炎サイトで弊社で除外と判断した".$ex_bgatakanen."件";
	}
	$inv_hibouchuushou = $all_call_hibouchuushou + $all_mail_hibouchuushou - $res_hibouchuushou - $ex_hibouchuushou - $req_ex_hibouchuushou;
	if ($inv_hibouchuushou > 0) {
		$inv_tmp_hibouchuushou = "誹謗中傷サイトで同一電話番号の電話・メール及び".$payments['9'][0]."秒未満電話の".$inv_hibouchuushou."件";
	}
	if ($req_ex_hibouchuushou > 0) {
		$inv_tmp_hibouchuushou .= "\n誹謗中傷サイトで除外依頼頂いた".$req_ex_hibouchuushou."件";
	}
	if ($ex_hibouchuushou > 0) {
		$inv_tmp_hibouchuushou .= "\n誹謗中傷サイトで弊社で除外と判断した".$ex_hibouchuushou."件";
	}
	$inv_jikouenyou = $all_call_jikouenyou + $all_mail_jikouenyou - $res_jikouenyou - $ex_jikouenyou - $req_ex_jikouenyou;
	if ($inv_jikouenyou > 0) {
		$inv_tmp_jikouenyou = "時効援用サイトで同一電話番号の電話・メール及び".$payments['10'][0]."秒未満電話の".$inv_jikouenyou."件";
	}
	if ($req_ex_jikouenyou > 0) {
		$inv_tmp_jikouenyou .= "\n時効援用サイトで除外依頼頂いた".$req_ex_jikouenyou."件";
	}
	if ($ex_jikouenyou > 0) {
		$inv_tmp_jikouenyou .= "\n時効援用サイトで弊社で除外と判断した".$ex_jikouenyou."件";
	}
	$inv_roudou = $all_call_roudou + $all_mail_roudou - $res_roudou - $ex_roudou - $req_ex_roudou;
	if ($inv_roudou > 0) {
		$inv_tmp_roudou = "労働問題サイトで同一電話番号の電話・メール及び".$payments['11'][0]."秒未満電話の".$inv_roudou."件";
	}
	if ($req_ex_roudou > 0) {
		$inv_tmp_roudou .= "\n労働問題サイトで除外依頼頂いた".$req_ex_roudou."件";
	}
	if ($ex_roudou > 0) {
		$inv_tmp_roudou .= "\n労働問題サイトで弊社で除外と判断した".$ex_roudou."件";
	}
	$inv_youikuhi = $all_call_youikuhi + $all_mail_youikuhi - $res_youikuhi - $ex_youikuhi - $req_ex_youikuhi;
	if ($inv_youikuhi > 0) {
		$inv_tmp_youikuhi = "養育費回収サイトで同一電話番号の電話・メール及び".$payments['12'][0]."秒未満電話の".$inv_youikuhi."件";
	}
	if ($req_ex_youikuhi > 0) {
		$inv_tmp_youikuhi .= "\n養育費回収サイトで除外依頼頂いた".$req_ex_youikuhi."件";
	}
	if ($ex_youikuhi > 0) {
		$inv_tmp_youikuhi .= "\n養育費回収サイトで弊社で除外と判断した".$ex_youikuhi."件";
	}
	$inv_fudousan = $all_call_fudousan + $all_mail_fudousan - $res_fudousan - $ex_fudousan - $req_ex_fudousan;
	if ($inv_fudousan > 0) {
		$inv_tmp_fudousan = "不動産問題サイトで同一電話番号の電話・メール及び".$payments['13'][0]."秒未満電話の".$inv_fudousan."件";
	}
	if ($req_ex_fudousan > 0) {
		$inv_tmp_fudousan .= "\n不動産問題サイトで除外依頼頂いた".$req_ex_fudousan."件";
	}
	if ($ex_fudousan > 0) {
		$inv_tmp_fudousan .= "\n不動産問題サイトで弊社で除外と判断した".$ex_fudousan."件";
	}
	$inv_seinenkouken = $all_call_seinenkouken + $all_mail_seinenkouken - $res_seinenkouken - $ex_seinenkouken - $req_ex_seinenkouken;
	if ($inv_seinenkouken > 0) {
		$inv_tmp_seinenkouken = "成年後見サイトで同一電話番号の電話・メール及び".$payments['14'][0]."秒未満電話の".$inv_seinenkouken."件";
	}
	if ($req_ex_seinenkouken > 0) {
		$inv_tmp_seinenkouken .= "\n成年後見サイトで除外依頼頂いた".$req_ex_seinenkouken."件";
	}
	if ($ex_seinenkouken > 0) {
		$inv_tmp_seinenkouken .= "\n成年後見サイトで弊社で除外と判断した".$ex_seinenkouken."件";
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
	if(!empty($res_bgatakanen)) {
		$va_bgatakanen = "Ｂ型肝炎".$res_bgatakanen."件・";
	}
	if(!empty($res_hibouchuushou)) {
		$va_hibouchuushou = "誹謗中傷".$res_hibouchuushou."件・";
	}
	if(!empty($res_jikouenyou)) {
		$va_jikouenyou = "時効援用".$res_jikouenyou."件・";
	}
	if(!empty($res_roudou)) {
		$va_roudou = "労働問題".$res_roudou."件・";
	}
	if(!empty($res_youikuhi)) {
		$va_youikuhi = "養育費回収".$res_youikuhi."件・";
	}
	if(!empty($res_fudousan)) {
		$va_fudousan = "不動産問題".$res_fudousan."件・";
	}
	if(!empty($res_seinenkouken)) {
		$va_seinenkouken = "成年後見".$res_seinenkouken."件・";
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
	if (!empty($bgatakanen_all_tmp)) {
		$all_tmp = $all_tmp."
".$bgatakanen_all_tmp;
	}
	if (!empty($hibouchuushou_all_tmp)) {
		$all_tmp = $all_tmp."
".$hibouchuushou_all_tmp;
	}
	if (!empty($jikouenyou_all_tmp)) {
		$all_tmp = $all_tmp."
".$jikouenyou_all_tmp;
	}
	if (!empty($roudou_all_tmp)) {
		$all_tmp = $all_tmp."
".$roudou_all_tmp;
	}
	if (!empty($youikuhi_all_tmp)) {
		$all_tmp = $all_tmp."
".$youikuhi_all_tmp;
	}
	if (!empty($fudousan_all_tmp)) {
		$all_tmp = $all_tmp."
".$fudousan_all_tmp;
	}
	if (!empty($seinenkouken_all_tmp)) {
		$all_tmp = $all_tmp."
".$seinenkouken_all_tmp;
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
	if (!empty($inv_tmp_bgatakanen)) {
		$inv_tmp = $inv_tmp."
".$inv_tmp_bgatakanen;
	}
	if (!empty($inv_tmp_hibouchuushou)) {
		$inv_tmp = $inv_tmp."
".$inv_tmp_hibouchuushou;
	}
	if (!empty($inv_tmp_jikouenyou)) {
		$inv_tmp = $inv_tmp."
".$inv_tmp_jikouenyou;
	}
	if (!empty($inv_tmp_roudou)) {
		$inv_tmp = $inv_tmp."
".$inv_tmp_roudou;
	}
	if (!empty($inv_tmp_youikuhi)) {
		$inv_tmp = $inv_tmp."
".$inv_tmp_youikuhi;
	}
	if (!empty($inv_tmp_fudousan)) {
		$inv_tmp = $inv_tmp."
".$inv_tmp_fudousan;
	}
	if (!empty($inv_tmp_seinenkouken)) {
		$inv_tmp = $inv_tmp."
".$inv_tmp_seinenkouken;
	}
	if (!empty($inv_tmp)) {
		$inv_tmp = $inv_tmp."
"."を差し引いて";
	}

	#valid_tmp
	$va_tmp = $va_shakkin.$va_souzoku.$va_koutsujiko.$va_ninibaikyaku.$va_meigihenkou.$va_setsuritsu.$va_keijijiken.$va_rikon.$va_bgatakanen.$va_hibouchuushou.$va_jikouenyou.$va_roudou.$va_youikuhi.$va_fudousan.$va_seinenkouken;
	$va_tmp = rtrim($va_tmp,'・');


	//案件に対する支払い種別の取得
	$sg_pm_arr = array(
		'0' => null,
		'1' => null,
		'2' => null,
		'3' => null,
		'4' => null,
		'5' => null,
		'6' => null,
		'7' => null,
		'8' => null,
		'9' => null,
		'10' => null,
		'11' => null,
		'12' => null,
		'13' => null,
		'14' => null,
	);
	$tofixed_costs = null;
	$flatrate_costs = null;
	$lastdt = get_last_datetime_of_month($year."-".$month);
	foreach($arr_ad_group_id as $row) {
		$ad_group_id = $row['ad_group_id'];
		$pm_arr = get_payment_methods($ad_group_id, $lastdt);
		foreach ($pm_arr as $pm) {
			if ($sg_pm_arr[$pm['site_group']] == null) {
				$sg_pm_arr[$pm['site_group']] = $pm['payment_method_id'];
				if ($tofixed_costs == null && $pm['payment_method_id'] == 3) {
					//固定費化型
					$tofixed_costs = get_tofixed_costs_for_bill($ad_group_id, $lastdt);

				}
				else if ($flatrate_costs == null && $pm['payment_method_id'] == 4) {
					//定額制
					$flatrate_costs = get_flatrate_costs_for_bill($ad_group_id, $lastdt);
				}
			}
		}
	}

	###################################
	##ここからがExcelへの記入に関するコード##
	###################################
	$sheet = $spreadsheet->getSheet(0);
	#monthを表示用数字に変更
	$month = sprintf('%01d', $month);
	#郵便番号
	$sheet->setCellValueByColumnAndRow(1, 2, "〒".$bill_payer_data['postal_code']);
	#住所
	$sheet->setCellValueByColumnAndRow(1, 3, $bill_payer_data['address_1']);
	$sheet->setCellValueByColumnAndRow(1, 4, " ".$bill_payer_data['address_2']);
	#貴社名/御氏名
	$sheet->setCellValueByColumnAndRow(1, 6, $bill_payer_data['bill_payer_name']);
	$sheet->setCellValueByColumnAndRow(1, 7, $c_name."　様");
	#行数の定義
	$i = 20;
	$sheet->setCellValueByColumnAndRow(1, $i, "1");
	#固定費化
	if ($tofixed_costs != null) {
		foreach ($tofixed_costs as $costs) {
			#月
			$sheet->setCellValueByColumnAndRow(2, $i, "$month");	
			#商品名
			$sheet->setCellValueByColumnAndRow(3, $i, "月サイト掲載料金(".$costs['site_group_names'].")");
			#数量
			$sheet->setCellValueByColumnAndRow(5, $i, 1);
			#単価
			$sheet->setCellValueByColumnAndRow(6, $i, $costs['flatrate_price']);
			$i = $i + 1;
		}
	}
	#定額制
	if ($flatrate_costs != null) {
		$sheet->setCellValueByColumnAndRow(5, 19, "掲載数");	
		foreach ($flatrate_costs as $costs) {
			#月
			$sheet->setCellValueByColumnAndRow(2, $i, "$month");	
			#商品名
			$sheet->setCellValueByColumnAndRow(3, $i, "月サイト掲載料金(".$costs['site_group_names'].")");
			#数量
			$sheet->setCellValueByColumnAndRow(5, $i, $costs['flatrate_count']);
			#単価
			$sheet->setCellValueByColumnAndRow(6, $i, FLATRATE_PRICE);
			$i = $i + 1;
		}
	}
	#借金問題
	if ($sg_pm_arr['0'] != null && $sg_pm_arr['0'] != '3' && $sg_pm_arr['0'] != '4' &&
			($shakkin_call > 0 || $shakkin_mail > 0)) {
		#月
		$sheet->setCellValueByColumnAndRow(2, $i, "$month");	
		#商品名
		$sheet->setCellValueByColumnAndRow(3, $i, "月掲載料金(借金問題)");
		#数量
		$sheet->setCellValueByColumnAndRow(5, $i, $shakkin_call + $shakkin_mail);
		#単価
		$sheet->setCellValueByColumnAndRow(6, $i, $payments['0'][1]);
		#合計金額
		$sum = ($shakkin_call + $shakkin_mail) * $payments['0'][1];
		$i = $i + 1;
	}
	#相続
	if ($sg_pm_arr['1'] != null && $sg_pm_arr['1'] != '3' && $sg_pm_arr['1'] != '4' &&
			($souzoku_call > 0 || $souzoku_mail > 0)) {
		#月
		$sheet->setCellValueByColumnAndRow(2, $i, "$month");	
		#商品名
		$sheet->setCellValueByColumnAndRow(3, $i, "月掲載料金(相続)");
		#数量
		$sheet->setCellValueByColumnAndRow(5, $i, $souzoku_call + $souzoku_mail);
		#単価
		$sheet->setCellValueByColumnAndRow(6, $i, $payments['1'][1]);
		#合計金額
		$sum = ($souzoku_call + $souzoku_mail) * $payments['1'][1];
		$i = $i + 1;
	}
	#交通事故
	if ($sg_pm_arr['2'] != null && $sg_pm_arr['2'] != '3' && $sg_pm_arr['2'] != '4' &&
			($koutsujiko_call > 0 || $koutsujiko_mail > 0)) {
		#月
		$sheet->setCellValueByColumnAndRow(2, $i, "$month");	
		#商品名
		$sheet->setCellValueByColumnAndRow(3, $i, "月掲載料金(交通事故)");
		#数量
		$sheet->setCellValueByColumnAndRow(5, $i, $koutsujiko_call + $koutsujiko_mail);
		#単価
		$sheet->setCellValueByColumnAndRow(6, $i, $payments['2'][1]);
		#合計金額
		$sum = ($koutsujiko_call + $koutsujiko_mail) * $payments['2'][1];
		$i = $i + 1;
	}
	#任意売却
	if ($sg_pm_arr['3'] != null && $sg_pm_arr['3'] != '3' && $sg_pm_arr['3'] != '4' &&
			($ninibaikyaku_call > 0 || $ninibaikyaku_mail > 0)) {
		#月
		$sheet->setCellValueByColumnAndRow(2, $i, "$month");	
		#商品名
		$sheet->setCellValueByColumnAndRow(3, $i, "月掲載料金(任意売却)");
		#数量
		$sheet->setCellValueByColumnAndRow(5, $i, $ninibaikyaku_call+$ninibaikyaku_mail);
		#単価
		$sheet->setCellValueByColumnAndRow(6, $i, $payments['3'][1]);
		#合計金額
		$sum =($ninibaikyaku_call + $ninibaikyaku_mail) * $payments['3'][1];
		$i = $i + 1;
	}
	#名義変更
	if ($sg_pm_arr['4'] != null && $sg_pm_arr['4'] != '3' && $sg_pm_arr['4'] != '4' &&
			($meigihenkou_call > 0 || $meigihenkou_mail > 0)) {
		#月
		$sheet->setCellValueByColumnAndRow(2, $i, "$month");	
		#商品名
		$sheet->setCellValueByColumnAndRow(3, $i, "月掲載料金(名義変更)");
		#数量
		$sheet->setCellValueByColumnAndRow(5, $i, $meigihenkou_call + $meigihenkou_mail);
		#単価
		$sheet->setCellValueByColumnAndRow(6, $i, $payments['4'][1]);
		#合計金額
		$sum = ($meigihenkou_call + $meigihenkou_mail) * $payments['4'][1];
		$i = $i + 1;
	}
	#会社設立
	if ($sg_pm_arr['5'] != null && $sg_pm_arr['5'] != '3' && $sg_pm_arr['5'] != '4' &&
			($setsuritsu_call > 0 || $setsuritsu_mail > 0)) {
		#月
		$sheet->setCellValueByColumnAndRow(2, $i, "$month");	
		#商品名
		$sheet->setCellValueByColumnAndRow(3, $i, "月掲載料金(会社設立)");
		#数量
		$sheet->setCellValueByColumnAndRow(5, $i, $setsuritsu_call+$setsuritsu_mail);
		#単価
		$sheet->setCellValueByColumnAndRow(6, $i, $payments['5'][1]);
		#合計金額
		$sum =($setsuritsu_call + $setsuritsu_mail) * $payments['5'][1];
		$i = $i + 1;
	}
	#刑事事件
	if ($sg_pm_arr['6'] != null && $sg_pm_arr['6'] != '3' && $sg_pm_arr['6'] != '4' &&
			($keijijiken_call > 0 || $keijijiken_mail > 0)) {
		#月
		$sheet->setCellValueByColumnAndRow(2, $i, "$month");	
		#商品名
		$sheet->setCellValueByColumnAndRow(3, $i, "月掲載料金(刑事事件)");
		#数量
		$sheet->setCellValueByColumnAndRow(5, $i, $keijijiken_call+$keijijiken_mail);
		#単価
		$sheet->setCellValueByColumnAndRow(6, $i, $payments['6'][1]);
		#合計金額
		$sum = ($keijijiken_call + $keijijiken_mail) * $payments['6'][1];
		$i = $i + 1;
	}
	#離婚
	if ($sg_pm_arr['7'] != null && $sg_pm_arr['7'] != '3' && $sg_pm_arr['7'] != '4' &&
			($rikon_call > 0 || $rikon_mail > 0)) {
		#月
		$sheet->setCellValueByColumnAndRow(2, $i, "$month");	
		#商品名
		$sheet->setCellValueByColumnAndRow(3, $i, "月掲載料金(離婚問題)");
		#数量
		$sheet->setCellValueByColumnAndRow(5, $i, $rikon_call+$rikon_mail);
		#単価
		$sheet->setCellValueByColumnAndRow(6, $i, $payments['7'][1]);
		#合計金額
		$sum =($rikon_call + $rikon_mail) * $payments['7'][1];
		$i = $i + 1;
	}
	#Ｂ型肝炎
	if ($sg_pm_arr['8'] != null && $sg_pm_arr['8'] != '3' && $sg_pm_arr['8'] != '4' &&
			($bgatakanen_call > 0 OR $bgatakanen_mail > 0)) {
		#月
		$sheet->setCellValueByColumnAndRow(2, $i, "$month");	
		#商品名
		$sheet->setCellValueByColumnAndRow(3, $i, "月掲載料金(Ｂ型肝炎)");
		#数量
		$sheet->setCellValueByColumnAndRow(5, $i, $bgatakanen_call+$bgatakanen_mail);
		#単価
		$sheet->setCellValueByColumnAndRow(6, $i, $payments['8'][1]);
		#合計金額
		$sum =($bgatakanen_call + $bgatakanen_mail) * $payments['8'][1];
		$i = $i + 1;
	}
	#誹謗中傷
	if ($sg_pm_arr['9'] != null && $sg_pm_arr['9'] != '3' && $sg_pm_arr['9'] != '4' &&
			($hibouchuushou_call > 0 OR $hibouchuushou_mail > 0)) {
		#月
		$sheet->setCellValueByColumnAndRow(2, $i, "$month");	
		#商品名
		$sheet->setCellValueByColumnAndRow(3, $i, "月掲載料金(誹謗中傷)");
		#数量
		$sheet->setCellValueByColumnAndRow(5, $i, $hibouchuushou_call+$hibouchuushou_mail);
		#単価
		$sheet->setCellValueByColumnAndRow(6, $i, $payments['9'][1]);
		#合計金額
		$sum =($hibouchuushou_call + $hibouchuushou_mail) * $payments['9'][1];
		$i = $i + 1;
	}
	#時効援用
	if ($sg_pm_arr['10'] != null && $sg_pm_arr['10'] != '3' && $sg_pm_arr['10'] != '4' &&
			($jikouenyou_call > 0 OR $jikouenyou_mail > 0)) {
		#月
		$sheet->setCellValueByColumnAndRow(2, $i, "$month");	
		#商品名
		$sheet->setCellValueByColumnAndRow(3, $i, "月掲載料金(時効援用)");
		#数量
		$sheet->setCellValueByColumnAndRow(5, $i, $jikouenyou_call+$jikouenyou_mail);
		#単価
		$sheet->setCellValueByColumnAndRow(6, $i, $payments['10'][1]);
		#合計金額
		$sum =($jikouenyou_call + $jikouenyou_mail) * $payments['10'][1];
		$i = $i + 1;
	}
	#労働問題
	if ($sg_pm_arr['11'] != null && $sg_pm_arr['11'] != '3' && $sg_pm_arr['11'] != '4' &&
			($roudou_call > 0 OR $roudou_mail > 0)) {
		#月
		$sheet->setCellValueByColumnAndRow(2, $i, "$month");	
		#商品名
		$sheet->setCellValueByColumnAndRow(3, $i, "月掲載料金(労働問題)");
		#数量
		$sheet->setCellValueByColumnAndRow(5, $i, $roudou_call+$roudou_mail);
		#単価
		$sheet->setCellValueByColumnAndRow(6, $i, $payments['11'][1]);
		#合計金額
		$sum =($roudou_call + $roudou_mail) * $payments['11'][1];
		$i = $i + 1;
	}
	#養育費回収
	if ($sg_pm_arr['12'] != null && $sg_pm_arr['12'] != '3' && $sg_pm_arr['12'] != '4' &&
			($youikuhi_call > 0 OR $youikuhi_mail > 0)) {
		#月
		$sheet->setCellValueByColumnAndRow(2, $i, "$month");	
		#商品名
		$sheet->setCellValueByColumnAndRow(3, $i, "月掲載料金(養育費回収)");
		#数量
		$sheet->setCellValueByColumnAndRow(5, $i, $youikuhi_call+$youikuhi_mail);
		#単価
		$sheet->setCellValueByColumnAndRow(6, $i, $payments['12'][1]);
		#合計金額
		$sum =($youikuhi_call + $youikuhi_mail) * $payments['12'][1];
		$i = $i + 1;
	}
	#不動産問題
	if ($sg_pm_arr['13'] != null && $sg_pm_arr['13'] != '3' && $sg_pm_arr['13'] != '4' &&
			($fudousan_call > 0 OR $fudousan_mail > 0)) {
		#月
		$sheet->setCellValueByColumnAndRow(2, $i, "$month");	
		#商品名
		$sheet->setCellValueByColumnAndRow(3, $i, "月掲載料金(養育費回収)");
		#数量
		$sheet->setCellValueByColumnAndRow(5, $i, $fudousan_call+$fudousan_mail);
		#単価
		$sheet->setCellValueByColumnAndRow(6, $i, $payments['13'][1]);
		#合計金額
		$sum =($fudousan_call + $fudousan_mail) * $payments['13'][1];
		$i = $i + 1;
	}
	#成年後見
	if ($sg_pm_arr['14'] != null && $sg_pm_arr['14'] != '3' && $sg_pm_arr['14'] != '4' &&
			($seinenkouken_call > 0 OR $seinenkouken_mail > 0)) {
		#月
		$sheet->setCellValueByColumnAndRow(2, $i, "$month");	
		#商品名
		$sheet->setCellValueByColumnAndRow(3, $i, "月掲載料金(養育費回収)");
		#数量
		$sheet->setCellValueByColumnAndRow(5, $i, $seinenkouken_call+$seinenkouken_mail);
		#単価
		$sheet->setCellValueByColumnAndRow(6, $i, $payments['14'][1]);
		#合計金額
		$sum =($seinenkouken_call + $seinenkouken_mail) * $payments['14'][1];
		$i = $i + 1;
	}

	#CallChargeとフリーダイヤルの数の取得
	#TODO:月次処理に戻すかも。
	$call_charge = null;
	$count_freedial = null;
	foreach($arr_ad_group_id as $row) {
		$ad_group_id = $row['ad_group_id'];
		//call_dataの取得
		//call_chargeの取得
		$stmt = $pdo_cdr->query("
			SELECT
				tel_to
			FROM
				call_data_view
			WHERE
				ad_group_id = $ad_group_id AND
				DATE_FORMAT(date_from,'%Y%m') = $year_month
			GROUP BY
				tel_to
		");
		$arr_call_num =$stmt->fetchAll(PDO::FETCH_ASSOC);
		foreach ($arr_call_num as $r) {
			$call_num = $r['tel_to'];
			$stmt = $pdo_cdr->query("
				SELECT
					call_charge
				FROM
					bill
				WHERE
					tel_to = $call_num AND
					year = $year AND
					month = $month
			");
			$arr_call_charge  = $stmt->fetchAll(PDO::FETCH_ASSOC);
			foreach ($arr_call_charge as $row) {
				$call_charge += $row['call_charge'];
			}
		}
		//count_freedialの取得
		$stmt = $pdo_cdr->query("
			SELECT DISTINCT
				b.tel_to as tel
			FROM
				cdr.bill b,
				(
					SELECT
						tel_to,
						office_id,
						ad_group_id
					FROM
						cdr.call_data_view
					WHERE
						DATE_FORMAT(date_from, '%Y%m') = $year_month
					ORDER BY
						date_from DESC
				) c
			WHERE
				b.year = $year AND
				b.month = $month AND
				b.tel_to = c.tel_to AND
				c.ad_group_id = $ad_group_id
		");
		$arr_adsip_conf = $stmt->fetchAll(PDO::FETCH_ASSOC);
		foreach ($arr_adsip_conf as $row) {
			$tel_num = $row['tel'];
			$freedial = substr($tel_num, 0, 4);
			if($freedial == "0120") {
				$count_freedial++;
			}
		}
	}

	$i = $i + 1;
	$sheet->setCellValueByColumnAndRow(1, $i, "2");
	#月
	$sheet->setCellValueByColumnAndRow(2, $i, "$month");	
	#フリーダイヤル料金記入
	$sheet->setCellValueByColumnAndRow(3, $i, "月フリーダイヤル通話料金");
	#単価
	$sheet->setCellValueByColumnAndRow(6, $i, $call_charge);
	#合計金額
	$sheet->setCellValueByColumnAndRow(7, $i, $call_charge);
	if ($count_freedial != null) {
		$i = $i + 1;
		#月
		$sheet->setCellValueByColumnAndRow(2, $i, "$month");
		#発番費用
		$sheet->setCellValueByColumnAndRow(3, $i, "月フリーダイヤル費用");
		#数量
		$sheet->setCellValueByColumnAndRow(5, $i, $count_freedial);
		#単価
		$sheet->setCellValueByColumnAndRow(6, $i, 1000);//変更可能である可能性あり
		#合計金額
		$sum = ($count_freedial) * 1000;
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
	$sheet = $spreadsheet->getSheet(1);
	$sheet->setCellValueByColumnAndRow(1, 1, "
【専門家検索ドットコム】請求書（".$year."年".$month."月分）

".$c_name."様

いつもお世話になっております。
ウィズパッションの坂田です。

".$month."月分の請求書を添付させていただきます。

".$month."月は、".$all_tmp."
が発生致しました。".$inv_tmp."
計".$all_sum."件(".$va_tmp.")を請求させて頂きます。

ご不明な点があればなんなりとご連絡ください。
今後ともよろしくお願い致します。"
	);

	// 事務所グループ毎にシートを作る
	// 請求先全体シート
	get_all_billing_contents(
		$spreadsheet,
		2,
		$arr_ad_group_id,
		$year,
		$month,
		$year_month
	);
	get_each_ad_details_data(
		$spreadsheet,
		3,
		$arr_ad_group_id,
		$year,
		$month,
		$year_month,
		$bill_payer_name
	);
	$sn = 4;
	foreach ($arr_ad_group_id as $row) {
		$ad_group_id = $row['ad_group_id'];
		$arr = array();
		array_push($arr, $row);
		get_each_ad_details_data(
			$spreadsheet,
			$sn,
			$arr,
			$year,
			$month,
			$year_month,
			"G_".$row['group_name']
		);
		$sn++;
	}

	#事務所毎でのsheetの名前
	$xls_name = "請求書（".$bill_payer_name.$year."年".$month."月分）";
	$outfile = $xls_name.".xlsx";
	$writer = new XlsxWriter($spreadsheet);
	if ($filepath != null) {
		$outfile = $filepath."/".$outfile;
		$writer->save($outfile);
	}
	else {
		//ダウンロード用
		//MIMEタイプ：https://technet.microsoft.com/ja-jp/ee309278.aspx
		header("Content-Description: File Transfer");
		header('Content-Disposition: attachment; filename="'. $outfile. '"');
		header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
		header('Content-Transfer-Encoding: binary');
		header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
		header('Expires: 0');
		ob_end_clean(); //バッファ消去
		$writer->save('php://output');
	}
}
#end_of_function/get_each_ad_data

function get_all_billing_contents(
	$spreadsheet,
	$sheet_num,
	$arr_ad_group_id,
	$year,
	$month,
	$year_month
) {
	global $pdo_request,$pdo_cdr,$pdo_wordpress;
	########################
	###CRMシートに載せる内容###
	########################
	$sheet = $spreadsheet->getSheet($sheet_num);
	$sheet->setCellValueByColumnAndRow(1, 1, $year."年".$month."月");
	#電話件数の出力
	$sheet->setCellValueByColumnAndRow(3, 1, "電話件数");
	#メール件数の出力
	$sheet->setCellValueByColumnAndRow(4, 1, "メール件数");
	#数量の出力
	$sheet->setCellValueByColumnAndRow(5, 1, "数量");
	#単価の出力
	$sheet->setCellValueByColumnAndRow(6, 1, "単価(税抜き)");
	#合計金額の出力
	$sheet->setCellValueByColumnAndRow(7, 1, "合計金額");
	#行数を変数に指定
	$i = 2;

	//通話料の取得
	foreach ($arr_ad_group_id as $row) {
		$ad_group_id = $row['ad_group_id'];
		$stmt = $pdo_cdr->query("
			SELECT
				SUM(a.call_charge) as call_charge
			FROM (
				SELECT
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
					v.tel_to
			) a
		");
		$ret = $stmt->fetch(PDO::FETCH_ASSOC);
		$call_charge = $ret['call_charge'];

		//0120番号発行数の取得
		$stmt = $pdo_cdr->query("
			SELECT
				count(a.tel_to) free_dial_count
			FROM
			(
				SELECT DISTINCT
					b.tel_to
				FROM
					cdr.bill b,
					(
						SELECT
							tel_to,
							office_id,
							ad_group_id
						FROM
							cdr.call_data_view
						WHERE
							DATE_FORMAT(date_from, '%Y%m') = $year_month AND
							tel_to like '0120%'
						ORDER BY
							date_from DESC
					) c
				WHERE
					b.tel_to = c.tel_to AND
					b.year = $year AND
					b.month = $month AND
					c.ad_group_id = $ad_group_id
			) a
		");
		$ret = $stmt->fetch(PDO::FETCH_ASSOC);
		$free_dial_count = $ret['free_dial_count'];

		//単価情報の収集
		$first_day = $year_month."01000000";
		$stmt = $pdo_cdr->query("
			SELECT DISTINCT
				gpm.ad_group_id,
				gpm.site_group,
				gpm.payment_method_id,
				pm.method,
				gpm.charge_seconds,
				gpm.unit_price,
				sg.site_group_name
			FROM
				cdr.payment_method pm,
				cdr.office_group_payment_method gpm,
				wordpress.ss_site_group sg
			WHERE
				pm.id = gpm.payment_method_id AND
				gpm.site_group = sg.site_group AND
				(gpm.from_date BETWEEN CAST($first_day AS DATETIME) AND CAST(CONCAT(LAST_DAY($first_day), ' 23:59:59') AS DATETIME) OR
				 gpm.to_date BETWEEN CAST($first_day AS DATETIME) AND CAST(CONCAT(LAST_DAY($first_day), ' 23:59:59') AS DATETIME) OR
				 gpm.to_date > CAST($first_day AS DATETIME)) AND
				ad_group_id = $ad_group_id
			ORDER BY
				gpm.site_group,
				gpm.from_date
		");
		$res_arr = $stmt->fetchAll(PDO::FETCH_ASSOC);
		$adg_counts = array();
		foreach ($res_arr as $gpm) {
			$sgp = $gpm['site_group']."-".$gpm['unit_price'];
			$adg_counts[$sgp] = array();
			$adg_counts[$sgp]['site_group_name'] = $gpm['site_group_name'];
			$adg_counts[$sgp]['unit_price'] = $gpm['unit_price'];
			$adg_counts[$sgp]['payment_method_id'] = $gpm['payment_method_id'];
			$adg_counts[$sgp]['payment_method'] = $gpm['method'];
			$adg_counts[$sgp]['tel_count'] = 0;
			$adg_counts[$sgp]['mail_count'] = 0;
		}
		

		//有効電話数の取得
		$valid_call_data = get_monthly_group_calls_and_price($year_month, $ad_group_id);
		foreach ($valid_call_data as $vd) {
			if ($vd['site_group'] != null && strpos($vd['unit_price'], ',') === false) {
				$sgp = $vd['site_group']."-".$vd['unit_price'];
				$adg_counts[$sgp]['tel_count'] += intval($vd['tel_count']);
			}
		}
		//有効メールの取得
		$valid_mail_data = get_monthly_group_mails_and_price($year_month, $ad_group_id);
		foreach ($valid_mail_data as $vd) {
			if ($vd['site_group'] != null && strpos($vd['unit_price'], ',') === false) {
				$sgp = $vd['site_group']."-".$vd['unit_price'];
				$adg_counts[$sgp]['mail_count'] += intval($vd['mail_count']);
			}
		}

		//シートに記述
		$sheet->setCellValueByColumnAndRow(1, $i, $row['group_name']);
		foreach ($adg_counts as $cnt) {
			if ($cnt['payment_method_id'] == 0) {
				$tel_count = intval($cnt['tel_count']);
				$mail_count = intval($cnt['mail_count']);
				$total_count = $tel_count + $mail_count;
				$unit_price = intval($cnt['unit_price']);
				$sheet->setCellValueByColumnAndRow(2, $i, $cnt['site_group_name']);
				$sheet->setCellValueByColumnAndRow(3, $i, $tel_count);
				$sheet->setCellValueByColumnAndRow(4, $i, $mail_count);
				$sheet->setCellValueByColumnAndRow(5, $i, $total_count);
				$sheet->setCellValueByColumnAndRow(6, $i, $unit_price);
				$sheet->setCellValueByColumnAndRow(7, $i, $total_count * $unit_price);
			}
			else {
				$sheet->setCellValueByColumnAndRow(2, $i, $cnt['site_group_name']);
				$sheet->setCellValueByColumnAndRow(3, $i, "");
				$sheet->setCellValueByColumnAndRow(4, $i, "");
				$sheet->setCellValueByColumnAndRow(5, $i, "");
				$sheet->setCellValueByColumnAndRow(6, $i, "");
				$sheet->setCellValueByColumnAndRow(7, $i, 0);
			}
			$i++;
		}
		if (intval($free_dial_count) > 0) {
			$sheet->setCellValueByColumnAndRow(2, $i, "フリーダイヤル発番料");
			$sheet->setCellValueByColumnAndRow(5, $i, intval($free_dial_count));
			$sheet->setCellValueByColumnAndRow(6, $i, 1000);
			$sheet->setCellValueByColumnAndRow(7, $i, intval($free_dial_count) * 1000);
			$i++;
		}
		$sheet->setCellValueByColumnAndRow(2, $i, "フリーダイヤル通話料");
		$sheet->setCellValueByColumnAndRow(7, $i, intval($call_charge));
		$i+=2;
	}
	$sheet->setCellValueByColumnAndRow(6, $i, "税抜き計");
	$sheet->setCellValueByColumnAndRow(7, $i, "=SUM(G2:G".($i-2).")");
	$i++;
	$sheet->setCellValueByColumnAndRow(6, $i, "消費税");
	$sheet->setCellValueByColumnAndRow(7, $i, "=ROUNDDOWN(G".($i-1)."*0.1,0)");
	$i++;
	$sheet->setCellValueByColumnAndRow(6, $i, "合計額 ");
	$sheet->setCellValueByColumnAndRow(7, $i, "=G".($i-2)."+G".($i-1));
	$i++;

	$sheet->setTitle("請求金額内訳");

}

function get_each_ad_details_data(
	$spreadsheet,
	$sheet_num,
	$arr_ad_group_id,
	$year,
	$month,
	$year_month,
	$sheet_name
) {
	global $pdo_request,$pdo_cdr,$pdo_wordpress;
	########################
	###CRMシートに載せる内容###
	########################
	$sheet = $spreadsheet->getSheet($sheet_num);
	$sheet->setCellValueByColumnAndRow(1, 1, $month."月");
	#全体コール数の出力
	$sheet->setCellValueByColumnAndRow(2, 2, "発生コール");
	#参考コール数の出力
	$sheet->setCellValueByColumnAndRow(3, 2, "参考コール");
	#有効コール数の出力
	$sheet->setCellValueByColumnAndRow(4, 2, "有効コール");
	#発番電話番号の出力
	$sheet->setCellValueByColumnAndRow(5, 2, "発番電話番号");
	#請求通話料金の出力
	$sheet->setCellValueByColumnAndRow(6, 2, "請求通話料金");
	#行数を変数に指定
	$i = 3;
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
		foreach ($arr_each_tel_to as $row2) {
			$ad_id = $row2['advertiser_id'];
			$tel_to = $row2['tel_to'];
			$media_id = $row2['media_id'];
			$media_type = $row2['media_type'];
			$site_group = $row2['site_group'];
			$call_charge = $row2['call_charge'];
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
				if (!isset($adg_call[$ad_group_id][$ad_id][$site_group]['tel_to'])) {
					$adg_call[$ad_group_id][$ad_id][$site_group]['tel_to'] = $tel_to;
					$adg_call[$ad_group_id][$ad_id][$site_group]['call_charge'] = $call_charge;
				}
				else {
					$adg_call[$ad_group_id][$ad_id][$site_group]['tel_to'] .= ", ".$tel_to;
					$adg_call[$ad_group_id][$ad_id][$site_group]['call_charge'] .= ", ".$call_charge;
				}

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

		$all_call_data = get_monthly_total_calls($year_month, CALL_TYPE_ALL, $ad_group_id);
		$sample_call_data = get_monthly_total_calls($year_month, CALL_TYPE_SAMPLE, $ad_group_id);
		$valid_call_data = get_monthly_total_calls($year_month, CALL_TYPE_VALID, $ad_group_id);
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
				$sheet->setCellValueByColumnAndRow(1, $i, "全事務所合計");
			}
			else {
				$sheet->setCellValueByColumnAndRow(1, $i, "案件種別：$sg_key 合計");
			}
			$sheet->setCellValueByColumnAndRow(2, $i, $bp_call[$sg_key][CALL_TYPE_ALL]);
			$sheet->setCellValueByColumnAndRow(3, $i, $bp_call[$sg_key][CALL_TYPE_SAMPLE]);
			$sheet->setCellValueByColumnAndRow(4, $i, $bp_call[$sg_key][CALL_TYPE_VALID]);
			$sheet->setCellValueByColumnAndRow(6, $i, $bp_call[$sg_key]['call_charge']);
			$i++;
		}
	}
	//グループ別合計
	if (count($adg_call)) {
		foreach ($arr_ad_group_id as $row) {
			$ad_group_id = $row['ad_group_id'];
			if (!array_key_exists($ad_group_id, $adg_call)) {
				continue;
			}
			foreach (array_keys($adg_call[$ad_group_id]) as $ad_key) {
				if ($ad_key === 'total') {
					$sheet->setCellValueByColumnAndRow(1, $i, "グループID：$ad_group_id 合計");
					$sheet->setCellValueByColumnAndRow(2, $i, $adg_call[$ad_group_id][$ad_key][CALL_TYPE_ALL]);
					$sheet->setCellValueByColumnAndRow(3, $i, $adg_call[$ad_group_id][$ad_key][CALL_TYPE_SAMPLE]);
					$sheet->setCellValueByColumnAndRow(4, $i, $adg_call[$ad_group_id][$ad_key][CALL_TYPE_VALID]);
					$sheet->setCellValueByColumnAndRow(6, $i, $adg_call[$ad_group_id][$ad_key]['call_charge']);
					$i++;
				}
			}
		}
	}
	//グループ案件別合計
	if (count($sgp_call)) {
		foreach ($arr_ad_group_id as $row) {
			$ad_group_id = $row['ad_group_id'];
			if (!array_key_exists($ad_group_id, $sgp_call)) {
				continue;
			}
			foreach (array_keys($sgp_call[$ad_group_id]) as $sg_key) {
				$sheet->setCellValueByColumnAndRow(1, $i, "グループID：$ad_group_id 案件種別：$sg_key 合計");
				$sheet->setCellValueByColumnAndRow(2, $i, $sgp_call[$ad_group_id][$sg_key][CALL_TYPE_ALL]);
				$sheet->setCellValueByColumnAndRow(3, $i, $sgp_call[$ad_group_id][$sg_key][CALL_TYPE_SAMPLE]);
				$sheet->setCellValueByColumnAndRow(4, $i, $sgp_call[$ad_group_id][$sg_key][CALL_TYPE_VALID]);
				$sheet->setCellValueByColumnAndRow(6, $i, $sgp_call[$ad_group_id][$sg_key]['call_charge']);
				$i++;
			}
		}
	}
	//事務所別合計
	if (count($adg_call)) {
		foreach ($arr_ad_group_id as $row) {
			$ad_group_id = $row['ad_group_id'];
			if (!array_key_exists($ad_group_id, $adg_call)) {
				continue;
			}
			foreach (array_keys($adg_call[$ad_group_id]) as $ad_key) {
				if ($ad_key !== 'total') {
					foreach (array_keys($adg_call[$ad_group_id][$ad_key]) as $sg_key) {
						if ($sg_key === 'total') {
							$sheet->setCellValueByColumnAndRow(1, $i, "事務所ID：$ad_key 合計");
						}
						else {
							$sheet->setCellValueByColumnAndRow(1, $i, "事務所ID：$ad_key 案件種別：$sg_key 合計");
							if (isset($adg_call[$ad_group_id][$ad_key][$sg_key]['tel_to'])) {
								$sheet->setCellValueByColumnAndRow(5, $i, $adg_call[$ad_group_id][$ad_key][$sg_key]['tel_to']);
							}
						}
						$sheet->setCellValueByColumnAndRow(2, $i, $adg_call[$ad_group_id][$ad_key][$sg_key][CALL_TYPE_ALL]);
						$sheet->setCellValueByColumnAndRow(3, $i, $adg_call[$ad_group_id][$ad_key][$sg_key][CALL_TYPE_SAMPLE]);
						$sheet->setCellValueByColumnAndRow(4, $i, $adg_call[$ad_group_id][$ad_key][$sg_key][CALL_TYPE_VALID]);
						$sheet->setCellValueByColumnAndRow(6, $i, $adg_call[$ad_group_id][$ad_key][$sg_key]['call_charge']);
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
	$sheet->setCellValueByColumnAndRow(2, $i, "発生メール");
	#参考メール数の出力
	$sheet->setCellValueByColumnAndRow(3, $i, "参考メール");
	#参考メール数の出力
	$sheet->setCellValueByColumnAndRow(4, $i, "有効メール");

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
				$sheet->setCellValueByColumnAndRow(1, $i, "全事務所合計");
			}
			else {
				$sheet->setCellValueByColumnAndRow(1, $i, "案件種別：$sg_key 合計");
			}
			$sheet->setCellValueByColumnAndRow(2, $i, $bp_mail[$sg_key][MAIL_TYPE_ALL]);
			$sheet->setCellValueByColumnAndRow(3, $i, $bp_mail[$sg_key][MAIL_TYPE_SAMPLE]);
			$sheet->setCellValueByColumnAndRow(4, $i, $bp_mail[$sg_key][MAIL_TYPE_VALID]);
			$i++;
		}
	}
	//グループ別合計
	if (count($adg_mail)) {
		foreach ($arr_ad_group_id as $row) {
			$ad_group_id = $row['ad_group_id'];
			if (!array_key_exists($ad_group_id, $adg_mail)) {
				continue;
			}
			foreach (array_keys($adg_mail[$ad_group_id]) as $ad_key) {
				if ($ad_key === 'total') {
					$sheet->setCellValueByColumnAndRow(1, $i, "グループID：$ad_group_id 合計");
					$sheet->setCellValueByColumnAndRow(2, $i, $adg_mail[$ad_group_id][$ad_key][MAIL_TYPE_ALL]);
					$sheet->setCellValueByColumnAndRow(3, $i, $adg_mail[$ad_group_id][$ad_key][MAIL_TYPE_SAMPLE]);
					$sheet->setCellValueByColumnAndRow(4, $i, $adg_mail[$ad_group_id][$ad_key][MAIL_TYPE_VALID]);
					$i++;
				}
			}
		}
	}
	//グループ案件別合計
	if (count($sgp_mail)) {
		foreach ($arr_ad_group_id as $row) {
			$ad_group_id = $row['ad_group_id'];
			if (!array_key_exists($ad_group_id, $sgp_mail)) {
				continue;
			}
			foreach (array_keys($sgp_mail[$ad_group_id]) as $sg_key) {
				$sheet->setCellValueByColumnAndRow(1, $i, "グループID：$ad_group_id 案件種別：$sg_key 合計");
				$sheet->setCellValueByColumnAndRow(2, $i, $sgp_mail[$ad_group_id][$sg_key][MAIL_TYPE_ALL]);
				$sheet->setCellValueByColumnAndRow(3, $i, $sgp_mail[$ad_group_id][$sg_key][MAIL_TYPE_SAMPLE]);
				$sheet->setCellValueByColumnAndRow(4, $i, $sgp_mail[$ad_group_id][$sg_key][MAIL_TYPE_VALID]);
				$i++;
			}
		}
	}
	//事務所別合計
	if (count($adg_mail)) {
		foreach ($arr_ad_group_id as $row) {
			$ad_group_id = $row['ad_group_id'];
			if (!array_key_exists($ad_group_id, $adg_mail)) {
				continue;
			}
			foreach (array_keys($adg_mail[$ad_group_id]) as $ad_key) {
				if ($ad_key !== 'total') {
					foreach (array_keys($adg_mail[$ad_group_id][$ad_key]) as $sg_key) {
						if ($sg_key === 'total') {
							$sheet->setCellValueByColumnAndRow(1, $i, "事務所ID：$ad_key 合計");
						}
						else {
							$sheet->setCellValueByColumnAndRow(1, $i, "事務所ID：$ad_key 案件種別：$sg_key 合計");
						}
						$sheet->setCellValueByColumnAndRow(2, $i, $adg_mail[$ad_group_id][$ad_key][$sg_key][MAIL_TYPE_ALL]);
						$sheet->setCellValueByColumnAndRow(3, $i, $adg_mail[$ad_group_id][$ad_key][$sg_key][MAIL_TYPE_SAMPLE]);
						$sheet->setCellValueByColumnAndRow(4, $i, $adg_mail[$ad_group_id][$ad_key][$sg_key][MAIL_TYPE_VALID]);
						$i++;
					}
				}
			}
		}
	}
	//var_dump($bp_mail);
	//var_dump($adg_mail);
	//var_dump($sgp_mail);

	//案件別の単価表示
	if ($sheet_num >= 3) {
		$n = 2;
		$sheet->setCellValueByColumnAndRow(8, $n, "グループID");
		$sheet->setCellValueByColumnAndRow(9, $n, "サイト種別ID");
		$sheet->setCellValueByColumnAndRow(10, $n, "サイト種別");
		$sheet->setCellValueByColumnAndRow(11, $n, "支払い種別");
		$sheet->setCellValueByColumnAndRow(12, $n, "有効秒数");
		$sheet->setCellValueByColumnAndRow(13, $n, "料金");
		$n++;

		$ad_group_id = $arr_ad_group_id[0]['ad_group_id'];
		$stmt = $pdo_cdr->query("
			SELECT
				gpm.ad_group_id,
		    gpm.site_group,
				sg.site_group_name,
				pm.method,
				gpm.charge_seconds,
				gpm.unit_price
			FROM
				cdr.office_group_payment_method gpm,
				cdr.payment_method pm,
				wordpress.ss_site_group sg
			WHERE
				gpm.payment_method_id = pm.id AND
				gpm.site_group = sg.site_group AND
				gpm.ad_group_id = $ad_group_id AND
				CAST(CONCAT(LAST_DAY('". $year_month . "01'), ' 23:59:59') AS DATETIME) BETWEEN gpm.from_date AND gpm.to_date
		");
		$arr_call_data = $stmt->fetchAll(PDO::FETCH_ASSOC);
		foreach ($arr_call_data as $row) {
			$sheet->setCellValueByColumnAndRow(8, $n, $row['ad_group_id']);
			$sheet->setCellValueByColumnAndRow(9, $n, $row['site_group']);
			$sheet->setCellValueByColumnAndRow(10, $n, $row['site_group_name']);
			$sheet->setCellValueByColumnAndRow(11, $n, $row['method']);
			$sheet->setCellValueByColumnAndRow(12, $n, $row['charge_seconds']);
			$sheet->setCellValueByColumnAndRow(13, $n, $row['unit_price']);
			$n++;
		}
	}

	$i++;//一行開ける
	$sheet->setCellValueByColumnAndRow(1, $i, "通話データ");
	$i++;//一行開ける

	//有効秒数等のヘッダの表示
	$sheet->setCellValueByColumnAndRow(1, $i, "事務所ID");
	$sheet->setCellValueByColumnAndRow(2, $i, "事務所名");
	$sheet->setCellValueByColumnAndRow(4, $i, "サイト種別");
	$sheet->setCellValueByColumnAndRow(5, $i, "電話番号");
	$sheet->setCellValueByColumnAndRow(6, $i, "転送先番号");
	$sheet->setCellValueByColumnAndRow(7, $i, "通話開始日時");
	$sheet->setCellValueByColumnAndRow(8, $i, "通話終了日時");
	$sheet->setCellValueByColumnAndRow(9, $i, "通話秒数");
	$sheet->setCellValueByColumnAndRow(10, $i, "発信元番号");
	$sheet->setCellValueByColumnAndRow(11, $i, "通話状態");
	$sheet->setCellValueByColumnAndRow(12, $i, "有効無効(60秒)");
	$sheet->setCellValueByColumnAndRow(13, $i, "有効秒数");
	$sheet->setCellValueByColumnAndRow(14, $i, "有効無効");
	$sheet->setCellValueByColumnAndRow(15, $i, "換算値");
	$sheet->setCellValueByColumnAndRow(16, $i, "除外理由");

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
				pm.charge_seconds,
				(pm.unit_price / 10000) as unit
			FROM
				wordpress.ss_advertisers ad,
				cdr.call_data_view dv,
				cdr.office_group_payment_method pm
			WHERE
				dv.advertiser_id = ad.ID AND
				dv.ad_group_id = pm.ad_group_id AND
				dv.site_group = pm.site_group AND
				dv.date_from BETWEEN pm.from_date AND pm.to_date AND
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
			$unit = $row['unit'];
			$dpl_tel_cnt_for_billing = $row['dpl_tel_cnt_for_billing'];
			$is_exclusion = $row['is_exclusion'];
			$exclusion_is_request = $row['exclusion_is_request'];
			$exclusion_reason = $row['exclusion_reason'];

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
					$media_name = "不明";
					$site_group = "-1";
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
			$unit_value = '';
			if ($is_exclusion) {
				if ($exclusion_is_request) {
					$check_call_dpl_for_billing = "除外依頼";
				}
				else {
					$check_call_dpl_for_billing = "弊社除外";
				}
			}
			else {
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
							$unit_value = strval(doubleval($unit));
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
			$arr['unit_value'] = $unit_value;
			$arr['exclusion_reason'] = $exclusion_reason;
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
			$sheet->setCellValueByColumnAndRow(1, $i, $out['advertiser_id']);
			$sheet->setCellValueByColumnAndRow(2, $i, $out['office_name']);
			$sheet->setCellValueByColumnAndRow(4, $i, $out['media_name']);
			$sheet->setCellValueByColumnAndRow(5, $i, $out['tel_to']);
			$sheet->setCellValueByColumnAndRow(6, $i, $out['tel_send']);
			$sheet->setCellValueByColumnAndRow(7, $i, $out['date_from']);
			$sheet->setCellValueByColumnAndRow(8, $i, $out['date_to']);
			$sheet->setCellValueByColumnAndRow(9, $i, $out['call_minutes']);
			$sheet->setCellValueByColumnAndRow(10, $i, $out['tel_from']);
			$sheet->setCellValueByColumnAndRow(11, $i, $out['call_status']);
			$sheet->setCellValueByColumnAndRow(12, $i, $out['check_call_dpl']);
			$sheet->setCellValueByColumnAndRow(13, $i, $out['charge_seconds']);
			$sheet->setCellValueByColumnAndRow(14, $i, $out['check_call_dpl_for_billing']);
			$sheet->setCellValueByColumnAndRow(15, $i, $out['unit_value']);
			$sheet->setCellValueByColumnAndRow(16, $i, $out['exclusion_reason']);
			$i++;
	}

	//crmシートメールデータ処理
	$sheet->setCellValueByColumnAndRow(1, $i, "メールデータ");
	$output_arr = array();
	foreach ($arr_ad_group_id as $row) {
		$ad_group_id = $row['ad_group_id'];
		#登録事務所名の取得
		$sum_crm_mail_data = array();
		$stmt = $pdo_cdr->query("
			SELECT
				c.*,
				ad.office_name,
				sg.site_group_name,
				st.site_type_name,
				(pm.unit_price / 10000) as unit
			FROM
				wordpress.ss_advertisers ad,
				wordpress.ss_site_type st,
				cdr.office_group_payment_method pm,
				cdr.mail_conv_view c
			LEFT OUTER JOIN wordpress.ss_site_group sg
			ON c.site_group = sg.site_group
			WHERE
				c.advertiser_id = ad.ID AND
				c.site_type = st.site_type AND
				c.ad_group_id = pm.ad_group_id AND
				c.site_group = pm.site_group AND
				c.register_dt BETWEEN pm.from_date AND pm.to_date AND
				DATE_FORMAT(c.register_dt,'%Y%m') = $year_month AND
				c.ad_group_id = $ad_group_id
			ORDER BY
				register_dt

		");
		$arr_crm_mail_data = $stmt->fetchAll(PDO::FETCH_ASSOC);
		foreach ($arr_crm_mail_data as $r) {
			$advertiser_id = $r['advertiser_id'];
			$office_name = $r['office_name'];
			$site_type = $r['site_type'];
			$site_type_name = $r['site_type_name'];
			$site_group = $r['site_group'];
			$site_group_name = $r['site_group_name'];
			$sender_tel = $r['sender_tel'];
			$date = $r['register_dt'];
			$dpl_tel_cnt = $r['dpl_tel_cnt'];
			$dpl_mail_cnt = $r['dpl_mail_cnt'];
			$is_exclusion = $r['is_exclusion'];
			$exclusion_is_request = $r['exclusion_is_request'];
			$exclusion_reason = $r['exclusion_reason'];
			$unit = $r['unit'];
			$unit_value = '';
			if ($is_exclusion) {
				if ($exclusion_is_request) {
					$check_mail_dpl = "除外依頼";
				}
				else {
					$check_mail_dpl = "弊社除外";
				}
			}
			else if ($dpl_tel_cnt == 0 && $dpl_mail_cnt == 0) {
				$check_mail_dpl = "○";
				$unit_value = strval(doubleval($unit));
			}
			else if ($dpl_tel_cnt > 0 && $dpl_mail_cnt > 0) {
				$check_mail_dpl = "同一電話・メール";
			}
			else if ($dpl_tel_cnt > 0) {
				$check_mail_dpl = "同一電話";
			}
			else if ($dpl_mail_cnt > 0) {
				$check_mail_dpl = "同一メール";
			}
			#事務所毎かつ、発生メール毎の情報が入る配列
			$new_crm_mail_array_data = array();
			array_push(
				$new_crm_mail_array_data,
				$advertiser_id,
				$office_name,
				$site_group,
				$site_group_name,
				$site_type,
				$site_type_name,
				$sender_tel,
				$date,
				$check_mail_dpl,
				$exclusion_reason,
				$unit_value
			);
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
			$exclusion_reason = $row['9'];
			$unit_value = $row['10'];

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
			$arr['exclusion_reason'] = $exclusion_reason;
			$arr['unit_value'] = $unit_value;
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
			$sheet->setCellValueByColumnAndRow(1, $i, $out['advertiser_id']);
			$sheet->setCellValueByColumnAndRow(2, $i, $out['office_name']);
			$sheet->setCellValueByColumnAndRow(4, $i, $out['site_group_name']);
			$sheet->setCellValueByColumnAndRow(5, $i, $out['site_type_name']);
			$sheet->setCellValueByColumnAndRow(8, $i, $out['mail_date']);
			$sheet->setCellValueByColumnAndRow(10, $i, $out['sender_tel']);
			$sheet->setCellValueByColumnAndRow(12, $i, $out['check_mail_dpl']);
			$sheet->setCellValueByColumnAndRow(14, $i, $out['check_mail_dpl']);
			$sheet->setCellValueByColumnAndRow(15, $i, $out['unit_value']);
			$sheet->setCellValueByColumnAndRow(16, $i, $out['exclusion_reason']);
	}

	#シートネームを設定
	$sheet->setTitle($sheet_name);
}
