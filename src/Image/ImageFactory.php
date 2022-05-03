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

use Contao\CoreBundle\Image\ImageFactoryInterface;
use Contao\Image\ImageInterface;

class ImageFactory implements ImageFactoryInterface
{
    private $inner;

    public function __construct(ImageFactoryInterface $inner)
    {
        $this->inner = $inner;
    }

    public function create($path, $size = null, $options = null)
    {
        return $this->inner->create($path, $size, $options);
    }

    public function getImportantPartFromLegacyMode(ImageInterface $image, $mode)
    {
        return $this->inner->getImportantPartFromLegacyMode($image, $mode);
    }
}
