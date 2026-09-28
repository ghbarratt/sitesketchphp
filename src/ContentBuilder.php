<?php

declare(strict_types=1);

namespace Sitesketch;

class ContentBuilder
{
    public static $templateExtension = 'tpl';
    public static $template_extension = 'tpl';
    public static $templateDirectory = 'templates';
    public static $template_directory = 'templates';
    public static $endOfLine = "\n";
    public static $end_of_line = "\n";
    public static $indentationUnit = "\t";
    public static $indentation_unit = "\t";
    public static $singularizePluralMagicKeys = false;
    public static $singularize_plural_magic_keys = false;
    public static $webDirectory = 'public';
    public static $web_directory = 'public';
    public static $sitePath;
    public static $site_path;
    public static $siteWebPath;
    public static $site_web_path;
    public static $sitesketchPath;
    public static $sitesketch_path;

    protected static $tagBang = '!';
    protected static $tag_bang = '!';
    protected static $tagPrefix = '~';
    protected static $tag_prefix = '~';
    protected static $tagPostfix = '~';
    protected static $tag_postfix = '~';
    protected static $tagOpenPrefix = '~';
    protected static $tag_open_prefix = '~';
    protected static $tagOpenPostfix = '{';
    protected static $tag_open_postfix = '{';
    protected static $tagClosePrefix = '}';
    protected static $tag_close_prefix = '}';
    protected static $tagClosePostfix = '~';
    protected static $tag_close_postfix = '~';

    public $linePrefix = '';
    public $line_prefix = '';
    public $replacements;
    public $removeLeftoverTags = true;
    public $remove_leftover_tags = true;
    public $useSmartIndentation = false; // faster
    public $use_smart_indentation = false;

