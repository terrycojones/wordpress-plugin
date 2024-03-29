<?php
/*
Plugin Name: Fluidinfo
Plugin URI: http://www.fluidinfo.com
Description: Plugin to export posts to Fluidinfo
Author: PA Parent
Version: 0.1-alpha
Author URI: http://www.twitter.com/paparent
License: MIT
*/

/*
 * Copyright (c) 2011 PA Parent
 *
 *
 * Permission is hereby granted, free of charge, to any person
 * obtaining a copy of this software and associated documentation
 * files (the "Software"), to deal in the Software without
 * restriction, including without limitation the rights to use,
 * copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the
 * Software is furnished to do so, subject to the following
 * conditions:
 *
 * The above copyright notice and this permission notice shall be
 * included in all copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND,
 * EXPRESS OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES
 * OF MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE AND
 * NONINFRINGEMENT. IN NO EVENT SHALL THE AUTHORS OR COPYRIGHT
 * HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER LIABILITY,
 * WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING
 * FROM, OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR
 * OTHER DEALINGS IN THE SOFTWARE.
 */


// Set-up Hooks
register_uninstall_hook(__FILE__, 'fi_delete_plugin_options');
add_action('admin_init', 'fi_init' );
add_action('admin_menu', 'fi_admin_menu');

// Delete options table entries ONLY when plugin deactivated AND deleted
function fi_delete_plugin_options() {
	delete_option('fi_options');
}

// Init plugin options to white list our options
function fi_init() {
	register_setting('fi_plugin_options', 'fi_options', 'fi_validate_options');
}

// Add menu page
function fi_admin_menu() {
	add_options_page('Fluidinfo Options Page', 'Fluidinfo', 'manage_options', __FILE__, 'fi_options_render');
	add_management_page('Fluidinfo Export', 'Fluidinfo Export', 'export', __FILE__, 'fi_export_render');
}

// Render the Plugin options form
function fi_options_render() {
?>
<div class="wrap">

	<div class="icon32" id="icon-options-general"><br></div>
	<h2>Fluidinfo</h2>
	<p>Fluidinfo configuration</p>

	<form method="post" action="options.php">
		<?php settings_fields('fi_plugin_options'); ?>
		<?php $options = get_option('fi_options'); ?>

		<table class="form-table">

			<tr>
				<th scope="row">Username</th>
				<td>
					<input type="text" size="57" name="fi_options[username]" value="<?php echo $options['username']; ?>" />
				</td>
			</tr>

			<tr>
				<th scope="row">Password</th>
				<td>
					<input type="password" size="57" name="fi_options[password]" value="<?php echo $options['password']; ?>" />
				</td>
			</tr>

			<tr>
				<th scope="row">Namespace</th>
				<td>
					<input type="text" size="57" name="fi_options[namespace]" value="<?php echo $options['namespace']; ?>" />
				</td>
			</tr>

			<tr>
				<th scope="row">Import server</th>
				<td>
					<input type="text" size="57" name="fi_options[importserver]" value="<?php echo $options['importserver']; ?>" />
				</td>
			</tr>

		</table>
		<p class="submit">
		<input type="submit" class="button-primary" value="<?php _e('Save Changes') ?>" />
		</p>
	</form>

</div>
<?php
}

// Sanitize and validate input. Accepts an array, return a sanitized array.
function fi_validate_options($input) {
	$input['username'] =  wp_filter_nohtml_kses($input['username']);
	$input['password'] =  wp_filter_nohtml_kses($input['password']);
	return $input;
}

add_filter('plugin_action_links', 'fi_plugin_action_links', 10, 2);
// Display a Settings link on the main Plugins page
function fi_plugin_action_links($links, $file) {

	if ( $file == plugin_basename( __FILE__ ) ) {
		$fi_links = '<a href="'.get_admin_url().'options-general.php?page=fluidinfo-sync/fluidinfo-sync.php">'.__('Settings').'</a>';
		// make the 'Settings' link appear first
		array_unshift( $links, $fi_links );
	}

	return $links;
}

