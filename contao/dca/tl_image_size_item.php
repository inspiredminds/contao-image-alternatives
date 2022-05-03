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

$GLOBALS['TL_DCA']['tl_image_size_item']['fields']['alternative'] = [
    'inputType' => 'select',
    'eval' => ['includeBlankOption' => true, 'tl_class' => 'w50'],
    'sql' => ['type' => 'string', 'length' => 32, 'default' => ''],
];

PaletteManipulator::create()
    ->addField('alternative', 'source_legend', PaletteManipulator::POSITION_APPEND)
    ->applyToPalette('default', 'tl_image_size_item')
;