    protected $template;
    protected $content;
    protected $hasPlacedReplacements = false;
    protected $has_placed_replacements = false;
    protected $evalOpenTag = '~eval(';
    protected $eval_open_tag = '~eval(';
    protected $evalCloseTag = ')eval~';
    protected $eval_close_tag = ')eval~';
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
            self::$template_directory = $templateDirectory;
        }
        if ($linePrefix) {
            $this->linePrefix = $linePrefix;
            $this->line_prefix = $linePrefix;
        }
        if ($indentationUnit) {
            $this->indentationUnit = $indentationUnit;
            $this->indentation_unit = $indentationUnit;
        }

        return true;
    }

    public static function getSitesketchPath()
    {
        $path = self::$sitesketchPath ?? self::$sitesketch_path;
        if ($path) {
            return $path;
        }

        // What is the directory of the Sitesketch package root?
        $path = dirname(__DIR__);
        self::$sitesketchPath = $path;
        self::$sitesketch_path = $path;

        return $path;
    }

    public static function getSitePath()
    {
        $sitePath = self::$sitePath ?? self::$site_path;
        if (isset($sitePath) && strlen($sitePath)) {
            return $sitePath;
        }

        $webDir = self::$webDirectory ?? self::$web_directory;

        // Attempt to use the first web directory on current path, otherwise use the web ("document") root
        $webPos = isset($_SERVER['SCRIPT_FILENAME']) ? strrpos($_SERVER['SCRIPT_FILENAME'], $webDir) : false;
        if ($webPos !== false && isset($_SERVER['SCRIPT_FILENAME'])) {
            $siteWebPath = substr($_SERVER['SCRIPT_FILENAME'], 0, $webPos + strlen($webDir));
        } elseif (isset($_SERVER['DOCUMENT_ROOT'])) {
            $siteWebPath = $_SERVER['DOCUMENT_ROOT'];
        } else {
            $siteWebPath = '';
        }

        self::$siteWebPath = $siteWebPath;
        self::$site_web_path = $siteWebPath;

        // Usually site path is one directory up from the site web path
        $sitePath = dirname($siteWebPath);
        self::$sitePath = $sitePath;
        self::$site_path = $sitePath;

        return $sitePath;
    }

    public static function getTemplateExtension()
    {
        return self::$templateExtension ?? self::$template_extension;
    }

    public function setTemplate($template)
    {
        if (!$template) {
            return false;
        }

        $ext = self::getTemplateExtension();
        $template = str_replace('.' . $ext . '.' . $ext, '.' . $ext, $template);

        $this->hasPlacedReplacements = false;
        $this->has_placed_replacements = false;

        $templateDir = self::$templateDirectory ?? self::$template_directory;

        $filepathsToTry = [
            $template,
            self::getSitePath() . '/' . $template,
            self::getSitePath() . '/' . $templateDir . '/' . $template,
            self::getSitePath() . '/templates/' . $template,
            self::getSitesketchPath() . '/resources/' . $template,
            self::getSitesketchPath() . '/resources/' . $templateDir . '/' . $template,
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
        $this->use_smart_indentation = $use;
    }

    public function adjustIndentation($amount = false)
    {
        $indentUnit = $this->indentationUnit ?? $this->indentation_unit ?? self::$indentationUnit;
        $linePrefix = $this->linePrefix ?? $this->line_prefix ?? '';

        if ($amount > 0) {
            $this->linePrefix = str_pad($linePrefix, (int)$amount, $indentUnit, STR_PAD_LEFT);
            $this->line_prefix = $this->linePrefix;
        } elseif ($amount < 0) {
            $this->linePrefix = str_replace($indentUnit, '', $linePrefix, $amount);
            $this->line_prefix = $this->linePrefix;
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

        $tagOpenPrefix = self::$tagOpenPrefix ?? self::$tag_open_prefix;
        $tagBang = self::$tagBang ?? self::$tag_bang;
        $tagOpenPostfix = self::$tagOpenPostfix ?? self::$tag_open_postfix;
        $tagClosePrefix = self::$tagClosePrefix ?? self::$tag_close_prefix;
        $tagClosePostfix = self::$tagClosePostfix ?? self::$tag_close_postfix;
        $tagPrefix = self::$tagPrefix ?? self::$tag_prefix;
        $tagPostfix = self::$tagPostfix ?? self::$tag_postfix;
        $endOfLine = self::$endOfLine ?? self::$end_of_line;
        $indentationUnit = self::$indentationUnit ?? self::$indentation_unit;
        $removeLeftoverTags = $this->removeLeftoverTags ?? $this->remove_leftover_tags;
        $useSmartIndentation = $this->useSmartIndentation ?? $this->use_smart_indentation;

        // Find regular tags
        $pattern = '/' . $tagOpenPrefix . '(' . $tagBang . '?[A-z0-9,_]+)' . $tagOpenPostfix . '/';
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

                if (strpos($tag, $tagBang) !== false) {
                    $noBangTag = substr($tag, strlen($tagBang));

                    if (!isset($replacements[$noBangTag]) || !$replacements[$noBangTag]) {
                        $replacements[$tag] = $replacements;
                    }
                } else {
                    if ($removeLeftoverTags && !in_array($tag, array_keys($replacements))) {
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

                    $openTag = $tagOpenPrefix . $tag . $tagOpenPostfix;
                    $closeTag = $tagClosePrefix . $tag . $tagClosePostfix;

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
                                        $magic = self::$singularizePluralMagicKeys
                                            ?? self::$singularize_plural_magic_keys;
                                        if ($magic) {
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
            $pattern = '/' . $tagPrefix . '([A-z0-9,_]+)' . $tagPostfix . '/';
            $matchCount = preg_match_all($pattern, $content, $matches);

            if ($matchCount) {
                $replacementTags = $matches[1];
            } else {
                $replacementTags = [];
            }

            foreach ($replacementTags as $tag) {
                if (
                    $removeLeftoverTags
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

                    if (is_string($value) && substr_count($value, $endOfLine)) {
                        $deleteEndCharacters = [' ', "\n", "\r", "\t"];
                        $cutoffPos = strlen($value) - 1;
                        if (strlen(trim($value))) {
                            while (in_array(substr($value, $cutoffPos, 1), $deleteEndCharacters)) {
                                $cutoffPos--;
                            }
                            $value = substr($value, 0, $cutoffPos + 1);
                        }

                        if ($useSmartIndentation) {
                            $offset = 0;
                            while ($offset < strlen($content) && $cutStart = strpos($content, $tag, $offset)) {
                                $offset = $cutStart + strlen($tag);

                                $indentationEnd = $cutStart - 1;
                                if ($indentationEnd > 0) {
                                    $indentationOffset = -(strlen($content) - ($indentationEnd));

                                    $indentationStart = strrpos($content, $endOfLine, $indentationOffset)
                                        + strlen($endOfLine);
                                    $indentationMaterial = substr(
                                        $content,
                                        $indentationStart,
                                        ($indentationEnd - $indentationStart)
                                    );
                                    $indentationLevel = substr_count($indentationMaterial, $indentationUnit);

                                    if (
                                        $indentationLevel * strlen($indentationUnit)
                                        == strlen($indentationMaterial)
                                    ) {
                                        $value = str_replace(
                                            $endOfLine,
                                            $endOfLine . $indentationMaterial,
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

                    $content = str_replace($tagPrefix . $tag . $tagPostfix, $value, $content);
                }
            }
        }

        // Find eval tags
        $evalOpen = $this->evalOpenTag ?? $this->eval_open_tag;
        $evalClose = $this->evalCloseTag ?? $this->eval_close_tag;

        $pattern = '/' . str_replace(['(', ')'], ['\(', '\)'], $evalOpen) . '(.+?)'
            . str_replace(['(', ')'], ['\(', '\)'], $evalClose) . '/';
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
        $this->has_placed_replacements = true;

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
                $templateDirectory = self::$templateDirectory ?? self::$template_directory;
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
