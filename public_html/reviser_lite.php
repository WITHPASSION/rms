<?PHP
/**
 * Excel_Reviser Lite beta Version  Author:kishiyan
 * 
 * Copyright (c) 2006-2008 kishiyan <excelreviser@gmail.com>
 * All rights reserved.
 *
 * Support
 *   URL  http://chazuke.com/forum/index.php?c=2
 *
 * Redistribution and use in source, with or without modification, are
 * permitted provided that the following conditions are met:
 * 1. Redistributions of source code must retain the above copyright
 *    notice, this list of conditions and the following disclaimer,
 *    without modification, immediately at the beginning of the file.
 * 2. The name of the author may not be used to endorse or promote products
 *    derived from this software without specific prior written permission.
 *
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License
 * as published by the Free Software Foundation; either version 2
 * of the License, or (at your option) any later version.
 * 
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *   URL http://www.gnu.org/licenses/gpl.html
 * 
 * @package Excel_Reviser
 * @author kishiyan <excelreviser@gmail.com>
 * @copyright Copyright &copy; 2006-2007, kishiyan
 * @since PHP 4.4.1 or 5.1.1
 * @version alpha1 2007/08/19
 */

/*  HISTORY
2007.08.18 alpha version release
2007.12.16 beta version release
2007.12.19 change 1st line '<?' to '<?PHP'
2008.06.29 FIX for some file by MAC
2010.04.06 FIX over-7Mbytes BUG
*/

define('Reviser_Version','0.03beta');
define('Version_Num', 0.03);

define('Default_CHARSET', 'eucJP-win');
define('Code_BIFF8', 0x600);
define('Code_WorkbookGlobals', 0x5);
define('Code_Worksheet', 0x10);
define('Type_EOF', 0x0a);
define('Type_BOUNDSHEET', 0x85);
define('Type_SST', 0xfc);
define('Type_CONTINUE', 0x3c);
define('Type_EXTSST', 0xff);
define('Type_LABELSST', 0xfd);
define('Type_WRITEACCESS', 0x5c);
define('Type_OBJPROJ', 0xd3);
define('Type_BUTTONPROPERTYSET', 0x1ba);
define('Type_DIMENSION', 0x200);
define('Type_ROW', 0x208);
define('Type_DBCELL', 0xd7);
define('Type_RK', 0x7e);
define('Type_RK2', 0x27e);
define('Type_MULRK', 0xbd);
define('Type_MULBLANK', 0xbe);
define('Type_INDEX', 0x20b);
#define('Type_LABELSST', 0xfd);
define('Type_NUMBER', 0x203);
define('Type_FORMULA', 0x406);
define('Type_FORMULA2', 0x6);
define('Type_BOOLERR', 0x205);
define('Type_UNKNOWN', 0xffff);
define('Type_BLANK', 0x201);
define('Type_SharedFormula', 0x4bc);
define('Type_STRING', 0x207);
define('Type_HEADER', 0x14);
define('Type_FOOTER', 0x15);
define('Type_BOF', 0x809);
define('Type_WINDOW2', 0x23e);
define('Type_SUPBOOK', 0x1ae);
define('Type_EXTERNSHEET', 0x17);
define('Type_NAME', 0x18);

/**
* Class for regenerating Excel Spreadsheets
* @package Excel_Reviser
* @author kishiyan <excelreviser@gmail.com>
* @copyright Copyright &copy; 2006-2007, kishiyan
* @since PHP 4.4
* @example ./sample.php sample
*/
class Excel_Reviser
{
	// temp for workbook Globals Substream data
//	var $wbdat='';
	// part of workbook Globals Substream data
	var $globaldat=array();
	// buffer for all cell-record
	var $cellblock=array();
	// sheet-block data
	var $sheetbin=array();
	// each parameter of sheet record
	var $boundsheets=array();
	// buffer of user setting parameter
	var $revise_dat=array();
	// number of strings in sst record
	var $ssttotal; // total reffer
	var $sstnum; // real number
	// sheet-number for erase by user
	var $rmsheets=array();
	// cell for erase by user
	var $rmcells=array();
	// charactor-set name
	var $charset;
	// number of REF structure in EXTERNSHEET
	var $refnum;
	// rapid write area
	var $fixrow=array();
	// Cell-block bin
	var $cbsheet=array();
	// save magic_quotes Flag
	var $Flag_Magic_Quotes=False;
	// save error_reporting
	var $Flag_Error_Reporting;

	// Constructor
	function Excel_Reviser(){
		$this->charset = Default_CHARSET;
		$this->Flag_Error_Reporting=error_reporting();
	}

	/**
	* Set(Get) internal charset, if you use multibyte-code.
	* @param string $chrset charactor-set name(Ex. SJIS)
	* @return string current charector-set name
	* @access public
	*/
	function setInternalCharset($chrset=''){
		if (strlen(trim($chrset)) > 2) {
			$this->charset = $chrset;
		}
		return $this->charset;
	}

	/**
	* Parse file and Remake
	* @param string $readfile full path filename for read
	* @param string $outfile filename for web output
	* @param string $path if not null then save file
	* @access public
	* @example ./sample.php sample
	*/
	function reviseFile($readfile,$outfile,$path=null){
		$this->parseFile($readfile);
		$this->makeFile($outfile,$path);
	}

	/**
	* Set border Rapid write area
	* @param integer $sheet sheet number
	* @param integer $row border row
	* @access public
	*/
	function setBorder($sheet,$row){
		$this->fixrow[$sheet] = $row;
	}

	/**
	* Set remove Sheet number
	* @param integer $sheet sheet number  0 indexed
	* @access public
	* @example ./sample.php sample
	*/
	function rmSheet($sheet){
		if (is_numeric($sheet)){
			$this->rmsheets[$sheet]=TRUE;
		}
	}

	/**
	* Set remove Cell
	* @param integer $sheet sheet number
	* @param integer $row Row position
	* @param integer $col Column posion  0 base indexed
	* @access public
	* @example ./sample.php sample
	*/
	function rmCell($sheet,$row,$col){
		if (is_numeric($sheet) && is_numeric($row) && is_numeric($col)){
			$this->rmcells[$sheet][$row][$col]=TRUE;
		}
	}

