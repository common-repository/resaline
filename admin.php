<?php
add_action( 'admin_menu', 'resaline_admin_menu' );

function resaline_nonce_field($action = -1) { return wp_nonce_field($action); }
$resaline_nonce = 'resaline-update-key';

function resaline_plugin_action_links( $links, $file ) {
	if ( $file == plugin_basename( dirname(__FILE__).'/resaline.php' ) ) {
		$links[] = '<a href="' . admin_url( 'admin.php?page=resaline-config' ) . '">'.__( 'Settings' ).'</a>';
	}

	return $links;
}

add_filter( 'plugin_action_links', 'resaline_plugin_action_links', 10, 2 );

/*
 * Manage admin post form
 */
function resaline_conf() {
	global $resaline_nonce, $wpcom_api_key;

	if ( isset($_POST['submit']) ) {
		if ( function_exists('current_user_can') && !current_user_can('manage_options') )
			die(__('Cheatin&#8217; uh?'));

		check_admin_referer( $resaline_nonce );
		
		$key = $_POST['username'];
		$home_url = parse_url( get_bloginfo('url') );

		update_option('resaline_frame_height', $_POST['frame-height']);
		update_option('resaline_frame_length', $_POST['frame-length']);
		update_option('resaline_frame_background', $_POST['frame-background']);

		/* Delete all former calendar datas */
		update_option('resaline_nb_calendars', 0);
		for ($i = 0; $i < get_option('resaline_nb_calendars'); $i++) {
			delete_option('resaline_calendar_'.$i);   
		}

		if ( empty($key) ) {
			$key_status = 'empty';
			$ms[] = 'account_number_empty';
			delete_option('resaline_account_id');
		} elseif ( empty($home_url['host']) ) {
			$key_status = 'empty';
			$ms[] = 'bad_home_url';
		} else {
			$key_status = 'valid';
			
			$api_call = file_get_contents("http://www.resaline.net/rest?username={$key}");
			$calendars_json = json_decode($api_call);

			if ( $calendars_json->status === "NOK" ){
				$ms[] = 'no_account';	
			} else  if (count($calendars_json->results) == 0){
				$ms[] = 'no_calendars';
			} else {
				/* MAJ calendars */
				update_option('resaline_nb_calendars', count($calendars_json->results));
				
				foreach ( $calendars_json->results as $idx=>$calendar){
					/* Temporary - main site returns english as main language. If file not found use french as default */
					$headers = @get_headers($calendar->file);
					if(!strpos($headers[0],'404') === false) {
					  $calendar->file = str_replace("/en/", "/fr/", $calendar->file);
					}

					$serial = serialize($calendar);
					update_option('resaline_calendar_'.$idx, $serial);
				}	
			}
		}


		if ( $key_status == 'valid' ) {
			update_option('resaline_account_id', $key);
			$ms[] = 'account_number_valid';
		} else if ( $key_status == 'invalid' ) {
			$ms[] = 'account_number_invalid';
		} else if ( $key_status == 'failed' ) {
			$ms[] = 'account_number_failed';
		}

	} elseif ( isset($_POST['check']) ) {
		resaline_get_server_connectivity(0);
	}

	if ( empty( $key_status) ||  $key_status != 'valid' ) {
		$key = get_option('resaline_account_id');
		if ( empty( $key ) ) {
			$key_status = 'empty';
		} else {
			$key_status = 'valid';
			//$key_status = resaline_verify_key( $key );
		}
		if ( $key_status == 'valid' ) {
			$ms[] = 'key_valid';
		} else if ( $key_status == 'invalid' ) {
			$ms[] = 'key_invalid';
		} else if ( !empty($key) && $key_status == 'failed' ) {
			$ms[] = 'key_failed';
		}
	}

	$messages = array(
		'account_number_empty' => array('color' => 'aa0', 'text' => __('Your key has been cleared.')),
		'account_number_valid' => array('color' => '4AB915', 'text' => __('Your key has been updated!')),
		'account_number_invalid' => array('color' => '888', 'text' => __('The key you entered is invalid. Please double-check it.')),
		'account_number_failed' => array('color' => '888', 'text' => __('The key you entered could not be verified because a connection to resaline.com could not be established. Please check your server configuration.')),
		'no_account' => array('color' => 'FF0000', 'text' => sprintf(__('There is no such username registered. '))),
		'no_calendars' => array('color' => 'FF6600', 'text' => sprintf(__('You have no active calendars. Be sure to publish them. '))),
		'key_empty' => array('color' => 'aa0', 'text' => sprintf(__('Please enter an account number. '), 'http://resaline.net')),
		'key_valid' => array('color' => '4AB915', 'text' => __('This key is valid.')),
		'key_invalid' => array('color' => '888', 'text' => __('This key is invalid.')),
		'key_failed' => array('color' => 'aa0', 'text' => __('The key below was previously validated but a connection to resaline.com can not be established at this time. Please check your server configuration.')),
		'bad_home_url' => array('color' => '888', 'text' => sprintf( __('Your WordPress home URL %s is invalid.  Please fix the <a href="%s">home option</a>.'), esc_html( get_bloginfo('url') ), admin_url('options.php#home') ) ),
	);
?>


<!-- Admin content page -->

<?php if ( !empty($_POST['submit'] ) ) : ?>
	<div id="message" class="updated fade"><p><strong><?php _e('Options saved.') ?></strong></p></div>
<?php endif; ?>



<div class="wrap">
	<h2><?php _e('Resaline Configuration'); ?></h2>
	<div class="narrow">
		<form action="" method="post" id="resaline-conf" style="margin: auto; width: 400px; ">
			<?php if ( !$wpcom_api_key ) { ?>
				<p><?php printf(__('<a href="%2$s">RESALINE.NET</a>'), 'http://resaline.net', 'http://resaline.net'); ?></p>
				<h3><label for="key"><?php _e('RESALINE username'); ?></label></h3>
				<?php foreach ( (array)$ms as $m ) : ?>
					<p style="padding: .5em; background-color: #<?php echo $messages[$m]['color']; ?>; color: #fff; font-weight: bold;"><?php echo $messages[$m]['text']; ?></p>
				<?php endforeach; ?>
				<p><input id="username" name="username" type="text" size="20" maxlength="20" value="<?php echo get_option('resaline_account_id'); ?>" style="font-family: 'Courier New', Courier, mono; font-size: 1.5em;" /></p>
				<br/>
				<h3>Iframe configuration</h3>
				<ul class="frame-style">
					<li>
						<label for="frame-height">Hauteur (px)</label>
						<input id="frame-height" name="frame-height" type="text" value="<?php $height = get_option('resaline_frame_height'); echo $height ? $height : 1200 ; ?>" />
					</li>
					<li>
						<label for="frame-length">Largeur (px)</label>
						<input id="frame-length" name="frame-length" type="text" value="<?php $length = get_option('resaline_frame_length'); echo $length ? $length : 800 ; ?>" />
					</li>
					<li>
						<label for="frame-background">Couleur de fond (hexa)</label>
						<input id="frame-background" name="frame-background" type="text" value="<?php $background = get_option('resaline_frame_background'); echo $background ? $background : '' ; ?>" />
					</li>
				</ul>
			<?php } ?>
			<?php resaline_nonce_field($resaline_nonce) ?>
			<p class="submit"><input type="submit" name="submit" value="<?php _e('Update calendars &raquo;'); ?>" /></p>
		</form>
	</div>
	<div class="resaline_calendars">
		<?php display_resaline_list() ?>
	</div>
</div>
<?php
}



