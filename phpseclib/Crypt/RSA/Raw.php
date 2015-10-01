<?php
/**
 * Raw RSA Key Handler
 *
 * PHP version 5
 *
 * An array containing two \phpseclib\Math\BigInteger objects.
 *
 * The exponent can be indexed with any of the following:
 *
 * 0, e, exponent, publicExponent
 *
 * The modulus can be indexed with any of the following:
 *
 * 1, n, modulo, modulus
 *
 * @category  Crypt
 * @package   RSA
 * @author    Jim Wigginton <terrafrost@php.net>
 * @copyright 2015 Jim Wigginton
 * @license   http://www.opensource.org/licenses/mit-license.html  MIT License
 * @link      http://phpseclib.sourceforge.net
 */

namespace phpseclib\Crypt\RSA;

use phpseclib\Math\BigInteger;

/**
 * Raw RSA Key Handler
 *
 * @package RSA
 * @author  Jim Wigginton <terrafrost@php.net>
 * @access  public
 */
class Raw
{
    /**
     * Break a public or private key down into its constituent components
     *
     * @access public
     * @param string $key
     * @param string $password optional
     * @return array
     */
    static function load($key, $password = '')
    {
        if (!is_array($key)) {
            return false;
        }
        $components = array('isPublicKey' => true);
        switch (true) {
            case isset($key['e']):
                $components['publicExponent'] = $key['e']->copy();
                break;
            case isset($key['exponent']):
                $components['publicExponent'] = $key['exponent']->copy();
                break;
            case isset($key['publicExponent']):
                $components['publicExponent'] = $key['publicExponent']->copy();
                break;
            case isset($key[0]):
                $components['publicExponent'] = $key[0]->copy();
        }
        switch (true) {
            case isset($key['n']):
                $components['modulus'] = $key['n']->copy();
                break;
            case isset($key['modulo']):
                $components['modulus'] = $key['modulo']->copy();
                break;
            case isset($key['modulus']):
                $components['modulus'] = $key['modulus']->copy();
                break;
            case isset($key[1]):
                $components['modulus'] = $key[1]->copy();
        }
        return isset($components['modulus']) && isset($components['publicExponent']) ? $components : false;
    }

    /**
     * Convert a public key to the appropriate format
     *
     * @access public
     * @param \phpseclib\Math\BigInteger $n
     * @param \phpseclib\Math\BigInteger $e
     * @return string
     */
    static function savePublicKey(BigInteger $n, BigInteger $e)
    {
        return array('e' => clone $e, 'n' => clone $n);
    }
}