	/**
	* Add String to Cell
	* @param integer $sheet sheet number
	* @param integer $row Row position
	* @param integer $col Column posion  0indexed
	* @param string $str string
	* @param integer $refrow reference row(option)
	* @param integer $refcol reference column(option)
	* @param integer $refsheet reference sheet number(option)
	* @access public
	* @example ./sample.php sample
	*/
	function addString($sheet,$row, $col, $str, $refrow = null, $refcol = null, $refsheet = null){
		if (($row < 0) || ($col < 0) || ($sheet < 0)) return -1;
		if ($refsheet === null) $refsheet = $sheet;
		if ($refrow === null) $refrow = $row;
		if ($refcol === null) $refcol = $col;
		if (!$str){
			$this->addBlank($sheet,$row, $col, $refrow, $refcol, $refsheet);
			return;
		}
		$val['sheet']=$sheet;
		$val['row']=$row;
		$val['col']=$col;
		$val['str']=$str;
		$val['refrow']=$refrow;
		$val['refcol']=$refcol;
		$val['refsheet']=$refsheet;
		$this->revise_dat['add_str'][]=$val;
	}

	/**
	* Add Number to Cell
	* @param integer $sheet sheet number
	* @param integer $row Row position
	* @param integer $col Column posion  0indexed
	* @param integer $num number
	* @param integer $refrow reference row(option)
	* @param integer $refcol refernce column(option)
	* @param integer $refsheet reference sheet number(option)
	* @access public
	* @example ./sample.php sample
	*/
	function addNumber($sheet,$row, $col, $num, $refrow = null, $refcol = null, $refsheet = null){
		if (($row < 0) || ($col < 0) || ($sheet < 0)) return -1;
		if ($refsheet === null) $refsheet = $sheet;
		if ($refrow === null) $refrow = $row;
		if ($refcol === null) $refcol = $col;
		$val['sheet']=$sheet;
		$val['row']=$row;
		$val['col']=$col;
		$val['num']=$num;
		$val['refrow']=$refrow;
		$val['refcol']=$refcol;
		$val['refsheet']=$refsheet;
		$this->revise_dat['add_num'][]=$val;
	}

	/**
	* overwrite Sheetname
	* @param integer $sn sheet number
	* @param string $str new sheet name
	* @access public
	* @example ./sample.php sample
	*/
	function setSheetname($sn,$str){
			$len = strlen($str);
			if (mb_detect_encoding($str,"ASCII,ISO-8859-1")=="ASCII"){
				$opt =0;
			} else {
				$opt =1;
				$str = mb_convert_encoding($str,'UTF-16LE',$this->charset);
				$len = mb_strlen($str,'UTF-16LE');
			}
			$val = pack("CC",$len,$opt);
		$this->revise_dat['sheetname'][$sn]=$val.$str;
	}

	/**
	* overwrite header string
	* @param integer $sn sheet number
	* @param string $str new header-string
	* @access public
	* @example ./sample.php sample
	*/
	function setHeader($sn,$str){
			if (mb_detect_encoding($str,"ASCII,ISO-8859-1")=="ASCII"){
				$opt =0;
				$len = strlen($str);
			} else {
				$opt =1;
				$str = mb_convert_encoding($str,'UTF-16LE',$this->charset);
				$len = mb_strlen($str,'UTF-16LE');
			}
			$val = pack("vC",$len,$opt);
		$this->revise_dat['header'][$sn]=$val.$str;
	}

	/**
	* overwrite footer string
	* @param integer $sn sheet number
	* @param string $str new footer-string
	* @access public
	* @example ./sample.php sample
	*/
	function setFooter($sn,$str){
			if (mb_detect_encoding($str,"ASCII,ISO-8859-1")=="ASCII"){
				$opt =0;
				$len = strlen($str);
			} else {
				$opt =1;
				$str = mb_convert_encoding($str,'UTF-16LE',$this->charset);
				$len = mb_strlen($str,'UTF-16LE');
			}
			$val = pack("vC",$len,$opt);
		$this->revise_dat['footer'][$sn]=$val.$str;
	}

	/**
	* Add Blank Cell
	* @param integer $sheet sheet number  0 base indexed
	* @param integer $row Row position  0 base indexed
	* @param integer $col Column posion  0 base indexed
	* @param integer $refrow reference row(option)
	* @param integer $refcol reference column(option)
	* @param integer $refsheet ref sheet number(option)
	* @access public
	* @example ./sample3.php sample3
	*/
	function addBlank($sheet,$row, $col, $refrow, $refcol, $refsheet = null){
		if (($row < 0) || ($col < 0) || ($sheet < 0)) return -1;
		if (($refrow < 0) || ($refcol < 0)) return -1;
		if ($refsheet === null) $refsheet = $sheet;
		$val['sheet']=$sheet;
		$val['row']=$row;
		$val['col']=$col;
		$val['refrow']=$refrow;
		$val['refcol']=$refcol;
		$val['refsheet']=$refsheet;
		$this->revise_dat['add_blank'][]=$val;
	}

