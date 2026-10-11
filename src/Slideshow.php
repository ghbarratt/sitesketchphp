<?php

declare(strict_types=1);

namespace Sitesketch;

class Slideshow extends ThumbnailGenerator
{
    private static $defaultSlideshowsDirectory;
    public $viewportSize = 'vewport';
    public $slideshowWebPath;

    public function __construct($slideshowWebPath, $thumbnailSizes = false, $alias = false)
    {
        $this->slideshowWebPath = $slideshowWebPath;

        if (!$alias) {
            $this->alias = substr($slideshowWebPath, strrpos($slideshowWebPath, '/', -2) + 1);
        } else {
            $this->alias = $alias;
        }

        // Now call the thumbnail generator
        parent::__construct($slideshowWebPath, $thumbnailSizes);
    }

    public function setViewportSize($viewportSizeIn)
    {
        $this->viewportSize = $viewportSizeIn;
    }

    public function getImageCount()
    {
        if (!$this->images) {
            $this->createThumbnails();
        }
        return count($this->images);
    }

    public function getViewportWidth($size = null)
    {
        if (empty($size)) {
            $size = $this->viewportSize;
        }
        return $this->thumbnailSizes[$size]['width'];
    }

    public function getViewportHeight($size = null)
    {
        if (empty($size)) {
            $size = $this->viewportSize;
        }
        return $this->thumbnailSizes[$size]['height'];
    }

    public function getContent($template = false, $additionalReplacements = false)
    {
        if (!$template) {
            $template = ContentBuilder::$templateDirectory . '/slideshow.tpl';
        }
        if (!$this->images) {
            $this->createThumbnails();
        }

        // This turned out to be neccessary
        foreach ($this->images as &$i) {
            $i['slideshow_alias'] = $this->alias;
        }

        $replacements = [
            'alias'                      => $this->alias,
            'images'                     => $this->images,
            'image_count'                => count($this->images),
            'thumbnail_sizes'            => $this->thumbnailSizes,
            'viewport_size'              => $this->viewportSize,
            'viewport_width'             => $this->thumbnailSizes[$this->viewportSize]['width'],
            'viewport_height'            => $this->thumbnailSizes[$this->viewportSize]['height'],
        ];
        if (isset($this->images[0])) {
            $replacements['initial_image_web_filepath'] =
                $this->images[0]['thumbnail_web_filepaths'][$this->viewportSize];
        }

        if ($additionalReplacements && is_array($additionalReplacements)) {
            $replacements = array_merge($replacements, $additionalReplacements);
        }

        $cb = new ContentBuilder($template, $replacements);

        $content = $cb->getContent();

        return $content;
    }
}
