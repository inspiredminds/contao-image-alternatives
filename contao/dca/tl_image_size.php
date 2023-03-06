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

$GLOBALS['TL_DCA']['tl_image_size']['fields']['preCrop'] = [
    'exclude' => true,
    'inputType' => 'checkbox',
    'eval' => ['tl_class' => 'w50'],
    'sql' => ['type' => 'boolean', 'default' => false],
];

PaletteManipulator::create()
    ->addField('preCrop', 'expert_legend', PaletteManipulator::POSITION_APPEND)
    ->applyToPalette('default', 'tl_image_size')
;