	/**
	* read OLE container
	* @param  $Fname:filename
	* @access private
	*/
	function __oleread($Fname){
		if(!is_readable($Fname)) {
			die("ERROR Cannot read file $Fname \nProbably there is not reading permission whether there is not a file");
		}
		$this->Flag_Magic_Quotes = get_magic_quotes_runtime();
		if ($this->Flag_Magic_Quotes) set_magic_quotes_runtime(0);
		$ole_data = @file_get_contents($Fname);
		if ($this->Flag_Magic_Quotes) set_magic_quotes_runtime($this->Flag_Magic_Quotes);
		if (!$ole_data) { 
			die("ERROR Cannot open file $Fname \n");
		}
		if (substr($ole_data, 0, 8) != pack("CCCCCCCC",0xd0,0xcf,0x11,0xe0,0xa1,0xb1,0x1a,0xe1)) {
			die("ERROR Template file($Fname) is not EXCEL file.\n");
	   	}
		$numDepots = $this->__get4($ole_data, 0x2c);
		$sStartBlk = $this->__get4($ole_data, 0x3c);
		$ExBlock = $this->__get4($ole_data, 0x44);
		$numExBlks = $this->__get4($ole_data, 0x48);

		$len_ole = strlen($ole_data);
		if ($numDepots > ($len_ole / 65536 +1))
			die("ERROR file($Fname) is broken (numDepots)");
		#if ($sStartBlk > ($len_ole / 512 +1))
			#die("ERROR file($Fname) is broken (sStartBlk)");
		#if ($ExBlock > ($len_ole / 512 +1))
			#die("ERROR file($Fname) is broken (ExBlock)");
		if ($numExBlks > ($len_ole / 512 +1))
			die("ERROR file($Fname) is broken (numExBlks)");

		$DepotBlks = array();
		$pos = 0x4c;
		$dBlks = $numDepots;
		if ($numExBlks != 0) $dBlks = (0x200 - 0x4c)/4;
		for ($i = 0; $i < $dBlks; $i++) {
			  $DepotBlks[$i] = $this->__get4($ole_data, $pos);
			  $pos += 4;
		}
	
		for ($j = 0; $j < $numExBlks; $j++) {
			$pos = ($ExBlock + 1) * 0x200;
			$ReadBlks = min($numDepots - $dBlks, 0x200 / 4 - 1);
			for ($i = $dBlks; $i < $dBlks + $ReadBlks; $i++) {
				$DepotBlks[$i] = $this->__get4($ole_data, $pos);
				$pos += 4;
			}   
			$dBlks += $ReadBlks;
			if ($dBlks < $numDepots) $ExBlock = $this->__get4($ole_data, $pos);
		}
	
		$pos = 0;
		$index = 0;
		$BlkChain = array();
		for ($i = 0; $i < $numDepots; $i++) {
			$pos = ($DepotBlks[$i] + 1) * 0x200;
			for ($j = 0 ; $j < 0x200 / 4; $j++) {
				$BlkChain[$index] = $this->__get4($ole_data, $pos);
				$pos += 4 ;
				$index++;
			}
		}

		$eoc= 0xFE | (0xFFFFFF << 8);
		$pos = 0;
		$index = 0;
		$sBlkChain = array();
		while ($sStartBlk != $eoc) {
			$pos = ($sStartBlk + 1) * 0x200;
			for ($j = 0; $j < 0x80; $j++) {
				$sBlkChain[$index] = $this->__get4($ole_data, $pos);
				$pos += 4 ;
				$index++;
			}
			$chk[$sStartBlk]=true;
			$sStartBlk = $BlkChain[$sStartBlk];
			if($chk[$sStartBlk]){
	die("Big Block chain for small-block ERROR 1\nTemplate file is broken");
			}
		}
		unset($chk);
		$block = $this->__get4($ole_data, 0x30);
		$pos = 0;
		$entry = '';
		while ($block != $eoc)  {
			$pos = ($block + 1) * 0x200;
			$entry .= substr($ole_data, $pos, 0x200);
			$chk[$block]=true;
			$block = $BlkChain[$block];
	                if(isset($chk[$block])){
	die("Big Block chain for Entry  ERROR 2\nTemplate file is broken");
	                }
		}
		unset($chk);
		$offset = 0;
		$rootBlock =$this->__get4($entry, 0x74);
		while ($offset < strlen($entry)) {
			  $d = substr($entry, $offset, 0x80);
			  $name = str_replace("\x00", "", substr($d,0,$this->__get2($d,0x40)));
			if (($name == "Workbook") || ($name == "Book")) {
				$wbstartBlock =$this->__get4($d, 0x74);
				$wbsize = $this->__get4($d, 0x78);
			}
//			if ($name == "Root Entry") {
//				$rootBlock =$this->__get4($d, 0x74);
//			}
			$offset += 0x80;
		}
		if ($wbsize < 0x1000) {
			$pos = 0;
			$rdata = '';
			while ($rootBlock != $eoc)  {
				$pos = ($rootBlock + 1) * 0x200;
				$rdata = $rdata.substr($ole_data, $pos, 0x200);
				$chk[$rootBlock]=true;
				$rootBlock = $BlkChain[$rootBlock];
				if(isset($chk[$rootBlock])){
	die("Big Block chain ERROR 3\nTemplate file is broken");
				}
			}
			unset($chk);
			$pos = 0;
			$wbData = '';
			$block = $wbstartBlock;
			while ($block != $eoc) {
				$pos = $block * 0x40;
				$wbData .= substr($rdata, $pos, 0x40);
				$chk[$block]=true;
				$block = $sBlkChain[$block];
				if(isset($chk[$block])){
	die("Big Block chain ERROR 4\nTemplate file is broken");
				}
			}
			unset($chk);
			return $wbData;
		} else {
			$numBlocks = $wbsize / 0x200;
			if ($wbsize % 0x200 != 0) $numBlocks++;
			if ($numBlocks == 0) return '';
			$wbData = '';
			$block = $wbstartBlock;
			$pos = 0;
			while ($block != $eoc) {
				$pos = ($block + 1) * 0x200;
				$wbData .= substr($ole_data, $pos, 0x200);
				$chk[$block]=true;
				$block = $BlkChain[$block];
				if(isset($chk[$block])){
	die("Big Block chain ERROR 5\nTemplate file is broken");
				}
			}
			unset($chk);
			return $wbData;
		}
	}

