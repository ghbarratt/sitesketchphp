<?php

declare(strict_types=1);

namespace Sitesketch;

class ContentBuilder
{
    public static $templateExtension = 'tpl';
    public static $templateDirectory = 'templates';
    public static $endOfLine = "\n";
    public static $indentationUnit = "\t";
    public static $singularizePluralMagicKeys = false;
    public static $webDirectory = 'public';
    public static $sitePath;
    public static $siteWebPath;
    public static $sitesketchPath;

    protected static $tagBang = '!';
    protected static $tagPrefix = '~';
    protected static $tagPostfix = '~';
    protected static $tagOpenPrefix = '~';
    protected static $tagOpenPostfix = '{';
    protected static $tagClosePrefix = '}';
    protected static $tagClosePostfix = '~';

    public $linePrefix = '';
    public $replacements;
    public $removeLeftoverTags = true;
    public $useSmartIndentation = false; // faster

    protected $template;
    protected $content;
    protected $hasPlacedReplacements = false;
    protected $evalOpenTag = '~eval(';
    protected $evalCloseTag = ')eval~';
    protected $errors = [];

    public function __construct(
        $template = '',
        $replacements = false,
        $templateDirectory = false,
        $linePrefix = false,
        $indentationUnit = false,
        $tagPrefix = false,
        $tagPostfix = false
    ) {
        return $this->reset(
            $template,
            $replacements,
            $templateDirectory,
            $linePrefix,
            $indentationUnit,
            $tagPrefix,
            $tagPostfix
        );
    }

    public function reset(
        $template = '',
        $replacements = false,
        $templateDirectory = false,
        $linePrefix = false,
        $indentationUnit = false,
        $tagPrefix = false,
        $tagPostfix = false
    ) {
        $this->setTemplate($template);
        if ($replacements) {
            $this->replacements = $replacements;
        }
        if ($templateDirectory) {
            self::$templateDirectory = $templateDirectory;
        }
        if ($linePrefix) {
            $this->linePrefix = $linePrefix;
        }
        if ($indentationUnit) {
            $this->indentationUnit = $indentationUnit;
        }

        return true;
    }

    public static function getSitesketchPath()
    {
        if (self::$sitesketchPath) {
            return self::$sitesketchPath;
        }

        // What is the directory of the Sitesketch package root?
        self::$sitesketchPath = dirname(__DIR__);

        return self::$sitesketchPath;
    }

    public static function getSitePath()
    {
        if (isset(self::$sitePath) && strlen(self::$sitePath)) {
            return self::$sitePath;
        }

        $scriptFilename = $_SERVER['SCRIPT_FILENAME'] ?? '';
        $webPos = $scriptFilename !== '' ? strrpos($scriptFilename, self::$webDirectory) : false;
        if ($webPos !== false && isset($_SERVER['SCRIPT_FILENAME'])) {
            $siteWebPath = substr($_SERVER['SCRIPT_FILENAME'], 0, $webPos + strlen(self::$webDirectory));
        } elseif (isset($_SERVER['DOCUMENT_ROOT'])) {
            $siteWebPath = $_SERVER['DOCUMENT_ROOT'];
        } else {
            $siteWebPath = '';
        }

        self::$siteWebPath = $siteWebPath;

        // Usually site path is one directory up from the site web path
        self::$sitePath = dirname($siteWebPath);

        return self::$sitePath;
    }

    public static function getTemplateExtension()
    {
        return self::$templateExtension;
    }

    public function setTemplate($template)
    {
        if (!$template) {
            return false;
        }

        $ext = self::getTemplateExtension();
        $template = str_replace('.' . $ext . '.' . $ext, '.' . $ext, $template);

        $this->hasPlacedReplacements = false;

        $filepathsToTry = [
            $template,
            self::getSitePath() . '/' . $template,
            self::getSitePath() . '/' . self::$templateDirectory . '/' . $template,
            self::getSitePath() . '/templates/' . $template,
            self::getSitesketchPath() . '/resources/' . $template,
            self::getSitesketchPath() . '/resources/' . self::$templateDirectory . '/' . $template,
            self::getSitesketchPath() . '/resources/templates/' . $template,
        ];

        $templateFound = false;
        foreach ($filepathsToTry as $filepath) {
            if (is_file($filepath)) {
                $this->template = $filepath;
                $templateFound = true;
                break;
            }
        }

        if (!$templateFound) {
            $this->errors[] = 'The template file: "' . $template . '" could not be found. Path is: '
                . get_include_path();
        }

        $this->content = null;
    }

