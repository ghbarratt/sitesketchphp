<?php

/**
 * Sitesketch Helper Functions
 */

if (!function_exists('isValidIP')) {
	function isValidIP($ip)
	{
		if (!empty($ip) && ip2long($ip) != -1) {
			$reserved_ips = array(
				array('0.0.0.0', '2.255.255.255'),
				array('10.0.0.0', '10.255.255.255'),
				array('127.0.0.0', '127.255.255.255'),
				array('169.254.0.0', '169.254.255.255'),
				array('172.16.0.0', '172.31.255.255'),
				array('192.0.2.0', '192.0.2.255'),
				array('192.168.0.0', '192.168.255.255'),
				array('255.255.255.0', '255.255.255.255')
			);
			foreach ($reserved_ips as $r) {
				$min = ip2long($r[0]);
				$max = ip2long($r[1]);
				if ((ip2long($ip) >= $min) && (ip2long($ip) <= $max)) {
					return false;
				}
			}
			return true;
		} else {
			return false;
		}
	}
}

if (!function_exists('getIP')) {
	function getIP()
	{
		if (!empty($_SERVER['HTTP_CLIENT_IP']) && isValidIP($_SERVER['HTTP_CLIENT_IP'])) {
			return $_SERVER['HTTP_CLIENT_IP'];
		}

		if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
			foreach (explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']) as $ip) {
				if (isValidIP(trim($ip))) {
					return $ip;
				}
			}
		}

		if (!empty($_SERVER['HTTP_X_FORWARDED']) && isValidIP($_SERVER['HTTP_X_FORWARDED'])) {
			return $_SERVER['HTTP_X_FORWARDED'];
		} elseif (!empty($_SERVER['HTTP_FORWARDED_FOR']) && isValidIP($_SERVER['HTTP_FORWARDED_FOR'])) {
			return $_SERVER['HTTP_FORWARDED_FOR'];
		} elseif (!empty($_SERVER['HTTP_FORWARDED']) && isValidIP($_SERVER['HTTP_FORWARDED'])) {
			return $_SERVER['HTTP_FORWARDED'];
		} elseif (!empty($_SERVER['HTTP_X_FORWARDED']) && isValidIP($_SERVER['HTTP_X_FORWARDED'])) {
			return $_SERVER['HTTP_X_FORWARDED'];
		} else {
			return isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : null;
		}
	}
}

if (!function_exists('forceHTTPS')) {
	function forceHTTPS()
	{
		if (isset($_SERVER)) {
			if (!isset($_SERVER['HTTPS']) || strtolower($_SERVER['HTTPS']) != 'on') {
				header('Location: https://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI']);
				exit;
			}
		}
	}
}

if (!function_exists('getPageAliasUsingURL')) {
	function getPageAliasUsingURL()
	{
		$url = !empty($_SERVER['SCRIPT_NAME']) ? $_SERVER['SCRIPT_NAME'] : (isset($_SERVER['PHP_SELF']) ? $_SERVER['PHP_SELF'] : '');
		$alias = str_replace('index.php', '', $url);
		// Remove first /
		while (substr($alias, 0, 1) == '/') {
			$alias = substr($alias, 1);
		}
		// Remove last /
		while (substr($alias, strlen($alias) - 1, 1) == '/') {
			$alias = substr($alias, 0, strlen($alias) - 1);
		}
		// Just take what comes after the LAST /
		if (strpos($alias, '/') !== false) {
			$alias = substr($alias, strrpos($alias, '/') + 1);
		}
		return $alias;
	}
}

if (!function_exists('isEmailValid')) {
	function isEmailValid($email)
	{
		if (preg_match('/^([a-zA-Z0-9])+([a-zA-Z0-9\._-]).*@([a-zA-Z0-9_-])+([a-zA-Z0-9\._-]+)+$/', $email)) {
			list($username, $domain) = explode('@', $email, 2);
			if (!checkdnsrr($domain, 'MX')) {
				return false;
			}
		}
		return true;
	}
}