	/*
	** parse sheetblock
	** @access private
	*/
	function __parsesheet(&$dat,$sn,$spos){
		$code = 0;
		$version = $this->__get2($dat,$spos + 4);
		$substreamType = $this->__get2($dat,$spos + 6);
		if ($version != Code_BIFF8) {
			die("Contents(included sheet) is not BIFF8 format.\n");
		}
		if ($substreamType != Code_Worksheet) {
			die("Contents is unknown format.\nCan't find Worksheet.\n");
		}
		$tmp='';
		$dimnum=0;
		$bof_num=0;
		$sposlimit=strlen($dat);
		while($code != Type_EOF) {
			if ($spos > $sposlimit) {
				die("Sheet $sn Read ERROR\nTemplate file is broken.\n");
			}
			$code = $this->__get2($dat,$spos);
			$length = $this->__get2($dat,$spos + 2);
			if ($code == Type_BOF) $bof_num++;
			if ($bof_num > 1){
				$tmp.=substr($dat, $spos, $length+4);
				while($code != Type_EOF) {
					if ($spos > $sposlimit) {
						die("Parse-Sheet Error\n");
					}
					$spos += $length+4;
					$code = $this->__get2($dat,$spos);
					$length = $this->__get2($dat,$spos + 2);
					$tmp.=substr($dat, $spos, $length+4);
				}
				$bof_num--;
				$spos += $length+4;
				$code = $this->__get2($dat,$spos);
				$length = $this->__get2($dat,$spos + 2);
				$tmp.=substr($dat, $spos, $length+4);
			}else
			switch ($code) {
				case Type_HEADER:
					if (isset($this->revise_dat['header'][$sn])){
						$tmp.=pack("vv",Type_HEADER,strlen($this->revise_dat['header'][$sn]));
						$tmp.=$this->revise_dat['header'][$sn];
unset($this->revise_dat['header'][$sn]);
					} else
					$tmp.=substr($dat, $spos, $length+4);
					break;
				case Type_FOOTER:
					if (isset($this->revise_dat['footer'][$sn])){
						$tmp.=pack("vv",Type_FOOTER,strlen($this->revise_dat['footer'][$sn]));
						$tmp.=$this->revise_dat['footer'][$sn];
unset($this->revise_dat['footer'][$sn]);
					} else
					$tmp.=substr($dat, $spos, $length+4);
					break;
				case Type_DIMENSION:
					$tmp.=substr($dat, $spos, $length+4);
					if ($dimnum==0){
						$this->sheetbin[$sn]['preCB']=$tmp;
						$tmp='';
					}
					$dimnum++;
					break;
				case Type_RK2:
				case Type_LABELSST:
				case Type_NUMBER:
				case Type_FORMULA2:
				case Type_BOOLERR:
				case Type_BLANK:
					$row=$this->__get2($dat,$spos + 4);
					$col=$this->__get2($dat,$spos + 6);
					$this->cellblock[$sn][$row][$col]['xf']=$this->__get2($dat,$spos + 8);
					$this->cellblock[$sn][$row][$col]['type']=$code;
					$this->cellblock[$sn][$row][$col]['dat']=substr($dat, $spos+10, $length-6);
					$this->cellblock[$sn][$row][$col]['record']=substr($dat, $spos, $length+4);
					$this->cellblock[$sn][$row][$col]['string']='';
					if ($code == Type_FORMULA2){
						$dispnum = substr($dat, $spos+10, 8);
						$opflag = $this->__get2($dat,$spos + 18) | 0x02; // Calculate on open
						$tokens = substr($dat, $spos+20, $length - 16);
						$this->cellblock[$sn][$row][$col]['dat']=$dispnum . pack("v",$opflag) . $tokens;
						$this->cellblock[$sn][$row][$col]['record']='';
						if ($this->__get2($dat,$spos + $length + 4) == Type_SharedFormula){
							$spos += $length + 4;
							$length = $this->__get2($dat,$spos + 2);
							$this->cellblock[$sn][$row][$col]['sharedform']=substr($dat,$spos,$length+4);
						}
						if ($this->__get2($dat,$spos + $length + 4) == Type_STRING){
							$spos += $length + 4;
							$length = $this->__get2($dat,$spos + 2);
							$this->cellblock[$sn][$row][$col]['string']=substr($dat,$spos,$length+4);
						}
					}
					break;
				case Type_MULBLANK:
					$muln=($length-6)/2;
					$row=$this->__get2($dat,$spos + 4);
					$col=$this->__get2($dat,$spos + 6);
					$i=-1;
					while(++$i < $muln){
						$this->cellblock[$sn][$row][$i+$col]['xf']=$this->__get2($dat,$spos+8+$i*2);
						$this->cellblock[$sn][$row][$i+$col]['type']=Type_BLANK;
						$this->cellblock[$sn][$row][$i+$col]['dat']='';
						$this->cellblock[$sn][$row][$i+$col]['record']=pack("vvvv", 0x0201, 0x06, $row, $i+$col). substr($dat, $spos+8+$i*2, 2);
					}
					break;
				case Type_MULRK:
					$muln=($length-6)/6;
					$row=$this->__get2($dat,$spos + 4);
					$col=$this->__get2($dat,$spos + 6);
					$i=-1;
					while(++$i < $muln){
						$this->cellblock[$sn][$row][$i+$col]['xf']=$this->__get2($dat,$spos+8+$i*6);
						$this->cellblock[$sn][$row][$i+$col]['type']=Type_RK;
						$this->cellblock[$sn][$row][$i+$col]['dat']=substr($dat, $spos+10+$i*6, 4);
						$this->cellblock[$sn][$row][$i+$col]['record']=pack("vvvv", 0x027e, 0x0a, $row, $i+$col). substr($dat, $spos+8+$i*6, 6);
					}
					break;
				case Type_DBCELL:
					break;
				case Type_BUTTONPROPERTYSET:
					break;
				case Type_EOF:
					break;
				default:
					$tmp.= substr($dat, $spos, $length+4);
			}
			$spos += $length+4;
		}
		$this->sheetbin[$sn]['tail']=$tmp;
	}

	/**
	* remake Cell records
	* @access private
	*/
	function __makeCellRecord($sn){
		$tmp='';
		if(isset($this->cellblock[$sn])){
			ksort($this->cellblock[$sn]);
			foreach((array)$this->cellblock[$sn] as $keyR => $rowval) {

if ($this->fixrow[$sn] <= $keyR) break;

				ksort($rowval);
				foreach($rowval as $keyC => $cellval) {
					if (isset($this->rmcells[$sn][$keyR][$keyC])) continue;
					if (!isset($cellval['record'])) $cellval['record']='';
					if ($cellval['record']) {
						$tmp.=$cellval['record'];
					} else {
						$tmp.=pack("vv",$cellval['type'],strlen($cellval['dat'])+6);
						$tmp.=pack("vvv",$keyR,$keyC,$cellval['xf']).$cellval['dat'];
					}
					if (isset($cellval['sharedform'])) $tmp.=$cellval['sharedform'];
					if (isset($cellval['string'])) $tmp.=$cellval['string'];
				}
			}
		}
unset($this->cellblock[$sn]);
		return $tmp;
	}

	/**
	* remake sheet-block
	* @access private
	*/
	function __makeSheet($sn){
		$tmp='';
		$tmp.=$this->sheetbin[$sn]['preCB'];
		$tmp.=$this->__makeCellRecord($sn).$this->cbsheet[$sn];
		$tmp.=$this->sheetbin[$sn]['tail'];
		$tmp.=pack("H*","0a000000");
unset($this->sheetbin[$sn]);
		return $tmp;
	}

	/**
	* convert 1,2,4 bytes string to number
	* @param $d:string,$p:position
	* @return number
	* @access private
	*/
	function __get4(&$d, $p) {
		return ord($d[$p]) | (ord($d[$p+1]) << 8) |
			(ord($d[$p+2]) << 16) | (ord($d[$p+3]) << 24);
	}

