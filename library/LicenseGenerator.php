<?php
/**
 * This class generates a new license key.
 * 
 * Giving the following information:
 * <ul>
 * <li>Key Manager (see KeyManager)</li>
 * <li>Register To</li> 
 * <li>Version</li> 
 * <li>Expires</li> 
 * <li>User limits</li> 
 * <li>SKU</li> 
 * </ul>
 * 
 * Generate a string license that includes:
 * <ul>
 * <li>Data</li>
 * <li>Signature</li> 
 * <li>Public Key</li> 
 * </ul>
 * 
 * @author Roy, 2011
 */
class LicenseGenerator
{

    /**
     * @var used to separate tokens in the license
     */
    const SEPERATOR = ';';

    /**
     * @var Used to reprent a perpatual license
     */
    const NEVER = '00/00/0000';
    
    /**
     * @var List of SKUs
     */
    const SKU_STANDARD = '1';
    const SKU_IBM = '2';
        
    private $_keyManager;
    private $_register;
    private $_version;
    private $_expires;
    private $_users;
    private $_sku;
    
    function __construct (KeysManager $keyManager, $register, $version, 
                         $expires = LicenseGenerator::NEVER, $users = '1', 
                         $sku = LicenseGenerator::SKU_STANDARD)
    {
        $this->validateArguments($keyManager, $register, $version, $expires, 
        $users, $sku);
        $this->_keyManager = $keyManager;
        $this->_register = $register;
        $this->_version = $version;
        $this->_expires = $expires;
        $this->_users = $users;
        $this->_sku = $sku;
    }

    /**
     * @param privateKey
     * @param register
     * @param version
     * @param expires
     * @param users
     * @param sku
     */
    private function validateArguments ($keyManager, $register, $version, $expires, $users, $sku)
    {
        if (empty($keyManager) || empty($register) || empty($version) ||
             empty($expires) || empty($users) || empty($sku)) {
            throw new Exception('One or more of the parameters is missing.');
        }
        
        preg_match('/\d{1,2}\.\d{1}/', $version, $matches);
        if (empty($matches)) {
            throw new Exception('Version must be [0-9]+\.[0-9]+');
        }
        preg_match('/^\d{2}(\-|\/|\.)\d{2}\1(\d{4})$/', $expires, $matches);
        if (empty($matches) ) {
            throw new Exception('Expiration date must be DD/MM/YYYY');
        }

        if (!is_numeric($users)) {
            throw new Exception('Users must be a four digit number');
        }

        preg_match('/\d{1}/', $sku, $matches);
        if (empty($matches) ) {
            throw new Exception('Unknown SKU was provided [0-9]');
        }
    }
    /**
     * @throws Exception
     */
    function getLicense ()
    {
        // get encoded data
        $data = $this->getData();
        
        // get signature
        openssl_sign($data, $signature, $this->_keyManager->getPrivateKey());

        return $this->strToHex($signature) . $this->strToHex($data);
    }

    private function strToHex ($string)
    {
        $hex = '';
        for ($i = 0; $i < strlen($string); $i ++) {
            $hex .= str_pad(dechex(ord($string[$i])), 2, '0', STR_PAD_LEFT);
        } 
        return $hex;
    }
    
    /**
     * 
     */
    private function getData ()
    {
        $r = str_pad(substr(str_replace(LicenseGenerator::SEPERATOR, '_', $this->_register), 0, 5), 5, '_');
		return $r .
             LicenseGenerator::SEPERATOR . $this->_expires .
             LicenseGenerator::SEPERATOR . $this->_version .
             LicenseGenerator::SEPERATOR . str_pad($this->_users, 3, '0', STR_PAD_LEFT) .
             LicenseGenerator::SEPERATOR . $this->_sku .
             LicenseGenerator::SEPERATOR . str_pad(rand(0, 99), 2, '0', STR_PAD_LEFT) ;
    }
}
