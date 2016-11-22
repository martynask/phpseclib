<?php

/**
 * PKCS#8 Formatted Key Handler
 *
 * PHP version 5
 *
 * Used by PHP's openssl_public_encrypt() and openssl's rsautl (when -pubin is set)
 *
 * Processes keys with the following headers:
 *
 * -----BEGIN ENCRYPTED PRIVATE KEY-----
 * -----BEGIN PRIVATE KEY-----
 * -----BEGIN PUBLIC KEY-----
 *
 * Analogous to ssh-keygen's pkcs8 format (as specified by -m). Although PKCS8
 * is specific to private keys it's basically creating a DER-encoded wrapper
 * for keys. This just extends that same concept to public keys (much like ssh-keygen)
 *
 * @category  Crypt
 * @package   Common
 * @author    Jim Wigginton <terrafrost@php.net>
 * @copyright 2015 Jim Wigginton
 * @license   http://www.opensource.org/licenses/mit-license.html  MIT License
 * @link      http://phpseclib.sourceforge.net
 */

namespace phpseclib\Crypt\Common;

use ParagonIE\ConstantTime\Base64;
use phpseclib\Crypt\DES;
use phpseclib\Crypt\RC2;
use phpseclib\Crypt\RC4;
use phpseclib\Crypt\AES;
use phpseclib\Crypt\TripleDES;
use phpseclib\Crypt\Common\BlockCipher;
use phpseclib\Crypt\Random;
use phpseclib\Math\BigInteger;
use phpseclib\File\ASN1;
use phpseclib\Exception\UnsupportedAlgorithmException;

// from https://tools.ietf.org/html/rfc2898
define(__NAMESPACE__ . '\oids', [
    // PBES1 encryption schemes
    '1.2.840.113549.1.5.1' => 'pbeWithMD2AndDES-CBC',
    '1.2.840.113549.1.5.4' => 'pbeWithMD2AndRC2-CBC',
    '1.2.840.113549.1.5.3' => 'pbeWithMD5AndDES-CBC',
    '1.2.840.113549.1.5.6' => 'pbeWithMD5AndRC2-CBC',
    '1.2.840.113549.1.5.10'=> 'pbeWithSHA1AndDES-CBC',
    '1.2.840.113549.1.5.11'=> 'pbeWithSHA1AndRC2-CBC',

    // from PKCS#12:
    // https://tools.ietf.org/html/rfc7292
    '1.2.840.113549.1.12.1.1' => 'pbeWithSHAAnd128BitRC4',
    '1.2.840.113549.1.12.1.2' => 'pbeWithSHAAnd40BitRC4',
    '1.2.840.113549.1.12.1.3' => 'pbeWithSHAAnd3-KeyTripleDES-CBC',
    '1.2.840.113549.1.12.1.4' => 'pbeWithSHAAnd2-KeyTripleDES-CBC',
    '1.2.840.113549.1.12.1.5' => 'pbeWithSHAAnd128BitRC2-CBC',
    '1.2.840.113549.1.12.1.6' => 'pbeWithSHAAnd40BitRC2-CBC',

    '1.2.840.113549.1.5.12' => 'id-PBKDF2',
    '1.2.840.113549.1.5.13' => 'id-PBES2',
    '1.2.840.113549.1.5.14' => 'id-PBMAC1',

    // from PKCS#5 v2.1:
    // http://www.rsa.com/rsalabs/pkcs/files/h11302-wp-pkcs5v2-1-password-based-cryptography-standard.pdf
    '1.2.840.113549.2.7' => 'id-hmacWithSHA1',
    '1.2.840.113549.2.8' => 'id-hmacWithSHA224',
    '1.2.840.113549.2.9' => 'id-hmacWithSHA256',
    '1.2.840.113549.2.10'=> 'id-hmacWithSHA384',
    '1.2.840.113549.2.11'=> 'id-hmacWithSHA512',
    '1.2.840.113549.2.12'=> 'id-hmacWithSHA512-224',
    '1.2.840.113549.2.13'=> 'id-hmacWithSHA512-256',

    '1.3.14.3.2.7'       => 'desCBC',
    '1.2.840.113549.3.7' => 'des-EDE3-CBC',
    '1.2.840.113549.3.2' => 'rc2CBC',
    '1.2.840.113549.3.9' => 'rc5-CBC-PAD',

    '2.16.840.1.101.3.4.1.2' => 'aes128-CBC-PAD',
    '2.16.840.1.101.3.4.1.22'=> 'aes192-CBC-PAD',
    '2.16.840.1.101.3.4.1.42'=> 'aes256-CBC-PAD'
]);