	/**
	* @access private
	*/
	function __get2(&$d, $p) {
		return ord($d[$p]) | (ord($d[$p+1]) << 8);
	}

	/**
	* @access private
	*/
	function __get1(&$d, $p) {
		return ord($d[$p]);
	}

	/*
	** Parse Excel file
	** @input  $filename:full path for OLE file
	** @access private
	*/
	function parseFile($filename){
		error_reporting(E_ALL ^ E_NOTICE);

		$dat = $this->__oleread($filename);
		if (strlen($dat) < 256) {
			die("Contents is too small (".strlen($dat).")\nProbably template file is not right Excel file.\n");
		}
		$presheet=1;
		$pos = 0;
		$version = $this->__get2($dat,$pos + 4);
		$substreamType = $this->__get2($dat,$pos + 6);
		if ($version != Code_BIFF8) {
			die("Contents is not BIFF8 format.\n");
		}
		if ($substreamType != Code_WorkbookGlobals) {
			die("Contents is unknown format.\nCan't find WorkbookGlobal.");
		}
		$code=-1;
		$poslimit=strlen($dat);
		while ($code != Type_EOF){
			if ($pos > $poslimit){
				die("Global Area Read Error\nTemplate file is broken");
			}
		    $code = $this->__get2($dat,$pos);
		    $length = $this->__get2($dat,$pos+2);
		    switch ($code) {
			case Type_SST:
				$this->globaldat['presst']=$this->wbdat;
				$this->wbdat='';
				$this->ssttotal = $this->__get4($dat,$pos+4);
				$this->sstnum = $this->__get4($dat,$pos+8);
				$this->globaldat['sst1']=substr($dat, $pos, 4);
				$this->globaldat['sst2']=substr($dat, $pos+4, 8);
				$this->globaldat['sst']=substr($dat, $pos+12, $length-8);
				while ($this->__get2($dat,$pos + $length + 4) == Type_CONTINUE){
					$pos += $length + 4;
					$length = $this->__get2($dat,$pos+2);
					$this->globaldat['sst'].=substr($dat, $pos, $length+4);
				}
			    break;
			case Type_EXTSST:
				$exsstbin = '';
				break;
			case Type_OBJPROJ:
			case Type_BUTTONPROPERTYSET:
				break;
			case Type_BOUNDSHEET:
				if ($presheet) {
					$this->globaldat['presheet']=$this->wbdat;
					$this->wbdat='';
					$presheet=0;
				}
				$rec_offset = $this->__get4($dat, $pos+4);
			    $sheetno['code'] = substr($dat, $pos, 2);
			    $sheetno['length'] = substr($dat, $pos+2, 2);
			    $sheetno['offsetbin'] = substr($dat, $pos+4, 4);
			    $sheetno['offset'] = $rec_offset;
			    $sheetno['visible'] = substr($dat, $pos+8, 1);
			    $sheetno['type'] = substr($dat, $pos+9, 1);
			    $sheetno['name'] = substr($dat, $pos+10, $length-6);
			    $this->boundsheets[] = $sheetno;
			    break;
			case Type_SUPBOOK:
				if (substr($dat, $pos+6,2)=="\x01\x04"){
					$this->globaldat['presup'].=$this->wbdat;
					$this->wbdat='';
					$this->globaldat['supbook']=substr($dat, $pos, $length+4);
					if ($this->__get2($dat, $pos+$length+4)!=Type_EXTERNSHEET) break; // deliberately
					$pos +=$length+4;
					$length = $this->__get2($dat,$pos+2);
					$this->refnum = $this->__get2($dat,$pos+4);
					$this->globaldat['extsheet']=substr($dat, $pos, $length+4);
					$this->globaldat['name']='';
					$this->globaldat['namerecord']='';
					while($this->__get2($dat, $pos+$length+4)==Type_NAME){
						$pos +=$length+4;
						$length = $this->__get2($dat,$pos+2);
						if ($this->__get2($dat,$pos+12)==0){
							$this->globaldat['name'].=substr($dat, $pos, $length+4);
						} else {
							$this->globaldat['namerecord'].=substr($dat, $pos, $length+4);
							$lenform=$this->__get2($dat,$pos+8);
							$namtype=$this->__get1($dat,$pos+19);
							$tmp['flags2notu']=substr($dat, $pos, 12);
							$tmp['sheetindex']=$this->__get2($dat,$pos+12);
							$tmp['menu2name']=substr($dat, $pos+14, 6);
							$tmp['formula']=$this->analizeform(substr($dat,$pos+20,$lenform));
							$tmp['remain']=substr($dat,$pos+20+$lenform,$length-(16+$lenform));
							$this->boundsheets[$this->__get2($dat,$pos+12)-1]['namerecord'][$namtype]=$tmp;
						}
					}
				} else {
					$this->wbdat .= substr($dat, $pos, $length+4);
				}
			    break;
			case Type_WRITEACCESS:
				$wa = "5c00700027000145007800630065006c005f00520065007600";
				$wa.= "690073006500720020004c0069007400650048722000200068";
				$wa.= "007400740070003a002f002f006300680061007a0075006b00";
				$wa.= "65002e0063006f006d00202020202020202020202020202020";
				$wa.= "20202020202020202020202020202020";
				$this->wbdat .= pack("H*",$wa);
			    break;
			case Type_EOF:
				$this->globaldat['last']= $this->wbdat . substr($dat, $pos, $length+4);
			    break;
			default:
				$this->wbdat .= substr($dat, $pos, $length+4);
			}
			$pos += $length + 4;
		}
		foreach ($this->boundsheets as $key=>$val){
		    $this->__parsesheet($dat,$key,$val['offset']);
if (!isset($this->fixrow[$key])) $this->fixrow[$key]=0x010000;
//		}

//		foreach($this->boundsheets as $key=>$val){
			if (strlen($this->revise_dat['sheetname'][$key])){
			    $this->boundsheets[$key]['name'] = $this->revise_dat['sheetname'][$key];
			    $this->boundsheets[$key]['length'] = pack("v",6 + strlen($this->revise_dat['sheetname'][$key]));
			}
		}
unset($this->revise_dat['sheetname']);

		error_reporting($this->Flag_Error_Reporting);
	}

