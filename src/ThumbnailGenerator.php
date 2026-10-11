<?php

declare(strict_types=1);

namespace Sitesketch;

class ThumbnailGenerator
{
    private static $defaultImagesDirectory = 'images';

    public $workingWebPath;
    public $webrootPath;
    public $workingPath;

    public $userGroup = 'dev';
    public $setUserGroup = true;

    protected $images;
    protected $thumbnailSizes;

    public function __construct($workingWebPath, $thumbnailSizes = false)
    {
        if ($workingWebPath[strlen($workingWebPath) - 1] == '/') {
            $workingWebPath = substr($workingWebPath, 0, strlen($workingWebPath) - 1);
        }
        if (!$this->resolveWorkingPath($workingWebPath)) {
            return false;
        } else {
            $this->workingWebPath = $workingWebPath;
        }

        if (isset($thumbnailSizes) && is_array($thumbnailSizes)) {
            $this->setThumbnailSizes($thumbnailSizes);
        }
    }

    public function resolveWorkingPath($workingWebPath = false)
    {
        if (!$this->workingPath || $this->workingWebPath != $workingWebPath) {
            $webroot = $this->webrootPath;
            if (!isset($webroot) || !$webroot) {
                $webroot = $this->resolveWebrootPath();
            }

            if ($workingWebPath[0] == '/') {
                $workingWebPath = substr($workingWebPath, 1);
            }

            $workingPath = $webroot . '/' . $workingWebPath;

            if (is_dir($workingPath)) {
                $this->workingPath = $workingPath;
            } else {
                return false;
            }
        }

        return $this->workingPath;
    }

    public function setThumbnailSizes($thumbnailSizes = false)
    {
        if ($thumbnailSizes && is_array($thumbnailSizes)) {
            $this->thumbnailSizes = $thumbnailSizes;
        }
    }

    public function addThumbnailSize($thumbnailSize)
    {
        if (is_array($thumbnailSize) && isset($thumbnailSize['method'])) {
            $existing = $this->thumbnailSizes ?? [];
            $this->thumbnailSizes = array_merge($existing, $thumbnailSize);
            return true;
        } else {
            return false;
        }
    }

    public function resolveWebrootPath()
    {
        if (!$this->webrootPath || !is_dir($this->webrootPath)) {
            if (isset($_SERVER['DOCUMENT_ROOT']) && is_dir($_SERVER['DOCUMENT_ROOT'])) {
                $this->webrootPath = $_SERVER['DOCUMENT_ROOT'];
            }
        }

        return $this->webrootPath;
    }

    public function isImage($filepath)
    {
        $filename = substr($filepath, (strrpos($filepath, '/') + 1));
        $extension = trim(strtolower(strrchr($filename, '.')));

        switch (strtolower($extension)) {
            case '.jpg':
            case '.gif':
            case '.png':
            case '.bmp':
            case '.jpeg':
                return true;
            default:
                break;
        }

        return false;
    }

    public function isThumbnail($filepath)
    {
        $filename = substr($filepath, (strrpos($filepath, '/') + 1));
        $answer = false;

        if (isset($this->thumbnailSizes)) {
            foreach ($this->thumbnailSizes as $tsAlias => $ts) {
                if (
                    (isset($ts['prefix']) && $ts['prefix'] && strpos($filename, $ts['prefix']) !== false)
                    || (isset($ts['postfix']) && $ts['postfix'] && strpos($filename, $ts['postfix']) !== false)
                    || (
                        isset($ts['directory'])
                        && $ts['directory']
                        && strpos($filepath, '/' . $ts['directory'] . '/') !== false
                    )
                    || (isset($tsAlias) && $tsAlias && strpos($filepath, '/' . $tsAlias . '/') !== false)
                ) {
                    $answer = true;
                    break;
                }
            }
        }

        return $answer;
    }

