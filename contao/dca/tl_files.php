<?php

declare(strict_types=1);

/*
 * This file is part of the Contao Image Alternatives extension.
 *
 * (c) inspiredminds
 *
 * @license LGPL-3.0-or-later
 */

use Contao\CoreBundle\DataContainer\PaletteManipulator;
use InspiredMinds\ContaoImageAlternatives\DataContainer\FolderDriver;

$GLOBALS['TL_DCA']['tl_files']['config']['dataContainer'] = FolderDriver::class;

$GLOBALS['TL_DCA']['tl_files']['fields']['importantParts'] = [
    'inputType' => 'textarea',
    'eval' => ['tl_class' => 'clr', 'decodeEntities' => true],
    'sql' => ['type' => 'blob', 'notnull' => false, 'length' => 65535], // MySqlPlatform::LENGTH_LIMIT_BLOB
];

$GLOBALS['TL_DCA']['tl_files']['fields']['alternatives'] = [
    'sql' => ['type' => 'blob', 'notnull' => false, 'length' => 65535], // MySqlPlatform::LENGTH_LIMIT_BLOB
];

PaletteManipulator::create()
    ->removeField('importantPartX')
    ->removeField('importantPartY')
    ->removeField('importantPartWidth')
    ->removeField('importantPartHeight')
    ->applyToPalette('default', 'tl_files')
;
