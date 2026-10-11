<?php

declare(strict_types=1);

namespace Sitesketch;

class SlideController
{
    public static $webDirectory = 'web';
    public static $sitePath;
    public static $slidesWebpath = '/images/slides/';

    private static $dbh;
    private static $cb;
    private static $thumbnailDirectory = 'thumbnail';
    private static $thumbnailSizeAlias = 'thumbnail';
    private static $possibleExtensions = ['.jpg', '.gif', '.png', '.jpeg', '.JPG', '.GIF', '.PNG'];
    private static $changeGroup = 'dev';

    private static $debugging = false;
    private static $thumbnailSizes = [];

    private $slidesSubpath;
    private $template = 'slide_controls.tpl';

    private $requireProcessingForRender = false;
    private $processed = false;

    private $data;
    private $errors;

    public function __construct($dbhIn = false, $cb = false)
    {
        global $dbh;

        if (!isset(self::$sitePath) || !self::$sitePath) {
            self::$sitePath = ContentBuilder::getSitePath();
        }

        if ($dbhIn) {
            self::$dbh = $dbhIn;
        } elseif (isset($dbh) && $dbh) {
            self::$dbh = $dbh;
        }

        if ($cb) {
            self::$cb = $cb;
        } else {
            self::$cb = new ContentBuilder();
        }
    }

    public static function getSlidesPath()
    {
        if (!isset(self::$sitePath) || !self::$sitePath) {
            self::$sitePath = ContentBuilder::getSitePath();
        }
        return self::$sitePath . DIRECTORY_SEPARATOR . self::$webDirectory . DIRECTORY_SEPARATOR . 'images'
            . DIRECTORY_SEPARATOR . 'slides';
    }

    public function getSlideCollections($params = false)
    {
        $slidesPath = self::getSlidesPath();

        // What are the slide collections? - the directories under the slides_path should tell us
        $slideCollectionAliases = glob($slidesPath . DIRECTORY_SEPARATOR . '*');

        // Clean off the path
        foreach ($slideCollectionAliases as &$sca) {
            $sca = str_replace($slidesPath . DIRECTORY_SEPARATOR, '', $sca);
        }

        $slideCollections = [];

        // Now for each of these slide collections, scan the directories to find
        foreach ($slideCollectionAliases as &$sca) {
            $slides = glob($slidesPath . DIRECTORY_SEPARATOR . $sca . DIRECTORY_SEPARATOR . '*');
            // Clean off the path
            foreach ($slides as $si => &$s) {
                $s = str_replace($slidesPath . DIRECTORY_SEPARATOR . $sca . DIRECTORY_SEPARATOR, '', $s);

                // Thumbnails are a special exception
                if ($s == 'thumbnail') {
                    unset($slides[$si]);
                }
            }
            $slideCollections[$sca] = $slides;
        }

        return $slideCollections;
    }

    public function getSlidesSubpath()
    {
        if (!$this->slidesSubpath) {
            $this->slidesSubpath = str_replace('index.php', '', $_SERVER['PHP_SELF']);
        }
        if ($this->slidesSubpath[strlen($this->slidesSubpath) - 1] == DIRECTORY_SEPARATOR) {
            $this->slidesSubpath = substr($this->slidesSubpath, 0, strlen($this->slidesSubpath) - 1);
        }
        if ($this->slidesSubpath && $this->slidesSubpath[0] == DIRECTORY_SEPARATOR) {
            $this->slidesSubpath = substr($this->slidesSubpath, 1);
        }
        return $this->slidesSubpath;
    }

