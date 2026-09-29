<?php

declare(strict_types=1);

namespace Sitesketch;

class PageBuilder
{
    public static $siteKeywords;
    public static $site_keywords;
    public static $siteDescription;
    public static $site_description;
    public static $siteHeadExtra;
    public static $site_head_extra;
    public static $sitePath;
    public static $site_path;
    public static $siteAlias;
    public static $site_alias;
    public static $siteTitle;
    public static $site_title;
    public static $siteScripts;
    public static $site_scripts;
    public static $siteStylesheets;
    public static $site_stylesheets;
    public static $siteIcon;
    public static $site_icon;

    public $alias;
    public $type = '';
    public $title = '';

    protected $replacements = [];

    protected $description;
    protected $keywords;
    protected $doctypeAlias;
    protected $doctype_alias;
    protected $uri;
    protected $templates;
    protected $stylesheets;
    protected $scripts;
    protected $metas;
    protected $headExtra;
    protected $head_extra;
    protected $bodyAttributes;
    protected $body_attributes;
    protected $icon;
    protected $domain;

    protected $headerContent;
    protected $header_content;
    protected $footerContent;
    protected $footer_content;
    protected $bodyContent;
    protected $body_content;

    protected $cb;

    protected $errors = [];

    public function __construct(
        string|null $alias = null,
        array|null $replacements = null,
        string|null $type = null,
        string|null $title = null
    ) {
        $this->cb = new ContentBuilder();
        if (empty($title) && !empty($alias)) {
            $title = ucwords(str_replace('_', ' ', $alias));
        }
        $this->reset($alias, $replacements, $type, $title);
    }

    public function reset(
        string|null $alias = null,
        array|null $replacements = null,
        string|null $type = null,
        string|null $title = null
    ) {
        if (!empty($alias)) {
            $this->alias = $alias;
        }

        if (!empty($replacements) && is_array($replacements)) {
            $this->replacements = $replacements;
        }

        if (!empty($type)) {
            $this->type = $type;
        }

        $siteTitle = static::$siteTitle ?? static::$site_title;
        $siteAlias = static::$siteAlias ?? static::$site_alias;

        if (!empty($title)) {
            $this->title = $title;
        } elseif (isset($this->alias) && $this->alias[0] != '_') {
            $this->title = ucwords(str_replace('_', ' ', $this->alias));
        } else {
            $url = $_SERVER['REQUEST_URI'] ?? '';
            if (strpos($url, '.') !== false) {
                $url = substr($url, 0, strpos($url, '.'));
            }
            while (strlen($url) && $url[0] == '/') {
                $url = substr($url, 1);
            }
            while (strlen($url) && $url[strlen($url) - 1] == '/') {
                $url = substr($url, 0, strlen($url) - 1);
            }
            $this->title = ucwords(str_replace(['/', '_'], [' - ', ' '], parse_url($url, PHP_URL_PATH) ?? ''));
        }

        if (empty($this->alias) && !empty($this->title)) {
            $this->alias = str_replace([' - ', ' '], '_', strtolower($this->title));
        }

        if (empty($siteTitle) && !empty($siteAlias) && $siteAlias[0] != '_') {
            $siteTitle = ucwords(str_replace('_', ' ', $siteAlias));
            static::$siteTitle = $siteTitle;
            static::$site_title = $siteTitle;
        }

        // Prepend the site title?
        if (!empty($siteTitle) && !empty($this->title)) {
            $this->title = $siteTitle . ' - ' . $this->title;
        }

        // Page title is STILL empty?? Then just use the site title if we have that
        if (empty($this->title) && !empty($siteTitle)) {
            $this->title = $siteTitle;
        }

        $siteIcon = static::$siteIcon ?? static::$site_icon;
        if (empty($siteIcon)) {
            if (is_file(($_SERVER['DOCUMENT_ROOT'] ?? '') . '/favicon.ico')) {
                static::$siteIcon = '/favicon.ico';
                static::$site_icon = '/favicon.ico';
            }
        }

        $this->stylesheets = null;
        $siteStylesheets = static::$siteStylesheets ?? static::$site_stylesheets;
        if (!empty($siteStylesheets)) {
            $this->stylesheets = $siteStylesheets;
        }
        $documentRootPath = $_SERVER['DOCUMENT_ROOT'] ?? '';
        $attemptToAddCss = ['/css/global.css', '/css/primary.css'];
        if (isset($siteAlias) && $siteAlias) {
            $attemptToAddCss[] = '/css/' . $siteAlias . '.css';
        }
        if (isset($this->type) && $this->type) {
            $attemptToAddCss[] = '/css/' . $this->type . '.css';
        }
        if (isset($this->alias) && $this->type) {
            $attemptToAddCss[] = '/css/' . $this->alias . '.css';
        }

        foreach ($attemptToAddCss as $ac) {
            if (is_file($documentRootPath . $ac)) {
                $this->stylesheets[] = ['href' => $ac];
            }
        }

        $this->scripts = null;
        $this->templates = [];
        if ($type) {
            $this->setType($type);
        } else {
            $this->type = false;
        }

        $this->headerContent = null;
        $this->header_content = null;
        $this->footerContent = null;
        $this->footer_content = null;
        $this->bodyContent = null;
        $this->body_content = null;
    }

