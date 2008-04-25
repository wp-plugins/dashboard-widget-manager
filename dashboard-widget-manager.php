<?php /*

**************************************************************************

Plugin Name:  Dashboard Widget Manager
Plugin URI:   http://www.viper007bond.com/wordpress-plugins/dashboard-widget-manager/
Description:  Greatly enhances your WordPress 2.5+ dashboard by allowing widget re-ordering and storage of preferences on a per-user basis.
Version:      1.3.0
Author:       Viper007Bond
Author URI:   http://www.viper007bond.com/

**************************************************************************/

class DashboardWidgetManager {
	var $version = '1.3.0';
	var $stub = 'manage-widgets';
	var $parent = 'index.php';
	var $sidebar = 'wp_dashboard'; // This should never change as it's defined by WordPress
	var $settings = array();
	var $editablewidgets = array();

	function DashboardWidgetManager() {
		if ( !file_exists(ABSPATH . 'wp-admin/includes/dashboard.php') ) return; // Requires WP 2.5+

		// Load up the localization file if we're using WordPress in a different language
		// Place it in this plugin's folder and name it "dashwidman-[value in wp-config].mo"
		load_plugin_textdomain( 'dashwidman', '/wp-content/plugins/dashboard-widget-manager' );


		// Check for an old version of this plugin that's been previously installed and upgrade if need be
		$this->settings = get_option( 'dashboard_widget_manager' );
		if ( !is_array($this->settings) )             $this->settings                = array();
		if ( !isset($this->settings['version']) )     $this->settings['version']     = '1.0.0'; // Force upgrade
		if ( !isset($this->settings['defaultuser']) ) $this->settings['defaultuser'] = 0;
		if ( !isset($this->settings['allcanedit']) )  $this->settings['allcanedit']  = 1;
		if ( version_compare($this->settings['version'], '1.3.0', '<') ) {
			// Reset the global widget options to their defaults as we coulda mucked them up in previous versions
			update_option( 'dashboard_widget_options', array() );
		}
		if ( version_compare($this->settings['version'], $this->version, '<') ) {
			$this->settings['version'] = $this->version;
			update_option( 'dashboard_widget_manager', $this->settings );
		}


		// Hook into the admin menu
		add_action( 'admin_menu', array(&$this, 'AddAdminMenus') );

		// We can stop here if this isn't the admin area
		if ( !is_admin() ) return;


		// For debugging
		/*
		if ( 'reset' == $_GET['dashwidman'] && current_user_can( 'manage_options' ) ) {
			update_option( 'sidebars_widgets', array( 'sidebar-1' => array( 'pages', 'calendar', 'archives', 'links', 'search', 'meta' ) ) );
			update_option( 'dashboard_widget_options', array() );
			update_option( 'dashboard_widget_order', array() );
			update_option( 'dashboard_widget_peruser_options', array() );
			update_option( 'dashboard_widget_manager', array() );
			wp_redirect( 'index.php ');
			exit();
		}
		*/


		// If our forms have been posted to, register the POST handling function
		if ( 'POST' == $_SERVER['REQUEST_METHOD'] && isset($_POST['dashwidman-action']) )
			add_action( 'init', array(&$this, 'HandleFormPOST') );


		// A semi-dirty way of only doing stuff if we're on the dashboard or this plugin's page
		if ( $this->parent == basename($_SERVER['PHP_SELF']) ) {
			add_action( 'wp_dashboard_setup', array(&$this, 'RegisterCustomWidgets') );
			add_action( 'wp_dashboard_setup', array(&$this, 'SetWidgetDescriptions'), 99 );

			// These hooks are for hacking in per-user widget options
			add_filter( 'pre_option_dashboard_widget_options', array(&$this, 'pre_option_dashboard_widget_options') );
			add_filter( 'update_option_dashboard_widget_options', array(&$this, 'update_option_dashboard_widget_options'), 10, 2 );
			add_filter( 'add_option_dashboard_widget_options', array(&$this, 'add_option_dashboard_widget_options'), 10, 2 );

			// We need to add a filter to the dashboard, but only later in the page load. "activity_box_end" works nicely.
			add_action( 'activity_box_end', array(&$this, 'register_pre_option_sidebars_widgets') );

			// Load up the RSS file to make sure any poorly coded widgets don't break. We're gonna need it anyway, so it doesn't hurt to load it early.
			require_once( ABSPATH . WPINC . '/rss.php' );

			// Dashboard only stuff
			if ( !isset($_GET['page']) ) {
				if ( 1 == $this->settings['allcanedit'] ) add_filter( 'user_has_cap', array(&$this, 'add_edit_dashboard_cap'), 15, 3 );
				if ( isset($_GET['edit']) )               add_action( 'init', array(&$this, 'CheckIfUserCanReallyEdit') );
			}

			// Widget management only stuff
			elseif ( $_GET['page'] == $this->stub ) {
				require_once( ABSPATH . 'wp-admin/includes/dashboard.php' );
				require_once( ABSPATH . 'wp-admin/includes/widgets.php' );
				require_once( ABSPATH . 'wp-admin/includes/template.php' );

				wp_enqueue_script( array( 'wp-lists', 'admin-widgets' ) );

				add_action( 'admin_head', array(&$this, 'HideWidgetControlsViaCSS') );

				add_filter( 'pre_option_sidebars_widgets', array(&$this, 'pre_option_sidebars_widgets') );
			}
		}


		// DASHBOARD WIDGET AUTHORS:
		// If you want your widget to be editable by ALL users, you need to use this filter to add your widget ID to this array. This is a safety precaution as this
		// plugin doesn't want to allow no-access users to edit the options of a widget that doesn't support per-user options. You also need to either use the
		// "dashboard_widget_options" option to store your options (this plugin will automatically make it per-user) or take care of making it a per-user on your own.
		$this->editablewidgets = apply_filters( 'dashwidman_safewidgets', array( 'dashboard_incoming_links', 'dashboard_primary', 'dashboard_secondary', 'dashboard_text' ) );
	}


