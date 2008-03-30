<?php /*

**************************************************************************

Plugin Name:  Dashboard Widget Manager
Plugin URI:   http://www.viper007bond.com/wordpress-plugins/dashboard-widget-manager/
Description:  Allows you to re-order as well as hide widgets on the WordPress 2.5+ dashboard.
Version:      1.1.0
Author:       Viper007Bond
Author URI:   http://www.viper007bond.com/

**************************************************************************/

class DashboardWidgetManager {
	var $stub = 'manage-widgets';
	var $parent = 'index.php';
	var $sidebar = 'wp_dashboard'; // This should never change as it's defined by WordPress

	function DashboardWidgetManager() {
		// Load up the localization file if we're using WordPress in a different language
		// Place it in this plugin's folder and name it "dashwidman-[value in wp-config].mo"
		load_plugin_textdomain( 'dashwidman', '/wp-content/plugins/dashboard-widget-manager' );

		// Hook into the admin menu
		add_action( 'admin_menu', array(&$this, 'AddAdminMenus') );

		// We need to add a filter to the dashboard, but only later in the page load. "activity_box_end" works nicely.
		add_action( 'activity_box_end', array(&$this, 'register_pre_option_sidebars_widgets') );

		// A semi-dirty way of only doing stuff if we're on this plugin's page
		if ( $this->parent == basename($_SERVER['PHP_SELF']) && $_GET['page'] == $this->stub ) {
			require_once(ABSPATH . 'wp-admin/includes/dashboard.php');
			require_once(ABSPATH . 'wp-admin/includes/widgets.php');

			wp_enqueue_script( array( 'wp-lists', 'admin-widgets' ) );

			add_action( 'admin_head',  array(&$this, 'admin_head') );
			add_filter( 'pre_option_sidebars_widgets', array(&$this, 'pre_option_sidebars_widgets') );

			if ( 'POST' == $_SERVER['REQUEST_METHOD'] && isset($_POST['sidebar']) && $_POST['sidebar'] == $this->sidebar )
				add_action( 'init', array(&$this, 'HandleFormPOST') );
		}
	}


	// Register our pre_option_sidebars_widgets hook (in it's own function so it can be called later to avoid screwing up the total widget count)
	function register_pre_option_sidebars_widgets() {
		add_filter( 'pre_option_sidebars_widgets', array(&$this, 'pre_option_sidebars_widgets') );
	}


	// Register our new menu page with WordPress
	function AddAdminMenus() {
		add_submenu_page( $this->parent, __('Dashboard Widget Manager', 'dashwidman'), __('Widgets'), 'manage_options', $this->stub, array(&$this, 'ManagePage') );
	}


	// Some CSS to hide stuff in widgets we don't need
	function admin_head() {
		echo "	<style type='text/css'>.widget-control p, .widget-control-save { display: none; } ul.widget-control-list div.widget-control-actions { border-top: none; }</style>\n";
	}


	// This filter function modifies the value that get_option('sidebars_widgets') returns since dynamic_sidebar() lacks a hook
	// This filter function is only registered when the manage page is active -- it doesn't affect it otherwise
	function pre_option_sidebars_widgets() {
		global $user_ID;

		// Remove this function from the filer list so we can get the real value...
		remove_filter( 'pre_option_sidebars_widgets', array(&$this, 'pre_option_sidebars_widgets') );

		// Grab that real value...
		$sidebars_widgets = get_option('sidebars_widgets');

		// And add this filter back into place
		add_filter( 'pre_option_sidebars_widgets', array(&$this, 'pre_option_sidebars_widgets') );

		if ( empty($GLOBALS['wp_dashboard_sidebars'][$this->sidebar] ) && function_exists('wp_dashboard_setup') ) wp_dashboard_setup();

		$widgets = get_option( 'dashboard_widget_order' );

		// If we have a custom widget order, use it, otherwise use the default order
		if ( is_array($widgets[$user_ID]) ) {
			$sidebars_widgets[$this->sidebar] = $widgets[$user_ID];
		} else {
			$sidebars_widgets[$this->sidebar] = $GLOBALS['wp_dashboard_sidebars'][$this->sidebar];
		}

		return $sidebars_widgets;
	}


	// Handle the POST results from our form
	function HandleFormPOST() {
		check_admin_referer( 'edit-sidebar_' . $_POST['sidebar'] );

		global $user_ID;

		$widgets = array();
		$widgets[$user_ID] = $_POST['widget-id'];

		update_option( 'dashboard_widget_order', $widgets );
		
		wp_redirect( add_query_arg( 'message', 'updated' ) );
		exit();

	}


