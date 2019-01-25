<?php

require '../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Reader\Xlsx as XlsxReader;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx as XlsxWriter;

#共通関数のインクルード
include 'common_functions.php';
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

//事務所グループID
$ad_group_id = $_GET['ad_group_id'];
//対象年月
$year_month = $_GET['year_month'];
$ymd = "";
if (empty($year_month) || !is_numeric($year_month)) {
	$year_month = new DateTime();
}
else {
	$year_month = new DateTime($year_month."01");
}
$ymd = new DateTime("last day of ". $year_month->format("Y-m"));
//error_log(var_export($ymd, true), 0);

if (empty($ad_group_id) || !is_numeric($ad_group_id)) {
	print('<!DOCTYPE html>');
	print('<html lang="ja">');
	print('<head>');
	print('<meta charset="UTF-8">');
	print('<title>作成できません</title>');
	print('</head>');
	print('<body>');
	print('<a href="/">戻る</a>');
	print("<br>");
	print("事務所グループIDが未指定です。");
	print('</body>');
	print('</html>');
	die();
}

//月次のアクションデータ取得
$action_data = get_monthly_fixedcost_charged_actions($ad_group_id, $ymd->format("Y-m-d 23:59:59"));
//月次の定額データ取得
$flatrate_costs = get_monthly_flatrate_costs($ad_group_id, $ymd->format("Y-m-d"));

$ym_arr = array();
$sg_arr = array();
$min_ym = null;
$max_ym = null;
//年月最小・最大抽出、サイトグループリスト作成
foreach  ($action_data as $a) {
	if ($min_ym == null || $min_ym > intval($a['ym'])) {
		$min_ym = intval($a['ym']);
	}
	if ($max_ym == null || $max_ym < intval($a['ym'])) {
		$max_ym = intval($a['ym']);
	}
	if (!in_array($a['site_group'], $sg_arr)) {
		array_push($sg_arr, $a['site_group']);
	}
}

//年月リスト作成
$dt = new DateTime($min_ym."01 00:00:00");
while(true) {
	array_push($ym_arr, $dt->format('Ym'));
	$dt->modify("+1 months");
	if (intval($dt->format('Ym')) > $max_ym) {
		break;
	}
}

$cost_arr = array();
//定額データを整理する
$grp_tmp = array();
$max_cost_count = 0;
foreach ($flatrate_costs as $cost) {
	$ym = (new DateTime($cost['dt'].' 00:00:00'))->format('Ym');
	$site_groups = $cost['site_groups'] == '' ? 'all' : $cost['site_groups'];
	$key = $ym."|".$site_groups;
	$cost_arr[$key] = array('site_group_names' => $cost['site_group_names'], 'flatrate_price' => $cost['flatrate_price'], 'adjusted_price' => $cost['adjusted_price'], 'count_cell' => array());
	if (!array_key_exists($site_groups, $grp_tmp)) {
		$grp_tmp[$site_groups] = array($site_groups, $cost['site_group_names']);
	}
}
$max_cost_count = count($grp_tmp);

$tmp_ym = "";
foreach ($ym_arr as $ym) {
	foreach ($grp_tmp as $grp) {
		$key = $ym."|".$grp[0];
		//キーが無ければ前月のものを継承
		if (!array_key_exists($key, $cost_arr)) {
			$old_cost = $cost_arr[$tmp_ym."|".$grp[0]];
			$cost_arr[$key] = array('site_group_names' => $grp[1], 'flatrate_price' => $old_cost['flatrate_price'], 'adjusted_price' => 0, 'count_cell' => array());
		}
	}
	$tmp_ym = $ym;
}
error_log(var_export($cost_arr, true), 0);

//スプレッドシート生成
$spreadsheet = new Spreadsheet();
$spreadsheet->getProperties()
	->setTitle('タイトル')
	->setSubject('サブタイトル')
	->setCreator('作成者')
	->setCompany('会社名')
	->setManager('管理者')
	->setCategory('分類')
	->setDescription('コメント')
	->setKeywords('キーワード');

$sheet = $spreadsheet->getSheet(0);

