<?php

/**
 * Created by PhpStorm.
 * Author: LYK
 * Date: 9/20/2013
 * Time: 5:13 PM
 * Reference : http://www.tuxradar.com/practicalphp/11/2/(1-30)
 */

namespace Npf\Library;

/**
 * Class GD
 * Draw some image graphic effects.
 * @package Framework\Utils\Helper
 */
class Gd
{

    private $imgResource, $imgWidth, $imgHeight;

    /**
     * @param null $img
     * @return bool
     */
    public function importImage($img = NULL)
    {
        if ($this->isGDResource($img)) {
            $this->imgResource = $img;
            $this->initLoad();
            return TRUE;
        } elseif (is_string($img)) {
            $img = @imagecreatefromstring($img);
            if ($img !== FALSE) {
                $this->imgResource = $img;
                return TRUE;
            } else  return FALSE;
        } else  return FALSE;
    }

    /**
     * Is GD Resource
     * @param NULL $img
     * @return bool
     */
    private function isGDResource($img = NULL)
    {
        if (NULL === $img) $img = $this->imgResource;
        if (!is_resource($img)) return FALSE;
        if (get_resource_type($img) === 'gd') return TRUE;
        return TRUE;
    }

    /**
     * Initial Load and Initial Save and Check Resource
     * @param NULL $img
     * @return bool
     */
    private function initLoad($img = NULL)
    {
        if (NULL === $img) $img = $this->imgResource;
        if (!$this->isGDResource($img)) return FALSE;
        imagealphablending($img, TRUE);
        imagesavealpha($img, TRUE);
        $this->imgWidth = imagesx($img);
        $this->imgHeight = imagesy($img);
        return TRUE;
    }

    /**
     * Section: Special Process
     */

    /**
     * Get Color
     * @param int $red
     * @param int $green
     * @param int $blue
     * @param int $Alpha
     * @param NULL $img
     * @return bool|int
     */
    public function getColor($red = 0, $green = 0, $blue = 0, $Alpha = 0, $img = NULL)
    {
        if (NULL === $img) $img = $this->imgResource;
        if (!$this->isGDResource($img)) return FALSE;
        $red = (int)$red;
        $green = (int)$green;
        $blue = (int)$blue;
        $Alpha = (int)$Alpha;
        return @imagecolorallocatealpha($img, $red, $green, $blue, $Alpha);
    }

    /**
     * Get Loaded Image Given Position Pixel Color
     * @param int $x
     * @param int $y
     * @param bool $Assoc
     * @param NULL $img
     * @return array|bool|int
     */
    public function getPixelColor($x = 0, $y = 0, $Assoc = FALSE, $img = NULL)
    {
        if (NULL === $img) $img = $this->imgResource;
        if (!$this->isGDResource($img)) return FALSE;
        $x = (int)$x;
        $y = (int)$y;
        $Assoc = (boolean)$Assoc;
        $color = imagecolorat($img, $x, $y);
        if ($Assoc !== TRUE) return $color;
        else  return imagecolorsforindex($img, $color);
    }

    /**
     * Get Loaded Image Orientation
     * @param NULL $img
     * @return bool|string
     */
    public function getOrientation($img = NULL)
    {
        if (NULL === $img) $img = $this->imgResource;
        if (!$this->isGDResource($img)) return FALSE;
        if (imagesx($img) > imagesy($img))
            return 'LANDSCAPE';
        else
            return 'PORTRAIT';
    }

    /**
     * Image Process - Copy image from file to memory (partial of rect)
     * @param $file
     * @param NULL $Rect
     * @param NULL $img
     * @return bool
     */
    public function copyImageFromFile($file, $Rect = NULL, $img = NULL)
    {
        if (NULL === $img) $img = $this->imgResource;
        if (!$this->isGDResource($img)) return FALSE;
        if (!is_array($Rect)) $Rect = [];
        if (!array_key_exists('X', $Rect)) $Rect['X'] = (int)0;
        if (!array_key_exists('Y', $Rect)) $Rect['Y'] = (int)0;
        if (!array_key_exists('L', $Rect)) $Rect['L'] = (int)0;
        if (!array_key_exists('T', $Rect)) $Rect['T'] = (int)0;
        $imgSrc = $this->loadImageFromFile($file);

        if ($imgSrc !== FALSE) {

            if (!array_key_exists('W', $Rect) || empty($Rect['W'])) $Rect['W'] = (int)imagesx($imgSrc);
            if (!array_key_exists('H', $Rect) || empty($Rect['H'])) $Rect['H'] = (int)imagesy($imgSrc);
            $this->imgResource = $img;
            $this->initLoad($img);
            $Rect['X'] = (int)$Rect['X'];
            $Rect['Y'] = (int)$Rect['Y'];
            $Rect['W'] = (int)$Rect['W'];
            $Rect['H'] = (int)$Rect['H'];
            $Rect['L'] = (int)$Rect['L'];
            $Rect['T'] = (int)$Rect['T'];

            imagecopyresampled($img, $imgSrc, $Rect['X'], $Rect['Y'], $Rect['L'], $Rect['T'], $Rect['W'], $Rect['H'], imagesx($imgSrc),
                imagesy($imgSrc));
            imagealphablending($img, TRUE);
            imagedestroy($imgSrc);
            return TRUE;
        } else  return FALSE;
    }

    /**
     * Section: Graphic Tool
     */

    /**
     * Load File Image To Memory
     * @param string $File
     * @return bool|resource|string
     */
    public function loadImageFromFile($File = '')
    {
        $img = FALSE;
        if ($this->isGDResource($File)) {
            $this->imgResource = $File;
            $this->initLoad();
            return $this->imgResource;
        } elseif (file_exists($File)) {
            if (function_exists('exif_imagetype')) $imgType = exif_imagetype($File);
            else {
                $imgInfo = getimagesize($File);
                $imgType = $imgInfo[2];
            }
            switch ($imgType) {
                case IMAGETYPE_GIF:
                    if (imagetypes() & IMG_GIF) $img = @imagecreatefromgif($File);
                    break;
                case IMAGETYPE_JPEG:
                    if (imagetypes() & IMG_JPG) $img = @imagecreatefromjpeg($File);
                    break;
                case IMAGETYPE_PNG:
                    if (imagetypes() & IMG_PNG) $img = @imagecreatefrompng($File);
                    break;
                case IMAGETYPE_XBM:
                    if (imagetypes() & IMAGETYPE_XBM) $img = @imagecreatefromxbm($File);
                    break;
                case IMAGETYPE_WBMP:
                    if (imagetypes() & IMG_WBMP) $img = @imagecreatefromwbmp($File);
                    break;
                default: #Try load the to image
                    $img = @imagecreatefromstring(file_get_contents($File));
            }
            if (!$this->isGDResource($img)) return FALSE;
            else {
                $this->imgResource = $img;
                $this->initLoad();
                return $this->imgResource;
            }
        } else  return FALSE;
    }

    /**
     * Image Process - Merge Image from Load from file and memory with percentage override.
     * @param $File
     * @param NULL $Rect
     * @param int $Percent
     * @param string $crop
     * @param NULL $img
     * @return bool
     */
    public function mergeImageFromFile($File, $Rect = NULL, $Percent = 100, $crop = '', $img = NULL)
    {
        if (NULL === $img) $img = $this->imgResource;
        if (!$this->isGDResource($img)) return FALSE;
        $Percent = (int)$Percent;
        if (!is_array($Rect)) $Rect = [];
        if (!array_key_exists('X', $Rect)) $Rect['X'] = (int)0;
        if (!array_key_exists('Y', $Rect)) $Rect['Y'] = (int)0;
        if (!array_key_exists('W', $Rect) || empty($Rect['W'])) $Rect['W'] = (int)imagesx($img);
        if (!array_key_exists('H', $Rect) || empty($Rect['H'])) $Rect['H'] = (int)imagesy($img);
        $imgSrc = $this->loadImageFromFile($File);
        $imgSrc = $this->resizeCaves($Rect['W'], $Rect['H'], $crop, $imgSrc);
        $this->imgResource = $img;
        $this->initLoad($img);
        $Rect['X'] = (int)$Rect['X'];
        $Rect['Y'] = (int)$Rect['Y'];
        $Rect['W'] = (int)$Rect['W'];
        $Rect['H'] = (int)$Rect['H'];

        if (is_resource($imgSrc)) {
            imagecopymerge($img, $imgSrc, $Rect['X'], $Rect['Y'], 0, 0, $Rect['W'], $Rect['H'], $Percent);
            imagedestroy($imgSrc);
            return TRUE;
        } else  return FALSE;
    }