    public static function setSiteVariable(string $name, string|array $value): bool
    {
        $candidates = [];
        if (str_starts_with($name, 'site_')) {
            $candidates[] = $name;
            $candidates[] = lcfirst(str_replace(' ', '', ucwords(str_replace('_', ' ', $name))));
        } elseif (str_starts_with($name, 'site')) {
            $candidates[] = $name;
            $candidates[] = strtolower(preg_replace('/(?<!^)[A-Z]/', '_$0', $name));
        } else {
            $candidates[] = 'site_' . $name;
            $candidates[] = 'site' . ucfirst($name);
        }

        $matched = false;
        $ref = new \ReflectionClass(static::class);
        foreach ($candidates as $prop) {
            if ($ref->hasProperty($prop) && $ref->getProperty($prop)->isStatic()) {
                static::${$prop} = $value;
                $matched = true;
            }
        }
        return $matched;
    }

    private function setSiteAlias(string|null $siteAlias = null): string
    {
        if (!empty($siteAlias)) {
            static::$siteAlias = $siteAlias;
            static::$site_alias = $siteAlias;
        } else {
            $host = $_SERVER['HTTP_HOST'] ?? '';
            $dotPos = strrpos($host, '.');
            $alias = str_replace('www.', '', $dotPos !== false ? substr($host, 0, $dotPos) : $host);
            static::$siteAlias = $alias;
            static::$site_alias = $alias;
        }

        return static::$siteAlias;
    }

    public function setType(string $type)
    {
        $this->type = $type;

        // If the type css is not present, add it
        if (!$this->hasStylesheet('/css/' . $this->type . '.css')) {
            if (!is_array($this->stylesheets)) {
                $this->stylesheets = [];
            }
            $this->stylesheets = array_merge($this->stylesheets, [0 => ['href' => '/css/' . $this->type . '.css']]);
        }
    }

    public function setTitle(string $title)
    {
        $this->title = $title;
    }

    public function setDescription(string $description)
    {
        $this->description = $description;
    }

    public function hasStylesheet(string $href)
    {
        if (empty($this->stylesheets) || !is_array($this->stylesheets) || !count($this->stylesheets)) {
            return false;
        }
        foreach ($this->stylesheets as $stylesheet) {
            if ((is_array($stylesheet) && $stylesheet['href'] == $href) || $stylesheet == $href) {
                return true;
            }
        }

        return false;
    }

    public function useSmartIndentation(bool $setting = true)
    {
        return $this->cb->useSmartIndentation($setting);
    }

