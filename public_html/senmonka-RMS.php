<!DOCTYPE html>
<html lang="ja">
<head> 
<meta charset="UTF-8">
<title>月次請求内容一覧</title>
<style>
.offices{
	font-size: 12px;
	border-collapse: separate;
	border-spacing: 0px;
	border-top: 1px solid #ccc;
	border-left: 1px solid #ccc;
}
.offices th{
	padding: 4px;
	text-align: left;
	vertical-align: top;
	color: #444;
	background-color: #ccc;
	border-top: 1px solid #fff;
	border-left: 1px solid #fff;
	border-right: 1px solid #ccc;
	border-bottom: 1px solid #ccc;
}
.offices td{
	padding: 4px;
	background-color: #fafafa;
	border-right: 1px solid #ccc;
	border-bottom: 1px solid #ccc;
}
.right_txt {
	text-align: right;
}
.bold {
	font-weight: bold;
}
.bold_steel_blue {
	font-weight: bold;
	color: steelblue;
}
.bold_blue {
	font-weight: bold;
	color: blue;
}
.red {
	color: red;
}
.bold_red {
	font-weight: bold;
	color: red;
}
.gray_down {
	color: #AAAAAA;
}
</style>
<script src="//code.jquery.com/jquery-2.1.3.min.js"></script>
<script type="text/javascript">
<!--
function changeYM() {
	var ym = $("#id_ym").val();
	var year = ym.substring(0, 4);
	var month = ym.substring(4, 6);
	form1.year.value = year;
	form1.month.value = month;
	form2.year.value = year;
	form2.month.value = month;
	form3.year.value = year;
	form3.month.value = month;
	form4.year.value = year;
	form4.month.value = month;

	$.getJSON(
		"/get_monthly_total.php",
		{'year' : year, 'month' : month},
		function(json) {
			var html = "<body>";
			var counts = json.counts
			var keys = Object.keys(counts);
			var total_call_count = 0;
			var total_sample_call_count = 0;
			var total_mail_count = 0;
			var total_sample_mail_count = 0;
			var total_earnings = 0;
			for (i = 0; i < keys.length; i++) {
				var bill_payer_id = keys[i];
				var bill_payer = counts[bill_payer_id];
				var adg_keys = Object.keys(bill_payer.ad_groups);
				var bill_payer_row_count = 1;

				//請求先分の行数カウント
				for (n = 0; n < adg_keys.length; n++) {
					var ad_group_id = adg_keys[n];
					var ad_group = counts[bill_payer_id]['ad_groups'][ad_group_id];
					var ad_keys = Object.keys(ad_group.advertisers);
					//事務所数分の行数カウント
					for (t = 0; t < ad_keys.length; t++) {
						var adv = ad_group.advertisers[ad_keys[t]];
						var med_keys = Object.keys(adv.medias);
						for (z = 0; z < med_keys.length; z++) {
							var med = adv.medias[med_keys[z]];
							if (med.call_count > 0 || med.sample_call_count > 0 || med.mail_count > 0 || med.sample_mail_count > 0) {
								bill_payer_row_count++;
							}
						}
						bill_payer_row_count++;
					}
					bill_payer_row_count++;
				}

				var bill_payer_named = false;
				for (n = 0; n < adg_keys.length; n++) {
					var ad_group_id = adg_keys[n];
					var ad_group = counts[bill_payer_id]['ad_groups'][ad_group_id];
					var ad_keys = Object.keys(ad_group.advertisers);
					var group_row_count = 1;
					//事務所数分の行数カウント
					for (t = 0; t < ad_keys.length; t++) {
						var adv = ad_group.advertisers[ad_keys[t]];
						var med_keys = Object.keys(adv.medias);
						for (z = 0; z < med_keys.length; z++) {
							var med = adv.medias[med_keys[z]];
							if (med.call_count > 0 || med.sample_call_count > 0 || med.mail_count > 0 || med.sample_mail_count > 0) {
								group_row_count++;
							}
						}
						group_row_count++;
					}

					var has_bill = false;
					var has_sheet = false;
					if (bill_payer.call_count > 0 || bill_payer.mail_count > 0) {
						has_bill = true;
					}
					var group_named = false;
					for (t = 0; t < ad_keys.length; t++) {
						var ad_id = ad_keys[t];
						var adv = ad_group.advertisers[ad_id];
						var med_keys = Object.keys(adv.medias);
						var adv_named = false;
						var adv_row_count = 1;
						//メディア数分の行数カウント
						for (z = 0; z < med_keys.length; z++) {
							var med = adv.medias[med_keys[z]];
							if (med.call_count > 0 || med.sample_call_count > 0 || med.mail_count > 0 || med.sample_mail_count > 0) {
								adv_row_count++;
							}
						}
						for (z = 0; z < med_keys.length; z++) {
							var med = adv.medias[med_keys[z]];
							var med_name = "";
							if (med.call_count == 0 && med.sample_call_count == 0 && med.mail_count == 0 && med.sample_mail_count == 0) {
								continue;
							}
							if (med_keys[z] == "shakkin") {
								med_name = "借金問題";
							}
							else if (med_keys[z] == "souzoku") {
								med_name = "相続問題";
							}
							else if (med_keys[z] == "koutsujiko") {
								med_name = "交通事故";
							}
							else if (med_keys[z] == "ninibaikyaku") {
								med_name = "任意売却";
							}
							else if (med_keys[z] == "meigihenkou") {
								med_name = "名義変更";
							}
							else if (med_keys[z] == "setsuritsu") {
								med_name = "会社設立";
							}
							else if (med_keys[z] == "keijijiken") {
								med_name = "刑事事件";
							}
							else if (med_keys[z] == "rikon") {
								med_name = "離婚問題";
							}
							else if (med_keys[z] == "bgatakanen") {
								med_name = "Ｂ型肝炎";
							}
							else if (med_keys[z] == "hibouchuushou") {
								med_name = "誹謗中傷";
							}
							else if (med_keys[z] == "jikouenyou") {
								med_name = "時効援用";
							}
							else if (med_keys[z] == "roudou") {
								med_name = "労働問題";
							}
							else if (med_keys[z] == "youikuhi") {
								med_name = "養育費回収";
							}
							if (med.payment_method.lastIndexOf('月額固定', 0) === 0) {
								//月額固定費の案件が有ればダウンロードボタン表示
								has_bill = true;
							}
							else if (med.payment_method.lastIndexOf('定額制', 0) === 0 || med.payment_method.lastIndexOf('固定費化', 0) === 0) {
								//定額制、固定費化の案件が有れば管理シートダウンロードボタン表示
								has_sheet = true;
							}
							html += "<tr>";
							if (!bill_payer_named) {
								html += "<td rowspan='" + bill_payer_row_count + "'>" + bill_payer_id + "." + bill_payer.bill_payer_name + "</td>";
								bill_payer_named = true;
							}
							if (!group_named) {
								html += "<td rowspan='" + group_row_count + "'>" + ad_group_id + "." + ad_group.group_name + "</td>";
								group_named = true;
							}
							if (!adv_named) {
								html += "<td rowspan='" + adv_row_count + "'>" + ad_id + "." + adv.office_name + "</td>";
								adv_named = true;
							}
							html += "<td class='right_txt'>" + med_name + "<small>（" + med.payment_method + "）</small></td>";
							html += "<td class='right_txt'>" + med.call_count + "</td>";
							html += "<td class=''><small>(" + med.sample_call_count + ")</small></td>";
							html += "<td class='right_txt'>" + med.mail_count + "</td>";
							html += "<td class=''><small>(" + med.sample_mail_count + ")</small></td>";
							html += "<td class='right_txt'>" + getCommaSepNum(med.earnings) + "</td>";
							html += "</tr>";
						}
						html += "<tr>";
						if (!bill_payer_named) {
							html += "<td rowspan='" + bill_payer_row_count + "'>" + bill_payer_id + "." + bill_payer.bill_payer_name + "</td>";
							bill_payer_named = true;
						}
						if (!group_named) {
							html += "<td rowspan='" + group_row_count + "'>" + ad_group_id + "." + ad_group.group_name + "</td>";
							group_named = true;
						}
						if (!adv_named) {
							html += "<td rowspan='" + adv_row_count + "'>" + ad_id + "." + adv.office_name + "</td>";
							adv_named = true;
						}
						html += "<td class='right_txt bold'>事務所計</td>";
						html += "<td class='right_txt bold'>" + adv.call_count + "</td>";
						html += "<td class=''><small>(" + adv.sample_call_count + ")</small></td>";
						html += "<td class='right_txt bold'>" + adv.mail_count + "</td>";
						html += "<td class=''><small>(" + adv.sample_mail_count + ")</small></td>";
						html += "<td class='right_txt'>" + getCommaSepNum(adv.earnings) + "</td>";
						html += "</tr>";
					}
					html += "<tr>";
					html += "<td colspan='2' class='right_txt bold_steel_blue'>";
					if (has_sheet) {
						html += "<input type='button' value='管理シートダウンロード' onclick='download_management_sheet(" + ad_group_id + ")' style='font-size: 1.2em; font-weight: bold;'>　";
					}
					html += "事務所グループ計</td>";
					html += "<td class='right_txt bold_steel_blue'>" + ad_group.call_count + "</td>";
					html += "<td class=''><small>(" + ad_group.sample_call_count + ")</small></td>";
					html += "<td class='right_txt bold_steel_blue'>" + ad_group.mail_count + "</td>";
					html += "<td class=''><small>(" + ad_group.sample_mail_count + ")</small></td>";
					html += "<td class='right_txt'>" + getCommaSepNum(ad_group.earnings) + "</td>";
					html += "</tr>";
				}

				html += "<tr>";
				if (has_bill) {
					html += "<td colspan='3' class='right_txt bold_blue'>";
					html += "<input type='button' value='請求書ダウンロード' onclick='download_bill(" + bill_payer_id + ")' style='font-size: 1.2em; font-weight: bold;'>";
					html += "　請求計</td>";
					html += "<td class='right_txt bold_blue'>" + bill_payer.call_count + "</td>";
					html += "<td class=''><small>(" + bill_payer.sample_call_count + ")</small></td>";
					html += "<td class='right_txt bold_blue'>" + bill_payer.mail_count + "</td>";
					html += "<td class=''><small>(" + bill_payer.sample_mail_count + ")</small></td>";
					html += "<td class='right_txt'>" + getCommaSepNum(bill_payer.earnings) + "</td>";
				}
				else {
					html += "<td colspan='3' class='right_txt gray_down'>";
					html += "<input type='button' value='請求書ダウンロード' onclick='download_bill(" + bill_payer_id + ")' style='font-size: 1.2em; font-weight: bold;'>";
					html += "　請求計</td>";
					html += "<td class='right_txt gray_down'>" + bill_payer.call_count + "</td>";
					html += "<td class='gray_down'><small>(" + bill_payer.sample_call_count + ")</small></td>";
					html += "<td class='right_txt gray_down'>" + bill_payer.mail_count + "</td>";
					html += "<td class='gray_down'><small>(" + bill_payer.sample_mail_count + ")</small></td>";
					html += "<td class='right_txt'>" + getCommaSepNum(bill_payer.earnings) + "</td>";
				}
				total_call_count += parseInt(bill_payer.call_count);
				total_sample_call_count += parseInt(bill_payer.sample_call_count);
				total_mail_count += parseInt(bill_payer.mail_count);
				total_sample_mail_count += parseInt(bill_payer.sample_mail_count);
				total_earnings += parseInt(bill_payer.earnings);
				html += "</tr>";
			}

			var total = json.total;
			var keys = Object.keys(json.total);
			for (i = 0; i < keys.length; i++) {
				var k = keys[i];
				var name = "";
				switch (k) {
					case 'shakkin': name = "借金問題"; break;
					case 'souzoku': name = "相続問題"; break;
					case 'koutsujiko': name = "交通事故"; break;
					case 'ninibaikyaku': name = "任意売却"; break;
					case 'meigihenkou': name = "名義変更"; break;
					case 'setsuritsu': name = "会社設立"; break;
					case 'keijijiken': name = "刑事事件"; break;
					case 'rikon': name = "離婚問題"; break;
					case 'bgatakanen': name = "Ｂ型肝炎"; break;
					case 'hibouchuushou': name = "誹謗中傷"; break;
					case 'jikouenyou': name = "時効援用"; break;
					case 'roudou': name = "労働問題"; break;
					case 'youikuhi': name = "養育費回収"; break;
				}
				html += "<tr>";
				html += "<td colspan='4' class='right_txt red'><big>" + name + " 計</big></td>";
				html += "<td class='right_txt red'><big>" + total[k]['call_count'] + "</big></td>";
				html += "<td class=''>(" + total[k]['sample_call_count'] + ")</td>";
				html += "<td class='right_txt red'><big>" + total[k]['mail_count'] + "</big></td>";
				html += "<td class=''>(" + total[k]['sample_mail_count'] + ")</td>";
				html += "<td class='right_txt red'><big>" + getCommaSepNum(total[k]['earnings']) + "</big></td>";
				html += "</tr>";
			}

			html += "<tr>";
			html += "<td colspan='4' class='right_txt bold_red'><big>全請求計</big></td>";
			html += "<td class='right_txt bold_red'><big>" + total_call_count + "</big></td>";
			html += "<td class=''>(" + total_sample_call_count + ")</td>";
			html += "<td class='right_txt bold_red'><big>" + total_mail_count + "</big></td>";
			html += "<td class=''>(" + total_sample_mail_count + ")</td>";
			html += "<td class='right_txt bold_red'><big>" + getCommaSepNum(total_earnings) + "</big></td>";
			html += "</tr>";
			html += "</body>";
			$("#office_table").html(html);
		}
	);
}