if (!function_exists('getContentFromFile')) {
	function getContentFromFile($filename, $line_prefix = '')
	{
		$file_text = getTextFromFile($filename);
		$content = '';

		$file_lines = explode("\n", $file_text);
		foreach ($file_lines as $Line) {
			$content .= $line_prefix . $Line . "\n";
		}

		$replacements = array(
			'elevated' => array(
				'start_tag' => '<span type="elevated">',
				'end_tag' => '</span>',
				'function' => 'wrapElevatedContent'
			),
			'quote' => array(
				'start_tag' => '<q>',
				'end_tag' => '</q>',
				'function' => 'wrapQuoteContent'
			),
		);

		foreach ($replacements as $r) {
			$start_position = strpos($content, $r['start_tag']);

			while ($start_position !== false) {
				$end_position = strpos($content, $r['end_tag'], $start_position);
				if ($end_position !== false) {
					$to_replace = substr($content, $start_position, (($end_position + strlen($r['end_tag'])) - $start_position));
					$inside_content = substr($content, $start_position + strlen($r['start_tag']), ($end_position - ($start_position + strlen($r['start_tag']))));
					$replace_with = function_exists($r['function']) ? $r['function']($inside_content, $line_prefix) : $inside_content;
					$content = str_replace($to_replace, $replace_with, $content);
				}
				$start_position = strpos($content, $r['start_tag']);
			}
		}

		return $content;
	}
}

if (!function_exists('getTextFromFile')) {
	function getTextFromFile($filename, $Debugging = false)
	{
		$file_text = '';
		$FileHandler = @fopen($filename, 'r');
		if ($FileHandler) {
			$file_text = fread($FileHandler, filesize($filename));
			fclose($FileHandler);
		} else {
			$file_text = 'ERROR: fopen could not open ' . $filename . ".\n";
		}

		return $file_text;
	}
}

if (!function_exists('adjustLinePrefix')) {
	function adjustLinePrefix($amount = 0)
	{
		global $line_prefix;

		if (!isset($line_prefix)) {
			$line_prefix = "\t\t\t\t";
		}

		if (!is_int($amount)) {
			return false;
		}

		if ($amount < 0) {
			$line_prefix = substr($line_prefix, abs($amount));
		} else {
			for ($i = 0; $i < $amount; $i++) {
				$line_prefix = "\t" . $line_prefix;
			}
		}

		return $line_prefix;
	}
}

if (!function_exists('getButtonContent')) {
	function getButtonContent($alias, $id, $link, $alt = '', $extra = '')
	{
		global $line_prefix;

		if (!isset($line_prefix)) {
			$line_prefix = "\t\t\t\t";
		}

		$content = $line_prefix . "<a href=\"" . $link . "\">\n";
		$content .= $line_prefix . "\t<img src=\"/images/button_" . $alias . '_up.gif" alt="' . $alt . '" id="' . $id . '" name="' . $id . '" onmouseover="changeImage(\'' . $id . '\', \'/images/button_' . $alias . '_hover.gif\');" onmouseout="changeImage(\'' . $id . '\', \'/images/button_' . $alias . '_up.gif\');" onmousedown="changeImage(\'' . $id . '\', \'/images/button_' . $alias . '_down.gif\');" onmouseup="changeImage(\'' . $id . '\', \'/images/button_' . $alias . '_hover.gif\');" ' . $extra . ">\n";
		$content .= $line_prefix . "</a>\n";

		return $content;
	}
}

if (!function_exists('getSelectInputContent')) {
	function getSelectInputContent($alias, $values, $labels = false, $default = false, $extra_attributes = '')
	{
		global $line_prefix;

		if (!is_array($values)) {
			return false;
		}
		if (!is_array($labels)) {
			$labels = $values;
		}

		$content = '';
		$content .= $line_prefix . '<select id="' . $alias . '" name="' . $alias . '" ' . $extra_attributes . ">\n";
		for ($i = 0; $i < count($values); $i++) {
			$temp_values = $values[$i];
			$temp_label = isset($labels[$i]) ? $labels[$i] : $temp_values;

			$content .= $line_prefix . "\t<option value=\"" . $temp_values . '"';
			if (($temp_values == $default) || ($temp_label == $default)) {
				$content .= ' selected';
			}
			$content .= '>' . $temp_label . "</option>\n";
		}
		$content .= $line_prefix . "</select>\n";

		return $content;
	}
}

if (!function_exists('getSelectContent')) {
	function getSelectContent($alias, $values, $labels = false, $default = false, $extra_attributes = '')
	{
		return getSelectInputContent($alias, $values, $labels, $default, $extra_attributes);
	}
}

if (!function_exists('getDropDownInputContent')) {
	function getDropDownInputContent($alias, $values, $labels = false, $default = false, $extra_attributes = '')
	{
		return getSelectInputContent($alias, $values, $labels, $default, $extra_attributes);
	}
}