    private function getMetas(
        string|null $alias = null,
        string|null $description = null,
        array|string|null $keywords = null,
        string|null $contentType = null
    ) {
        if (empty($alias)) {
            $alias = $this->getAlias();
        }
        if (empty($description) && !empty($this->description)) {
            $description = $this->description;
        }
        $siteDesc = static::$siteDescription ?? static::$site_description;
        if (empty($description) && !empty($siteDesc)) {
            $description = $siteDesc;
        }

        if (empty($keywords)) {
            if (!empty($this->keywords)) {
                $keywords = $this->keywords;
            }
        }

        $siteKw = static::$siteKeywords ?? static::$site_keywords;
        if (!empty($siteKw)) {
            if (is_array($keywords)) {
                $keywords = array_merge($keywords, $siteKw);
            } else {
                $keywords = $siteKw;
            }
        }

        if (empty($contentType)) {
            $contentType = $this->getContentType();
        }

        if (!empty($this->metas) && count($this->metas)) {
            $metas = $this->metas;
        } else {
            $metas = [];
        }
        if (!empty($contentType)) {
            $metas[] = ['attribute' => 'http-equiv', 'attribute_value' => 'content-type', 'content' => $contentType];
        }

        if (!empty($description)) {
            $metas[] = ['attribute' => 'name', 'attribute_value' => 'description', 'content' => $description];
        }

        if (!empty($keywords)) {
            if (is_array($keywords)) {
                $keywordsString = implode(', ', $keywords);
            } else {
                $keywordsString = $keywords;
            }

            $metas[] = ['attribute' => 'name', 'attribute_value' => 'keywords', 'content' => $keywordsString];
        }

        return $metas;
    }

    public function addMeta($meta)
    {
        $this->metas[] = $meta;
    }

    public function getHeadExtra()
    {
        return $this->headExtra ?? $this->head_extra;
    }

    public function setKeywords(array|string $keywords)
    {
        $this->keywords = $keywords;
    }

    public function setHeadExtra($headExtra)
    {
        $this->headExtra = $headExtra;
        $this->head_extra = $headExtra;
    }

    public function setBodyAttributes($bodyAttributes)
    {
        $this->bodyAttributes = $bodyAttributes;
        $this->body_attributes = $bodyAttributes;
    }

    public function getDefaultReplacements()
    {
        $doctypeAlias = $this->doctypeAlias ?? $this->doctype_alias;
        return [
            'alias' => $this->getAlias(),
            'type' => $this->type,
            'doctype_alias' => $doctypeAlias,
            'this_year' => date('Y'),
        ];
    }

    public function getReplacements()
    {
        return array_merge($this->getDefaultReplacements(), $this->replacements);
    }

    public function addReplacements($replacements)
    {
        if (!is_array($this->replacements)) {
            $this->replacements = [];
        }
        $this->replacements = array_merge($replacements, $this->replacements);
    }

    public function addStylesheets($stylesheets)
    {
        if (!is_array($this->stylesheets)) {
            $this->stylesheets = [];
        }
        if (!is_array($stylesheets) && is_string($stylesheets)) {
            return $this->addStylesheet($stylesheets);
        }
        foreach ($stylesheets as $s) {
            if (!is_array($s) && is_string($s)) {
                $this->stylesheets[] = ['href' => $s];
            } else {
                $this->stylesheets[] = $s;
            }
        }
    }

    public function addScripts(array|string $scripts)
    {
        if (is_string($scripts)) {
            $this->addScript($scripts);
        } elseif (is_array($scripts)) {
            foreach ($scripts as $s) {
                if (!is_array($s) && is_string($s)) {
                    $this->scripts[] = ['src' => $s, 'type' => 'text/javascript'];
                } else {
                    $this->scripts[] = $s;
                }
            }
        }
    }

    public function addReplacement($tag, $value)
    {
        $this->replacements = array_merge([$tag => $value], $this->replacements);
    }