	// Hack for just the dashboard so that current_user_can('edit_dashboard') always returns TRUE
	function add_edit_dashboard_cap( $allcaps, $cap ) {
		$allcaps['edit_dashboard'] = TRUE;
		return $allcaps;
	}


	// Register our pre_option_sidebars_widgets hook (in it's own function so it can be called later to avoid screwing up the total widget count)
	function register_pre_option_sidebars_widgets() {
		add_filter( 'pre_option_sidebars_widgets', array(&$this, 'pre_option_sidebars_widgets') );
	}


	// Register our new menu page with WordPress
	function AddAdminMenus() {
		$widgetcap = ( 1 == $this->settings['allcanedit'] ) ? 'read' : 'edit_dashboard';

		add_submenu_page( $this->parent, __('Dashboard Widget Manager', 'dashwidman'), __('Widgets'), $widgetcap, $this->stub, array(&$this, 'ManagePage') );
		add_options_page( __('Dashboard Widget Manager', 'dashwidman'), __('Dashboard Widgets', 'dashwidman'), 'manage_options', $this->stub, array(&$this, 'OptionsPage') );
	}


	// Register some custom widgets for increased dashboard functionality
	function RegisterCustomWidgets() {
		if ( !$widget_options = get_option( 'dashboard_widget_options' ) )
			$widget_options = array();

		wp_register_sidebar_widget( 'dashboard_text', __( 'Text' ), array(&$this, 'TextWidget') );
		wp_register_widget_control( 'dashboard_text', __( 'Text' ), array(&$this, 'TextWidgetControl'), array(), array( 'widget_id' => 'dashboard_text' ) );
		add_action( 'admin_head', array(&$this, 'TextWidgetCSS') );


		if ( !isset($widget_options['dashboard_rss']) || !is_array($widget_options['dashboard_rss']) ) {
			$widget_options['dashboard_rss'] = array(
				'link' => trailingslashit( get_bloginfo('url') ),
				'url' => get_bloginfo('rss2_url'),
				'title' => get_bloginfo('name'),
				'items' => 10,
				'show_summary' => 1,
				'show_author' => 1,
				'show_date' => 1
			);
			update_option( 'dashboard_widget_options', $widget_options );
		}

		$rsstitle = ( !empty($_GET['page']) && $_GET['page'] == $this->stub ) ? __( 'RSS' ) : $widget_options['dashboard_rss']['title'];

		wp_register_sidebar_widget( 'dashboard_rss', $rsstitle, 'wp_dashboard_empty', array( 'all_link' => $widget_options['dashboard_rss']['link'], 'feed_link' => $widget_options['dashboard_rss']['url'], 'width' => 'half', 'class' => 'widget_rss' ), 'wp_dashboard_cached_rss_widget', 'wp_dashboard_rss_output' );
		wp_register_widget_control( 'dashboard_rss', __( 'RSS' ), 'wp_dashboard_rss_control', array(), array( 'widget_id' => 'dashboard_rss', 'form_inputs' => array() ) );
	}