    public function useSmartIndentation($use = true)
    {
        $this->useSmartIndentation = $use;
    }

    public function adjustIndentation($amount = false)
    {
        $indentUnit = $this->indentationUnit ?? self::$indentationUnit;
        $linePrefix = $this->linePrefix ?? '';

        if ($amount > 0) {
            $this->linePrefix = str_pad($linePrefix, (int)$amount, $indentUnit, STR_PAD_LEFT);
        } elseif ($amount < 0) {
            $this->linePrefix = str_replace($indentUnit, '', $linePrefix, $amount);
        } else {
            return preg_match_all('/' . $indentUnit . '/', $linePrefix);
        }
    }

    public function adjustLinePrefix($amount)
    {
        return $this->adjustIndentation($amount);
    }

    public function setReplacements($replacements)
    {
        $this->replacements = $replacements;
    }

    public function addReplacements($replacements)
    {
        $this->replacements = array_merge($replacements, $this->replacements);
    }

    public function addReplacement($tag, $value)
    {
        $this->replacements = array_merge([$tag => $value], $this->replacements);
    }

    public function getTemplateContent($template = false)
    {
        if (!$template) {
            $template = $this->template;
        }

        if (empty($template) || !is_string($template)) {
            return (string)$template;
        }

        $isFilename = false;
        $ext = self::getTemplateExtension();

        if (strpos($template, '.' . $ext) && strpos($template, '<') === false) {
            $isFilename = true;
        }

        if ($isFilename) {
            return $this->loadTemplateContent($template);
        } else {
            return $template;
        }
    }

