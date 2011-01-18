<?php

/**
    * @package Socialize This
*/
/*
Plugin Name:Socialize This
Plugin URI: http://www.fullondesign.co.uk/socialize-this
Description: Adds social widgets to your blog posts. It also can update your twitter status when you publish a post.
Version: 2.1.0
Author: Mike Rogers
Author URI: http://www.fullondesign.co.uk/
Text Domain: st_plugin
License: GPLv2
*/

/*
Copyright 2011 Mike Rogers - http://www.fullondesign.co.uk/

This program is free software; you can redistribute it and/or
modify it under the terms of the GNU General Public License
as published by the Free Software Foundation; either version 2
of the License, or (at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program; if not, write to the Free Software
Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
*/

define('ST_FILE', plugin_basename(__FILE__));
define('ST_VERSION', '2.1.0');
$st_folder = explode('socialize-this.php', __FILE__);
define('ST_FOLER', $st_folder[0]);
unset($st_folder);
load_theme_textdomain('st_plugin', ST_FOLER . '/locale');

// Check which version PHP the user has.
if (substr(phpversion(), 0, 1) >= 5) {
    require('inc/twitter.class.php');
    require('inc/bitly.class.php');
    require('inc/socialize-this-php5-enviroment.php');
    $ql = new socialize_this();
} else {
    require('inc/socialize-this-php4-enviroment.php'); // The PHP4 version is really dated now :(
    $ql = new socialize_this();
    $ql->__construct(); // __construct is not supported in PHP4
}

if (!function_exists('show_social_widgets')) {

    function show_social_widgets($names=NULL, $post_id=NULL, $permalink=NULL, $title=NULL) { // Use this to show custom widgets.
        global $ql;
        $ql->show_social_widgets($names, $post_id, $permalink, $title);
    }

}

if (!function_exists('updateSocialized')) {

    function updateSocialized() {
        global $ql;
        $ql->updateSocialized();
    }

    add_action('updateSocialized', 'updateSocialized');
}

// The install/uninstall directorys.

register_activation_hook(ST_FILE, array($ql, 'st_install'));
register_deactivation_hook(ST_FILE, array($ql, 'st_uninstall'));