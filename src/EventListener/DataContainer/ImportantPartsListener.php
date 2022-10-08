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

use Contao\CoreBundle\ServiceAnnotation\Callback;
use Contao\DataContainer;
use Contao\FilesModel;

class ImportantPartsListener
{
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

        return $value;
    }
}