    public function addStylesheet($href)
    {
        if (!isset($this->stylesheets) || !is_array($this->stylesheets)) {
            $this->stylesheets = [];
        }
        $this->stylesheets = array_merge([0 => ['href' => $href]], $this->stylesheets);
    }

    public function removeStylesheet($href)
    {
        $result = false;
        if (!$this->hasStylesheet($href)) {
            return false;
        } else {
            foreach ($this->stylesheets as $hi => $s) {
                if ($s['href'] == $href) {
                    unset($this->stylesheets[$hi]);
                    $result = true;
                }
            }
        }
        return $result;
    }

    public function addScript(string|array $src, string $type = 'text/javascript')
    {
        if (!is_array($this->scripts)) {
            $this->scripts = [];
        }

        if (is_string($src)) {
            $this->scripts[] = ['src' => $src, 'type' => $type];
        } elseif (is_array($src)) {
            $this->scripts[] = $src;
        }
    }

    public function setDoctypeAlias($doctypeAlias)
    {
        $this->doctypeAlias = $doctypeAlias;
        $this->doctype_alias = $doctypeAlias;

        return $doctypeAlias;
    }

    public function setDoctype($doctypeAlias)
    {
        return $this->setDoctypeAlias($doctypeAlias);
    }

    public function getDoctypeAlias()
    {
        $doctypeAlias = $this->doctypeAlias ?? $this->doctype_alias;
        if ($doctypeAlias) {
            return $doctypeAlias;
        } else {
            return 'xhtml1_transitional';
        }
    }

    public function getContentType($doctypeAlias = false)
    {
        if (!$doctypeAlias) {
            $doctypeAlias = $this->getDoctypeAlias();
        }

        switch ($doctypeAlias) {
            case 'html':
            case 'html_transitional':
            case 'html_loose':
            case 'html_4':
            case 'html_4_transitional':
            case 'html_4_loose':
            case 'html_4.01':
            case 'html_4.01_transitional':
            case 'html_4.01_loose':
            case 'html4':
            case 'html4_transitional':
            case 'html4_loose':
            case 'html4.01':
            case 'html4.01_transitional':
            case 'html4.01_loose':
                $contentType = 'text/html; charset=iso-8859-1';
                break;
            default:
                $contentType = 'text/html; charset=utf-8';
                break;
        }

        return $contentType;
    }

