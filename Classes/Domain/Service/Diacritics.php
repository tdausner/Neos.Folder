<?php

namespace Neos\Folder\Domain\Service;
/*
 * This file is part of the Neos.Neos.Ui package.
 *
 * (c) Contributors of the Neos Project - www.neos.io
 *
 * This package is Open Source Software. For the full copyright and license
 * information, please view the LICENSE file which was distributed with this
 * source code.
 */

/**
 * Diacritics (umlaut) and greek character conversion to ascii, diacritics strlen etc.
 *
 * @package "Neos.Folder"
 * @api
 */
abstract class Diacritics
{
    /**
     * Upper case [ diacritics, ascii ]
     */
    final protected const UPPER_CASE = [
        'diacritics' => [
            'Â', 'À', 'Α', 'Å', 'Ã', 'Ä', 'Β', 'Ç', 'Χ', 'Δ', 'É', 'Ê', 'È', 'Ε', 'Η', 'Ð', 'Ë', 'Γ', 'Í', 'Î', 'Ì', 'Ι',
            'Ï', 'Κ', 'Λ', 'Μ', 'Ñ', 'Ν', 'Ó', 'Ô', 'Ò', 'Ω', 'Ο', 'Ø', 'Õ', 'Ö', 'Φ', 'Π', 'Ψ', 'Ρ', 'Š', 'Σ', 'Τ', 'Θ',
            'Þ', 'Ú', 'Û', 'Ù', 'ϒ', 'Υ', 'Ü', 'Ξ', 'Ý', 'Ÿ', 'Ζ'
        ],
        'ascii' => [
            'A', 'A', 'A', 'A', 'A', 'Ae', 'B', 'C', 'C', 'D', 'E', 'E', 'E', 'E', 'E', 'E', 'E', 'G', 'I', 'I', 'I', 'I',
            'I', 'K', 'L', 'Mu', 'N', 'Nu', 'O', 'O', 'O', 'O', 'O', 'O', 'O', 'Oe', 'P', 'P', 'Ps', 'R', 'S', 'S', 'T', 'Th',
            'Th', 'U', 'U', 'U', 'U', 'U', 'Ue', 'Xi', 'Y', 'Y', 'Z'
        ]
    ];

    /**
     * Lower case [ diacritics, ascii ]
     */
    final protected const LOWER_CASE = [
        'diacritics' => [
            'â', 'à', 'α', 'å', 'ã', 'ä', 'β', 'ç', 'χ', 'δ', 'é', 'ê', 'è', 'ε', 'η', 'ð', 'ë', 'γ', 'í', 'î', 'ì', 'ι',
            'ï', 'κ', 'λ', 'μ', 'ñ', 'ν', 'ó', 'ô', 'ò', 'ω', 'ο', 'ø', 'õ', 'ö', 'φ', 'π', 'ψ', 'ρ', 'š', 'σ', 'τ', 'θ',
            'þ', 'ú', 'û', 'ù', 'υ', 'ü', '℘', 'ξ', 'ý', 'ÿ', 'ζ', 'ß'
        ],
        'ascii' => [
            'a', 'a', 'a', 'a', 'a', 'ae', 'b', 'c', 'c', 'd', 'e', 'e', 'e', 'E', 'e', 'e', 'e', 'g', 'i', 'i', 'i', 'i',
            'i', 'k', 'l', 'mu', 'n', 'nu', 'o', 'o', 'o', 'o', 'o', 'o', 'o', 'oe', 'p', 'p', 'ps', 'r', 's', 's', 't', 'th',
            'th', 'u', 'u', 'u', 'u', 'ue', 'p', 'xi', 'y', 'y', 'z', 'ss'
        ]
    ];

    /**
     * Convert diacritics input  to ascii
     *
     * @param string $input
     *
     * @return string ascii result
     * @api
     */
    public static function toAscii(string $input): string
    {
        $temp = str_replace(self::UPPER_CASE['diacritics'], self::UPPER_CASE['ascii'], $input);
        return str_replace(self::LOWER_CASE['diacritics'], self::LOWER_CASE['ascii'], $temp);
    }

    /**
     * Convert diacritics to lower case ascii and non "word" characters (except slash) to dash
     *
     * @param string $input
     *
     * @return string converted path having lower case, digit and dash characters
     * @api
     */
    public static function path(string $input): string
    {
        return preg_replace('/[^\w\/]/', '-', strtolower(self::toAscii($input)));
    }

    /**
     * strlen with respect to diacritics
     *
     * @param string $input
     *
     * @return int length of string with respect to diacritics
     * @api
     */
    public static function strlen(string $input): int
    {
        $temp = str_replace(self::UPPER_CASE['diacritics'], '-', $input);
        return strlen(str_replace(self::LOWER_CASE['diacritics'], '-', $temp));
    }

    /**
     * str_pad with respect to diacritics
     *
     * @param string $input
     * @param int $length
     * @param string $padString
     * @param int $padType
     *
     * @return string padded string with respect to diacritics
     * @api
     */
    public static function str_pad(string $input, int $length, string $padString = ' ', int $padType = STR_PAD_RIGHT): string
    {
        $diff = strlen($input) - self::strlen($input);
        return str_pad($input, $length + $diff, $padString, $padType);
    }
}