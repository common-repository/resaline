<?php
/**
 * @package resaline
 */
/*
Plugin Name: Resaline
Plugin URI: http://resaline.net
Description: Premier plugin resaline
Version: 1.2
Author: Resaline
License: ??
*/	

// Make sure we don't expose any info if called directly
if ( !function_exists( 'add_action' ) ) {
	header('Status: 403 Forbidden');
    header('HTTP/1.1 403 Forbidden');
    exit();
}

define('RESALINE_VERSION', '1.2');
define('RESALINE_PLUGIN_URL', plugin_dir_url( __FILE__ ));

/* Base resaline script links */
define('BASE_RESAL_SCRIPTS', 'http://www.resaline.fr/js/DATA/fr/');




/**
  * Enqueue plugin style-file
  */
wp_enqueue_style( 'resaline_style', RESALINE_PLUGIN_URL . '/assets/css/resaline.css', array() );

/** If you hardcode a WP.com API key here, all key config screens will be hidden */
if ( defined('WPCOM_API_KEY') )
	$wpcom_api_key = constant('WPCOM_API_KEY');
else
	$wpcom_api_key = '';

if ( is_admin() )
	require_once dirname( __FILE__ ) . '/admin.php';


function resaline_get_key() {
	global $wpcom_api_key;
	if ( !empty($wpcom_api_key) )
		return $wpcom_api_key;
	return get_option('resaline_account_id');
}

/*
*  Toolbox function
*
*/
	// Returns array with headers in $response[0] and body in $response[1]
	function resaline_http_post($request, $host, $path, $port = 80, $ip=null) {
		global $wp_version;

		$resaline_ua = "WordPress/{$wp_version} | ";
		$resaline_ua .= 'resaline/' . constant( 'RESALINE_VERSION' );

		$resaline_ua = apply_filters( 'resaline_ua', $resaline_ua );

		$content_length = strlen( $request );

		$http_host = $host;
		// use a specific IP if provided
		// needed by resaline_check_server_connectivity()
		if ( $ip && long2ip( ip2long( $ip ) ) ) {
			$http_host = $ip;
		} else {
			$http_host = $host;
		}
		
		// use the WP HTTP class if it is available
		if ( function_exists( 'wp_remote_post' ) ) {
			$http_args = array(
				'body'			=> $request,
				'headers'		=> array(
					'Content-Type'	=> 'application/x-www-form-urlencoded; ' .
										'charset=' . get_option( 'blog_charset' ),
					'Host'			=> $host,
					'User-Agent'	=> $resaline_ua
				),
				'httpversion'	=> '1.0',
				'timeout'		=> 15
			);
			$resaline_url = "http://{$http_host}{$path}";
			$response = wp_remote_post( $resaline_url, $http_args );
			if ( is_wp_error( $response ) )
				return '';

			return array( $response['headers'], $response['body'] );
		} else {
			$http_request  = "POST $path HTTP/1.0\r\n";
			$http_request .= "Host: $host\r\n";
			$http_request .= 'Content-Type: application/x-www-form-urlencoded; charset=' . get_option('blog_charset') . "\r\n";
			$http_request .= "Content-Length: {$content_length}\r\n";
			$http_request .= "User-Agent: {$resaline_ua}\r\n";
			$http_request .= "\r\n";
			$http_request .= $request;
			
			$response = '';
			if( false != ( $fs = @fsockopen( $http_host, $port, $errno, $errstr, 10 ) ) ) {
				fwrite( $fs, $http_request );

				while ( !feof( $fs ) )
					$response .= fgets( $fs, 1160 ); // One TCP-IP packet
				fclose( $fs );
				$response = explode( "\r\n\r\n", $response, 2 );
			}
			return $response;
		}
	}

	function resaline_microtime() {
		$mtime = explode( ' ', microtime() );
		return $mtime[1] + $mtime[0];
	}

	function resaline_cmp_time( $a, $b ) {
		return $a['time'] > $b['time'] ? -1 : 1;
	}

/*
 * End toolbox
 */
	


/**
* Resaline init
 */
function init() {
	// Set up localisation
	$locale = apply_filters( 'plugin_locale', get_locale(), 'resaline' );
	
	load_textdomain( 'resaline', WP_LANG_DIR . "/resaline/i18n/resaline-$locale.mo" );

	// Load admin specific MO files
	if ( is_admin() ) {
		if (file_exists(WP_LANG_DIR . "/resaline/i18n/resaline-admin-$locale.mo")){
			load_textdomain( 'resaline', WP_LANG_DIR . "/resaline/i18n/resaline-admin-$locale.mo" );
			load_textdomain( 'resaline', RESALINE_PLUGIN_URL . "/i18n/resaline-admin-$locale.mo" );
		} else {
			//If not traducted for now then pick english version
			load_textdomain( 'resaline', WP_LANG_DIR . "/resaline/i18n/resaline-admin-en_US.mo" );
			load_textdomain( 'resaline', RESALINE_PLUGIN_URL . "/i18n/resaline-admin-en_US.mo" );
		}
		
	}

	load_plugin_textdomain( 'resaline', false, dirname( plugin_basename( __FILE__ ) ) . "/i18n" );
}


/* Catch and replace all resaline_calendar shortcodes */
function resaline_shortcode( $atts, $content = null ) {
	/*extract( shortcode_atts( array(
	    'class' => 'caption',
	    ), $atts ) );
	*/

	$cal = unserialize(get_option('resaline_calendar_'.$atts['id']));
	if ($cal){
		
		$file =	str_replace("datadyn.js", "caletab.html", $cal->file);
		$file =	str_replace("/I", "/", $file);

		$var_back = get_option('resaline_frame_background');
		$var_height = get_option('resaline_frame_height');
		$var_length = get_option('resaline_frame_length');

		$background =  empty($var_back) ?  'transparent' : '#'.$var_back ;
		$height =  empty($var_height) ?  '100%' : $var_height.'px' ;
		$length =  empty($var_length) ?  '800px' : $var_length.'px' ;

		$return = "<iframe src='{$file}' style=' width: ". $length ."; height: ". $height . "; background-color: ". $background ."' >";
		$return .= "<a href='{$file}' target='_blank' style='text-decoration:underline; display: block'> <p style='text-align: center'>- Calendrier de r&eacute;servation Resaline -</p> </a>";
		$return .= "</iframe>";

		/*$return  =  '<div id="contReservation" ></div>';
		$return .=  '<script type="text/javascript" src="'. BASE_RESAL_SCRIPTS .'prototype.js"></script>';
		$return .=  '<script type="text/javascript" src="'. $cal->file .'"></script>';*/
	} else {
		$return = '';
	}
	
	return $return;
}


add_action( 'init', 'init' , 0 );
add_shortcode( 'resaline_calendar', 'resaline_shortcode' );

?>