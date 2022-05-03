<?php

declare(strict_types=1);

/*
 * This file is part of the Contao Image Alternatives extension.
 *
 * (c) inspiredminds
 *
 * @license LGPL-3.0-or-later
 */

$GLOBALS['TL_DCA']['tl_files']['fields']['alternatives'] = [
    'sql' => ['type' => 'blob', 'notnull' => false, 'length' => 65535], // MySqlPlatform::LENGTH_LIMIT_BLOB
];
