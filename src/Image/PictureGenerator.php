<?php

declare(strict_types=1);

/*
 * This file is part of the Contao Image Alternatives extension.
 *
 * (c) inspiredminds
 *
 * @license LGPL-3.0-or-later
 */

namespace InspiredMinds\ContaoImageAlternatives\Image;

use Contao\Image\ImageInterface;
use Contao\Image\PictureConfiguration;
use Contao\Image\PictureGeneratorInterface;
use Contao\Image\PictureInterface;
use Contao\Image\ResizeOptions;

class PictureGenerator implements PictureGeneratorInterface
{
    private $inner;

    public function __construct(PictureGeneratorInterface $inner)
    {
        $this->inner = $inner;
    }

    public function generate(ImageInterface $image, PictureConfiguration $config, ResizeOptions $options): PictureInterface
    {
        return $this->inner->generate($image, $config, $options);
    }
}
