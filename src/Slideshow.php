<?php

declare(strict_types=1);

namespace Sitesketch;

class Slideshow extends ThumbnailGenerator
{
    private static $defaultSlideshowsDirectory;
    public $viewportSize = 'vewport';
    public $viewport_size = 'vewport';
    public $slideshowWebPath;
    public $slideshow_web_path;

    public function __construct($slideshowWebPath, $thumbnailSizes = false, $alias = false)
    {
        $this->slideshowWebPath = $slideshowWebPath;
        $this->slideshow_web_path = $slideshowWebPath;

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
        $this->viewport_size = $viewportSizeIn;
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
            $size = $this->viewportSize ?? $this->viewport_size;
        }
        $sizes = $this->thumbnailSizes ?? $this->thumbnail_sizes;
        return $sizes[$size]['width'];
    }

    public function getViewportHeight($size = null)
    {
        if (empty($size)) {
            $size = $this->viewportSize ?? $this->viewport_size;
        }
        $sizes = $this->thumbnailSizes ?? $this->thumbnail_sizes;
        return $sizes[$size]['height'];
    }

    public function getContent($template = false, $additionalReplacements = false)
    {
        if (!$template) {
            $template = ContentBuilder::$template_directory . '/slideshow.tpl';
        }
        if (!$this->images) {
            $this->createThumbnails();
        }

        // This turned out to be neccessary
        foreach ($this->images as &$i) {
            $i['slideshow_alias'] = $this->alias;
        }

        $activeViewportSize = $this->viewportSize ?? $this->viewport_size;
        $activeThumbnailSizes = $this->thumbnailSizes ?? $this->thumbnail_sizes;

        $replacements = [
            'alias'                      => $this->alias,
            'images'                     => $this->images,
            'image_count'                => count($this->images),
            'thumbnail_sizes'            => $activeThumbnailSizes,
            'viewport_size'              => $activeViewportSize,
            'viewport_width'             => $activeThumbnailSizes[$activeViewportSize]['width'],
            'viewport_height'            => $activeThumbnailSizes[$activeViewportSize]['height']
        ];
        if (isset($this->images[0])) {
            $replacements['initial_image_web_filepath'] =
                $this->images[0]['thumbnail_web_filepaths'][$activeViewportSize];
        }

        if ($additionalReplacements && is_array($additionalReplacements)) {
            $replacements = array_merge($replacements, $additionalReplacements);
        }

        $cb = new ContentBuilder($template, $replacements);

        $content = $cb->getContent();

        return $content;
    }
}
