<?php


namespace BlazeWooless\Core;

class Cookie
{
	private static $instance = null;

	public static function get_instance()
	{
		if (self::$instance === null) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	public function main_domain()
	{
		$domain_parts = get_home_url();
		$main_domain = preg_replace('/^https?:\/\//mi', '', $domain_parts);
		return $main_domain;
	}

	public function cookie_domain() {
		$domain_cookie = '.' . $this->main_domain();
		return $domain_cookie;
	}
	
	public function set($name, $value)
	{
		$cookie_domain = apply_filters( 'blaze_commerce_cookie_domain', $this->cookie_domain() );
		setcookie(
			$name,
			$value,
			array(
				'domain' 	=> $cookie_domain,
				'expires' 	=> apply_filters( 'blaze_commerce_cookie_expiry', time() + 3600 ), 
				'path' 		=> "/", 
				'samesite' 	=> 'None',
				'secure' 	=> true,
			)
		);
	}
	
	public function delete($name)
	{
		$cookie_domain = apply_filters( 'blaze_commerce_cookie_domain', $this->cookie_domain() );
		setcookie($name, "", array(
			"expires" => apply_filters( 'blaze_commerce_cookie_expiry', time() - 3600 ),
			'domain' 	=> $cookie_domain,
		));
		setcookie($name, "", array(
			"expires" => apply_filters( 'blaze_commerce_cookie_expiry', time() - 3600 ),
			'domain' 	=> preg_replace('/^https?:\/\//mi', '', get_home_url()),
		));
	}
}