function download_bill(bill_payer_id) {
	form2.bill_payer_id.value = bill_payer_id;
	return form2.submit();
}

function download_management_sheet(ad_group_id, year_month) {
	form4.ad_group_id.value = ad_group_id;
	return form4.submit();
}

function getCommaSepNum(num) {
	return String(num).replace(/(\d)(?=(\d\d\d)+(?!\d))/g, '$1,');
}

$(function(){
	changeYM();
});
-->
</script>
</head>
<body>
<select id="id_ym" name="ym" onChange="changeYM()">
<?php 
$now_ym = date("Ym01");
$default_ym = date("Ym01", strtotime($now_ym.' -1 month'));
$startdt = '20140901';
$i = 0;
while (true) {
	$d = date('Ym01', strtotime($startdt.' +'.$i.' month'));
	$d1 = date('Ym', strtotime($d));
	$d2 = date('Y年m月', strtotime($d));
	if ($d == $default_ym) {
?>
<option value="<?php echo $d1;?>" selected><?php echo $d2;?></option>
<?php
	} else {
?>
<option value="<?php echo $d1;?>"><?php echo $d2;?></option>
<?php
	}
	if ($d == $now_ym) {
		break;
	}
	$i++;
}
?>
</select>分
<br>
<br>
<form method="post" name="form1" action="create_monthly_details.php">
	<input type="hidden" name="year" value="">
	<input type="hidden" name="month" value="">
	<input type="submit" value="月次詳細情報ダウンロード" style="font-size: 1.2em; width: 300px;">
</form>
<form method="post" name="form3" action="create_bill.php">
	<input type="hidden" name="year" value="">
	<input type="hidden" name="month" value="">
	<input type="hidden" name="pack" value="true">
	<input type="submit" value="請求書一括ダウンロード" style="font-size: 1.2em; width: 300px;">
</form>
<form method="post" name="form2" action="create_bill.php">
	<input type="hidden" name="year" value="">
	<input type="hidden" name="month" value="">
	<input type="hidden" name="bill_payer_id" value="">
</form>
<form method="post" name="form4" action="get_fixedrate_sheet.php">
	<input type="hidden" name="year" value="">
	<input type="hidden" name="month" value="">
	<input type="hidden" name="ad_group_id" value="">
</form>
<br>
<table class="offices">
	<thead>
		<th>請求先</th>
		<th>事務所グループ</th>
		<th>事務所</th>
		<th>メディア</th>
		<th colspan="2">請求コール数 <small>(参考値)</small></th>
		<th colspan="2">請求メール数 <small>(参考値)</small></th>
		<th>売上</th>
	</thead>
	<tbody id="office_table">
	</tbody>
</table>
<br>
<br>
<br>
</body>
</html>
