<?php
#データベース接続処理
$pdo =null;
$reviser=null;
$dsn ='mysql:dbname=smk_request_data;host=localhost';
$user = 'suzukikunihiro';
$pass = 'pikoro';
try{	
		$pdo = new PDO($dsn,$user,$pass);
}catch(PDOException $e){
		exit('接続ミス'.$e->getMessage());
}
$stmt=$pdo->query('SET NAMES utf8');
if(!$stmt){
		$info=$pdo->errorinfo();
		exit($info[2]);
}
#reviser呼び出し
require_once('reviser_lite.php');
$reviser = NEW Excel_Reviser;
$reviser->setInternalCharset('utf-8');	

#DB内の行最大数を取得
$max_row = $pdo->query("SELECT COUNT(*) FROM ad_req_data");

#フォームからの事務所IDの受け取り
$id = $_POST['change'];
#フォームからの年月の受け取り
$year = $_POST['year'];
$month = $_POST['month'];
#請求有効件数が0であった場合には出力しない
$check = $pdo->query("SELECT valid_call_shakkin,valid_call_souzoku,valid_call_koutsujiko,valid_call_meigihenkou,valid_call_setsuritsu,valid_call_keijijiken FROM ad_monthly_valid_call WHERE req_id=$id AND year=$year AND month=$month");
$check = $check->fetch(PDO::FETCH_ASSOC);
$check2 = $pdo->query("SELECT mail_shakkin,mail_souzoku,mail_koutsujiko,mail_meigihenkou,mail_setsuritsu FROM ad_monthly_mail_num WHERE req_id=$id AND year=$year AND month=$month");
$check2 = $check2->fetch(PDO::FETCH_ASSOC);
if(!empty($check)|| !empty($check2)){
	get_each_ad_data($id,$year,$month);
		}
else{
		print('<a href="http://localhost/senmonka-RMS.php">戻る</a>');
		print("<br>");
		die("この年月では、この事務所は有効電話数とメール数が０件です");
}

