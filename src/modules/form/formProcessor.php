<?php

class formProcessor extends formFields{
	const ERR_OK           = 0;
	const ERR_NO_POST      = 1;
	const ERR_NO_NAME      = 2;
	const ERR_INVALID_NAME = 3;
	const ERR_VALIDATION   = 4;
	const ERR_SYSTEM       = 5;

	public function __construct(){
	}

	public static function createProcessor(){
		return new self;
	}

	public function process(){
		// Lots O' magic!

		// Check CSRF token
		/*
		if(!session::csrfTokenCheck($post['__csrfID'], $post['__csrfToken'])){
			// FREAK OUT!
		}
		*/

		return 0;
	}


}