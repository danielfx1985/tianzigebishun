<?php
//error_reporting(0);
include_once 'Pinyin.php';

$words=$_POST['words']??'';//笔顺字

$bglx=$_POST['types']??'tzg';//表格类型，默认田字格
$bgcolor=$_POST['bgcolor']??'black';//表格颜色

if($_POST['bgcolor']!='black'){
	$bglx=$bglx.$bgcolor;//表格颜色变化
}

$z_color=$_POST['zcolor']??'black';//主字体颜色
$f_color=$_POST['fcolor']??'5';//辅字体颜色
$title=$_POST['title']??'';//辅字体颜色
$bs=$_POST['bs']??'0';//笔顺填充
$py=$_POST['py']??'0';//拼音
$show_strokes=intval($_POST['show_strokes']??1);//是否显示笔顺
$layout=$_POST['layout']??'single';//排列方式：single=按字分行，flow=连续排列
// 连续排列不支持笔顺展开（笔顺格会把参考字淹没在大量浅色格中）
if($layout === 'flow') $show_strokes = 0;
$cols=max(5, min(30, intval($_POST['cols']??12)));//每行列数
$rows=max(5, min(30, intval($_POST['rows']??15)));//每页行数
$font=preg_replace('/["\'\<\>\{\}\\\\]/', '', trim($_POST['font']??''));//自定义字体

// 根据列数等比缩放格子和SVG（基准：12列时格子80px，SVG 54px）
$page_width = 938;
$cell = floor($page_width / $cols);
$svg_dim = round($cell * 54 / 80);
$scale_x = round(0.058 * $svg_dim / 54 * 10000) / 10000;
$scale_y = round(0.0572 * $svg_dim / 54 * 10000) / 10000;
$translate_y = round(48 * $svg_dim / 54);
$margin_top_svg = round(-11 * $svg_dim / 54);
$font_size_li = round(58 * $cell / 80);
$line_height_li = round(85 * $cell / 80);
$py_font_size = max(8, round(13 * $cell / 80));

/*过滤掉空白换行，保留汉字和常用中文标点*/
$words = preg_replace('/\s+/u', '', $words);
preg_match_all("/[\x{4e00}-\x{9fff}\x{3400}-\x{4dbf}\x{3000}-\x{303f}\x{ff00}-\x{ffef}\x{201c}\x{201d}\x{2018}\x{2019}\x{3001}\x{2026}\x{2014}\x{ff5e}\x{3010}\x{3011}\x{300a}\x{300b}\x{ff08}\x{ff09}]/u", $words, $matches);
$words = implode('', $matches[0]);


//没有文字，跳转
if(!$words){
	header("Location: /");
	exit();
}

/*主字体颜色*/
$color=[
'green'=>'0,176,80',//绿色
'black'=>'0,0,0',//黑色
'red'=>'152,15,41',//红色
];

/*辅字体颜色*/
$fz_color=[
'10'=>'255,255,255',//白色

'green1'=>'136,255,136',//绿色1
'green2'=>'153,255,153',//绿色2
'green3'=>'160,255,160',//绿色3
'green4'=>'170,255,170',//绿色4
'green5'=>'184,255,184',//绿色5
'green6'=>'204,255,204',//绿色6

'black1'=>'136,136,136',//黑色1
'black2'=>'153,153,153',//黑色2
'black3'=>'160,160,160',//黑色3
'black4'=>'170,170,170',//黑色4
'black5'=>'184,184,184',//黑色5
'black6'=>'204,204,204',//黑色6

'red1'=>'255,136,136',//红色1
'red2'=>'255,153,153',//红色2
'red3'=>'255,160,160',//红色3
'red4'=>'255,170,170',//红色4
'red5'=>'255,184,184',//红色5
'red6'=>'255,204,204',//红色6
];

$color=$color[$z_color];//显示主颜色

$fcolor=$fz_color[$z_color.$f_color];//辅助颜色

