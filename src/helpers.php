<?php

/**
 * Sitesketch Helper Functions
 */

declare(strict_types=1);

if (!function_exists('isValidIP')) {
    function isValidIP($ip)
    {
        if (!empty($ip) && ip2long($ip) != -1) {
            $reservedIps = [
                ['0.0.0.0', '2.255.255.255'],
                ['10.0.0.0', '10.255.255.255'],
                ['127.0.0.0', '127.255.255.255'],
                ['169.254.0.0', '169.254.255.255'],
                ['172.16.0.0', '172.31.255.255'],
                ['192.0.2.0', '192.0.2.255'],
                ['192.168.0.0', '192.168.255.255'],
                ['255.255.255.0', '255.255.255.255'],
            ];
            foreach ($reservedIps as $r) {
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
        $url = !empty($_SERVER['SCRIPT_NAME']) ?
            $_SERVER['SCRIPT_NAME']
            : (isset($_SERVER['PHP_SELF']) ? $_SERVER['PHP_SELF'] : '');
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
    function getContentFromFile($filename, $linePrefix = '')
    {
        $fileText = getTextFromFile($filename);
        $content = '';

        $fileLines = explode("\n", $fileText);
        foreach ($fileLines as $line) {
            $content .= $linePrefix . $line . "\n";
        }

        $replacements = [
            'elevated' => [
                'start_tag' => '<span type="elevated">',
                'end_tag' => '</span>',
                'function' => 'wrapElevatedContent',
            ],
            'quote' => [
                'start_tag' => '<q>',
                'end_tag' => '</q>',
                'function' => 'wrapQuoteContent',
            ],
        ];

        foreach ($replacements as $r) {
            $startPosition = strpos($content, $r['start_tag']);

            while ($startPosition !== false) {
                $endPosition = strpos($content, $r['end_tag'], $startPosition);
                if ($endPosition !== false) {
                    $toReplace = substr(
                        $content,
                        $startPosition,
                        (($endPosition + strlen($r['end_tag'])) - $startPosition)
                    );
                    $insideContent = substr(
                        $content,
                        $startPosition + strlen($r['start_tag']),
                        ($endPosition - ($startPosition + strlen($r['start_tag'])))
                    );
                    $replaceWith = function_exists($r['function']) ?
                        $r['function']($insideContent, $linePrefix)
                        : $insideContent;
                    $content = str_replace($toReplace, $replaceWith, $content);
                }
                $startPosition = strpos($content, $r['start_tag']);
            }
        }

        return $content;
    }
}

if (!function_exists('getTextFromFile')) {
    function getTextFromFile($filename, $debugging = false)
    {
        $fileText = '';
        $fileHandler = @fopen($filename, 'r');
        if ($fileHandler) {
            $fileText = fread($fileHandler, filesize($filename));
            fclose($fileHandler);
        } else {
            $fileText = 'ERROR: fopen could not open ' . $filename . ".\n";
        }

        return $fileText;
    }
}

if (!function_exists('adjustLinePrefix')) {
    function adjustLinePrefix($amount = 0)
    {
        global $linePrefix, $line_prefix;

        if (!isset($linePrefix)) {
            $linePrefix = $line_prefix ?? "\t\t\t\t";
        }

        if (!is_int($amount)) {
            return false;
        }

        if ($amount < 0) {
            $linePrefix = substr($linePrefix, abs($amount));
        } else {
            for ($i = 0; $i < $amount; $i++) {
                $linePrefix = "\t" . $linePrefix;
            }
        }

        return $linePrefix;
    }
}

if (!function_exists('getButtonContent')) {
    function getButtonContent($alias, $id, $link, $alt = '', $extra = '')
    {
        global $linePrefix, $line_prefix;

        if (!isset($linePrefix)) {
            $linePrefix = $line_prefix ?? "\t\t\t\t";
        }

        $content = $linePrefix . "<a href=\"" . $link . "\">\n";
        $content .= $linePrefix . "\t<img src=\"/images/button_" . $alias . '_up.gif" alt="' . $alt . '" id="' . $id
            . '" name="' . $id . '" onmouseover="changeImage(\'' . $id . '\', \'/images/button_' . $alias
            . '_hover.gif\');" onmouseout="changeImage(\'' . $id . '\', \'/images/button_' . $alias
            . '_up.gif\');" onmousedown="changeImage(\'' . $id . '\', \'/images/button_' . $alias
            . '_down.gif\');" onmouseup="changeImage(\'' . $id . '\', \'/images/button_' . $alias . '_hover.gif\');" '
            . $extra . ">\n";
        $content .= $linePrefix . "</a>\n";

        return $content;
    }
}

if (!function_exists('getSelectInputContent')) {
    function getSelectInputContent($alias, $values, $labels = false, $default = false, $extraAttributes = '')
    {
        global $linePrefix, $line_prefix;

        if (!isset($linePrefix)) {
            $linePrefix = $line_prefix ?? "\t\t\t\t";
        }

        if (!is_array($values)) {
            return false;
        }
        if (!is_array($labels)) {
            $labels = $values;
        }

        $content = '';
        $content .= $linePrefix . '<select id="' . $alias . '" name="' . $alias . '" ' . $extraAttributes . ">\n";
        for ($i = 0; $i < count($values); $i++) {
            $tempValues = $values[$i];
            $tempLabel = isset($labels[$i]) ? $labels[$i] : $tempValues;

            $content .= $linePrefix . "\t<option value=\"" . $tempValues . '"';
            if (($tempValues == $default) || ($tempLabel == $default)) {
                $content .= ' selected';
            }
            $content .= '>' . $tempLabel . "</option>\n";
        }
        $content .= $linePrefix . "</select>\n";

        return $content;
    }
}

if (!function_exists('getSelectContent')) {
    function getSelectContent($alias, $values, $labels = false, $default = false, $extraAttributes = '')
    {
        return getSelectInputContent($alias, $values, $labels, $default, $extraAttributes);
    }
}

if (!function_exists('getDropDownInputContent')) {
    function getDropDownInputContent($alias, $values, $labels = false, $default = false, $extraAttributes = '')
    {
        return getSelectInputContent($alias, $values, $labels, $default, $extraAttributes);
    }
}

if (!function_exists('getRadioInputContent')) {
    function getRadioInputContent($alias, $values, $labels = false, $default = false, $extraAttributes = '')
    {
        global $linePrefix, $line_prefix;

        if (!isset($linePrefix)) {
            $linePrefix = $line_prefix ?? "\t\t\t\t";
        }

        if (!is_array($values)) {
            return false;
        }
        if (!is_array($labels)) {
            $labels = $values;
        }

        $content = '';
        for ($i = 0; $i < count($values); $i++) {
            $tempValues = $values[$i];
            $tempLabel = isset($labels[$i]) ? $labels[$i] : $tempValues;

            $content .= $linePrefix . "\t<input type=\"radio\" name=\"" . $alias . '" value="' . $tempValues . '"';
            if (($tempValues == $default) || ($tempLabel == $default)) {
                $content .= ' checked';
            }
            $content .= '>' . $tempLabel . "\n";
        }

        return $content;
    }
}

if (!function_exists('getDateInputsContent')) {
    function getDateInputsContent(
        $alias = false,
        $defaultDate = false,
        $minYear = false,
        $maxYear = false,
        $format = false,
        $placeBaseInput = 'hidden'
    ) {
        global $linePrefix, $line_prefix;

        if (!$linePrefix) {
            $linePrefix = $line_prefix ?? "\t\t\t\t";
        }

        if (isset($_POST[$alias]) && $_POST[$alias]) {
            $defaultDate = $_POST[$alias];
        }

        $defaultYear = null;
        $defaultMonth = null;
        $defaultDay = null;

        if (is_numeric($defaultDate)) {
            if (strlen((string)$defaultDate) == 8) {
                // Eightdate
                $defaultYear = intval(substr((string)$defaultDate, 0, 4));
                $defaultMonth = intval(substr((string)$defaultDate, 4, 2));
                $defaultDay = intval(substr((string)$defaultDate, 6, 2));
            } elseif (strlen((string)$defaultDate) == 6) {
                // Sixdate
                $defaultYear = 2000 + intval(substr((string)$defaultDate, 0, 2));
                $defaultMonth = intval(substr((string)$defaultDate, 2, 2));
                $defaultDay = intval(substr((string)$defaultDate, 4, 2));
            } else {
                // Unix timestamp
                $defaultYear = intval(date('Y', $defaultDate));
                $defaultMonth = intval(date('m', $defaultDate));
                $defaultDay = intval(date('d', $defaultDate));
            }
        } elseif (is_string($defaultDate) && strlen($defaultDate) > 7) {
            $dashParts = explode('-', $defaultDate);
            $slashParts = explode('/', $defaultDate);
            if (count($dashParts) == 3) {
                $defaultYear = $dashParts[0];
                $defaultMonth = $dashParts[1];
                $defaultDay = $dashParts[2];
            } elseif (count($slashParts) == 3) {
                $defaultYear = $slashParts[0];
                $defaultMonth = $slashParts[1];
                $defaultDay = $slashParts[2];
            } elseif (strlen($defaultDate) == 10) {
                // YYYYXMMXDD
                $defaultYear = intval(substr($defaultDate, 0, 4));
                $defaultMonth = intval(substr($defaultDate, 5, 2));
                $defaultDay = intval(substr($defaultDate, 8, 2));
            } elseif (strlen($defaultDate) == 8) {
                // YYXMMXDD
                $defaultYear = intval(substr($defaultDate, 0, 2));
                $defaultMonth = intval(substr($defaultDate, 3, 2));
                $defaultDay = intval(substr($defaultDate, 7, 2));
            } else {
                $time = strtotime($defaultDate);
                $defaultYear = intval(date('Y', $time));
                $defaultMonth = intval(date('m', $time));
                $defaultDay = intval(date('d', $time));
            }
        }

        if (is_numeric($minYear)) {
            if (strlen((string)$minYear) == 4) {
                $minYear = intval(substr((string)$minYear, 0, 4));
            } elseif (strlen((string)$minYear) == 2) {
                $minYear = 2000 + intval($minYear);
            } else {
                $minYear = intval(date('Y', $minYear));
            }
        } else {
            $minYear = intval(date('Y')) - 10;
        }

        if (is_numeric($maxYear)) {
            if (strlen((string)$maxYear) == 4) {
                $maxYear = intval(substr((string)$maxYear, 0, 4));
            } elseif (strlen((string)$maxYear) == 2) {
                $maxYear = 2000 + intval($maxYear);
            } else {
                $maxYear = intval(date('Y', $maxYear));
            }
        } else {
            $maxYear = intval(date('Y')) + 10;
        }

        if ($defaultYear) {
            if ($minYear > $defaultYear) {
                $minYear = $defaultYear;
            }
            if ($maxYear < $defaultYear) {
                $maxYear = $defaultYear;
            }
        }

        // Build the onchange which should just update the "hidden field" when one of the select drop downs get changed
        if ($format && stripos($format, 'timestamp') !== false) {
            $onChange = 'onchange="{' .
                "year=this.form['" . $alias . "[year]'].value;" .
                "month=this.form['" . $alias . "[month]'].value;" .
                "day=this.form['" . $alias . "[day]'].value;" .
                'd=new Date();' .
                'd.setFullYear(year,parseInt(month)-1,day);' .
                "this.form['" . $alias . "'].value=parseInt(d.getTime()/1000)+" . (-(mktime() - mktime(0, 0, 0))) . ';'
                . '}"';
        } else {
            $onChange = 'onchange="' .
                "this.form['" . $alias . "'].value = this.form['" . $alias . "[year]'].value+'-'+this.form['" . $alias
                . "[month]'].value+'-'+this.form['" . $alias . "[day]'].value;\"";
        }

        $content = '';

        $yearLabels = ['year'];
        $yearValues = [''];
        for ($tempYear = $minYear; $tempYear <= $maxYear; $tempYear++) {
            $yearLabels[] = $tempYear;
            $yearValues[] = $tempYear;
        }
        $yearInputContent = getSelectInputContent(
            $alias . '[year]',
            $yearValues,
            $yearLabels,
            $defaultYear,
            $onChange
        );

        $monthLabels = ['month'];
        $monthValues = [''];
        for ($tempMonth = 1; $tempMonth <= 12; $tempMonth++) {
            $monthLabels[] = date('M', mktime(0, 0, 0, $tempMonth, 1));
            $monthValues[] = $tempMonth;
        }
        $monthInputContent = getSelectInputContent(
            $alias . '[month]',
            $monthValues,
            $monthLabels,
            $defaultMonth,
            $onChange
        );

        $dayLabels = ['day'];
        $dayValues = [''];
        for ($tempDay = 1; $tempDay <= 31; $tempDay++) {
            $dayLabels[] = $tempDay;
            $dayValues[] = $tempDay;
        }
        $dayInputContent = getSelectInputContent(
            $alias . '[day]',
            $dayValues,
            $dayLabels,
            $defaultDay,
            $onChange
        );

        if ($format && (stripos($format, 'mdy') !== false || stripos($format, 'md,y') !== false)) {
            // Month day year order
            $content .= $monthInputContent . $dayInputContent;
            if (stripos($format, 'md,y') !== false) {
                $content .= ',';
            }
            $content .= $yearInputContent;
        } elseif (
            $format
            && (stripos($format, 'my') !== false || stripos($format, 'ym') !== false)
            && stripos($format, 'd') === false
        ) {
            // No day
            if (stripos($format, 'ym') !== false) {
                $content = $yearInputContent . $monthInputContent;
            } else {
                $content .= $monthInputContent . $yearInputContent;
            }
            $content .= '<input name="' . $alias . '[day]" type="hidden" value="1" />';
        } else {
            // Logical order = ymd
            $content .= $yearInputContent . $monthInputContent . $dayInputContent;
        }

        if ($format && stripos($format, 'timestamp') !== false) {
            $formattedDefaultDate = $defaultDate;
        } else {
            $formattedDefaultDate = $defaultYear . '-' . $defaultMonth . '-' . $defaultDay;
        }

        if ($placeBaseInput) {
            $content .= $linePrefix . '<input type="' . $placeBaseInput . '" name="' . $alias . '" value="'
                . $formattedDefaultDate . "\">\n";
        }

        return $content;
    }
}

if (!function_exists('getDOBInputsContent')) {
    function getDOBInputsContent($minAge = 0, $maxAge = 110)
    {
        global $linePrefix, $line_prefix;

        if (!$linePrefix) {
            $linePrefix = $line_prefix ?? "\t\t\t\t";
        }

        $content = "<!-- DOB INPUTS START HERE -->\n";

        $defaultYear = intval(date('Y')) - (int)(($minAge + $maxAge) / 2);
        if (isset($_POST['dob_year']) && $_POST['dob_year']) {
            $defaultYear = $_POST['dob_year'];
        }
        $years = [];
        for (
            $tempYear = intval(date('Y')) - $maxAge;
            $tempYear <= intval(date('Y')) - ($minAge - 1);
            $tempYear++
        ) {
            $years[] = $tempYear;
        }

        $content .= getSelectInputContent('dob_year', $years, false, $defaultYear);

        $defaultMonth = false;
        if (isset($_POST['dob_month']) && $_POST['dob_month']) {
            $defaultMonth = $_POST['dob_month'];
        }
        $months = [];
        $monthLabels = [];
        for ($tempMonth = 1; $tempMonth <= 12; $tempMonth++) {
            $monthLabels[] = date('M', mktime(0, 0, 0, $tempMonth, 1));
            $months[] = $tempMonth;
        }

        $content .= getSelectInputContent('dob_month', $months, $monthLabels, $defaultMonth);

        $defaultDay = false;
        if (isset($_POST['dob_day']) && $_POST['dob_day']) {
            $defaultDay = $_POST['dob_day'];
        }
        $days = [];
        $dayLabels = [];
        for ($tempDay = 1; $tempDay <= 31; $tempDay++) {
            $days[] = $tempDay;
            $dayLabels[] = $tempDay;
        }

        $content .= getSelectInputContent('dob_day', $days, $dayLabels, $defaultDay);

        $content .= "<!-- DOB INPUTS END HERE -->\n";

        return $content;
    }
}