/**
 * PKCS#8 Formatted Key Handler
 *
 * @package Common
 * @author  Jim Wigginton <terrafrost@php.net>
 * @access  public
 */
class PKCS8 extends PKCS
{
    /**
     * Default encryption algorithm
     *
     * @var string
     * @access private
     */
    static $defaultEncryptionAlgorithm = 'id-PBES2';

    /**
     * Default encryption scheme
     *
     * Only used when defaultEncryptionAlgorithm is id-PBES2
     *
     * @var string
     * @access private
     */
    static $defaultEncryptionScheme = 'aes128-CBC-PAD';

    /**
     * Default PRF
     *
     * Only used when defaultEncryptionAlgorithm is id-PBES2
     *
     * @var string
     * @access private
     */
    static $defaultPRF = 'id-hmacWithSHA256';

    /**
     * Default Iteration Count
     *
     * @var int
     * @access private
     */
    static $defaultIterationCount = 2048;

    /**
     * Sets the default encryption algorithm
     *
     * @access public
     * @param string $algo
     */
    static function setEncryptionAlgorithm($algo)
    {
        self::$defaultEncryptionAlgorithm = $algo;
    }

    /**
     * Sets the default encryption algorithm for PBES2
     *
     * @access public
     * @param string $algo
     */
    static function setEncryptionScheme($algo)
    {
        self::$defaultEncryptionScheme = $algo;
    }

    /**
     * Sets the iteration count
     *
     * @access public
     * @param int $count
     */
    static function setIterationCount($count)
    {
        self::$defaultIterationCount = $count;
    }

    /**
     * Sets the PRF for PBES2
     *
     * @access public
     * @param string $algo
     */
    static function setPRF($algo)
    {
        self::$defaultPRF = $algo;
    }

    /**
     * Returns a SymmetricKey object based on a PBES1 $algo
     *
     * @access public
     * @param string $algo
     */
    static function getPBES1EncryptionObject($algo)
    {
        $algo = preg_match('#^pbeWith(?:MD2|MD5|SHA1|SHA)And(.*?)-CBC$#', $algo, $matches) ?
            $matches[1] :
            substr($algo, 13); // strlen('pbeWithSHAAnd') == 13

        switch ($algo) {
            case 'DES':
                $cipher = new DES(BlockCipher::MODE_CBC);
                break;
            case 'RC2':
                $cipher = new RC2(BlockCipher::MODE_CBC);
                break;
            case '3-KeyTripleDES':
                $cipher = new TripleDES(BlockCipher::MODE_CBC);
                break;
            case '2-KeyTripleDES':
                $cipher = new TripleDES(BlockCipher::MODE_CBC);
                $cipher->setKeyLength(128);
                break;
            case '128BitRC2':
                $cipher = new RC2(BlockCipher::MODE_CBC);
                $cipher->setKeyLength(128);
                break;
            case '40BitRC2':
                $cipher = new RC2(BlockCipher::MODE_CBC);
                $cipher->setKeyLength(40);
                break;
            case '128BitRC4':
                $cipher = new RC4();
                $cipher->setKeyLength(128);
                break;
            case '40BitRC4':
                $cipher = new RC4();
                $cipher->setKeyLength(40);
                break;
            default:
                throw new UnsupportedAlgorithmException("$algo is not a supported algorithm");
        }

        return $cipher;
    }

    /**
     * Returns a hash based on a PBES1 $algo
     *
     * @access public
     * @param string $algo
     */
    static function getPBES1Hash($algo)
    {
        if (preg_match('#^pbeWith(MD2|MD5|SHA1|SHA)And.*?-CBC$#', $algo, $matches)) {
            return $matches[1] == 'SHA' ? 'sha1' : $matches[1];
        }

        return 'sha1';
    }