    public function getTemplateWithReplacementsContent($template = false, $replacements = false)
    {
        if (!$template) {
            $template = $this->template;
        }

        $templateContent = $this->getTemplateContent($template);

        if (!$replacements || !is_array($replacements)) {
            if (isset($this->replacements) && is_array($this->replacements)) {
                $replacements = $this->replacements;
            } else {
                $replacements = [];
            }
        }

        $content = $templateContent;

        // Find regular tags
        $pattern = '/' . self::$tagOpenPrefix . '(' . self::$tagBang . '?[A-z0-9,_]+)' . self::$tagOpenPostfix . '/';
        $matchCount = preg_match_all($pattern, $content, $matches);

        if ($matchCount) {
            $embedTags = $matches[1];
        } else {
            $embedTags = [];
        }

        $leftoverEmbedTags = [];

        if (isset($replacements) && is_array($replacements)) {
            foreach ($embedTags as $embedTagIndex => $tag) {
                $noBangTag = $tag;

                if (strpos($tag, self::$tagBang) !== false) {
                    $noBangTag = substr($tag, strlen(self::$tagBang));

                    if (!isset($replacements[$noBangTag]) || !$replacements[$noBangTag]) {
                        $replacements[$tag] = $replacements;
                    }
                } else {
                    if ($this->removeLeftoverTags && !in_array($tag, array_keys($replacements))) {
                        $replacements[$tag] = '';
                    }
                }

                if (in_array($noBangTag, array_keys($replacements))) {
                    $value = false;
                    if (isset($replacements[$tag])) {
                        $value = $replacements[$tag];
                    }

                    if (is_array($value)) {
                        foreach ($value as $vi => $vv) {
                            if (is_array($vv)) {
                                $value[$vi] = array_merge($replacements, $vv);
                            } else {
                                $originalVv = $vv;
                                $vv = $replacements;
                                $vv[] = $originalVv;
                            }
                        }
                    }

                    $openTag = self::$tagOpenPrefix . $tag . self::$tagOpenPostfix;
                    $closeTag = self::$tagClosePrefix . $tag . self::$tagClosePostfix;

                    $offset = 0;
                    $cutStart = strpos($content, $openTag, $offset);
                    while ($offset < strlen($content) && $cutStart !== false) {
                        $offset = $cutStart + strlen($openTag);
                        $cutEnd = strpos($content, $closeTag, $offset);
                        if ($cutEnd !== false) {
                            $offset = $cutEnd + strlen($closeTag);
                            $embedTemplate = substr(
                                $content,
                                ($cutStart + strlen($openTag)),
                                ($cutEnd - ($cutStart + strlen($openTag)))
                            );

                            $filledTemplate = '';

                            $contentToReplace = substr(
                                $content,
                                $cutStart,
                                ($cutEnd + strlen($closeTag) - $cutStart)
                            );

                            if (is_array($value)) {
                                $isArrayNumeric = true;
                                foreach (array_keys($value) as $key) {
                                    if (!is_numeric($key)) {
                                        $isArrayNumeric = false;
                                    }
                                }

                                if ($isArrayNumeric) {
                                    reset($value);
                                    $firstKey = key($value);
                                    if (
                                        !empty($firstKey)
                                        && !empty($value[$firstKey])
                                        && !is_array($value[$firstKey])
                                    ) {
                                        if (self::$singularizePluralMagicKeys) {
                                            $newKey = self::singularize($tag);
                                        } else {
                                            $newKey = $tag;
                                        }
                                        $newValue = [];
                                        foreach ($value as $tempIndex => $embedReplacements) {
                                            $newValue[$tempIndex] = [$newKey => $embedReplacements];
                                        }
                                        $value = $newValue;
                                        unset($newValue);
                                    }
                                    foreach ($value as $tempIndex => $embedReplacements) {
                                        if (!array_key_exists('index', $embedReplacements)) {
                                            $embedReplacements['index'] = $tempIndex;
                                        }

                                        $filledTemplate .= $this->getTemplateWithReplacementsContent(
                                            $embedTemplate,
                                            $embedReplacements
                                        );
                                    }
                                } else {
                                    $filledTemplate = $this->getTemplateWithReplacementsContent(
                                        $embedTemplate,
                                        $value
                                    );
                                }
                            } else {
                                if ($value) {
                                    $filledTemplate = $this->getTemplateWithReplacementsContent(
                                        $embedTemplate,
                                        $replacements
                                    );
                                }
                            }

                            $content = str_replace($contentToReplace, $filledTemplate, $content);
                        }

                        if ($offset < strlen($content)) {
                            $cutStart = strpos($content, $openTag, $offset);
                        } else {
                            $cutStart = false;
                        }
                    }
                } else {
                    $leftoverEmbedTags[] = $tag;
                }
            }

            // Now do the regular tags ...
            $pattern = '/' . self::$tagPrefix . '([A-z0-9,_]+)' . self::$tagPostfix . '/';
            $matchCount = preg_match_all($pattern, $content, $matches);

            if ($matchCount) {
                $replacementTags = $matches[1];
            } else {
                $replacementTags = [];
            }

            foreach ($replacementTags as $tag) {
                if (
                    $this->removeLeftoverTags
                    && (!isset($replacements[$tag])
                    || !in_array($tag, array_keys($replacements))
                    || $replacements[$tag] === null)
                ) {
                    $replacements[$tag] = '';
                }

                if (isset($replacements[$tag])) {
                    $value = $replacements[$tag];

                    if (is_array($value)) {
                        $newValue = '';

                        if (count($value)) {
                            $tempKeys = array_keys($value);
                            if (is_array($value[$tempKeys[0]])) {
                                foreach ($value as $v) {
                                    $newValue .= $v;
                                }
                            }
                        } else {
                            $newValue = implode(', ', $value);
                        }
                        $value = $newValue;
                    }

                    if (is_string($value) && substr_count($value, self::$endOfLine)) {
                        $deleteEndCharacters = [' ', "\n", "\r", "\t"];
                        $cutoffPos = strlen($value) - 1;
                        if (strlen(trim($value))) {
                            while (in_array(substr($value, $cutoffPos, 1), $deleteEndCharacters)) {
                                $cutoffPos--;
                            }
                            $value = substr($value, 0, $cutoffPos + 1);
                        }

                        if ($this->useSmartIndentation) {
                            $offset = 0;
                            while ($offset < strlen($content) && $cutStart = strpos($content, $tag, $offset)) {
                                $offset = $cutStart + strlen($tag);

                                $indentationEnd = $cutStart - 1;
                                if ($indentationEnd > 0) {
                                    $indentationOffset = -(strlen($content) - ($indentationEnd));

                                    $indentationStart = strrpos($content, self::$endOfLine, $indentationOffset)
                                        + strlen(self::$endOfLine);
                                    $indentationMaterial = substr(
                                        $content,
                                        $indentationStart,
                                        ($indentationEnd - $indentationStart)
                                    );
                                    $indentationLevel = substr_count($indentationMaterial, self::$indentationUnit);

                                    if (
                                        $indentationLevel * strlen(self::$indentationUnit)
                                        == strlen($indentationMaterial)
                                    ) {
                                        $value = str_replace(
                                            self::$endOfLine,
                                            self::$endOfLine . $indentationMaterial,
                                            $value
                                        );
                                    }
                                }
                            }
                        }
                    }

                    if (is_bool($value) || is_int($value)) {
                        $value = (string) $value;
                    }

                    $content = str_replace(self::$tagPrefix . $tag . self::$tagPostfix, $value, $content);
                }
            }
        }

        // Find eval tags
        $pattern = '/' . str_replace(['(', ')'], ['\(', '\)'], $this->evalOpenTag) . '(.+?)'
            . str_replace(['(', ')'], ['\(', '\)'], $this->evalCloseTag) . '/';
        $matchCount = preg_match_all($pattern, $content, $matches);

        if ($matchCount) {
            $toReplace = $matches[0];
            $toEvaluate = $matches[1];

            foreach ($toEvaluate as $index => $te) {
                $te = $this->getTemplateWithReplacementsContent($te, $replacements);

                $tempResult = '';
                eval('$tempResult = (' . $te . ');');

                $content = str_replace($toReplace[$index], (string)$tempResult, $content);
            }
        }

        $this->hasPlacedReplacements = true;

        return $content;
    }