	/*
	** Remake Excel file
	** @input  $filename:file-name for web output
	** @output stdout for web-output
	** @access private
	*/
	function makeFile($filename,$path=null){
		error_reporting(E_ALL ^ E_NOTICE);

		$this->reviseCell();
$this->ex_makesstend();
		krsort($this->rmsheets);
		foreach ($this->rmsheets as $key => $val) {
			if ((count($this->boundsheets) > 1) && $val){
				unset($this->boundsheets[$key]);
			}
		}
		$this->_makesupblock();
		$sstbin=$this->globaldat['sst1'].pack("VV",$this->ssttotal,$this->sstnum).$this->globaldat['sst'];
$sstbin.=$this->ex_sstbin;
unset($this->ex_sstbin);
		$tmplen=strlen($this->globaldat['presheet'] . $this->globaldat['presst'] . $this->globaldat['last']);
		$tmplen += strlen($this->globaldat['presup'].$this->globaldat['supbook']);
		$tmplen += strlen($this->globaldat['extsheet'].$this->globaldat['name']);
		$tmplen += strlen($this->globaldat['namerecord']);
		$tmplen += strlen($sstbin.$this->globaldat['exsstbin']);
//		$refnum1=$refnum;
		foreach ($this->boundsheets as $key=>$val){
			$tmplen += strlen($val['code']);
			$tmplen += strlen($val['length']);
			$tmplen += strlen($val['offsetbin']);
			$tmplen += strlen($val['visible']);
			$tmplen += strlen($val['type']);
			$tmplen += strlen($val['name']);
			$sheetdat[$key]=$this->__makeSheet($key);
		}
	
		foreach ((array)$sheetdat as $key=>$val){
			$this->boundsheets[$key]['offsetbin']=pack("V",$tmplen);
			$tmplen += strlen($val);
		}
	// make global-block
		$tmp=$this->globaldat['presheet'];
		foreach ($this->boundsheets as $key=>$val){
			$tmp .= $val['code'];
			$tmp .= $val['length'];
			$tmp .= $val['offsetbin'];
			$tmp .= $val['visible'];
			$tmp .= $val['type'];
			$tmp .= $val['name'];
		}
		$tmp .= $this->globaldat['presup'].$this->globaldat['supbook'];
		$tmp .= $this->globaldat['extsheet'];
		$tmp .= $this->globaldat['name'];
		$tmp .= $this->globaldat['namerecord'];
		$tmp .= $this->globaldat['presst'] . $sstbin . $this->globaldat['exsstbin'];
		$tmp .= $this->globaldat['last'];
unset($this->globaldat);
		foreach ((array)$sheetdat as $val){
			$tmp .= $val;
		}
unset($sheetdat);
	// from here making Excel-file
		if (($path === null) || (trim($path)=="")) {
			header("Content-type: application/vnd.ms-excel");
			header("Content-Disposition: attachment; filename=\"$filename\"");
			header("Expires: 0");
			header("Cache-Control: must-revalidate, post-check=0,pre-check=0");
			header("Pragma: public");
			print $this->makeole2($tmp);
		} else {
			if (substr($path,-1) == '/') $path = substr($path,0,-1);
			if (!file_exists($path)) die("The path $path does not exist.");
			$filename = $path . '/' . $filename;
			$_FILEH_ = @fopen($filename, "wb");
			if ($_FILEH_ == false) {
				die("Can't open $filename. It may be in use or protected.");
			}
			fwrite($_FILEH_, $this->makeole2($tmp));
			@fclose($_FILEH_);
		}
		error_reporting($this->Flag_Error_Reporting);
	}

	/**
	* Remake Cell block
	* @access private
	*/
	function reviseCell(){
		if (isset($this->revise_dat['add_str']))
		foreach((array)$this->revise_dat['add_str'] as $key => $val) {
			if ($this->fixrow[$val['sheet']] <= $val['row']) continue;
			$xf= (isset($this->cellblock[$val['refsheet']][$val['refrow']][$val['refcol']]['xf'])) ? $this->cellblock[$val['refsheet']][$val['refrow']][$val['refcol']]['xf'] : 0x0f;
			$header    = pack('vv', Type_LABELSST, 0x0a);
			$data      = pack('vvvV', $val['row'], $val['col'], $xf, $this->sstnum);
			$this->cellblock[$val['sheet']][$val['row']][$val['col']]['record']=$header.$data;
			$this->ex_makesst($val['str']);
unset($this->revise_dat['add_str'][$key]);
		}
		if (isset($this->revise_dat['add_num']))
		foreach((array)$this->revise_dat['add_num'] as $key => $val) {
			if ($this->fixrow[$val['sheet']] <= $val['row']) continue;
			$xf= (isset($this->cellblock[$val['refsheet']][$val['refrow']][$val['refcol']]['xf'])) ? $this->cellblock[$val['refsheet']][$val['refrow']][$val['refcol']]['xf'] : 0x0f;
			$packednum = (pack("N",1)==pack("L",1)) ? strrev(pack("d", $val['num'])) : pack("d", $val['num']);
			$header = pack('vv', Type_NUMBER, 0x0e);
			$data = pack('vvv', $val['row'], $val['col'], $xf).$packednum;
			$this->cellblock[$val['sheet']][$val['row']][$val['col']]['record']=$header.$data;
unset($this->revise_dat['add_num'][$key]);
		}
		if (isset($this->revise_dat['add_blank']))
		foreach((array)$this->revise_dat['add_blank'] as $key => $val) {
			if ($this->fixrow[$val['sheet']] <= $val['row']) continue;
			$xf= (isset($this->cellblock[$val['refsheet']][$val['refrow']][$val['refcol']]['xf'])) ? $this->cellblock[$val['refsheet']][$val['refrow']][$val['refcol']]['xf'] : 0x0f;
			$header = pack('vv', Type_BLANK, 0x06);
			$data = pack('vvv', $val['row'], $val['col'], $xf);
			$this->cellblock[$val['sheet']][$val['row']][$val['col']]['record']=$header.$data;
		}
unset($this->revise_dat);
	}