    /**
     * Returns a KDF baesd on a PBES1 $algo
     *
     * @access public
     * @param string $algo
     */
    static function getPBES1KDF($algo)
    {
        switch ($algo) {
            case 'pbeWithMD2AndDES-CBC':
            case 'pbeWithMD2AndRC2-CBC':
            case 'pbeWithMD5AndDES-CBC':
            case 'pbeWithMD5AndRC2-CBC':
            case 'pbeWithSHA1AndDES-CBC':
            case 'pbeWithSHA1AndRC2-CBC':
                return 'pbkdf1';
        }

        return 'pkcs12';
    }

    /**
     * Returns a SymmetricKey object baesd on a PBES2 $algo
     *
     * @access public
     * @param string $algo
     */
    static function getPBES2EncryptionObject($algo)
    {
        switch ($algo) {
            case 'desCBC':
                $cipher = new TripleDES(BlockCipher::MODE_CBC);
                break;
            case 'des-EDE3-CBC':
                $cipher = new TripleDES(BlockCipher::MODE_CBC);
                break;
            case 'rc2CBC':
                $cipher = new RC2(BlockCipher::MODE_CBC);
                // in theory this can be changed
                $cipher->setKeyLength(128);
                break;
            case 'rc5-CBC-PAD':
                throw new UnsupportedAlgorithmException('rc5-CBC-PAD is not supported for PBES2 PKCS#8 keys');
            case 'aes128-CBC-PAD':
            case 'aes192-CBC-PAD':
            case 'aes256-CBC-PAD':
                $cipher = new AES(BlockCipher::MODE_CBC);
                $cipher->setKeyLength(substr($algo, 3, 3));
                break;
            default:
                throw new UnsupportedAlgorithmException("$algo is not supported");
        }

        return $cipher;
    }

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
        if (!is_string($key)) {
            return false;
        }

        if (self::$format != self::MODE_DER) {
            $decoded = ASN1::extractBER($key);
            if ($decoded !== false) {
                $key = $decoded;
            } elseif (self::$format == self::MODE_PEM) {
                return false;
            }
        }

        $asn1 = new ASN1();
        $decoded = $asn1->decodeBER($key);
        if (empty($decoded)) {
            return false;
        }

        $meta = [];

