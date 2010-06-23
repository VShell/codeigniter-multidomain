<?php  if ( ! defined('BASEPATH')) exit('No direct script access allowed');

class MY_Router extends CI_Router {
	var $hostregex = '';
	var $hostdir = '';
	
	function _set_routing()
	{
		// Are query strings enabled in the config file?
		// If so, we're done since segment based URIs are not used with query strings.
		if ($this->config->item('enable_query_strings') === TRUE AND isset($_GET[$this->config->item('controller_trigger')]))
		{
			$this->set_class(trim($this->uri->_filter_uri($_GET[$this->config->item('controller_trigger')])));

			if (isset($_GET[$this->config->item('function_trigger')]))
			{
				$this->set_method(trim($this->uri->_filter_uri($_GET[$this->config->item('function_trigger')])));
			}
			
			return;
		}
		
		// Load the routes.php file.
		@include(APPPATH.'config/routes'.EXT);
		$this->routes = ( ! isset($route) OR ! is_array($route)) ? array() : $route;
		unset($route);
		
		// Set the default controller so we can display it in the event
		// the URI doesn't correlated to a valid controller.
		$this->default_controller = ( ! isset($this->routes['default_controller']) OR $this->routes['default_controller'] == '') ? FALSE : strtolower($this->routes['default_controller']);
		unset($this->routes['default_controller']);
		
		// Fetch the complete URI string
		$this->uri->_fetch_uri_string();
		
		// Do we need to remove the URL suffix?
		$this->uri->_remove_url_suffix();
		
		// Compile the segments into an array
		$this->uri->_explode_segments();
		
		// Parse any custom routing that may exist
		$this->_parse_routes();		
		
		// Re-index the segment array so that it starts with 1 rather than 0
		$this->uri->_reindex_segments();
	}
	
	function _validate_request($segments)
	{
		// Does the requested controller exist in the root folder?
		if (file_exists(APPPATH.'controllers/'.$this->fetch_directory().$segments[0].EXT))
		{
			return $segments;
		}
		
		// Is the controller in a sub-folder?
		while (count($segments) > 0 && is_dir(APPPATH.'controllers/'.$this->fetch_directory().$segments[0]))
		{
			$this->set_directory($this->fetch_directory().array_shift($segments));
		}
		if (count($segments) > 0)
		{
			if (!file_exists(APPPATH.'controllers/'.$this->fetch_directory().$segments[0].EXT))
			{
				show_404($this->fetch_directory().$segments);
			}
		}
		else
		{
			if (!file_exists(APPPATH.'controllers/'.$this->fetch_directory().$this->default_controller.EXT))
			{
				show_404($this->fetch_directory().$this->default_controller);
			}
			return array($this->default_controller);
		}
		
		return $segments;
	}
	
	function _parse_hosts()
	{
		$this->hostdir = $this->uri->host();
		
		// Is there a literal match for host?
		if(isset($this->routes[preg_quote($this->uri->host())]))
		{
			$this->hostregex = preg_quote($this->uri->host());
			return $this->routes[preg_quote($this->uri->host())];
		}
		
		// Loop through the host array looking for wild-cards
		foreach ($this->routes as $key => $val)
		{						
			// Convert wild-cards to RegEx
			$key = str_replace(':any', '.+', str_replace(':num', '[0-9]+', $key));
			
			// Does the RegEx match?
			$matches = array();
			if (preg_match('#^'.$key.'$#', $this->uri->host(), $matches))
			{
				$this->hostregex = $key;
				if(isset($matches['hostdir'])) $this->hostdir = $matches['hostdir'];
				return $val;
			}
		}
		
		return isset($this->routes['default_host']) ? $this->routes['default_host'] : array();
	}
	
	function _parse_routes()
	{
		// Do we even have any custom routing to deal with?
		if (count($this->routes) == 0)
		{
			$this->_set_request($this->uri->segments);
			return;
		}
		
		// Turn the segment array into a URI string
		$uri = implode('/', $this->uri->segments);
		
		// Parse hosts
		$route = $this->_parse_hosts();
		
		// Update the default controller and scaffolding command
		if (isset($route['default_controller']))
		{
			$this->default_controller = $route['default_controller'];
			unset($route['default_controller']);
		}
		if (!isset($route['scaffolding_trigger']))
		{
			$route['scaffolding_trigger'] = $this->routes['scaffolding_trigger'];
		}
		
		// If we're on the frontpage, we can skip everything else
		if ($uri == '')
		{
			if ($this->hostdir !== '' && file_exists(APPPATH.'controllers/'.$this->hostdir.'/'.$this->default_controller.EXT))
			{
				$this->_set_request(array_merge(array($this->hostdir), explode('/', $this->default_controller)));
			}
			else
			{
				$this->_set_request(explode('/', $this->default_controller));
			}
			return;
		}
		
		// If there's no routes, there's no need to bother going through the list
		if (count($route) > 1)
		{
			// Is there a literal match for the route? If so we're done
			if (isset($route[preg_quote($uri)]))
			{
				$this->_set_request(explode('/', $this->routes[$uri]));		
				return;
			}
			
			// Loop through the route array looking for wild-cards
			foreach ($route as $key => $val)
			{
				// Convert wild-cards to RegEx
				$key = str_replace(':any', '.+', str_replace(':num', '[0-9]+', $key));
				
				// Does the RegEx match?
				if (preg_match('#^'.$key.'$#', $uri))
				{
					// Do we have a back-reference?
					if (strpos($val, '$') !== FALSE AND (strpos($key, '(') !== FALSE OR strpos($hostregex, '(') !== FALSE))
					{
						$val = preg_replace('#^'.$this->hostregex.'/'.$key.'$#', $val, $uri);
					}
					
					$this->_set_request(explode('/', $val));		
					return;
				}
			}
		}
		
		// Try to see whether the controller exists in a directory with the same name as the host
		if ($this->hostdir !== '' && is_dir(APPPATH.'controllers/'.$this->hostdir))
		{
			$this->set_directory($this->hostdir);
			$segments = $this->uri->segments;
			while (count($segments) > 0 && is_dir(APPPATH.'controllers/'.$this->fetch_directory().$segments[0]))
			{
				$this->set_directory($this->fetch_directory().array_shift($segments));
			}
			if(count($segments) > 0)
			{
				if (file_exists(APPPATH.'controllers/'.$this->fetch_directory().$segments[0].EXT))
				{
					$this->_set_request($segments);
					return;
				}
			}
			else
			{
				if (file_exists(APPPATH.'controllers/'.$this->fetch_directory().$this->default_controller.EXT))
				{
					$this->_set_request(array_merge($segments, array($this->default_controller)));
					return;
				}
			}
			$this->directory = '';
		}
		
		// If we got this far it means we didn't encounter a
		// matching route so we'll set the site default route
		$this->_set_request($this->uri->segments);
	}
}