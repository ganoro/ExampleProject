<?php

/** 
 * Handles private and public keys 
 * @author Roy, 2011
 */
class KeysManager {
	
	const PRIVATE_FILENAME = 'private_key.pem';
	const PASSPHRASE = '';
	
	private $_baseDirectory;
	
	public function KeysManager($_baseDirectory = null) {
		if (! $_baseDirectory) {
			$_baseDirectory = __DIR__ . '/../resource/';
		}
		$this->_baseDirectory = $_baseDirectory;
	}
	
	/**
	 * true if keys file already exists
	 * @return boolean
	 */
	public function keysExists() {
		return file_exists ( $this->getPrivateFilename () );
	}
	
	/**
	 * returns the public key
	 */
	public function getPrivateKey() {
		return openssl_pkey_get_private ( 'file://' . $this->getPrivateFilename (), KeysManager::PASSPHRASE );
	}
	
	public function getPrivateFilename() {
		return $this->_baseDirectory . KeysManager::PRIVATE_FILENAME;
	}
}