if($f_color=='10'){
	$fcolor=$fz_color['10'];
}
?><!doctype html>
<html>
<head>
<meta charset="utf-8">
<title>田字格字帖生成器</title>
<style>
body,div,p,ul,li{ padding:0; margin:0; list-style:none;}
div{ width:<?=$cell*$cols?>px; margin:0 auto; }
li{display: inline-block; vertical-align: top; width:<?=$cell?>px; height:<?=$cell?>px; font-family:<?=$font ? '"'.addslashes($font).'", ' : ''?>"楷体","楷体_gb2312", "Kaiti SC", STKaiti, "AR PL UKai CN", "AR PL UKai HK", "AR PL UKai TW", "AR PL UKai TW MBE", "AR PL KaitiM GB", KaiTi, KaiTi_GB2312, DFKai-SB, "TW\-Kai"; font-size:<?=$font_size_li?>px; text-align:center; line-height:<?=$line_height_li?>px; background:url(img/<?=$bglx;?>.svg); background-size:100% 100%; margin:0; color:#b8b8b8; }
li.f{color:#000;}
li.svg{line-height:<?=$cell?>px;}
li svg{ vertical-align:middle;}
.afterpage{ page-break-before:always;}
.page-head{height: 116px;line-height: 136px; font-size: 32px;text-align: center;display: none;color: #666666}
@media print{.afterpage{ page-break-before:always;}.page-head{display: block;}}
@page {size: auto;margin: 5mm 16mm 5mm 16mm;}
</style>
</head>
<body>
<div>
<ul>
<?php

preg_match_all("/./u",$words,$hz);

// SVG 公共开始标签
$svg_open = '<svg width="'.$svg_dim.'" height="'.$svg_dim.'" style="margin-top:'.$margin_top_svg.'px;"><g transform="translate(-2.9,'.$translate_y.') scale('.$scale_x.', -'.$scale_y.')">';
$svg_close = '</g></svg>';

// 查找字符对应的笔顺数据文件，找不到返回 null
function find_bishun($hz_char) {
	$hzGBK = iconv('UTF-8', 'GB2312//IGNORE', $hz_char);
	if($hzGBK && file_exists("bishun_data/".$hzGBK.".json")) return "bishun_data/".$hzGBK.".json";
	if(file_exists("bishun_data/".$hz_char.".json")) return "bishun_data/".$hz_char.".json";
	return null;
}

// 输出完整字符 li（含可选拼音）
function render_char_cell($hz_char, $data, $color, $py, $py_font_size, $cell, $svg_open, $svg_close) {
	if($py) {
		$py_str = Pinyin::getPinyin($hz_char);
		$li = '<li class="svg" style="position:relative;"><span style="font-size:'.$py_font_size.'px;font-weight:bolder;display:block;position:absolute;width:'.$cell.'px;color:rgb('.$color.')">'.$py_str.'</span>'.$svg_open;
	} else {
		$li = '<li class="svg">'.$svg_open;
	}
	foreach ($data['strokes'] as $v) {
		$li .= '<path d="'.$v.'" style="fill:rgb('.$color.');stroke:rgb('.$color.');" stroke-width="0"></path>';
	}
	$li .= $svg_close.'</li>';
	return $li;
}

// 无笔顺数据时（标点等）以文字渲染
function render_text_cell($hz_char, $cell) {
	$fs = round($cell * 0.6);
	return '<li class="svg" style="font-size:'.$fs.'px;line-height:'.$cell.'px;">'.htmlspecialchars($hz_char).'</li>';
}

// 自定义字体时用 CSS 文字渲染（flow 模式）
function render_char_cell_font($hz_char, $fcolor, $py, $py_font_size, $cell) {
	$style = 'color:rgb('.$fcolor.');line-height:'.$cell.'px;';
	if($py) {
		$py_str = Pinyin::getPinyin($hz_char);
		return '<li class="svg" style="position:relative;'.$style.'"><span style="font-size:'.$py_font_size.'px;font-weight:bolder;display:block;position:absolute;width:'.$cell.'px;">'.$py_str.'</span>'.htmlspecialchars($hz_char).'</li>';
	}
	return '<li class="svg" style="'.$style.'">'.htmlspecialchars($hz_char).'</li>';
}

// 输出笔顺步骤 li
function render_stroke_cell($data, $step, $fcolor, $svg_open, $svg_close) {
	$li = '<li class="svg">'.$svg_open;
	for($ii=0;$ii<=$step;$ii++) {
		$li .= '<path d="'.$data['strokes'][$ii].'" style="fill:rgb('.$fcolor.');stroke:rgb('.$fcolor.');" stroke-width="0"></path>';
	}
	$li .= $svg_close.'</li>';
	return $li;
}

if($layout === 'flow') {
	// 连续排列：所有格子顺序流动，按总格数分页
	$cells_per_page = $rows * $cols;
	$rendered = 0;

	$emit = function($li) use (&$rendered, $cells_per_page) {
		echo $li;
		$rendered++;
		if($rendered % $cells_per_page === 0) {
			echo "</ul></div><div class='afterpage'><ul>";
		}
	};

	for($ihz=0; $ihz<count($hz['0']); $ihz++) {
		$hz_char = $hz['0'][$ihz];

		if($font) {
			// 自定义字体：直接用 CSS 文字渲染，不依赖笔顺数据
			$emit(render_char_cell_font($hz_char, $fcolor, $py, $py_font_size, $cell));
			continue;
		}

		$data_file = find_bishun($hz_char);
		if(!$data_file) {
			$emit(render_text_cell($hz_char, $cell));
			continue;
		}
		$data = json_decode(file_get_contents($data_file), 1);
		$count = count($data['strokes']);

		$emit(render_char_cell($hz_char, $data, $fcolor, $py, $py_font_size, $cell, $svg_open, $svg_close));

		if($show_strokes) {
			for($i=0; $i<$count; $i++) {
				$emit(render_stroke_cell($data, $i, $fcolor, $svg_open, $svg_close));
			}
		}
	}

	// 补满最后一页
	$remaining = ($cells_per_page - $rendered % $cells_per_page) % $cells_per_page;
	for($i=0; $i<$remaining; $i++) {
		echo '<li class="svg">&nbsp;</li>';
	}

} else {
	// 按字分行：每字独立占行，与原有逻辑一致
	for($ihz=0;$ihz<count($hz['0']);$ihz++){
		$hz_char = $hz['0'][$ihz];
		$data_file = find_bishun($hz_char);

		if(!$data_file) {
			// 无笔顺数据（标点等），文字渲染，占1格补满行
			echo render_text_cell($hz_char, $cell);
			$kg = ($cols - 1) % $cols === 0 ? 0 : $cols - 1;
			for($i=0;$i<$kg;$i++) echo '<li class="svg">&nbsp;</li>';
			$tzg_hs[] = 1;
			$arraytzg = intval(array_sum($tzg_hs)) / $rows;
			if(is_int($arraytzg)) echo "</ul></div><div class='afterpage'><ul>";
			continue;
		}

		$data=json_decode(file_get_contents($data_file),1);
		$count=count($data['strokes']);

		echo render_char_cell($hz_char, $data, $color, $py, $py_font_size, $cell, $svg_open, $svg_close);

		if($show_strokes){
			for($i=0;$i<$count;$i++){
				echo render_stroke_cell($data, $i, $fcolor, $svg_open, $svg_close);
			}
		}

		/*计算当前字剩余空格（补齐到整行）*/
		$total_cells = $show_strokes ? $count + 1 : 1;
		$used = $total_cells % $cols;
		$kg = $used == 0 ? 0 : $cols - $used;

		/*行数不够，填充*/
		if($kg and $bs){
			for($i=0;$i<$kg;$i++){
				echo '<li class="svg">'.$svg_open;
				foreach ($data['strokes'] as $v){
					echo '<path d="'.$v.'" style="fill:rgb('.$fcolor.');stroke:rgb('.$fcolor.');" stroke-width="0"></path>';
				}
				echo $svg_close.'</li>';
			}
		}
		if($kg and !$bs){
			for($i=0;$i<$kg;$i++){
				echo '<li class="svg">&nbsp;</li>';
			}
		}

		/*分页显示标题头部*/
		$tzg12 = $show_strokes ? ($count+1)/$cols : 1/$cols;
		$tzg_hs[]= ceil($tzg12);
		$arraytzg=intval(array_sum($tzg_hs));
		$arraytzg=$arraytzg/$rows;
		if(is_int($arraytzg)){
			echo "</ul></div><div class='afterpage'><ul>";
		}

	}

	//堆满整页
	$tzg_hs=array_sum($tzg_hs);
	$tzgzys=ceil($tzg_hs/$rows);
	$zhengye=($tzgzys*$rows-$tzg_hs)*$cols;

	for($i=0;$i<$zhengye;$i++){
		echo "<li>&nbsp;</li>";
	}
}

?>
</ul>
</div>
<div style="display: none;">

</div>
<div id="page-head-box" style="display: none;">
<div class="page-head"><?=$title;?></div>
</div>

<script src="https://ajax.aspnetcdn.com/ajax/jquery/jquery-2.1.1.min.js"></script>
<script type="text/javascript">
    $('body').prepend($('#page-head-box').html());
    $('.afterpage').prepend($('#page-head-box').html());
    window.onload = function(){
        setTimeout(function(){window.print(); }, 1000);
    }
</script>
