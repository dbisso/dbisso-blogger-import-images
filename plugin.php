<?php
/*
Plugin Name: Blogger Import Images
Description: Searches for images in post content imported from blogger
Version: 0.1
Author: Dan Bissonnet
Author URI: http://danisadesigner.com
*/

/**
 * Copyright (c) 2013 Dan Bissonnet. All rights reserved.
 *
 * Released under the GPL license
 * http://www.opensource.org/licenses/gpl-license.php
 *
 * This is an add-on for WordPress
 * http://wordpress.org/
 *
 * **********************************************************************
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 * **********************************************************************
 */
$loader = include_once __DIR__ . '/vendor/autoload.php';
$loader->add( 'DBisso', __DIR__ . '/lib' );

/**
 * Bootstrap or die
 */
try {
	if ( class_exists( '\DBisso\Util\Hooker' ) ) {
		DBisso\Plugin\BloggerImportImages\Plugin::bootstrap( new DBisso\Util\Hooker );
	} else {
		throw new \Exception( 'Class DBisso\Util\Hooker not found. Check that the plugin is installed.', 1 );
	}
} catch ( \Exception $e ) {
	wp_die( $e->getMessage(), $title = 'Theme Exception' );
}