	/**
	* make OLE container
	* @param $tmpbin:binary data
	* @return packed data
	* @access private
	*/
    function makeole2(& $tmpbin){

	$orglen=strlen($tmpbin);
	if ($orglen < 0x1000) {
		$tmpbin=str_pad($tmpbin, 0x1000, "\x00");
		$orglen = 0x1000;
	} else {
		if ($orglen % 512 != 0)
		$tmpbin .= str_repeat("\x00", 512 - ($orglen % 512));
	}
	$needSecs = strlen($tmpbin)/512;
	$AllTbl=$needSecs + 1;// 1=PPS / 4
	$rootSec=$needSecs;
        $BdCntW = floor($AllTbl / 0x80) + (($AllTbl % 0x80)? 1: 0);
        $BdCnt = floor(($AllTbl + $BdCntW) / 0x80) + ((($AllTbl+$BdCntW) % 0x80)? 1: 0);
	$MSATex=0;
	while ($BdCnt > ($MSATex * 0x80 + 109)) {
		$MSATex++;
		$AllTbl++;
		$BdCntW = floor($AllTbl / 0x80) + (($AllTbl % 0x80)? 1: 0);
		$BdCnt = floor(($AllTbl + $BdCntW) / 0x80) + ((($AllTbl+$BdCntW) % 0x80)? 1: 0);
	}


	$oledat =pack("H*","D0CF11E0A1B11AE1")
			. str_repeat("\x00", 16)
			. pack("v", 0x3b)
			. pack("v", 0x03)
			. pack("v", -2)
			. pack("v", 9)
			. pack("v", 6)
			. str_repeat("\x00", 10)
			. pack("V", $BdCnt)
			. pack("V", $needSecs)	// Root Entry
			. pack("V", 0)
			. pack("V", 0x1000)
			. pack("V", -2)  //Short Block Depot
			. pack("V", 0);
	if ($BdCnt < 109) {	//$masterAlTbl ,$masterAlnum)
			$oledat .= pack("V", -2) . pack("V", 0);	
	} else {
			$oledat .= pack("V", $needSecs+1+$BdCnt) . pack("V", $MSATex);
	}

	for($i=0;$i<109;$i++){
		if($i < $BdCnt){
			$oledat.=pack("V",$i+$needSecs+1);// 1 for PPS
		} else {
			$oledat.=pack("V",-1);
		}
	}
	$oledat.= $tmpbin;

	$oledat.=str_pad($this->asc2utf('Root Entry'), 64, "\x00")	//0- 64
		. pack("v",2*(1+strlen('Root Entry')))		//64- 2
		. "\x05"		//66- 1
		. "\x01"		//67- 1
		. pack("V",-1)	//68- 4
		. pack("V",-1)	//72- 4
		. pack("V",1)	//76- 4
		. str_repeat("\x00", 16)	//80- 16
		. pack("V",0)	//96- 4
		. pack("d",0)	//100- 8
		. pack("d",0)	//108- 8
		. pack("V",0)	//116- 4
		. pack("V",0)	//120- 4
		. pack("V",0);	//124- 4

		$nextSec=0;
		$oledat.=str_pad($this->asc2utf('Workbook'), 64, "\x00")	//0- 64
			. pack("v",2*(1+strlen('Workbook')))		//64- 2
			. "\x02"		//66- 1
			. "\x01"		//67- 1
			. pack("V",-1)	//68- 4
			. pack("V",-1)	//72- 4
			. pack("V",-1)	//76- 4
			. str_repeat("\x00", 16)	//80- 16
			. pack("V",0)	//96- 4
			. pack("d",0)	//100- 8
			. pack("d",0)	//108- 8
			. pack("V",$nextSec)	//116- 4
			. pack("V",$orglen)	//120- 4
			. pack("V",0);	//124- 4
		$oledat.=str_repeat("\x00", 256);
		$nextSec += (floor($orglen / 0x200) + (($orglen % 0x200)? 1: 0));

		for ($i=0; $i<($needSecs-1); $i++) $oledat.=pack("V", $i+1);
		$oledat.=pack("V", -2);
		$oledat.=pack("V", -2);// for PPS
        for ($i = 0; $i < $BdCnt; $i++) $oledat.= pack("V", -3);//for BBD
        for ($i = 0; $i < $MSATex; $i++) $oledat.= pack("V", -4);//for Ex
        if (strlen($oledat) % 0x200 != 0) {
			$n=0x200-(strlen($oledat) % 0x200);
			for ($i=0; $i<$n ; $i += 4)
				$oledat .= pack("V", -1);
		}

		if ($BdCnt > 109) {
			$n=0;
			$b=0;
			for ($i = 109;$i < $BdCnt; $i++, $n++) {
				if ($n >= (0x80 - 1)) {
					$n = 0;
					$b++;
					$oledat .= pack("V", $needSecs+$BdCnt+$b);
				}
				$oledat .= pack("V", $needSecs+1+$i);//1=PPS
			}
			if (($BdCnt-109) % (0x80-1)) {
				for ($i = 0; $i < ((0x80 - 1) - (($BdCnt - 109) % (0x80 - 1))); $i++) {
					$oledat .= pack("V", -1); 
				}
			}
			$oledat .= pack("V", -2);
		}
		return $oledat;
	}

