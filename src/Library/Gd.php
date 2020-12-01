<?php

namespace Npf\Library;

class Gd
{

    private $imgResource, $imgWidth, $imgHeight;

    /**
     * Is GD Resource
     * @param null $img
     * @return bool
     */
    private function isGDResource($img = null)
    {
        if (null === $img) $img = $this->imgResource;
        if (!is_resource($img)) return false;
        if (get_resource_type($img) === 'gd') return true;
        return false;
    }

    /**
     * Initial Load and Initial Save and Check Resource
     * @param null $img
     * @return Gd
     */
    private function initImage($img = null)
    {
        if ($this->isGDResource($img)) {
            imagepalettetotruecolor($img);
            imageantialias($img, true);
            imagealphablending($img, true);
            imagesavealpha($img, true);
            $this->imgWidth = imagesx($img);
            $this->imgHeight = imagesy($img);
            $this->imgResource = $img;
        }
        return $this;
    }

    /**
     * Section: Get Image Info
     */

    /**
     * Get Color
     * @param int $red
     * @param int $green
     * @param int $blue
     * @param int $alpha
     * @return bool|int
     */
    public function getColor($red = 0, $green = 0, $blue = 0, $alpha = 0)
    {
        if (!$this->isGDResource($this->imgResource)) return false;
        $red = (int)$red;
        $green = (int)$green;
        $blue = (int)$blue;
        $alpha = (int)$alpha;
        return @imagecolorallocatealpha($this->imgResource,
            (int)$red,
            (int)$green,
            (int)$blue,
            (int)$alpha);
    }

    /**
     * Get Loaded Image Given Position Pixel Color
     * @param int $x
     * @param int $y
     * @param bool $assoc
     * @return array|bool|int
     */
    public function getPixelColor($x = 0, $y = 0, $assoc = false)
    {
        $x = (int)$x;
        $y = (int)$y;
        $assoc = (boolean)$assoc;
        $color = imagecolorat($this->imgResource, $x, $y);
        if ($assoc !== true)
            return $color;
        else
            return imagecolorsforindex($this->imgResource, $color);
    }

    /**
     * Get Loaded Image Orientation
     * @return bool|string
     */
    public function getOrientation()
    {
        if (imagesx($this->imgResource) > imagesy($this->imgResource))
            return 'LANDSCAPE';
        else
            return 'PORTRAIT';
    }

    /**
     * @param $file
     * @return Gd
     */
    final public function loadImage($file)
    {
        $this->initImage($this->getImgResFromFile($file));
        return $this;
    }

    /**
     * Load File Image To Memory
     * @param string|resource $file
     * @return bool|resource|string
     */
    public function getImgResFromFile($file = '')
    {
        $img = false;
        if ($this->isGDResource($file)) {
            imagepalettetotruecolor($file);
            imageantialias($file, true);
            imagealphablending($file, true);
            imagesavealpha($file, true);
            return $file;
        } elseif (file_exists($file)) {
            if (function_exists('exif_imagetype'))
                $imgType = exif_imagetype($file);
            else {
                $imgInfo = getimagesize($file);
                $imgType = $imgInfo[2];
            }
            switch ($imgType) {
                case IMAGETYPE_GIF:
                    if (imagetypes() & IMG_GIF) $img = @imagecreatefromgif($file);
                    break;
                case IMAGETYPE_JPEG:
                    if (imagetypes() & IMG_JPG) $img = @imagecreatefromjpeg($file);
                    break;
                case IMAGETYPE_PNG:
                    if (imagetypes() & IMG_PNG) $img = @imagecreatefrompng($file);
                    break;
                case IMAGETYPE_XBM:
                    if (imagetypes() & IMG_XPM) $img = @imagecreatefromxbm($file);
                    break;
                case IMAGETYPE_WBMP:
                    if (imagetypes() & IMG_WBMP) $img = @imagecreatefromwbmp($file);
                    break;
                default:
                    $img = @imagecreatefromstring(file_get_contents($file));
            }
        } else
            $img = @imagecreatefromstring($file);
        if (!$this->isGDResource($img))
            return false;
        else {
            imagepalettetotruecolor($img);
            imageantialias($img, true);
            imagealphablending($img, true);
            imagesavealpha($img, true);
            return $img;
        }
    }

    /**
     * Section: Graphic Tool
     */

    /**
     * Get loaded image calculate new maintain ratio height with given width
     * @param int $width
     * @param null $oriHeight
     * @param null $oriWidth
     * @return float
     */
    public function getNewHeight($width = 0, $oriWidth = null, $oriHeight = null)
    {
        if ($oriWidth === null)
            $oriWidth = $this->imgWidth;
        if ($oriHeight === null)
            $oriHeight = $this->imgHeight;
        return $oriHeight / ($oriWidth / $width);
    }

    /**
     * Get Loaded Image Calculate new maintain ratio width with given height
     * @param int $height
     * @param null $oriWidth
     * @param null $oriHeight
     * @return float
     */
    public function getNewWidth($height = 0, $oriWidth = null, $oriHeight = null)
    {
        if ($oriWidth === null)
            $oriWidth = $this->imgWidth;
        if ($oriHeight === null)
            $oriHeight = $this->imgHeight;
        return $oriWidth / ($oriHeight / $height);
    }

