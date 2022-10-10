<?php

declare(strict_types=1);

/*
 * This file is part of the Contao Image Alternatives extension.
 *
 * (c) inspiredminds
 *
 * @license LGPL-3.0-or-later
 */

namespace InspiredMinds\ContaoImageAlternatives\EventListener\DataContainer;

use Contao\Config;
use Contao\CoreBundle\DataContainer\PaletteManipulator;
use Contao\CoreBundle\ServiceAnnotation\Callback;
use Contao\DataContainer;
use Contao\Dbafs;
use Contao\FilesModel;
use Symfony\Component\HttpFoundation\RequestStack;
use Webmozart\PathUtil\Path;

class ImportantPartsListener
{
    private $requestStack;
    private $projectDir;

    public function __construct(RequestStack $requestStack, string $projectDir)
    {
        $this->requestStack = $requestStack;
        $this->projectDir = $projectDir;
    }

    /**
     * @Callback(table="tl_files", target="config.onload")
     */
    public function adjustPalettes(DataContainer $dc): void
    {
        $request = $this->requestStack->getCurrentRequest();

        if ('edit' !== $request->query->get('act')) {
            return;
        }

        if (!file_exists(Path::join($this->projectDir, $dc->id))) {
            return;
        }

        $file = Dbafs::addResource($dc->id);

        if (null === $file) {
            return;
        }

        if ('file' !== $file->type || !\in_array($file->extension, explode(',', Config::get('validImageTypes')), true)) {
            return;
        }

        PaletteManipulator::create()
            ->addField('importantParts', 'importantPartHeight')
            ->applyToPalette('default', 'tl_files')
        ;
    }

    /**
     * @Callback(table="tl_files", target="fields.importantParts.load")
     */
    public function importantPartsLoadCallback($value, DataContainer $dc): string
    {
        $importantParts = (!empty($value) ? json_decode($value, true) : []) ?: [];

        $file = FilesModel::findByPath($dc->id);

        if ($file->importantPartWidth > 0 && $file->importantPartHeight > 0) {
            $importantParts['default'] = [
                'x' => $file->importantPartX,
                'y' => $file->importantPartY,
                'width' => $file->importantPartWidth,
                'height' => $file->importantPartHeight,
            ];
        }

        return json_encode((object) $importantParts, \JSON_PRETTY_PRINT);
    }

    /**
     * @Callback(table="tl_files", target="fields.importantParts.save")
     */
    public function importantPartsSaveCallback($value, DataContainer $dc): string
    {
        $importantParts = (!empty($value) ? json_decode($value, true) : []) ?: [];

        $file = FilesModel::findByPath($dc->id);

        $file->importantPartX = $importantParts['default']['x'] ?? 0;
        $file->importantPartY = $importantParts['default']['y'] ?? 0;
        $file->importantPartWidth = $importantParts['default']['width'] ?? 0;
        $file->importantPartHeight = $importantParts['default']['height'] ?? 0;

        $file->save();

        // Remove any invalid entries
        $importantParts = array_filter($importantParts, function (array $importantPart): bool {
            return (float) $importantPart['width'] > 0 && (float) $importantPart['height'] > 0;
        });

        // "compress" JSON
        return json_encode((object) $importantParts);
    }
}
