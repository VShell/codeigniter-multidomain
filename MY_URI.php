<?php  if ( ! defined('BASEPATH')) exit('No direct script access allowed');

class MY_URI extends CI_URI {
	function host()
	{
		return $_SERVER['HTTP_HOST'];
	}
}