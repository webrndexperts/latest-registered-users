<?php
/*
Plugin Name: Latest Registered Users
Author:RND Experts
Author URI: http://rndexperts.com/
Description: Add a sortable columns to the users list to show last login date, registration date and also export users to csv file.
Version: 1.1
Text Domain: latest-registered-users
License: MIT License (MIT)
Network: true
Copyright 2020-2021 

This file is part of Latest Registered Users, a plugin for WordPress.

Latest Registered Users is free software: you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation, either version 2 of the License, or
(at your option) any later version.

Latest Registered Users is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with WordPress.  If not, see <http://www.gnu.org/licenses/>.

*/



class RNDLRU {
	public $users;

	/**
	 * Let's get this party started
	 *
	 * @since 1.1
	 * @access public
	 */

	public function __construct() {
		add_action( 'init', array( &$this, 'rnd_init' ) );
	}


	/**
	 * All init functions
	 *
	 * @since 1.1
	 * @access public
	 */

	public function rnd_init() {
		add_filter( 'wp_login', array( $this,'rnd_wp_login'),10,99 );
		add_filter( 'user_register', array( $this,'rnd_user_register'),10,99 );
		add_filter( 'manage_users_columns', array( $this,'rnd_users_columns') );
		add_action( 'manage_users_custom_column',  array( $this ,'rnd_users_custom_column'), 10, 3);
		add_filter( 'manage_users_sortable_columns', array( $this ,'rnd_users_sortable_columns') );
		add_filter( 'request', array( $this ,'rnd_users_orderby_column') );
		add_action( 'plugins_loaded', array( $this ,'rnd_load_this_textdomain') );
		// Add Button into admin side user list
		add_action('admin_footer',  array( $this ,'rnd_export_users'));
		// Use your hidden "action" field value when adding the actions
		add_action( 'admin_post_nopriv_my_simple_form',  array( $this ,'rnd_handle_form_submit') );
		add_action( 'admin_post_my_simple_form',  array( $this ,'rnd_handle_form_submit') );
		// includes files from includes folder 
		$this->rnd_includeProductFiles();

	}
	
	public function rnd_includeProductFiles(){
		require_once( __DIR__ . "/includes/rndlru-users.php");
		require_once( __DIR__ ."/includes/rndlru-csv.php");
		$this->users = new RNDUsers();
	}
	
	/**
	 * Update the login timestamp.
	 *
	 * @access public
	 *
	 * @param  string $user_login The user's login name.
	 *
	 * @return void
	 */
	public function rnd_wp_login( $user_login ) {
		$user = get_user_by( 'login', $user_login );
		update_user_meta( $user->ID, 'lastlogindate', time() );
	}
	
	/**
	 * Set default data for new users.
	 *
	 * @author Konstantin Obenland
	 * @since  3 - 12.09.2019
	 * @access public
	 *
	 * @param int $user_id The user ID.
	 */
	public function rnd_user_register( $user_id ) {
		update_user_meta( $user_id, 'lastlogindate', 0 );
	}
	/**
	 * Registers column for display
	 *
	 * @since 2.0
	 * @access public
	 */

	public static function rnd_users_columns($columns) {
		$columns['registerdate'] = _x('Registered', 'user', 'latest-registered-users');
		$columns['lastlogindate'] = _x('Last Login', 'user', 'latest-registered-users');
		return $columns;
	}
	
	

	/**
	 * Handles the registered date column output.
	 * 
	 * This uses the same code as column_registered, which is why
	 * the date isn't filterable.
	 *
	 * @since 2.0
	 * @access public
	 *
	 * @global string $mode
	 */

