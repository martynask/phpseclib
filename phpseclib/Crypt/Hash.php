<?php

/**
 * Pure-PHP implementations of keyed-hash message authentication codes (HMACs) and various cryptographic hashing functions.
 *
 * Basically a wrapper for hash().  Currently supports the following:
 *
 * md2, md5, md5-96, sha1, sha1-96, sha256, sha256-96, sha384, and sha512, sha512-96
 *
 * If {@link \phpseclib\Crypt\Hash::setKey() setKey()} is called, {@link \phpseclib\Crypt\Hash::hash() hash()} will return the HMAC as opposed to
 * the hash.  If no valid algorithm is provided, sha1 will be used.
 *
 * PHP version 5
 *
 * Here's a short example of how to use this library:
 * <code>
 * <?php
 *    include 'vendor/autoload.php';
 *
 *    $hash = new \phpseclib\Crypt\Hash('sha1');
 *
 *    $hash->setKey('abcdefg');
 *
 *    echo base64_encode($hash->hash('abcdefg'));
 * ?>
 * </code>
 *
 * @category  Crypt
 * @package   Hash
 * @author    Jim Wigginton <terrafrost@php.net>
 * @copyright 2007 Jim Wigginton
 * @license   http://www.opensource.org/licenses/mit-license.html  MIT License
 * @link      http://phpseclib.sourceforge.net
 */

namespace phpseclib\Crypt;

/**
 * Pure-PHP implementations of keyed-hash message authentication codes (HMACs) and various cryptographic hashing functions.
 *
 * @package Hash
 * @author  Jim Wigginton <terrafrost@php.net>
 * @access  public
 */
class Hash
{
    /**
     * Hash Parameter
     *
     * @see \phpseclib\Crypt\Hash::setHash()
     * @var Integer
     * @access private
     */
    var $hashParam;

    /**
     * Byte-length of hash output (Internal HMAC)
     *
     * @see \phpseclib\Crypt\Hash::setHash()
     * @var Integer
     * @access private
     */
    var $l = false;

    /**
     * Hash Algorithm
     *
     * @see \phpseclib\Crypt\Hash::setHash()
     * @var String
     * @access private
     */
    var $hash;

    /**
     * Key
     *
     * @see \phpseclib\Crypt\Hash::setKey()
     * @var String
     * @access private
     */
    var $key = false;

    /**
     * Default Constructor.
     *
     * @param optional String $hash
     * @return \phpseclib\Crypt\Hash
     * @access public
     */
    function __construct($hash = 'sha1')
    {
        $this->setHash($hash);
    }

    /**
     * Sets the key for HMACs
     *
     * Keys can be of any length.
     *
     * @access public
     * @param optional String $key
     */
    function setKey($key = false)
    {
        $this->key = $key;
    }

    /**
     * Gets the hash function.
     *
     * As set by the constructor or by the setHash() method.
     *
     * @access public
     * @return String
     */
    function getHash()
    {
        return $this->hashParam;
    }

    /**
     * Sets the hash function.
     *
     * @access public
     * @param String $hash
     */
    function setHash($hash)
    {
        $this->hashParam = $hash = strtolower($hash);
        switch ($hash) {
            case 'md5-96':
            case 'sha1-96':
            case 'sha256-96':
            case 'sha512-96':
                $hash = substr($hash, 0, -3);
                $this->l = 12; // 96 / 8 = 12
                break;
            case 'md2':
            case 'md5':
                $this->l = 16;
                break;
            case 'sha1':
                $this->l = 20;
                break;
            case 'sha256':
                $this->l = 32;
                break;
            case 'sha384':
                $this->l = 48;
                break;
            case 'sha512':
                $this->l = 64;
                break;
            default:
                $hash = 'sha1';
                $this->l = 20;
        }
    }

    /**
     * Compute the HMAC.
     *
     * @access public
     * @param String $text
     * @return String
     */
    function hash($text)
    {
        $output = !empty($this->key) || is_string($this->key) ?
            hash_hmac($this->hash, $text, $this->key, true) :
            hash($this->hash, $text, true);

        return substr($output, 0, $this->l);
    }

    /**
     * Returns the hash length (in bytes)
     *
     * @access public
     * @return Integer
     */
    function getLength()
    {
        return $this->l;
    }
}
