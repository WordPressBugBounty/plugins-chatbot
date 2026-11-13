<?php
if (!defined('ABSPATH')) exit; // Exit if accessed directly
/*******************************************
 * QuantumCloud Plugin Upgrade Link for Free Plugins
 * Last Updated On: 05-24-2017
 *******************************************/

if( !class_exists('QcchatbotPluginUpgradeToProNotice') )
{
	class QcchatbotPluginUpgradeToProNotice {
		
		//Public variables, these can be overrides using instance callback

		public $upgrade_link = "https://www.quantumcloud.net";
		public $link_color = "#FCB214";
		public $link_text = "Upgrade to Pro";
		public $link_class = "";
		public $link_target = "_blank";
		
		public $plugin_slug = ""; //Exact plugin folder name
		public $plugin_main_file = ""; //Exact file name with extension of primary file
		public $plugin_menu_slug = ""; //Parent menu slug of the plugin
		
		public $plugin_slug_plus_file = "";

		//Turn on or off the hooks, use false to off
		public $show_with_action_links = true; //in plugin.php page
		public $show_with_meta_links = true; //in plugin.php page
		public $show_with_plugin_menu = true; //inside parent menu of the plugin
		
		//Contructor - Set defaults 
		function __construct()
		{
			add_action('admin_head', array(&$this, 'qcld_upgrade_to_pro_heading_part') );
		}

		function qcld_upgrade_to_pro_heading_part()
		{
			if( is_admin() ){
			?>
				<script>
					jQuery(document).ready(function($){
						$(".qc-up-pro-link-chatbot").parent('a').on("click", function(e){
							e.preventDefault();
							var link = $(this).attr('href');
							window.open(link, '_blank');
						});
					});
				</script>
			<?php
			}
		}

		/*******************************
		 * Check if the current screen
		 * is the plugins.php page or not
		 *******************************/
		function check_if_plugin_page()
		{
			
			//Check if current page is plugins.php, otherwise return false
			global $pagenow;
			
			if( is_admin() && $pagenow == 'plugins.php' )
			{
			  return true;
			}

			return false;

		} //End of check_if_plugin_page
		
		/*******************************
		 * Put Link with Plugin Action Links
		 * Like Active, Edit etc.
		 * Ofcourse, in plugin.php page
		 *******************************/
		function hook_with_plugin_action_links(){
			
			$this->plugin_slug_plus_file = $this->plugin_slug .'/'. $this->plugin_main_file;
			
			if( $this->show_with_action_links && $this->plugin_slug_plus_file != "" )
			{
				add_action( 'plugin_action_links_' . $this->plugin_slug_plus_file, array(&$this, 'func_show_upgrade_link_with_action_links'), 95 );
			}
			else
			{
			  return;
			}
			
		}
		
		/*******************************
		 * Callback function for the above hook
		 * used inside hook_with_plugin_action_links
		 *******************************/
		function func_show_upgrade_link_with_action_links( $links )
		{
			// Owner, 12 Nov 2026: Settings, Help, Get Support (WordPress.org forum) and a gold Upgrade to Pro pill.
			$links[] = '<a href="' . esc_url( admin_url( 'admin.php?page=wpbot-panel' ) ) . '">' . esc_html__( 'Settings', 'chatbot' ) . '</a>';
			$links[] = '<a href="' . esc_url( admin_url( 'admin.php?page=wpbot-help' ) ) . '">' . esc_html__( 'Help', 'chatbot' ) . '</a>';
			$links[] = '<a href="' . esc_url( 'https://wordpress.org/support/plugin/chatbot/' ) . '" target="_blank" rel="noopener noreferrer">' . esc_html__( 'Get Support', 'chatbot' ) . '</a>';
			$links[] = '<a class="qcld-wpbot-upgrade-pill" href="' . esc_url( QCLD_WPBot_Free_Pro_Catalogue::upgrade_url( 'plugins-row' ) ) . '" target="_blank" rel="noopener noreferrer"><span aria-hidden="true">&#9733;</span> ' . esc_html__( 'Upgrade to Pro', 'chatbot' ) . '</a>';
			return $links;
		}
		
		/*******************************
		 * Put Link with Plugin Meta Links
		 * Like Plugin Author, Details, etc.
		 * Ofcourse, in plugin.php page
		 *******************************/
		function hook_with_plugin_meta_links(){
			if ( $this->show_with_meta_links && $this->plugin_main_file != "" ) {
				add_action( 'after_plugin_row_meta', array( $this, 'show_pro_feature_chips' ), 10, 1 );
				add_action( 'admin_head', array( 'QCLD_WPBot_Free_Pro_Catalogue', 'plugins_page_css' ) );
			}
		}

		/**
		 * What WPBot Pro adds, as small chips under the plugin's details (Plugins page, WordPress 6.5+).
		 *
		 * @param string $file Plugin file of the row.
		 * @return void
		 */
		public function show_pro_feature_chips( $file ) {
			if ( $this->plugin_slug . '/' . $this->plugin_main_file !== $file ) {
				return;
			}
			QCLD_WPBot_Free_Pro_Catalogue::print_chips();
		}

		/*******************************
		 * Put Link inside WP Admin Menu
		 * Created by the respective plugin
		 *******************************/
		function hook_with_plugin_submenu()
		{
		   if( !$this->show_with_plugin_menu || $this->plugin_menu_slug == "" ){
		     return;
		   }
		   else
		   {
			  add_action( 'admin_menu' , array(&$this, 'func_qc_external_upgrade_link'), 20 );
		   }
		}
		
		/*******************************
		 * Callback function for the avbove function/hook
		 *******************************/
		function func_qc_external_upgrade_link()
		{
		    global $submenu;
			$current_user = wp_get_current_user();
			
			if( !$this->show_with_plugin_menu || $this->plugin_menu_slug == "" )
			{
		     return;
		    }
		   
		    // Owner, 12 Nov 2026: the unlock meter (how much of WPBot the site uses, and what Pro unlocks).
		    $link_text = QCLD_WPBot_Free_Pro_Catalogue::menu_meter();

			if($current_user->roles[0]!='subscriber') {
				$parent_slug = isset( $submenu['wpbot_openAi'] ) ? 'wpbot_openAi' : ( isset( $submenu['wpbot-panel'] ) ? 'wpbot-panel' : $this->plugin_menu_slug );
				$submenu["$parent_slug"][300] = array( $link_text, 'activate_plugins' , QCLD_WPBot_Free_Pro_Catalogue::upgrade_url( 'admin-menu' ) );
			}
			
			return $submenu;
		}
		
		

	} //End of class QcPluginUpgradeToProNotice

} // End of class_exists