	// Some CSS to hide stuff in widgets we don't need
	function HideWidgetControlsViaCSS() {
		echo "	<style type='text/css'>.widget-control p, .widget-control-save { display: none; } ul.widget-control-list div.widget-control-actions { border-top: none; }</style>\n";
	}


	// When a user attempts to edit a widget on the dashboard but in reality lacks the 'edit_dashboard' cap,
	// abort if it's an unknown widget as we don't know how custom widgets handle their options (per-user or not)
	function CheckIfUserCanReallyEdit() {
		// Get the real value of current_user_can('edit_dashboard')
		remove_filter( 'user_has_cap', array(&$this, 'add_edit_dashboard_cap'), 15, 3 );
		$capcheck = current_user_can('edit_dashboard');
		add_filter( 'user_has_cap', array(&$this, 'add_edit_dashboard_cap'), 15, 3 );

		// If they can't really edit AND the widget isn't a known one, don't allow them to edit
		if ( !$capcheck && !in_array($_GET['edit'], $this->editablewidgets) )
			add_action( 'admin_notices', array(&$this, 'ShowNotSafeWidgetError') );
	}


	// Show an error message meant for low access users that they can't edit this unknown widget
	function ShowNotSafeWidgetError() {
		// Output a div with the ID of the widget. It'll break page validation, but at least the browser won't scroll down to the widget
		echo '<div id="' . htmlspecialchars($_GET['edit']) . '"></div>';

		echo '<div id="message" class="error"><p><strong>' . __( "Since this custom dashboard widget is unknown to Dashboard Widget Manager and you can't normally edit widgets due to a lack of permissions, you have been blocked from editing this widget.", 'dashwidman' ) . "</strong></p></div>\n</script>";

		unset( $_GET['edit'] );
	}


	function SetWidgetDescriptions() {
		global $wp_registered_widgets;

		if ( isset($wp_registered_widgets['dashboard_recent_comments']) )
			$wp_registered_widgets['dashboard_recent_comments']['description'] = __( 'The most recent comments' );
		if ( isset($wp_registered_widgets['dashboard_incoming_links']) )
			$wp_registered_widgets['dashboard_incoming_links']['description']  = __( 'A list of the latest sites to link to your blog', 'dashwidman' );
		if ( isset($wp_registered_widgets['dashboard_primary']) )
			$wp_registered_widgets['dashboard_primary']['description']         = __( 'The latest 2 posts from the WordPress development blog', 'dashwidman' );
		if ( isset($wp_registered_widgets['dashboard_secondary']) )
			$wp_registered_widgets['dashboard_secondary']['description']       = __( 'WordPress news and posts from various trusted sources', 'dashwidman' );
		if ( isset($wp_registered_widgets['dashboard_plugins']) )
			$wp_registered_widgets['dashboard_plugins']['description']         = __( 'Some of the plugins from the WordPress.org plugin repository', 'dashwidman' );
		if ( isset($wp_registered_widgets['dashboard_text']) )
			$wp_registered_widgets['dashboard_text']['description']            = __( 'Arbitrary text or HTML' );
		if ( isset($wp_registered_widgets['dashboard_rss']) )
			$wp_registered_widgets['dashboard_rss']['description']             = __( 'Entries from any RSS or Atom feed' );
		// http://wordpress.org/extend/plugins/stats/
		if ( isset($wp_registered_widgets['dashboard_stats']) )
			$wp_registered_widgets['dashboard_stats']['description']           = __( 'WordPress.com powered stats', 'dashwidman' );
	}


