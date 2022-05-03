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

use Contao\CoreBundle\Image\PictureFactory as ContaoPictureFactory;
use Contao\CoreBundle\Image\PictureFactoryInterface;
use Contao\FilesModel;
use Contao\Image\Picture;
use Contao\Image\PictureInterface;
use Contao\StringUtil;
use Webmozart\PathUtil\Path;

class PictureFactory implements PictureFactoryInterface
{
    private $inner;
    private $alternativeSizes;
    private $predefinedSizes;
    private $projectDir;

    public function __construct(ContaoPictureFactory $inner, array $alternativeSizes, array $predefinedSizes, string $projectDir)
    {
        $this->inner = $inner;
        $this->alternativeSizes = $alternativeSizes;
        $this->predefinedSizes = $predefinedSizes;
        $this->projectDir = $projectDir;

        foreach ($this->predefinedSizes as $name => &$config) {
            if (isset($this->alternativeSizes[$name]['items'], $config['items'])) {
                foreach ($this->alternativeSizes[$name]['items'] as $alternativeItem) {
                    foreach ($config['items'] as &$item) {
                        if (isset($alternativeItem['useAlternative']) && $item['media'] === $alternativeItem['media']) {
                            $item['useAlternative'] = $alternativeItem['useAlternative'];
                        }
                    }
                }
            }
        }
    }

    public function setDefaultDensities($densities): void
    {
        $this->inner->setDefaultDensities($densities);
    }

    public function create($path, $size = null): PictureInterface
    {
        if (!\is_array($size) || !isset($size[2])) {
            return $this->inner->create($path, $size);
        }

        $file = FilesModel::findByPath($path);

        if (null === $file) {
            return $this->inner->create($path, $size);
        }

        if (!is_numeric($size[2]) && isset($this->alternativeSizes[$size[2]])) {
            $predefinedSize = $this->predefinedSizes[$size[2]] ?? [];

            if (!empty($predefinedSize['items'])) {
                $useAlternatives = false;

                foreach ($predefinedSize['items'] as $item) {
                    if (!empty($item['useAlternative'])) {
                        $alternativeFile = $this->getAlternative($file, $item['useAlternative']);

                        if (null !== $alternativeFile) {
                            $useAlternatives = true;
                            break;
                        }
                    }
                }

                if ($useAlternatives) {
                    $index = 0;
                    $sources = [];

                    foreach ($predefinedSize['items'] as $item) {
                        $alternativeSizeName = $size[2].'_alternative_item_'.$index;
                        $alternativeSize = $item;
                        $alternativeSize['formats'] = $predefinedSize['formats'] ?? null;
                        $alternativeSize['items'] = [];
                        $alternativePath = $path;

                        if (!empty($item['useAlternative']) && null !== ($alternativeFile = $this->getAlternative($file, $item['useAlternative']))) {
                            // Do not use Path::join here (https://github.com/contao/contao/pull/4596)
                            $alternativePath = $this->projectDir.'/'.$alternativeFile->path;
                        }

                        $this->predefinedSizes[$alternativeSizeName] = $alternativeSize;
                        $this->inner->setPredefinedSizes($this->predefinedSizes);

                        $picture = $this->inner->create($alternativePath, [0, 0, $alternativeSizeName]);
                        $sources = array_merge($sources, $picture->getSources());
                        $sources[] = $picture->getImg();

                        ++$index;
                    }

                    $alternativeSizeName = $size[2].'_alternative';
                    $alternativeSize = $predefinedSize;
                    $alternativeSize['items'] = [];

                    $this->predefinedSizes[$alternativeSizeName] = $alternativeSize;
                    $this->inner->setPredefinedSizes($this->predefinedSizes);

                    $picture = $this->inner->create($path, [0, 0, $alternativeSizeName]);
                    $img = $picture->getImg();
                    $sources = array_merge($sources, $picture->getSources());

                    return new Picture($img, $sources);
                }
            }
        }

        return $this->inner->create($path, $size);
    }

    private function getAlternative(FilesModel $file, string $alternative): ?FilesModel
    {
        $alternatives = StringUtil::deserialize($file->alternatives, true);

        if (empty($alternatives[$alternative])) {
            return null;
        }

        $alternativeFile = FilesModel::findByUuid($alternatives[$alternative]);

        if (null === $alternativeFile) {
            return null;
        }

        if (!file_exists(Path::join($this->projectDir, $alternativeFile->path))) {
            return null;
        }

        return $alternativeFile;
    }
}