$start_cell = array(3,4);
$row = $start_cell[1];
$sheet_arr = array();
$price_arr = array();
//ベースになるシートとデータを同時作成
foreach ($ym_arr as $ym) {
	$dt = new DateTime($ym."01 00:00:00");
	$sheet->setCellValueByColumnAndRow($start_cell[0] - 1, $row, $dt->format('Y年m月'));
	$col = $start_cell[0];
	foreach ($sg_arr as $sg) {
		$sheet->setCellValueByColumnAndRow($col, $start_cell[1] - 1, SITE_GROUP_NAMES[$sg]);
		$sheet->setCellValueByColumnAndRow($col + 1, $start_cell[1] - 1, "件数合計");
		$sheet_arr[$ym."|".$sg] = array($col, $row, 0);
		$price_arr[$ym."|".$sg] = 0;
		$col++;
	}
	$sheet->setCellValueByColumnAndRow($col, $row, '=SUM('.Coordinate::stringFromColumnIndex($start_cell[0]).$row.':'.Coordinate::stringFromColumnIndex($col - 1).$row.')');
	$sheet->getStyleByColumnAndRow($col, $row)->getFont()->setBold(true);
	$row++;
}

$col = 0;
//合計欄をシートへ描画
for ($i = 0; $i <= count($sg_arr); $i++) {
	$col = $i + $start_cell[0];
	$sheet->setCellValueByColumnAndRow($col, $row, '=SUM('.Coordinate::stringFromColumnIndex($col).$start_cell[1].':'.Coordinate::stringFromColumnIndex($col).($row - 1).')');
	$sheet->getStyleByColumnAndRow($col, $row)->getFont()->setBold(true);
}

//ベースデータに実際のデータを挿入
foreach ($action_data as $a) {
	$ym = $a['ym'];
	$site_group = $a['site_group'];
	$key = $ym."|".$site_group;
	if (array_key_exists($key, $sheet_arr)) {
		$sheet_arr[$key][2] = $a['count'];
		$price_arr[$key] = $a['unit_price'];
	}
}

//金額計算の表を生成
$price_row = $start_cell[1];
$price_init_col = $col + 2;
foreach ($ym_arr as $ym) {
	$price_col = $price_init_col;
	$count_col = $start_cell[0];
	foreach ($sg_arr as $sg) {
		$sheet->setCellValueByColumnAndRow($price_col, $start_cell[1] - 1, SITE_GROUP_NAMES[$sg]);
		$sheet->setCellValueByColumnAndRow($price_col + 1, $start_cell[1] - 1, "小計");
		$key = $ym."|".$sg;
		$price = intval($price_arr[$key]) / 10000;
		$calc = "=".Coordinate::stringFromColumnIndex($count_col).$price_row."*".$price;
		$sheet->setCellValueByColumnAndRow($price_col, $price_row, $calc);
		foreach ($cost_arr as $k => $val) {
			$ka = explode("|", $k);
			$c_ym = $ka[0];
			$c_sg = $ka[1];
			if ($c_ym != $ym) {
				continue;
			}
			if ($c_sg == "all") {
				array_push($cost_arr[$k]['count_cell'], Coordinate::stringFromColumnIndex($price_col).$price_row);
				break;
			}
			else {
				$c_sg_a = explode(",", $c_sg);
				if (in_array($sg, $c_sg_a)) {
					array_push($cost_arr[$k]['count_cell'], Coordinate::stringFromColumnIndex($price_col).$price_row);
					break;
				}
			}
		}
		$price_col++;
		$count_col++;
	}
	$sheet->setCellValueByColumnAndRow($price_col, $price_row, '=SUM('.Coordinate::stringFromColumnIndex($price_init_col).$price_row.':'.Coordinate::stringFromColumnIndex($price_col - 1).$price_row.')');
	$sheet->getStyleByColumnAndRow($price_col, $price_row)->getFont()->setBold(true);
	$price_row++;
}

//金額合計欄をシートへ描画
$price_col = 0;
for ($i = 0; $i <= count($sg_arr); $i++) {
	$price_col = $i + $price_init_col;
	$sheet->setCellValueByColumnAndRow($price_col, $price_row, '=SUM('.Coordinate::stringFromColumnIndex($price_col).$start_cell[1].':'.Coordinate::stringFromColumnIndex($price_col).($price_row - 1).')');
	$sheet->getStyleByColumnAndRow($price_col, $price_row)->getFont()->setBold(true);
}