	public static function rnd_users_custom_column( $value, $column_name, $user_id ) {

		global $mode;
		if( isset($_REQUEST['mode']) ){
			if( !empty($_REQUEST['mode']) ){
				$mode = filter_var($_REQUEST['mode'], FILTER_SANITIZE_STRING);
			}else{
				$mode = 'list';
			}
		}else{
			$mode = 'list';
		}
		
		if('lastlogindate' === $column_name){
			$lastlogindate      = __( 'Never.', 'latest-registered-users' );
			$last_login = (int) get_user_meta( $user_id, 'lastlogindate', true );
			if ( is_multisite() && ( 'list' == $mode ) ) {
				$formated_date = __( 'Y/m/d' );
			} else {
				$formated_date = __( 'Y/m/d g:i:s a' );
			}
			if ( $last_login ) {
				$lastlogindate = '<span>'. date_i18n( $formated_date, $last_login ) .'</span>' ;
			}
			return $lastlogindate;
			
		}elseif ( 'registerdate' != $column_name ) {
			return $value;
		} else {
			$user = get_userdata( $user_id );

			if ( is_multisite() && ( 'list' == $mode ) ) {
				$formated_date = __( 'Y/m/d' );
			} else {
				$formated_date = __( 'Y/m/d g:i:s a' );
			}
			
			$registered   = strtotime(get_date_from_gmt($user->user_registered));
			$registerdate = '<span>'. date_i18n( $formated_date, $registered ) .'</span>' ;

			return $registerdate;
		}
	}

	/**
	 * Makes the column sortable
	 *
	 * @since 1.0
	 * @access public
	 */

	public static function rnd_users_sortable_columns($columns) {
		$custom = array(
			// meta column id => sortby value used in query
			'registerdate'    => 'registered',
			'lastlogindate'    => 'Last Login',
		);
		return wp_parse_args($custom, $columns);
	}

	/**
	 * Calculate the order if we sort by date.
	 *
	 * @since 1.0
	 * @access public
	 */
	public static function rnd_users_orderby_column( $vars ) {
		if ( isset( $vars['orderby'] ) && 'registerdate' == $vars['orderby'] ) {
			$vars = array_merge( $vars, array(
				'meta_key' => 'registerdate',
				'orderby'  => 'meta_value'
			) );
		}
		if ( isset( $vars['orderby'] ) && 'lastlogindate' == $vars['orderby'] ) {
			$vars = array_merge( $vars, array(
				'meta_key' => 'lastlogindate',
				'orderby'  => 'meta_value_num'
			) );
		}
		return $vars;
	}

	/**
	 * Internationalization - We're just going to use the language packs for this.
	 *
	 * @since 1.1
	 * @access public
	 */
	public function rnd_load_this_textdomain() {
		load_plugin_textdomain( 'latest-registered-users' );
	}
	

	public function rnd_export_users() {
		$screen = get_current_screen();
		// Only add to users.php page
		if ( $screen->id != "users" )
			return;
		?>
			<script type="text/javascript">
				jQuery(document).ready( function($) {
					jQuery('.tablenav.top .clear, .tablenav.bottom .clear').before('<form action="<?php echo esc_url( admin_url('admin-post.php') ); ?>" method="POST"><input type="hidden" id="export_csv" name="rnd_export_csv" value="1" /><input type="hidden" name="action" value="my_simple_form"><input class="button button-primary user_export_button" type="submit" name="rnd_export_submit" value="<?php esc_attr_e('Export Users list as CSV', 'latest-registered-users');?>" /></form>');
				});
			</script>
		<?php
	}
	