	// Since we're hacking a few get_option()'s via a filter, we need a way to sometimes get the real value. This is some code to do that.
	// Just pass it the option name and optionally the function and class name. The function defaults to "pre_option_setting_name" and the class to $this.
	function get_real_option( $setting, $function = NULL, $class = NULL ) {
		$hookname = 'pre_option_' . $setting;
		if ( NULL == $function ) $function = $hookname;
		if ( NULL == $class ) $class = $this;

		// Remove this function from the filer list so we can get the real value...
		remove_filter( $hookname, array(&$class, $function) );

		// Grab that real value...
		$value = get_option( $setting );

		// And add this filter back into place
		add_filter( $hookname, array(&$class, $function) );

		return $value;
	}


	// This filter function modifies the value that get_option('sidebars_widgets') returns since dynamic_sidebar() lacks a hook
	// This filter function is only registered when the dashboard widgets are being used -- it doesn't affect it otherwise
	function pre_option_sidebars_widgets() {
		global $user_ID;

		$sidebars_widgets = $this->get_real_option('sidebars_widgets');

		if ( empty($GLOBALS['wp_dashboard_sidebars'][$this->sidebar] ) && function_exists('wp_dashboard_setup') ) wp_dashboard_setup();

		$widgets = get_option( 'dashboard_widget_order' );

		// If the user has their own widgets array
		if ( isset($widgets[$user_ID]) && is_array($widgets[$user_ID]) )
			$sidebars_widgets[$this->sidebar] = $widgets[$user_ID];

		// If the default widgets are of a user and that user has a widgets array
		elseif ( 0 != $this->settings['defaultuser'] && isset($widgets[$this->settings['defaultuser']]) && is_array($widgets[$this->settings['defaultuser']]) )
			$sidebars_widgets[$this->sidebar] = $widgets[$this->settings['defaultuser']];

		// Still here? Then use an uncustomized dashboard
		else
			$sidebars_widgets[$this->sidebar] = $GLOBALS['wp_dashboard_sidebars'][$this->sidebar];

		return $sidebars_widgets;
	}


	// This filter function modifies the value that get_option('dashboard_widget_options') returns to allow per-user settings
	// This filter function is only registered when the dashboard widgets are being used -- it doesn't affect it otherwise
	function pre_option_dashboard_widget_options() {
		global $user_ID;

		// To make life easier and to not modifiy the original "dashboard_widget_options" structure,
		// per-user options are stored in a different options name
		$dashboard_widget_options = get_option('dashboard_widget_peruser_options');

		// If the user has their own options array
		if ( isset($dashboard_widget_options[$user_ID]) && is_array($dashboard_widget_options[$user_ID]) )
			return $dashboard_widget_options[$user_ID];

		// If the default options are of a user and that user has an options array
		if ( 0 != $this->settings['defaultuser'] && isset($dashboard_widget_options[$this->settings['defaultuser']]) && is_array($dashboard_widget_options[$this->settings['defaultuser']]) )
			return $dashboard_widget_options[$this->settings['defaultuser']];

		// Still here? Then use an uncustomized dashboard
		return array();
	}


	// So that we can use one function for both the update_option() and add_option() hooks, we need to use wrappers
	function update_option_dashboard_widget_options( $oldvalue, $newvalue ) {
		$this->addupdate_option_dashboard_widget_options( $newvalue, $oldvalue );
	}
	function add_option_dashboard_widget_options( $name, $value ) {
		$this->addupdate_option_dashboard_widget_options( $value );
	}


