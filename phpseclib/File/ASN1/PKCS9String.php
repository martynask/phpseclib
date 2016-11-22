<?php

/**
 * PKCS9String 
 *
 * PHP version 5
 *
 * @category  File
 * @package   ASN1
 * @author    Jim Wigginton <terrafrost@php.net>
 * @copyright 2016 Jim Wigginton
 * @license   http://www.opensource.org/licenses/mit-license.html  MIT License
 * @link      http://phpseclib.sourceforge.net
 */

namespace phpseclib\File\ASN1;

use phpseclib\File\ASN1;

/**
 * PKCS9String 
 *
 * @package ASN1
 * @author  Jim Wigginton <terrafrost@php.net>
 * @access  public
 */
class PKCS9String 
{
    const MAP = [
        'type'     => ASN1::TYPE_CHOICE,
        'children' => [
            'ia5String'       => ['type' => ASN1::TYPE_IA5_STRING],
            'directoryString' => DirectoryString::MAP
        ]
    ];
}