    /**
     * Image Process - Resize Caves, Crop or resize image
     * @param int $width
     * @param int $height
     * @param bool $crop
     * @param string $Align
     * @param NULL $img
     * @return bool|NULL
     */
    public function resizeCaves($width = 0, $height = 0, $crop = FALSE, $Align = 'cc', $img = NULL)
    {
        if (NULL === $img) $img = $this->imgResource;
        if (!$this->isGDResource($img)) return FALSE;
        $width = (int)$width;
        $height = (int)$height;
        if (empty($width) && empty($height)) return $img;
        $this->initLoad($img);
        $imageX = $this->imgWidth;
        $imageY = $this->imgHeight;
        $SARatio = $imageX / $imageY;
        $RARatio = $width / $height;
        if (($crop !== FALSE && $RARatio < $SARatio) || ($crop === FALSE && $RARatio > $SARatio)) {
            $newWidth = (int)($height * $SARatio);
            $newHeight = $height;
        } else {
            $newWidth = $width;
            $newHeight = (int)($width / $SARatio);
        }
        $newImg = $this->createImage($width, $height);

        switch (strtolower($Align)) {
            case 'lt':
                $x = 0;
                $y = 0;
                break;

            case 'cb':
            case 'ct':
                $x = ($width - $newWidth) / 2;
                $y = $height - $newHeight;
                break;

            case 'rt':
                $x = $width - $newWidth;
                $y = 0;
                break;

            case 'lb':
                $x = 0;
                $y = $height - $newHeight;
                break;

            case 'rb':
                $x = $width - $newWidth;
                $y = $height - $newHeight;
                break;

            default:
                $x = ($width - $newWidth) / 2;
                $y = ($height - $newHeight) / 2;
        }
        imagecopyresampled($newImg, $img, $x, $y, 0, 0, $newWidth, $newHeight, $imageX, $imageY);
        imagedestroy($img);
        return $this->imgResource;
    }

    /**
     * Create image to memory
     * @param $width
     * @param $height
     * @return bool|resource
     */
    public function createImage($width, $height)
    {
        $width = (int)$width;
        $height = (int)$height;
        if (!empty($width) && !empty($height)) {
            $this->imgResource = imagecreatetruecolor($width, $height);
            imagefill($this->imgResource, 0, 0, imagecolorallocatealpha($this->imgResource, 255, 255, 255, 127));
            $this->initLoad();
            return $this->imgResource;
        } else  return FALSE;
    }

    /**
     * Image Process - Fill Color Start From Given Position
     * @param int $color
     * @param int $x
     * @param int $y
     * @param NULL $img
     * @return bool
     */
    public function fillImage($color = 0, $x = 0, $y = 0, $img = NULL)
    {
        if (NULL === $img) $img = $this->imgResource;
        if (!$this->isGDResource($img)) return FALSE;
        $x = (int)$x;
        $y = (int)$y;
        $color = (int)$color;
        return @imagefill($img, $x, $y, $color);
    }

    /**
     * Image Process - Draw a rectangle from given position
     * @param int $x1
     * @param int $y1
     * @param int $x2
     * @param int $y2
     * @param int $color
     * @param bool $fill
     * @param NULL $img
     * @return bool
     */
    public function drawRectangle($x1 = 0, $y1 = 0, $x2 = 0, $y2 = 0, $color = 0, $fill = false, $img = NULL)
    {
        if (NULL === $img) $img = $this->imgResource;
        if (!$this->isGDResource($img)) return FALSE;
        $x1 = (int)$x1;
        $y1 = (int)$y1;
        $x2 = (int)$x2;
        $y2 = (int)$y2;
        $color = (int)$color;
        $fill = (boolean)$fill;
        if ($fill)
            return @imagefilledrectangle($img, $x1, $y1, $x2, $y2, $color);
        else
            return @imagerectangle($img, $x1, $y1, $x2, $y2, $color);
    }

    /**
     * Image Process - Draw a rectangle from given position
     * @param int $x1
     * @param int $y1
     * @param int $x2
     * @param int $y2
     * @param int $color
     * @param NULL $img
     * @return bool
     */
    public function drawLine($x1 = 0, $y1 = 0, $x2 = 0, $y2 = 0, $color = 0, $img = NULL)
    {
        if (NULL === $img) $img = $this->imgResource;
        if (!$this->isGDResource($img)) return FALSE;
        $x1 = (int)$x1;
        $y1 = (int)$y1;
        $x2 = (int)$x2;
        $y2 = (int)$y2;
        $color = (int)$color;
        return @imageline($img, $x1, $y1, $x2, $y2, $color);
    }

    /**
     * Image Process - Rotate Image
     * @param int $angle
     * @param int $Background
     * @param bool $IgnoreTrans
     * @param NULL $img
     * @return bool|resource
     */
    public function rotateImage($angle = 0, $Background = 0, $IgnoreTrans = FALSE, $img = NULL)
    {
        if (NULL === $img) $img = $this->imgResource;
        if (!$this->isGDResource($img)) return FALSE;
        $angle = (double)$angle;
        $Background = (int)$Background;
        $IgnoreTrans = (boolean)$IgnoreTrans;
        if (!empty($angle)) {
            $this->initSave($img);
            $result = imagerotate($this->imgResource, $angle, $Background, $IgnoreTrans);
            imagedestroy($img);
            $this->initLoad($result);
            $this->imgResource = $result;
            return $this->imgResource = $result;
        } else
            return FALSE;
    }

    /**
     * Initial Save and Check Resource
     * @param NULL $img
     * @return bool
     */
    private function initSave($img = NULL)
    {
        if (NULL === $img) $img = $this->imgResource;
        if (!$this->isGDResource($img)) return FALSE;
        imagesavealpha($img, TRUE);
        return TRUE;
    }

    /**
     * Image Process - Resize Image
     * @param int $width
     * @param int $height
     * @param NULL $img
     * @return bool|NULL
     */
    public function resize($width = 0, $height = 0, $img = NULL)
    {
        if (NULL === $img) $img = $this->imgResource;
        if (!$this->isGDResource($img)) return FALSE;
        $width = (int)$width;
        $height = (int)$height;
        if (empty($width) && empty($height)) return $img;
        $this->initLoad($img);
        $imageX = $this->imgWidth;
        $imageY = $this->imgHeight;
        if (!empty($width) && empty($height)) $height = $this->getNewHeight($width);
        if (empty($width) && !empty($height)) $width = $this->getNewWidth($height);
        $newImg = $this->createImage($width, $height);
        imagecopyresampled($newImg, $img, 0, 0, 0, 0, $width, $height, $imageX, $imageY);
        imagedestroy($img);
        return $this->imgResource;
    }

    /**
     * Get loaded image calculate new maintain ratio height with given width
     * @param int $width
     * @return float
     */
    public function getNewHeight($width = 0)
    {
        return $this->imgHeight / ($this->imgWidth / $width);
    }

    /**
     * Get Loaded Image Calculate new maintain ratio width with given height
     * @param int $height
     * @return float
     */
    public function getNewWidth($height = 0)
    {
        return $this->imgWidth / ($this->imgHeight / $height);
    }