	// When add/update_option('dashboard_widget_options') is called, copy the new values to our per-user option
	// This function isn't called directly, but rather by the two wrapper functions above
	function addupdate_option_dashboard_widget_options( $newvalue, $oldvalue = NULL ) {
		global $user_ID;

		// Update the per-user option with this user's new preferences
		$dashboard_widget_options = get_option('dashboard_widget_peruser_options');
		$dashboard_widget_options[$user_ID] = $newvalue;
		update_option( 'dashboard_widget_peruser_options', $dashboard_widget_options );

		// Reverse the change to the global options setting
		if ( NULL !== $oldvalue ) {
			remove_filter( 'update_option_dashboard_widget_options', array(&$this, 'update_option_dashboard_widget_options'), 10, 2 );
			update_option( 'dashboard_widget_options', $oldvalue );
			add_filter( 'update_option_dashboard_widget_options', array(&$this, 'update_option_dashboard_widget_options'), 10, 2 );
		}
	}


	// Handle the POST results from our form
	function HandleFormPOST() {
		switch ( $_POST['dashwidman-action'] ) {
			case 'manage':
				check_admin_referer( 'edit-sidebar_' . $_POST['sidebar'] );

				global $user_ID;

				$widgets = get_option( 'dashboard_widget_order' );

				$widgets[$user_ID] = ( is_array($_POST['widget-id']) ) ? $_POST['widget-id'] : array();

				update_option( 'dashboard_widget_order', $widgets );

				wp_redirect( add_query_arg( 'message', 'updated' ) );
				exit();

			case 'options':
				check_admin_referer( 'dashboard-widget-manager' );

				$this->settings = get_option( 'dashboard_widget_manager' );

				$this->settings['defaultuser'] = (int) $_POST['dashwidman_defaultuser'];
				$this->settings['allcanedit']  = (int) $_POST['dashwidman_allcanedit'];

				update_option( 'dashboard_widget_manager', $this->settings );

				wp_redirect( add_query_arg( 'updated', 'true' ) );
				exit();
		}
	}