/*Resaline back-end submenu*/
function resaline_admin_menu() {
	resaline_load_menu();
}

function resaline_load_menu() {   
	add_submenu_page('plugins.php', __('Resaline Configuration'), __('Resaline Configuration'), 'manage_options', 'resaline-config', 'resaline_conf');
}

/* Display all calendars */
function display_resaline_list(){
	$nb_calendars = get_option('resaline_nb_calendars');
	echo "<h2>Mes etablissements</h2>";
	try{
		for ($i = 0; $i < $nb_calendars; $i++) {
		    $cal = unserialize(get_option('resaline_calendar_'.$i));
		    echo "<div class='calendar_item'><p class='id_calendar'>{$cal->id}</p><h3>{$cal->name}</h3><p class='adress'>{$cal->adress}</p></div>";
		}
	} catch (Exception $e) {}		
}


/** Ajout bouton editor
 */
function resaline_add_shortcode_button() {
	if ( ! current_user_can('edit_posts') && ! current_user_can('edit_pages') ) return;
	if ( get_user_option('rich_editing') == 'true') :
		add_filter('mce_external_plugins', 'resaline_add_shortcode_tinymce_plugin');
		add_filter('mce_buttons', 'resaline_register_shortcode_button');
	endif;
}


function resaline_add_tinymce_lang( $arr ) {
    $arr['ResalineShortcodes'] = untrailingslashit( plugin_dir_path( __FILE__ ) ) . '/assets/js/admin/editor_plugin_lang.php';
    return $arr;
}
add_filter( 'mce_external_languages', 'resaline_add_tinymce_lang', 10, 1 );

/**
 * Force TinyMCE to refresh.
 *
 */
function resaline_refresh_mce( $ver ) {
	$ver += 3;
	return $ver;
}

/**
 * Register the shortcode button.
 *
 */
function resaline_register_shortcode_button($buttons) {
	array_push($buttons, "|", "resaline_shortcodes_button");
	return $buttons;
}

/**
 * Add the shortcode button to TinyMCE
 *
 */
function resaline_add_shortcode_tinymce_plugin($plugin_array) {
	$plugin_array['ResalineShortcodes'] = RESALINE_PLUGIN_URL . '/assets/js/admin/editor_plugin.js';
	return $plugin_array;
}



/**
 * Queue admin menu icons CSS.
 *
 */
function resaline_admin_menu_styles() {
	wp_enqueue_style( 'resaline_admin_menu_styles', RESALINE_PLUGIN_URL . '/assets/css/resaline.css', array() );
}

add_action( 'admin_print_styles', 'resaline_admin_menu_styles' );



/**
 * Shortcode buttons for editor
 *
 */
/*add_filter( 'mce_external_languages', 'resaline_add_tinymce_button', 10, 1 );*/
add_action( 'init', 'resaline_add_shortcode_button' );
add_filter( 'tiny_mce_version', 'resaline_refresh_mce' );