    /**
     * Image Process - Flip Image
     * @param bool $Vertical
     * @param bool $Horizontal
     * @param NULL $img
     * @return bool|resource
     */
    public function flipImage($Vertical = FALSE, $Horizontal = FALSE, &$img = NULL)
    {
        if (NULL === $img) $img = $this->imgResource;
        if (!$this->isGDResource($img)) return FALSE;
        if (empty($Vertical) && empty($Horizontal)) return FALSE;
        $startX = 0;
        $startY = 0;
        $imageX = $this->imgWidth;
        $imageY = $this->imgHeight;
        if ($Horizontal === TRUE) {
            $startX = $imageX - 1;
            $imageX *= -1;
        }
        if ($Vertical === TRUE) {
            $startY = $imageY - 1;
            $imageY *= -1;
        }
        $result = imagecreatetruecolor($this->imgWidth, $this->imgHeight);
        imagecopyresampled($result, $img, 0, 0, $startX, $startY, $this->imgWidth, $this->imgHeight, $imageX, $imageY);
        imagedestroy($img);
        $this->imgResource = $result;
        $this->initLoad($result);
        return $result;
    }

    /**
     * Image Process - Masking Color with Alpha
     * @param $maskImage
     * @param bool $crop
     * @param NULL $img
     * @return bool|resource
     */
    public function maskAlpha($maskImage, $crop = TRUE, $img = NULL)
    {
        if (NULL === $img) $img = $this->imgResource;
        if (!$this->isGDResource($img)) return FALSE;

        $mask = $this->loadImageFromFile($maskImage);
        if (!$this->isGDResource($mask)) return FALSE;

        $imageX = $this->getWidth($mask);
        $imageY = $this->getHeight($mask);
        $resize = $this->resizeCaves($imageX, $imageY, $crop, $img);
        $result = $this->createImage($imageX, $imageY);
        if (is_resource($resize) && is_resource($result)) {
            for ($x = 0; $x < $imageX; $x++)
                for ($y = 0; $y < $imageY; $y++) {
                    $OColor = imagecolorsforindex($resize, imagecolorat($resize, $x, $y));
                    $MColor = imagecolorsforindex($mask, imagecolorat($mask, $x, $y));
                    if ($MColor['alpha'] > 0) imagesetpixel($result, $x, $y, imagecolorallocatealpha($result, $OColor['red'],
                        $OColor['green'], $OColor['blue'], 127 - $MColor['alpha']));
                }
        }
        imagedestroy($resize);
        imagedestroy($mask);
        return $result;
    }

    /**
     * * Get Loaded Image Width
     * @param NULL $img
     * @return bool|int
     */
    public function getWidth($img = NULL)
    {
        if (NULL === $img) $img = $this->imgResource;
        if (!$this->isGDResource($img)) return FALSE;
        return imagesx($img);
    }

    /**
     * Get Loaded Image Height
     * @param NULL $img
     * @return bool|int
     */
    public function getHeight($img = NULL)
    {
        if (NULL === $img) $img = $this->imgResource;
        if (!$this->isGDResource($img)) return FALSE;
        return imagesy($img);
    }

    /**
     * Image Process - Masking Color
     * @param $maskImage
     * @param $color
     * @param bool $MatchAlpha
     * @param bool $crop
     * @param NULL $img
     * @return bool|resource
     */
    public function maskColor($maskImage, $color, $MatchAlpha = TRUE, $crop = TRUE, $img = NULL)
    {
        if (NULL === $img) $img = $this->imgResource;
        if (!$this->isGDResource($img)) return FALSE;

        $mask = $this->loadImageFromFile($maskImage);
        if (!$this->isGDResource($mask)) return FALSE;

        $imageX = $this->imgWidth;
        $imageY = $this->imgHeight;
        $resize = $this->resizeCaves($imageX, $imageY, $crop, $img);
        $result = $this->createImage($imageX, $imageY);

        if (!is_resource($resize))
            return FALSE;

        if (!is_array($color)) $SColor = [
            'red' => (int)$color,
            'green' => (int)$color,
            'blue' => (int)$color,
            'alpha' => (int)$color];
        else  $SColor = [
            'red' => array_key_exists('red', $color) ? (int)$color['red'] : 0,
            'green' => array_key_exists('green', $color) ? (int)$color['green'] : 0,
            'blue' => array_key_exists('blue', $color) ? (int)$color['blue'] : 0,
            'alpha' => array_key_exists('alpha', $color) ? (int)$color['alpha'] : 0
        ];

        for ($x = 0; $x < $imageX; $x++)
            for ($y = 0; $y < $imageY; $y++) {
                $OColor = imagecolorsforindex($resize, imagecolorat($resize, $x, $y));
                $MColor = imagecolorsforindex($mask, imagecolorat($mask, $x, $y));
                if (($MatchAlpha === TRUE && $SColor === $MColor) || ($MatchAlpha === FALSE && $SColor['red'] === $MColor['red'] &&
                        $SColor['green'] === $MColor['green'] && $SColor['blue'] === $MColor['blue'])
                ) imagesetpixel($result,
                    $x, $y, imagecolorallocatealpha($result, $OColor['red'], $OColor['green'], $OColor['blue'], $MColor['alpha']));
            }
        imagedestroy($resize);
        imagedestroy($mask);
        return $result;
    }

    /**
     * Image Process - Rounded Corner
     * @param int $radius
     * @param NULL $img
     * @return bool|NULL
     */
    public function roundedCorner($radius = 10, $img = NULL)
    {
        if (NULL === $img) $img = $this->imgResource;
        if (!$this->isGDResource($img)) return FALSE;
        $radius = (int)$radius;
        if (empty($radius)) return FALSE;

        $imageX = $this->imgWidth;
        $imageY = $this->imgHeight;
        imagealphablending($img, FALSE);

        $imgCorner = imagecreatetruecolor($radius, $radius);
        $background = imagecolorallocatealpha($imgCorner, 255, 255, 255, 127);
        imagefill($imgCorner, 0, 0, $background);

        $this->smoothArc($radius, $radius, $radius * 2, $radius * 2, 0, 0, 360, $imgCorner);

        $this->cornerFill($imgCorner, $img, $radius, 0, 0, $background);

        $imgCorner = imagerotate($imgCorner, 90, 0);
        $this->cornerFill($imgCorner, $img, $radius, 0, $imageY - $radius, $background);

        $imgCorner = imagerotate($imgCorner, 90, 0);
        $this->cornerFill($imgCorner, $img, $radius, $imageX - $radius, $imageY - $radius, $background);

        $imgCorner = imagerotate($imgCorner, 90, 0);
        $this->cornerFill($imgCorner, $img, $radius, $imageX - $radius, 0, $background);

        imagealphablending($img, TRUE);
        return $img;
    }

    /**
     * Image Process - Smooth Arc
     * @param $cX
     * @param $cY
     * @param $width
     * @param $height
     * @param int $color
     * @param int $start
     * @param int $stop
     * @param NULL $img
     * @return bool
     */
    public function smoothArc($cX, $cY, $width, $height, $color = 0, $start = 0, $stop = 360, $img = NULL)
    {
        if (NULL === $img) $img = $this->imgResource;
        if (!$this->isGDResource($img)) return FALSE;
        $start = deg2rad($start);
        $stop = deg2rad($stop);
        $color = (int)$color;
        while ($start < 0) $start += 2 * M_PI;
        while ($stop < 0) $stop += 2 * M_PI;
        while ($start > 2 * M_PI) $start -= 2 * M_PI;
        while ($stop > 2 * M_PI) $stop -= 2 * M_PI;
        if ($start > $stop) {
            $this->smoothArc($cX, $cY, $width, $height, $color, rad2deg($start), 2 * M_PI, $img);
            $this->smoothArc($cX, $cY, $width, $height, $color, 0, rad2deg($stop), $img);
            return FALSE;
        }
        $a = 1.0 * round($width / 2);
        $b = 1.0 * round($height / 2);
        $cX = 1.0 * round($cX);
        $cY = 1.0 * round($cY);
        $aaAngle = atan(($b * $b) / ($a * $a) * tan(0.25 * M_PI));
        $aaAngleX = $a * cos($aaAngle);
        $aaAngleY = $b * sin($aaAngle);
        $a -= 0.5;
        $b -= 0.5;
        for ($i = 0; $i < 4; $i++)
            if ($start < ($i + 1) * M_PI / 2)
                if ($start > $i * M_PI / 2) {
                    if ($stop > ($i + 1) * M_PI / 2) $this->smoothArcDrawSegment($img, $cX, $cY, $a, $b, $aaAngleX, $aaAngleY,
                        $color, $start, ($i + 1) * M_PI / 2, $i);
                    else {
                        $this->smoothArcDrawSegment($img, $cX, $cY, $a, $b, $aaAngleX, $aaAngleY, $color, $start, $stop, $i);
                        break;
                    }
                } else {
                    if ($stop > ($i + 1) * M_PI / 2) $this->smoothArcDrawSegment($img, $cX, $cY, $a, $b, $aaAngleX, $aaAngleY,
                        $color, $i * M_PI / 2, ($i + 1) * M_PI / 2, $i);
                    else {
                        $this->smoothArcDrawSegment($img, $cX, $cY, $a, $b, $aaAngleX, $aaAngleY, $color, $i * M_PI / 2, $stop,
                            $i);
                        break;
                    }
                }
        return TRUE;
    }

