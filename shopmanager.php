<?php
/**
 * Plugin Name: Shop Manager
 * Plugin URI: http://www.dribbl.es
 * Description: Woocommerce Simple Manager
 * Version: 0.0.1
 * Author: Killthelord
 * Author URI: http://twitter.com/rchmet
 * Requires at least: 3.3
 * Tested up to: 3.5
 *
 * Text Domain: shopmanager
 * Domain Path: /languages/
 *
 * @package ShopManager
 * @category Core
 * @author Killthelord
 */

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

if ( ! class_exists( 'ShopManager' ) ) { 
	
class ShopManager {
	
	function __construct(){
		$this->load_file('posttypebuilder.php', true);

	}
	
	function load_file($name, $once = false){
		if(!$once){
			include dirname(__FILE__).'/'.$name;
		}
		else{
			include_once dirname(__FILE__).'/'.$name;
		}
	}
	
}	

/**
 * Init class
 */
$GLOBALS['shopmanager'] = new ShopManager();
	
}