    public function getThumbnails($subpath = null)
    {
        if (!$subpath && $this->slidesSubpath) {
            $subpath = $this->slidesSubpath;
        }
        if (!$subpath) {
            $subpath = $this->getSlidesSubpath();
        }

        $slidesPath = self::getSlidesPath();

        // What are the slide collections? - the directories under the slides_path should tell us
        $thumbnailCandidates = glob($slidesPath . DIRECTORY_SEPARATOR . $subpath . DIRECTORY_SEPARATOR
            . self::$thumbnailDirectory . DIRECTORY_SEPARATOR . '*');

        $thumbnails = [];
        $slidesWebpath = self::$slidesWebpath;

        // Clean out directories and clean off the slide path
        foreach ($thumbnailCandidates as &$tc) {
            $newThumbnail = false;
            foreach (self::$possibleExtensions as $pe) {
                if (stripos($tc, $pe) !== false) {
                    $newThumbnail = [
                        'filename' => str_replace($slidesPath . DIRECTORY_SEPARATOR . $subpath . DIRECTORY_SEPARATOR
                            . self::$thumbnailDirectory . DIRECTORY_SEPARATOR, '', $tc),
                        'thumbnail_server_filepath' => $tc,
                    ];
                    $newThumbnail['thumbnail_web_filepath'] = $slidesWebpath
                        . ($this->slidesSubpath ? $this->slidesSubpath . DIRECTORY_SEPARATOR : '')
                        . self::$thumbnailSizeAlias . DIRECTORY_SEPARATOR . $newThumbnail['filename'];
                    $newThumbnail['original_web_filepath'] = $slidesWebpath
                        . ($this->slidesSubpath ? $this->slidesSubpath . DIRECTORY_SEPARATOR : '')
                        . $newThumbnail['filename'];
                    break;
                }
            }
            if ($newThumbnail) {
                $thumbnails[] = $newThumbnail;
            }
        }

        return $thumbnails;
    }

    public function getSlides($subpath = null)
    {
        if (!$subpath && $this->slidesSubpath) {
            $subpath = $this->slidesSubpath;
        }
        if (!$subpath) {
            $subpath = $this->getSlidesSubpath();
        }

        $this->slidesSubpath = $subpath;

        $slidesPath = self::getSlidesPath();

        // What are the slide collections? - the directories under the slides_path should tell us
        $slideCandidates = glob($slidesPath . DIRECTORY_SEPARATOR . $subpath . DIRECTORY_SEPARATOR . '*');

        $slides = [];

        // Clean out directories and clean off the slide path
        foreach ($slideCandidates as &$sc) {
            foreach (self::$possibleExtensions as $pe) {
                if (stripos($sc, $pe) !== false) {
                    $slides[] = str_replace(
                        $slidesPath . DIRECTORY_SEPARATOR . $subpath . DIRECTORY_SEPARATOR,
                        '',
                        $sc
                    );
                    break;
                }
            }
        }

        return $slides;
    }

    public function setTemplate($template)
    {
        $this->template = $template;
    }

    public function getContent($template = false, $replacements = false)
    {
        // Check if the first parameter is actually just the collection alias
        $pos = stripos($template, ContentBuilder::getTemplateExtension());
        if ($pos === false || $pos !== (strlen($template) - strlen(ContentBuilder::getTemplateExtension()))) {
            $collectionAlias = $template;
            $template = $this->template;
        } else {
            $collectionAlias = substr($template, 0, -strlen(ContentBuilder::getTemplateExtension()));
        }

        if (!$template) {
            $template = $this->template;
        }

        $slides = $this->getSlides($this->slidesSubpath);

        if (count($slides) > 1) {
            // We need to make the slide paths webroot-relative
            $slidesWebpath = self::$slidesWebpath;
            if ($slidesWebpath) {
                foreach ($slides as &$s) {
                    $s = $slidesWebpath
                        . ($this->slidesSubpath ? $this->slidesSubpath . DIRECTORY_SEPARATOR : '') . $s;
                }
            }

            $replacements['slides'] = $slides;

            return self::$cb->getContent($template, $replacements);
        } else {
            return false;
        }
    }

    public function getErrors()
    {
        return $this->errors;
    }
}