    /**
     * Smooth Arc Draw Segment
     * @param $img
     * @param $cx
     * @param $cy
     * @param $a
     * @param $b
     * @param $aaAngleX
     * @param $aaAngleY
     * @param $fillColor
     * @param $start
     * @param $stop
     * @param $seg
     */
    private function smoothArcDrawSegment($img, $cx, $cy, $a, $b, $aaAngleX, $aaAngleY, $fillColor, $start,
                                          $stop, $seg)
    {
        $color = array_values(imagecolorsforindex($img, $fillColor));
        $xStart = abs($a * cos($start));
        $yStart = abs($b * sin($start));
        $xStop = abs($a * cos($stop));
        $yStop = abs($b * sin($stop));
        $dxStart = 0;
        $dyStart = 0;
        $dxStop = 0;
        $dyStop = 0;
        if ($xStart != 0) $dyStart = $yStart / $xStart;
        if ($xStop != 0) $dyStop = $yStop / $xStop;
        if ($yStart != 0) $dxStart = $xStart / $yStart;
        if ($yStop != 0) $dxStop = $xStop / $yStop;
        if (abs($xStart) >= abs($yStart)) $aaStartX = TRUE;
        else  $aaStartX = FALSE;
        if ($xStop >= $yStop) $aaStopX = TRUE;
        else  $aaStopX = FALSE;
        for ($x = 0; $x < $a; $x += 1) {
            $_y1 = $dyStop * $x;
            $_y2 = $dyStart * $x;
            if ($xStart > $xStop) {
                $error1 = $_y1 - (int)($_y1);
                $error2 = 1 - $_y2 + (int)$_y2;
                $_y1 = $_y1 - $error1;
                $_y2 = $_y2 + $error2;
            } else {
                $error1 = 1 - $_y1 + (int)$_y1;
                $error2 = $_y2 - (int)($_y2);
                $_y1 = $_y1 + $error1;
                $_y2 = $_y2 - $error2;
            }
            if ($seg == 0 || $seg == 2) {
                $i = $seg;
                if (!($start > $i * M_PI / 2 && $x > $xStart)) {
                    if ($i == 0) {
                        $xp = +1;
                        $yp = -1;
                        $xa = +1;
                        $ya = 0;
                    } else {
                        $xp = -1;
                        $yp = +1;
                        $xa = 0;
                        $ya = +1;
                    }
                    if ($stop < ($i + 1) * (M_PI / 2) && $x <= $xStop) {
                        $diffColor1 = imagecolorexactalpha($img, $color[0], $color[1], $color[2], 127 - (127 - $color[3]) *
                            $error1);
                        $y1 = $_y1;
                        if ($aaStopX) imagesetpixel($img, $cx + $xp * ($x) + $xa, $cy + $yp * ($y1 + 1) + $ya, $diffColor1);
                    } else {
                        $y = $b * sqrt(1 - ($x * $x) / ($a * $a));
                        $error = $y - (int)($y);
                        $y = (int)($y);
                        $diffColor = imagecolorexactalpha($img, $color[0], $color[1], $color[2], 127 - (127 - $color[3]) * $error);
                        $y1 = $y;
                        if ($x < $aaAngleX) imagesetpixel($img, $cx + $xp * $x + $xa, $cy + $yp * ($y1 + 1) + $ya, $diffColor);
                    }
                    if ($start > $i * M_PI / 2 && $x <= $xStart) {
                        $diffColor2 = imagecolorexactalpha($img, $color[0], $color[1], $color[2], 127 - (127 - $color[3]) *
                            $error2);
                        $y2 = $_y2;
                        if ($aaStartX) imagesetpixel($img, $cx + $xp * $x + $xa, $cy + $yp * ($y2 - 1) + $ya, $diffColor2);
                    } else  $y2 = 0;
                    if ($y2 <= $y1) imageline($img, $cx + $xp * $x + $xa, $cy + $yp * $y1 + $ya, $cx + $xp * $x + $xa, $cy +
                        $yp * $y2 + $ya, $fillColor);
                }
            }

            if ($seg == 1 || $seg == 3) {
                $i = $seg;
                if (!($stop < ($i + 1) * M_PI / 2 && $x > $xStop)) {
                    if ($i == 1) {
                        $xp = -1;
                        $yp = -1;
                        $xa = 0;
                        $ya = 0;
                    } else {
                        $xp = +1;
                        $yp = +1;
                        $xa = 1;
                        $ya = 1;
                    }
                    if ($start > $i * M_PI / 2 && $x < $xStart) {
                        $diffColor2 = imagecolorexactalpha($img, $color[0], $color[1], $color[2], 127 - (127 - $color[3]) *
                            $error2);
                        $y1 = $_y2;
                        if ($aaStartX) imagesetpixel($img, $cx + $xp * $x + $xa, $cy + $yp * ($y1 + 1) + $ya, $diffColor2);

                    } else {
                        $y = $b * sqrt(1 - ($x * $x) / ($a * $a));
                        $error = $y - (int)($y);
                        $y = (int)$y;
                        $diffColor = imagecolorexactalpha($img, $color[0], $color[1], $color[2], 127 - (127 - $color[3]) * $error);
                        $y1 = $y;
                        if ($x < $aaAngleX) imagesetpixel($img, $cx + $xp * $x + $xa, $cy + $yp * ($y1 + 1) + $ya, $diffColor);
                    }
                    if ($stop < ($i + 1) * M_PI / 2 && $x <= $xStop) {
                        $diffColor1 = imagecolorexactalpha($img, $color[0], $color[1], $color[2], 127 - (127 - $color[3]) *
                            $error1);
                        $y2 = $_y1;
                        if ($aaStopX) imagesetpixel($img, $cx + $xp * $x + $xa, $cy + $yp * ($y2 - 1) + $ya, $diffColor1);
                    } else  $y2 = 0;
                    if ($y2 <= $y1) imageline($img, $cx + $xp * $x + $xa, $cy + $yp * $y1 + $ya, $cx + $xp * $x + $xa, $cy +
                        $yp * $y2 + $ya, $fillColor);
                }
            }
        }
        for ($y = 0; $y < $b; $y += 1) {
            $_x1 = $dxStop * $y;
            $_x2 = $dxStart * $y;
            if ($yStart > $yStop) {
                $error1 = $_x1 - (int)($_x1);
                $error2 = 1 - $_x2 + (int)$_x2;
                $_x1 = $_x1 - $error1;
                $_x2 = $_x2 + $error2;
            } else {
                $error1 = 1 - $_x1 + (int)$_x1;
                $error2 = $_x2 - (int)($_x2);
                $_x1 = $_x1 + $error1;
                $_x2 = $_x2 - $error2;
            }
            if ($seg == 0 || $seg == 2) {
                $i = $seg;
                if (!($start > $i * M_PI / 2 && $y > $yStop)) {
                    if ($i == 0) {
                        $xp = +1;
                        $yp = -1;
                        $xa = 1;
                        $ya = 0;
                    } else {
                        $xp = -1;
                        $yp = +1;
                        $xa = 0;
                        $ya = 1;
                    }
                    if ($stop < ($i + 1) * (M_PI / 2) && $y <= $yStop) {
                        $diffColor1 = imagecolorexactalpha($img, $color[0], $color[1], $color[2], 127 - (127 - $color[3]) *
                            $error1);
                        $x1 = $_x1;
                        if (!$aaStopX) imagesetpixel($img, $cx + $xp * ($x1 - 1) + $xa, $cy + $yp * ($y) + $ya, $diffColor1);
                    }
                    if ($start > $i * M_PI / 2 && $y < $yStart) {
                        $diffColor2 = imagecolorexactalpha($img, $color[0], $color[1], $color[2], 127 - (127 - $color[3]) *
                            $error2);
                        $x2 = $_x2;
                        if (!$aaStartX) imagesetpixel($img, $cx + $xp * ($x2 + 1) + $xa, $cy + $yp * ($y) + $ya, $diffColor2);
                    } else {
                        $x = $a * sqrt(1 - ($y * $y) / ($b * $b));
                        $error = $x - (int)($x);
                        $x = (int)($x);
                        $diffColor = imagecolorexactalpha($img, $color[0], $color[1], $color[2], 127 - (127 - $color[3]) * $error);
                        $x1 = $x;
                        if ($y < $aaAngleY && $y <= $yStop) imagesetpixel($img, $cx + $xp * ($x1 + 1) + $xa, $cy + $yp * $y +
                            $ya, $diffColor);
                    }
                }
            }
            if ($seg == 1 || $seg == 3) {
                $i = $seg;
                if (!($stop < ($i + 1) * M_PI / 2 && $y > $yStart)) {
                    if ($i == 1) {
                        $xp = -1;
                        $yp = -1;
                        $xa = 0;
                        $ya = 0;
                    } else {
                        $xp = +1;
                        $yp = +1;
                        $xa = 1;
                        $ya = 1;
                    }
                    if ($start > $i * M_PI / 2 && $y < $yStart) {
                        $diffColor2 = imagecolorexactalpha($img, $color[0], $color[1], $color[2], 127 - (127 - $color[3]) *
                            $error2);
                        $x1 = $_x2;
                        if (!$aaStartX) imagesetpixel($img, $cx + $xp * ($x1 - 1) + $xa, $cy + $yp * $y + $ya, $diffColor2);
                    }
                    if ($stop < ($i + 1) * M_PI / 2 && $y <= $yStop) {
                        $diffColor1 = imagecolorexactalpha($img, $color[0], $color[1], $color[2], 127 - (127 - $color[3]) *
                            $error1);
                        $x2 = $_x1;
                        if (!$aaStopX) imagesetpixel($img, $cx + $xp * ($x2 + 1) + $xa, $cy + $yp * $y + $ya, $diffColor1);
                    } else {
                        $x = $a * sqrt(1 - ($y * $y) / ($b * $b));
                        $error = $x - (int)($x);
                        $x = (int)($x);
                        $diffColor = imagecolorexactalpha($img, $color[0], $color[1], $color[2], 127 - (127 - $color[3]) * $error);
                        $x1 = $x;
                        if ($y < $aaAngleY && $y < $yStart) imagesetpixel($img, $cx + $xp * ($x1 + 1) + $xa, $cy + $yp * $y +
                            $ya, $diffColor);
                    }
                }
            }
        }
    }

