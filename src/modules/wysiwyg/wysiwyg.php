<?php

class wysiwyg {
	/**
	 * @var string Directory where our assets are
	 */
	public $assetsDir;

	/**
	 * @var string Base URL where handleRequest() is 'mounted' at
	 */
	public $baseURL='/engineIncludes/wysiwyg';

	/**
	 * @var array Array of valid providers
	 */
	private $availableProviders = array();

	/**
	 * @var string The current provider
	 */
	private $provider;

	/**
	 * Class constructor
	 *
	 * @param string $initialProvider The initial provider to set
	 * @param string $assetsDir       The location of our assets
	 */
	public function __construct($initialProvider='ckeditor', $assetsDir=NULL){
		$this->assetsDir = isset($assetsDir) && is_dir($assetsDir)
			? $assetsDir
			: __DIR__.'/assets';

		$this->setProvider($initialProvider);
	}

	/**
	 * Returns an array of available providers
	 *
	 * @return array
	 */
	public function getAvailableProviders(){
		if(!sizeof($this->availableProviders)){
			foreach(scandir($this->assetsDir) as $file){
				// Skip dot files
				if($file[0] == '.') continue;

				// Make file an absolute filepath for testing
				$filepath = $this->assetsDir.DIRECTORY_SEPARATOR.$file;

				// If filepath is a dir, add it as a valid provider
				if(is_dir($filepath)) $this->availableProviders[] = trim(strtolower($file));
			}
		}
		return $this->availableProviders;
	}

	/**
	 * Sets the current provider
	 *
	 * @param string $provider
	 * @return bool
	 */
	public function setProvider($provider){
		$provider = trim(strtolower($provider));
		if(!in_array($provider, $this->getAvailableProviders())) return FALSE;

		$this->provider = $provider;
		return TRUE;
	}

	/**
	 * Returns the current provider
	 * @return string
	 */
	public function getProvider(){
		return $this->provider;
	}



	/**
	 * Process a request for an asset file
	 */
	public function handleRequest($request=NULL){
		// If request wasn't provided, we need to figure it out ourselves
		if(!isset($request)) $request = str_replace($this->baseURL, '', $_SERVER['REDIRECT_URL']);

		// Turn the relative $request into an absolute $file
		$file = $this->assetsDir.DIRECTORY_SEPARATOR.$this->provider.$request;

		// Locate, and return the $file. Else, we're at a 404
		if(is_readable($file)){
			// Set MIME type
			$mimeType = get_file_mime_type($file);
			header("Content-Type: $mimeType");
			die(file_get_contents($file));
		}else{
			trigger_error("[".__CLASS__."] 404 Error: File '$file' not found!", E_USER_NOTICE);
			http::sendStatus(404);
			die('404: Not Found!');
		}
	}
} 