    /**
     * Image Process - Resize Caves, Crop or resize image
     * @param int $width
     * @param int $height
     * @param bool $crop
     * @param string $align
     * @return Gd
     */
    public function resizeCaves($width = 0, $height = 0, $crop = false, $align = 'cc')
    {
        $width = (int)$width;
        $height = (int)$height;
        if (!empty($width) || !empty($height)) {
            $imageX = $this->imgWidth;
            $imageY = $this->imgHeight;
            $oldRatio = $imageX / $imageY;
            $newRatio = $width / $height;
            if (($crop !== false && $newRatio < $oldRatio) || ($crop === false && $newRatio > $oldRatio)) {
                $newWidth = (int)($height * $oldRatio);
                $newHeight = $height;
            } else {
                $newWidth = $width;
                $newHeight = (int)($width / $oldRatio);
            }
            $imgOri = $this->imgResource;
            $this->createImage($width, $height);

            switch (strtolower($align)) {
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
            imagecopyresampled($this->imgResource, $imgOri, $x, $y, 0, 0, $newWidth, $newHeight, $imageX, $imageY);
            $this->fxAntiAlias();
            imagedestroy($imgOri);
        }
        return $this;
    }

    /**
     * Create image to memory
     * @param $width
     * @param $height
     * @return Gd
     */
    public function createImage($width, $height)
    {
        $width = (int)$width;
        $height = (int)$height;
        if (!empty($width) && !empty($height)) {
            $img = imagecreatetruecolor($width, $height);
            imagefill($img, 0, 0, $this->getColor(255, 255, 255, 127));
            $this->initImage($img);
        }
        return $this;
    }

    /**
     * Image Process - Resize Image
     * @param array $affine
     * @param array|null $rect
     * @return Gd
     */
    public function affine(array $affine, array $rect = [])
    {
        try {
            $img = @imageaffine($this->imgResource, $affine, $rect);
        } catch (\Exception $ex) {
            return $this;
        }
        if ($this->isGDResource($img))
            $this->initImage($img);
        return $this;
    }

    /**
     * Image Process - Resize Image
     * @param int $width
     * @param int $height
     * @return Gd
     */
    public function resize($width = 0, $height = 0)
    {
        $width = (int)$width;
        $height = (int)$height;
        $imageX = $this->imgWidth;
        $imageY = $this->imgHeight;
        if (!empty($width) && empty($height)) $height = $this->getNewHeight($width);
        if (empty($width) && !empty($height)) $width = $this->getNewWidth($height);
        $imageOri = $this->imgResource;
        $this->createImage($width, $height);
        imagecopyresampled($this->imgResource, $imageOri, 0, 0, 0, 0, $width, $height, $imageX, $imageY);
        imagedestroy($imageOri);
        return $this;
    }

    /**
     * Image Process - Merge Image from Load from file and memory with percentage override.
     * @param $file
     * @param array $rect
     * @param int $percent
     * @return Gd
     */
    public function copyImageFromFile($file, array $rect = null, $percent = 100)
    {
        $percent = (int)$percent;
        if (!is_array($rect)) $rect = [];
        if (!isset($rect['X'])) $rect['X'] = (int)0;
        if (!isset($rect['Y'])) $rect['Y'] = (int)0;
        $imgSrc = $this->getImgResFromFile($file);
        if ($this->isGDResource($imgSrc)) {

            $imgWidth = (int)imagesx($imgSrc);
            $imgHeight = (int)imagesy($imgSrc);
            if (!empty($rect['W']) && empty($rect['H']))
                $rect['H'] = $this->getNewHeight($rect['W'], $imgWidth, $imgHeight);
            elseif (empty($rect['W']) && !empty($rect['H']))
                $rect['W'] = $this->getNewWidth($rect['H'], $imgWidth, $imgHeight);
            elseif (empty($rect['H']) && empty($rect['H'])) {
                $rect['W'] = $imgWidth;
                $rect['H'] = $imgHeight;
            }
            $rect['X'] = (int)$rect['X'];
            $rect['Y'] = (int)$rect['Y'];
            $rect['W'] = (int)$rect['W'];
            $rect['H'] = (int)$rect['H'];

            imagecopymerge($this->imgResource, $imgSrc, $rect['X'], $rect['Y'], 0, 0, $rect['W'], $rect['H'], $percent);
            imagedestroy($imgSrc);
        }
        return $this;
    }

    public function autoCrop($mode = IMG_CROP_DEFAULT, $threshold = .5, $color = -1)
    {
        $cropped = imagecropauto($this->imgResource, $mode, $threshold, $color);
        if ($cropped !== false) {
            imagedestroy($this->imgResource);
            $this->initImage($cropped);
        }
        return $this;
    }

    /**
     * Image Process - Rotate Image
     * @param int $angle
     * @param int $bgColor
     * @param bool $ignoreTrans
     * @return Gd
     */
    public function rotateImage($angle = 0, $bgColor = 0, $ignoreTrans = false)
    {
        $angle = (double)$angle;
        $bgColor = (int)$bgColor;
        $ignoreTrans = (boolean)$ignoreTrans;
        if (!empty($angle)) {
            $this->initImage(imagerotate($this->imgResource, $angle, $bgColor, $ignoreTrans));
            $this->fxAntiAlias();
        }
        return $this;
    }

    /**
     * Image Process - Fill Color Start From Given Position
     * @param int $color
     * @param int $x
     * @param int $y
     * @return Gd
     */
    public function fillImage($color = 0, $x = 0, $y = 0)
    {
        $x = (int)$x;
        $y = (int)$y;
        $color = (int)$color;
        imagefill($this->imgResource, $x, $y, $color);
        return $this;
    }

    /**
     * Image Process - Flip Image
     * @param bool $vertical
     * @param bool $horizontal
     * @return Gd
     */
    public function flipImage($vertical = false, $horizontal = false)
    {
        if (!empty($vertical) || !empty($horizontal)) {
            $startX = 0;
            $startY = 0;
            $imageX = $this->imgWidth;
            $imageY = $this->imgHeight;
            if ($horizontal === true) {
                $startX = $imageX - 1;
                $imageX *= -1;
            }
            if ($vertical === true) {
                $startY = $imageY - 1;
                $imageY *= -1;
            }
            $imgOri = $this->imgResource;
            $this->createImage($this->imgWidth, $this->imgHeight);
            imagecopyresampled($this->imgResource, $imgOri, 0, 0, $startX, $startY, $this->imgWidth, $this->imgHeight, $imageX, $imageY);
            imagedestroy($imgOri);
        }
        return $this;
    }

    /**
     * Image Process - Rounded Corner
     * @param int $radius
     * @param bool $topLeft
     * @param bool $topRight
     * @param bool $bottomLeft
     * @param bool $bottomRight
     * @return Gd
     */
    public function roundedCorner($radius = 10, $topLeft = true, $topRight = true, $bottomLeft = true, $bottomRight = true)
    {
        $radius = (int)$radius;
        if (!empty($radius)) {
            imagealphablending($this->imgResource, false);
            $ghostColor = $this->getColor(255, 255, 255, 127);
            if ($topLeft) {
                imagearc($this->imgResource, $radius - 1, $radius - 1, $radius * 2, $radius * 2, 180, 270, $ghostColor);
                imagefilltoborder($this->imgResource, 0, 0, $ghostColor, $ghostColor);
            }
            if ($topRight) {
                imagearc($this->imgResource, $this->imgWidth - $radius, $radius - 1, $radius * 2, $radius * 2, 270, 0, $ghostColor);
                imagefilltoborder($this->imgResource, $this->imgWidth - 1, 0, $ghostColor, $ghostColor);
            }
            if ($bottomLeft) {
                imagearc($this->imgResource, $radius - 1, $this->imgHeight - $radius, $radius * 2, $radius * 2, 90, 180, $ghostColor);
                imagefilltoborder($this->imgResource, 0, $this->imgHeight - 1, $ghostColor, $ghostColor);
            }
            if ($bottomRight) {
                imagearc($this->imgResource, $this->imgWidth - $radius, $this->imgHeight - $radius, $radius * 2, $radius * 2, 0, 90, $ghostColor);
                imagefilltoborder($this->imgResource, $this->imgWidth - 1, $this->imgHeight - 1, $ghostColor, $ghostColor);
            }
            imagealphablending($this->imgResource, true);
        }
        return $this;
    }

    /**
     * Image Process - Draw a rectangle from given position
     * @param int $x1
     * @param int $y1
     * @param int $x2
     * @param int $y2
     * @param int $color
     * @param bool $fill
     * @return Gd
     */
    public function drawRectangle($x1 = 0, $y1 = 0, $x2 = 0, $y2 = 0, $color = 0, $fill = false)
    {
        $x1 = (int)$x1;
        $y1 = (int)$y1;
        $x2 = (int)$x2;
        $y2 = (int)$y2;
        $color = (int)$color;
        $fill = (boolean)$fill;
        if ($fill)
            imagefilledrectangle($this->imgResource, $x1, $y1, $x2, $y2, $color);
        else
            imagerectangle($this->imgResource, $x1, $y1, $x2, $y2, $color);
        return $this;
    }

    /**
     * Image Process - Draw a rectangle from given position
     * @param int $x1
     * @param int $y1
     * @param int $x2
     * @param int $y2
     * @param int $color
     * @return Gd
     */
    public function drawLine($x1 = 0, $y1 = 0, $x2 = 0, $y2 = 0, $color = 0)
    {
        $x1 = (int)$x1;
        $y1 = (int)$y1;
        $x2 = (int)$x2;
        $y2 = (int)$y2;
        $color = (int)$color;
        imageline($this->imgResource, $x1, $y1, $x2, $y2, $color);
        return $this;
    }


    /**
     * Image Process - Smooth Arc
     * @param $x
     * @param $y
     * @param $width
     * @param $height
     * @param int $color
     * @param int $start
     * @param int $stop
     * @return Gd
     */
    public function smoothArc($x, $y, $width, $height, $color = 0, $start = 0, $stop = 360)
    {
        $start = deg2rad($start);
        $stop = deg2rad($stop);
        $color = (int)$color;
        while ($start < 0) $start += 2 * M_PI;
        while ($stop < 0) $stop += 2 * M_PI;
        while ($start > 2 * M_PI) $start -= 2 * M_PI;
        while ($stop > 2 * M_PI) $stop -= 2 * M_PI;
        if ($start > $stop) {
            $this->smoothArc($x, $y, $width, $height, $color, rad2deg($start), 2 * M_PI);
            $this->smoothArc($x, $y, $width, $height, $color, 0, rad2deg($stop));
            return $this;
        }
        $a = 1.0 * round($width / 2);
        $b = 1.0 * round($height / 2);
        $x = 1.0 * round($x);
        $y = 1.0 * round($y);
        $aaAngle = atan(($b * $b) / ($a * $a) * tan(0.25 * M_PI));
        $aaAngleX = $a * cos($aaAngle);
        $aaAngleY = $b * sin($aaAngle);
        $a -= 0.5;
        $b -= 0.5;
        for ($i = 0; $i < 4; $i++)
            if ($start < ($i + 1) * M_PI / 2)
                if ($start > $i * M_PI / 2) {
                    if ($stop > ($i + 1) * M_PI / 2) $this->smoothArcDrawSegment($x, $y, $a, $b, $aaAngleX, $aaAngleY,
                        $color, $start, ($i + 1) * M_PI / 2, $i);
                    else {
                        $this->smoothArcDrawSegment($x, $y, $a, $b, $aaAngleX, $aaAngleY, $color, $start, $stop, $i);
                        break;
                    }
                } else {
                    if ($stop > ($i + 1) * M_PI / 2) $this->smoothArcDrawSegment($x, $y, $a, $b, $aaAngleX, $aaAngleY,
                        $color, $i * M_PI / 2, ($i + 1) * M_PI / 2, $i);
                    else {
                        $this->smoothArcDrawSegment($x, $y, $a, $b, $aaAngleX, $aaAngleY, $color, $i * M_PI / 2, $stop,
                            $i);
                        break;
                    }
                }
        return $this;
    }

    /**
     * Smooth Arc Draw Segment
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
    private function smoothArcDrawSegment($cx, $cy, $a, $b, $aaAngleX, $aaAngleY, $fillColor, $start,
                                          $stop, $seg)
    {
        $color = array_values(imagecolorsforindex($this->imgResource, $fillColor));
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
        if (abs($xStart) >= abs($yStart)) $aaStartX = true;
        else  $aaStartX = false;
        if ($xStop >= $yStop) $aaStopX = true;
        else  $aaStopX = false;
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
                        $diffColor1 = imagecolorexactalpha($this->imgResource, $color[0], $color[1], $color[2], 127 - (127 - $color[3]) *
                            $error1);
                        $y1 = $_y1;
                        if ($aaStopX) imagesetpixel($this->imgResource, $cx + $xp * ($x) + $xa, $cy + $yp * ($y1 + 1) + $ya, $diffColor1);
                    } else {
                        $y = $b * sqrt(1 - ($x * $x) / ($a * $a));
                        $error = $y - (int)($y);
                        $y = (int)($y);
                        $diffColor = imagecolorexactalpha($this->imgResource, $color[0], $color[1], $color[2], 127 - (127 - $color[3]) * $error);
                        $y1 = $y;
                        if ($x < $aaAngleX) imagesetpixel($this->imgResource, $cx + $xp * $x + $xa, $cy + $yp * ($y1 + 1) + $ya, $diffColor);
                    }
                    if ($start > $i * M_PI / 2 && $x <= $xStart) {
                        $diffColor2 = imagecolorexactalpha($this->imgResource, $color[0], $color[1], $color[2], 127 - (127 - $color[3]) *
                            $error2);
                        $y2 = $_y2;
                        if ($aaStartX) imagesetpixel($this->imgResource, $cx + $xp * $x + $xa, $cy + $yp * ($y2 - 1) + $ya, $diffColor2);
                    } else  $y2 = 0;
                    if ($y2 <= $y1) imageline($this->imgResource, $cx + $xp * $x + $xa, $cy + $yp * $y1 + $ya, $cx + $xp * $x + $xa, $cy +
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
                        $diffColor2 = imagecolorexactalpha($this->imgResource, $color[0], $color[1], $color[2], 127 - (127 - $color[3]) *
                            $error2);
                        $y1 = $_y2;
                        if ($aaStartX) imagesetpixel($this->imgResource, $cx + $xp * $x + $xa, $cy + $yp * ($y1 + 1) + $ya, $diffColor2);

                    } else {
                        $y = $b * sqrt(1 - ($x * $x) / ($a * $a));
                        $error = $y - (int)($y);
                        $y = (int)$y;
                        $diffColor = imagecolorexactalpha($this->imgResource, $color[0], $color[1], $color[2], 127 - (127 - $color[3]) * $error);
                        $y1 = $y;
                        if ($x < $aaAngleX) imagesetpixel($this->imgResource, $cx + $xp * $x + $xa, $cy + $yp * ($y1 + 1) + $ya, $diffColor);
                    }
                    if ($stop < ($i + 1) * M_PI / 2 && $x <= $xStop) {
                        $diffColor1 = imagecolorexactalpha($this->imgResource, $color[0], $color[1], $color[2], 127 - (127 - $color[3]) *
                            $error1);
                        $y2 = $_y1;
                        if ($aaStopX) imagesetpixel($this->imgResource, $cx + $xp * $x + $xa, $cy + $yp * ($y2 - 1) + $ya, $diffColor1);
                    } else  $y2 = 0;
                    if ($y2 <= $y1) imageline($this->imgResource, $cx + $xp * $x + $xa, $cy + $yp * $y1 + $ya, $cx + $xp * $x + $xa, $cy +
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
                        $diffColor1 = imagecolorexactalpha($this->imgResource, $color[0], $color[1], $color[2], 127 - (127 - $color[3]) *
                            $error1);
                        $x1 = $_x1;
                        if (!$aaStopX) imagesetpixel($this->imgResource, $cx + $xp * ($x1 - 1) + $xa, $cy + $yp * ($y) + $ya, $diffColor1);
                    }
                    if ($start > $i * M_PI / 2 && $y < $yStart) {
                        $diffColor2 = imagecolorexactalpha($this->imgResource, $color[0], $color[1], $color[2], 127 - (127 - $color[3]) *
                            $error2);
                        $x2 = $_x2;
                        if (!$aaStartX) imagesetpixel($this->imgResource, $cx + $xp * ($x2 + 1) + $xa, $cy + $yp * ($y) + $ya, $diffColor2);
                    } else {
                        $x = $a * sqrt(1 - ($y * $y) / ($b * $b));
                        $error = $x - (int)($x);
                        $x = (int)($x);
                        $diffColor = imagecolorexactalpha($this->imgResource, $color[0], $color[1], $color[2], 127 - (127 - $color[3]) * $error);
                        $x1 = $x;
                        if ($y < $aaAngleY && $y <= $yStop) imagesetpixel($this->imgResource, $cx + $xp * ($x1 + 1) + $xa, $cy + $yp * $y +
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
                        $diffColor2 = imagecolorexactalpha($this->imgResource, $color[0], $color[1], $color[2], 127 - (127 - $color[3]) *
                            $error2);
                        $x1 = $_x2;
                        if (!$aaStartX) imagesetpixel($this->imgResource, $cx + $xp * ($x1 - 1) + $xa, $cy + $yp * $y + $ya, $diffColor2);
                    }
                    if ($stop < ($i + 1) * M_PI / 2 && $y <= $yStop) {
                        $diffColor1 = imagecolorexactalpha($this->imgResource, $color[0], $color[1], $color[2], 127 - (127 - $color[3]) *
                            $error1);
                        $x2 = $_x1;
                        if (!$aaStopX) imagesetpixel($this->imgResource, $cx + $xp * ($x2 + 1) + $xa, $cy + $yp * $y + $ya, $diffColor1);
                    } else {
                        $x = $a * sqrt(1 - ($y * $y) / ($b * $b));
                        $error = $x - (int)($x);
                        $x = (int)($x);
                        $diffColor = imagecolorexactalpha($this->imgResource, $color[0], $color[1], $color[2], 127 - (127 - $color[3]) * $error);
                        $x1 = $x;
                        if ($y < $aaAngleY && $y < $yStart) imagesetpixel($this->imgResource, $cx + $xp * ($x1 + 1) + $xa, $cy + $yp * $y +
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

    /**
     * Image Effect - Gaussian Blur
     * @param int $level
     * @return Gd
     */
    public function fxAntiAlias($level = 1)
    {
        $level = (int)$level;
        if (!empty($level)) {
            $matrix = [
                [-1, -1, -1],
                [-1, 16, -1],
                [-1, -1, -1]
            ];
            for ($i = 0; $i < $level; $i++) imageconvolution($this->imgResource, $matrix, 8, 0);
        }
        return $this;
    }

    /**
     * Image Effect - Gamma Correction
     * @param int $gamma
     * @return Gd
     */
    public function fxGammaCorrection($gamma)
    {
        $gamma = (double)$gamma;
        if (!empty($gamma))
            imagegammacorrect($this->imgResource, 1, $gamma);
        return $this;
    }

    /**
     * Section: Image FX Effect Process
     */

    /**
     * Image Effect - Blur
     * @param int $level
     * @return Gd
     */
    public function fxBlur($level = 0)
    {
        $level = (int)$level;
        if (!function_exists('imagefilter'))
            for ($i = 0; $i < $level; $i++) imagefilter($this->imgResource, IMG_FILTER_SELECTIVE_BLUR);
        return $this;
    }

    /**
     * Image Effect - Contrast
     * @param int $level
     * @return Gd
     */
    public function fxContrast($level = 0)
    {
        if (function_exists('imagefilter'))
            imagefilter($this->imgResource, IMG_FILTER_CONTRAST, $level);
        return $this;
    }

    /**
     * Image Effect - Brightness
     * @param int $level
     * @return Gd
     */
    public function fxBrightness($level = 0)
    {
        if (!function_exists('imagefilter'))
            imagefilter($this->imgResource, IMG_FILTER_BRIGHTNESS, $level);
        return $this;
    }

    /**
     * Image Effect - Smooth
     * @param int $level
     * @return Gd
     */
    public function fxSmooth($level = 0)
    {
        if (!function_exists('imagefilter'))
            imagefilter($this->imgResource, IMG_FILTER_SMOOTH, $level);
        return $this;
    }

    /**
     * Image Effect - Sketchy
     * @return Gd
     */
    public function fxSketchy()
    {
        if (!function_exists('imagefilter'))
            imagefilter($this->imgResource, IMG_FILTER_MEAN_REMOVAL);
        return $this;
    }

    /**
     * Image Effect - Emboss
     * @return Gd
     */
    public function fxEmboss()
    {
        if (!function_exists('imagefilter'))
            imagefilter($this->imgResource, IMG_FILTER_EMBOSS);
        return $this;
    }

    /**
     * Image Effect - Edge
     * @return Gd
     */
    public function fxEdge()
    {
        if (!function_exists('imagefilter'))
            imagefilter($this->imgResource, IMG_FILTER_EDGEDETECT);
        return $this;
    }

    /**
     * Image Effect - Invert
     * @return Gd
     */
    public function fxInvert()
    {
        if (!function_exists('imagefilter'))
            imagefilter($this->imgResource, IMG_FILTER_NEGATE);
        return $this;
    }

    /**
     * Image Effect - Interlace
     * @param int $color
     * @return Gd
     */
    public function fxInterlace($color = 0)
    {
        $color = (int)$color;
        $imageX = $this->imgWidth;
        $imageY = $this->imgHeight;
        for ($y = 1; $y < $imageY; $y += 2)
            imageline($this->imgResource, 0, $y, $imageX, $y, $color);
        return $this;
    }

    /**
     * Image Effect - Greyscale
     * @return Gd
     */
    public function fxGreyscale()
    {
        if (!function_exists('imagefilter') || !imagefilter($this->imgResource, IMG_FILTER_GRAYSCALE)) {
            for ($y = 0; $y < $this->imgHeight; ++$y)
                for ($x = 0; $x < $this->imgWidth; ++$x) {
                    $color = imagecolorsforindex($this->imgResource, imagecolorat($this->imgResource, $x, $y));
                    $grey = (int)(($color['red'] + $color['green'] + $color['blue']) / 3);
                    imagesetpixel($this->imgResource, $x, $y, imagecolorallocatealpha($this->imgResource, $grey, $grey, $grey, $color['alpha']));
                }
        }
        return $this;
    }

    /**
     * Color Filter
     * @param bool $red
     * @param bool $green
     * @param bool $blue
     * @param int $compare
     * @return Gd
     */
    public function fxColorFilter($red = false, $green = false, $blue = false, $compare = 0)
    {
        $imageX = $this->imgWidth;
        $imageY = $this->imgHeight;
        $type = ($red === true ? 'Y' : 'N') . ($green === true ? 'Y' : 'N') . ($blue === true ? 'Y' : 'N');
        for ($y = 0; $y < $imageY; ++$y)
            for ($x = 0; $x < $imageX; ++$x) {
                $color = imagecolorsforindex($this->imgResource, imagecolorat($this->imgResource, $x, $y));
                $greyscale = true;
                switch ($type) {

                    case 'YNN':
                        if ($color['red'] - $color['green'] > $compare && $color['red'] - $color['blue'] > $compare)
                            $greyscale = false;
                        break;

                    case 'NYN':
                        if ($color['green'] - $color['red'] > $compare && $color['green'] - $color['blue'] > $compare)
                            $greyscale = false;
                        break;

                    case 'NNY':
                        if ($color['blue'] - $color['red'] > $compare && $color['blue'] - $color['green'] > $compare)
                            $greyscale = false;
                        break;

                    case 'YYN':
                        if ($color['red'] - $color['blue'] > $compare && $color['green'] - $color['blue'] > $compare)
                            $greyscale = false;
                        break;

                    case 'YNY':
                        if ($color['red'] - $color['green'] > $compare && $color['blue'] - $color['green'] > $compare)
                            $greyscale = false;
                        break;

                    case 'NYY':
                        if ($color['blue'] - $color['red'] > $compare && $color['green'] - $color['red'] > $compare)
                            $greyscale = false;
                        break;

                    default:
                        $greyscale = false;
                }
                if ($greyscale === true) {
                    $Grey = (int)(($color['red'] + $color['green'] + $color['blue']) / 3);
                    imagesetpixel($this->imgResource, $x, $y, imagecolorallocatealpha($this->imgResource, $Grey, $Grey, $Grey, $color['alpha']));
                }
            }
        return $this;
    }

    /**
     * Image Effect - Colorize
     * @param int $red
     * @param int $green
     * @param int $blue
     * @param int $alpha
     * @return Gd
     */
    public function fxColorize($red = 0, $green = 0, $blue = 0, $alpha = 0)
    {
        $red = (int)$red;
        $green = (int)$green;
        $blue = (int)$blue;
        if (empty($red) && empty($green) && empty($blue) && empty($blue)) return $this;
        if (!function_exists('imagefilter') || !imagefilter($this->imgResource, IMG_FILTER_COLORIZE, $red, $green, $blue,
                $alpha)
        ) {
            for ($y = 0; $y < $this->imgHeight; ++$y)
                for ($x = 0; $x < $this->imgWidth; ++$x) {
                    $color = imagecolorsforindex($this->imgResource, imagecolorat($this->imgResource, $x, $y));
                    $iRed = $color['red'] + $red;
                    $iGreen = $color['green'] + $green;
                    $iBlue = $color['blue'] + $blue;
                    $iAlpha = $color['alpha'] + $alpha;
                    if ($iRed > 255) $iRed = 255;
                    if ($iGreen > 255) $iGreen = 255;
                    if ($iBlue > 255) $iBlue = 255;
                    if ($iAlpha > 255) $iAlpha = 255;
                    if ($iRed < 0) $iRed = 0;
                    if ($iGreen < 0) $iGreen = 0;
                    if ($iBlue < 0) $iBlue = 0;
                    if ($iAlpha < 0) $iBlue = 0;
                    imagesetpixel($this->imgResource, $x, $y, imagecolorallocatealpha($this->imgResource, $iRed, $iGreen, $iBlue, $iAlpha));
                }
        }
        return $this;
    }

    /**
     * Image Effect - Noise
     * @param int $noise
     * @param int $level
     * @return Gd
     */
    public function fxNoise($noise = 50, $level = 20)
    {
        $level = (int)$level;
        $noise = (int)$noise;
        if (empty($level) && empty($noise)) return $this;
        for ($x = 0; $x < $this->imgWidth; $x++)
            for ($y = 0; $y < $this->imgHeight; $y++)
                if (rand(0, 100) <= $noise) {
                    $color = imagecolorsforindex($this->imgResource, imagecolorat($this->imgResource, $x, $y));
                    $modifier = rand($level * -1, $level);
                    $red = $color['red'] + $modifier;
                    $green = $color['green'] + $modifier;
                    $blue = $color['blue'] + $modifier;
                    if ($red > 255) $red = 255;
                    if ($green > 255) $green = 255;
                    if ($blue > 255) $blue = 255;
                    if ($red < 0) $red = 0;
                    if ($green < 0) $green = 0;
                    if ($blue < 0) $blue = 0;
                    imagesetpixel($this->imgResource, $x, $y, imagecolorallocatealpha($this->imgResource, $red, $green, $blue, $color['alpha']));
                }
        return $this;
    }

    /**
     * Image Effect - Scatter
     * @param int $level
     * @return Gd
     */
    public function fxScatter($level = 4)
    {
        $level = (int)$level;
        if (!empty($level)) {
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
                    $oldColor = imagecolorat($this->imgResource, $x, $y);
                    $newColor = imagecolorat($this->imgResource, $x + $DistX, $y + $DistY);
                    imagesetpixel($this->imgResource, $x, $y, $newColor);
                    imagesetpixel($this->imgResource, $x + $DistX, $y + $DistY, $oldColor);
                }
        }
        return $this;
    }