    /**
     * Image Process - Rounded Each Side Corner
     * @param $imgCorner
     * @param $img
     * @param $radius
     * @param $startX
     * @param $startY
     * @param $background
     */
    private function cornerFill($imgCorner, $img, $radius, $startX, $startY, $background)
    {
        for ($y = $startY; $y < $startY + $radius; $y++)
            for ($x = $startX; $x < $startX + $radius; $x++) {
                $color = imagecolorsforindex($img, imagecolorat($imgCorner, $x - $startX, $y - $startY));
                if ($color['red'] > 230 && $color['green'] > 0 && $color['blue'] > 0) imagesetpixel($img, $x, $y,
                    $background);
            }
    }

    ##########################################################################################
    # Section: Special FX Effect
    ##########################################################################################

    /**
     * Image Effect - Gamma Correction
     * @param int $gamma
     * @param NULL $img
     * @return bool|NULL
     */
    public function fxGammaCorrection($gamma = 100, &$img = NULL)
    {
        if (NULL === $img) $img = $this->imgResource;
        if (!$this->isGDResource($img)) return FALSE;
        $gamma = (double)$gamma;
        if (!empty($gamma))
            imagegammacorrect($img, 100, $gamma);
        return $img;
    }

    /**
     * Image Effect - Blur
     * @param int $level
     * @param NULL $img
     * @return bool|NULL
     */
    public function fxBlur($level = 0, &$img = NULL)
    {
        if (NULL === $img) $img = $this->imgResource;
        if (!$this->isGDResource($img)) return FALSE;
        $level = (int)$level;
        if (!function_exists('imagefilter'))
            for ($i = 0; $i < $level; $i++) imagefilter($img, IMG_FILTER_SELECTIVE_BLUR);
        else  return FALSE;
        return $img;
    }

    /**
     * Image Effect - Contrast
     * @param int $level
     * @param NULL $img
     * @return bool|NULL
     */
    public function fxContrast($level = 0, &$img = NULL)
    {
        if (NULL === $img) $img = $this->imgResource;
        if (!$this->isGDResource($img)) return FALSE;
        if (!function_exists('imagefilter') || !imagefilter($img, IMG_FILTER_CONTRAST, $level)) return FALSE;
        return $img;
    }

    /**
     * Image Effect - Brightness
     * @param int $level
     * @param NULL $img
     * @return bool|NULL
     */
    public function fxBrightness($level = 0, &$img = NULL)
    {
        if (NULL === $img) $img = $this->imgResource;
        if (!$this->isGDResource($img)) return FALSE;
        if (!function_exists('imagefilter') || !imagefilter($img, IMG_FILTER_BRIGHTNESS, $level)) return FALSE;
        return $img;
    }

    /**
     * Image Effect - Smooth
     * @param int $level
     * @param NULL $img
     * @return bool|NULL
     */
    public function fxSmooth($level = 0, &$img = NULL)
    {
        if (NULL === $img) $img = $this->imgResource;
        if (!$this->isGDResource($img)) return FALSE;
        if (!function_exists('imagefilter') || !imagefilter($img, IMG_FILTER_SMOOTH, $level)) return FALSE;
        return $img;
    }

    /**
     * Image Effect - Sketchy
     * @param NULL $img
     * @return bool|NULL
     */
    public function fxSketchy(&$img = NULL)
    {
        if (NULL === $img) $img = $this->imgResource;
        if (!$this->isGDResource($img)) return FALSE;
        if (!function_exists('imagefilter') || !imagefilter($img, IMG_FILTER_MEAN_REMOVAL)) return FALSE;
        return $img;
    }

    /**
     * Image Effect - Emboss
     * @param NULL $img
     * @return bool|NULL
     */
    public function fxEmboss(&$img = NULL)
    {
        if (NULL === $img) $img = $this->imgResource;
        if (!$this->isGDResource($img)) return FALSE;
        if (!function_exists('imagefilter') || !imagefilter($img, IMG_FILTER_EMBOSS)) return FALSE;
        return $img;
    }

    /**
     * Image Effect - Edge
     * @param NULL $img
     * @return bool|NULL
     */
    public function fxEdge(&$img = NULL)
    {
        if (NULL === $img) $img = $this->imgResource;
        if (!$this->isGDResource($img)) return FALSE;
        if (!function_exists('imagefilter') || !imagefilter($img, IMG_FILTER_EDGEDETECT)) return FALSE;
        return $img;
    }

    /**
     * Image Effect - Invert
     * @param NULL $img
     * @return bool|NULL
     */
    public function fxInvert(&$img = NULL)
    {
        if (NULL === $img) $img = $this->imgResource;
        if (!$this->isGDResource($img)) return FALSE;
        if (!function_exists('imagefilter') || !imagefilter($img, IMG_FILTER_NEGATE)) return FALSE;
        return $img;
    }

