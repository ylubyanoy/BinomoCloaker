<?php
//Подгрузка контента из другой папки через CURL
function load_content($url,$land_number) {
	global $fb_use_pageview;
	$domain = $_SERVER['HTTP_HOST'];
	$prefix = isset($_SERVER['HTTPS']) ? 'https://' : 'http://';
	$fullpath = $prefix.$domain.'/'.$url.'/';
	$querystr = $_SERVER['QUERY_STRING'];
	if (!empty($querystr))
		$fullpath = $fullpath.'?'.$querystr;
	
	$curl = curl_init();
	$optArray = array(
			CURLOPT_URL => $fullpath,
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_SSL_VERIFYPEER => false, );
	curl_setopt_array($curl, $optArray);
	$html = curl_exec($curl);
	curl_close($curl);
	$baseurl = '/'.$url.'/';
	//переписываем все относительные src,href & action (не начинающиеся с http)
	$html = preg_replace('/\ssrc=[\'\"](?!http)([^\'\"]+)[\'\"]/', " src=\"$baseurl\\1\"", $html);
	$html = preg_replace('/\shref=[\'\"](?!http|#)([^\'\"]+)[\'\"]/', " href=\"$baseurl\\1\"", $html);
	$html = preg_replace('/\saction=[\'\"](?!http)([^\'\"]+)[\'\"]/', " action=\"$baseurl\\1\"", $html);

	//добавляем в страницу скрипт GTM
	$html = insert_gtm_script($html);
	//добавляем в страницу скрипт Yandex Metrika
	$html = insert_yandex_script($html);
	//добавляем в страницу скрипт Facebook Pixel с событием PageView
	if ($fb_use_pageview)
		$html = insert_fb_pixel_script($html,'PageView');
	//добавляем во все формы сабы
	$html = insert_subs($html);
	//добавляем в формы id пикселя фб
	$html = insert_fbpixel_id($html);
	
	if ($land_number>=0)
	{
		$html = preg_replace('/(<a[^>]+href=")([^"]*)/', "\\1".$prefix.$domain.'/landing.php?l='.$land_number.'&'.(!empty($querystr)?$querystr:''), $html);
	}

	return $html;
}

function load_white_curl($url){
	$curl = curl_init();
	$optArray = array(
			CURLOPT_URL => $url,
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_SSL_VERIFYPEER => false, 
			CURLOPT_FOLLOWLOCATION => true);
	curl_setopt_array($curl, $optArray);
	$html = curl_exec($curl);
	curl_close($curl);
	//добавляем в страницу скрипт Facebook Pixel
	$html = insert_fb_pixel_script($html,'PageView');
	return $html;	
}

//вставляет все сабы в hidden полях каждой формы
function insert_subs($html) {
	$all_subs = '';
	if (!empty($_GET['sub1']))
		$all_subs = $all_subs.'<input type="hidden" name="sub1" value="'.$_GET['sub1'].'"/>';
	if (!empty($_GET['sub2']))
		$all_subs = $all_subs.'<input type="hidden" name="sub2" value="'.$_GET['sub2'].'"/>';
	if (!empty($_GET['sub3']))
		$all_subs = $all_subs.'<input type="hidden" name="sub3" value="'.$_GET['sub3'].'"/>';
	if (!empty($_GET['sub4']))
		$all_subs = $all_subs.'<input type="hidden" name="sub4" value="'.$_GET['sub4'].'"/>';
	if (!empty($_GET['sub5']))
		$all_subs = $all_subs.'<input type="hidden" name="sub5" value="'.$_GET['sub5'].'"/>';
	$needle = '</form>';
	return insert_before_tag($html,$needle,$all_subs);
}

//если в querystring есть id пикселя фб, то встраиваем его скрытым полем в форму на лендинге
//чтобы потом передать его на страницу "Спасибо" через send.php и там отстучать Lead
function insert_fbpixel_id($html) {
	$fbpixel_subname="px"; //имя параметра из querystring, в которой будет лежать ID пикселя
	$fb_pixel = isset($_GET[$fbpixel_subname])?$_GET[$fbpixel_subname]:'';
	if (empty($fb_pixel)) return $html;
	$fb_input = '<input type="hidden" name="'.$fbpixel_subname.'" value="'.$fb_pixel.'"/>';
	$needle = '</form>';
	return insert_before_tag($html,$needle,$fb_input);
}

//вставляет в head полный код пикселя фб с указанным в $event событим (Lead,PageView,Purchase итп)
function insert_fb_pixel_script($html,$event){
	$fbpixel_subname="px"; //имя параметра из querystring, в которой будет лежать ID пикселя
	$fb_pixel = isset($_GET[$fbpixel_subname])?$_GET[$fbpixel_subname]:'';
	if (empty($fb_pixel)) return $html;
	$file_name='scripts/fbpxcode.js';
	if (!file_exists($file_name)) return $html;
	$px_code = file_get_contents($file_name);	
	if (empty($px_code)) return $html;

	$search='{PIXELID}';
	$px_code = str_replace($search,$fb_pixel,$px_code);
	$search='{EVENT}';
	$px_code = str_replace($search,$event,$px_code);

	$needle='</head>';
	return insert_before_tag($html,$needle,$px_code);
}

//если задан ID Google Tag Manager, то вставляем его скрипт
function insert_gtm_script($html) {
	global $gtm_id;
	if ($gtm_id=='' || empty($gtm_id)) return $html;

	$code_file_name='scripts/gtmcode.js';
	if (!file_exists($code_file_name)) return $html;
	$gtm_code = file_get_contents($code_file_name);
	if (empty($gtm_code))	return $html;

	$search = '{GTMID}';
	$gtm_code = str_replace($search,$gtm_id,$gtm_code);
	$needle='</head>';
	return insert_before_tag($html,$needle,$gtm_code);
}

//если задан ID Yandex Metrika, то вставляем её скрипт
function insert_yandex_script($html) {
	global $ya_id;
	if ($ya_id=='' || empty($ya_id)) return $html;
	
	$code_file_name='scripts/yacode.js';
	if (!file_exists($code_file_name)) return $html;
	$ya_code = file_get_contents($code_file_name);
	if (empty($ya_code))	return $html;

	$search = '{YAID}';
	$ya_code = str_replace($search,$ya_id,$ya_code);
	$needle='</head>';
	return insert_before_tag($html,$needle,$ya_code);
}


function insert_before_tag($html,$needle,$str_to_insert){
	$lastPos = 0;
	$positions = array();
	while (($lastPos = strpos($html, $needle, $lastPos)) !== false) {
		$positions[] = $lastPos;
		$lastPos = $lastPos + strlen($needle);
	}
	$positions = array_reverse($positions);

	foreach($positions as $pos) {
		$html = substr_replace($html, $str_to_insert, $pos, 0);
	}
	return $html;
}
?>