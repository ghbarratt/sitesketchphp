<?php

declare(strict_types=1);

namespace Sitesketch;

class ThumbnailGenerator
{
    private static $defaultImagesDirectory = 'images';

    public $workingWebPath;
    public $working_web_path;
    public $webrootPath;
    public $webroot_path;
    public $workingPath;
    public $working_path;

    public $userGroup = 'dev';
    public $user_group = 'dev';
    public $setUserGroup = true;
    public $set_user_group = true;

    protected $images;
    protected $thumbnailSizes;
    protected $thumbnail_sizes;

    public function __construct($workingWebPath, $thumbnailSizes = false)
    {
        if ($workingWebPath[strlen($workingWebPath) - 1] == '/') {
            $workingWebPath = substr($workingWebPath, 0, strlen($workingWebPath) - 1);
        }
        if (!$this->resolveWorkingPath($workingWebPath)) {
            return false;
        } else {
            $this->workingWebPath = $workingWebPath;
            $this->working_web_path = $workingWebPath;
        }

        if (isset($thumbnailSizes) && is_array($thumbnailSizes)) {
            $this->setThumbnailSizes($thumbnailSizes);
        }
    }

    public function resolveWorkingPath($workingWebPath = false)
    {
        $currentWorkingPath = $this->workingPath ?? $this->working_path;
        $currentWorkingWebPath = $this->workingWebPath ?? $this->working_web_path;

        if (!$currentWorkingPath || $currentWorkingWebPath != $workingWebPath) {
            $webroot = $this->webrootPath ?? $this->webroot_path;
            if (!isset($webroot) || !$webroot) {
                $webroot = $this->resolveWebrootPath();
            }

            if ($workingWebPath[0] == '/') {
                $workingWebPath = substr($workingWebPath, 1);
            }

            $workingPath = $webroot . '/' . $workingWebPath;

            if (is_dir($workingPath)) {
                $this->workingPath = $workingPath;
                $this->working_path = $workingPath;
            } else {
                return false;
            }
        }

        return $this->workingPath ?? $this->working_path;
    }

    public function setThumbnailSizes($thumbnailSizes = false)
    {
        if ($thumbnailSizes && is_array($thumbnailSizes)) {
            $this->thumbnailSizes = $thumbnailSizes;
            $this->thumbnail_sizes = $thumbnailSizes;
        }
    }

    public function addThumbnailSize($thumbnailSize)
    {
        if (is_array($thumbnailSize) && isset($thumbnailSize['method'])) {
            $existing = $this->thumbnailSizes ?? $this->thumbnail_sizes ?? [];
            $merged = array_merge($existing, $thumbnailSize);
            $this->thumbnailSizes = $merged;
            $this->thumbnail_sizes = $merged;
            return true;
        } else {
            return false;
        }
    }

    public function resolveWebrootPath()
    {
        $webroot = $this->webrootPath ?? $this->webroot_path;
        if (!$webroot || !is_dir($webroot)) {
            if (isset($_SERVER['DOCUMENT_ROOT']) && is_dir($_SERVER['DOCUMENT_ROOT'])) {
                $this->webrootPath = $_SERVER['DOCUMENT_ROOT'];
                $this->webroot_path = $_SERVER['DOCUMENT_ROOT'];
            }
        }

        return $this->webrootPath ?? $this->webroot_path;
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
        $sizes = $this->thumbnailSizes ?? $this->thumbnail_sizes;

        if (isset($sizes)) {
            foreach ($sizes as $tsAlias => $ts) {
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
        $currentWorkingWebPath = $this->workingWebPath ?? $this->working_web_path;
        $currentWorkingPath = $this->workingPath ?? $this->working_path;
        $webroot = $this->webrootPath ?? $this->webroot_path;

        if (
            !$this->images
            || !is_array($this->images)
            || ($workingWebPath && $workingWebPath != $currentWorkingWebPath)
        ) {
            $this->images = [];

            if (!$workingWebPath && $currentWorkingWebPath) {
                $workingWebPath = $currentWorkingWebPath;
            } else {
                return false;
            }

            if (!$currentWorkingPath || $currentWorkingWebPath != $workingWebPath) {
                $this->resolveWorkingPath($workingWebPath);
                $currentWorkingPath = $this->workingPath ?? $this->working_path;
                $webroot = $this->webrootPath ?? $this->webroot_path;
            }

            $filenames = [];
            $dp = opendir($currentWorkingPath);
            while ($filename = readdir($dp)) {
                $webFilepath = $workingWebPath . '/' . $filename;
                if (
                    $this->isImage($filename)
                    && !$this->isThumbnail($webFilepath)
                    && is_file($webroot . $webFilepath)
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
                    if (is_file($webroot . $webFilepath)) {
                        $tempImageData = @getimagesize(
                            $webroot . $webFilepath,
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

        $sizes = $this->thumbnailSizes ?? $this->thumbnail_sizes;
        if (is_string($options) && isset($sizes[$options])) {
            $options = $sizes[$options];
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
            $userGroup = $this->userGroup ?? $this->user_group;
            @chgrp($thumbnailFilepath, $userGroup);
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

        $sizes = $this->thumbnailSizes ?? $this->thumbnail_sizes;
        $webroot = $this->webrootPath ?? $this->webroot_path;
        $userGroup = $this->userGroup ?? $this->user_group;

        foreach ($this->images as &$i) {
            foreach ($sizes as $tsAlias => $ts) {
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
                    $targetDir = $webroot . $i['web_path'] . '/' . $tsAlias;
                    if (!is_dir($targetDir)) {
                        if (!mkdir($targetDir)) {
                            $username = exec('whoami');
                            echo 'ERROR Could not create directory ' . $targetDir . ' as ' . $username;
                            return false;
                        }
                        $chmodResult = chmod($targetDir, 0775);
                        $chgrpResult = @chgrp($targetDir, $userGroup);
                        if (!$chgrpResult && $chmodResult) {
                            chmod($targetDir, 0777);
                        }
                    }
                    $thumbnailWebFilepath = $i['web_path'] . '/' . $tsAlias . '/' . $i['filename'];
                }
                if ($forceCreation || !is_file($webroot . $thumbnailWebFilepath)) {
                    $this->createThumbnail(
                        $webroot . $i['web_path'] . '/' . $i['filename'],
                        $webroot . $thumbnailWebFilepath,
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