if (!function_exists('getRadioInputContent')) {
	function getRadioInputContent($alias, $values, $labels = false, $default = false, $extra_attributes = '')
	{
		global $line_prefix;

		if (!is_array($values)) {
			return false;
		}
		if (!is_array($labels)) {
			$labels = $values;
		}

		$content = '';
		for ($i = 0; $i < count($values); $i++) {
			$temp_values = $values[$i];
			$temp_label = isset($labels[$i]) ? $labels[$i] : $temp_values;

			$content .= $line_prefix . "\t<input type=\"radio\" name=\"" . $alias . '" value="' . $temp_values . '"';
			if (($temp_values == $default) || ($temp_label == $default)) {
				$content .= ' checked';
			}
			$content .= '>' . $temp_label . "\n";
		}

		return $content;
	}
}

if (!function_exists('getDateInputsContent')) {
	function getDateInputsContent($alias = false, $default_date = false, $min_year = false, $max_year = false, $format = false, $place_base_input = 'hidden')
	{
		global $line_prefix;

		if (!$line_prefix) {
			$line_prefix = "\t\t\t\t";
		}

		if (isset($_POST[$alias]) && $_POST[$alias]) {
			$default_date = $_POST[$alias];
		}

		$default_year = null;
		$default_month = null;
		$default_day = null;

		if (is_numeric($default_date)) {
			if (strlen((string)$default_date) == 8) {
				// Eightdate
				$default_year = intval(substr((string)$default_date, 0, 4));
				$default_month = intval(substr((string)$default_date, 4, 2));
				$default_day = intval(substr((string)$default_date, 6, 2));
			} elseif (strlen((string)$default_date) == 6) {
				// Sixdate
				$default_year = 2000 + intval(substr((string)$default_date, 0, 2));
				$default_month = intval(substr((string)$default_date, 2, 2));
				$default_day = intval(substr((string)$default_date, 4, 2));
			} else {
				// Unix timestamp
				$default_year = intval(date('Y', $default_date));
				$default_month = intval(date('m', $default_date));
				$default_day = intval(date('d', $default_date));
			}
		} elseif (is_string($default_date) && strlen($default_date) > 7) {
			$dash_parts = explode('-', $default_date);
			$slash_parts = explode('/', $default_date);
			if (count($dash_parts) == 3) {
				$default_year = $dash_parts[0];
				$default_month = $dash_parts[1];
				$default_day = $dash_parts[2];
			} elseif (count($slash_parts) == 3) {
				$default_year = $slash_parts[0];
				$default_month = $slash_parts[1];
				$default_day = $slash_parts[2];
			} elseif (strlen($default_date) == 10) {
				// YYYYXMMXDD
				$default_year = intval(substr($default_date, 0, 4));
				$default_month = intval(substr($default_date, 5, 2));
				$default_day = intval(substr($default_date, 8, 2));
			} elseif (strlen($default_date) == 8) {
				// YYXMMXDD
				$default_year = intval(substr($default_date, 0, 2));
				$default_month = intval(substr($default_date, 3, 2));
				$default_day = intval(substr($default_date, 7, 2));
			} else {
				$time = strtotime($default_date);
				$default_year = intval(date('Y', $time));
				$default_month = intval(date('m', $time));
				$default_day = intval(date('d', $time));
			}
		}

		if (is_numeric($min_year)) {
			if (strlen((string)$min_year) == 4) {
				$min_year = intval(substr((string)$min_year, 0, 4));
			} elseif (strlen((string)$min_year) == 2) {
				$min_year = 2000 + intval($min_year);
			} else {
				$min_year = intval(date('Y', $min_year));
			}
		} else {
			$min_year = intval(date('Y')) - 10;
		}

		if (is_numeric($max_year)) {
			if (strlen((string)$max_year) == 4) {
				$max_year = intval(substr((string)$max_year, 0, 4));
			} elseif (strlen((string)$max_year) == 2) {
				$max_year = 2000 + intval($max_year);
			} else {
				$max_year = intval(date('Y', $max_year));
			}
		} else {
			$max_year = intval(date('Y')) + 10;
		}

		if ($default_year) {
			if ($min_year > $default_year) {
				$min_year = $default_year;
			}
			if ($max_year < $default_year) {
				$max_year = $default_year;
			}
		}

		// Build the onchange which should just update the "hidden field" when one of the select drop downs get changed
		if ($format && stripos($format, 'timestamp') !== false) {
			$on_change = 'onchange=' .
				'"' .
				'{' .
				"year=this.form['" . $alias . "[year]'].value;" .
				"month=this.form['" . $alias . "[month]'].value;" .
				"day=this.form['" . $alias . "[day]'].value;" .
				'd=new Date();' .
				'd.setFullYear(year,parseInt(month)-1,day);' .
				"this.form['" . $alias . "'].value=parseInt(d.getTime()/1000)+" . (-(mktime() - mktime(0, 0, 0))) . ';' .
				'}' .
				'"';
		} else {
			$on_change = 'onchange=' .
				'"' .
				"this.form['" . $alias . "'].value = this.form['" . $alias . "[year]'].value+'-'+this.form['" . $alias . "[month]'].value+'-'+this.form['" . $alias . "[day]'].value;" .
				'"';
		}

		$content = '';

		$year_labels = array('year');
		$year_values = array('');
		for ($temp_year = $min_year; $temp_year <= $max_year; $temp_year++) {
			$year_labels[] = $temp_year;
			$year_values[] = $temp_year;
		}
		$year_input_content = getSelectInputContent($alias . '[year]', $year_values, $year_labels, $default_year, $on_change);

		$month_labels = array('month');
		$month_values = array('');
		for ($temp_month = 1; $temp_month <= 12; $temp_month++) {
			$month_labels[] = date('M', mktime(0, 0, 0, $temp_month, 1));
			$month_values[] = $temp_month;
		}
		$month_input_content = getSelectInputContent($alias . '[month]', $month_values, $month_labels, $default_month, $on_change);

		$day_labels = array('day');
		$day_values = array('');
		for ($temp_day = 1; $temp_day <= 31; $temp_day++) {
			$day_labels[] = $temp_day;
			$day_values[] = $temp_day;
		}
		$day_input_content = getSelectInputContent($alias . '[day]', $day_values, $day_labels, $default_day, $on_change);

		if ($format && (stripos($format, 'mdy') !== false || stripos($format, 'md,y') !== false)) {
			// Month day year order
			$content .= $month_input_content . $day_input_content;
			if (stripos($format, 'md,y') !== false) {
				$content .= ',';
			}
			$content .= $year_input_content;
		} elseif ($format && (stripos($format, 'my') !== false || stripos($format, 'ym') !== false) && stripos($format, 'd') === false) {
			// No day
			if (stripos($format, 'ym') !== false) {
				$content = $year_input_content . $month_input_content;
			} else {
				$content .= $month_input_content . $year_input_content;
			}
			$content .= '<input name="' . $alias . '[day]" type="hidden" value="1" />';
		} else {
			// Logical order = ymd
			$content .= $year_input_content . $month_input_content . $day_input_content;
		}

		if ($format && stripos($format, 'timestamp') !== false) {
			$formatted_default_date = $default_date;
		} else {
			$formatted_default_date = $default_year . '-' . $default_month . '-' . $default_day;
		}

		if ($place_base_input) {
			$content .= $line_prefix . '<input type="' . $place_base_input . '" name="' . $alias . '" value="' . $formatted_default_date . "\">\n";
		}

		return $content;
	}
}