    /**
     * Image Effect - Interlace
     * @param int $color
     * @param NULL $img
     * @return bool|NULL
     */
    public function fxInterlace($color = 0, &$img = NULL)
    {
        if (NULL === $img) $img = $this->imgResource;
        if (!$this->isGDResource($img)) return FALSE;
        $color = (int)$color;
        $imageX = $this->imgWidth;
        $imageY = $this->imgHeight;
        for ($y = 1; $y < $imageY; $y += 2) imageline($img, 0, $y, $imageX, $y, $color);
        return $img;
    }

    /**
     * Image Effect - Greyscale
     * @param NULL $img
     * @return bool|NULL
     */
    public function fxGreyscale(&$img = NULL)
    {
        if (NULL === $img) $img = $this->imgResource;
        if (!$this->isGDResource($img)) return FALSE;
        if (!function_exists('imagefilter') || !imagefilter($img, IMG_FILTER_GRAYSCALE)) {
            $imageX = $this->imgWidth;
            $imageY = $this->imgHeight;
            for ($y = 0; $y < $imageY; ++$y)
                for ($x = 0; $x < $imageX; ++$x) {
                    $color = imagecolorsforindex($img, imagecolorat($img, $x, $y));
                    $Grey = (int)(($color['red'] + $color['green'] + $color['blue']) / 3);
                    imagesetpixel($img, $x, $y, imagecolorallocatealpha($img, $Grey, $Grey, $Grey, $color['alpha']));
                }
        }
        return $img;
    }

    /**
     * Color Filter
     * @param bool $red
     * @param bool $green
     * @param bool $blue
     * @param int $compare
     * @param NULL $img
     * @return bool|NULL
     */
    public function fxColorFilter($red = FALSE, $green = FALSE, $blue = FALSE, $compare = 127, &$img = NULL)
    {
        if (NULL === $img) $img = $this->imgResource;
        if (!$this->isGDResource($img)) return FALSE;
        $imageX = $this->imgWidth;
        $imageY = $this->imgHeight;
        $type = ($red === TRUE ? 'Y' : 'N') . ($green === TRUE ? 'Y' : 'N') . ($blue === TRUE ? 'Y' : 'N');
        for ($y = 0; $y < $imageY; ++$y)
            for ($x = 0; $x < $imageX; ++$x) {
                $color = imagecolorsforindex($img, imagecolorat($img, $x, $y));
                $Greyscale = TRUE;
                switch ($type) {

                    case 'YNN':
                        if ($color['red'] - $color['green'] > $compare && $color['red'] - $color['blue'] > $compare) $Greyscale = FALSE;
                        break;

                    case 'NYN':
                        if ($color['green'] - $color['red'] > $compare && $color['green'] - $color['blue'] > $compare) $Greyscale = FALSE;
                        break;

                    case 'NNY':
                        if ($color['blue'] - $color['red'] > $compare && $color['blue'] - $color['green'] > $compare) $Greyscale = FALSE;
                        break;

                    case 'YYN':
                        if ($color['red'] - $color['blue'] > $compare && $color['green'] - $color['blue'] > $compare) $Greyscale = FALSE;
                        break;

                    case 'YNY':
                        if ($color['red'] - $color['green'] > $compare && $color['blue'] - $color['green'] > $compare) $Greyscale = FALSE;
                        break;

                    case 'NYY':
                        if ($color['blue'] - $color['red'] > $compare && $color['green'] - $color['red'] > $compare) $Greyscale = FALSE;
                        break;

                    default:
                        $Greyscale = FALSE;
                }
                if ($Greyscale === TRUE) {
                    $Grey = (int)(($color['red'] + $color['green'] + $color['blue']) / 3);
                    imagesetpixel($img, $x, $y, imagecolorallocatealpha($img, $Grey, $Grey, $Grey, $color['alpha']));
                }
            }
        return $img;
    }

    /**
     * Image Effect - Colorize
     * @param int $red
     * @param int $green
     * @param int $blue
     * @param int $Alpha
     * @param NULL $img
     * @return bool|NULL
     */
    public function fxColorize($red = 0, $green = 0, $blue = 0, $Alpha = 0, &$img = NULL)
    {
        if (NULL === $img) $img = $this->imgResource;
        if (!$this->isGDResource($img)) return FALSE;
        $red = (int)$red;
        $green = (int)$green;
        $blue = (int)$blue;
        if (empty($red) && empty($green) && empty($blue) && empty($blue)) return FALSE;
        if (!function_exists('imagefilter') || !imagefilter($img, IMG_FILTER_COLORIZE, $red, $green, $blue,
                $Alpha)
        ) {
            $imageX = $this->imgWidth;
            $imageY = $this->imgHeight;
            for ($y = 0; $y < $imageY; ++$y)
                for ($x = 0; $x < $imageX; ++$x) {
                    $color = imagecolorsforindex($img, imagecolorat($img, $x, $y));
                    $iRed = $color['red'] + $red;
                    $iGreen = $color['green'] + $green;
                    $iBlue = $color['blue'] + $blue;
                    $iAlpha = $color['alpha'] + $Alpha;
                    if ($iRed > 255) $iRed = 255;
                    if ($iGreen > 255) $iGreen = 255;
                    if ($iBlue > 255) $iBlue = 255;
                    if ($iAlpha > 255) $iAlpha = 255;
                    if ($iRed < 0) $iRed = 0;
                    if ($iGreen < 0) $iGreen = 0;
                    if ($iBlue < 0) $iBlue = 0;
                    if ($iAlpha < 0) $iBlue = 0;
                    imagesetpixel($img, $x, $y, imagecolorallocatealpha($img, $iRed, $iGreen, $iBlue, $iAlpha));
                }
        }
        return $img;
    }

    /**
     * Image Effect - Noise
     * @param int $noise
     * @param int $level
     * @param NULL $img
     * @return bool|NULL
     */
    public function fxNoise($noise = 50, $level = 20, &$img = NULL)
    {
        if (NULL === $img) $img = $this->imgResource;
        if (!$this->isGDResource($img)) return FALSE;
        $level = (int)$level;
        $noise = (int)$noise;
        if (empty($level) && empty($noise)) return FALSE;
        $imageX = $this->imgWidth;
        $imageY = $this->imgHeight;
        for ($x = 0; $x < $imageX; $x++)
            for ($y = 0; $y < $imageY; $y++)
                if (rand(0, 100) <= $noise) {
                    $color = imagecolorsforindex($img, imagecolorat($img, $x, $y));
                    $Modifier = rand($level * -1, $level);
                    $red = $color['red'] + $Modifier;
                    $green = $color['green'] + $Modifier;
                    $blue = $color['blue'] + $Modifier;
                    if ($red > 255) $red = 255;
                    if ($green > 255) $green = 255;
                    if ($blue > 255) $blue = 255;
                    if ($red < 0) $red = 0;
                    if ($green < 0) $green = 0;
                    if ($blue < 0) $blue = 0;
                    imagesetpixel($img, $x, $y, imagecolorallocatealpha($img, $red, $green, $blue, $color['alpha']));
                }
        return $img;
    }

    /**
     * Image Effect - Scatter
     * @param int $level
     * @param NULL $img
     * @return bool|NULL
     */
    public function fxScatter($level = 4, &$img = NULL)
    {
        if (NULL === $img) $img = $this->imgResource;
        if (!$this->isGDResource($img)) return FALSE;
        $level = (int)$level;
        if (empty($level)) return FALSE;
        $imageX = $this->imgWidth;
        $imageY = $this->imgHeight;
        for ($y = 0; $y < $imageY; $y++)
            for ($x = 0; $x < $imageX; $x++) {
                $DistX = rand(-($level), $level);
                $DistY = rand(-($level), $level);
                if ($x + $DistX >= $imageX) continue;
                if ($x + $DistX < 0) continue;
                if ($y + $DistY >= $imageY) continue;
                if ($y + $DistY < 0) continue;
                $Oldcol = imagecolorat($img, $x, $y);
                $newImgCol = imagecolorat($img, $x + $DistX, $y + $DistY);
                imagesetpixel($img, $x, $y, $newImgCol);
                imagesetpixel($img, $x + $DistX, $y + $DistY, $Oldcol);
            }
        return $img;
    }

