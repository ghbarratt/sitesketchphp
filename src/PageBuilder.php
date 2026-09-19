<?php

declare(strict_types=1);

namespace Sitesketch;

class PageBuilder
{
    public static $site_keywords;
    public static $site_description;
    public static $site_head_extra;
    public static $site_path;
    public static $site_alias;
    public static $site_title;
    public static $site_scripts;
    public static $site_stylesheets;
    public static $site_icon;

    public $alias;
    public $type = '';
    public $title = '';

    protected $replacements = [];

    protected $description;
    protected $keywords;
    protected $doctype_alias;
    protected $uri;
    protected $templates;
    protected $stylesheets;
    protected $scripts;
    protected $metas;
    protected $head_extra;
    protected $body_attributes;
    protected $icon;

    protected $header_content;
    protected $footer_content;
    protected $body_content;

    protected $cb;

    protected $errors = [];


    public function __construct(string|null $alias = null, array|null $replacements = null, string|null $type = null, string|null $title = null)
    {
        $this->cb = new ContentBuilder();
        if (empty($title) && !empty($alias)) {
            $title = ucwords(str_replace('_', ' ', $alias));
        }
        $this->reset($alias, $replacements, $type, $title);
    }

    public function reset(string|null $alias = null, array|null $replacements = null, string|null $type = null, string|null $title = null)
    {
        if (!empty($alias)) {
            $this->alias = $alias;
        }

        if (!empty($replacements) && is_array($replacements)) {
            $this->replacements = $replacements;
        }

        if (!empty($type)) {
            $this->type = $type;
        }

        if (!empty($title)) {
            $this->title = $title;
        } elseif (isset($this->alias) && $this->alias[0] != '_') {
            $this->title = ucwords(str_replace('_', ' ', $this->alias));
        } else {
            $url = $_SERVER['REQUEST_URI'];
            if (strpos($url, '.') !== false) {
                $url = substr($url, 0, strpos($url, '.'));
            }
            while (strlen($url) && $url[0] == '/') {
                $url = substr($url, 1);
            }
            while (strlen($url) && $url[strlen($url) - 1] == '/') {
                $url = substr($url, 0, strlen($url) - 1);
            }
            $this->title = ucwords(str_replace(['/', '_'], [' - ', ' '], parse_url($url, PHP_URL_PATH)));
        }

        if (empty($this->alias) && !empty($this->title)) {
            $this->alias = str_replace([' - ',' '], '_', strtolower($this->title));
        }

        if (empty(static::$site_title) && !empty(static::$site_alias) && static::$site_alias[0] != '_') {
            static::$site_title = ucwords(str_replace('_', ' ', static::$site_alias));
        }

        // Prepend the site title?
        if (!empty(static::$site_title) && !empty($this->title)) {
            $this->title = static::$site_title . ' - ' . $this->title;
        }

        // Page title is STILL empty?? Then just use the site title if we have that
        if (empty($this->title) && !empty(static::$site_title)) {
            $this->title = static::$site_title;
        }

        if (empty(static::$site_icon)) {
            if (is_file(($_SERVER['DOCUMENT_ROOT'] ?? '') . '/favicon.ico')) {
                static::$site_icon = '/favicon.ico';
            }
        }

        $this->stylesheets = null;
        if (!empty(static::$site_stylesheets)) {
            $this->stylesheets = static::$site_stylesheets;
        }
        $document_root_path = $_SERVER['DOCUMENT_ROOT'] ?? '';
        $attempt_to_add_css = ['/css/global.css', '/css/primary.css'];
        if (isset(static::$site_alias) && static::$site_alias) {
            $attempt_to_add_css[] = '/css/' . static::$site_alias . '.css';
        }
        if (isset($this->type) && $this->type) {
            $attempt_to_add_css[] = '/css/' . $this->type . '.css';
        }
        if (isset($this->alias) && $this->type) {
            $attempt_to_add_css[] = '/css/' . $this->alias . '.css';
        }

        foreach ($attempt_to_add_css as $ac) {
            if (is_file($document_root_path . $ac)) {
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

        $this->header_content = null;
        $this->footer_content = null;
        $this->body_content = null;
    }

    public static function setSiteVariable(string $name, string|array $value) : bool
    {
        if (!str_starts_with($name, 'site_')) {
            $name = 'site_' . $name;
        }
        if (property_exists(static::class, $name)) {
            static::${$name} = $value;
            return true;
        } else {
            return false;
        }
    }

    private function setSiteAlias(string|null $site_alias = null) : string
    {
        if (!empty($site_alias)) {
            static::$site_alias = $site_alias;
        } else {
            static::$site_alias =
                str_replace('www.', '', substr($_SERVER['HTTP_HOST'], 0, strrpos($_SERVER['HTTP_HOST'], '.')));
        }

        return static::$site_alias;
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

    private function getMetas(string|null $alias = null, string|null $description = null, array|string|null $keywords = null, string|null $content_type = null)
    {
        if (empty($alias)) {
            $alias = $this->getAlias();
        }
        if (empty($description) && !empty($this->description)) {
            $description = $this->description;
        }
        if (empty($description) && !empty(static::$site_description)) {
            $description = static::$site_description;
        }

        if (empty($keywords)) {
            if (!empty($this->keywords)) {
                $keywords = $this->keywords;
            }
        }

        if (!empty(static::$site_keywords)) {
            if (is_array($keywords)) {
                $keywords = array_merge($keywords, static::$site_keywords);
            } else {
                $keywords = static::$site_keywords;
            }
        }

        if (empty($content_type)) {
            $content_type = $this->getContentType();
        }

        if (!empty($this->metas) && count($this->metas)) {
            $metas = $this->metas;
        } else {
            $metas = [];
        }
        if (!empty($content_type)) {
            $metas[] = ['attribute' => 'http-equiv', 'attribute_value' => 'content-type', 'content' => $content_type];
        }

        if (!empty($description)) {
            $metas[] = ['attribute' => 'name', 'attribute_value' => 'description', 'content' => $description];
        }

        if (!empty($keywords)) {
            if (is_array($keywords)) {
                $keywords_string = implode(', ', $keywords);
            } else {
                $keywords_string = $keywords;
            }

            $metas[] = ['attribute' => 'name', 'attribute_value' => 'keywords', 'content' => $keywords_string];
        }

        return $metas;
    }

    public function addMeta($meta)
    {
        $this->metas[] = $meta;
    }

    public function getHeadExtra()
    {
        return $this->head_extra;
    }

    public function setKeywords(array|string $keywords)
    {
        $this->keywords = $keywords;
    }

    public function setHeadExtra($head_extra)
    {
        $this->head_extra = $head_extra;
    }

    public function setBodyAttributes($body_attributes)
    {
        $this->body_attributes = $body_attributes;
    }

    public function getDefaultReplacements()
    {
        return
        [
            'alias' => $this->getAlias(),
            'type' => $this->type,
            'doctype_alias' => $this->doctype_alias,
            'this_year' => date('Y')
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

    public function setDoctypeAlias($doctype_alias)
    {
        $this->doctype_alias = $doctype_alias;

        return $this->doctype_alias;
    }

    public function setDoctype($doctype_alias)
    {
        return $this->setDoctypeAlias($doctype_alias);
    }

    public function getDoctypeAlias()
    {
        if ($this->doctype_alias) {
            return $this->doctype_alias;
        } else {
            return 'xhtml1_transitional';
        }
    }

    public function getContentType($doctype_alias = false)
    {
        if (!$doctype_alias) {
            $doctype_alias = $this->getDoctypeAlias();
        }

        switch ($doctype_alias) {
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
                $content_type = 'text/html; charset=iso-8859-1';
                break;
            default:
                $content_type = 'text/html; charset=utf-8';
                break;
        }

        return $content_type;
    }

    public function getDTDContent(string|null $alias = null, string|null $doctype_alias = null)
    {
        if (empty($alias)) {
            $alias = $this->getAlias();
        }
        if (empty($doctype_alias)) {
            $doctype_alias = $this->getDoctypeAlias();
        }

        switch ($doctype_alias) {
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
                $this->doctype_alias = 'xhtml1_transitional';
                break;
            default: // default is HTML 5
                $access = '';
                $declaration = '';
                $link = '';
                $this->doctype_alias = 'html5';
                break;
        }

        if (isset($this->templates['dtd'])) {
            $template = $this->templates['dtd'];
        } else {
            $template = ContentBuilder::$template_directory . '/dtd.' . ContentBuilder::$template_extension;
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

        return false;
    }

    public function getScripts($scripts = false)
    {
        if (isset($this->scripts) && is_array($this->scripts) && count($this->scripts)) {
            $scripts = $this->scripts;
        }

        // Place the site scripts in first
        if (!empty(static::$site_scripts) && is_array(static::$site_scripts) && count(static::$site_scripts)) {
            $scripts = array_merge(static::$site_scripts, (is_array($scripts) ? $scripts : []));
        }

        if (is_array($scripts)) {
            // Standardize the scripts array format
            foreach ($scripts as &$script) {
                if (!is_array($script) && strpos($script, '.')) {
                    $script_src = $script;
                    $script = [];
                    $script['src'] = $script_src;
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
        return $this->icon ?? static::$site_icon ?? false;
    }

    public function getHeadContent(
        $alias = false,
        $stylesheets = false,
        $scripts = false,
        $title = false,
        $metas = false,
        $head_extra = false
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

        if (isset($this->head_extra)) {
            $head_extra .= $this->getHeadExtra();
        }

        if (!empty(static::$site_head_extra)) {
            $head_extra .= static::$site_head_extra;
        }

        $template = ContentBuilder::$template_directory . '/head.' . ContentBuilder::$template_extension;
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
        if ($head_extra) {
            $replacements['extra'] = $head_extra;
        }

        return $this->cb->getContent($template, $replacements);
    }

    public function hasType() : bool
    {
        if (!empty($this->type)) {
            return true;
        } else {
            return false;
        }
    }

    public function getHeaderContent(string|null $alias = null, string|null $template = null) : string
    {
        if (empty(static::$site_path)) {
            static::$site_path = ContentBuilder::getSitePath();
        }

        if (!empty($this->header_content)) {
            return $this->header_content;
        }

        if (empty($alias)) {
            $alias = $this->getAlias();
        }
        if (empty($template)) {
            $template = static::$site_path . ContentBuilder::$template_directory . '/'
                . ($this->hasType() ? $this->type . '_' : '') . 'header.' . ContentBuilder::$template_extension;
        }

        if (is_file($template)) {
            $replacements = $this->getReplacements();

            $this->cb->reset($template, $replacements);

            $this->header_content = $this->cb->getContent();
        }

        return $this->header_content ?? '';
    }

    public function getFooterContent(string|null $alias = null, string|null $template = null) : string
    {
        if (empty(static::$site_path)) {
            static::$site_path = ContentBuilder::getSitePath();
        }

        if (!empty($this->footer_content)) {
            return $this->footer_content;
        }

        if (empty($alias)) {
            $alias = $this->getAlias();
        }
        if (empty($template)) {
            $template = static::$site_path . ContentBuilder::$template_directory . '/'
                . ($this->hasType() ? $this->type . '_' : '') . 'footer.' . ContentBuilder::$template_extension;
        }


        if (is_file($template)) {
            $replacements = $this->getReplacements();

            $this->cb->reset($template, $replacements);

            $this->footer_content = $this->cb->getContent();
        }

        return $this->footer_content ?? '';
    }

    public function getBodyContent(string|null $alias = null, string $attributes = '') : string
    {

        if (empty($alias)) {
            $alias = $this->getAlias();
        }

        if (!empty($this->body_content)) {
            return $this->body_content;
        }

        $replacements = $this->getReplacements();

        if (!empty($this->body_attributes)) {
            $attributes .= $this->body_attributes;
        }

        if ($this->hasType()) {
            $inside_template = ContentBuilder::$template_directory . '/' . $this->type . '.'
                . ContentBuilder::$template_extension;
        } else {
            $inside_template = ContentBuilder::$template_directory . '/' . $alias . '.'
                . ContentBuilder::$template_extension;
        }

        $this->cb->reset($inside_template, $replacements);

        $normal_content = $this->cb->getContent();

        $body_inside_content = $this->getHeaderContent($alias) . $normal_content . $this->getFooterContent($alias);

        $replacements = array_merge(['attributes' => $attributes, 'inside' => $body_inside_content], $replacements);

        $this->cb->reset(ContentBuilder::$template_directory . '/body.'
            . ContentBuilder::$template_extension, $replacements);

        $this->body_content = $this->cb->getContent();

        return $this->body_content ?? '';
    }

    public function getPageContent(string|null $alias = null, string $attributes = '') : string
    {
        if (empty($alias)) {
            $alias = $this->getAlias();
        }
        if (empty($this->doctype_alias)) {
            $this->doctype_alias = $this->getDoctypeAlias();
        }

        $replacements = [
            'dtd' => $this->getDTDContent($alias),
            'attributes' => '',
            'head' => $this->getHeadContent($alias),
            'body' => $this->getBodyContent($alias)
        ];

        if (stripos($this->doctype_alias, 'xhtml') !== false) {
            $replacements['attributes'] .= 'xmlns="http://www.w3.org/1999/xhtml" xml:lang="en" lang="en"';
        }

        $replacements = array_merge($replacements, $this->getReplacements());

        $this->cb->reset(ContentBuilder::$template_directory . '/page.'
            . ContentBuilder::$template_extension, $replacements);

        $content = $this->cb->getContent();

        return $content ?? '';
    }

    public function getHTMLContent(string|null $alias = null, string $attributes = '') : string
    {
        return $this->getPageContent($alias, $attributes) ?? '';
    }

    public function getDomain() : string
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

        //echo 'DEBUG cutting alias with '.$start_position.' and '.$end_position;

        if (!$alias) {
            $alias = '_';
        }

        return $alias;
    }

    public function getContent(string|null $alias = null) : string
    {
        if (empty($alias)) {
            $alias = $this->getAlias();
        }

        $content = $this->getPageContent($alias);

        if (count($this->errors)) {
            $content .= '<ul class="errors">';
            foreach ($this->errors as $error_message) {
                $content .= '<li>' . $error_message . '</li>';
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

    public static function getAliasUsingURL(string|null $url = null) : string
    {
        if (empty($url)) {
            $url = $_SERVER['SCRIPT_NAME'] ?? $_SERVER['PHP_SELF'] ?? null;
            if (empty($url) && !empty($_SERVER['REQUEST_URI'])) {
                $uri_parts = parse_url($_SERVER['REQUEST_URI']);
                $url = $uri_parts['path'] ?? null;
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