	// Output the contents of our manage page. It's based heavily on /wp-admin/widgets.php
	function ManagePage() {
		global $user_ID, $wp_registered_widgets, $sidebars_widgets, $wp_registered_widget_controls, $wp_registered_sidebars;

		// Handle default resets
		if ( 'defaultwidgets' == $_GET['message'] ) {
			$widgets = get_option( 'dashboard_widget_order' );
			unset( $widgets[$user_ID] );
			update_option( 'dashboard_widget_order', $widgets );
		}
		elseif ( 'defaultoptions' == $_GET['message'] ) {
			$options = get_option( 'dashboard_widget_peruser_options' );
			unset( $options[$user_ID] );
			update_option( 'dashboard_widget_peruser_options', $options );
		}

		$sidebars_widgets = wp_get_sidebars_widgets();
		if ( empty( $sidebars_widgets ) )
			$sidebars_widgets = wp_get_widget_defaults();

		$wp_registered_widgets = array();
		$wp_registered_widget_controls = array();

		// Have the dashboard widgets be created
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
		//print_r( $this->get_real_option('sidebars_widgets') );
		//print_r( $_POST );
		//print_r( $wp_registered_widgets );
		//print_r( $wp_registered_widget_controls );
		//print_r( get_option('dashboard_widget_options') );
		//print_r( get_option('dashboard_widget_peruser_options') );
		echo '</pre>';
		*/

		$messages = array(
			'updated' => __('Changes saved.'),
			'defaultwidgets' => __("Dashboard widgets reset to their default order.", 'dashwidman'),
			'defaultoptions' => __("Dashboard widgets reset to their default options.", 'dashwidman'),
		);

		if ( isset($_GET['message']) && isset($messages[$_GET['message']]) ) : ?>

<div id="message" class="updated fade"><p><?php echo $messages[$_GET['message']]; ?></p></div>

<?php endif; ?>

<div class="wrap">
	<h2><?php _e( 'Dashboard Widgets', 'dashwidman' ); ?></h2>

	<p><?php _e( 'Rearrange your widgets here. To edit the widget options, visit the dashboard (no sense in reinventing the wheel). Also, only one text and one custom RSS widget are currently available. Getting multi-instance widgets to function correctly here is still a work in progress.' ); ?></p>

	<p><?php printf( __( "If you need to restore the default widgets order, just <a href='%s'>click here</a>. You can also reset all widget options to their defaults by <a href='%s'>clicking here</a>.", 'dashwidman'), add_query_arg( 'message', 'defaultwidgets' ), add_query_arg( 'message', 'defaultoptions' ) ); ?></p>

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
			<input type="hidden" id="sidebar" name="sidebar" value="<?php echo $sidebar; ?>" />
			<input type="hidden" id="generated-time" name="generated-time" value="<?php echo time() - 1199145600; // Jan 1, 2008 ?>" />
			<input type="hidden" name="dashwidman-action" value="manage" />
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


	// Some admin-only options for this plugin
	function OptionsPage() {
		$wp_user_search = new WP_User_Search();

		?>

<div class="wrap">
	<h2><?php _e( 'Dashboard Widgets', 'dashwidman' ); ?></h2>

	<form method="post" action="">
<?php wp_nonce_field('dashboard-widget-manager') ?>

	<table class="form-table">
		<tr valign="top">
			<th scope="row"><?php _e( 'Defaults', 'dashwidman' ); ?></th>
			<td>
				<label for="dashwidman_defaultuser">
					<?php _e( "For users with no custom dashboard, show them the following user's dasahboard:", 'dashwidman' ) ?> 
					<select name="dashwidman_defaultuser">
						<option value="0"<?php selected(0, $this->settings['defaultuser']); ?>><?php _e( '[Default Dashboard]', 'dashwidman' ) ?></option>
<?php

						foreach ( $wp_user_search->get_results() as $userid ) {
							$user_object = new WP_User($userid);
							echo '						<option value="' . $userid . '"';
							selected($userid, $this->settings['defaultuser']);
							echo '>' . $user_object->user_login . "</option>\n";
						}

?>
					</select>
				</label>
			</td>
		</tr>
		<tr valign="top">
			<th scope="row"><?php _e( 'All Can Edit', 'dashwidman' ); ?></th>
			<td>
				<label for="dashwidman_allcanedit">
					<input name="dashwidman_allcanedit" type="checkbox" id="dashwidman_allcanedit" value="1"<?php checked('1', $this->settings['allcanedit']); ?> />
					<?php _e( "Allow users who can't normally edit the dashboard (subscribers, contributors, etc.) to make their own customized dashboard.", 'dashwidman' ); ?> 
				</label><br />
			</td>
		</tr>
	</table>

	<p class="submit">
		<input type="hidden" name="regusersonly_action" value="update" />
		<input type="hidden" name="dashwidman-action" value="options" />
		<input type="submit" name="Submit" value="<?php _e('Save Changes') ?>" />
	</p>

	</form>
</div>

<?php
	}


	// A custom text widget tailored for the dashboard and this plugin
	function TextWidget( $args ) {
		extract( $args, EXTR_SKIP );

		if ( !$widget_options = get_option( 'dashboard_widget_options' ) )
			$widget_options = array();

		$number = 1; // Hackity hack, don't come back (forwards compatibility for when I get multi-instance widgets working)

		if ( !isset($widget_options[$widget_id][$number]) )
			$widget_options[$widget_id][$number] = array();

		$title = $widget_options[$widget_id][$number]['title'];
		$text = $widget_options[$widget_id][$number]['text'];

		if ( !$title ) $title = __( 'Text', 'dashwidman' );
		if ( !$text )  $text  = __( 'Edit this widget to set some text.', 'dashwidman' );

		echo $before_widget;

		echo $before_title;
		echo $title;
		echo $after_title;

		echo apply_filters( 'the_content', $text );

		echo $after_widget;
	}


	// The control function for the custom text widget
	function TextWidgetControl( $args ) {
		extract( $args );
		if ( !$widget_id )
			return false;

		if ( !$widget_options = get_option( 'dashboard_widget_options' ) )
			$widget_options = array();

		$number = 1; // Hackity hack, don't come back (forwards compatibility for when I get multi-instance widgets working)

		if ( !isset($widget_options[$widget_id][$number]) )
			$widget_options[$widget_id][$number] = array();


		// Update form handling
		if ( 'POST' == $_SERVER['REQUEST_METHOD'] && isset($_POST['dashboard-widget-text'][$number]) ) {
			$title = strip_tags(stripslashes($_POST['dashboard-widget-text'][$number]['title']));
			if ( current_user_can('unfiltered_html') )
				$text = stripslashes($_POST['dashboard-widget-text'][$number]['text']);
			else
				$text = stripslashes(wp_filter_post_kses($_POST['dashboard-widget-text'][$number]['text']));

			$widget_options[$widget_id][$number]['title']  = $title;
			$widget_options[$widget_id][$number]['text']   = $text;
			$widget_options[$widget_id][$number]['random'] = mt_rand(); // Weird bug where updated rows will return 0 if title/text is blank

			update_option( 'dashboard_widget_options', $widget_options );
		}


		$title = attribute_escape($widget_options[$widget_id][$number]['title']);
		$text = format_to_edit($widget_options[$widget_id][$number]['text']);

		?>
		<p>
			<input class="widefat" id="dashboard-text-title-<?php echo $number; ?>" name="dashboard-widget-text[<?php echo $number; ?>][title]" type="text" value="<?php echo $title; ?>" />
			<textarea class="widefat" rows="8" cols="20" id="dashboard-text-text-<?php echo $number; ?>" name="dashboard-widget-text[<?php echo $number; ?>][text]"><?php echo $text; ?></textarea>
			<input type="hidden" name="dashboard-widget-text[<?php echo $number; ?>][submit]" value="1" />
		</p>
<?php
	}


	// Some CSS for the text widget
	function TextWidgetCSS() {
		echo "	<style type='text/css'>.DashboardWidgetManager_TextWidget img { max-width: 100%; }</style>\n";
	}


	// The control function for the RSS widget
	function RSSWidgetControl( $args ) {
		extract( $args );
		if ( !$widget_id )
			return false;

		if ( !$widget_options = get_option( 'dashboard_widget_options' ) )
			$widget_options = array();

		if ( !isset($widget_options[$widget_id]) )
			$widget_options[$widget_id] = array();

		$number = 1; // Hack to use wp_widget_rss_form()
		$widget_options[$widget_id]['number'] = $number;

		if ( 'POST' == $_SERVER['REQUEST_METHOD'] && isset($_POST['widget-rss'][$number]) ) {
			$_POST['widget-rss'][$number] = stripslashes_deep( $_POST['widget-rss'][$number] );
			$widget_options[$widget_id] = wp_widget_rss_process( $_POST['widget-rss'][$number] );
			// title is optional.  If black, fill it if possible
			if ( !$widget_options[$widget_id]['title'] && isset($_POST['widget-rss'][$number]['title']) ) {
				require_once(ABSPATH . WPINC . '/rss.php');
				$rss = fetch_rss($widget_options[$widget_id]['url']);
				$widget_options[$widget_id]['title'] = htmlentities(strip_tags($rss->channel['title']));
			}
			update_option( 'dashboard_widget_options', $widget_options );
		}

		wp_widget_rss_form( $widget_options[$widget_id], $form_inputs );
	}


	// Do nothing (used where we need to call a function but not have it do anything)
	function DoNothing() { }
}

// Start this plugin once all other plugins are fully loaded
add_action( 'plugins_loaded', create_function( '', 'global $DashboardWidgetManager; $DashboardWidgetManager = new DashboardWidgetManager();' ) );

?>