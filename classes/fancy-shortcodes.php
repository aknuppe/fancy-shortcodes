<?php

if ( ! class_exists( 'Fancy_Shortcodes' ) ) {

	/**
	 * Main / front controller class
	 *
	 * WordPress_Plugin_Skeleton is an object-oriented/MVC base for building WordPress plugins
	 */
	class Fancy_Shortcodes extends FSH_Module {
		protected static $readable_properties  = array();    // These should really be constants, but PHP doesn't allow class constants to be arrays
		protected static $writeable_properties = array();
		protected $modules;
		
		protected $shortcode_map;
		protected $button_slug;
		const VERSION    = FANCY_SH_VERSION;
		const DEBUG_MODE = FANCY_SH_DEBUG;
		const PREFIX     = 'fsh_';

		/*
		 * Magic methods
		 */

		/**
		 * Constructor
		 *
		 * @mvc Controller
		 */
		protected function __construct() {
			self::$readable_properties = array( 'shortcode_map', 'button_slug' );
			$this->shortcode_map = new FSH_Shortcode_Map;
			$this->button_slug   = 'fsh_mce_btn';
			
			$this->register_hook_callbacks();
			
			$this->modules = array(
				'FSH_Settings'    => FSH_Settings::get_instance(),
			);

		}


		/*
		 * Static methods
		 */

		/**
		 * Enqueues CSS, JavaScript, etc
		 *
		 * @mvc Controller
		 */
		public static function load_resources() {
			/*wp_register_script(
				self::PREFIX . 'wordpress-plugin-skeleton',
				plugins_url( 'js/wordpress-plugin-skeleton.js', dirname( __FILE__ ) ),
				array( 'jquery' ),
				self::VERSION,
				true
			);

			wp_register_style(
				self::PREFIX . 'admin',
				plugins_url( 'css/admin.css', dirname( __FILE__ ) ),
				array(),
				self::VERSION,
				'all'
			);

			if ( is_admin() ) {
				wp_enqueue_style( self::PREFIX . 'admin' );
			} else {
				wp_enqueue_script( self::PREFIX . 'wordpress-plugin-skeleton' );
			}*/
		}

		/**
		 * Clears caches of content generated by caching plugins like WP Super Cache
		 *
		 * @mvc Model
		 */
		protected static function clear_caching_plugins() {
			// WP Super Cache
			if ( function_exists( 'wp_cache_clear_cache' ) ) {
				wp_cache_clear_cache();
			}

			// W3 Total Cache
			if ( class_exists( 'W3_Plugin_TotalCacheAdmin' ) ) {
				$w3_total_cache = w3_instance( 'W3_Plugin_TotalCacheAdmin' );

				if ( method_exists( $w3_total_cache, 'flush_all' ) ) {
					$w3_total_cache->flush_all();
				}
			}
		}


		/*
		 * Instance methods
		 */

		/**
		 * Prepares sites to use the plugin during single or network-wide activation
		 *
		 * @mvc Controller
		 *
		 * @param bool $network_wide
		 */
		public function activate( $network_wide ) {
			if ( $network_wide && is_multisite() ) {
				$sites = wp_get_sites( array( 'limit' => false ) );

				foreach ( $sites as $site ) {
					switch_to_blog( $site['blog_id'] );
					$this->single_activate( $network_wide );
				}

				restore_current_blog();
			} else {
				$this->single_activate( $network_wide );
			}
		}

		/**
		 * Runs activation code on a new WPMS site when it's created
		 *
		 * @mvc Controller
		 *
		 * @param int $blog_id
		 */
		public function activate_new_site( $blog_id ) {
			switch_to_blog( $blog_id );
			$this->single_activate( true );
			restore_current_blog();
		}

		/**
		 * Prepares a single blog to use the plugin
		 *
		 * @mvc Controller
		 *
		 * @param bool $network_wide
		 */
		protected function single_activate( $network_wide ) {
			foreach ( $this->modules as $module ) {
				$module->activate( $network_wide );
			}

			flush_rewrite_rules();
		}

		/**
		 * Rolls back activation procedures when de-activating the plugin
		 *
		 * @mvc Controller
		 */
		public function deactivate() {
			foreach ( $this->modules as $module ) {
				$module->deactivate();
			}

			flush_rewrite_rules();
		}

		/**
		 * Register callbacks for actions and filters
		 *
		 * @mvc Controller
		 */
		public function register_hook_callbacks() {
			add_action( 'wpmu_new_blog',         __CLASS__ . '::activate_new_site' );
			add_action( 'wp_enqueue_scripts',    __CLASS__ . '::load_resources' );
			add_action( 'admin_enqueue_scripts', __CLASS__ . '::load_resources' );

			add_action( 'init',                  array( $this, 'init' ) );
			add_action( 'init',                  array( $this, 'check_for_updates' ), 11 );
			add_action( 'init',                  array( $this, 'upgrade' ), 11 );
			
			add_action( 'admin_head',            array( $this, 'add_tinymce_button' ) );
			add_action( 'admin_footer',          array( $this, 'add_js_shortcodes'));
		}

		/**
		 * Initializes variables
		 *
		 * @mvc Controller
		 */
		public function init() {
			try {
				if( ! isset( $this->shortcode_map ) )
					return;
					
				// Add every shortcode
				foreach($this->shortcode_map->map as $shortcode => $content) {
					add_shortcode( $shortcode, array($this->shortcode_map, 'shortcode_'.$shortcode) );	
				}
			} catch ( Exception $exception ) {
				//error
			}
		}
		
		public function add_js_shortcodes()
		{
			
			echo '<script type="text/javascript">
			var shortcodes_button = new Array();';
	
			$count = 0;
	
			foreach($this->shortcode_map->map as $content)
			{
				
				if( !isset($content["add_to_editor"]) || true === $content["add_to_editor"] ) {
					$editor_content = $content['content'];
					$tag_name = $content['tag_name'];
					$category = $content['category'];
					echo "shortcodes_button[{$count}] = {'tag' : '[{$tag_name}]', 'content' : '{$editor_content}', 'category' : '{$category}' };";
					$count++;
				}
			}
	
			echo '</script>';
		}
		
		public function add_tinymce_button()
		{
			if ( !current_user_can( 'edit_posts' ) && !current_user_can( 'edit_pages' ) ) {
				return;
			}
	
			if ( true == get_user_option( 'rich_editing' ) ) {
				add_filter( 'mce_external_plugins', array( $this, 'add_new_tinymce_plugin' ) );
				add_filter( 'mce_buttons', array( $this, 'register_new_button' ) );
			}
		}
		
		public function add_new_tinymce_plugin( $plugins_array )
		{
			if ( floatval( get_bloginfo( 'version' ) ) >= 3.9 ) { //tinymce 4
				$plugins_array[ $this->button_slug ] = plugins_url('/assets/js/mce/shortcode-tinymce-button.js', FANCY_SH_PLUGIN_FILE);
			}
			else {
				$plugins_array[ $this->button_slug ] = plugins_url('/assets/js/mce/shortcode-tinymce-button.old.js', FANCY_SH_PLUGIN_FILE);
			}
			return $plugins_array;
		}
		
		/*
		*
		*/
		public function register_new_button( $buttons )
		{
			array_push($buttons, 'separator', $this->button_slug);
			return $buttons;
		}

		
		/**
		 * checks for updates (automaticly)
		 *
		 * @mvc Controller
		 */
		public function check_for_updates() {
			if ( is_admin() ) { 
				require_once(plugin_dir_path( FANCY_SH_PLUGIN_FILE ).'classes/autoupdate.php');

				if (!function_exists('get_plugin_data')) {
					require_once(ABSPATH . 'wp-admin/includes/plugin.php');
				}
				
				$current_plugin = get_plugin_data(FANCY_SH_PLUGIN_FILE, $markup = true, $translate = true);
				$current_version = $current_plugin['Version'];
				//echo "url: ".$this->modules['FSH_Settings']->settings['update-remote-path'];
				new FSH_Autoupdate($current_version, $this->modules['FSH_Settings']->settings['update-remote-path'], FANCY_SH_PLUGIN_SLUG);
				
			}
		}

		/**
		 * Checks if the plugin was recently updated and upgrades if necessary
		 *
		 * @mvc Controller
		 *
		 * @param string $db_version
		 */
		public function upgrade( $plugin_version = 0 ) {
			if ( version_compare( $this->modules['FSH_Settings']->settings['plugin-version'], self::VERSION, '==' ) ) {
				return;
			}

			foreach ( $this->modules as $module ) {
				$module->upgrade( $this->modules['FSH_Settings']->settings['plugin-version'] );
			}

			$this->modules['FSH_Settings']->settings = array( 'plugin-version' => self::VERSION );
			self::clear_caching_plugins();
		}

		/**
		 * Checks that the object is in a correct state
		 *
		 * @mvc Model
		 *
		 * @param string $property An individual property to check, or 'all' to check all of them
		 * @return bool
		 */
		protected function is_valid( $property = 'all' ) {
			return true;
		}
	} // end WordPress_Plugin_Skeleton
}