// Tools menu - Fluidinfo Export
function fi_export_render() {

	$hidden_field_name = 'fi_submit_hidden';

?>
<div class="wrap">
	<div class="icon32" id="icon-tools"><br></div>
	<h2>Fluidinfo export</h2>
<?php

	if (isset($_POST[$hidden_field_name]) && $_POST[$hidden_field_name] == 'Y') {

		$args = array('nopaging'=>true);
		$posts = get_posts($args);

		fi_export_posts($posts);

		echo '<div id="message" class="updated below-h2"><p><strong>Posts exported.</strong></p></div>';
	}
?>

	<p>You can here export all your posts. Blah blah blah.</p>

	<form method="post" action="">
		<input type="hidden" name="<?php echo $hidden_field_name; ?>" value="Y">
		<p class="submit"><input type="submit" name="Submit" class="button-primary" value="<?php esc_attr_e('Export all posts') ?>" /></p>
	</form>
</div>
<?php
}

function fi_export_posts($posts) {

	$json_posts = array();
	foreach ($posts as $post) {
		$json_posts[] = fi_post_to_json($post);
	}

	fi_send_to_import_server($json_posts);
}

function fi_post_to_json($post) {

	$options = get_option('fi_options');
	$ns = $options['namespace'];

	$data = array();

	$data['fluiddb/about'] = $post->guid;
	$data[$ns.'/text'] = strip_tags($post->post_content);
	$data[$ns.'/html'] = $post->post_content;
	$data[$ns.'/title'] = $post->post_title;

	// Can posts have more that one author?
	$author = get_userdata($post->post_author);
	$data[$ns.'/author-names'] = array($author->user_firstname . ' ' . $author->user_lastname);

	$data[$ns.'/publication-datetime'] = $post->post_date_gmt;
	$publication = strtotime($post->post_date_gmt);
	$data[$ns.'/publication-year'] = (int) date('Y', $publication);
	$data[$ns.'/publication-month'] = (int) date('n', $publication);
	$data[$ns.'/publication-day'] = (int) date('j', $publication);
	$data[$ns.'/publication-time'] = date('g:i:s', $publication);
	$data[$ns.'/publication-timestamp'] = (float) $publication; // TODO: Need to force float...
	// "paparent/wordpress/publication-date": "May 29th 2011",

	$data[$ns.'/modification-datetime'] = $post->post_modified_gmt;
	$modification = strtotime($post->post_modified_gmt);
	$data[$ns.'/modification-year'] = (int) date('Y', $modification);
	$data[$ns.'/modification-month'] = (int) date('n', $modification);
	$data[$ns.'/modification-day'] = (int) date('j', $modification);
	$data[$ns.'/modification-time'] = date('g:i:s', $modification);
	$data[$ns.'/modification-timestamp'] = (float) $modification; // TODO: Need to force float...
	// "paparent/wordpress/modification-date": "June 10th 2011",

	// Do we need that ??
	// "paparent/wordpress/excerpt": "...",
	// "paparent/wordpress/status": "published",

	$post_cats = get_the_category($post->ID);
	if ($post_cats) {
		foreach ($post_cats as $cat) {
			$data[$ns.'/categories/'.$cat->slug] = null;
		}
	}

	$post_tags = get_the_tags($post->ID);
	if ($post_tags) {
		foreach ($post_tags as $tag) {
			$data[$ns.'/tags/'.$tag->name] = null;
		}
	}

	$json = json_encode($data);

	return $json;
}

function fi_send_to_import_server($json_posts) {
	if (!is_array($json_posts)) $json_posts = array($json_posts);

	$options = get_option('fi_options');

	$json = '{"config":{';
	$json .= '"username":"' . $options['username'] . '",';
	$json .= '"password":"' . $options['password'] . '"';
	$json .= '},"data":[';
	$json .= implode(',', $json_posts);
	$json .= ']}';

	echo 'Import URL: ' . $options['importserver'];
	echo '<pre>';
	print_r(($json));
	// print_r(json_decode($json));
	echo '</pre>';
}

