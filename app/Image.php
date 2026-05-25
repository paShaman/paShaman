<?php namespace App;

class Image
{
    /**
     * блюрим
     *
     * @param $file
     * @param $destination
     * @param $iterations
     */
    public static function blur($file, $destination, $iterations = 10)
    {
        $image = imagecreatefromjpeg($file);

        /* Get original image size */
        list($w, $h) = getimagesize($file);

        /* Create array with width and height of down sized images */
        $size = array('sm'=>array('w'=>intval($w/4), 'h'=>intval($h/4)),
            'md'=>array('w'=>intval($w/2), 'h'=>intval($h/2))
        );

        /* Scale by 25% and apply Gaussian blur */
        $sm = imagecreatetruecolor($size['sm']['w'],$size['sm']['h']);
        imagecopyresampled($sm, $image, 0, 0, 0, 0, $size['sm']['w'], $size['sm']['h'], $w, $h);

        for ($x=1; $x <=$iterations; $x++){
            imagefilter($sm, IMG_FILTER_GAUSSIAN_BLUR, 999);
        }

        imagefilter($sm, IMG_FILTER_SMOOTH,99);
        imagefilter($sm, IMG_FILTER_BRIGHTNESS, 10);

        /* Scale result by 200% and blur again */
        $md = imagecreatetruecolor($size['md']['w'], $size['md']['h']);
        imagecopyresampled($md, $sm, 0, 0, 0, 0, $size['md']['w'], $size['md']['h'], $size['sm']['w'], $size['sm']['h']);
        imagedestroy($sm);

        for ($x=1; $x <=$iterations; $x++){
            imagefilter($md, IMG_FILTER_GAUSSIAN_BLUR, 999);
        }

        imagefilter($md, IMG_FILTER_SMOOTH,99);
        imagefilter($md, IMG_FILTER_BRIGHTNESS, 10);

        /* Scale result back to original size */
        imagecopyresampled($image, $md, 0, 0, 0, 0, $w, $h, $size['md']['w'], $size['md']['h']);
        imagedestroy($md);

        // Apply filters of upsized image if you wish, but probably not needed
        //imagefilter($image, IMG_FILTER_GAUSSIAN_BLUR);
        //imagefilter($image, IMG_FILTER_SMOOTH,99);
        //imagefilter($image, IMG_FILTER_BRIGHTNESS, 10);

        imagejpeg($image, $destination);
        imagedestroy($image);
    }

    /**
    * image - the location of the image to pixelate
    * pixelate_x - the size of "pixelate" effect on X axis (default 10)
    * pixelate_y - the size of "pixelate" effect on Y axis (default 10)
    * output - the name of the output file (extension will be added)
    */
    public static function pixelate($file, $destination, $pixelate_x = 20, $pixelate_y = 20)
    {
        $image = imagecreatefromjpeg($file);

        /* Get original image size */
        list($width, $height) = getimagesize($file);

        // start from the top-left pixel and keep looping until we have the desired effect
        for($y = 0;$y < $height;$y += $pixelate_y+1)
        {

            for($x = 0;$x < $width;$x += $pixelate_x+1)
            {
                // get the color for current pixel
                $rgb = imagecolorsforindex($image, imagecolorat($image, $x, $y));

                // get the closest color from palette
                $color = imagecolorclosest($image, $rgb['red'], $rgb['green'], $rgb['blue']);
                imagefilledrectangle($image, $x, $y, $x+$pixelate_x, $y+$pixelate_y, $color);

            }
        }

        imagejpeg($image, $destination);
        imagedestroy($image);
    }
}