	/**
	* convert charset ASCII to UTF16
	* @param $ascii string
	* @return UTF16 string
	* @access private
	*/
	function asc2utf($ascii){
		$utfname='';
		for ($i = 0; $i < strlen($ascii); $i++) {
			$utfname.=$ascii{$i}."\x00";
		}
		return $utfname;
	}
	/**
	* @access private
	*/
	function _makesupblock(){
		if (count($this->rmsheets) >0){
			$curnum=count($this->boundsheets);
			$this->globaldat['supbook']=pack("vvvv",Type_SUPBOOK,4,$curnum,0x401);
			$exsheetdat=substr($this->globaldat['extsheet'],6);
			for($i=0;$i<$curnum;$i++){
				$exsheetdat.=pack("vvv",0,$i,$i);
			}
			$this->globaldat['extsheet']=pack("vvv",0x17,strlen($exsheetdat)+2,$curnum+$this->refnum).$exsheetdat;
			$nr='';
			$i=0;
			foreach((array)$this->boundsheets as $sn =>$tmp){
				if (count($tmp['namerecord'][6])>0){
					$nr.=$tmp['namerecord'][6]['flags2notu'].pack("v",$i+1).$tmp['namerecord'][6]['menu2name'];
					$nr.=pack("H*",str_replace('X',bin2hex(pack('v',$i+$this->refnum)),$tmp['namerecord'][6]['formula']));
					$nr.=$tmp['namerecord'][6]['remain'];
				}
				if (count($tmp['namerecord'][7])>0){
					$nr.=$tmp['namerecord'][7]['flags2notu'].pack("v",$i+1).$tmp['namerecord'][7]['menu2name'];
					$nr.=pack("H*",str_replace('X',bin2hex(pack('v',$i+$this->refnum)),$tmp['namerecord'][7]['formula']));
					$nr.=$tmp['namerecord'][7]['remain'];
				}
				$i++;
			}
			$this->globaldat['namerecord']=$nr;
		}
		return;
	}
	/**
	* @access private
	*/
	function analizeform($form){
		$fpos=0;
		$flen=strlen($form);
		$ret='';
		while ($fpos < $flen){
			$token=$this->__get1($form,$fpos);
			if ($token > 0x3F) $token -=0x20;
			if ($token > 0x3F) $token -=0x20;
			switch ($token){
			case 0x3:
			case 0x4:
			case 0x5:
			case 0x6:
			case 0x7:
			case 0x8:
			case 0x9:
			case 0xA:
			case 0xB:
			case 0xC:
			case 0xD:
			case 0xE:
			case 0xF:
			case 0x10:
			case 0x11:
			case 0x12:
			case 0x13:
			case 0x14:
			case 0x15:
			case 0x16:
				$ret.=bin2hex(substr($form,$fpos,1));
				$fpos+=1;
				break;
			case 0x1C:
			case 0x1D:
				$ret.=bin2hex(substr($form,$fpos,2));
				$fpos+=2;
				break;
			case 0x1E:
			case 0x29:
			case 0x2E:
			case 0x2F:
			case 0x3D:
				$ret.=bin2hex(substr($form,$fpos,3));
				$fpos+=3;
				break;
			case 0x21:
				$ret.=bin2hex(substr($form,$fpos,4));
				$fpos+=4;
				break;
			case 0x1:
			case 0x2:
			case 0x22:
			case 0x23:
			case 0x24:
			case 0x2A:
			case 0x2C:
				$ret.=bin2hex(substr($form,$fpos,5));
				$fpos+=5;
				break;
			case 0x39:
			case 0x3A:
			case 0x3C:
				$ret.=bin2hex(substr($form,$fpos,1));
				$ret.="X";
				$ret.=bin2hex(substr($form,$fpos+3,4));
				$fpos+=7;
				break;
			case 0x26:
			case 0x27:
			case 0x28:
				$ret.=bin2hex(substr($form,$fpos,7));
				$fpos+=7;
				break;
			case 0x1F:
			case 0x20:
			case 0x25:
			case 0x2B:
			case 0x2D:
				$ret.=bin2hex(substr($form,$fpos,9));
				$fpos+=9;
				break;
			case 0x3B:
			case 0x3D:
				$ret.=bin2hex(substr($form,$fpos,1));
				$ret.="X";
				$ret.=bin2hex(substr($form,$fpos+3,8));
				$fpos+=11;
				break;
			default:
				$ret=bin2hex($form);
				$fpos = $flen;
			}
		}
		return $ret;
	}

	var $ex_nokori;
	var $ex_rdat;
	var $ex_sstbin;
	var	$ex_num;

	function ex_makesst($str){
		$this->ex_nokori = 0x2020 - strlen($this->ex_rdat);
		if ($this->ex_nokori < 10) {
			$this->ex_sstbin .= pack("vv",0x3c,strlen($this->ex_rdat)).$this->ex_rdat;
			$this->ex_rdat = '';
		}
		if (mb_detect_encoding($str,"ASCII,ISO-8859-1")=="ASCII"){
			$opt =0;
			$len = strlen($str);
			$lenb = $len;
		} else {
			$opt =1;
			$str = mb_convert_encoding($str, "UTF-16LE", $this->charset);
			$len = mb_strlen ($str,"UTF-16LE");
			$lenb = 2 * $len;
		}
		$this->ex_rdat.=pack("vC",$len,$opt);
		$this->ex_nokori = 0x2020 - strlen($this->ex_rdat);
		while ($this->ex_nokori < $lenb) {
			$this->ex_nokori &= 0xfffe;
			$this->ex_rdat .= substr($str,0,$this->ex_nokori);
			$str = substr($str,$this->ex_nokori);
			$this->ex_sstbin .= pack("vv",0x3c,strlen($this->ex_rdat)).$this->ex_rdat;
			$lenb -= $this->ex_nokori;
			$opt &=1;
			$this->ex_rdat = pack("C",$opt);
			$this->ex_nokori = 0x201f;
		}
		$this->ex_rdat .= $str;
		$this->ssttotal++;
		$this->sstnum++;
		return;
	}

	function ex_makesstend(){
		if ($this->ex_rdat)
			$this->ex_sstbin .= pack("vv",0x3c,strlen($this->ex_rdat)).$this->ex_rdat;
		return;
	}

	function addRecord($type,$sheet,$row, $col, $val, $refrow = null, $refcol = null, $refsheet = null){
		if ($refsheet == null) $refsheet = $sheet;
		if (($refrow !== null) && ($refcol !== null)) {
			$xf= (isset($this->cellblock[$refsheet][$refrow][$refcol]['xf'])) ? $this->cellblock[$refsheet][$refrow][$refcol]['xf'] : 0x0f;
		} else {
			$xf= (isset($this->cellblock[$sheet][$row][$col]['xf'])) ? $this->cellblock[$sheet][$row][$col]['xf'] : 0x0f;
		}
		switch($type){
		case 'Str':
			$header    = pack('vv', Type_LABELSST, 0x0a);
			$data      = pack('vvvV', $row, $col, $xf, $this->sstnum);
$this->ex_makesst($val);
			break;
		case 'Number':
			$packednum = (pack("N",1)==pack("L",1)) ? strrev(pack("d", $val)) : pack("d", $val);
			$header = pack('vv', Type_NUMBER, 0x0e);
			$data = pack('vvv', $row, $col, $xf).$packednum;
			break;
		case 'Blank':
			$header    = pack('vv', Type_BLANK, 0x06);
			$data      = pack('vvv', $row, $col, $xf);
			break;
		}
		$this->cbsheet[$sheet] .=$header.$data;
	}

}

/**
* convert UNIXTIME to MS-EXCEL time
* @param integer $timevalue UNIXTIME
* @return integer MS-EXCEL time
* @access public
* @example ./sample.php sample
*/
function unixtime2ms($timevalue) {
	return (($timevalue /60 /60 +9) /24 + 25569);
}
?>