    /**
     * Image Effect - Pixelate
     * @param int $level
     * @param NULL $img
     * @return bool|NULL
     */
    public function fxPixelate($level = 12, &$img = NULL)
    {
        if (NULL === $img) $img = $this->imgResource;
        if (!$this->isGDResource($img)) return FALSE;
        $level = (int)$level;
        if (empty($level)) return FALSE;
        if (!function_exists('imagefilter') || !imagefilter($img, IMG_FILTER_PIXELATE, $level, TRUE)) {
            $imageX = $this->imgWidth;
            $imageY = $this->imgHeight;
            $pixelSize = (int)$level;

            for ($x = 0; $x < $imageX; $x += $pixelSize)
                for ($y = 0; $y < $imageY; $y += $pixelSize) {
                    $tCol = imagecolorat($img, $x, $y);
                    $nCol = ['r' => 0, 'g' => 0, 'b' => 0, 'a' => 0];
                    $cols = [];
                    for ($l = $y; $l < $y + $pixelSize; ++$l)
                        for ($k = $x; $k < $x + $pixelSize; ++$k) {
                            if ($k < 0) {
                                $cols[] = $tCol;
                                continue;
                            }
                            if ($k >= $imageX) {
                                $cols[] = $tCol;
                                continue;
                            }
                            if ($l < 0) {
                                $cols[] = $tCol;
                                continue;
                            }
                            if ($l >= $imageY) {
                                $cols[] = $tCol;
                                continue;
                            }
                            $cols[] = imagecolorat($img, $k, $l);
                        }
                    foreach ($cols as $col) {
                        $color = imagecolorsforindex($img, $col);
                        $nCol['r'] += $color['red'];
                        $nCol['g'] += $color['green'];
                        $nCol['b'] += $color['blue'];
                        $nCol['a'] += $color['alpha'];
                    }
                    $pixelCount = count($cols);
                    $nCol['r'] /= $pixelCount;
                    $nCol['g'] /= $pixelCount;
                    $nCol['b'] /= $pixelCount;
                    $nCol['a'] /= $pixelCount;
                    $nCol['Result'] = imagecolorallocatealpha($img, $nCol['r'], $nCol['g'], $nCol['b'], $nCol['a']);
                    imagefilledrectangle($img, $x, $y, $x + $pixelSize - 1, $y + $pixelSize - 1, $nCol['Result']);
                }
        }
        return $img;
    }

    /**
     * Image Effect - Gaussian Blur
     * @param int $level
     * @param NULL $img
     * @return bool|resource
     */
    public function fxGaussianBlur($level = 1, &$img = NULL)
    {
        if (NULL === $img) $img = $this->imgResource;
        if (!$this->isGDResource($img)) return FALSE;
        $level = (int)$level;
        if (empty($level)) return $img;
        $Gaussian = [
            [1.0, 2.0, 1.0],
            [2.0, 4.0, 2.0],
            [1.0, 2.0, 1.0]
        ];
        for ($i = 0; $i < $level; $i++) imageconvolution($img, $Gaussian, 16, 0);
        return $img;
    }

    /**
     * Image Effect - Fish Eye
     * @param NULL $img
     * @return bool|resource
     */
    public function fxFishEye(&$img = NULL)
    {
        if (NULL === $img) $img = $this->imgResource;
        if (!$this->isGDResource($img)) return FALSE;
        $imageX = $this->imgWidth;
        $imageY = $this->imgHeight;
        $CImageX = $imageX / 2; //Source middle
        $CImageY = $imageY / 2;
        if ($imageX > $imageY) $OW = 2 * $imageY / pi(); //Width for the destination image
        else  $OW = 2 * $imageX / pi();
        $newImg = imagecreatetruecolor($OW + 1, $OW + 1);
        imagefill($newImg, 0, 0, imagecolorallocatealpha($newImg, 255, 255, 255, 0));
        $OM = $OW / 2;
        for ($y = 0; $y <= $OW; ++$y)
            for ($x = 0; $x <= $OW; ++$x) {
                $OTX = $x - $OM;
                $OTY = $y - $OM; //Y in relation to the middle
                $OH = hypot($OTX, $OTY); //distance
                $Arc = (2 * $OM * asin($OH / $OM)) / (2);
                $Factor = $Arc / $OH;
                if ($OH <= $OM) imagesetpixel($newImg, $x, $y, imagecolorat($img, round($OTX * $Factor + $CImageX),
                    round($OTY * $Factor + $CImageY)));
            }
        imagedestroy($img);
        $this->imgResource = $newImg;
        $this->initLoad();
        return $this->imgResource;
    }

    /**
     * Image Effect - Dream
     * @param int $percent
     * @param int $type
     * @param NULL $img
     * @return bool|NULL
     */
    public function fxDream($percent = 30, $type = 0, $img = NULL)
    {
        if (NULL === $img) $img = $this->imgResource;
        if (!$this->isGDResource($img)) return FALSE;
        $imageX = $this->imgWidth;
        $imageY = $this->imgHeight;
        $effect = $this->createImage(255, 255);
        $type = is_int($type) ? $type : rand(0, 5);
        for ($x = 0; $x <= 255; $x++)
            for ($y = 0; $y <= 255; $y++) {
                switch ($type) {
                    case 1:
                        $col = imagecolorallocate($effect, 255, $y, $x);
                        break;

                    case 2:
                        $col = imagecolorallocate($effect, $y, 255, $x);
                        break;

                    case 3:
                        $col = imagecolorallocate($effect, $x, 255, $y);
                        break;

                    case 4:
                        $col = imagecolorallocate($effect, $x, $y, 255);
                        break;

                    case 5:
                        $col = imagecolorallocate($effect, $y, $x, 255);
                        break;

                    default:
                        $col = imagecolorallocate($effect, 255, $x, $y);
                }
                imagesetpixel($effect, $x, $y, $col);
            }
        if (!$effect || !is_resource($effect))
            return FALSE;
        imagecopymerge($img, $effect, 0, 0, 0, 0, $imageX, $imageY, $percent);
        imagedestroy($effect);
        $this->imgResource = $img;
        $this->initLoad();
        return $this->imgResource;
    }

    ##########################################################################################
    # Section: Special FX Font Effect
    ##########################################################################################

    /**
     * Build and return TRUE tye font box information
     * @param $content
     * @param $font
     * @param int $size
     * @param int $x
     * @param int $y
     * @param int $color
     * @param int $angle
     * @return array
     */
    public function ttfBox($content, $font, $size = 10, $x = 0, $y = 0, $color = 0, $angle = 0)
    {
        $size = (int)$size;
        $x = (int)$x;
        $y = (int)$y;
        $angle = (double)$angle;
        $color = (int)$color;
        $tBox = imagettfbbox($size, $angle, $font, $content);
        $ttfBox = [
            'X' => $x,
            'Y' => $y + abs($tBox[5]) - (abs($tBox[1]) / 2),
            'Width' => abs($tBox[4] - $tBox[0]),
            'Height' => abs($tBox[5]),
            'Font' => $font,
            'Size' => $size,
            'Color' => $color,
            'Angle' => $angle,
            'Content' => $content
        ];
        return $ttfBox;
    }

    /**
     * Draw TRUE type font text
     * @param $ttfBox
     * @param NULL $img
     * @return bool
     */
    public function ttfText($ttfBox, $img = NULL)
    {
        if (NULL === $img) $img = $this->imgResource;
        if (!$this->isGDResource($img)) return FALSE;
        if (!$this->isTTFBox($ttfBox)) return FALSE;
        $this->ttfBoxText($ttfBox['Size'], $ttfBox['Angle'], $ttfBox['X'], $ttfBox['Y'], $ttfBox['Color'], $ttfBox['Font'],
            $ttfBox['Content']);
        return TRUE;
    }

