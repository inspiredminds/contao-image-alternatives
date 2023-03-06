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
use Contao\CoreBundle\Image\PictureFactory as ContaoPictureFactory;
use Contao\CoreBundle\Image\PictureFactoryInterface;
use Contao\FilesModel;
use Contao\Image\ImageInterface;
use Contao\Image\ImportantPart;
use Contao\Image\Picture;
use Contao\Image\PictureConfiguration;
use Contao\Image\PictureConfigurationItem;
use Contao\Image\PictureInterface;
use Contao\Image\ResizeConfiguration;
use Contao\Image\ResizeOptions;
use Contao\Image\ResizerInterface;
use Contao\ImageSizeItemModel;
use Contao\ImageSizeModel;
use Contao\Model;
use Contao\StringUtil;
use Webmozart\PathUtil\Path;

class PictureFactory implements PictureFactoryInterface
{
    /**
     * Copy of Contao\CoreBundle\Image\PictureFactory::FORMATS_ORDER.
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
    private $imageFactory;
    private $resizer;
    private $alternativeSizes;
    private $predefinedSizes;
    private $projectDir;

    public function __construct(ContaoPictureFactory $inner, ImageFactoryInterface $imageFactory, ResizerInterface $resizer, array $alternativeSizes, array $predefinedSizes, string $projectDir)
    {
        $this->inner = $inner;
        $this->imageFactory = $imageFactory;
        $this->resizer = $resizer;
        $this->alternativeSizes = $alternativeSizes;
        $this->predefinedSizes = $this->mergeImageSizes($predefinedSizes, $alternativeSizes);
        $this->projectDir = $projectDir;
    }

    public function setDefaultDensities($densities): void
    {
        $this->inner->setDefaultDensities($densities);
    }

    public function create($path, $size = null): PictureInterface
    {
        $size = StringUtil::deserialize($size);

        if (\is_int($size) || \is_string($size)) {
            $size = [0, 0, $size];
        }

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
                        $importantParts = $this->getImportantParts($alternativeFile ?? $file);

                        if (null !== $alternativeFile || isset($importantParts[$item['alternative']])) {
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
                        $itemPath = $path;
                        $alternativeFile = null;

                        if (!empty($item['alternative']) && null !== ($alternativeFile = $this->getAlternative($file, $item['alternative']))) {
                            // Do not use Path::join here (https://github.com/contao/contao/pull/4596)
                            $itemPath = $this->projectDir.'/'.$alternativeFile->path;
                        }

                        $this->predefinedSizes[$alternativeSizeName] = $alternativeSize;
                        $this->inner->setPredefinedSizes($this->predefinedSizes);

                        if ($itemPath instanceof ImageInterface) {
                            $itemImage = $itemPath;
                        } else {
                            $itemImage = $this->imageFactory->create($itemPath);
                        }

                        $importantParts = $this->getImportantParts($alternativeFile ?? $file);

                        // Override the important part
                        if (!empty($item['alternative']) && isset($importantParts[$item['alternative']])) {
                            $importantPart = $importantParts[$item['alternative']];

                            if ((float) $importantPart['width'] > 0 && (float) $importantPart['height'] > 0) {
                                $itemImage->setImportantPart(new ImportantPart(
                                    (float) $importantPart['x'],
                                    (float) $importantPart['y'],
                                    (float) $importantPart['width'],
                                    (float) $importantPart['height']
                                ));
                            }
                        }

                        if ($item['preCrop'] ?? false) {
                            $itemImage = $this->cropToImportantPart($itemImage);
                        }

                        $picture = $this->inner->create($itemImage, [0, 0, $alternativeSizeName]);
                        $sources = array_merge($sources, $picture->getSources());
                        $sources[] = $picture->getImg();

                        ++$index;
                    }

                    $alternativeSizeName = $size[2].'_alternative';
                    $alternativeSize = $predefinedSize;
                    $alternativeSize['items'] = [];

                    $this->predefinedSizes[$alternativeSizeName] = $alternativeSize;
                    $this->inner->setPredefinedSizes($this->predefinedSizes);

                    if ($this->alternativeSizes[$size[2]]['preCrop'] ?? false) {
                        $path = $this->cropToImportantPart($path);
                    }

                    $picture = $this->inner->create($path, [0, 0, $alternativeSizeName]);
                    $sources = array_merge($sources, $picture->getSources());

                    return new Picture($picture->getImg(), $sources);
                }
            }

            if ($this->alternativeSizes[$size[2]]['preCrop'] ?? false) {
                $path = $this->cropToImportantPart($path);
            }
        } elseif (is_numeric($size[2]) && null !== ($imageSize = ImageSizeModel::findByPk($size[2]))) {
            if (null !== ($sizeItems = ImageSizeItemModel::findVisibleByPid($imageSize->id, ['order' => 'sorting ASC']))) {
                $useAlternatives = false;

                foreach ($sizeItems as $sizeItem) {
                    if (!empty($sizeItem->alternative)) {
                        $alternativeFile = $this->getAlternative($file, $sizeItem->alternative);
                        $importantParts = $this->getImportantParts($alternativeFile ?? $file);

                        if (null !== $alternativeFile || isset($importantParts[$sizeItem->alternative])) {
                            $useAlternatives = true;
                            break;
                        }
                    }
                }

                if ($useAlternatives) {
                    $sources = [];

                    foreach ($sizeItems as $sizeItem) {
                        $sizeItem->preCrop = $imageSize->preCrop;
                        $picture = $this->getPicture($file, $sizeItem);
                        $sources = array_merge($sources, $picture->getSources());
                        $sources[] = $picture->getImg();
                    }

                    $picture = $this->getPicture($file, $imageSize);
                    $sources = array_merge($sources, $picture->getSources());

                    $img = $picture->getImg();

                    if ($imageSize->cssClass) {
                        $img['class'] = $imageSize->cssClass;
                    }

                    if ($imageSize->lazyLoading) {
                        $img['loading'] = 'lazy';
                    }

                    return new Picture($img, $sources);
                }
            }

            if ($imageSize->preCrop) {
                $path = $this->cropToImportantPart($path);
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
     * Copy of Contao\CoreBundle\Image\PictureFactory::createConfigItem.
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
     * Copy of Contao\CoreBundle\Image\PictureFactory::createConfig.
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
        $alternativeFile = null;

        if (!empty($sizeModel->alternative) && null !== ($alternativeFile = $this->getAlternative($file, $sizeModel->alternative))) {
            // Do not use Path::join here (https://github.com/contao/contao/pull/4596)
            $path = $this->projectDir.'/'.$alternativeFile->path;
        }

        $options = new ResizeOptions();
        $options->setSkipIfDimensionsMatch((bool) $sizeModel->skipIfDimensionsMatch);

        $config = new PictureConfiguration();
        $config->setFormats($this->getFormats($sizeModel));
        $config->setSize($this->createConfigItem($sizeModel->row()));

        $image = $this->imageFactory->create($path);
        $importantParts = $this->getImportantParts($alternativeFile ?? $file);

        // Override the important part
        if (!empty($sizeModel->alternative) && isset($importantParts[$sizeModel->alternative])) {
            $importantPart = $importantParts[$sizeModel->alternative];

            if ((float) $importantPart['width'] > 0 && (float) $importantPart['height'] > 0) {
                $image->setImportantPart(new ImportantPart(
                    (float) $importantPart['x'],
                    (float) $importantPart['y'],
                    (float) $importantPart['width'],
                    (float) $importantPart['height']
                ));
            }
        }

        if ($sizeModel->preCrop) {
            $image = $this->cropToImportantPart($image);
        }

        return $this->inner->create($image, $config, $options);
    }

    private function mergeImageSizes(array $predefinedSizes, array $alternativeSizes): array
    {
        foreach ($predefinedSizes as $name => &$config) {
            if (isset($alternativeSizes[$name]['items'], $config['items'])) {
                foreach ($alternativeSizes[$name]['items'] as $alternativeItem) {
                    foreach ($config['items'] as &$item) {
                        if (isset($alternativeItem['alternative']) && $item['media'] === $alternativeItem['media']) {
                            $item['alternative'] = $alternativeItem['alternative'];
                        }
                    }
                }
            }
        }

        return $predefinedSizes;
    }

    private function getImportantParts(FilesModel $file): array
    {
        if (empty($file->importantParts)) {
            return [];
        }

        return json_decode($file->importantParts, true);
    }

    /**
     * @param string|ImageInterface $image
     */
    private function cropToImportantPart($image): ImageInterface
    {
        if (!$image instanceof ImageInterface) {
            $image = $this->imageFactory->create($image);
        }

        $importantPart = $image->getImportantPart();

        if ($importantPart->getWidth() <= 0 || $importantPart->getHeight() <= 0) {
            return $image;
        }

        $imageSize = $image->getDimensions()->getSize();

        $config = (new ResizeConfiguration())
            ->setMode(ResizeConfiguration::MODE_CROP)
            ->setWidth((int) ($imageSize->getWidth() * $importantPart->getWidth()))
            ->setHeight((int) ($imageSize->getHeight() * $importantPart->getHeight()))
            ->setZoomLevel(100)
        ;

        return $this->resizer->resize($image, $config, (new ResizeOptions()))
            ->setImportantPart(null)
        ;
    }
}
