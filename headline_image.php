<?php
/*
Plugin Name: Unique Image for Article
Plugin URI: http://blog.klacansky.com/tag/wordpress
Description: This plugin unlocks unique image for each article. It has integrated button in media gallery for easier choosing image.
Version: trunk
Author: Pavol Klacansky
Author URI: http://klacansky.com
*/

wp_enqueue_script('headline_image_script', WP_PLUGIN_URL . '/headline-image/js/image.js');// register script

// get setted options
$options = get_option('_unique_image');

// only in admin section
if (is_admin())
{
	// load function
	if (!function_exists('add_meta_box'))
		require_once('includes/template.php');

	// add box in Post page
	add_meta_box('headline_image_div', __('Unique Image'), 'headline_image_meta_box', 'post', 'normal', 'high');
	add_action('save_post', 'headline_image_save');
	
	// add entry to Settings menu and add setting page
	add_action('admin_menu', 'headline_image_admin_menu');
  add_action('admin_init', 'headline_image_admin_register');
	
	function headline_image_admin_menu()
	{
		add_submenu_page('options-general.php', 'Unique Image Settings', 'Unique Image', 'administrator', __FILE__, 'unique_image_settings_page', '');
	}
	
	function unique_image_settings_page()
	{
		global $options;
		
		$url = $options['facebook_default'];
		
		// URL must be valid
		if (!headline_image_valid_url($url))
		{
			$error = __('Not valid URL.', 'unique_image') . ' ';
			$url = '';
		}	
?>
<div class="wrap">
	<h2>Unique Image Settings</h2>

	<form method="post" action="options.php">
    <?php settings_fields('unique_image_settings_page'); ?>
    <h3>Facebook Share thumb integration</h3>
    <table class="form-table">
        <tr valign="top">
					<th scope="row"><label for="_unique_image[facebook]"><?php _e('Facebook share support', 'unique_image'); ?></label></th>
					<td><input type="checkbox" name="_unique_image[facebook]" <?php echo ($options['facebook'] ? 'checked="checked"' : ''); ?> /></td>
        </tr>
        <tr valign="top">
					<th scope="row"><label for="_unique_image[facebook_default]"><?php _e('Facebook share default url', 'unique_image'); ?></label></th>
					<td><input type="text" name="_unique_image[facebook_default]" value="<?php echo $url; ?>" /></td>
					<td><?php echo $error; ?><?php _e('Displayed, when page is not Single (valid format is e.g. http://klacansky.com/images/favicon.png).', 'unique_image'); ?></td>
					<?php if ($url) echo '<td><img width="32" height="32" src="', $url, '" alt="', __('Favicon Thumb', 'unique_image'), '" /></td>'; ?>
        </tr>
        <tr valign="top">
					<td colspan="3"><img src="<?php echo plugins_url('headline-image/screenshot-5.png'); ?>" alt="<?php _e('Facebook usage', 'unique_image'); ?>" /></td>
        </tr>
    </table>
    
    <p class="submit">
			<input type="submit" class="button-primary" value="<?php _e('Save Changes') ?>" />
    </p>

	</form>
	
	<!-- begin: example of integration with template -->
	<h3>Example of integration with your template</h3>
	<ol>
		<li>Open your template file e.g. /wp-content/themes/your_theme_name/single.php</li>
		<li>Add into LOOP that code <code>&lt;?php if (function_exists(&#039;headline_image_show&#039;)) headline_image_show(); ?&gt;</code></li>
	</ol>
	<!-- end: example of integration with template -->
</div>
<?php
	}

	// register settings
	function headline_image_admin_register()
	{
		register_setting('unique_image_settings_page', '_unique_image');
	}

}

// copied and modified function media_buttons() from file /wp-admin/includes/media.php
function headline_image_button()
{
	global $post_ID, $temp_ID;
	
	$uploading_iframe_ID = (int) (0 == $post_ID ? $temp_ID : $post_ID);
	$context = apply_filters('media_buttons_context', __('Upload/Insert %s'));
	$media_upload_iframe_src = "media-upload.php?post_id=$uploading_iframe_ID";
	
	$image_upload_iframe_src = apply_filters('image_upload_iframe_src', "$media_upload_iframe_src&amp;type=image");
	$image_title = __('Add an Image');
	$out = <<<EOF
	<a href="{$image_upload_iframe_src}&amp;TB_iframe=true" class="thickbox" title='$image_title' onclick="return false;"><img src='images/media-button-image.gif' alt='$image_title' /></a>
EOF;
	printf($context, $out);
}

function headline_image_meta_box($post)
{
	global $post_ID, $temp_ID;
	
	$image_ID = get_post_meta($post_ID, '_headline_image', true);
	
	echo '<input type="hidden" name="headline_image_value" id="headline_image_value" value="' . $image_ID . '" />';
?>
<div id="headline_image_button"><?php headline_image_button(); ?></div>

<p id="headline_image_show">
	<?php echo wp_get_attachment_image($image_ID); ?>
</p>

<a href="#headline_image_div" onclick="headline_image_clear()" <?php echo $image_ID ? '' : 'style="display: none;"'; ?> id="headline_image_remove"><?php echo __('Remove'); ?></a>
<?php
}

// save image to DB
function headline_image_save($post_ID)
{
	// if hidden form does not exists, then do nothing (0 => no image, '' => form not exists, 1,2,... => id of image)
	if ($_POST['headline_image_value'] != '')
		update_post_meta($post_ID, '_headline_image', intval($_POST['headline_image_value']));
}

function headline_image_show($post_ID = 0)
{
	global $post;
	
	// if $post_ID is not setted (for special purposes), use normal ID from global variable
	if (!$post_ID)
		$post_ID = $post->ID;
	
	$attachment_id = get_post_meta($post_ID, '_headline_image', true);
	
	// use alternate text for image (if it is setted)
	echo wp_get_attachment_image($attachment_id, $size = 'thumbnail', $icon = false, array('alt' => get_post_meta($attachment_id, '_wp_attachment_image_alt', true)));
}

/*********** FACEBOOK SUPPORT *************/

// add meta to header if facebook support is enabled
if ($options['facebook'])
	add_action('wp_head', 'headline_image_facebook_meta');

// show meta (<link />) in <head></head>
function headline_image_facebook_meta()
{
	global $wp_query;
		
	// only for single post
	if (is_single())
	{
		$thePostID = $wp_query->post->ID;
		$image = wp_get_attachment_image_src(get_post_meta($thePostID, '_headline_image', true));
		$image = $image[0];
	}
	
	// get default image (setted in administration), when $image does not exists (page is not Single or there is no Unique image for article)
	if (!$image && headline_image_valid_url(get_option('_unique_image_facebook_default')))
		$image = get_option('_unique_image_facebook_default');
		
	echo '<link rel="image_src" href="', $image, '" />';
}
/*********** FACEBOOK SUPPORT *************/

function headline_image_valid_url($url)
{
	return preg_match('|^http(s)?://[a-z0-9-]+(.[a-z0-9-]+)*(:[0-9]+)?(/.*)?$|i', $url);
}
?>