    public function getContent($template = false, $replacements = false)
    {
        if (isset($this->content) && strlen($this->content) && !$template && !$replacements) {
            return $this->content;
        }

        $needsReset = false;

        if (!$template) {
            $template = $this->template;
        } else {
            $needsReset = true;
        }

        if (!$replacements) {
            $replacements = $this->replacements;
        } else {
            $needsReset = true;
        }

        if ($needsReset) {
            $this->reset($template, $replacements);
            $template = $this->template;
            $replacements = $this->replacements;
        }

        $content = $this->getTemplateWithReplacementsContent($template, $replacements);

        if (!isset($this->content) || !$this->content) {
            $this->content = $content;
        }

        return $content;
    }

    public function loadTemplateContent($filepath, $templateDirectory = false)
    {
        if (!is_file($filepath)) {
            if (!$templateDirectory) {
                $templateDirectory = self::$templateDirectory;
            }
            if (is_file(($templateDirectory ? $templateDirectory . '/' : '') . $filepath)) {
                $filepath = ($templateDirectory ? $templateDirectory . '/' : '') . $filepath;
            }
        }

        return file_get_contents($filepath, true);
    }

    public function hasErrors()
    {
        return count($this->errors);
    }

    public function getErrors()
    {
        return $this->errors;
    }

    public function render($template = false, $replacements = false)
    {
        echo $this->getContent($template, $replacements);
    }

    public function write()
    {
        $this->render();
    }

    public static function singularize($word)
    {
        $singular = [
            '/(quiz)zes$/i' => '\1',
            '/(matr)ices$/i' => '\1ix',
            '/(vert|ind)ices$/i' => '\1ex',
            '/^(ox)en/i' => '\1',
            '/(alias|status)es$/i' => '\1',
            '/([octop|vir])i$/i' => '\1us',
            '/(cris|ax|test)es$/i' => '\1is',
            '/(shoe)s$/i' => '\1',
            '/(o)es$/i' => '\1',
            '/(bus)es$/i' => '\1',
            '/([m|l])ice$/i' => '\1ouse',
            '/(x|ch|ss|sh)es$/i' => '\1',
            '/(m)ovies$/i' => '\1ovie',
            '/(s)eries$/i' => '\1eries',
            '/([^aeiouy]|qu)ies$/i' => '\1y',
            '/([lr])ves$/i' => '\1f',
            '/(tive)s$/i' => '\1',
            '/(hive)s$/i' => '\1',
            '/([^f])ves$/i' => '\1fe',
            '/(^analy)ses$/i' => '\1sis',
            '/((a)naly|(b)a|(d)iagno|(p)arenthe|(p)rogno|(s)ynop|(t)he)ses$/i' => '\1\2sis',
            '/([ti])a$/i' => '\1um',
            '/(n)ews$/i' => '\1ews',
            '/s$/i' => '',
        ];

        $uncountable = ['equipment', 'information', 'rice', 'money', 'species', 'series', 'fish', 'sheep'];

        $irregular = [
            'person' => 'people',
            'man' => 'men',
            'child' => 'children',
            'sex' => 'sexes',
            'move' => 'moves'
        ];

        $lowercasedWord = strtolower($word);
        foreach ($uncountable as $item) {
            if (substr($lowercasedWord, (-1 * strlen($item))) == $item) {
                return $word;
            }
        }

        foreach ($irregular as $plural => $sing) {
            if (preg_match('/(' . $sing . ')$/i', $word, $arr)) {
                return preg_replace('/(' . $sing . ')$/i', substr($arr[0], 0, 1) . substr($plural, 1), $word);
            }
        }

        foreach ($singular as $rule => $replacement) {
            if (preg_match($rule, $word)) {
                return preg_replace($rule, $replacement, $word);
            }
        }

        return $word;
    }
}