//error_log(var_export($sheet_arr, true), 0);

//データ書き出し
foreach ($sheet_arr as $sa) {
	$sheet->setCellValueByColumnAndRow($sa[0], $sa[1], $sa[2]);
}

//定額データに金額情報をマージ
$cost_row = $start_cell[1];
$cost_init_col = $price_col + 2;
$cost_calc_init_col = $cost_init_col + $max_cost_count + 2;
foreach ($ym_arr as $ym) {
	$cost_col = $cost_init_col;
	$cost_calc_col = $cost_calc_init_col;
	foreach ($cost_arr as $k => $val) {
		$k_a = explode("|", $k);
		$c_ym = $k_a[0];
		if ($ym != $c_ym) {
			continue;
		}
		$sheet->setCellValueByColumnAndRow($cost_col, $start_cell[1] - 1, $val['site_group_names']);
		$sheet->setCellValueByColumnAndRow($cost_col + 1, $start_cell[1] - 1, "小計");

		$cost = intval($val['flatrate_price']) / 10000;
		$adjust = intval($val['adjusted_price']) / 10000;
		if ($adjust == 0) {
			$sheet->setCellValueByColumnAndRow($cost_col, $cost_row, $cost);
		}
		else {
			if ($adjust > 0) {
				$adjust = "+".$adjust;
			}
			$sheet->setCellValueByColumnAndRow($cost_col, $cost_row, '='.$cost.$adjust);
		}

		$sheet->setCellValueByColumnAndRow($cost_calc_col, $start_cell[1] - 1, $val['site_group_names']);
		$sheet->setCellValueByColumnAndRow($cost_calc_col + 1, $start_cell[1] - 1, "小計");

		$c_base = implode("-", $val['count_cell']);
		$c = "=".Coordinate::stringFromColumnIndex($cost_col).$cost_row."-".$c_base;
		$sheet->setCellValueByColumnAndRow($cost_calc_col, $cost_row, $c);

		$cost_col++;
		$cost_calc_col++;
	}
	$sheet->setCellValueByColumnAndRow($cost_col, $cost_row, '=SUM('.Coordinate::stringFromColumnIndex($cost_init_col).$cost_row.':'.Coordinate::stringFromColumnIndex($cost_col - 1).$cost_row.')');
	$sheet->getStyleByColumnAndRow($cost_col, $cost_row)->getFont()->setBold(true);
	$sheet->setCellValueByColumnAndRow($cost_calc_col, $cost_row, '=SUM('.Coordinate::stringFromColumnIndex($cost_calc_init_col).$cost_row.':'.Coordinate::stringFromColumnIndex($cost_calc_col - 1).$cost_row.')');
	$sheet->getStyleByColumnAndRow($cost_calc_col, $cost_row)->getFont()->setBold(true);
	$cost_row++;
}

//金額合計欄をシートへ描画
$cost_col = 0;
$cost_calc_col = 0;
for ($i = 0; $i <= $max_cost_count; $i++) {
	$cost_col = $i + $cost_init_col;
	$cost_calc_col = $i + $cost_calc_init_col;
	$sheet->setCellValueByColumnAndRow($cost_col, $cost_row, '=SUM('.Coordinate::stringFromColumnIndex($cost_col).$start_cell[1].':'.Coordinate::stringFromColumnIndex($cost_col).($cost_row - 1).')');
	$sheet->getStyleByColumnAndRow($cost_col, $cost_row)->getFont()->setBold(true);
	$sheet->setCellValueByColumnAndRow($cost_calc_col, $cost_row, '=SUM('.Coordinate::stringFromColumnIndex($cost_calc_col).$start_cell[1].':'.Coordinate::stringFromColumnIndex($cost_calc_col).($cost_row - 1).')');
	$sheet->getStyleByColumnAndRow($cost_calc_col, $cost_row)->getFont()->setBold(true);
}

//ダウンロード用
//MIMEタイプ：https://technet.microsoft.com/ja-jp/ee309278.aspx
header("Content-Description: File Transfer");
header('Content-Disposition: attachment; filename="weather.xlsx"');
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Transfer-Encoding: binary');
header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
header('Expires: 0');
ob_end_clean(); //バッファ消去
   
$writer = new XlsxWriter($spreadsheet);
$writer->save('php://output');