    private function resolveImages($workingWebPath = false, $doSort = true)
    {
        if (
            !$this->images
            || !is_array($this->images)
            || ($workingWebPath && $workingWebPath != $this->workingWebPath)
        ) {
            $this->images = [];

            if (!$workingWebPath && $this->workingWebPath) {
                $workingWebPath = $this->workingWebPath;
            } else {
                return false;
            }

            if (!$this->workingPath || $this->workingWebPath != $workingWebPath) {
                $this->resolveWorkingPath($workingWebPath);
            }

            $filenames = [];
            $dp = opendir($this->workingPath);
            while ($filename = readdir($dp)) {
                $webFilepath = $workingWebPath . '/' . $filename;
                if (
                    $this->isImage($filename)
                    && !$this->isThumbnail($webFilepath)
                    && is_file($this->webrootPath . $webFilepath)
                ) {
                    $filenames[] = $filename;
                }
            }
            closedir($dp);

            if (is_array($filenames) && count($filenames)) {
                if ($doSort) {
                    sort($filenames);
                }

                foreach ($filenames as $filename) {
                    $webFilepath = $workingWebPath . '/' . $filename;
                    $tempImage = [];
                    $tempImageData = [];
                    $tempExtendedImageData = [];
                    if (is_file($this->webrootPath . $webFilepath)) {
                        $tempImageData = @getimagesize(
                            $this->webrootPath . $webFilepath,
                            $tempExtendedImageData
                        );
                    }
                    $newImageData = [];
                    $newImageData['web_path'] = $workingWebPath;
                    $newImageData['filename'] = $filename;
                    $newImageData['width'] = $tempImageData[0] ?? 0;
                    $newImageData['height'] = $tempImageData[1] ?? 0;
                    $newImageData['type'] = $tempImageData[2] ?? 0;
                    if (isset($tempImageData['bits'])) {
                        $newImageData['bits'] = $tempImageData['bits'];
                    }
                    if (isset($tempImageData['channels'])) {
                        $newImageData['channels'] = $tempImageData['channels'];
                    }

                    if (is_array($tempExtendedImageData)) {
                        $tempImageData = array_merge($newImageData, $tempExtendedImageData);
                    } else {
                        $tempImageData = $newImageData;
                    }

                    foreach ($tempImageData as $tidIndex => $tid) {
                        $tempImage[$tidIndex] = $tid;
                    }

                    $this->images[] = $tempImage;
                }
            }
        }

        return $this->images;
    }

    public function createThumbnail($originalFilepath, $thumbnailFilepath, $options)
    {
        ini_set('memory_limit', '100M');
        set_time_limit(3600);

        if (is_string($options) && isset($this->thumbnailSizes[$options])) {
            $options = $this->thumbnailSizes[$options];
        }

        $originalSize = getimagesize($originalFilepath);
        $originalWidth = $originalSize[0];
        $originalHeight = $originalSize[1];

        $adjustedOriginalWidth = $originalSize[0];
        $adjustedOriginalHeight = $originalSize[1];
        $thumbnailWidth = $originalWidth;
        $thumbnailHeight = $originalHeight;

        if (isset($options['width'])) {
            $thumbnailWidth = $options['width'];
        }
        if (isset($options['height'])) {
            $thumbnailHeight = $options['height'];
        }

        $fitting = false;
        $originalAspectRatio = $originalWidth / $originalHeight;
        $idealThumbnailAspectRatio = $thumbnailWidth / $thumbnailHeight;
        if ($originalAspectRatio > $idealThumbnailAspectRatio) {
            $fitting = 'wide';
        } elseif ($originalAspectRatio < $idealThumbnailAspectRatio) {
            $fitting = 'tall';
        }

        if (isset($options['method'])) {
            $method = strtolower($options['method']);
        } else {
            $method = 'fit';
        }

        $srcX = 0;
        $srcY = 0;

        if ($method == 'smaller') {
            if ($fitting == 'tall') {
                $method = 'height';
            } else {
                $method = 'width';
            }
        } elseif ($method == 'larger') {
            if ($fitting == 'tall') {
                $method = 'width';
            } else {
                $method = 'height';
            }
        }

        if ($method == 'width') {
            $thumbnailHeight = $thumbnailWidth * $originalHeight / $originalWidth;
        } elseif ($method == 'height') {
            $thumbnailWidth = $thumbnailHeight * $originalWidth / $originalHeight;
        } elseif (
            ($method == 'fit' && $fitting == 'tall')
            || ($method == 'crop' && $fitting == 'wide')
        ) {
            $adjustedOriginalWidth = $originalHeight * $thumbnailWidth / $thumbnailHeight;
            $srcX = ($originalWidth - $adjustedOriginalWidth) / 2;
        } elseif (
            ($method == 'crop' && $fitting == 'tall')
            || ($method == 'fit' && $fitting == 'wide')
        ) {
            $adjustedOriginalHeight = $originalWidth * $thumbnailHeight / $thumbnailWidth;
            $srcY = ($originalHeight - $adjustedOriginalHeight) / 2;
        }

        $thumbnailImage = imagecreatetruecolor((int)$thumbnailWidth, (int)$thumbnailHeight);

        switch ($originalSize[2]) {
            case 1:
                $originalImage = imagecreatefromgif($originalFilepath);
                break;
            case 2:
                $originalImage = imagecreatefromjpeg($originalFilepath);
                break;
            case 3:
                $originalImage = imagecreatefrompng($originalFilepath);
                break;
            default:
                $originalImage = imagecreatefromjpeg($originalFilepath);
                break;
        }

        $quality = isset($options['quality']) ? $options['quality'] : 80;

        imagecopyresized(
            $thumbnailImage,
            $originalImage,
            0,
            0,
            (int)$srcX,
            (int)$srcY,
            (int)$thumbnailWidth,
            (int)$thumbnailHeight,
            (int)$adjustedOriginalWidth,
            (int)$adjustedOriginalHeight
        );

        $result = false;
        if (imagejpeg($thumbnailImage, $thumbnailFilepath, $quality)) {
            chmod($thumbnailFilepath, 0664);
            @chgrp($thumbnailFilepath, $this->userGroup);
            $result = true;
        }
        imagedestroy($thumbnailImage);
        imagedestroy($originalImage);

        return $result;
    }

