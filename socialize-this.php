<?php
/*
Plugin Name: Socialize This
Plugin URI: http://www.fullondesign.co.uk/socialize-this
Description: Adds social widgets to your blog posts. It also can update your twitter status when you publish a post.
Version: 2.0.5
Author: Mike Rogers
Author URI: http://www.fullondesign.co.uk/
Text Domain: st_plugin

Some code and ideas from WordPress(http://wordpress.org/).

Released under the GPL v.2, http://www.gnu.org/copyleft/gpl.html

	This program is distributed in the hope that it will be useful,
	but WITHOUT ANY WARRANTY; without even the implied warranty of
	MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
	GNU General Public License for more details.

*/



define('ST_FILE', plugin_basename(__FILE__));
define('ST_VERSION', '2.0.5');
$st_folder = explode('socialize-this.php', __FILE__);
define('ST_FOLER', $st_folder[0]);
unset($st_folder);

load_theme_textdomain('st_plugin', ST_FOLER.'/locale');

if(substr(phpversion(),0, 1) >= 5){
	include('inc/twitter.class.php');
	include('inc/bitly.class.php');
	include('inc/socialize-this-php5-enviroment.php');
	$ql = new socialize_this(); 
} else {
	include('inc/socialize-this-php4-enviroment.php'); // The PHP4 version is really dated now :(
	$ql = new socialize_this(); 
	$ql->__construct(); // __construct is not supported in PHP4
}

if(!function_exists('show_social_widgets')){ // stops it screwing with other plugins.
	function show_social_widgets($names=NULL, $post_id=NULL, $permalink=NULL, $title=NULL){ // Use this to show custom widgets.
		global $ql;
		$ql->show_social_widgets($names, $post_id, $permalink, $title);
	}
}
if(!function_exists('updateSocialized')){
	function updateSocialized(){
		global $ql;
		$ql->updateSocialized();
	}
	add_action('updateSocialized', 'updateSocialized');
}

// The install/uninstall directorys.
register_activation_hook(ST_FILE, array($ql, 'st_install'));
register_deactivation_hook(ST_FILE, array($ql, 'st_uninstall')); 