    public function getDTDContent(string|null $alias = null, string|null $doctypeAlias = null)
    {
        if (empty($alias)) {
            $alias = $this->getAlias();
        }
        if (empty($doctypeAlias)) {
            $doctypeAlias = $this->getDoctypeAlias();
        }

        switch ($doctypeAlias) {
            case 'html_transitional':
            case 'html_loose':
            case 'html_4':
            case 'html_4_transitional':
            case 'html_4_loose':
            case 'html_4.01':
            case 'html_4.01_transitional':
            case 'html_4.01_loose':
            case 'html4':
            case 'html4_transitional':
            case 'html4_loose':
            case 'html4.01':
            case 'html4.01_transitional':
            case 'html4.01_loose':
                $access = ' PUBLIC';
                $declaration = ' "-//W3C//DTD HTML 4.01 Transitional//EN"';
                $link = ' "http://www.w3.org/TR/html4/loose.dtd"';
                $this->doctypeAlias = 'html4_transitional';
                $this->doctype_alias = 'html4_transitional';
                break;
            case 'html_strict':
            case 'html_4_strict':
            case 'html_4.01_strict':
            case 'html4_strict':
            case 'html4.01_strict':
                $access = ' PUBLIC';
                $declaration = ' "-//W3C//DTD HTML 4.01//EN"';
                $link = ' "http://www.w3.org/TR/html4/strict.dtd"';
                $this->doctypeAlias = 'html4_strict';
                $this->doctype_alias = 'html4_strict';
                break;
            case 'xhtml_strict':
            case 'xhtml_1_strict':
            case 'xhtml_1.0_strict':
            case 'xhtml1_strict':
            case 'xhtml1.0_strict':
                $access = ' PUBLIC';
                $declaration = ' "-//W3C//DTD XHTML 1.0 Strict//EN"';
                $link = ' "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd"';
                $this->doctypeAlias = 'xhtml1_strict';
                $this->doctype_alias = 'xhtml1_strict';
                break;
            case 'xhtml_transitional':
            case 'xhtml_1_transitional':
            case 'xhtml_1.0_transitional':
            case 'xhtml1_transitional':
            case 'xhtml1.0_transitional':
                $access = ' PUBLIC';
                $declaration = ' "-//W3C//DTD XHTML 1.0 Transitional//EN"';
                $link = ' "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd"';
                $this->doctypeAlias = 'xhtml1_transitional';
                $this->doctype_alias = 'xhtml1_transitional';
                break;
            default: // default is HTML 5
                $access = '';
                $declaration = '';
                $link = '';
                $this->doctypeAlias = 'html5';
                $this->doctype_alias = 'html5';
                break;
        }

        $templateExt = ContentBuilder::getTemplateExtension();
        $templateDir = ContentBuilder::$templateDirectory ?? ContentBuilder::$template_directory;

        if (isset($this->templates['dtd'])) {
            $template = $this->templates['dtd'];
        } else {
            $template = $templateDir . '/dtd.' . $templateExt;
        }

        $this->templates['dtd'] = $template;
        $this->cb->reset($template);
        $this->cb->setReplacements(['access' => $access, 'declaration' => $declaration, 'link' => $link]);
        if ($this->cb->hasErrors()) {
            $this->errors = array_merge($this->errors, $this->cb->getErrors());
            return false;
        } else {
            return $this->cb->getContent();
        }
    }

    public function getScripts($scripts = false)
    {
        if (isset($this->scripts) && is_array($this->scripts) && count($this->scripts)) {
            $scripts = $this->scripts;
        }

        $siteScripts = static::$siteScripts ?? static::$site_scripts;
        if (!empty($siteScripts) && is_array($siteScripts) && count($siteScripts)) {
            $scripts = array_merge($siteScripts, (is_array($scripts) ? $scripts : []));
        }

        if (is_array($scripts)) {
            // Standardize the scripts array format
            foreach ($scripts as &$script) {
                if (!is_array($script) && strpos($script, '.')) {
                    $scriptSrc = $script;
                    $script = [];
                    $script['src'] = $scriptSrc;
                    $script['type'] = 'text/javascript';
                } else {
                    if (!isset($script['type']) || !$script['type']) {
                        $script['type'] = 'text/javascript';
                    }
                }
            }
        }

        return $scripts;
    }

    public function setIcon(string $icon)
    {
        $this->icon = $icon;
    }

    public function getIcon(): string|false
    {
        return $this->icon ?? static::$siteIcon ?? static::$site_icon ?? false;
    }