	public function rnd_handle_form_submit() {
		if(isset($_POST['rnd_export_submit'])){
			require_once ABSPATH . 'wp-admin/includes/user.php';
			/** SECURITY: Only valid column names. */
			$allowlist = $this->users->get_all_columns();

			/** SECURITY: Do not export sensitive data, even if they are on the allow list. */
			$denylist = array(
				'user_pass',
				'user_activation_key',
				'session_tokens',
				'wp_user-settings',
				'wp_user-settings-time',
				'wp_capabilities',
				'community-events-location',
			);

			/** Get selected columns. */
			$columns = get_option( 'rndlru_columns' );
			$columns = $columns ? $columns : $allowlist; /** Empty = All. */

			/**
			 * Delimiter / Enclosure.
			 */
			$delimiter_char      = null;
			$enclosure_char      = null;
			$has_custom_settings = get_option( 'rndlru_use_custom_csv_settings' );
			if ( 'yes' === $has_custom_settings ) {
				$delimiter_char = get_option( 'rndlru_field_separator' );
				$enclosure_char = get_option( 'rndlru_text_qualifier' );
				if ( 'custom' === $delimiter_char ) {
					$delimiter_char = get_option( 'rndlru_custom_field_separator' );
				}
				if ( 'custom' === $enclosure_char ) {
					$enclosure_char = get_option( 'rndlru_custom_text_qualifier' );
				}
			}

			/** Get selected users (ids). */
			$roles = get_option( 'rndlru_roles' );
			$ids   = $roles ? $this->users->get_user_ids_by_roles( $roles ) : $this->users->get_user_ids();

			/** Execute this block on a try-catch because CSV lib can throw exceptions. */
			try {
				$csv = ( new RNDCSV() )
				->set_filename( 'php://output' )
				->set_columns( $columns )
				->set_delimiter( $delimiter_char )
				->set_enclosure( $enclosure_char )
				->set_allowlist( $allowlist )
				->set_denylist( $denylist )
				->check_errors();

				/**
				 * Tell browser that response is a file-stream.
				 */
				$this->rnd_send_file_stream_http_headers();

				/** Process in batches of 1k users */
				$page_size  = 1000;
				$page_count = floor( ( count( $ids ) - 1 ) / $page_size ) + 1;
				for ( $i = 0; $i < $page_count; $i ++ ) {
					$ids_page = array_splice( $ids, 0, $page_size );
					$data     = $this->users->get_users_data( $ids_page );
					$csv->write( $data );
				}

				/**
				 * Close stream and quit.
				 */
				$csv->close();
				exit();
			} catch ( Exception $e ) {
				$this->wc->add_error( $e->getMessage() );
			}
		}

		// This is where you will control your form after it is submitted, you have access to $_POST here.

	}
	/**
	 * Custom field type.
	 *
	 * @param array $value An associative array with the field data.
	 */
	public function rnd_select_with_text( $value ) {
		$option_value = $value['value'];
		?>
		<tr valign="top">
		<th scope="row" class="titledesc">
			<label for="<?php echo esc_attr( $value['id'] ); ?>"><?php echo esc_html( $value['title'] ); ?></label>
		</th>
		<td class="forminp forminp-<?php echo esc_attr( sanitize_title( $value['type'] ) ); ?>">
			<select
				class="<?php echo esc_attr( $value['class'] ); ?>"
				name="<?php echo esc_attr( $value['id'] ); ?>"
			>
		<?php
		/** Scan array. */
		foreach ( $value['options'] as $key => $val ) {
			$selected = $key === $option_value ? 'selected="selected" ' : '';
			echo '<option value="' . esc_attr( $key ) . '" ' . esc_attr( $selected ) . '>' . esc_attr( $val ) . '</option>' . "\n";
		}
		echo "</select>\n";

		/** Text input wrapper. */
		echo '<span class="select-with-text-wrapper">';

		/** Label. */
		echo '<span>' . esc_attr( $value['text_title'] ) . '</span>';

		/** Input. */
		$saved_text_value = get_option( $value['text_id'] );
		echo '<input
				maxlength=1
				type="text"
				name="' . esc_attr( $value['text_id'] ) . '"
				value="' . esc_attr( $saved_text_value ) . '"
			/>';

		/** End wrapper. */
		echo '</span>';

		echo '</td></tr>';
	}
	/**
	 * Send HTTP headers for download CSV file.
	 */
	private function rnd_send_file_stream_http_headers() {
		header( 'Content-Encoding: UTF-8' );
		header( 'Content-Type: text/csv; charset=UTF-8' );
		header( 'Content-Disposition: attachment; filename=' . date( 'Y-m-d-H-i_s' ) . '-users.csv' );
		header( 'Pragma: no-cache' );
		header( 'Expires: 0' );
	}
}

new RNDLRU();