if (!function_exists('getDOBInputsContent')) {
	function getDOBInputsContent($min_age = 0, $max_age = 110)
	{
		global $line_prefix;

		if (!$line_prefix) {
			$line_prefix = "\t\t\t\t";
		}

		$content = "<!-- DOB INPUTS START HERE -->\n";

		$default_year = intval(date('Y')) - (int)(($min_age + $max_age) / 2);
		if (isset($_POST['dob_year']) && $_POST['dob_year']) {
			$default_year = $_POST['dob_year'];
		}
		$years = array();
		for ($temp_year = intval(date('Y')) - $max_age; $temp_year <= intval(date('Y')) - ($min_age - 1); $temp_year++) {
			$years[] = $temp_year;
		}

		$content .= getSelectInputContent('dob_year', $years, false, $default_year);

		$default_month = false;
		if (isset($_POST['dob_month']) && $_POST['dob_month']) {
			$default_month = $_POST['dob_month'];
		}
		$months = array();
		$month_labels = array();
		for ($temp_month = 1; $temp_month <= 12; $temp_month++) {
			$month_labels[] = date('M', mktime(0, 0, 0, $temp_month, 1));
			$months[] = $temp_month;
		}

		$content .= getSelectInputContent('dob_month', $months, $month_labels, $default_month);

		$default_day = false;
		if (isset($_POST['dob_day']) && $_POST['dob_day']) {
			$default_day = $_POST['dob_day'];
		}
		$days = array();
		$day_labels = array();
		for ($temp_day = 1; $temp_day <= 31; $temp_day++) {
			$days[] = $temp_day;
			$day_labels[] = $temp_day;
		}

		$content .= getSelectInputContent('dob_day', $days, $day_labels, $default_day);

		$content .= "<!-- DOB INPUTS END HERE -->\n";

		return $content;
	}
}