function get_each_ad_data($id,$year,$month){
		#####有効コール請求内容データの取得
		global $pdo,$reviser,$month;
		$sheet_num =0;
		$stmt = $pdo->query("SELECT * FROM ad_monthly_valid_call WHERE req_id=$id AND year=$year AND month=$month");
		$req_mvc_data = $stmt->fetch(PDO::FETCH_ASSOC);
		####課金メール数請求内容データの取得
		$stmt2 = $pdo->query("SELECT * FROM ad_monthly_mail_num WHERE req_id=$id AND year=$year AND month=$month");
		$req_mail_data = $stmt2->fetch(PDO::FETCH_ASSOC);

		#####事務所情報データの取得
		$stmt3 = $pdo->query("SELECT * FROM ad_req_data WHERE req_id=$id");
		$ad_data = $stmt3->fetch(PDO::FETCH_ASSOC);
		$req_ad_name = $ad_data['req_ad_name'];
		$reviser->setSheetname($sheet_num,$req_ad_name);
		#御担当者名の表示
		$c_name  = $ad_data['person_in_charge'];
		$reviser->addString($sheet_num,8,2,$c_name."　様");
		#####データベースからの取得内容表示
		#郵便番号
		$reviser->addString($sheet_num,4,2,"〒".$ad_data['postal_code']);
		#住所
		$reviser->addString($sheet_num,5,2,$ad_data['address_1']);
		$reviser->addString($sheet_num,6,2," ".$ad_data['address_2']);
		#貴社名/御氏名
		$reviser->addString($sheet_num,7,2,$ad_data['req_ad_name']);
		#行数の定義
		$i=18;
		$reviser->addNumber($sheet_num,$i,0,"1");
		#借金問題
		if($req_mvc_data['valid_call_shakkin']>0 OR $req_mail_data['mail_shakkin']>0){
				#月
				$reviser->addNumber($sheet_num,$i,1,"$month");	
				#商品名
				$reviser->addString($sheet_num,$i,2,"月成果料金(借金問題)");
				#数量
				$reviser->addNumber($sheet_num,$i,3,$req_mvc_data['valid_call_shakkin']+$req_mail_data['mail_shakkin']);
				#単価
				$reviser->addNumber($sheet_num,$i,4,10000);
				#合計金額
				$sum = ($req_mvc_data['valid_call_shakkin']+$req_mail_data['mail_shakkin'])*10000;
				$reviser->addNumber($sheet_num,$i,5,$sum);
				$i=$i+1;
		}
		#相続
		if($req_mvc_data['valid_call_souzoku']>0 OR $req_mail_data['mail_souzoku']>0){
				#月
				$reviser->addNumber($sheet_num,$i,1,"$month");	
				#商品名
				$reviser->addString($sheet_num,$i,2,"月成果料金(相続)");
				#数量
				$reviser->addNumber($sheet_num,$i,3,$req_mvc_data['valid_call_souzoku']+$req_mail_data['mail_souzoku']);
				#単価
				$reviser->addNumber($sheet_num,$i,4,5000);
				#合計金額
				$sum = ($req_mvc_data['valid_call_souzoku']+$req_mail_data['mail_souzoku'])*5000;
				$reviser->addNumber($sheet_num,$i,5,$sum);
				$i=$i+1;
		}
		#交通事故
		if($req_mvc_data['valid_call_koutsujiko']>0 OR $req_mail_data['mail_koutsujiko']>0){
				#月
				$reviser->addNumber($sheet_num,$i,1,"$month");	
				#商品名
				$reviser->addString($sheet_num,$i,2,"月成果料金(交通事故)");
				#数量
				$reviser->addNumber($sheet_num,$i,3,$req_mvc_data['valid_call_koutsujiko']+$req_mail_data['mail_koutsujiko']);
				#単価
				$reviser->addNumber($sheet_num,$i,4,10000);
				#合計金額
				$sum = ($req_mvc_data['valid_call_koutsujiko']+$req_mail_data['mail_koutsujiko'])*10000;
				$reviser->addNumber($sheet_num,$i,5,$sum);
				$i=$i+1;
		}
		#名義変更
		if($req_mvc_data['valid_call_meigihenkou']>0 OR $req_mail_data['mail_meigihenkou']>0){
				#月
				$reviser->addNumber($sheet_num,$i,1,"$month");	
				#商品名
				$reviser->addString($sheet_num,$i,2,"月成果料金(名義変更)");
				#数量
				$reviser->addNumber($sheet_num,$i,3,$req_mvc_data['valid_call_meigihenkou']+$req_mail_data['mail_meigihenkou']);
				#単価
				$reviser->addNumber($sheet_num,$i,4,5000);
				#合計金額
				$sum = ($req_mvc_data['valid_call_meigihenkou']+$req_mail_data['mail_meigihenkou'])*5000;
				$reviser->addNumber($sheet_num,$i,5,$sum);
				$i=$i+1;
		}
		#会社設立
		if($req_mvc_data['valid_call_setsuritsu']>0 OR $req_mail_data['mail_setsuritsu']>0){
				#月
				$reviser->addNumber($sheet_num,$i,1,"$month");	
				#商品名
				$reviser->addString($sheet_num,$i,2,"月成果料金(会社設立)");
				#数量
				$reviser->addNumber($sheet_num,$i,3,$req_mvc_data['valid_call_setsuritsu']+$req_mail_data['mail_setsuritsu']);
				#単価
				$reviser->addNumber($sheet_num,$i,4,5000);
				#合計金額
				$sum =($req_mvc_data['valid_call_setsuritsu']+$req_mail_data['mail_setsuritsu'])*5000;
				$reviser->addNumber($sheet_num,$i,5,$sum);
				$i=$i+1;
		}
		#刑事事件
		if($req_mvc_data['valid_call_keijijiken']>0){
				#月
				$reviser->addNumber($sheet_num,$i,1,"$month");	
				#商品名
				$reviser->addString($sheet_num,$i,2,"月成果料金(刑事事件)");
				#数量
				$reviser->addNumber($sheet_num,$i,3,$req_mvc_data['valid_call_keijijiken']);
				#単価
				$reviser->addNumber($sheet_num,$i,4,10000);
				#合計金額
				$sum =($req_mvc_data['valid_call_keijijiken'])*10000;
				$reviser->addNumber($sheet_num,$i,5,$sum);
				$i=$i+1;
		}
		$i=$i+1;
		$reviser->addNumber($sheet_num,$i,0,"2");
		#月
		$reviser->addNumber($sheet_num,$i,1,"$month");	
		#フリーダイヤル料金記入
		$reviser->addString($sheet_num,$i,2,"月フリーダイヤル通話料金");
		#単価
		$reviser->addNumber($sheet_num,$i,4,$req_mvc_data['call_charge']);
		#合計金額
		$reviser->addNumber($sheet_num,$i,5,$req_mvc_data['call_charge']);
		if($req_mvc_data['count_freedial']!=null){
			$i=$i+1;
			#月
			$reviser->addNumber($sheet_num,$i,1,"$month");
			#発番費用
			$reviser->addString($sheet_num,$i,2,"月フリーダイヤル費用");
			#数量
			$reviser->addNumber($sheet_num,$i,3,$req_mvc_data['count_freedial']);
			#単価
			$reviser->addNumber($sheet_num,$i,4,1000);//変更可能である可能性あり
			#合計金額
			$sum =($req_mvc_data['count_freedial'])*1000;
			$reviser->addNumber($sheet_num,$i,5,$sum);
		}
		#メール内容の書き込み
		/*
		$sheet_num =1;
		$reviser->addString($sheet_num,0,0,"先生
				
		いつもお世話になっております。
		
		ウィズパッションの土田です。
		
		月文分の請求書を添付されていただきます。
		
		月は、
		");
		 */
		#テンプレを読み込み、出力する
		$readfile = "./template.xls";	
		$outfile="$year$month$req_ad_name.xls";
		$reviser->revisefile($readfile,$outfile);

}#end_of_function/get_each_ad_data

?>
