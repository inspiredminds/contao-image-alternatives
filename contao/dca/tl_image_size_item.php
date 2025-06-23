<?php

declare(strict_types=1);

/*
 * (c) INSPIRED MINDS
 */

use Contao\CoreBundle\DataContainer\PaletteManipulator;

$GLOBALS['TL_DCA']['tl_image_size_item']['fields']['alternative'] = [
    'exclude' => true,
    'inputType' => 'select',
    'eval' => ['includeBlankOption' => true, 'tl_class' => 'w50'],
    'sql' => ['type' => 'string', 'length' => 32, 'default' => ''],
];

PaletteManipulator::create()
    ->addField('alternative', 'source_legend', PaletteManipulator::POSITION_APPEND)
    ->applyToPalette('default', 'tl_image_size_item')
;