        $asn1->loadOIDs(oids);
        $decrypted = $asn1->asn1map($decoded[0], ASN1\EncryptedPrivateKeyInfo::MAP);
        if (strlen($password) && is_array($decrypted)) {
            $algorithm = $decrypted['encryptionAlgorithm']['algorithm'];
            switch ($algorithm) {
                // PBES1
                case 'pbeWithMD2AndDES-CBC':
                case 'pbeWithMD2AndRC2-CBC':
                case 'pbeWithMD5AndDES-CBC':
                case 'pbeWithMD5AndRC2-CBC':
                case 'pbeWithSHA1AndDES-CBC':
                case 'pbeWithSHA1AndRC2-CBC':
                case 'pbeWithSHAAnd3-KeyTripleDES-CBC':
                case 'pbeWithSHAAnd2-KeyTripleDES-CBC':
                case 'pbeWithSHAAnd128BitRC2-CBC':
                case 'pbeWithSHAAnd40BitRC2-CBC':
                case 'pbeWithSHAAnd128BitRC4':
                case 'pbeWithSHAAnd40BitRC4':
                    $cipher = self::getPBES1EncryptionObject($algorithm);
                    $hash = self::getPBES1Hash($algorithm);
                    $kdf = self::getPBES1KDF($algorithm);

                    $meta['meta']['algorithm'] = $algorithm;

                    $temp = $asn1->decodeBER($decrypted['encryptionAlgorithm']['parameters']);
                    extract($asn1->asn1map($temp[0], ASN1\PBEParameter::MAP));
                    $iterationCount = (int) $iterationCount->toString();
                    $cipher->setPassword($password, $kdf, $hash, Base64::decode($salt), $iterationCount);
                    $key = $cipher->decrypt(Base64::decode($decrypted['encryptedData']));
                    $decoded = $asn1->decodeBER($key);
                    if (empty($decoded)) {
                        return false;
                    }

                    break;
                case 'id-PBES2':
                    $meta['meta']['algorithm'] = $algorithm;

                    $temp = $asn1->decodeBER($decrypted['encryptionAlgorithm']['parameters']);
                    $temp = $asn1->asn1map($temp[0], ASN1\PBES2params::MAP);
                    extract($temp);

                    $cipher = self::getPBES2EncryptionObject($encryptionScheme['algorithm']);
                    $meta['meta']['cipher'] = $encryptionScheme['algorithm'];

                    $temp = $asn1->decodeBER($decrypted['encryptionAlgorithm']['parameters']);
                    $temp = $asn1->asn1map($temp[0], ASN1\PBES2params::MAP);
                    extract($temp);

                    if (!$cipher instanceof RC2) {
                        $cipher->setIV(Base64::decode($encryptionScheme['parameters']['octetString']));
                    } else {
                        $temp = $asn1->decodeBER($encryptionScheme['parameters']);
                        extract($asn1->asn1map($temp[0], ASN1\RC2CBCParameter::MAP));
                        $effectiveKeyLength = (int) $rc2ParametersVersion->toString();
                        switch ($effectiveKeyLength) {
                            case 160:
                                $effectiveKeyLength = 40;
                                break;
                            case 120:
                                $effectiveKeyLength = 64;
                                break;
                            case 58:
                                $effectiveKeyLength = 128;
                                break;
                            //default: // should be >= 256
                        }
                        $cipher->setIV(Base64::decode($iv));
                        $cipher->setKeyLength($effectiveKeyLength);
                    }

                    $meta['meta']['keyDerivationFunc'] = $keyDerivationFunc['algorithm'];
                    switch ($keyDerivationFunc['algorithm']) {
                        case 'id-PBKDF2':
                            $temp = $asn1->decodeBER($keyDerivationFunc['parameters']);
                            $prf = ['algorithm' => 'id-hmacWithSHA1'];
                            $params = $asn1->asn1map($temp[0], ASN1\PBKDF2params::MAP);
                            extract($params);
                            $meta['meta']['prf'] = $prf['algorithm'];
                            $hash = str_replace('-', '/', substr($prf['algorithm'], 11));
                            $params = [
                                $password,
                                'pbkdf2',
                                $hash,
                                Base64::decode($salt),
                                (int) $iterationCount->toString()
                            ];
                            if (isset($keyLength)) {
                                $params[] = (int) $keyLength->toString();
                            }
                            call_user_func_array([$cipher, 'setPassword'], $params);
                            $key = $cipher->decrypt(Base64::decode($decrypted['encryptedData']));
                            $decoded = $asn1->decodeBER($key);
                            if (empty($decoded)) {
                                return false;
                            }
                            break;
                        default:
                            throw new UnsupportedAlgorithmException('Only PBKDF2 is supported for PBES2 PKCS#8 keys');
                    }
                    break;
                case 'id-PBMAC1':
                    //$temp = $asn1->decodeBER($decrypted['encryptionAlgorithm']['parameters']);
                    //$value = $asn1->asn1map($temp[0], ASN1\PBMAC1params::MAP);
                    // since i can't find any implementation that does PBMAC1 it is unsupported
                    throw new UnsupportedAlgorithmException('Only PBES1 and PBES2 PKCS#8 keys are supported.');
                // at this point we'll assume that the key conforms to PublicKeyInfo
            }
        }

        $private = $asn1->asn1map($decoded[0], ASN1\PrivateKeyInfo::MAP);
        if (is_array($private)) {
            return $private + $meta;
        }

        // EncryptedPrivateKeyInfo and PublicKeyInfo have largely identical "signatures". the only difference
        // is that the former has an octet string and the later has a bit string. the first byte of a bit
        // string represents the number of bits in the last byte that are to be ignored but, currently,
        // bit strings wanting a non-zero amount of bits trimmed are not supported
        $public = $asn1->asn1map($decoded[0], ASN1\PublicKeyInfo::MAP);
        if (is_array($public)) {
            $public['publicKey'] = base64_decode($public['publicKey']);
            if ($public['publicKey'][0] != "\0") {
                return false;
            }
            $public['publicKey'] = base64_encode(substr($public['publicKey'], 1));
            return $public;
        }