	// Output the contents of our manage page. It's based heavily on /wp-admin/widgets.php
	function ManagePage() {
		global $user_ID, $wp_registered_widgets, $sidebars_widgets, $wp_registered_widget_controls, $wp_registered_sidebars;

		// Reset the widgets to the defaults
		if ( 'defaults' == $_GET['message'] ) {
			$widgets = get_option( 'dashboard_widget_order' );
			unset( $widgets[$user_ID] );
			update_option( 'dashboard_widget_order', $widgets );
		}

		$sidebars_widgets = wp_get_sidebars_widgets();
		if ( empty( $sidebars_widgets ) )
			$sidebars_widgets = wp_get_widget_defaults();

		// Dump the list of widgets that contains the standard sidebar ones and remake it with just the dashboard widgets
		$wp_registered_widgets = array();
		wp_dashboard_setup();

		// Hack to make an "Edit" link show up on the recent comments widget so you can remove it
		wp_register_widget_control( 'dashboard_recent_comments', __( 'Recent Comments' ), array(&$this, 'DoNothing'), array(), array( 'widget_id' => 'dashboard_recent_comments' ) );

		$sidebar = $this->sidebar; // Just to use the same var as /wp-admin/widgets.php for consistency

		$sidebar_widget_count = count($sidebars_widgets[$sidebar]);
		$sidebar_info_text = __ngettext( 'You are using %1$s widget on the dashboard.', 'You are using %1$s widgets on the dashboard.', $sidebar_widget_count, 'dashwidman' );
		$sidebar_info_text = sprintf( wp_specialchars( $sidebar_info_text ), "<span id='widget-count'>$sidebar_widget_count</span>", $wp_registered_sidebars[$sidebar]['name'] );

		# DEBUG
		/*
		echo '<pre>';
		//print_r( $sidebars_widgets );
		//print_r( $wp_registered_widgets );
		//print_r( $wp_registered_widget_controls );
		echo '</pre>';
		*/

		$messages = array(
			'updated' => __('Changes saved.'),
			'defaults' => __("Dashboard reset to it's default configuration.", 'dashwidman'),
		);

		if ( isset($_GET['message']) && isset($messages[$_GET['message']]) ) : ?>

<div id="message" class="updated fade"><p><?php echo $messages[$_GET['message']]; ?></p></div>

<?php endif; ?>

<div class="wrap">

	<h2><?php _e( 'Dashboard Widgets', 'dashwidman' ); ?></h2>

	<p><?php printf( __( "If you'd like to reset the dashboard to the default configuration, just <a href='%s'>click here</a>.", 'dashwidman'), add_query_arg( 'message', 'defaults' ) ); ?></p>

	<div class="widget-liquid-left-holder">
	<div id="available-widgets-filter" class="widget-liquid-left">
		<h3><?php _e('Available Widgets'); ?></h3>
	</div>
	</div>

	<div id="available-sidebars" class="widget-liquid-right">
		<h3><?php _e('Current Widgets'); ?></h3>
	</div>

	<div id="widget-content" class="widget-liquid-left-holder">

		<div id="available-widgets" class="widget-liquid-left">

			<?php wp_list_widgets(); // This lists all the widgets for the query ( $show, $search ) ?>

			<div class="nav">
				<p class="pagenav">
					&nbsp;
				</p>
			</div>
		</div>
	</div>

	<form id="widget-controls" action="" method="post">

	<div id="current-widgets-head" class="widget-liquid-right">

		<div id="sidebar-info">
			<p><?php echo $sidebar_info_text; ?></p>
			<p><?php if ( count($wp_registered_widgets) > $sidebar_widget_count ) _e( 'Add more from the Available Widgets section.' ); ?></p>
		</div>

	</div>

	<div id="current-widgets" class="widget-liquid-right">
		<div id="current-sidebar">

			<?php wp_list_widget_controls( $sidebar ); // Show the control forms for each of the widgets in this sidebar ?>

		</div>

		<p class="submit">
			<input type="hidden" id='sidebar' name='sidebar' value="<?php echo $sidebar; ?>" />
			<input type="hidden" id="generated-time" name="generated-time" value="<?php echo time() - 1199145600; // Jan 1, 2008 ?>" />
			<input type="submit" name="save-widgets" value="<?php _e( 'Save Changes' ); ?>" />
<?php
			wp_nonce_field( 'edit-sidebar_' . $sidebar );
?>
		</p>
	</div>

	</form>

</div>

<?php
	}


	// Do nothing (used where we need to call a function but not have it do anything)
	function DoNothing() { }
}

// Start this plugin once all other plugins are fully loaded
add_action( 'plugins_loaded', create_function( '', 'global $DashboardWidgetManager; $DashboardWidgetManager = new DashboardWidgetManager();' ) );


// Cause a fatal error on activation on purpose if the user's WordPress version is too old
if ( !file_exists(ABSPATH . 'wp-admin/includes/dashboard.php') )
	exit( sprintf( __('Dashboard Widget Manager requires WordPress 2.5.0 or newer. <a href="%s" target="_blank">Please update!</a>', 'dashwidman'), 'http://codex.wordpress.org/Upgrading_WordPress' ) );

?>