    public function createThumbnails($forceCreation = false)
    {
        if (!isset($this->images) || !is_array($this->images)) {
            $this->resolveImages();
        }

        if (!is_array($this->images)) {
            return false;
        }

        foreach ($this->images as &$i) {
            foreach ($this->thumbnailSizes as $tsAlias => $ts) {
                if (
                    (isset($ts['prefix']) && $ts['prefix'])
                    || (isset($ts['postfix']) && $ts['postfix'])
                ) {
                    $dotPos = strrpos($i['filename'], '.');
                    $filenameBase = substr($i['filename'], 0, $dotPos);
                    $extension = substr($i['filename'], $dotPos + 1);
                    $thumbnailWebFilepath = $i['web_path'] . '/' . (isset($ts['prefix']) ? $ts['prefix'] : '')
                        . $filenameBase . (isset($ts['postfix']) ? $ts['postfix'] : '') . '.' . $extension;
                } else {
                    $targetDir = $this->webrootPath . $i['web_path'] . '/' . $tsAlias;
                    if (!is_dir($targetDir)) {
                        if (!mkdir($targetDir)) {
                            $username = exec('whoami');
                            echo 'ERROR Could not create directory ' . $targetDir . ' as ' . $username;
                            return false;
                        }
                        $chmodResult = chmod($targetDir, 0775);
                        $chgrpResult = @chgrp($targetDir, $this->userGroup);
                        if (!$chgrpResult && $chmodResult) {
                            chmod($targetDir, 0777);
                        }
                    }
                    $thumbnailWebFilepath = $i['web_path'] . '/' . $tsAlias . '/' . $i['filename'];
                }
                if ($forceCreation || !is_file($this->webrootPath . $thumbnailWebFilepath)) {
                    $this->createThumbnail(
                        $this->webrootPath . $i['web_path'] . '/' . $i['filename'],
                        $this->webrootPath . $thumbnailWebFilepath,
                        $ts
                    );
                }
                $i['thumbnail_web_filepaths'][$tsAlias] = $thumbnailWebFilepath;
            }
        }
    }

    public function getContent($template = false, $additionalReplacements = false)
    {
        return 'The Thumbnailer does not provide content';
    }

    public function getImages()
    {
        if (!isset($this->images) || !is_array($this->images)) {
            $this->resolveImages();
        }
        return $this->images;
    }
}
