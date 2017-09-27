<?php
/**
 * Admin Assets Class
 *
 * Sets up admin menu items.
 * @since   1.0.0
 * @version [version]
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

class LLMS_Admin_Menus {

	/**
	 * Constructor
	 * @since   1.0.0
	 * @version [version]
	 */
	public function __construct() {

		add_action( 'admin_init', array( $this, 'status_page_actions' ) );
		add_action( 'admin_init', array( $this, 'builder_page_actions' ) );

		add_filter( 'custom_menu_order', array( $this, 'submenu_order' ) );
		add_action( 'admin_menu', array( $this, 'display_admin_menu' ) );
		add_action( 'admin_menu', array( $this, 'display_admin_menu_late' ), 7777 );

		// shame shame shame
		add_action( 'admin_menu', array( $this, 'instructor_menu_hack' ) );

	}

	/**
	 * Remove the default menu page from the submenu
	 * @param  array
	 * @return array
	 * @since   1.0.0
	 * @version 3.2.0
	 */
	public function submenu_order( $menu_ord ) {
		global $submenu;

		if ( isset( $submenu['lifterlms'] ) ) {
			unset( $submenu['lifterlms'][0] );
		}

		return $menu_ord;
	}


	/**
	 * Handle init actions on the course builder page
	 * Used for post-locking redirects when taking over from another user
	 * on the course builder page
	 * @return   void
	 * @since    [version]
	 * @version  [version]
	 */
	public function builder_page_actions() {

		if ( ! isset( $_GET['page'] ) || 'llms-course-builder' !== $_GET['page'] ) {
			return;
		}

		if ( ! empty( $_GET['get-post-lock'] ) && ! empty( $_GET['course_id'] ) ) {
			$post_id = absint( $_GET['course_id'] );
			check_admin_referer( 'lock-post_' . $post_id );
			wp_set_post_lock( $post_id );
			wp_redirect( add_query_arg( array(
				'page' => 'llms-course-builder',
				'course_id' => $post_id,
			), admin_url( 'admin.php' ) ) );
			exit();

		}
	}

	/**
	 * Admin Menu
	 * @return void
	 * @since   1.0.0
	 * @version [version]
	 */
	public function display_admin_menu() {

		global $menu;

		$menu[51] = array( '', 'read', 'llms-separator','','wp-menu-separator' );

		add_menu_page( 'lifterlms', 'LifterLMS', 'read', 'lifterlms', '__return_empty_string', plugin_dir_url( LLMS_PLUGIN_FILE ) . 'assets/images/lifterLMS-wp-menu-icon.png', 51 );

		add_submenu_page( 'lifterlms', __( 'LifterLMS Settings', 'lifterlms' ), __( 'Settings', 'lifterlms' ), 'manage_lifterlms', 'llms-settings', array( $this, 'settings_page_init' ) );

		add_submenu_page( 'lifterlms', __( 'LifterLMS Reporting', 'lifterlms' ), __( 'Reporting', 'lifterlms' ), 'view_lifterlms_reports', 'llms-reporting', array( $this, 'reporting_page_init' ) );

		add_submenu_page( 'lifterlms', __( 'LifterLMS Import', 'lifterlms' ), __( 'Import', 'lifterlms' ), 'manage_lifterlms', 'llms-import', array( $this, 'import_page_init' ) );

		add_submenu_page( 'lifterlms', __( 'LifterLMS Status', 'lifterlms' ), __( 'Status', 'lifterlms' ), 'manage_lifterlms', 'llms-status', array( $this, 'status_page_init' ) );

		add_submenu_page( null, __( 'LifterLMS Course Builder', 'lifterlms' ), __( 'Course Builder', 'lifterlms' ), 'edit_courses', 'llms-course-builder', array( $this, 'course_builder_init' ) );

	}

	/**
	 * Add items to the admin menu with a later priority
	 * @return   void
	 * @since    3.5.0
	 * @version  [version]
	 */
	public function display_admin_menu_late() {

		/**
		 * Do you not want your clients buying addons or fiddling with this screen?
		 */
		if ( apply_filters( 'lifterlms_disable_addons_screen', false ) ) {
			return;
		}

		add_submenu_page( 'lifterlms', __( 'LifterLMS Add-ons', 'lifterlms' ), __( 'Add-ons', 'lifterlms' ), 'manage_lifterlms', 'llms-add-ons', array( $this, 'add_ons_page_init' ) );

	}

	/**
	 * Outupt the addons screen
	 * @since    3.5.0
	 * @version  3.5.0
	 */
	public function add_ons_page_init() {
		require_once 'class.llms.admin.addons.php';
		$view = new LLMS_Admin_AddOns();
		$view->output();
	}

	/**
	 * Output the HTML for the Course Builder
	 * @return   void
	 * @since    [version]
	 * @version  [version]
	 */
	public function course_builder_init() {
		require_once 'class.llms.course.builder.php';
		LLMS_Course_Builder::output();
	}

	/**
	 * Outputs the LifterLMS Importer Screen HTML
	 * @return   void
	 * @since    3.3.0
	 * @version  3.3.0
	 */
	public function import_page_init() {
		LLMS_Admin_Import::output();
	}

	/**
	 * Removes edit.php from the admin menu for instructors/asst instructiors
	 * note that the post screen is still technically accessible...
	 * posts will need to be submitted for review as the instructors only actually have
	 * the capability of a contributor with regards to posts
	 * but this hack will allow instructors to publish new lessons, quizzes, & questions
	 * @see      WP Core Issue(s): https://core.trac.wordpress.org/ticket/22895
	 *           				   https://core.trac.wordpress.org/ticket/16808
	 * @return   void
	 * @since    [version]
	 * @version  [version]
	 */
	public function instructor_menu_hack() {

		$user = wp_get_current_user();
		if ( array_intersect( array( 'instructor', 'instructors_assistant' ), $user->roles ) ) {
			remove_menu_page( 'edit.php' );
		}
	}

	/**
	 * Output the HTLM for admin settings screens
	 * @return void
	 */
	public function settings_page_init() {
		include_once( 'class.llms.admin.settings.php' );
		LLMS_Admin_Settings::output();
	}

	/**
	 * Output the HTML for the reporting screens
	 * @return   void
	 * @since    3.2.0
	 * @version  [version]
	 */
	public function reporting_page_init() {

		if ( isset( $_GET['student_id'] ) && ! llms_current_user_can( 'view_lifterlms_reports', $_GET['student_id'] ) ) {
			wp_die( __( 'You do not have permission to access this content.', 'lifterlms' ) );
		}

		require_once 'reporting/class.llms.admin.reporting.php';
		$gb = new LLMS_Admin_Reporting();
		$gb->output();

	}

	/**
	 * Handle form submission actiosn on the status pages
	 * @return   void
	 * @since    3.11.2
	 * @version  3.11.2
	 */
	public function status_page_actions() {
		require_once 'class.llms.admin.page.status.php';
		LLMS_Admin_Page_Status::handle_actions();
	}

	/**
	 * Output the HTML for the Status Pages
	 * @return   void
	 * @since    ??
	 * @version  3.11.2
	 */
	public function status_page_init() {
		require_once 'class.llms.admin.page.status.php';
		LLMS_Admin_Page_Status::output();
	}
}

return new LLMS_Admin_Menus();
