<?php

class Welcome extends Controller {

	function Welcome()
	{
		parent::Controller();	
	}
	
	function index()
	{
		echo "This is a controller that's available through both domains";
	}
}

/* End of file welcome.php */
/* Location: ./system/application/controllers/welcome.php */
