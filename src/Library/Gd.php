<?php

namespace Npf\Library;

use Exception;
use GdImage;
use JetBrains\PhpStorm\ArrayShape;
use JetBrains\PhpStorm\Pure;

class Gd
{
    private ?GdImage $imgResource = null;
    private int $imgWidth = 0;
    private int $imgHeight = 0;

    /**
     * Gd Destructor
     */
    final public function __destruct()
    {
        $this->close();
    }

    /**
     * Is GD Resource
     * @param GdImage|null $img
     * @return bool
     */
    private function isGDResource(mixed $img = null): bool
    {
        if (null === $img) $img = $this->imgResource;
        if ($img instanceof GdImage) return true;
        return false;
    }

    /**
     * Initial Load and Initial Save and Check Resource
     * @param bool|GdImage|null $img
     * @return void
     */
    private function initImage(bool|null|GdImage $img = null): void
    {
        if ($img && $this->isGDResource($img)) {
            imagepalettetotruecolor($img);
            imageantialias($img, true);
            imagealphablending($img, true);
            imagesavealpha($img, true);
            $this->imgWidth = imagesx($img);
            $this->imgHeight = imagesy($img);
            $this->imgResource = $img;
        }
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
    final public function getColor(int $red = 0, int $green = 0, int $blue = 0, int $alpha = 0): bool|int
    {
        if (!$this->isGDResource($this->imgResource)) return false;
        return @imagecolorallocatealpha($this->imgResource,
            $red,
            $green,
            $blue,
            $alpha);
    }

    /**
     * Get Loaded Image Given Position Pixel Color
     * @param int $x
     * @param int $y
     * @param bool $assoc
     * @return array|bool|int
     */
    #[Pure] public function getPixelColor(int $x = 0, int $y = 0, bool $assoc = false): array|bool|int
    {
        if (!$this->isGDResource($this->imgResource))
            return false;
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
    #[Pure] public function getOrientation(): bool|string
    {
        if (!$this->isGDResource($this->imgResource))
            return '-';
        elseif (imagesx($this->imgResource) > imagesy($this->imgResource))
            return 'LANDSCAPE';
        else
            return 'PORTRAIT';
    }

    /**
     * @param string|GdImage $file
     * @return self
     */
    final public function loadImage(string|GdImage $file): self
    {
        $this->initImage($this->getImgResFromFile($file));
        return $this;
    }

    /**
     * Load File Image To Memory
     * @param string|GdImage $file
     * @return bool|string
     */
    final public function getImgResFromFile(string|GdImage $file = ''): bool|GdImage
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
     * @param int|null $oriWidth
     * @param int|null $oriHeight
     * @return float|int
     */
    final public function getNewHeight(int $width = 0, ?int $oriWidth = null, ?int $oriHeight = null): float|int
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
     * @param int|null $oriWidth
     * @param int|null $oriHeight
     * @return float|int
     */
    final public function getNewWidth(int $height = 0, ?int $oriWidth = null, ?int $oriHeight = null): float|int
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
     * @return self
     */
    final public function resizeCaves(int $width = 0, int $height = 0, bool $crop = false, string $align = 'cc'): self
    {
        if (!$this->isGDResource($this->imgResource))
            return $this;
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
     * @param int $width
     * @param int $height
     * @return Gd
     */
    final public function createImage(int $width, int $height): self
    {
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
     * @param array $rect
     * @return self
     */
    final public function affine(array $affine, array $rect = []): self
    {
        if ($this->isGDResource($this->imgResource)) {
            try {
                $img = @imageaffine($this->imgResource, $affine, $rect);
            } catch (Exception) {
                return $this;
            }
            if ($this->isGDResource($img))
                $this->initImage($img);
        }
        return $this;
    }

    /**
     * Image Process - Resize Image
     * @param int $width
     * @param int $height
     * @return self
     */
    final public function resize(int $width = 0, int $height = 0): self
    {
        if ($this->isGDResource($this->imgResource)) {
            $imageX = $this->imgWidth;
            $imageY = $this->imgHeight;
            if (!empty($width) && empty($height)) $height = $this->getNewHeight($width);
            if (empty($width) && !empty($height)) $width = $this->getNewWidth($height);
            $imageOri = $this->imgResource;
            $this->createImage($width, $height);
            imagecopyresampled($this->imgResource, $imageOri, 0, 0, 0, 0, $width, $height, $imageX, $imageY);
            imagedestroy($imageOri);
        }
        return $this;
    }

    /**
     * Image Process - Merge Image from Load from file and memory with percentage override.
     * @param string|GdImage $file
     * @param array|null $rect
     * @param float $percent
     * @return self
     */
    final public function copyImageFromFile(string|GdImage $file,
                                            array          $rect = null,
                                            float          $percent = 100): self
    {
        if ($this->isGDResource($this->imgResource)) {
            $percent = (int)$percent;
            if (!is_array($rect)) $rect = [];
            if (!isset($rect['X'])) $rect['X'] = 0;
            if (!isset($rect['Y'])) $rect['Y'] = 0;
            $imgSrc = $this->getImgResFromFile($file);
            if ($this->isGDResource($imgSrc)) {

                $imgWidth = (int)imagesx($imgSrc);
                $imgHeight = (int)imagesy($imgSrc);
                if (!empty($rect['W']) && empty($rect['H']))
                    $rect['H'] = $this->getNewHeight($rect['W'], $imgWidth, $imgHeight);
                elseif (empty($rect['W']) && !empty($rect['H']))
                    $rect['W'] = $this->getNewWidth($rect['H'], $imgWidth, $imgHeight);
                elseif (empty($rect['W']) && empty($rect['H'])) {
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
        }
        return $this;
    }

    /**
     * @param int $mode
     * @param float $threshold
     * @param int $color
     * @return $this
     */
    final public function autoCrop(int   $mode = IMG_CROP_DEFAULT,
                                   float $threshold = .5,
                                   int   $color = -1): self
    {
        if ($this->isGDResource($this->imgResource)) {
            $cropped = imagecropauto($this->imgResource, $mode, $threshold, $color);
            if ($cropped !== false) {
                imagedestroy($this->imgResource);
                $this->initImage($cropped);
            }
        }
        return $this;
    }

    /**
     * Image Process - Rotate Image
     * @param int $angle
     * @param int $bgColor
     * @param bool $ignoreTrans
     * @return self
     */
    final public function rotateImage(int  $angle = 0,
                                      int  $bgColor = 0,
                                      bool $ignoreTrans = false): self
    {
        if ($this->isGDResource($this->imgResource) && !empty($angle)) {
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
     * @return self
     */
    final public function fillImage(int $color = 0,
                                    int $x = 0,
                                    int $y = 0): self
    {
        if ($this->isGDResource($this->imgResource))
            imagefill($this->imgResource, $x, $y, $color);
        return $this;
    }

    /**
     * Image Process - Flip Image
     * @param bool $vertical
     * @param bool $horizontal
     * @return self
     */
    final public function flipImage(bool $vertical = false, bool $horizontal = false): self
    {
        if ($this->isGDResource($this->imgResource)) {
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
     * @return self
     */
    final public function roundedCorner(int  $radius = 10,
                                        bool $topLeft = true,
                                        bool $topRight = true,
                                        bool $bottomLeft = true,
                                        bool $bottomRight = true): self
    {
        if ($this->isGDResource($this->imgResource) && !empty($radius)) {
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
     * @return self
     */
    final public function drawRectangle(int  $x1 = 0,
                                        int  $y1 = 0,
                                        int  $x2 = 0,
                                        int  $y2 = 0,
                                        int  $color = 0,
                                        bool $fill = false): self
    {
        if ($this->isGDResource($this->imgResource)) {
            if ($fill)
                imagefilledrectangle($this->imgResource, $x1, $y1, $x2, $y2, $color);
            else
                imagerectangle($this->imgResource, $x1, $y1, $x2, $y2, $color);
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
     * @return Gd
     */
    final public function drawLine(int $x1 = 0,
                                   int $y1 = 0,
                                   int $x2 = 0,
                                   int $y2 = 0,
                                   int $color = 0): self
    {
        if ($this->isGDResource($this->imgResource))
            imageline($this->imgResource, $x1, $y1, $x2, $y2, $color);
        return $this;
    }


    /**
     * Image Process - Smooth Arc
     * @param int $x
     * @param int $y
     * @param int $width
     * @param int $height
     * @param int $color
     * @param int $start
     * @param int $stop
     * @return self
     */
    final public function smoothArc(int $x,
                                    int $y,
                                    int $width,
                                    int $height,
                                    int $color = 0,
                                    int $start = 0,
                                    int $stop = 360): self
    {
        if (!$this->isGDResource($this->imgResource))
            return $this;
        $start = deg2rad($start);
        $stop = deg2rad($stop);
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
     * @param int $cx
     * @param int $cy
     * @param int $a
     * @param int $b
     * @param int $aaAngleX
     * @param int $aaAngleY
     * @param int $fillColor
     * @param int $start
     * @param int $stop
     * @param int $seg
     * @return void
     */
    private function smoothArcDrawSegment(int $cx,
                                          int $cy,
                                          int $a,
                                          int $b,
                                          int $aaAngleX,
                                          int $aaAngleY,
                                          int $fillColor,
                                          int $start,
                                          int $stop,
                                          int $seg): void
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
        if (abs($xStart) >= abs($yStart)) $aaStartX = true; else  $aaStartX = false;
        if ($xStop >= $yStop) $aaStopX = true; else  $aaStopX = false;
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
                        $diffColor1 = imagecolorexactalpha($this->imgResource, $color[0], $color[1], $color[2], 127 - (127 - $color[3]) * $error1);
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
                        $diffColor2 = imagecolorexactalpha($this->imgResource, $color[0], $color[1], $color[2], 127 - (127 - $color[3]) * $error2);
                        $y2 = $_y2;
                        if ($aaStartX) imagesetpixel($this->imgResource, $cx + $xp * $x + $xa, $cy + $yp * ($y2 - 1) + $ya, $diffColor2);
                    } else  $y2 = 0;
                    if ($y2 <= $y1) imageline($this->imgResource, $cx + $xp * $x + $xa, $cy + $yp * $y1 + $ya, $cx + $xp * $x + $xa, $cy + $yp * $y2 + $ya, $fillColor);
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
                        $diffColor2 = imagecolorexactalpha($this->imgResource, $color[0], $color[1], $color[2], 127 - (127 - $color[3]) * $error2);
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
                        $diffColor1 = imagecolorexactalpha($this->imgResource, $color[0], $color[1], $color[2], 127 - (127 - $color[3]) * $error1);
                        $y2 = $_y1;
                        if ($aaStopX) imagesetpixel($this->imgResource, $cx + $xp * $x + $xa, $cy + $yp * ($y2 - 1) + $ya, $diffColor1);
                    } else  $y2 = 0;
                    if ($y2 <= $y1) imageline($this->imgResource, $cx + $xp * $x + $xa, $cy + $yp * $y1 + $ya, $cx + $xp * $x + $xa, $cy + $yp * $y2 + $ya, $fillColor);
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
                        $diffColor1 = imagecolorexactalpha($this->imgResource, $color[0], $color[1], $color[2], 127 - (127 - $color[3]) * $error1);
                        $x1 = $_x1;
                        if (!$aaStopX) imagesetpixel($this->imgResource, $cx + $xp * ($x1 - 1) + $xa, $cy + $yp * ($y) + $ya, $diffColor1);
                    }
                    if ($start > $i * M_PI / 2 && $y < $yStart) {
                        $diffColor2 = imagecolorexactalpha($this->imgResource, $color[0], $color[1], $color[2], 127 - (127 - $color[3]) * $error2);
                        $x2 = $_x2;
                        if (!$aaStartX) imagesetpixel($this->imgResource, $cx + $xp * ($x2 + 1) + $xa, $cy + $yp * ($y) + $ya, $diffColor2);
                    } else {
                        $x = $a * sqrt(1 - ($y * $y) / ($b * $b));
                        $error = $x - (int)($x);
                        $x = (int)($x);
                        $diffColor = imagecolorexactalpha($this->imgResource, $color[0], $color[1], $color[2], 127 - (127 - $color[3]) * $error);
                        $x1 = $x;
                        if ($y < $aaAngleY && $y <= $yStop) imagesetpixel($this->imgResource, $cx + $xp * ($x1 + 1) + $xa, $cy + $yp * $y + $ya, $diffColor);
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
                        $diffColor2 = imagecolorexactalpha($this->imgResource, $color[0], $color[1], $color[2], 127 - (127 - $color[3]) * $error2);
                        $x1 = $_x2;
                        if (!$aaStartX) imagesetpixel($this->imgResource, $cx + $xp * ($x1 - 1) + $xa, $cy + $yp * $y + $ya, $diffColor2);
                    }
                    if ($stop < ($i + 1) * M_PI / 2 && $y <= $yStop) {
                        $diffColor1 = imagecolorexactalpha($this->imgResource, $color[0], $color[1], $color[2], 127 - (127 - $color[3]) * $error1);
                        $x2 = $_x1;
                        if (!$aaStopX) imagesetpixel($this->imgResource, $cx + $xp * ($x2 + 1) + $xa, $cy + $yp * $y + $ya, $diffColor1);
                    } else {
                        $x = $a * sqrt(1 - ($y * $y) / ($b * $b));
                        $error = $x - (int)($x);
                        $x = (int)($x);
                        $diffColor = imagecolorexactalpha($this->imgResource, $color[0], $color[1], $color[2], 127 - (127 - $color[3]) * $error);
                        $x1 = $x;
                        if ($y < $aaAngleY && $y < $yStart) imagesetpixel($this->imgResource, $cx + $xp * ($x1 + 1) + $xa, $cy + $yp * $y + $ya, $diffColor);
                    }
                }
            }
        }
    }

    /**
     * Image Effect - Gaussian Blur
     * @param int $level
     * @return self
     */
    final public function fxAntiAlias(int $level = 1): self
    {
        if ($this->isGDResource($this->imgResource) && !empty($level)) {
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
     * @return self
     */
    final public function fxGammaCorrection(int $gamma): self
    {
        $gamma = (double)$gamma;
        if ($this->isGDResource($this->imgResource) && !empty($gamma))
            imagegammacorrect($this->imgResource, 1, $gamma);
        return $this;
    }

    /**
     * Section: Image FX Effect Process
     */

    /**
     * Image Effect - Blur
     * @param int $level
     * @return self
     */
    final public function fxBlur(int $level = 0): self
    {
        if ($this->isGDResource($this->imgResource) && function_exists('imagefilter'))
            for ($i = 0; $i < $level; $i++) imagefilter($this->imgResource, IMG_FILTER_SELECTIVE_BLUR);
        return $this;
    }

    /**
     * Image Effect - Contrast
     * @param int $level
     * @return self
     */
    final public function fxContrast(int $level = 0): self
    {
        if ($this->isGDResource($this->imgResource) && function_exists('imagefilter'))
            imagefilter($this->imgResource, IMG_FILTER_CONTRAST, $level);
        return $this;
    }

    /**
     * Image Effect - Brightness
     * @param int $level
     * @return self
     */
    final public function fxBrightness(int $level = 0): self
    {
        if ($this->isGDResource($this->imgResource) && function_exists('imagefilter'))
            imagefilter($this->imgResource, IMG_FILTER_BRIGHTNESS, $level);
        return $this;
    }

    /**
     * Image Effect - Smooth
     * @param int $level
     * @return self
     */
    final public function fxSmooth(int $level = 0): self
    {
        if ($this->isGDResource($this->imgResource) && function_exists('imagefilter'))
            imagefilter($this->imgResource, IMG_FILTER_SMOOTH, $level);
        return $this;
    }

    /**
     * Image Effect - Sketchy
     * @return self
     */
    final public function fxSketchy(): self
    {
        if ($this->isGDResource($this->imgResource) && function_exists('imagefilter'))
            imagefilter($this->imgResource, IMG_FILTER_MEAN_REMOVAL);
        return $this;
    }

    /**
     * Image Effect - Emboss
     * @return self
     */
    final public function fxEmboss(): self
    {
        if ($this->isGDResource($this->imgResource) && function_exists('imagefilter'))
            imagefilter($this->imgResource, IMG_FILTER_EMBOSS);
        return $this;
    }

    /**
     * Image Effect - Edge
     * @return self
     */
    final public function fxEdge(): self
    {
        if ($this->isGDResource($this->imgResource) && function_exists('imagefilter'))
            imagefilter($this->imgResource, IMG_FILTER_EDGEDETECT);
        return $this;
    }

    /**
     * Image Effect - Invert
     * @return self
     */
    final public function fxInvert(): self
    {
        if ($this->isGDResource($this->imgResource) && function_exists('imagefilter'))
            imagefilter($this->imgResource, IMG_FILTER_NEGATE);
        return $this;
    }

    /**
     * Image Effect - Interlace
     * @param int $color
     * @return self
     */
    final public function fxInterlace(int $color = 0): self
    {
        if (!$this->isGDResource($this->imgResource)) return $this;
        $imageX = $this->imgWidth;
        $imageY = $this->imgHeight;
        for ($y = 1; $y < $imageY; $y += 2)
            imageline($this->imgResource, 0, $y, $imageX, $y, $color);
        return $this;
    }

    /**
     * Image Effect - Greyscale
     * @return self
     */
    final public function fxGreyscale(): self
    {
        if (!$this->isGDResource($this->imgResource)) return $this;
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
     * @return self
     */
    final public function fxColorFilter(bool $red = false,
                                        bool $green = false,
                                        bool $blue = false,
                                        int  $compare = 0): self
    {
        if (!$this->isGDResource($this->imgResource)) return $this;
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
     * @return self
     */
    final public function fxColorize(int $red = 0,
                                     int $green = 0,
                                     int $blue = 0,
                                     int $alpha = 0): self
    {
        if (!$this->isGDResource($this->imgResource)) return $this;
        if (empty($red) && empty($green) && empty($blue)) return $this;
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
     * @return self
     */
    final public function fxNoise(int $noise = 50, int $level = 20): self
    {
        if (!$this->isGDResource($this->imgResource) || (empty($level) && empty($noise))) return $this;
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
     * @return self
     */
    final public function fxScatter(int $level = 4): self
    {
        if ($this->isGDResource($this->imgResource) && !empty($level)) {
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
     * @return self
     */
    final public function fxPixelate(int $level = 8): self
    {
        if ($this->isGDResource($this->imgResource) && !empty($level)) {
            if (!function_exists('imagefilter') || !imagefilter($this->imgResource, IMG_FILTER_PIXELATE, $level, true)) {
                $pixelSize = $level;
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
     * @return self
     */
    final public function fxBoxBlur(int $level = 1): self
    {
        if ($this->isGDResource($this->imgResource) && !empty($level)) {
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
     * @return self
     */
    final public function fxGaussianBlur(int $level = 1): self
    {
        if ($this->isGDResource($this->imgResource) && !empty($level)) {
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
     * @return self
     */
    final public function fxSharpen(int $level = 1): self
    {
        if ($this->isGDResource($this->imgResource) && !empty($level)) {
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
     * @return self
     */
    final public function fxCustom(array $matrix, int $offset = 0, int $level = 1): self
    {
        if ($this->isGDResource($this->imgResource)) {
            $div = array_sum(array_map('array_sum', $matrix));
            if (!empty($level))
                for ($i = 0; $i < $level; $i++) imageconvolution($this->imgResource, $matrix, $div, $offset);
        }
        return $this;
    }

    /**
     * Image Effect - Fish Eye
     * @return self
     */
    final public function fxFishEye(): self
    {
        if ($this->isGDResource($this->imgResource)) {
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
        }
        return $this;
    }

    /**
     * Image Effect - Dream
     * @param int $percent
     * @param int $type
     * @return self
     */
    final public function fxDream(int $percent = 30, int $type = 0): self
    {
        if ($this->isGDResource($this->imgResource)) {
            $imageX = $this->imgWidth;
            $imageY = $this->imgHeight;
            $imageOri = $this->imgResource;
            $this->createImage(255, 255);
            $type = is_int($type) ? $type : rand(0, 5);
            for ($x = 0; $x <= 255; $x++)
                for ($y = 0; $y <= 255; $y++) {
                    $col = match ($type) {
                        1 => imagecolorallocate($this->imgResource, 255, $y, $x),
                        2 => imagecolorallocate($this->imgResource, $y, 255, $x),
                        3 => imagecolorallocate($this->imgResource, $x, 255, $y),
                        4 => imagecolorallocate($this->imgResource, $x, $y, 255),
                        5 => imagecolorallocate($this->imgResource, $y, $x, 255),
                        default => imagecolorallocate($this->imgResource, 255, $x, $y),
                    };
                    imagesetpixel($this->imgResource, $x, $y, $col);
                }
            $this->resize($imageX, $imageY);
            imagecopymerge($imageOri, $this->imgResource, 0, 0, 0, 0, $imageX, $imageY, $percent);
            imagedestroy($this->imgResource);
            $this->initImage($imageOri);
        }
        return $this;
    }

    /**
     * Build and return true tye font box information
     * @param string $content
     * @param string $font
     * @param int $size
     * @param int $x
     * @param int $y
     * @param int $color
     * @param int $angle
     * @return array
     */
    #[ArrayShape(['X' => "int", 'Y' => "float|int", 'Width' => "float|int", 'Height' => "float|int", 'Font' => "", 'Size' => "int", 'Color' => "int", 'Angle' => "float", 'Content' => ""])]
    final public function ttfBox(string $content,
                                 string $font,
                                 int    $size = 10,
                                 int    $x = 0,
                                 int    $y = 0,
                                 int    $color = 0,
                                 int    $angle = 0): array
    {
        $tBox = imagettfbbox($size, $angle, $font, $content);
        return [
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
    }

    /**
     * Draw true type font text
     * @param array $ttfBox
     * @return self
     */
    final public function ttfText(array $ttfBox): self
    {
        if (!$this->isTTFBox($ttfBox)) return $this;
        $this->drawTtfText($ttfBox['Size'], $ttfBox['Angle'], $ttfBox['X'], $ttfBox['Y'], $ttfBox['Color'], $ttfBox['Font'],
            $ttfBox['Content']);
        return $this;
    }

##########################################################################################
# Section: Special FX Font Effect
##########################################################################################

    /**
     * Check the parameter is generate by ttfBox or not.
     * @param array $ttfBox
     * @return bool
     */
    #[Pure] private function isTTFBox(array $ttfBox): bool
    {
        if (isset($ttfBox['X']) && isset($ttfBox['Y']) && isset($ttfBox['Width']) &&
            isset($ttfBox['Height']) && isset($ttfBox['Font']) && isset($ttfBox['Size']) &&
            isset($ttfBox['Color']) && isset($ttfBox['Angle']) && isset($ttfBox['Content']) &&
            !empty($ttfBox['Content']) && !empty($ttfBox['Font']) && !
            empty($ttfBox['Size'])
        ) return true;
        return false;
    }

    /**
     * Draw a true type font box text, process part
     * @param int $size
     * @param int $angle
     * @param int $x
     * @param int $y
     * @param int $color
     * @param string $font
     * @param string $text
     * @param int $blur
     * @return void
     */
    private function drawTtfText(int    $size,
                                 int    $angle,
                                 int    $x,
                                 int    $y,
                                 int    $color,
                                 string $font,
                                 string $text,
                                 int    $blur = 0): void
    {
        if ($this->isGDResource($this->imgResource)) {
            $angle = (double)$angle;
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
        }
    }

    /**
     * Draw true type font text with grow
     * @param array $ttfBox
     * @param int $grow
     * @param int $color
     * @return self
     */
    final public function ttfTextGrow(array $ttfBox, int $grow = 10, int $color = 0): self
    {
        if (!$this->isTTFBox($ttfBox)) return $this;
        imagealphablending($this->imgResource, true);
        $this->drawTtfText($ttfBox['Size'], $ttfBox['Angle'], $ttfBox['X'], $ttfBox['Y'], $color, $ttfBox['Font'],
            $ttfBox['Content'], $grow);
        $this->drawTtfText($ttfBox['Size'], $ttfBox['Angle'], $ttfBox['X'], $ttfBox['Y'], $ttfBox['Color'], $ttfBox['Font'],
            $ttfBox['Content']);
        return $this;
    }

    /**
     * Draw true type font text with shadow
     * @param array $ttfBox
     * @param int $shadow
     * @param int $color
     * @param string $direction
     * @return self
     */
    final public function ttfTextShadow(array  $ttfBox,
                                        int    $shadow = 10,
                                        int    $color = 0,
                                        string $direction = 'rb'): self
    {
        if (!$this->isTTFBox($ttfBox)) return $this;
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
            $ttfBox['Content']);
        return $this;
    }

    /**
     * Get image resource
     * @return GdImage
     */
    final public function getResource(): GdImage
    {
        return $this->imgResource;
    }

    /**
     * @return bool|GdImage
     */
    final public function duplicateNewImage(): GdImage|bool
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
    final public function getWidth(): bool|int
    {
        return $this->imgWidth;
    }

    /**
     * Get Loaded Image Height
     * @return bool|int
     */
    final public function getHeight(): bool|int
    {
        return $this->imgHeight;
    }

    /**
     * @param int|float $a
     * @param int|float $b
     * @return float|int
     */
    private function gcd(int|float $a, int|float $b): float|int
    {
        return ($a % $b) ? $this->gcd($b, $a % $b) : $b;
    }

    /**
     * @return string
     */
    final public function getRatio(): string
    {
        $gcd = $this->gcd($this->imgWidth, $this->imgHeight);
        return ($this->imgWidth / $gcd) . ':' . ($this->imgHeight / $gcd);
    }

    /**
     * @param string $outputType
     * @param string $imageType
     * @param string $file
     * @param array $params
     * @return string|Gd|GdImage
     */
    final public function output(string $outputType = 'o',
                                 string $imageType = 'png',
                                 string $file = '',
                                 array  $params = []): string|self|GdImage
    {
        if ($this->isGDResource($this->imgResource)) {
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

                    case 'r':
                        return $this->imgResource;
                }
            }
        }
        return $this;
    }

    final public function close()
    {
        if (isset($this->imgResource) && $this->isGDResource($this->imgResource))
            @imagedestroy($this->imgResource);
        $this->imgWidth = 0;
        $this->imgHeight = 0;
        $this->imgResource = null;
    }

}