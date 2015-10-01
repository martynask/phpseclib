<?php
/**
 * XML Formatted RSA Key Handler
 *
 * More info:
 *
 * http://www.w3.org/TR/xmldsig-core/#sec-RSAKeyValue
 * http://en.wikipedia.org/wiki/XML_Signature
 *
 * PHP version 5
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
 * XML Formatted RSA Key Handler
 *
 * @package RSA
 * @author  Jim Wigginton <terrafrost@php.net>
 * @access  public
 */
class XML
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
        $components = array(
            'isPublicKey' => false,
            'primes' => array(),
            'exponents' => array(),
            'coefficients' => array()
        );

        $dom = new \DOMDocument();
        if (!$dom->loadXML(strtolower('<xml>' . $key . '</xml>'))) {
            return false;
        }
        $keys = array('modulus', 'exponent', 'p', 'q', 'dp', 'dq', 'inverseq', 'd');
        foreach ($keys as $key) {
            $temp = $dom->getElementsByTagName($key);
            if (!$temp->length) {
                continue;
            }
            $value = new BigInteger(base64_decode($temp->item(0)->nodeValue), 256);
            switch ($key) {
                case 'modulus':
                    $components['modulus'] = $value;
                    break;
                case 'exponent':
                    $components['publicExponent'] = $value;
                    break;
                case 'p':
                    $components['primes'][1] = $value;
                    break;
                case 'q':
                    $components['primes'][2] = $value;
                    break;
                case 'dp':
                    $components['exponents'][1] = $value;
                    break;
                case 'dq':
                    $components['exponents'][2] = $value;
                    break;
                case 'inverseq':
                    $components['coefficients'][2] = $value;
                    break;
                case 'd':
                    $components['privateExponent'] = $value;
            }
        }

        return isset($components['modulus']) && isset($components['publicExponent']) ? $components : false;
    }

    /**
     * Convert a private key to the appropriate format.
     *
     * @access public
     * @param \phpseclib\Math\BigInteger $n
     * @param \phpseclib\Math\BigInteger $e
     * @param \phpseclib\Math\BigInteger $d
     * @param array $primes
     * @param array $exponents
     * @param array $coefficients
     * @param string $password optional
     * @return string
     */
    static function savePrivateKey(BigInteger $n, BigInteger $e, BigInteger $d, $primes, $exponents, $coefficients, $password = '')
    {
        if (count($primes) != 2) {
            return false;
        }
        return "<RSAKeyValue>\r\n" .
               '  <Modulus>' . base64_encode($n->toBytes()) . "</Modulus>\r\n" .
               '  <Exponent>' . base64_encode($e->toBytes()) . "</Exponent>\r\n" .
               '  <P>' . base64_encode($primes[1]->toBytes()) . "</P>\r\n" .
               '  <Q>' . base64_encode($primes[2]->toBytes()) . "</Q>\r\n" .
               '  <DP>' . base64_encode($exponents[1]->toBytes()) . "</DP>\r\n" .
               '  <DQ>' . base64_encode($exponents[2]->toBytes()) . "</DQ>\r\n" .
               '  <InverseQ>' . base64_encode($coefficients[2]->toBytes()) . "</InverseQ>\r\n" .
               '  <D>' . base64_encode($d->toBytes()) . "</D>\r\n" .
               '</RSAKeyValue>';
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
echo "\n\n\n" . base64_encode($n->toBytes()) . "\n\n\n";
        return "<RSAKeyValue>\r\n" .
               '  <Modulus>' . base64_encode($n->toBytes()) . "</Modulus>\r\n" .
               '  <Exponent>' . base64_encode($e->toBytes()) . "</Exponent>\r\n" .
               '</RSAKeyValue>';
    }
}