        return false;
    }

    /**
     * Wrap a private key appropriately
     *
     * @access public
     * @param string $algorithm
     * @param string $key
     * @param string $attr
     * @param string $password
     * @return string
     */
    static function wrapPrivateKey($key, $algorithm, $attr, $password)
    {
        $asn1 = new ASN1();
        $asn1->loadOIDs(oids);

        $key = [
            'version' => 'v1',
            'privateKeyAlgorithm' => ['algorithm' => $algorithm], // parameters are not currently supported
            'privateKey' => Base64::encode($key)
        ];
        if (!empty($attr)) {
            $key['attributes'] = $attr;
        }
        $key = $asn1->encodeDER($key, ASN1\PrivateKeyInfo::MAP);
        if (!empty($password) && is_string($password)) {
            $salt = Random::string(8);
            $iterationCount = self::$defaultIterationCount;

            if (self::$defaultEncryptionAlgorithm == 'id-PBES2') {
                $crypto = self::getPBES2EncryptionObject(self::$defaultEncryptionScheme);
                $hash = str_replace('-', '/', substr(self::$defaultPRF, 11));
                $kdf = 'pbkdf2';
                $iv = Random::string($crypto->getBlockLength() >> 3);

                $PBKDF2params = [
                    'salt' => Base64::encode($salt),
                    'iterationCount' => $iterationCount,
                    'prf' => ['algorithm' => self::$defaultPRF, 'parameters' => null]
                ];
                $PBKDF2params = $asn1->encodeDER($PBKDF2params, ASN1\PBKDF2params::MAP);

                if (!$crypto instanceof RC2) {
                    $params = ['octetString' => Base64::encode($iv)];
                } else {
                    $params = [
                        'rc2ParametersVersion' => 58,
                        'iv' => Base64::encode($iv)
                    ];
                    $params = $asn1->encodeDER($params, ASN1\RC2CBCParameter::MAP);
                    $params = new ASN1\Element($params);
                }

                $params = [
                    'keyDerivationFunc' => [
                        'algorithm' => 'id-PBKDF2',
                        'parameters' => new ASN1\Element($PBKDF2params)
                    ],
                    'encryptionScheme' => [
                        'algorithm' => self::$defaultEncryptionScheme,
                        'parameters' => $params
                    ]
                ];
                $params = $asn1->encodeDER($params, ASN1\PBES2params::MAP);

                $crypto->setIV($iv);
            } else {
                $crypto = self::getPBES1EncryptionObject(self::$defaultEncryptionAlgorithm);
                $hash = self::getPBES1Hash(self::$defaultEncryptionAlgorithm);
                $kdf = self::getPBES1KDF(self::$defaultEncryptionAlgorithm);

                $params = [
                    'salt' => Base64::encode($salt),
                    'iterationCount' => $iterationCount
                ];
                $params = $asn1->encodeDER($params, ASN1\PBEParameter::MAP);
            }
            $crypto->setPassword($password, $kdf, $hash, $salt, $iterationCount);
            $key = $crypto->encrypt($key);

            $key = [
                'encryptionAlgorithm' => [
                    'algorithm' => self::$defaultEncryptionAlgorithm,
                    'parameters' => new ASN1\Element($params)
                ],
                'encryptedData' => Base64::encode($key)
            ];

            $key = $asn1->encodeDER($key, ASN1\EncryptedPrivateKeyInfo::MAP);

            return "-----BEGIN ENCRYPTED PRIVATE KEY-----\r\n" .
                   chunk_split(Base64::encode($key), 64) .
                   "-----END ENCRYPTED PRIVATE KEY-----";
        }

        return "-----BEGIN PRIVATE KEY-----\r\n" .
               chunk_split(Base64::encode($key), 64) .
               "-----END PRIVATE KEY-----";
    }

    /**
     * Wrap a public key appropriately
     *
     * @access public
     * @param string $key
     * @return string
     */
    static function wrapPublicKey($key, $algorithm)
    {
        $asn1 = new ASN1();

        $key = [
            'publicKeyAlgorithm' => [
                'algorithm' => $algorithm,
                'parameters' => null // parameters are not currently supported
            ],
            'publicKey' => Base64::encode("\0" . $key)
        ];

        $key = $asn1->encodeDER($key, ASN1\PublicKeyInfo::MAP);

        return "-----BEGIN PUBLIC KEY-----\r\n" .
               chunk_split(Base64::encode($key), 64) .
               "-----END PUBLIC KEY-----";
    }
}