    /**
     * Image Effect - Pixelate
     * @param int $level
     * @return Gd
     */
    public function fxPixelate($level = 8)
    {
        $level = (int)$level;
        if (!empty($level)) {
            if (!function_exists('imagefilter') || !imagefilter($this->imgResource, IMG_FILTER_PIXELATE, $level, true)) {
                $pixelSize = (int)$level;
                for ($x = 0; $x < $this->imgWidth; $x += $pixelSize)
                    for ($y = 0; $y < $this->imgHeight; $y += $pixelSize) {
                        $tCol = imagecolorat($this->imgResource, $x, $y);
                        $nCol = ['r' => 0, 'g' => 0, 'b' => 0, 'a' => 0];
                        $cols = [];
                        for ($l = $y; $l < $y + $pixelSize; ++$l)
                            for ($k = $x; $k < $x + $pixelSize; ++$k) {
                                if ($k < 0) {
                                    $cols[] = $tCol;
                                    continue;
                                }
                                if ($k >= $this->imgWidth) {
                                    $cols[] = $tCol;
                                    continue;
                                }
                                if ($l < 0) {
                                    $cols[] = $tCol;
                                    continue;
                                }
                                if ($l >= $this->imgHeight) {
                                    $cols[] = $tCol;
                                    continue;
                                }
                                $cols[] = imagecolorat($this->imgResource, $k, $l);
                            }
                        foreach ($cols as $col) {
                            $color = imagecolorsforindex($this->imgResource, $col);
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
                        $nCol['Result'] = imagecolorallocatealpha($this->imgResource, $nCol['r'], $nCol['g'], $nCol['b'], $nCol['a']);
                        imagefilledrectangle($this->imgResource, $x, $y, $x + $pixelSize - 1, $y + $pixelSize - 1, $nCol['Result']);
                    }
            }
        }
        return $this;
    }

    /**
     * Image Effect - Box Blur
     * @param int $level
     * @return Gd
     */
    public function fxBoxBlur($level = 1)
    {
        $level = (int)$level;
        if (!empty($level)) {
            $matrix = [
                [1, 1, 1],
                [1, 1, 1],
                [1, 1, 1]
            ];
            for ($i = 0; $i < $level; $i++) imageconvolution($this->imgResource, $matrix, 9, 0);
        }
        return $this;
    }

    /**
     * Image Effect - Gaussian Blur
     * @param int $level
     * @return Gd
     */
    public function fxGaussianBlur($level = 1)
    {
        $level = (int)$level;
        if (!empty($level)) {
            $matrix = [
                [1, 2, 1],
                [2, 4, 2],
                [1, 2, 1]
            ];
            for ($i = 0; $i < $level; $i++) imageconvolution($this->imgResource, $matrix, 16, 0);
        }
        return $this;
    }

    /**
     * Image Effect - Gaussian Blur
     * @param int $level
     * @return Gd
     */
    public function fxSharpen($level = 1)
    {
        $level = (int)$level;
        if (!empty($level)) {
            $matrix = [
                [0, -1, 0],
                [-1, 5, -1],
                [0, -1, 0]
            ];
            for ($i = 0; $i < $level; $i++) imageconvolution($this->imgResource, $matrix, 1, 0);
        }
        return $this;
    }

    /**
     * Image Effect - Custom Convolution
     * @param array $matrix
     * @param int $offset
     * @param int $level
     * @return Gd
     */
    public function fxCustom(array $matrix, $offset = 0, $level = 1)
    {
        $level = (int)$level;
        $div = array_sum(array_map('array_sum', $matrix));
        if (!empty($level))
            for ($i = 0; $i < $level; $i++) imageconvolution($this->imgResource, $matrix, $div, $offset);
        return $this;
    }

    /**
     * Image Effect - Fish Eye
     * @return Gd
     */
    public function fxFishEye()
    {
        $CImageX = $this->imgWidth / 2; //Source middle
        $CImageY = $this->imgHeight / 2;
        if ($this->imgWidth > $this->imgHeight) $OW = 2 * $this->imgHeight / pi(); //Width for the destination image
        else  $OW = 2 * $this->imgWidth / pi();
        $imgOri = $this->imgResource;
        $this->createImage($OW + 1, $OW + 1);
        $OM = $OW / 2;
        for ($y = 0; $y <= $OW; ++$y)
            for ($x = 0; $x <= $OW; ++$x) {
                $OTX = $x - $OM;
                $OTY = $y - $OM; //Y in relation to the middle
                $OH = hypot($OTX, $OTY); //distance
                $Arc = (2 * $OM * asin($OH / $OM)) / (2);
                $Factor = $Arc / $OH;
                if ($OH <= $OM) imagesetpixel($this->imgResource, $x, $y, imagecolorat($imgOri, round($OTX * $Factor + $CImageX),
                    round($OTY * $Factor + $CImageY)));
            }
        imagedestroy($imgOri);
        return $this;
    }

    /**
     * Image Effect - Dream
     * @param int $percent
     * @param int $type
     * @return Gd
     */
    public function fxDream($percent = 30, $type = 0)
    {
        $imageX = $this->imgWidth;
        $imageY = $this->imgHeight;
        $imageOri = $this->imgResource;
        $this->createImage(255, 255);
        $type = is_int($type) ? $type : rand(0, 5);
        for ($x = 0; $x <= 255; $x++)
            for ($y = 0; $y <= 255; $y++) {
                switch ($type) {
                    case 1:
                        $col = imagecolorallocate($this->imgResource, 255, $y, $x);
                        break;

                    case 2:
                        $col = imagecolorallocate($this->imgResource, $y, 255, $x);
                        break;

                    case 3:
                        $col = imagecolorallocate($this->imgResource, $x, 255, $y);
                        break;

                    case 4:
                        $col = imagecolorallocate($this->imgResource, $x, $y, 255);
                        break;

                    case 5:
                        $col = imagecolorallocate($this->imgResource, $y, $x, 255);
                        break;

                    default:
                        $col = imagecolorallocate($this->imgResource, 255, $x, $y);
                }
                imagesetpixel($this->imgResource, $x, $y, $col);
            }
        $this->resize( $imageX, $imageY);
        imagecopymerge($imageOri, $this->imgResource, 0, 0, 0, 0, $imageX, $imageY, $percent);
        imagedestroy($this->imgResource);
        $this->initImage($imageOri);
        return $this;
    }

    /**
     * Build and return true tye font box information
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
     * Draw true type font text
     * @param $ttfBox
     * @return bool|Gd
     */
    public function ttfText($ttfBox)
    {
        if (!$this->isTTFBox($ttfBox)) return false;
        $this->drawTtfText($ttfBox['Size'], $ttfBox['Angle'], $ttfBox['X'], $ttfBox['Y'], $ttfBox['Color'], $ttfBox['Font'],
            $ttfBox['Content']);
        return $this;
    }

##########################################################################################
# Section: Special FX Font Effect
##########################################################################################

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
        ) return true;
        return false;
    }

    /**
     * Draw a true type font box text, process part
     * @param $size
     * @param $angle
     * @param $x
     * @param $y
     * @param $color
     * @param $font
     * @param $text
     * @param int $blur
     * @return Gd
     */
    private function drawTtfText($size, $angle, $x, $y, $color, $font, $text, $blur = 0)
    {
        $angle = (double)$angle;
        $x = (int)$x;
        $y = (int)$y;
        $color = (int)$color;
        $blur = (int)$blur;
        if ($blur > 0) {
            $textImg = imagecreatetruecolor(imagesx($this->imgResource), imagesy($this->imgResource));
            imagefill($textImg, 0, 0, imagecolorallocate($textImg, 0x00, 0x00, 0x00));
            imagettftext($textImg, $size, $angle, $x, $y, imagecolorallocate($textImg, 0xFF, 0xFF, 0xFF), $font, $text);
            for ($i = 1; $i <= $blur; $i++) imagefilter($textImg, IMG_FILTER_GAUSSIAN_BLUR);
            for ($xOff = 0; $xOff < imagesx($textImg); $xOff++)
                for ($yOff = 0; $yOff < imagesy($textImg); $yOff++) {
                    $Visible = (imagecolorat($textImg, $xOff, $yOff) & 0xFF) / 255;
                    if ($Visible > 0) imagesetpixel($this->imgResource, $xOff, $yOff, imagecolorallocatealpha($this->imgResource, ($color >> 16) &
                        0xFF, ($color >> 8) & 0xFF, $color & 0xFF, (1 - $Visible) * 127));
                }
            imagedestroy($textImg);
        } else
            imagettftext($this->imgResource, $size, $angle, $x, $y, $color, $font, $text);
        return $this;
    }

    /**
     * Draw true type font text with grow
     * @param $ttfBox
     * @param int $grow
     * @param int $color
     * @return Gd
     */
    public function ttfTextGrow($ttfBox, $grow = 10, $color = 0)
    {
        if (!$this->isTTFBox($ttfBox)) return $this;
        $grow = (int)$grow;
        $color = (int)$color;
        imagealphablending($this->imgResource, true);
        $this->drawTtfText($ttfBox['Size'], $ttfBox['Angle'], $ttfBox['X'], $ttfBox['Y'], $color, $ttfBox['Font'],
            $ttfBox['Content'], $grow);
        $this->drawTtfText($ttfBox['Size'], $ttfBox['Angle'], $ttfBox['X'], $ttfBox['Y'], $ttfBox['Color'], $ttfBox['Font'],
            $ttfBox['Content'], 0);
        return $this;
    }

    /**
     * Draw true type font text with shadow
     * @param $ttfBox
     * @param int $shadow
     * @param int $color
     * @param string $direction
     * @return Gd
     */
    public function ttfTextShadow($ttfBox, $shadow = 10, $color = 0, $direction = 'rb')
    {
        if (!$this->isTTFBox($ttfBox)) return $this;
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
        $this->drawTtfText($ttfBox['Size'], $ttfBox['Angle'], $shadowX, $shadowY, $color, $ttfBox['Font'], $ttfBox['Content'],
            $shadow);
        $this->drawTtfText($ttfBox['Size'], $ttfBox['Angle'], $ttfBox['X'], $ttfBox['Y'], $ttfBox['Color'], $ttfBox['Font'],
            $ttfBox['Content'], 0);
        return $this;
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
     * @return bool|resource
     */
    public function duplicateNewImage()
    {
        $width = $this->getWidth();
        $height = $this->getHeight();
        $newImg = imagecreatetruecolor($width, $height);
        imageantialias($newImg, true);
        imagealphablending($newImg, false);
        imagesavealpha($newImg, true);
        imagecopyresampled($newImg, $this->imgResource, 0, 0, 0, 0, $width, $height, $width, $height);
        imageconvolution($newImg, array(array(-1, -1, -1), array(-1, 16, -1), array(-1, -1, -1)), 8, 0);
        return $newImg;
    }

    /**
     * * Get Loaded Image Width
     * @return bool|int
     */
    public function getWidth()
    {
        return $this->imgWidth;
    }

    /**
     * Get Loaded Image Height
     * @return bool|int
     */
    public function getHeight()
    {
        return $this->imgHeight;
    }

    /**
     * @param string $outputType
     * @param string $imageType
     * @param string $file
     * @param array $params
     * @return Gd|string|resource
     */
    public function output($outputType = 'o', $imageType = 'png', $file = '', $params = [])
    {
        switch (strtolower($imageType)) {
            case 'gif':
                $imgFuncName = 'imagegif';
                $mime = 'gif';
                break;
            case 'jpg':
                $imgFuncName = 'imagejpeg';
                $mime = 'jpg';
                break;
            case 'png':
                $imgFuncName = 'imagepng';
                $mime = 'png';
                break;
            case 'wbmp':
                $imgFuncName = 'imagewbmp';
                $mime = 'vnd.wap.wbmp';
                break;
            case 'webp':
                $imgFuncName = 'imagewebp';
                $mime = 'webp';
                break;
            case 'xbm':
                $imgFuncName = 'imagexbm';
                $mime = 'xbm';
                break;
        }
        if (!empty($imgFuncName) && !empty($mime)) {
            if (!is_array($params))
                $params = [$params];
            $params = array_values($params);
            switch ($outputType) {
                case 'o':
                    header("Content-Type: image/{$mime}");
                    call_user_func_array($imgFuncName, array_merge([$this->imgResource, null], $params));
                    break;

                case 'f':
                    call_user_func_array($imgFuncName, array_merge([$this->imgResource, $file], $params));
                    break;

                case 'd':
                    header('Content-Type: application/octet-stream');
                    header("Content-Transfer-Encoding: Binary");
                    header("Content-disposition: attachment; filename=\"" . basename($file) . "\"");
                    call_user_func_array($imgFuncName, array_merge([$this->imgResource, null], $params));
                    break;

                case 's':
                    $tmp = tmpfile();
                    call_user_func_array($imgFuncName, array_merge([$this->imgResource, $tmp], $params));
                    rewind($tmp);
                    return stream_get_contents($tmp);
                    break;

                case 'r':
                    return $this->imgResource;
            }
        }
        return $this;
    }
}