/*******************************
 * Create instance and call the 
 * appropriate worker/callback
 *******************************/
 
$instance_bot2 = new QcchatbotPluginUpgradeToProNotice();

if( is_admin() )
{ 
	
	//Uncommnent and Set these instance variables as per the requirements

	$instance_bot2->upgrade_link = "https://www.wpbot.pro/";
	//$instance_sldf2->link_color = "#FCB214";
	//$instance_sldf2->link_text = "Upgrade To Pro";
	//$instance_sldf2->link_class = "";
	//$instance_sldf2->link_target = "_blank";

	$instance_bot2->plugin_slug = 'chatbot'; //Plugin Slug. i.e. Folder Name
	$instance_bot2->plugin_main_file = 'qcld-wpwbot.php'; //Primary file of the pluign
	$instance_bot2->plugin_menu_slug = ( isset( $GLOBALS['admin_page_hooks']['wpbot_openAi'] ) ? 'wpbot_openAi' : 'wpbot-panel' ); //Main Menu Slug

	$instance_bot2->show_with_action_links = true; //show in plugin.php page
	$instance_bot2->show_with_meta_links = true; //show in plugin.php page
	$instance_bot2->show_with_plugin_menu = true; //show inside parent menu of the plugin

	if( $instance_bot2->check_if_plugin_page() )
	{
		$instance_bot2->hook_with_plugin_action_links();
		$instance_bot2->hook_with_plugin_meta_links();
	}
	
	$instance_bot2->hook_with_plugin_submenu();

}