    public function getHeadContent(
        $alias = false,
        $stylesheets = false,
        $scripts = false,
        $title = false,
        $metas = false,
        $headExtra = false
    ) {
        if (!$alias) {
            $alias = $this->getAlias();
        }

        if (!$title && isset($this->title) && $this->title) {
            $title = $this->title;
        }

        if (!$stylesheets && isset($this->stylesheets) && is_array($this->stylesheets) && count($this->stylesheets)) {
            $stylesheets = $this->stylesheets;
        }

        if (!$scripts) {
            $scripts = $this->getScripts();
        }

        if (!$metas) {
            $metas = $this->getMetas($alias);
        }

        $currentHeadExtra = $this->headExtra ?? $this->head_extra;
        if (isset($currentHeadExtra)) {
            $headExtra .= $this->getHeadExtra();
        }

        $siteHeadExtra = static::$siteHeadExtra ?? static::$site_head_extra;
        if (!empty($siteHeadExtra)) {
            $headExtra .= $siteHeadExtra;
        }

        $templateDir = ContentBuilder::$templateDirectory ?? ContentBuilder::$template_directory;
        $templateExt = ContentBuilder::getTemplateExtension();
        $template = $templateDir . '/head.' . $templateExt;
        if (isset($this->templates['head'])) {
            $template = $this->templates['head'];
        }

        $replacements = [];

        if ($title) {
            $replacements['title'] = $title;
        }

        if ($icon = $this->getIcon()) {
            $replacements['icon'] = $icon;
        }

        if ($stylesheets) {
            $replacements['stylesheets'] = $stylesheets;
        }
        if ($scripts) {
            $replacements['scripts'] = $scripts;
        }
        if ($metas) {
            $replacements['metas'] = $metas;
        }
        if ($headExtra) {
            $replacements['head_extra'] = $headExtra;
        }

        return $this->cb->getContent($template, $replacements);
    }

    public function hasType(): bool
    {
        if (!empty($this->type)) {
            return true;
        } else {
            return false;
        }
    }

    public function getHeaderContent(string|null $alias = null, string|null $template = null): string
    {
        $sitePath = static::$sitePath ?? static::$site_path;
        if (empty($sitePath)) {
            $sitePath = ContentBuilder::getSitePath();
            static::$sitePath = $sitePath;
            static::$site_path = $sitePath;
        }

        $headerContent = $this->headerContent ?? $this->header_content;
        if (!empty($headerContent)) {
            return $headerContent;
        }

        if (empty($alias)) {
            $alias = $this->getAlias();
        }
        $templateDir = ContentBuilder::$templateDirectory ?? ContentBuilder::$template_directory;
        $templateExt = ContentBuilder::getTemplateExtension();
        if (empty($template)) {
            $template = $sitePath . $templateDir . '/'
                . ($this->hasType() ? $this->type . '_' : '') . 'header.' . $templateExt;
        }

        if (is_file($template)) {
            $replacements = $this->getReplacements();

            $this->cb->reset($template, $replacements);

            $this->headerContent = $this->cb->getContent();
            $this->header_content = $this->headerContent;
        }

        return $this->headerContent ?? $this->header_content ?? '';
    }

    public function getFooterContent(string|null $alias = null, string|null $template = null): string
    {
        $sitePath = static::$sitePath ?? static::$site_path;
        if (empty($sitePath)) {
            $sitePath = ContentBuilder::getSitePath();
            static::$sitePath = $sitePath;
            static::$site_path = $sitePath;
        }

        $footerContent = $this->footerContent ?? $this->footer_content;
        if (!empty($footerContent)) {
            return $footerContent;
        }

        if (empty($alias)) {
            $alias = $this->getAlias();
        }
        $templateDir = ContentBuilder::$templateDirectory ?? ContentBuilder::$template_directory;
        $templateExt = ContentBuilder::getTemplateExtension();
        if (empty($template)) {
            $template = $sitePath . $templateDir . '/'
                . ($this->hasType() ? $this->type . '_' : '') . 'footer.' . $templateExt;
        }

        if (is_file($template)) {
            $replacements = $this->getReplacements();

            $this->cb->reset($template, $replacements);

            $this->footerContent = $this->cb->getContent();
            $this->footer_content = $this->footerContent;
        }

        return $this->footerContent ?? $this->footer_content ?? '';
    }