    /**
     * Check the parameter is generate by ttfBox or not.
     * @param $ttfBox
     * @return bool
     */
    private function isTTFBox($ttfBox)
    {
        if (array_key_exists('X', $ttfBox) && array_key_exists('Y', $ttfBox) && array_key_exists('Width', $ttfBox) &&
            array_key_exists('Height', $ttfBox) && array_key_exists('Font', $ttfBox) && array_key_exists('Size',
                $ttfBox) && array_key_exists('Color', $ttfBox) && array_key_exists('Angle', $ttfBox) &&
            array_key_exists('Content', $ttfBox) && !empty($ttfBox['Content']) && !empty($ttfBox['Font']) && !
            empty($ttfBox['Size'])
        ) return TRUE;
        return FALSE;
    }

    /**
     * Draw a TRUE type font box text, process part
     * @param $size
     * @param $angle
     * @param $x
     * @param $y
     * @param $color
     * @param $font
     * @param $text
     * @param int $blur
     * @param NULL $img
     * @return array|bool
     */
    private function ttfBoxText($size, $angle, $x, $y, $color, $font, $text, $blur = 0, $img = NULL)
    {
        if (NULL === $img) $img = $this->imgResource;
        if (!$this->isGDResource($img)) return FALSE;
        $angle = (double)$angle;
        $x = (int)$x;
        $y = (int)$y;
        $color = (int)$color;
        $blur = (int)$blur;
        if ($blur > 0) {
            $TxtImg = imagecreatetruecolor(imagesx($img), imagesy($img));
            imagefill($TxtImg, 0, 0, imagecolorallocate($TxtImg, 0x00, 0x00, 0x00));
            imagettftext($TxtImg, $size, $angle, $x, $y, imagecolorallocate($TxtImg, 0xFF, 0xFF, 0xFF), $font, $text);
            for ($i = 1; $i <= $blur; $i++) imagefilter($TxtImg, IMG_FILTER_GAUSSIAN_BLUR);
            for ($xOff = 0; $xOff < imagesx($TxtImg); $xOff++)
                for ($yOff = 0; $yOff < imagesy($TxtImg); $yOff++) {
                    $Visible = (imagecolorat($TxtImg, $xOff, $yOff) & 0xFF) / 255;
                    if ($Visible > 0) imagesetpixel($img, $xOff, $yOff, imagecolorallocatealpha($img, ($color >> 16) &
                        0xFF, ($color >> 8) & 0xFF, $color & 0xFF, (1 - $Visible) * 127));
                }
            imagedestroy($TxtImg);
        } else
            imagettftext($img, $size, $angle, $x, $y, $color, $font, $text);
        return TRUE;
    }

    /**
     * Draw TRUE type font text with grow
     * @param $ttfBox
     * @param int $grow
     * @param int $color
     * @param NULL $img
     * @return bool
     */
    public function ttfTextGrow($ttfBox, $grow = 10, $color = 0, $img = NULL)
    {
        if (NULL === $img) $img = $this->imgResource;
        if (!$this->isGDResource($img)) return FALSE;
        if (!$this->isTTFBox($ttfBox)) return FALSE;
        $grow = (int)$grow;
        $color = (int)$color;
        imagealphablending($img, TRUE);
        $this->ttfBoxText($ttfBox['Size'], $ttfBox['Angle'], $ttfBox['X'], $ttfBox['Y'], $color, $ttfBox['Font'],
            $ttfBox['Content'], $grow, $img);
        $this->ttfBoxText($ttfBox['Size'], $ttfBox['Angle'], $ttfBox['X'], $ttfBox['Y'], $ttfBox['Color'], $ttfBox['Font'],
            $ttfBox['Content'], 0, $img);
        return TRUE;
    }

    /**
     * Draw TRUE type font text with shadow
     * @param $ttfBox
     * @param int $shadow
     * @param int $color
     * @param string $direction
     * @param NULL $img
     * @return bool
     */
    public function ttfTextShadow($ttfBox, $shadow = 10, $color = 0, $direction = 'rb', $img = NULL)
    {
        if (NULL === $img) $img = $this->imgResource;
        if (!$this->isGDResource($img)) return FALSE;
        if (!$this->isTTFBox($ttfBox)) return FALSE;
        $color = (int)$color;
        $shadowX = (int)$ttfBox['X'];
        $shadowY = (int)$ttfBox['Y'];
        switch ($direction) {

            case 'lt':
                $shadowX -= ($shadow / 2);
                $shadowY -= ($shadow / 2);
                break;

            case 'ct':
                $shadowY -= ($shadow / 2);
                break;

            case 'rt':
                $shadowX += ($shadow / 2);
                $shadowY -= ($shadow / 2);
                break;

            case 'lc':
                $shadowX -= ($shadow / 2);
                break;

            case 'rc':
                $shadowX += ($shadow / 2);
                break;

            case 'lb':
                $shadowX -= ($shadow / 2);
                $shadowY += ($shadow / 2);
                break;

            case 'cb':
                $shadowY += ($shadow / 2);
                break;

            default:
                $shadowX += ($shadow / 2);
                $shadowY += ($shadow / 2);
        }
        $this->ttfBoxText($ttfBox['Size'], $ttfBox['Angle'], $shadowX, $shadowY, $color, $ttfBox['Font'], $ttfBox['Content'],
            $shadow, $img);
        $this->ttfBoxText($ttfBox['Size'], $ttfBox['Angle'], $ttfBox['X'], $ttfBox['Y'], $ttfBox['Color'], $ttfBox['Font'],
            $ttfBox['Content'], 0, $img);
        return TRUE;
    }

    /**
     * Get image resource
     * @return mixed
     */
    public function getResource()
    {
        return $this->imgResource;
    }

    /**
     * @param null $img
     * @return bool|resource
     */
    public function duplicateNewImage($img = NULL)
    {
        if (NULL === $img) $img = $this->imgResource;
        if (!$this->isGDResource($img)) return FALSE;
        $width = imagesx($img);
        $height = imagesy($img);
        $newImg = imagecreatetruecolor($width, $height);
        imagealphablending($newImg, FALSE);
        imagesavealpha($newImg, TRUE);
        imagecopyresampled($newImg, $img, 0, 0, 0, 0, $width, $height, $width, $height);
        return $newImg;
    }

    /**
     * @param string $Output
     * @param string $IType
     * @param string $File
     * @param array $Params
     * @param null $img
     * @return bool|null
     */
    public function output($Output = 'o', $IType = 'png', $File = '', $Params = [], $img = NULL)
    {
        if (NULL === $img) $img = $this->imgResource;
        if (!$this->isGDResource($img)) return FALSE;
        $this->initSave($img);
        switch (strtolower($IType)) {
            case 'gif':
                $FncNm = 'imagegif';
                $Mime = 'gif';
                break;
            case 'jpg':
                $FncNm = 'imagejpeg';
                $Mime = 'jpg';
                break;
            case 'png':
                $FncNm = 'imagepng';
                $Mime = 'png';
                break;
            case 'wbmp':
                $FncNm = 'imagewbmp';
                $Mime = 'vnd.wap.wbmp';
                break;
            default:
                return $img;
        }
        if (!is_array($Params)) $Params = [$Params];
        $Params = array_values($Params);
        switch ($Output) {
            case 'o':
                header("Content-Type: image/{$Mime}");
                call_user_func_array($FncNm, [$img, NULL] + $Params);
                break;

            case 'f':
                call_user_func_array($FncNm, [$img, $File] + $Params);
                break;

            case 'd':
                header('Content-Type: application/octet-stream');
                header("Content-Transfer-Encoding: Binary");
                header("Content-disposition: attachment; filename=\"" . basename($File) . "\"");
                call_user_func_array($FncNm, [$img, NULL] + $Params);
                break;

            case 'r':
                return $img;
        }
        return TRUE;
    }

    /**
     * @param null $img
     * @return bool
     */
    public function close($img = NULL)
    {
        if (NULL === $img) $img = $this->imgResource;
        if (!$this->isGDResource($img)) return FALSE;
        if ($img === $this->imgResource) $this->imgResource = NULL;
        return imagedestroy($img);
    }

    public function __destruct()
    {
        if ($this->isGDResource($this->imgResource)) imagedestroy($this->imgResource);
    }
}