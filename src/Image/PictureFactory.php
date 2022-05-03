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
use Contao\Image\PictureConfiguration;
use Contao\Image\PictureConfigurationItem;
use Contao\Image\PictureInterface;
use Contao\Image\ResizeConfiguration;
use Contao\Image\ResizeOptions;
use Contao\ImageSizeItemModel;
use Contao\ImageSizeModel;
use Contao\Model;
use Contao\StringUtil;
use Webmozart\PathUtil\Path;

class PictureFactory implements PictureFactoryInterface
{
    /**
     * Copy of Contao\CoreBundle\Image\PictureFactory::FORMATS_ORDER
     */
    private const FORMATS_ORDER = [
        'jxl' => 1,
        'avif' => 2,
        'heic' => 3,
        'webp' => 4,
        'png' => 5,
        'jpg' => 6,
        'jpeg' => 7,
        'gif' => 8,
    ];

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
                        if (isset($alternativeItem['alternative']) && $item['media'] === $alternativeItem['media']) {
                            $item['alternative'] = $alternativeItem['alternative'];
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
                    if (!empty($item['alternative'])) {
                        $alternativeFile = $this->getAlternative($file, $item['alternative']);

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

                        if (!empty($item['alternative']) && null !== ($alternativeFile = $this->getAlternative($file, $item['alternative']))) {
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
                    $sources = array_merge($sources, $picture->getSources());

                    return new Picture($picture->getImg(), $sources);
                }
            }
        }
        elseif (is_numeric($size[2]) && null !== ($imageSize = ImageSizeModel::findByPk($size[2])) && null !== ($sizeItems = ImageSizeItemModel::findVisibleByPid($imageSize->id, ['order' => 'sorting ASC'])))
        {
            $useAlternatives = false;

            foreach ($sizeItems as $sizeItem) {
                if (!empty($sizeItem->alternative)) {
                    $alternativeFile = $this->getAlternative($file, $sizeItem->alternative);

                    if (null !== $alternativeFile) {
                        $useAlternatives = true;
                        break;
                    }
                }
            }

            if ($useAlternatives) {
                $sources = [];

                foreach ($sizeItems as $sizeItem) {
                    $picture = $this->getPicture($file, $sizeItem);
                    $sources = array_merge($sources, $picture->getSources());
                    $sources[] = $picture->getImg();
                }

                $picture = $this->getPicture($file, $imageSize);
                $sources = array_merge($sources, $picture->getSources());

                return new Picture($picture->getImg(), $sources);
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

    /**
     * Copy of Contao\CoreBundle\Image\PictureFactory::createConfigItem
     */
    private function createConfigItem(array $imageSize = null): PictureConfigurationItem
    {
        $configItem = new PictureConfigurationItem();
        $resizeConfig = new ResizeConfiguration();

        if (null !== $imageSize) {
            if (isset($imageSize['width'])) {
                $resizeConfig->setWidth((int) $imageSize['width']);
            }

            if (isset($imageSize['height'])) {
                $resizeConfig->setHeight((int) $imageSize['height']);
            }

            if (isset($imageSize['zoom'])) {
                $resizeConfig->setZoomLevel((int) $imageSize['zoom']);
            }

            if (isset($imageSize['resizeMode'])) {
                $resizeConfig->setMode((string) $imageSize['resizeMode']);
            }

            $configItem->setResizeConfig($resizeConfig);

            if (isset($imageSize['sizes'])) {
                $configItem->setSizes((string) $imageSize['sizes']);
            }

            if (isset($imageSize['densities'])) {
                $configItem->setDensities((string) $imageSize['densities']);
            }

            if (isset($imageSize['media'])) {
                $configItem->setMedia((string) $imageSize['media']);
            }
        }

        return $configItem;
    }

    /**
     * Copy of Contao\CoreBundle\Image\PictureFactory::createConfig
     * 
     * @param ImageSizeModel|ImageSizeItemModel $sizeModel 
     */
    private function getFormats(Model $sizeModel): array
    {
        $formats = [];

        if (empty($sizeModel->formats)) {
            return $formats;
        }

        $formatsString = implode(';', StringUtil::deserialize($sizeModel->formats, true));

        foreach (explode(';', $formatsString) as $format) {
            [$source, $targets] = explode(':', $format, 2);
            $targets = explode(',', $targets);

            if (!isset($formats[$source])) {
                $formats[$source] = $targets;
                continue;
            }

            $formats[$source] = array_unique(array_merge($formats[$source], $targets));

            usort(
                $formats[$source],
                static function ($a, $b) {
                    return (self::FORMATS_ORDER[$a] ?? $a) <=> (self::FORMATS_ORDER[$b] ?? $b);
                }
            );
        }

        return $formats;
    }

    /**
     * @param ImageSizeModel|ImageSizeItemModel $sizeModel 
     */
    private function getPicture(FilesModel $file, Model $sizeModel): Picture
    {
        $path = $this->projectDir.'/'.$file->path;

        if (!empty($sizeModel->alternative) && null !== ($alternativeFile = $this->getAlternative($file, $sizeModel->alternative))) {
            // Do not use Path::join here (https://github.com/contao/contao/pull/4596)
            $path = $this->projectDir.'/'.$alternativeFile->path;
        }

        $options = new ResizeOptions();
        $options->setSkipIfDimensionsMatch((bool) $sizeModel->skipIfDimensionsMatch);
        
        $config = new PictureConfiguration();
        $config->setFormats($this->getFormats($sizeModel));
        $config->setSize($this->createConfigItem($sizeModel->row()));

        return $this->inner->create($path, $config, $options);
    }
}