    public function getBodyContent(string|null $alias = null, string $attributes = ''): string
    {
        if (empty($alias)) {
            $alias = $this->getAlias();
        }

        $bodyContent = $this->bodyContent ?? $this->body_content;
        if (!empty($bodyContent)) {
            return $bodyContent;
        }

        $replacements = $this->getReplacements();

        $bodyAttrs = $this->bodyAttributes ?? $this->body_attributes;
        if (!empty($bodyAttrs)) {
            $attributes .= $bodyAttrs;
        }

        $templateDir = ContentBuilder::$templateDirectory ?? ContentBuilder::$template_directory;
        $templateExt = ContentBuilder::getTemplateExtension();

        if ($this->hasType()) {
            $insideTemplate = $templateDir . '/' . $this->type . '.' . $templateExt;
        } else {
            $insideTemplate = $templateDir . '/' . $alias . '.' . $templateExt;
        }

        $this->cb->reset($insideTemplate, $replacements);

        $normalContent = $this->cb->getContent();

        $bodyInsideContent = $this->getHeaderContent($alias) . $normalContent . $this->getFooterContent($alias);

        $replacements = array_merge(['attributes' => $attributes, 'inside' => $bodyInsideContent], $replacements);

        $this->cb->reset($templateDir . '/body.' . $templateExt, $replacements);

        $this->bodyContent = $this->cb->getContent();
        $this->body_content = $this->bodyContent;

        return $this->bodyContent ?? $this->body_content ?? '';
    }

    public function getPageContent(string|null $alias = null, string $attributes = ''): string
    {
        if (empty($alias)) {
            $alias = $this->getAlias();
        }
        $doctypeAlias = $this->doctypeAlias ?? $this->doctype_alias;
        if (empty($doctypeAlias)) {
            $doctypeAlias = $this->getDoctypeAlias();
            $this->doctypeAlias = $doctypeAlias;
            $this->doctype_alias = $doctypeAlias;
        }

        $replacements = [
            'dtd' => $this->getDTDContent($alias),
            'attributes' => '',
            'head' => $this->getHeadContent($alias),
            'body' => $this->getBodyContent($alias),
        ];

        if (stripos($doctypeAlias, 'xhtml') !== false) {
            $replacements['attributes'] .= 'xmlns="http://www.w3.org/1999/xhtml" xml:lang="en" lang="en"';
        }

        $replacements = array_merge($replacements, $this->getReplacements());

        $templateDir = ContentBuilder::$templateDirectory ?? ContentBuilder::$template_directory;
        $templateExt = ContentBuilder::getTemplateExtension();

        $this->cb->reset($templateDir . '/page.' . $templateExt, $replacements);

        $content = $this->cb->getContent();

        return $content ?? '';
    }

    public function getHTMLContent(string|null $alias = null, string $attributes = ''): string
    {
        return $this->getPageContent($alias, $attributes) ?? '';
    }

    public function getDomain(): string
    {
        if (empty($this->domain)) {
            $this->domain = $_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? '';
        }
        return $this->domain ?? '';
    }

    public function getAlias()
    {
        if ($this->alias) {
            return $this->alias;
        }

        $alias = self::getAliasUsingURL();

        if (!$alias) {
            $alias = '_';
        }

        return $alias;
    }

    public function getContent(string|null $alias = null): string
    {
        if (empty($alias)) {
            $alias = $this->getAlias();
        }

        $content = $this->getPageContent($alias);

        if (count($this->errors)) {
            $content .= '<ul class="errors">';
            foreach ($this->errors as $errorMessage) {
                $content .= '<li>' . $errorMessage . '</li>';
            }
            $content .= '</ul>';
        }

        return $content;
    }

    public function render(string|null $alias = null)
    {
        if (empty($alias)) {
            $alias = $this->getAlias();
        }
        echo $this->getContent($alias);
    }

    public static function getAliasUsingURL(string|null $url = null): string
    {
        if (empty($url)) {
            $url = $_SERVER['SCRIPT_NAME'] ?? $_SERVER['PHP_SELF'] ?? null;
            if (empty($url) && !empty($_SERVER['REQUEST_URI'])) {
                $uriParts = parse_url($_SERVER['REQUEST_URI']);
                $url = $uriParts['path'] ?? null;
            }
        }

        $alias = str_replace('index.php', '', (string) $url);
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
