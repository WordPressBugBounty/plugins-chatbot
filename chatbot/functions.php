<?php
/**
 * @param $type
 * Display wpwBot Icon ball
 */
if (!defined('ABSPATH')) exit; // Exit if accessed directly

/**
 * Plugin-relative path of a front-end asset: the .min copy built for releases, unless SCRIPT_DEBUG is on.
 */
function qcld_wpbot_min_asset( $path ) {
    if ( defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ) {
        return $path;
    }
    $min = preg_replace( '/\.(js|css)$/', '.min.$1', $path );
    return file_exists( QCLD_wpCHATBOT_PLUGIN_DIR_PATH . $min ) ? $min : $path;
}


if ( ! function_exists( 'qcld_wpbot_free_image_url' ) ) {
	/**
	 * A saved image address that still works, else the fallback (owner, 12 Nov 2026: the custom agent
	 * image showed broken). WPBot Free and Pro share these options, so a site that ran WPBot Pro can
	 * hold an address in Pro's plugin folder, or a Media file that was deleted. Addresses on this site
	 * (plugins or uploads) are checked on disk; other sites' addresses are kept as they are.
	 *
	 * @param string $url      Saved address.
	 * @param string $fallback Address to use when the saved one is empty or missing.
	 * @return string
	 */
	function qcld_wpbot_free_image_url( $url, $fallback ) {
		$url = trim( (string) $url );
		if ( '' === $url ) {
			return $fallback;
		}
		$path    = (string) wp_parse_url( $url, PHP_URL_PATH );
		$uploads = wp_get_upload_dir();
		$roots   = array(
			(string) wp_parse_url( plugins_url(), PHP_URL_PATH ) => WP_PLUGIN_DIR,
			(string) wp_parse_url( $uploads['baseurl'], PHP_URL_PATH ) => $uploads['basedir'],
		);
		foreach ( $roots as $root => $dir ) {
			if ( '' !== $root && 0 === strpos( $path, trailingslashit( $root ) ) ) {
				$file = trailingslashit( $dir ) . ltrim( rawurldecode( substr( $path, strlen( trailingslashit( $root ) ) ) ), '/' );
				return ( false === strpos( $file, '..' ) && file_exists( $file ) ) ? $url : $fallback;
			}
		}
		return $url;
	}
}

function qcld_custom_search_form( $form ) {
      // Add a hidden input to limit search to 'product' post type
    $wp_chatbot_custom_agent_path = class_exists( 'qcld_wb_Chatbot_free' ) ? qcld_wb_Chatbot_free::qcld_wb_chatbot_get_instance()->qcld_wb_chatbot_agent_icon() : QCLD_wpCHATBOT_IMG_URL . 'agent-0.png';
    $hidden_field = '<a class="wp-chatbot qc_wpbot_chat_link" id="wp-chatbot-search-btn" data-search-type="product" data-search-term="" style="max-height: 50px; margin-left: 10px;padding: 0 !important;position: absolute;top: -9px;right: -60px;"><img src="'. esc_url($wp_chatbot_custom_agent_path) .'" alt=""></a>';
    $block_content = str_replace( '</form>', $hidden_field . '</form>', $form );
    return $block_content;
}
if(get_option('wpbot_enable_on_search') == 1 && (get_option('disable_floating_button') != '1') && get_option('disable_wp_chatbot') != 1 ){
   add_filter( 'get_search_form', 'qcld_custom_search_form' );
   add_filter( 'render_block_core/search', 'qcld_modify_gutenberg_search_block', 10, 2 );
}


function qcld_modify_gutenberg_search_block( $block_content, $block ) {
    // Add a hidden input to limit search to 'product' post type
    if (get_option('wp_chatbot_agent_image') == "custom-agent.png") {
        $wp_chatbot_custom_agent_path = get_option('wp_chatbot_custom_agent_path');
    } else if (get_option('wp_chatbot_agent_image') != "custom-agent.png") {
        $wp_chatbot_custom_agent_path = QCLD_wpCHATBOT_IMG_URL . get_option('wp_chatbot_agent_image');
    } else {
        $wp_chatbot_custom_agent_path = QCLD_wpCHATBOT_IMG_URL . 'custom-agent.png';
    }
    $hidden_field = '<button type="button" class="wp-chatbot qc_wpbot_chat_link" id="wp-chatbot-search-btn" data-search-type="product" data-search-term="" style="max-height: 50px; margin-left: 10px;padding: 0 !important"><img src="'. esc_url($wp_chatbot_custom_agent_path) .'" alt=""></button>';
    // Inject the hidden field before the closing </form> tag
    $block_content = str_replace( '</div></form>', $hidden_field . '</div></form>', $block_content );
    return $block_content;
}
if(get_option('disable_floating_button') != '1'){
    add_action('wp_footer', 'wp_chatbot_load_footer_html');
}
add_action( 'admin_footer', 'qcld_style_for_hide_iframe');
function qcld_style_for_hide_iframe(){
?>
    <script>
        jQuery( document ).ready(function() {
            setInterval(function(){
                if(document.querySelector('.wp-block-legacy-widget__edit-preview-iframe')){
                    document.querySelector('.wp-block-legacy-widget__edit-preview-iframe').addEventListener("load", ev => {
                        jQuery('.wp-block-legacy-widget__edit-preview-iframe').contents().find('#wp-chatbot-chat-container').hide();
                    });

                }
            }, 500);
        });
    </script>
<?php 
}
/**
 * Extract a YouTube video ID from common URL formats.
 *
 * @param string $url YouTube watch, embed, short, or youtu.be URL.
 * @return string Video ID or empty string.
 */
if ( ! function_exists( 'qcld_wpbot_extract_youtube_id' ) ) {
	function qcld_wpbot_extract_youtube_id( $url ) {
		$url = trim( (string) $url );
		if ( $url === '' ) {
			return '';
		}

		if ( preg_match( '/(?:youtube\.com\/(?:embed\/|shorts\/|live\/|watch\?(?:.*&)?v=)|youtu\.be\/)([A-Za-z0-9_-]{11})/', $url, $matches ) ) {
			return $matches[1];
		}

		$path = (string) wp_parse_url( $url, PHP_URL_PATH );
		$base = basename( $path );
		if ( preg_match( '/^[A-Za-z0-9_-]{11}$/', $base ) ) {
			return $base;
		}

		return '';
	}
}
if ( ! function_exists( 'qcld_wpbot_youtube_icon_embed_src' ) ) {
	function qcld_wpbot_youtube_icon_embed_src( $url ) {
		$video_id = qcld_wpbot_extract_youtube_id( $url );
		if ( $video_id === '' ) {
			return '';
		}

		return add_query_arg(
			array(
				'autoplay'        => '1',
				'mute'            => '1',
				'loop'            => '1',
				'playlist'        => $video_id,
				'controls'        => '0',
				'showinfo'        => '0',
				'rel'             => '0',
				'fs'              => '0',
				'iv_load_policy'  => '3',
				'cc_load_policy'  => '0',
				'disablekb'       => '1',
				'playsinline'     => '1',
				'modestbranding'  => '1',
				'color'           => 'white',
			),
			'https://www.youtube.com/embed/' . rawurlencode( $video_id )
		);
	}
}
function wp_chatbot_load_footer_html(){
    /**
     * Whether this page may be served from a page cache with live show/hide
     * rules (WPBot Asset Cache turns it on while its cache is active). The
     * chatbot is then printed whenever any case shows it, held hidden, and
     * the browser decides.
     */
    $qcld_live_rules = ! is_admin() && apply_filters( 'qcld_wpbot_footer_live_rules', false );
    $qcld_rules      = $qcld_live_rules ? qcld_wpbot_free_rules_table() : '';
    $qcld_may_show   = $qcld_live_rules ? ( false !== strpos( $qcld_rules, '1' ) ) : ( wp_chatbot_load_controlling() === true );
    if ( get_option('disable_wp_chatbot') != 1 && $qcld_may_show ) {
        if ( $qcld_live_rules ) {
            /**
             * The live rules for this page (see qcld_wpbot_free_rules_table()).
             * WPBot Asset Cache holds the chatbot hidden and reveals it in the browser.
             */
            do_action( 'qcld_wpbot_footer_rules', $qcld_rules );
        }
		
        ?>
        <style>
            <?php if(get_option('wp_chatbot_custom_css')!="") {
     
                echo wp_strip_all_tags( get_option('wp_chatbot_custom_css') );// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
            }
            ?>
        </style>
       
        <?php if (get_option('qcld_wb_chatbot_change_bg') == 1) {
            if (get_option('qcld_wb_chatbot_board_bg_path') != "") {
                $qcld_wb_chatbot_board_bg_path = get_option('qcld_wb_chatbot_board_bg_path');
            } else {
                $qcld_wb_chatbot_board_bg_path = QCLD_wpCHATBOT_IMG_URL . 'background/background.png';
            }
            ?>
            <style>
                .wp-chatbot-container {
                    background-color: #eceef3 !important;
                    background-image: url(<?php echo esc_url($qcld_wb_chatbot_board_bg_path); ?>) !important;
                    background-size: cover !important;
                    background-position: center !important;
                    background-repeat: no-repeat !important;
                }
                .wp-chatbot-template-01 #wp-chatbot-board-container,
                .wp-chatbot-template-01 .wp-chatbot-board-container {
                    background-color: #eceef3 !important;
                    background-image: none !important;
                }
                .wp-chatbot-template-01 #wp-chatbot-board-container::before,
                .wp-chatbot-template-01 .wp-chatbot-board-container::before {
                    background-color: #eceef3 !important;
                    background-image: url(<?php echo esc_url($qcld_wb_chatbot_board_bg_path); ?>) !important;
                    background-size: cover !important;
                    background-position: center !important;
                    background-repeat: no-repeat !important;
                }
            </style>
        <?php }
        $wp_chatbot_enable_rtl = "";
        if (get_option('enable_wp_chatbot_rtl') == '1') {
            $wp_chatbot_enable_rtl .= "wp-chatbot-rtl";
        }
        $wp_chatbot_enable_mobile_screen = "";
      //  if (get_option('enable_wp_chatbot_mobile_full_screen')==1) {
            $wp_chatbot_enable_mobile_screen .= "wp-chatbot-mobile-full-screen";
       // }
        ?>
        <div id="wp-chatbot-chat-container" class="<?php echo esc_attr($wp_chatbot_enable_rtl .' '.$wp_chatbot_enable_mobile_screen); ?>" style="<?php if(get_option('disable_floating_button') == '1'){ echo 'display:none';} ?>">
            <div id="wp-chatbot-integration-container">
                <div class="wp-chatbot-integration-button-container">
                    <?php if (get_option('enable_wp_chatbot_skype_floating_icon') == 1) { ?>
                        <a href="skype:<?php echo esc_attr(get_option('enable_wp_chatbot_skype_id')); ?>?chat"><span
                                    class="inetegration-skype-btn" title="<?php esc_attr_e('Skype', 'chatbot'); ?>"> </span></a>
                    <?php } ?>
                    <?php if (get_option('enable_wp_chatbot_floating_whats') == 1) { ?>
                        <a href="<?php echo esc_url('https://api.whatsapp.com/send?phone=' . get_option('qlcd_wp_chatbot_whats_num')); ?>"
                           target="_blank"><span class="intergration-whats"
                                                 title="<?php esc_html_e('WhatsApp', 'chatbot'); ?>"></span></a>
                    <?php } ?>
                    <?php if (get_option('enable_wp_chatbot_floating_viber') == 1) { ?>
                        <a href="<?php echo esc_url('https://live.viber.com/#/' . get_option('qlcd_wp_chatbot_viber_acc')); ?>"
                           target="_blank"><span class="intergration-viber"
                                                 title="<?php esc_html_e('Viber', 'chatbot'); ?>"></span></a>
                    <?php } ?>
                    <?php if (get_option('enable_wp_chatbot_floating_phone') == 1 && get_option('qlcd_wp_chatbot_phone') != "") { ?>
                        <a href="tel:<?php echo esc_attr(get_option('qlcd_wp_chatbot_phone')); ?>"><span
                                    class="intergration-phone"
                                    title="<?php esc_html_e('Phone', 'chatbot'); ?>"> </span></a>
                    <?php } ?>
                    <?php if (get_option('enable_wp_chatbot_floating_link') == 1 && get_option('qlcd_wp_chatbot_weblink') != "") { ?>
                        <a href="<?php echo esc_url(get_option('qlcd_wp_chatbot_weblink')); ?>" target="_blank"><span
                                    class="intergration-weblink" title="<?php esc_html_e('Web Link', 'chatbot'); ?>"></span></a>
                    <?php } ?>
                </div>
            </div>
            <?php
            //Get wpcommerce cart
            
            $qcld_wb_chatbot_theme = get_option('qcld_wb_chatbot_theme');
            if (file_exists(QCLD_wpCHATBOT_PLUGIN_DIR_PATH . '/templates/' . $qcld_wb_chatbot_theme . '/style.css')) {
                wp_register_style('qcld-wp-chatbot-style', plugins_url(basename(plugin_dir_path(__FILE__)) . '/' . qcld_wpbot_min_asset('templates/' . $qcld_wb_chatbot_theme . '/style.css'), basename(__FILE__)), '', QCLD_wpCHATBOT_VERSION, 'screen');
                wp_enqueue_style('qcld-wp-chatbot-style');
            }
            if (file_exists(QCLD_wpCHATBOT_PLUGIN_DIR_PATH . '/templates/' . $qcld_wb_chatbot_theme . '/template.php')) {
                require_once(QCLD_wpCHATBOT_PLUGIN_DIR_PATH . '/templates/' . $qcld_wb_chatbot_theme . '/template.php');
            } else {
                echo "<h2>" . esc_html__('No wpWBot Theme Found!', 'chatbot') . "</h2>";
            }
            ?>
            <?php
            if (get_option('disable_wp_chatbot_notification') != 1) {
                ?>
                <div id="wp-chatbot-notification-container" class="wp-chatbot-notification-container">
                    <div class="wp-chatbot-notification-controller"> 
                        <span class="wp-chatbot-notification-close">
                            <?php esc_html_e('X', 'chatbot'); ?>
                        </span>
                    </div>
                    <?php
                    $testingTip="";
                    if (get_option('wp_chatbot_agent_image') == "custom-agent.png") {
                        $wp_chatbot_custom_agent_path = get_option('wp_chatbot_custom_agent_path');
                    } else if (get_option('wp_chatbot_agent_image') != "custom-agent.png") {
                        $wp_chatbot_custom_agent_path = QCLD_wpCHATBOT_IMG_URL . get_option('wp_chatbot_agent_image');
                    } else {
                        $wp_chatbot_custom_agent_path = QCLD_wpCHATBOT_IMG_URL . 'custom-agent.png';
                    }
                    ?>
                    <div class="wp-chatbot-notification-agent-profile">
                        <div class="wp-chatbot-notification-widget-avatar" ><img
                                    src="<?php echo esc_url($wp_chatbot_custom_agent_path); ?>" alt=""></div>
                        <div class="wp-chatbot-notification-welcome"><?php echo wp_kses_post(wpb_randmom_message_handle(maybe_unserialize(get_option('qlcd_wp_chatbot_welcome')))) . ' <strong>' . esc_html(get_option('qlcd_wp_chatbot_host')) . '</strong>'; ?></div>
                    </div>
                    <?php 
					//update_option('qlcd_wp_chatbot_notifications','Welcome to WpBot');
					$notifications = qcld_wb_chatbot_func_str_replace(wp_kses_post(maybe_unserialize(get_option('qlcd_wp_chatbot_notifications')))); 
					
					?>
					
                    <div class="wp-chatbot-notification-message"><?php echo esc_html($notifications[0]); ?></div>
                </div>
            <?php } ?>
            <!--wp-chatbot-board-container-->
            <div id="wp-chatbot-ball" class="">
                <div class="wp-chatbot-ball" role="button" tabindex="0" aria-label="<?php echo esc_attr__( 'Open or close the chat', 'chatbot' ); ?>" aria-expanded="false" aria-controls="wp-chatbot-board-container">
                    <div class="wp-chatbot-ball-animator wp-chatbot-ball-animation-switch"></div>
                    <?php
                    if (get_option('wp_chatbot_icon') == "custom.png") {
                        $wp_chatbot_custom_icon_path = (!empty(get_option('wp_chatbot_custom_icon_path'))) ? get_option('wp_chatbot_custom_icon_path') : QCLD_wpCHATBOT_IMG_URL . 'icon-1.png';
                   
                    } else if (get_option('wp_chatbot_icon') != "custom.png") {
                        $wp_chatbot_custom_icon_path = QCLD_wpCHATBOT_IMG_URL . get_option('wp_chatbot_icon');
                    } else {
                        $wp_chatbot_custom_icon_path = QCLD_wpCHATBOT_IMG_URL . 'custom.png';
                    }
                    $_wpbot_icon_video = get_option('wp_chatbot_icon_video', '');
                    $_wpbot_video_is_youtube = ( strpos( $_wpbot_icon_video, 'youtube.com' ) !== false || strpos( $_wpbot_icon_video, 'youtu.be' ) !== false );
					$_wpbot_youtube_embed_src = $_wpbot_video_is_youtube ? qcld_wpbot_youtube_icon_embed_src( $_wpbot_icon_video ) : '';
					$_wpbot_video_delay_ms = absint( get_option( 'wp_chatbot_icon_video_delay', 0 ) ) * 1000;

                    $wp_chatbot_ball_is_youtube = ($_wpbot_icon_video !== '' && (strpos($_wpbot_icon_video, 'youtube.com') !== false || strpos($_wpbot_icon_video, 'youtu.be') !== false));
                    $wp_chatbot_ball_is_video   = ($_wpbot_icon_video !== '' && !$wp_chatbot_ball_is_youtube);
                    $wp_chatbot_ball_has_video  = ($wp_chatbot_ball_is_youtube || $wp_chatbot_ball_is_video);
                    ?>
                    <img src="<?php echo esc_url($wp_chatbot_custom_icon_path); ?>"
                         alt="wpChatIcon" qcld_agent="<?php echo esc_url($wp_chatbot_custom_icon_path); ?>"
                         id="wp-chatbot-ball-icon-img"
                         <?php if ($wp_chatbot_ball_has_video) { echo 'style="display:none;"'; } ?> >
                    <?php if ( $_wpbot_icon_video !== '' ) : ?>
							<?php if ( $_wpbot_video_is_youtube && $_wpbot_youtube_embed_src !== '' ) : ?>
								<iframe class="wpbot-icon-video" src="<?php echo $_wpbot_video_delay_ms > 0 ? 'about:blank' : esc_url( $_wpbot_youtube_embed_src ); ?>" data-wpbot-yt-src="<?php echo esc_url( $_wpbot_youtube_embed_src ); ?>" frameborder="0" allow="autoplay; fullscreen; encrypted-media; picture-in-picture"></iframe>
							<?php elseif ( ! $_wpbot_video_is_youtube ) : ?>
								<video class="wpbot-icon-video" src="<?php echo esc_url( $_wpbot_icon_video ); ?>" autoplay muted loop playsinline preload="auto"></video>
							<?php endif; ?>
						<?php endif; ?>
                </div>

            </div>
            <?php 
            if ( $_wpbot_icon_video !== '' ) :
                ?>
			<script>
			(function(){
				var wpbotDelay = <?php echo (int) $_wpbot_video_delay_ms; ?>;
				function wpbotForcePlay(){
					var v = document.querySelector('#wp-chatbot-ball video.wpbot-icon-video');
					if( v ){
						v.muted   = true;
						v.volume  = 0;
						v.loop    = true;
						var tries = 0, maxTries = 30;
						var timer = setInterval(function(){
							tries++;
							v.play().then(function(){ clearInterval(timer); }).catch(function(){});
							if( tries >= maxTries ) clearInterval(timer);
						}, 300);
					}
					var yt = document.querySelector('#wp-chatbot-ball iframe.wpbot-icon-video');
					if( yt ){
						var ytSrc = yt.getAttribute('data-wpbot-yt-src');
						if( ytSrc && ( !yt.getAttribute('src') || yt.getAttribute('src') === 'about:blank' || yt.getAttribute('src').indexOf('autoplay=1') === -1 ) ){
							yt.setAttribute('src', ytSrc);
						}
					}
				}
				function wpbotDelayedPlay(){
					setTimeout(wpbotForcePlay, wpbotDelay);
				}
				if( document.readyState === 'loading' ){
					document.addEventListener('DOMContentLoaded', wpbotDelayedPlay);
				} else {
					wpbotDelayedPlay();
				}
				window.addEventListener('load', function(){
					setTimeout(wpbotForcePlay, wpbotDelay);
				});
			})();
			</script>
			<?php endif; ?>
            <?php
            $fb_app_id = get_option('qlcd_wp_chatbot_fb_app_id');
            $fb_page_id = get_option('qlcd_wp_chatbot_fb_page_id');
            $fb_mgs_color = get_option('qlcd_wp_chatbot_fb_color') != '' ? get_option('qlcd_wp_chatbot_fb_color') : '#0084ff';
            $fb_mgs_in = get_option('qlcd_wp_chatbot_fb_in_msg') != '' ? get_option('qlcd_wp_chatbot_fb_in_msg') : 'You are welcome';
            $fb_mgs_out = get_option('qlcd_wp_chatbot_fb_out_msg') != '' ? get_option('qlcd_wp_chatbot_fb_out_msg') : 'You are not logged in';
            if (get_option('enable_wp_chatbot_messenger') == 1 && get_option('enable_wp_chatbot_messenger_floating_icon') == 1) {
                ?>
                <!--                wp-chatbot-board-container-->
                <script>
                    
                    window.fbAsyncInit = function () {
                        FB.init({
                            appId: '<?php echo esc_js($fb_app_id); ?>',
                            autoLogAppEvents: true,
                            xfbml: true,
                            version: 'v2.12'
                        });
                    };
                    
                    (function (d, s, id) {
                        var js, fjs = d.getElementsByTagName(s)[0];
                        if (d.getElementById(id)) return;
                        js = d.createElement(s);
                        js.id = id;
                        js.src = 'https://connect.facebook.net/en_US/sdk.js';
                        fjs.parentNode.insertBefore(js, fjs);
                    }(document, 'script', 'facebook-jssdk'));
                </script>
                <div class="fb-customerchat"
                     page_id="<?php echo esc_attr($fb_page_id); ?>"
                     greeting_dialog_display="hide"
                     theme_color="<?php echo esc_attr($fb_mgs_color); ?>"
                     logged_in_greeting="<?php echo esc_attr($fb_mgs_in); ?>"
                     logged_out_greeting="<?php echo esc_attr($fb_mgs_out); ?>"></div>
                <?php
            }
            ?>
            <!--container-->
            <!--wp-chatbot-ball-wrapper-->
        </div>
        
        <?php

        if ( get_transient( 'qcld_bot_clear_cache' ) ) {
            echo  '<script type="text/javascript">var wpbot_clear_cache = 1 </script>';
            delete_transient( 'qcld_bot_clear_cache' );
        }

    }else{
        ?>
        <script>
            var openingHourIsFn = 1;
        </script>
        <?php
    }
}
//wp_chatbot load control handler.
/**
 * The show/hide answer of wp_chatbot_load_controlling() for all eight
 * combinations of the live rules, as eight characters "0"/"1". Character
 * index = open * 4 + mobile * 2 + logged_in. Used by WPBot Asset Cache so a
 * cached page still follows "hide on mobile" (the only rule here that depends
 * on the visitor).
 *
 * @return string
 */
function qcld_wpbot_free_rules_table() {
    $table = '';
    for ( $i = 0; $i < 8; $i++ ) {
        $table .= wp_chatbot_load_controlling(
            array(
                'open'      => (bool) ( $i & 4 ),
                'mobile'    => (bool) ( $i & 2 ),
                'logged_in' => (bool) ( $i & 1 ),
            )
        ) ? '1' : '0';
    }
    return $table;
}

/**
 * Whether the chatbot may show on this page view: the live answer, or, while
 * WPBot Asset Cache uses live show/hide rules, whether any case shows it
 * (so a page cached for one visitor still has the chatbot's files for another).
 *
 * @return bool
 */
function qcld_wpbot_free_may_load() {
    if ( ! is_admin() && apply_filters( 'qcld_wpbot_footer_live_rules', false ) ) {
        return false !== strpos( qcld_wpbot_free_rules_table(), '1' );
    }
    return wp_chatbot_load_controlling() === true;
}

/**
 * Whether the floating chatbot shows on this page.
 *
 * @param array|null $live Optional overrides for the live rules: 'mobile'
 *                         (visitor on a phone); 'open' and 'logged_in' are
 *                         accepted for the same table as WPBot Pro. Null uses
 *                         the current request, as before. WPBot Asset Cache
 *                         uses the overrides to precompute every case.
 * @return bool
 */
function wp_chatbot_load_controlling( $live = null ){
    $wp_chatbot_load = true;
    
	
    if (get_option('wp_chatbot_show_pages') == 'off') {
        $wp_chatbot_select_pages = unserialize(get_option('wp_chatbot_show_pages_list'));
        if (is_page() && !empty($wp_chatbot_select_pages)) {
            
            if (in_array(get_the_ID(), $wp_chatbot_select_pages) == true) {
                
                $wp_chatbot_load = true;
            } else {
                $wp_chatbot_load = false;
            }
            
        }
		
		if(function_exists('is_shop')){
			if (is_shop() || is_cart() || is_checkout() || 'product' == get_post_type()) {
				$wp_chatbot_load = false;
			}
		}
		
    }
    if (get_option('wp_chatbot_show_wpcommerce') == 'off') {
        
    }
    //load wpwbot shortcode template and prevent default wpwbot from footer.
    if (is_page()) {
        $page_id = get_the_ID();
        $page = get_post($page_id);
        if (has_shortcode($page->post_content, 'wpwbot')) {
            $wp_chatbot_load = false;
        }
    }
    
	$post_list = maybe_unserialize(get_option('wp_chatbot_exclude_post_list'));
    
	if( is_array( $post_list ) && in_array(get_post_type(), $post_list)){
        $wp_chatbot_load = false;
    }
	if ( ( is_array( $live ) ? ! empty( $live['mobile'] ) : wp_chatbot_is_mobile() ) && get_option('disable_wp_chatbot_on_mobile') == 1) {
        $wp_chatbot_load = false;
    }
	
	if (get_option('wp_chatbot_show_home_page') == 'off' && is_home()) {
        $wp_chatbot_load = false;
    }
	
    if (get_option('wp_chatbot_show_posts') == 'off' && 'post' == get_post_type()) {
        $wp_chatbot_load = false;
    }
	
	if(is_admin()){
		$wp_chatbot_load = false;
	}
    return $wp_chatbot_load;
}
//checking Devices
function wp_chatbot_is_mobile(){
    $useragent = isset( $_SERVER['HTTP_USER_AGENT'] ) ? $_SERVER['HTTP_USER_AGENT'] : '';
    if (preg_match('/(android|bb\d+|meego).+mobile|avantgo|bada\/|blackberry|blazer|compal|elaine|fennec|hiptop|iemobile|ip(hone|od)|iris|kindle|lge |maemo|midp|mmp|netfront|opera m(ob|in)i|palm( os)?|phone|p(ixi|re)\/|plucker|pocket|psp|series(4|6)0|symbian|treo|up\.(browser|link)|vodafone|wap|windows (ce|phone)|xda|xiino/i', $useragent) || preg_match('/1207|6310|6590|3gso|4thp|50[1-6]i|770s|802s|a wa|abac|ac(er|oo|s\-)|ai(ko|rn)|al(av|ca|co)|amoi|an(ex|ny|yw)|aptu|ar(ch|go)|as(te|us)|attw|au(di|\-m|r |s )|avan|be(ck|ll|nq)|bi(lb|rd)|bl(ac|az)|br(e|v)w|bumb|bw\-(n|u)|c55\/|capi|ccwa|cdm\-|cell|chtm|cldc|cmd\-|co(mp|nd)|craw|da(it|ll|ng)|dbte|dc\-s|devi|dica|dmob|do(c|p)o|ds(12|\-d)|el(49|ai)|em(l2|ul)|er(ic|k0)|esl8|ez([4-7]0|os|wa|ze)|fetc|fly(\-|_)|g1 u|g560|gene|gf\-5|g\-mo|go(\.w|od)|gr(ad|un)|haie|hcit|hd\-(m|p|t)|hei\-|hi(pt|ta)|hp( i|ip)|hs\-c|ht(c(\-| |_|a|g|p|s|t)|tp)|hu(aw|tc)|i\-(20|go|ma)|i230|iac( |\-|\/)|ibro|idea|ig01|ikom|im1k|inno|ipaq|iris|ja(t|v)a|jbro|jemu|jigs|kddi|keji|kgt( |\/)|klon|kpt |kwc\-|kyo(c|k)|le(no|xi)|lg( g|\/(k|l|u)|50|54|\-[a-w])|libw|lynx|m1\-w|m3ga|m50\/|ma(te|ui|xo)|mc(01|21|ca)|m\-cr|me(rc|ri)|mi(o8|oa|ts)|mmef|mo(01|02|bi|de|do|t(\-| |o|v)|zz)|mt(50|p1|v )|mwbp|mywa|n10[0-2]|n20[2-3]|n30(0|2)|n50(0|2|5)|n7(0(0|1)|10)|ne((c|m)\-|on|tf|wf|wg|wt)|nok(6|i)|nzph|o2im|op(ti|wv)|oran|owg1|p800|pan(a|d|t)|pdxg|pg(13|\-([1-8]|c))|phil|pire|pl(ay|uc)|pn\-2|po(ck|rt|se)|prox|psio|pt\-g|qa\-a|qc(07|12|21|32|60|\-[2-7]|i\-)|qtek|r380|r600|raks|rim9|ro(ve|zo)|s55\/|sa(ge|ma|mm|ms|ny|va)|sc(01|h\-|oo|p\-)|sdk\/|se(c(\-|0|1)|47|mc|nd|ri)|sgh\-|shar|sie(\-|m)|sk\-0|sl(45|id)|sm(al|ar|b3|it|t5)|so(ft|ny)|sp(01|h\-|v\-|v )|sy(01|mb)|t2(18|50)|t6(00|10|18)|ta(gt|lk)|tcl\-|tdg\-|tel(i|m)|tim\-|t\-mo|to(pl|sh)|ts(70|m\-|m3|m5)|tx\-9|up(\.b|g1|si)|utst|v400|v750|veri|vi(rg|te)|vk(40|5[0-3]|\-v)|vm40|voda|vulc|vx(52|53|60|61|70|80|81|83|85|98)|w3c(\-| )|webc|whit|wi(g |nc|nw)|wmlb|wonu|x700|yas\-|your|zeto|zte\-/i', substr($useragent, 0, 4))) {
        return true;
    } else {
        return false;
    }
}
//Checking wpwbot opening hour
function wp_chatbot_check_opening_hours(){
    $curent_day=strtolower(gmdate('l',strtotime(current_time( 'mysql' ))));
    $current_time=gmdate('H:i',strtotime(current_time( 'mysql')));
    $is_wpwbot_open =false;
    if(get_option('wpwbot_hours')) {
        $wpwbot_times = wp_kses_post(unserialize(get_option('wpwbot_hours')));
        if (isset($wpwbot_times[$curent_day])) {
            $day_times = $wpwbot_times[$curent_day];
            if (!empty($day_times)) {
                foreach ($day_times as $day_time) {
                    if(strtotime($current_time) > strtotime($day_time[0]) && strtotime($current_time) < strtotime($day_time[1])  ){
                        $is_wpwbot_open=true;
                    }
                }
            }
        }
    }
    return $is_wpwbot_open;
}

/**
 * wpwBot Search keyword product
 */
add_action('wp_ajax_qcld_wb_chatbot_keyword', 'qcld_wb_chatbot_keyword');
add_action('wp_ajax_nopriv_qcld_wb_chatbot_keyword', 'qcld_wb_chatbot_keyword');
function qcld_wb_chatbot_keyword(){
    // Verify nonce for security
    $nonce = isset($_POST['security']) ? sanitize_text_field(wp_unslash($_POST['security'])) : (isset($_POST['nonce']) ? sanitize_text_field(wp_unslash($_POST['nonce'])) : '');
    if ( ! wp_verify_nonce( $nonce, 'wp_chatbot' ) && ! wp_verify_nonce( $nonce, 'qcsecretbotnonceval123qc' ) ) {
        wp_send_json_error( array( 'status' => 'fail', 'message' => 'Security check failed.' ) );
        wp_die();
    }

    $keyword = sanitize_text_field(wp_unslash($_POST['keyword']));
    $product_per_page = get_option('qlcd_wp_chatbot_ppp') != '' ? get_option('qlcd_wp_chatbot_ppp') : 10;
    if (get_option('qlcd_wp_chatbot_search_option') == 'standard') {
        $product_orderby = sanitize_text_field(get_option('qlcd_wp_chatbot_product_orderby') != '' ? get_option('qlcd_wp_chatbot_product_orderby') : 'title');
        $product_order = sanitize_text_field(get_option('qlcd_wp_chatbot_product_order') != '' ? get_option('qlcd_wp_chatbot_product_order') : 'ASC');
        //Merging all query together.
        $argu_params = array(
            'post_type' => 'product',
            'post_status' => 'publish',
            'posts_per_page' => $product_per_page,
            'orderby' => $product_orderby,
            'order' => $product_order,
            's' => $keyword,
        );
        /******
         *WP Query Operation to get products.*
         *******/
        $product_query = new WP_Query($argu_params);
        $product_num = $product_query->post_count;
        //Getting total product number by string.
        $total_argu = array('post_type' => 'product', 's' => $keyword, 'posts_per_page' => 100);
        $total_query = new WP_Query($total_argu);
        $total_product_num = $total_query->post_count;
        $html = '<div class="wp-chatbot-products-area">';
        $_pf = new WC_Product_Factory();
        //repeating the products
        if ($product_num > 0) {
            $html .= '<ul class="wp-chatbot-products">';
            while ($product_query->have_posts()) : $product_query->the_post();
                $product = $_pf->get_product(get_the_ID());
                if (wp_chatbot_product_controlling(get_the_ID()) == true) {
                    $html .= '<li class="wp-chatbot-product">';
                    $html .= '<a target="_blank" href="' . get_permalink(get_the_ID()) . '"  wp-chatbot-pid= "' . get_the_ID() . '" title="' . esc_attr($product->post->post_title ? $product->post->post_title : get_the_ID()) . '">';
                    $html .= get_the_post_thumbnail(get_the_ID(), 'shop_catalog') . '
                       <div class="wp-chatbot-product-summary">
                       <div class="wp-chatbot-product-table">
                       <div class="wp-chatbot-product-table-cell">
                       <h3 class="wp-chatbot-product-title">' . $product->post->post_title . '</h3>
                       <div class="price">' . $product->get_price_html() . '</div>';
                    $html .= ' </div>
                       </div>
                       </div></a>
                       </li>';
                }
            endwhile;
            wp_reset_postdata();
            $html .= '</ul>';
            if ($total_product_num > $product_per_page && $product_per_page > 0 ) {
                $html .= '<p style="text-align: center"><button type="button" id="wp-chatbot-loadmore" data-offset="' . $product_per_page . '" data-search-type="product" data-search-term="' . esc_attr($keyword) . '" >' . wp_kses_post(wpb_randmom_message_handle(unserialize(get_option('qlcd_wp_chatbot_load_more')))) . ' <span id="wp-chatbot-loadmore-loader"></span></button> </p>';
            }
        }
        $html .= '</div>';
    } else if (get_option('qlcd_wp_chatbot_search_option') == 'advanced') {
        $result = wpwBot_Search::factory()->search($keyword);
        $products = $result['products'];
        $product_num = count($result['products']);
        $total_product_num = $result['total_products'];
        $more_product_ids = implode(",", $result['more_ids']);
        $html = '<div class="wp-chatbot-products-area">';
        $_pf = new WC_Product_Factory();
        //repeating the products
        if ($product_num > 0) {
            $html .= '<ul class="wp-chatbot-products">';
            foreach ($products as $product) {
                if (wp_chatbot_product_controlling($product->get_id()) == true) {
                    $html .= '<li class="wp-chatbot-product">';
                    $html .= '<a target="_blank" href="' . get_permalink($product->get_id()) . '" wp-chatbot-pid= "' . $product->get_id() . '"  title="' . esc_attr($product->get_title()) . '">';
                    $html .= get_the_post_thumbnail($product->get_id(), 'shop_catalog') . '
                       <div class="wp-chatbot-product-summary">
                       <div class="wp-chatbot-product-table">
                       <div class="wp-chatbot-product-table-cell">
                       <h3 class="wp-chatbot-product-title">' . $product->get_title() . '</h3>
                       <div class="price">' . $product->get_price_html() . '</div>';
                    $html .= ' </div></div></div></a></li>';
                }
            }
            $html .= '</ul>';
            if ($total_product_num > $product_per_page && $product_per_page > 0) {
                $html .= '<p style="text-align: center"><button type="button" id="wp-chatbot-loadmore" data-offset="' . $product_per_page . '" data-search-type="product" data-search-term="' . esc_attr($more_product_ids) . '" >' . wp_kses_post(wpb_randmom_message_handle(unserialize(get_option('qlcd_wp_chatbot_load_more')))) . ' <span id="wp-chatbot-loadmore-loader"></span></button> </p>';
            }
        }
        $html .= '</div>';
    }
    $response = array('html' => $html, 'product_num' => $total_product_num, 'per_page' => $product_per_page);
    wp_send_json($response);
   
}
/**
 * wpwBot Categories
 */
add_action('wp_ajax_qcld_wb_chatbot_category', 'qcld_wb_chatbot_category');
add_action('wp_ajax_nopriv_qcld_wb_chatbot_category', 'qcld_wb_chatbot_category');
function qcld_wb_chatbot_category(){
    $category_type="common";
    if (get_option('wp_chatbot_show_parent_category') != "") {
        $terms = get_terms( array( 'taxonomy' => 'product_cat', 'parent' => 0, 'hide_empty' => true, 'fields' => 'all' ) );
    } else {
        $terms = get_terms( array( 'taxonomy' => 'product_cat', 'hide_empty' => true, 'fields' => 'all' ) );
    }
    $html = "";
    foreach ($terms as $term) {
        $child_terms=get_terms( array( 'taxonomy' => 'product_cat', 'parent' => $term->term_id, 'hide_empty' => true, 'fields' => 'all' ) );
        if(get_option('wp_chatbot_show_sub_category')==1 && count($child_terms) >0){
            $category_type="hasChilds";
        }
        $html .= '<span class="qcld-chatbot-product-category" data-category-type="' . $category_type . '"  data-category-slug="' . $term->slug . '" data-category-id="' . $term->term_id . '">' . $term->name . '</span>';
    }
     wp_send_json($html);
    
}
/**
 * wpwBot Sub categories
 */
add_action('wp_ajax_qcld_wb_chatbot_sub_category', 'qcld_wb_chatbot_sub_category');
add_action('wp_ajax_nopriv_qcld_wb_chatbot_sub_category', 'qcld_wb_chatbot_sub_category');
function qcld_wb_chatbot_sub_category(){
    $parent_id = intval( wp_unslash( $_POST['parent_id'] ) );
    $terms = get_terms( array( 'taxonomy' => 'product_cat', 'parent' => $parent_id, 'hide_empty' => true, 'fields' => 'all' ) );
    $html = "";
    foreach ($terms as $term) {
        $html .= '<span class="qcld-chatbot-product-category" data-category-type="common"  data-category-slug="' . $term->slug . '" data-category-id="' . $term->term_id . '">' . $term->name . '</span>';
    }
     wp_send_json($html);
   
}
/**
 * wpwBot category product
 */
/**
 * wpwBot latest, featured, recent product
 */
//Product display controll
function wp_chatbot_product_controlling($product_id){
    $display_product = true;
    //Controlling Out of Stock product display from back end.
    $_pf = new WC_Product_Factory();
    $product = $_pf->get_product($product_id);
    if ($product->manage_stock == 'yes' && $product->stock_quantity <= 0 && get_option('wp_chatbot_exclude_stock_out_product') == 1) {
        $display_product = false;
    }
    return $display_product;
}
//Get Sale products
//load more
add_action('wp_ajax_qcld_wb_chatbot_load_more', 'qcld_wb_chatbot_load_more');
add_action('wp_ajax_nopriv_qcld_wb_chatbot_load_more', 'qcld_wb_chatbot_load_more');
function qcld_wb_chatbot_load_more(){
    $offset = intval( wp_unslash( $_POST['offset'] ) );
    $search_type = sanitize_text_field( wp_unslash( $_POST['search_type'] ) );
    $search_term = sanitize_text_field( wp_unslash( $_POST['search_term'] ) );
    $product_per_page = sanitize_text_field(get_option('qlcd_wp_chatbot_ppp') != '' ? get_option('qlcd_wp_chatbot_ppp') : 10);
    $product_orderby = sanitize_text_field(get_option('qlcd_wp_chatbot_product_orderby') != '' ? get_option('qlcd_wp_chatbot_product_orderby') : 'title');
    $product_order = sanitize_text_field(get_option('qlcd_wp_chatbot_product_order') != '' ? get_option('qlcd_wp_chatbot_product_order') : 'ASC');
    $next_offset = intval($product_per_page + $offset);
    if ($search_type == 'product' && $search_term != 'featured' && $search_term != 'sale' && get_option('qlcd_wp_chatbot_search_option') == 'advanced') {
        //if have multiple ids then explode else have single need to array push
        if (strpos($search_term, ',') !== false) {
            $product_ids = explode(',', $search_term);
        } else {
            $product_ids = array($search_term);
        }
        $result = wpwBot_Search::factory()->get_load_more_products($product_ids);
        $products = $result['products'];
        $product_num = count($result['products']);
        $total_product_num = $result['total_products'];
        $more_product_ids = implode(",", $result['more_ids']);
        $_pf = new WC_Product_Factory();
        //repeating the products
        $html = '';
        if ($product_num > 0) {
            foreach ($products as $product) {
                $html .= '<li class="wp-chatbot-product">';
                $html .= '<a target="_blank" href="' . get_permalink($product->get_id()) . '" wp-chatbot-pid= "' . $product->get_id() . '"  title="' . esc_attr($product->get_title()) . '">';
                $html .= get_the_post_thumbnail($product->get_id(), 'shop_catalog') . '
               <div class="wp-chatbot-product-summary">
               <div class="wp-chatbot-product-table">
               <div class="wp-chatbot-product-table-cell">
               <h3 class="wp-chatbot-product-title">' . $product->get_title() . '</h3>
               <div class="price">' . $product->get_price_html() . '</div>';
                $html .= ' </div></div></div></a></li>';
            }
        }
        $response = array('html' => $html, 'product_num' => $total_product_num, 'search_term' => $more_product_ids, 'offset' => $next_offset, 'per_page' => $product_per_page);
    } else {
        if ($search_type == 'product' && $search_term != 'featured' && $search_term != 'sale') {  //For Standard search
            $argu_params = array(
                'post_type' => 'product',
                'post_status' => 'publish',
                'posts_per_page' => $product_per_page,
                'offset' => $offset,
                'orderby' => $product_orderby,
                'order' => $product_order,
                's' => $search_term,
            );
        } else if ($search_type == 'category') {
            $argu_params = array(
                'post_type' => 'product',
                'post_status' => 'publish',
                'ignore_sticky_posts' => 1,
                'posts_per_page' => $product_per_page,
                'orderby' => $product_orderby,
                'order' => $product_order,
                'offset' => $offset,
                'tax_query' => array(
                    array(
                        'taxonomy' => 'product_cat',
                        'field' => 'term_id',
                        'terms' => $search_term,
                        'operator' => 'IN'
                    )
                )
            );
        } else if ($search_type == 'product' && $search_term == 'featured') {
            $argu_params = array(
                'post_type' => 'product',
                'post_status' => 'publish',
                'ignore_sticky_posts' => 1,
                'posts_per_page' => $product_per_page,
                'orderby' => $product_orderby,
                'order' => $product_order,
                'offset' => $offset,
                'meta_query' => array('key' => '_featured', 'value' => 'yes')
            );
        } else if ($search_type == 'product' && $search_term == 'sale') {
            $argu_params = array(
                'post_type' => 'product',
                'post_status' => 'publish',
                'ignore_sticky_posts' => 1,
                'posts_per_page' => $product_per_page,
                'orderby' => $product_orderby,
                'order' => $product_order,
                'offset' => $offset,
                'meta_query' => array(
                    'relation' => 'OR',
                    array( // Simple products type
                        'key' => '_sale_price',
                        'value' => 0,
                        'compare' => '>',
                        'type' => 'numeric'
                    ),
                    array( // Variable products type
                        'key' => '_min_variation_sale_price',
                        'value' => 0,
                        'compare' => '>',
                        'type' => 'numeric'
                    )
                )
            );
        }
        $product_query = new WP_Query($argu_params);
        $product_num = $product_query->post_count;
        $_pf = new WC_Product_Factory();
        //repeating the products
        $html = '';
        if ($product_num > 0) {
            while ($product_query->have_posts()) : $product_query->the_post();
                $product = $_pf->get_product(get_the_ID());
                $html .= '<li class="wp-chatbot-product">';
                $html .= '<a target="_blank" href="' . get_permalink(get_the_ID()) . '"  wp-chatbot-pid= "' . get_the_ID() . '" title="' . esc_attr($product->post->post_title ? $product->post->post_title : get_the_ID()) . '">';
                $html .= get_the_post_thumbnail(get_the_ID(), 'shop_catalog') . '
                   <div class="wp-chatbot-product-summary">
                   <div class="wp-chatbot-product-table">
                   <div class="wp-chatbot-product-table-cell">
                   <h3 class="wp-chatbot-product-title">' . $product->post->post_title . '</h3>
                   <div class="price">' . $product->get_price_html() . '</div>';
                $html .= ' </div>
                   </div>
                   </div></a>
                   </li>';
            endwhile;
            wp_reset_postdata();
        } else {
            $html .= '';
        }
        $response = array('html' => $html, 'product_num' => $product_num, 'search_term' => $search_term, 'offset' => $next_offset, 'per_page' => $product_per_page);
    }
    wp_send_json($response);
}
//product details
add_action('wp_ajax_qcld_wb_chatbot_product_details', 'qcld_wb_chatbot_product_details');
add_action('wp_ajax_nopriv_qcld_wb_chatbot_product_details', 'qcld_wb_chatbot_product_details');
function qcld_wb_chatbot_product_details(){
    $product_id = intval( wp_unslash( $_POST['wp_chatbot_pid'] ) );
    //Tracking product view from chat board
    wp_chatbot_view_track_product_by_id($product_id);
    //wpcommerce product factory
    $wc_pf = new WC_Product_Factory();
    $product = $wc_pf->get_product($product_id);
    $product_type = $wc_pf->get_product_type($product_id);
    $product_title = '<h2 id="wp-chatbot-product-title" ><a target="_blank" href="' . get_permalink($product->get_id()) . '">' . $product->get_title() . '</a></h2>';
    $product_desc = apply_filters('the_excerpt', $product->post->post_excerpt);//$product->post->post_excerpt;
    $gallery_ids = $product->get_gallery_image_ids();
    //images processing..
    $product_feature_image_id = get_post_thumbnail_id($product_id);
    $product_feature_image = wp_get_attachment_image_src($product_feature_image_id, 'full');
    $product_feature_thumb = wp_get_attachment_image_src($product_feature_image_id, 'shop_thumbnail');
    //$full_img_src = $product_feature_image[0];
    //$product_image = '<img  style="width:500px;height:300px" src="' . $full_img_src . '" >';
    $product_image = '<div class="wp-chatbot-product-image-large">
                     <a href="' . $product_feature_image[0] . '" id="wp-chatbot-product-image-large-path"><img id="wp-chatbot-product-image-large-src" src="' . $product_feature_image[0] . '" alt="Large Image" title="Zoom Image" /></a>
                      </div>';
    $product_image .= '<div class="wp-chatbot-product-image-thumbs"><ul> 
                      <li class="wp-chatbot-product-active-image-thumbs"><a href="' . $product_feature_image[0] . '" class="wp-chatbot-product-image-thumbs-path"><img  class="wp-chatbot-product-image-thumbs-src" src="' . $product_feature_thumb[0] . '" alt="Thumb Image" /></a></li>';
    if (!empty($gallery_ids)) {
        foreach ($gallery_ids as $gallery_id) {
            $gallery_image = wp_get_attachment_image_src($gallery_id, 'full');
            $gallery_thumb = wp_get_attachment_image_src($gallery_id, 'shop_thumbnail');
            $product_image .= '<li><a href="' . $gallery_image[0] . '" class="wp-chatbot-product-image-thumbs-path"><img class="wp-chatbot-product-image-thumbs-src" src="' . $gallery_thumb[0] . '" alt="Thumb Image" /></a></li>';
        }
    }
    $product_image .= '</ul></div>';
    $product_price = '<p class="wp-chatbot-product-price" id="wp-chatbot-product-price">' . $product->get_price_html() . '</p>';
    $product_sku = '<p class="wp-chatbot-product-sku"> ' . __('SKU', 'chatbot') . ' : ' . $product->get_sku() . '</p>';
    //if ( $product->is_in_stock() || $product->is_purchasable() )
    //Handle variable product start
    $variations = "";
    $add_cart_button = "";
    $product_quantity = "";
    if ($product->is_in_stock()) {
        if ($product_type == "variable") {
            //Getting product variation number based details
            $variations_data = array();
            foreach ($product->get_children() as $child_id) {
                $all_cfs = get_post_custom($child_id);
                array_push($variations_data, array('variation_id' => $child_id, 'variation_data' => $all_cfs));
            }
            $variations_data = json_encode($variations_data);
            $attributes = $product->get_attributes();
            //Handling Variant & Non Variant products
            $var_attrs = $product->get_variation_attributes();
            $varation_names = array();
            if (!empty($var_attrs)) {
                foreach ($var_attrs as $key => $value) {
                    array_push($varation_names, $key);
                }
            }
            $debug = $varation_names;
            foreach ($attributes as $attribute) {
                /*if (empty($attribute['is_visible']) || ($attribute['is_taxonomy'] && !taxonomy_exists($attribute['name']))) {
                    continue;
                }*/
                $title = wc_attribute_label($attribute['name']);
                $name = $attribute['name'];
                if ($attribute['is_taxonomy']) {
                    $values = wc_get_product_terms($product->get_id(), $attribute['name'], array('fields' => 'slugs'));
                } else {
                    $values = array_map('trim', explode(WC_DELIMITER, $attribute['value']));
                }
                natsort($values);
                if (!in_array($name, $varation_names)) {
                    $variations .= '<p><label for="' . sanitize_title($name) . '">' . $title . '</label> ' . ucfirst(implode(",", $values)) . '</p>';
                } else {
                    $variations .= '<div class="wp-chatbot-variable-' . sanitize_title($name) . '">';
                    $variations .= '<label for="' . sanitize_title($name) . '">' . $title . '</label>';
                    $variations .= '<select id="' . esc_attr(sanitize_title($name)) . '" name="attribute_' . sanitize_title($name) . '" data-attribute_name="attribute_' . sanitize_title($name) . '" class="each_attribute">';
                    $variations .= '<option value="">' . wp_kses_post(wpb_randmom_message_handle(unserialize(get_option('qlcd_wp_chatbot_choose_option')))) . '</option>';
                    foreach ($values as $value) {
                        $attr_key = 'attribute_' . sanitize_title( $name );
                        if ( isset( $_REQUEST[ $attr_key ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
                            $selected_value = sanitize_text_field( wp_unslash( $_REQUEST[ $attr_key ] ) );
                        } else {
                            $selected_value = '';
                        }
                        $variations .= '<option value="' . esc_attr(strtolower($value)) . '"' . selected($selected_value, $value, false) . '>' . apply_filters('wpcommerce_variation_option_name', $value) . '</option>';
                    }
                    $variations .= '</select></div>';
                }
            }
            $add_cart_button .= '<input type="button"  id="wp-chatbot-variation-add-to-cart" wp-chatbot-product-id="' . $product_id . '" value="' . wp_kses_post(wpb_randmom_message_handle(unserialize(get_option('qlcd_wp_chatbot_add_to_cart')))) . '" variation_id="" />';
            $add_cart_button .= "<input type='hidden'   id='wp-chatbot-variation-data'  data-product-variation='" . $variations_data . "' />";
        } else {
            $add_cart_button .= '<input type="button" id="wp-chatbot-add-cart-button" wp-chatbot-product-id="' . $product_id . '" value="' . wp_kses_post(wpb_randmom_message_handle(unserialize(get_option('qlcd_wp_chatbot_add_to_cart')))) . '" />';
        }
        //Handle variable product end.
        $product_quantity .= '<label for="">' . wp_kses_post(wpb_randmom_message_handle(unserialize(get_option('qlcd_wp_chatbot_cart_quantity')))) . '</label>
       <input type="number" id="vPQuantity" value="1" />';
    }
    //$response=$full_size_image;
    $response = array('title' => $product_title, 'description' => $product_desc, 'image' => $product_image, 'price' => $product_price, 'sku' => $product_sku, 'quantity' => $product_quantity, 'buttton' => $add_cart_button, 'variation' => $variations, 'type' => $product_type, 'debug' => $debug);
    wp_send_json($response);
}
//Add to cart for variable product.
add_action('wp_ajax_qcld_variable_add_to_cart', 'qcld_variable_add_to_cart');
add_action('wp_ajax_nopriv_qcld_variable_add_to_cart', 'qcld_variable_add_to_cart');
function qcld_variable_add_to_cart(){
    $product_id = intval( wp_unslash( $_POST['p_id'] ) );
    $quantity = intval( wp_unslash( $_POST['quantity'] ) );
    $variations_id = intval( wp_unslash( $_POST['variations_id'] ) );
    $attrs = isset( $_POST['attributes'] ) ? array_map( 'sanitize_text_field', wp_unslash( (array) $_POST['attributes'] ) ) : array();
    //echo wp_send_json(array('p_id'=>$product_id,'qnty'=>$quantity,'id'=>$variations_id,'att'=>$attrs));
    $attributes = array();
    foreach ($attrs as $attr) {
        $single = explode("#", $attr);
        if (isset($single[0])) {
            $a_name = explode("_", $single[0]);
        }
        $attributes[$a_name[2]] = $single[1];
    }
    global $wpcommerce;
    $result = $wpcommerce->cart->add_to_cart($product_id, $quantity, $variations_id, $attributes, null);
    if ($result != false) {
        wp_send_json('variable');
    } else {
        wp_send_json('error');
    }
}
//Add to cart for simple product.
add_action('wp_ajax_qcld_wb_chatbot_add_to_cart', 'qcld_wb_chatbot_add_to_cart');
add_action('wp_ajax_nopriv_qcld_wb_chatbot_add_to_cart', 'qcld_wb_chatbot_add_to_cart');
function qcld_wb_chatbot_add_to_cart(){
    $product_id = intval( wp_unslash( $_POST['product_id'] ) );
    $product_quantity = intval( wp_unslash( $_POST['quantity'] ) );
    global $wpcommerce;
    $result = $wpcommerce->cart->add_to_cart($product_id, $product_quantity);
    if ($result != false) {
        wp_send_json('simple');
    } else {
        wp_send_json('error');
    }
}
if ( ! function_exists( 'qc_fnc_get_ip_address' ) ) {
	function qc_fnc_get_ip_address() {
		if ( ! empty( $_SERVER['HTTP_CLIENT_IP'] ) ) {
			$ip_address = sanitize_text_field( wp_unslash( $_SERVER['HTTP_CLIENT_IP'] ) );
		} elseif ( ! empty( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) {
			$ip_address = sanitize_text_field( wp_unslash( $_SERVER['HTTP_X_FORWARDED_FOR'] ) );
		} elseif ( ! empty( $_SERVER['REMOTE_ADDR'] ) ) {
			$ip_address = sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) );
		} else {
			$ip_address = '';
		}
		return $ip_address;
	}
}

//Support part
add_action('wp_ajax_qcld_wb_chatbot_support_email', 'qcld_wb_chatbot_support_email');
add_action('wp_ajax_nopriv_qcld_wb_chatbot_support_email', 'qcld_wb_chatbot_support_email');
if ( ! function_exists( 'qcld_wb_chatbot_support_email' ) ) {
	function qcld_wb_chatbot_support_email(){
		$nonce = isset( $_POST['nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['nonce'] ) ) : '';
		if ( ! wp_verify_nonce( $nonce, 'qcsecretbotnonceval123qc' ) ) {
			wp_send_json_error( array( 'error' => esc_html__( 'Error: Invalid nonce verification.', 'chatbot' ) ) );
		}   
		$name    = isset( $_POST['name'] ) ? trim( sanitize_text_field( wp_unslash( $_POST['name'] ) ) ) : '';
		$email   = isset( $_POST['email'] ) ? sanitize_email( wp_unslash( $_POST['email'] ) ) : '';
		$message = isset( $_POST['message'] ) ? sanitize_text_field( wp_unslash( $_POST['message'] ) ) : '';
		$refurl  = isset( $_POST['url'] ) ? esc_url_raw( wp_unslash( $_POST['url'] ) ) : '';
		$subject = sanitize_text_field( get_option('qlcd_wp_chatbot_email_sub') != '' ? get_option('qlcd_wp_chatbot_email_sub') : 'Support Email from wpWBot by Client' );
		
		$user_agent = isset( $_SERVER['HTTP_USER_AGENT'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ) : '';
		$ip_address = qc_fnc_get_ip_address();

		//Extract Domain
		$url    = get_site_url();
		$url    = wp_parse_url($url);
		$domain = $url['host'];
		$admin_email = sanitize_email(get_option('admin_email'));
		$toEmail     = sanitize_email(get_option('qlcd_wp_chatbot_admin_email') != '' ? get_option('qlcd_wp_chatbot_admin_email') : $admin_email);
		
		if(get_option('qlcd_wp_chatbot_from_email') && get_option('qlcd_wp_chatbot_from_email')!=''){
			$fromEmail = get_option('qlcd_wp_chatbot_from_email');
		}else{
			$fromEmail = "wordpress@" . $domain;
		}

		//Starting messaging and status.
		$response['status'] = 'fail';
		$response['message'] = str_replace('\\', '', wp_kses_post(get_option('qlcd_wp_chatbot_email_fail')));

		if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
			$response['message'] = wp_kses_post(wpb_randmom_message_handle(unserialize(get_option('qlcd_wp_chatbot_invalid_email'))));
			$response['status']  = 'fail';
		} else {
			$primary_color = '#5b4e96';
			$site_name     = get_bloginfo( 'name' );
			$site_url      = site_url();
			$reply_subject = 'Re: ' . $subject;
			$mailto_url    = 'mailto:' . esc_attr( $email ) . '?subject=' . rawurlencode( $reply_subject );
			$date_str      = current_time( 'M j, Y - g:i a' );

			$bodyContent = '<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>' . esc_html( $subject ) . '</title>
<style>
:root {
	--woobot-primary-color: ' . esc_attr( $primary_color ) . ';
}
.woobot-primary-btn {
	background-color: ' . esc_attr( $primary_color ) . ' !important;
	border: 1px solid ' . esc_attr( $primary_color ) . ' !important;
	color: #ffffff !important;
}
.woobot-primary-tab {
	background-color: ' . esc_attr( $primary_color ) . ' !important;
	border: 1px solid ' . esc_attr( $primary_color ) . ' !important;
	color: #ffffff !important;
}
</style>
</head>
<body style="margin: 0; padding: 0; background-color: #f4f6fb; font-family: -apple-system, BlinkMacSystemFont, \'Segoe UI\', Roboto, Helvetica, Arial, sans-serif; color: #334155;">
	<table role="presentation" border="0" cellpadding="0" cellspacing="0" width="100%" style="background-color: #f4f6fb; padding: 30px 10px;">
		<tr>
			<td align="center">
				<table role="presentation" border="0" cellpadding="0" cellspacing="0" width="100%" style="max-width: 640px; background-color: #ffffff; border-radius: 14px; overflow: hidden; box-shadow: 0 6px 24px rgba(91, 78, 150, 0.08); border: 1px solid #e2e8f0;">
					<!-- Header Banner -->
					<tr>
						<td class="woobot-primary-tab" style="background: ' . esc_attr( $primary_color ) . '; background: linear-gradient(135deg, ' . esc_attr( $primary_color ) . ' 0%, #463a7a 100%); padding: 32px 30px; text-align: left; color: #ffffff;">
							<div style="font-size: 13px; font-weight: 600; text-transform: uppercase; letter-spacing: 1px; color: rgba(255,255,255,0.85); margin-bottom: 6px;">' . esc_html( $site_name ) . '</div>
							<h1 style="margin: 0; font-size: 24px; font-weight: 700; color: #ffffff; line-height: 1.3;">' . esc_html__( 'New Support Request', 'chatbot' ) . '</h1>
							<p style="margin: 6px 0 16px 0; font-size: 14px; color: rgba(255,255,255,0.9); line-height: 1.4;">' . esc_html__( 'A visitor submitted a support inquiry via WPBot.', 'chatbot' ) . '</p>
							<div>
								<span style="display: inline-block; background: rgba(255,255,255,0.18); border: 1px solid rgba(255,255,255,0.3); color: #ffffff; padding: 4px 10px; border-radius: 20px; font-size: 12px; font-weight: 600; margin-right: 6px; margin-top: 4px;">' . esc_html__( 'Status: New', 'chatbot' ) . '</span>
								<span style="display: inline-block; background: rgba(255,255,255,0.18); border: 1px solid rgba(255,255,255,0.3); color: #ffffff; padding: 4px 10px; border-radius: 20px; font-size: 12px; font-weight: 600; margin-right: 6px; margin-top: 4px;">' . esc_html( $date_str ) . '</span>
								<span style="display: inline-block; background: rgba(255,255,255,0.18); border: 1px solid rgba(255,255,255,0.3); color: #ffffff; padding: 4px 10px; border-radius: 20px; font-size: 12px; font-weight: 600; margin-top: 4px;">' . esc_html__( 'Source: Live Chat', 'chatbot' ) . '</span>
							</div>
						</td>
					</tr>
					<!-- Content -->
					<tr>
						<td style="padding: 28px 30px; background-color: #ffffff;">
							<!-- Message Callout Box -->
							<div style="margin-bottom: 24px;">
								<div style="font-size: 12px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; color: ' . esc_attr( $primary_color ) . '; margin-bottom: 8px;">' . esc_html__( 'Customer Message', 'chatbot' ) . '</div>
								<div style="background-color: #faf9fd; border-left: 4px solid ' . esc_attr( $primary_color ) . '; border-radius: 0 10px 10px 0; padding: 16px 20px; font-size: 15px; line-height: 1.6; color: #1e293b; word-break: break-word;">
									' . nl2br( esc_html( $message ) ) . '
								</div>
							</div>

							<!-- Details Table -->
							<div style="font-size: 12px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; color: #64748b; margin-bottom: 10px;">' . esc_html__( 'Contact & Request Details', 'chatbot' ) . '</div>
							<table role="presentation" border="0" cellpadding="0" cellspacing="0" width="100%" style="border-collapse: collapse; margin-bottom: 25px; border: 1px solid #e4e0f2; border-radius: 10px; overflow: hidden;">
								<tr style="border-bottom: 1px solid #f1f5f9; background-color: #ffffff;">
									<td style="padding: 12px 16px; font-size: 13.5px; font-weight: 600; color: #475569; width: 30%;">' . esc_html__( 'Sender Name', 'chatbot' ) . '</td>
									<td style="padding: 12px 16px; font-size: 13.5px; font-weight: 600; color: #1e293b;">' . esc_html( $name ) . '</td>
								</tr>
								<tr style="border-bottom: 1px solid #f1f5f9; background-color: #faf9fd;">
									<td style="padding: 12px 16px; font-size: 13.5px; font-weight: 600; color: #475569;">' . esc_html__( 'Sender Email', 'chatbot' ) . '</td>
									<td style="padding: 12px 16px; font-size: 13.5px; color: #1e293b;">
										<a href="mailto:' . esc_attr( $email ) . '" style="color: ' . esc_attr( $primary_color ) . '; font-weight: 600; text-decoration: underline;">' . esc_html( $email ) . '</a>
									</td>
								</tr>
								<tr style="border-bottom: 1px solid #f1f5f9; background-color: #ffffff;">
									<td style="padding: 12px 16px; font-size: 13.5px; font-weight: 600; color: #475569;">' . esc_html__( 'Subject', 'chatbot' ) . '</td>
									<td style="padding: 12px 16px; font-size: 13.5px; color: #1e293b;">' . esc_html( $subject ) . '</td>
								</tr>
								' . ( ! empty( $refurl ) ? '
								<tr style="border-bottom: 1px solid #f1f5f9; background-color: #faf9fd;">
									<td style="padding: 12px 16px; font-size: 13.5px; font-weight: 600; color: #475569;">' . esc_html__( 'Origin Page', 'chatbot' ) . '</td>
									<td style="padding: 12px 16px; font-size: 13px; color: #1e293b; word-break: break-all;">
										<a href="' . esc_url( $refurl ) . '" target="_blank" style="color: ' . esc_attr( $primary_color ) . '; text-decoration: underline;">' . esc_html( $refurl ) . '</a>
									</td>
								</tr>' : '' ) . '
								' . ( ! empty( $ip_address ) ? '
								<tr style="border-bottom: 1px solid #f1f5f9; background-color: #ffffff;">
									<td style="padding: 12px 16px; font-size: 13.5px; font-weight: 600; color: #475569;">' . esc_html__( 'IP Address', 'chatbot' ) . '</td>
									<td style="padding: 12px 16px; font-size: 13px; color: #64748b;">
										<code style="background-color: #f1f5f9; padding: 2px 6px; border-radius: 4px; font-family: monospace;">' . esc_html( $ip_address ) . '</code>
									</td>
								</tr>' : '' ) . '
								' . ( ! empty( $user_agent ) ? '
								<tr style="background-color: #faf9fd;">
									<td style="padding: 12px 16px; font-size: 13.5px; font-weight: 600; color: #475569;">' . esc_html__( 'User Agent', 'chatbot' ) . '</td>
									<td style="padding: 12px 16px; font-size: 12px; color: #64748b; line-height: 1.4; word-break: break-word;">' . esc_html( $user_agent ) . '</td>
								</tr>' : '' ) . '
							</table>

							<!-- Action Buttons -->
							<div style="margin-top: 30px; text-align: center;">
								<a href="' . $mailto_url . '" class="woobot-primary-btn" style="background-color: ' . esc_attr( $primary_color ) . '; border: 1px solid ' . esc_attr( $primary_color ) . '; color: #ffffff; padding: 12px 28px; border-radius: 8px; font-weight: 600; font-size: 14px; text-decoration: none; display: inline-block; box-shadow: 0 4px 12px rgba(91, 78, 150, 0.25); margin: 4px;">
									' . esc_html__( 'Reply to Customer', 'chatbot' ) . ' &rarr;
								</a>
							</div>
						</td>
					</tr>
					<!-- Footer -->
					<tr>
						<td style="background-color: #faf9fd; padding: 20px 30px; border-top: 1px solid #e4e0f2; text-align: center; color: #64748b; font-size: 12.5px; line-height: 1.5;">
							<p style="margin: 0 0 4px 0;">' . sprintf( esc_html__( 'This support request was submitted on %s via WPBot Chat.', 'chatbot' ), '<strong>' . esc_html( $site_name ) . '</strong>' ) . '</p>
							<p style="margin: 0; color: #94a3b8; font-size: 11.5px;">&copy; ' . gmdate( 'Y' ) . ' ' . esc_html( $site_name ) . ' &bull; <a href="' . esc_url( $site_url ) . '" style="color: ' . esc_attr( $primary_color ) . '; text-decoration: none;">' . esc_html( wp_parse_url( $site_url, PHP_URL_HOST ) ) . '</a></p>
						</td>
					</tr>
				</table>
			</td>
		</tr>
	</table>
</body>
</html>';

			$to        = $toEmail;
			$body      = $bodyContent;
			$headers   = array();
			$headers[] = 'Content-Type: text/html; charset=UTF-8';
			$headers[] = 'From: ' . esc_html( $name ) . ' <' . esc_html( $fromEmail ) . '>';
			$headers[] = 'Reply-To: ' . esc_html( $name ) . ' <' . esc_html( $email ) . '>';
			$result    = wp_mail( $to, $subject, $body, $headers );
			if ( $result ) {
				$response['status'] = 'success';
				$response['message'] = str_replace('\\', '', wp_kses_post(get_option('qlcd_wp_chatbot_email_sent')));
			}
		}
		echo wp_json_encode($response);
		die();
	}
}
//Support Phone
add_action('wp_ajax_qcld_wb_chatbot_support_phone', 'qcld_wb_chatbot_support_phone');
add_action('wp_ajax_nopriv_qcld_wb_chatbot_support_phone', 'qcld_wb_chatbot_support_phone');
if ( ! function_exists( 'qcld_wb_chatbot_support_phone' ) ) {
	function qcld_wb_chatbot_support_phone(){
		check_ajax_referer('qcsecretbotnonceval123qc', 'nonce');
		$name  = trim(sanitize_text_field(wp_unslash($_POST['name'])));
		$phone = sanitize_text_field(wp_unslash($_POST['phone']));

		$matches = array();
		// returns all results in array $matches
		preg_match_all( '/[0-9]{3}[\-][0-9]{6}|[0-9]{3}[\s][0-9]{6}|[0-9]{3}[\s][0-9]{3}[\s][0-9]{4}| [0-9]{15}|[0-9]{14}|[0-9]{13}|[0-9]{12}|[0-9]{11}|[0-9]{10}|[0-9]{9}|[0-9]{8}|[0-9]{7}|[0-9]{3}[\-][0-9]{3}[\-][0-9]{4}/', $phone, $matches );

		$matches = $matches[0];
		if ( empty( $matches ) ) {
			$response['status']  = 'invalid';
			$response['message'] = str_replace( '\\', '', get_option( 'qlcd_wp_chatbot_phone_fail' ) );
			echo wp_json_encode( $response );
		} else {
			$phone = $matches[0];

			$subject = ( get_option( 'qlcd_wp_chatbot_callback_email_sub' ) != '' ? maybe_unserialize( get_option( 'qlcd_wp_chatbot_callback_email_sub' ) ) : 'WPBot Support Mail Request for Callback' );

			if ( is_array( $subject ) && function_exists( 'get_wpbot_locale' ) && isset( $subject[ get_wpbot_locale() ] ) ) {
				$subject = $subject[ get_wpbot_locale() ];
			} elseif ( is_array( $subject ) ) {
				$subject = reset( $subject );
			}

			//Extract Domain
			$url    = get_site_url();
			$url    = wp_parse_url( $url );
			$domain = $url['host'];

			$admin_email = sanitize_email( get_option( 'admin_email' ) );
			$toEmail     = sanitize_email( get_option( 'qlcd_wp_chatbot_admin_email' ) != '' ? get_option( 'qlcd_wp_chatbot_admin_email' ) : $admin_email );
			$fromEmail   = 'wordpress@' . $domain;

			if ( get_option( 'qlcd_wp_chatbot_from_email' ) && get_option( 'qlcd_wp_chatbot_from_email' ) != '' ) {
				$fromEmail = sanitize_email( get_option( 'qlcd_wp_chatbot_from_email' ) );
			}

			$replyto = $fromEmail;

			if ( get_option( 'qlcd_wp_chatbot_reply_to_email' ) && get_option( 'qlcd_wp_chatbot_reply_to_email' ) != '' ) {
				$replyto = sanitize_email( get_option( 'qlcd_wp_chatbot_reply_to_email' ) );
			}

			//Starting messaging and status.
			$response['status']  = 'fail';
			$response['message'] = str_replace( '\\', '', wp_kses_post( get_option( 'qlcd_wp_chatbot_phone_fail' ) ) );
			$primary_color = '#5b4e96';
			$site_name     = get_bloginfo( 'name' );
			$site_url      = site_url();
			$date_str      = current_time( 'M j, Y - g:i a' );
			$tel_url       = 'tel:' . esc_attr( preg_replace( '/[^0-9+]/', '', $phone ) );

			$bodyContent = '<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>' . esc_html( $subject ) . '</title>
<style>
:root {
	--woobot-primary-color: ' . esc_attr( $primary_color ) . ';
}
.woobot-primary-btn {
	background-color: ' . esc_attr( $primary_color ) . ' !important;
	border: 1px solid ' . esc_attr( $primary_color ) . ' !important;
	color: #ffffff !important;
}
.woobot-primary-tab {
	background-color: ' . esc_attr( $primary_color ) . ' !important;
	border: 1px solid ' . esc_attr( $primary_color ) . ' !important;
	color: #ffffff !important;
}
</style>
</head>
<body style="margin: 0; padding: 0; background-color: #f4f6fb; font-family: -apple-system, BlinkMacSystemFont, \'Segoe UI\', Roboto, Helvetica, Arial, sans-serif; color: #334155;">
	<table role="presentation" border="0" cellpadding="0" cellspacing="0" width="100%" style="background-color: #f4f6fb; padding: 30px 10px;">
		<tr>
			<td align="center">
				<table role="presentation" border="0" cellpadding="0" cellspacing="0" width="100%" style="max-width: 640px; background-color: #ffffff; border-radius: 14px; overflow: hidden; box-shadow: 0 6px 24px rgba(91, 78, 150, 0.08); border: 1px solid #e2e8f0;">
					<!-- Header Banner -->
					<tr>
						<td class="woobot-primary-tab" style="background: ' . esc_attr( $primary_color ) . '; background: linear-gradient(135deg, ' . esc_attr( $primary_color ) . ' 0%, #463a7a 100%); padding: 32px 30px; text-align: left; color: #ffffff;">
							<div style="font-size: 13px; font-weight: 600; text-transform: uppercase; letter-spacing: 1px; color: rgba(255,255,255,0.85); margin-bottom: 6px;">' . esc_html( $site_name ) . '</div>
							<h1 style="margin: 0; font-size: 24px; font-weight: 700; color: #ffffff; line-height: 1.3;">' . esc_html__( 'New Call Request', 'chatbot' ) . '</h1>
							<p style="margin: 6px 0 16px 0; font-size: 14px; color: rgba(255,255,255,0.9); line-height: 1.4;">' . esc_html__( 'A visitor requested a phone callback via WPBot.', 'chatbot' ) . '</p>
							<div>
								<span style="display: inline-block; background: rgba(255,255,255,0.18); border: 1px solid rgba(255,255,255,0.3); color: #ffffff; padding: 4px 10px; border-radius: 20px; font-size: 12px; font-weight: 600; margin-right: 6px; margin-top: 4px;">' . esc_html__( 'Action Required', 'chatbot' ) . '</span>
								<span style="display: inline-block; background: rgba(255,255,255,0.18); border: 1px solid rgba(255,255,255,0.3); color: #ffffff; padding: 4px 10px; border-radius: 20px; font-size: 12px; font-weight: 600; margin-right: 6px; margin-top: 4px;">' . esc_html( $date_str ) . '</span>
								<span style="display: inline-block; background: rgba(255,255,255,0.18); border: 1px solid rgba(255,255,255,0.3); color: #ffffff; padding: 4px 10px; border-radius: 20px; font-size: 12px; font-weight: 600; margin-top: 4px;">' . esc_html__( 'Source: Live Chat', 'chatbot' ) . '</span>
							</div>
						</td>
					</tr>
					<!-- Content -->
					<tr>
						<td style="padding: 28px 30px; background-color: #ffffff;">
							<!-- Details Table -->
							<div style="font-size: 12px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; color: #64748b; margin-bottom: 10px;">' . esc_html__( 'Callback Details', 'chatbot' ) . '</div>
							<table role="presentation" border="0" cellpadding="0" cellspacing="0" width="100%" style="border-collapse: collapse; margin-bottom: 25px; border: 1px solid #e4e0f2; border-radius: 10px; overflow: hidden;">
								<tr style="border-bottom: 1px solid #f1f5f9; background-color: #ffffff;">
									<td style="padding: 12px 16px; font-size: 13.5px; font-weight: 600; color: #475569; width: 30%;">' . esc_html__( 'Contact Name', 'chatbot' ) . '</td>
									<td style="padding: 12px 16px; font-size: 13.5px; font-weight: 600; color: #1e293b;">' . esc_html( $name ) . '</td>
								</tr>
								<tr style="border-bottom: 1px solid #f1f5f9; background-color: #faf9fd;">
									<td style="padding: 12px 16px; font-size: 13.5px; font-weight: 600; color: #475569;">' . esc_html__( 'Phone Number', 'chatbot' ) . '</td>
									<td style="padding: 12px 16px; font-size: 15px; font-weight: 700; color: ' . esc_attr( $primary_color ) . ';">
										<a href="' . $tel_url . '" style="color: ' . esc_attr( $primary_color ) . '; text-decoration: none;">' . esc_html( $phone ) . '</a>
									</td>
								</tr>
								<tr style="border-bottom: 1px solid #f1f5f9; background-color: #ffffff;">
									<td style="padding: 12px 16px; font-size: 13.5px; font-weight: 600; color: #475569;">' . esc_html__( 'Subject', 'chatbot' ) . '</td>
									<td style="padding: 12px 16px; font-size: 13.5px; color: #1e293b;">' . esc_html( $subject ) . '</td>
								</tr>
								<tr style="background-color: #faf9fd;">
									<td style="padding: 12px 16px; font-size: 13.5px; font-weight: 600; color: #475569;">' . esc_html__( 'Message', 'chatbot' ) . '</td>
									<td style="padding: 12px 16px; font-size: 13.5px; color: #1e293b;">' . esc_html__( 'Please call me back at ', 'chatbot' ) . '<strong>' . esc_html( $phone ) . '</strong></td>
								</tr>
							</table>

							<!-- Action Buttons -->
							<div style="margin-top: 30px; text-align: center;">
								<a href="' . $tel_url . '" class="woobot-primary-btn" style="background-color: ' . esc_attr( $primary_color ) . '; border: 1px solid ' . esc_attr( $primary_color ) . '; color: #ffffff; padding: 12px 28px; border-radius: 8px; font-weight: 600; font-size: 14px; text-decoration: none; display: inline-block; box-shadow: 0 4px 12px rgba(91, 78, 150, 0.25); margin: 4px;">
									' . esc_html__( 'Call Customer Now', 'chatbot' ) . ' &rarr;
								</a>
							</div>
						</td>
					</tr>
					<!-- Footer -->
					<tr>
						<td style="background-color: #faf9fd; padding: 20px 30px; border-top: 1px solid #e4e0f2; text-align: center; color: #64748b; font-size: 12.5px; line-height: 1.5;">
							<p style="margin: 0 0 4px 0;">' . sprintf( esc_html__( 'This call request was submitted on %s via WPBot Chat.', 'chatbot' ), '<strong>' . esc_html( $site_name ) . '</strong>' ) . '</p>
							<p style="margin: 0; color: #94a3b8; font-size: 11.5px;">&copy; ' . gmdate( 'Y' ) . ' ' . esc_html( $site_name ) . ' &bull; <a href="' . esc_url( $site_url ) . '" style="color: ' . esc_attr( $primary_color ) . '; text-decoration: none;">' . esc_html( wp_parse_url( $site_url, PHP_URL_HOST ) ) . '</a></p>
						</td>
					</tr>
				</table>
			</td>
		</tr>
	</table>
</body>
</html>';

			$to           = $toEmail;
			$body         = $bodyContent;
			$headers      = array();
			$headers[]    = 'Content-Type: text/html; charset=UTF-8';
			$headers[]    = 'From: ' . esc_html( $name ) . ' <' . esc_html( $fromEmail ) . '>';
			$headers[]    = 'Reply-To: ' . esc_html( $name ) . ' <' . esc_html( $replyto ) . '>';
			$result       = wp_mail( $to, $subject, $body, $headers );
			if ( $result ) {
				$response['status']  = 'success';
				$response['message'] = str_replace( '\\', '', wp_kses_post( get_option( 'qlcd_wp_chatbot_phone_sent' ) ) );
			}
			ob_clean();
			echo wp_json_encode( $response );
		}
		die();
	}
}
// Order Status part. removed

function wpb_randmom_message_handle($items){
    return $items[wp_rand(0, count($items) - 1)];
}
function qcld_wb_chatbot_func_str_replace($messages = array()){
    $refined_mesgses = array();
    foreach ($messages as $message) {
        $refined_msg = str_replace('\\', '', $message);
        array_push($refined_mesgses, $refined_msg);
    }
    return $refined_mesgses;
}
function get_order_by_username($user_name){
    global $post;
    $response = array();
    $response['status'] .= "success";
    $user = get_user_by('login', $user_name);
    // The query arguments
    $customer_orders = get_posts(array(
        'numberposts' => -1,
        'meta_key' => '_customer_user',
        'meta_value' => $user->ID,
        'post_type' => wc_get_order_types(),
        'post_status' => array_keys(wc_get_order_statuses()),
        'posts_per_page' => 10,
        'orderby' => 'date',
    ));
    $response['order_num'] = count($customer_orders);
    $order_html = '';
    if ($response['order_num'] > 0) {
        $response['message'] .= wp_kses_post(wpb_randmom_message_handle(unserialize(get_option('qlcd_wp_chatbot_order_found'))));
        $order_html .= '<div class="wp-chatbot-orders-container">
            <div class="wp-chatbot-orders-header">
                <div class="order-id">' . __('ID', 'chatbot') . '</div> 
                <div class="order-date">' . __('Date', 'chatbot') . ' </div>
                <div class="order-items">' . __('Items', 'chatbot') . '</div>
                <div class="order-status">' . __('Status', 'chatbot') . '</div>
            </div>';
        foreach ($customer_orders as $order) {
            //Formatting order summery
            if (isset($_COOKIE['from_app']) && $_COOKIE['from_app'] == 'yes') {
                $thanks_page_id = sanitize_text_field(get_option('wp_chatbot_app_order_thankyou'));
                $thanks_parmanlink = esc_url(get_permalink($thanks_page_id));
                $order_url = '<a href="' . $thanks_parmanlink . '?order_id=' . $order->ID . '" >' . $order->ID . '</a>';
            } else {
                $order_url = '<a href="' . get_url(get_permalink(get_option('wpcommerce_myaccount_page_id')) . '/view-order/' . $order->ID) . '" target="_blank" >' . $order->ID . '</a>';
            }
            $order_html .= '<div class="wp-chatbot-orders-single">
                <div class="order-id"> ' . $order_url . '</div>
                <div class="order-date"> <p>' . gmdate("m/d/Y", strtotime($order->post_date)) . '</p> </div>
                <div class="order-items">';
            $singleOrder = new WC_Order($order->ID);
            $items = $singleOrder->get_items();
            foreach ($items as $item) {
                $order_html .= '<p>' . $item["name"] . ' X ' . $item["qty"] . '</p>';
            }
            $order_html .= '</div>
                <div class="order-status">' . strtoupper(end(explode("-", $order->post_status))) . '</div>
                
                 </div>';
        }
        $order_html .= '</div>';
    } else {
        $response['message'] .= get_option('qlcd_wp_chatbot_sorry') . '! <strong>' . $user_name . '</strong>';
        $order_html .= '<p>' . wp_kses_post(wpb_randmom_message_handle(unserialize(get_option('qlcd_wp_chatbot_order_not_found')))) . '</p>';
    }
    $response['html'] = $order_html;
    return $response;
}
//Recently viewed products
//keeping id in cookies as
function wp_chatbot_track_product_view(){
    if (!is_singular('product')) {
        return;
    }
    global $post;
    wp_chatbot_view_track_product_by_id($post->ID);
}
function wp_chatbot_view_track_product_by_id($post_id){
    if (empty($_COOKIE['wp_chatbot_wpcommerce_recently_viewed']))
        $viewed_products = array();
    else
        $viewed_products = (array)explode('|', $_COOKIE['wp_chatbot_wpcommerce_recently_viewed']);
    if (!in_array($post_id, $viewed_products)) {
        $viewed_products[] = $post_id;
    }
    if (sizeof($viewed_products) > 15) {
        array_shift($viewed_products);
    }
    // Store for session only
	if( function_exists( 'wc_setcookie' ) ){
		wc_setcookie('wp_chatbot_wpcommerce_recently_viewed', implode('|', $viewed_products));
	}
}
// Tracking now happens in qcld-wp-chatbot-front.js: a server-side cookie makes page caches skip product pages.
//recent view product ajax
add_action('wp_ajax_qcld_wb_chatbot_recently_viewed_products', 'qcld_wb_chatbot_recently_viewed_products');
add_action('wp_ajax_nopriv_qcld_wb_chatbot_recently_viewed_products', 'qcld_wb_chatbot_recently_viewed_products');
function qcld_wb_chatbot_recently_viewed_products(){
    // Get wpCommerce Global
    $_pf = new WC_Product_Factory();
    //show post per page.
    $product_per_page = sanitize_text_field(get_option('qlcd_wp_chatbot_ppp') != '' ? get_option('qlcd_wp_chatbot_ppp') : 10);
    $wp_chatbot_product_title = wp_kses_post(wpb_randmom_message_handle(unserialize(get_option('qlcd_wp_chatbot_latest_product_welcome'))));
    // Get recently viewed product cookies data
    $viewed_products = !empty($_COOKIE['wp_chatbot_wpcommerce_recently_viewed']) ? (array)explode('|', $_COOKIE['wp_chatbot_wpcommerce_recently_viewed']) : array();
    $viewed_products = array_filter(array_map('absint', $viewed_products));
    //get featured products if has.
    $featured_products = new WP_Query(array('post_status' => 'publish', 'posts_per_page' => $product_per_page, 'post_type' => 'product', 'tax_query' => array(array('taxonomy' => 'product_visibility', 'field' => 'name', 'terms' => 'featured'))));
    //Getting recently vieew products.
    if (!empty($viewed_products)) {
        $wp_chatbot_product_title = wp_kses_post(wpb_randmom_message_handle(unserialize(get_option('qlcd_wp_chatbot_viewed_product_welcome'))));
        $product_query = new WP_Query(array(
            'posts_per_page' => $product_per_page,
            'no_found_rows' => 1,
            'post_status' => 'publish',
            'post_type' => 'product',
            'post__in' => $viewed_products,
        ));
        //Getting featured products
    } else if ($featured_products->post_count > 0) {
        $wp_chatbot_product_title = wp_kses_post(wpb_randmom_message_handle(unserialize(get_option('qlcd_wp_chatbot_featured_product_welcome'))));
        $product_query = $featured_products;
    } else {
        $wp_chatbot_product_title = wp_kses_post(wpb_randmom_message_handle(unserialize(get_option('qlcd_wp_chatbot_latest_product_welcome'))));
        //Getting recent products
        $product_query = new WP_Query(array('post_status' => 'publish', 'posts_per_page' => $product_per_page, 'post_type' => 'product', 'orderby' => 'date', 'order' => 'DESC'));
    }
    if (get_option('wp_chatbot_custom_agent_path') != "" && get_option('wp_chatbot_agent_image') == "custom-agent.png") {
        $wp_chatbot_custom_icon_path = get_option('wp_chatbot_custom_agent_path');
    } else if (get_option('wp_chatbot_custom_agent_path') != "" && get_option('wp_chatbot_agent_image') != "custom-agent.png") {
        $wp_chatbot_custom_icon_path = QCLD_wpCHATBOT_IMG_URL . get_option('wp_chatbot_agent_image');
    } else {
        $wp_chatbot_custom_icon_path = QCLD_wpCHATBOT_IMG_URL . 'custom-agent.png';
    }
    $html = '<div class="wp-chatbot-agent-profile">
            <div class="wp-chatbot-widget-avatar"><img src="' . esc_url($wp_chatbot_custom_icon_path) . '" alt=""></div>
            <div class="wp-chatbot-widget-agent">' . esc_html(get_option('qlcd_wp_chatbot_agent')) . '</div>
            <div class="wp-chatbot-bubble">' . $wp_chatbot_product_title . '</div>
            </div>';
    if ($product_query->post_count > 0) {
        $html .= '<div class="wp-chatbot-products-area">';
        $html .= '<ul class="wp-chatbot-products">';
        while ($product_query->have_posts()) : $product_query->the_post();
            $product = $_pf->get_product(get_the_ID());
            $html .= '<li class="wp-chatbot-product">';
            $html .= '<a target="_blank" href="' . get_permalink(get_the_ID()) . '" wp-chatbot-pid= "' . get_the_ID() . '" title="' . esc_attr($product->post->post_title ? $product->post->post_title : get_the_ID()) . '">';
            $html .= get_the_post_thumbnail(get_the_ID(), 'shop_catalog') . '
       <div class="wp-chatbot-product-summary">
       <div class="wp-chatbot-product-table">
       <div class="wp-chatbot-product-table-cell">
       <h3 class="wp-chatbot-product-title">' . esc_html($product->post->post_title) . '</h3>
       <div class="price">' . $product->get_price_html() . '</div>';
            $html .= ' </div>
       </div>
       </div></a>
       </li>';
        endwhile;
        wp_reset_query();
        wp_reset_postdata();
        $html .= '</ul></div>';
    } else {
        $html .= '<div class="wp-chatbot-products-area">';
        $html .= '<p style="text-align: center">You have no products !';
        $html .= '</div>';
    }
    wp_send_json($html);
}
//Recently viewed product shortcode
add_shortcode('wpwbot_products', 'qcld_wb_chatbot_recently_viewed_shortcode');
function qcld_wb_chatbot_recently_viewed_shortcode(){
    // Get wpCommerce Global
    $_pf = new WC_Product_Factory();
    $product_per_page = sanitize_text_field(get_option('qlcd_wp_chatbot_ppp') != '' ? get_option('qlcd_wp_chatbot_ppp') : 10);
    $wp_chatbot_product_title = wp_kses_post(wpb_randmom_message_handle(unserialize(get_option('qlcd_wp_chatbot_latest_product_welcome'))));
    // Get recently viewed product cookies data
    $viewed_products = !empty($_COOKIE['wp_chatbot_wpcommerce_recently_viewed']) ? (array)explode('|', $_COOKIE['wp_chatbot_wpcommerce_recently_viewed']) : array();
    $viewed_products = array_filter(array_map('absint', $viewed_products));
    //get featured products if has.
    $featured_products = new WP_Query(array('post_status' => 'publish', 'posts_per_page' => $product_per_page, 'post_type' => 'product', 'tax_query' => array(array('taxonomy' => 'product_visibility', 'field' => 'name', 'terms' => 'featured'))));
    //Getting recently vieew products.
    if (!empty($viewed_products)) {
        $wp_chatbot_product_title = wp_kses_post(wpb_randmom_message_handle(unserialize(get_option('qlcd_wp_chatbot_viewed_product_welcome'))));
        $product_query = new WP_Query(array(
            'posts_per_page' => $product_per_page,
            'no_found_rows' => 1,
            'post_status' => 'publish',
            'post_type' => 'product',
            'post__in' => $viewed_products,
        ));
        //implementing featured products
    } else if ($featured_products->post_count > 0) {
        $wp_chatbot_product_title = wp_kses_post(wpb_randmom_message_handle(unserialize(get_option('qlcd_wp_chatbot_featured_product_welcome'))));
        $product_query = $featured_products;
    } else {
        $wp_chatbot_product_title = wp_kses_post(wpb_randmom_message_handle(unserialize(get_option('qlcd_wp_chatbot_latest_product_welcome'))));
        //Getting recent products
        $product_query = new WP_Query(array('post_status' => 'publish', 'posts_per_page' => $product_per_page, 'post_type' => 'product', 'orderby' => 'date', 'order' => 'DESC'));
    }
    if (get_option('wp_chatbot_custom_agent_path') != "" && get_option('wp_chatbot_agent_image') == "custom-agent.png") {
        $wp_chatbot_custom_icon_path = get_option('wp_chatbot_custom_agent_path');
    } else if (get_option('wp_chatbot_custom_agent_path') != "" && get_option('wp_chatbot_agent_image') != "custom-agent.png") {
        $wp_chatbot_custom_icon_path = QCLD_wpCHATBOT_IMG_URL . get_option('wp_chatbot_agent_image');
    } else {
        $wp_chatbot_custom_icon_path = QCLD_wpCHATBOT_IMG_URL . 'custom-agent.png';
    }
    $html = '<div class="wp-chatbot-agent-profile">
            <div class="wp-chatbot-widget-avatar"><img src="' . esc_url($wp_chatbot_custom_icon_path) . '" alt=""></div>
            <div class="wp-chatbot-widget-agent">' . esc_html(get_option('qlcd_wp_chatbot_agent')) . '</div>
            <div class="wp-chatbot-bubble">' . esc_html($wp_chatbot_product_title) . '</div>
            </div>';
    if ($product_query->post_count > 0) {
        $html .= '<div class="wp-chatbot-products-area">';
        $html .= '<ul class="wp-chatbot-products">';
        while ($product_query->have_posts()) : $product_query->the_post();
            $product = $_pf->get_product(get_the_ID());
            $html .= '<li class="wp-chatbot-product">';
            $html .= '<a target="_blank" href="' . get_permalink(get_the_ID()) . '" wp-chatbot-pid= "' . get_the_ID() . '" title="' . esc_attr($product->post->post_title ? $product->post->post_title : get_the_ID()) . '">';
            $html .= get_the_post_thumbnail(get_the_ID(), 'shop_catalog') . '
       <div class="wp-chatbot-product-summary">
       <div class="wp-chatbot-product-table">
       <div class="wp-chatbot-product-table-cell">
       <h3 class="wp-chatbot-product-title">' . esc_html($product->post->post_title) . '</h3>
       <div class="price">' . $product->get_price_html() . '</div>';
            $html .= ' </div>
       </div>
       </div></a>
       </li>';
        endwhile;
        wp_reset_query();
        wp_reset_postdata();
        $html .= '</ul></div>';
    } else {
        $html .= '<div class="wp-chatbot-products-area">';
        $html .= '<p style="text-align: center">' . __('You have no products', 'chatbot') . ' !';
        $html .= '</div>';
    }
    return $html;
}
//Show cart for wp chatbot
add_action('wp_ajax_qcld_wb_chatbot_show_cart', 'qcld_wb_chatbot_show_cart');
add_action('wp_ajax_nopriv_qcld_wb_chatbot_show_cart', 'qcld_wb_chatbot_show_cart');
function qcld_wb_chatbot_show_cart(){
    global $wpcommerce;
    // $cart_url = $wpcommerce->cart->get_cart_url();
    $cart_url = wc_get_cart_url();
    $checkout_url = wc_get_checkout_url();
    //$checkout_url = $wpcommerce->cart->get_checkout_url();
    $items = $wpcommerce->cart->get_cart();
    $itemCount = $wpcommerce->cart->cart_contents_count;
    $cart_title = wp_kses_post(wpb_randmom_message_handle(unserialize(get_option('qlcd_wp_chatbot_shopping_cart'))));
    $no_cart_item_msg = wp_kses_post(wpb_randmom_message_handle(unserialize(get_option('qlcd_wp_chatbot_no_cart_items'))));
    if (get_option('wp_chatbot_custom_agent_path') != "" && get_option('wp_chatbot_agent_image') == "custom-agent.png") {
        $wp_chatbot_custom_icon_path = get_option('wp_chatbot_custom_agent_path');
    } else if (get_option('wp_chatbot_custom_agent_path') != "" && get_option('wp_chatbot_agent_image') != "custom-agent.png") {
        $wp_chatbot_custom_icon_path = QCLD_wpCHATBOT_IMG_URL . get_option('wp_chatbot_agent_image');
    } else {
        $wp_chatbot_custom_icon_path = QCLD_wpCHATBOT_IMG_URL . 'custom-agent.png';
    }
    $html = '<div class="wp-chatbot-agent-profile">
            <div class="wp-chatbot-widget-avatar"><img src="' . esc_url($wp_chatbot_custom_icon_path) . '" alt=""></div>
            <div class="wp-chatbot-widget-agent">' . esc_html(get_option('qlcd_wp_chatbot_agent')) . '</div>
            <div class="wp-chatbot-bubble">' . wp_kses_post(wpb_randmom_message_handle(unserialize(get_option('qlcd_wp_chatbot_cart_welcome')))) . '</div>
            </div>';
    if ($itemCount >= 1) {
        $html .= '<div class ="wp-chatbot-cart-container">';
        $html .= '<div class="wp-chatbot-cart-header"><div class="qcld-wp-chatbot-cell">' . wp_kses_post(wpb_randmom_message_handle(unserialize(get_option('qlcd_wp_chatbot_cart_title')))) . '</div><div class="qcld-wp-chatbot-cell">' . wp_kses_post(wpb_randmom_message_handle(unserialize(get_option('qlcd_wp_chatbot_cart_quantity')))) . '</div><div class="qcld-wp-chatbot-cell">' . wp_kses_post(wpb_randmom_message_handle(unserialize(get_option('qlcd_wp_chatbot_cart_price')))) . '</div><div class="qcld-wp-chatbot-cell"></div></div>';
        $html .= '<div class ="wp-chatbot-cart-body">';
        foreach ($items as $item => $values) {
            $cart_item= apply_filters('wpcommerce_cart_item_product', $values['data'], $values, $item);
            //product image
            $getProductDetail = wc_get_product($values['product_id']);
            $price = get_post_meta($values['product_id'], '_price', true);
            $html .= '<div class="wp-chatbot-cart-single">
                        <div class="qcld-wp-chatbot-cell"> <h3 class="wp-chatbot-title">' . $cart_item->get_title() . '</h3></div>';
            $html .= '<div class="qcld-wp-chatbot-cell">';
            $html .= '<input class="qcld-wp-chatbot-cart-item-qnty" data-cart-item="' . $item . '" type="number" min="1" value="' . $values['quantity'] . '"></div>';
            $html .= '<div class="qcld-wp-chatbot-cell"><span class="wp-chatbot-cart-price">' . apply_filters('wpcommerce_cart_item_price', WC()->cart->get_product_price($cart_item), $values, $item) . '</span> </div>';
            $html .= '<div class="qcld-wp-chatbot-cell"><span data-cart-item="' . $item . '" class="wp-chatbot-remove-cart-item">X</span></div> </div>';
        }
        $html .= ' </div>';//End of cart body
        $html .= '<div class="wp-chatbot-cart-single">
                            <div class="qcld-wp-chatbot-cell"></div>
                            <div class="qcld-wp-chatbot-cell"><strong>Total</strong></div>
                            <div class="qcld-wp-chatbot-cell"><strong>' . $wpcommerce->cart->get_cart_total() . '</strong></div>
                        </div>';
        $html .= '<div class="wp-chatbot-cart-footer"><div class="qcld-wp-chatbot-cart-page"><a class="wp-chatbot-cart-link" href="' . $cart_url . '" target="_blank"  >' . wp_kses_post(wpb_randmom_message_handle(unserialize(get_option('qlcd_wp_chatbot_cart_link')))) . '</a></div><div class="qcld-wp-chatbot-checkout"><a class="wp-chatbot-checkout-link" href="' . $checkout_url . '" target="_blank"  >' . wp_kses_post(wpb_randmom_message_handle(unserialize(get_option('qlcd_wp_chatbot_checkout_link')))) . '</a></div></div>';
        $html .= ' </div>';
    } else {
        $html .= '<div class="wp-chatbot-cart-container">';
        $html .= '<div><p style="text-align:center">' . $no_cart_item_msg . '</p></div>';
        $html .= '</div>';
    }
    $response = array('html' => $html, 'items' => $itemCount);
    wp_send_json($response);
}
//cart onley for wp chatbot
//Cart show Shortcode
add_shortcode('wpwbot_cart', 'qcld_wb_chatbot_cart_shortcode');
function qcld_wb_chatbot_cart_shortcode(){
    global $wpcommerce;
    $items = $wpcommerce->cart->get_cart();
    $itemCount = $wpcommerce->cart->cart_contents_count;
    $cart_title = wp_kses_post(wpb_randmom_message_handle(unserialize(get_option('qlcd_wp_chatbot_shopping_cart'))));
    $no_cart_item_msg = wp_kses_post(wpb_randmom_message_handle(unserialize(get_option('qlcd_wp_chatbot_no_cart_items'))));
    if (get_option('wp_chatbot_custom_agent_path') != "" && get_option('wp_chatbot_agent_image') == "custom-agent.png") {
        $wp_chatbot_custom_icon_path = get_option('wp_chatbot_custom_agent_path');
    } else if (get_option('wp_chatbot_custom_agent_path') != "" && get_option('wp_chatbot_agent_image') != "custom-agent.png") {
        $wp_chatbot_custom_icon_path = QCLD_wpCHATBOT_IMG_URL . get_option('wp_chatbot_agent_image');
    } else {
        $wp_chatbot_custom_icon_path = QCLD_wpCHATBOT_IMG_URL . 'custom-agent.png';
    }
    $html = '<div class="wp-chatbot-agent-profile">
            <div class="wp-chatbot-widget-avatar"><img src="' . esc_url($wp_chatbot_custom_icon_path) . '" alt=""></div>
            <div class="wp-chatbot-widget-agent">' . esc_html(get_option('qlcd_wp_chatbot_agent')) . '</div>
            <div class="wp-chatbot-bubble">' . wp_kses_post(wpb_randmom_message_handle(unserialize(get_option('qlcd_wp_chatbot_cart_welcome')))) . '</div>
            </div>';
    if ($itemCount >= 1) {
        $html .= '<div class ="wp-chatbot-cart-container">';
        $html .= '<div class="wp-chatbot-cart-header"><div class="qcld-wp-chatbot-cell">' . wp_kses_post(wpb_randmom_message_handle(unserialize(get_option('qlcd_wp_chatbot_cart_title')))) . '</div><div class="qcld-wp-chatbot-cell">' . wp_kses_post(wpb_randmom_message_handle(unserialize(get_option('qlcd_wp_chatbot_cart_quantity')))) . '</div><div class="qcld-wp-chatbot-cell">' . wp_kses_post(wpb_randmom_message_handle(unserialize(get_option('qlcd_wp_chatbot_cart_price')))) . '</div> <div class="qcld-wp-chatbot-cell"></div> </div>';
        $html .= '<div class ="wp-chatbot-cart-body">';
        foreach ($items as $item => $values) {
            $cart_item = apply_filters('wpcommerce_cart_item_product', $values['data'], $values, $item);
            //product image
            $getProductDetail = wc_get_product($values['product_id']);
            $price = get_post_meta($values['product_id'], '_price', true);
            $html .= '<div class="wp-chatbot-cart-single">
                        <div class="qcld-wp-chatbot-cell"> <h3 class="wp-chatbot-title">' . $cart_item->get_title() . '</h3></div>';
            $html .= '<div class="qcld-wp-chatbot-cell">';
            $html .= '<input class="qcld-wp-chatbot-cart-item-qnty" data-cart-item="' . $item . '" type="number" min="1" value="' . $values['quantity'] . '"></div>';
            $html .= '<div class="qcld-wp-chatbot-cell"><span class="wp-chatbot-cart-price">' . apply_filters('wpcommerce_cart_item_price', WC()->cart->get_product_price($cart_item), $values, $item) . '</span> </div>';
            $html .= '<div class="qcld-wp-chatbot-cell"><span data-cart-item="' . $item . '" class="wp-chatbot-remove-cart-item">X</span></div> </div>';
        }
        $html .= ' </div>';//End of cart body
        $html .= '<div class="wp-chatbot-cart-single">
                            <div class="qcld-wp-chatbot-cell"></div>
                            <div class="qcld-wp-chatbot-cell"><strong>Total</strong></div>
                            <div class="qcld-wp-chatbot-cell"><strong>' . $wpcommerce->cart->get_cart_total() . '</strong></div>
                        </div>';
        $html .= '<div class="wp-chatbot-cart-footer"><div class="qcld-wp-chatbot-cart-page"><a href="' . site_url() . '/cart" target="_blank" >' . wp_kses_post(wpb_randmom_message_handle(unserialize(get_option('qlcd_wp_chatbot_cart_link')))) . '</a></div><div class="qcld-wp-chatbot-checkout"><a href="' . site_url() . '/checkout" target="_blank">' . wp_kses_post(wpb_randmom_message_handle(unserialize(get_option('qlcd_wp_chatbot_checkout_link')))) . '</a></div></div>';
        $html .= ' </div>';
    } else {
        $html .= '<div class="wp-chatbot-cart-container">';
        $html .= '<div><p style="text-align:center">' . $no_cart_item_msg . '</p></div>';
        $html .= '</div>';
    }
    // $response=array('html'=>$html,'items'=>$itemCount);
    return $html;
}
//Updating the cart items.
add_action('wp_ajax_qcld_wb_chatbot_update_cart_item_number', 'qcld_wb_chatbot_update_cart_item_number');
add_action('wp_ajax_nopriv_qcld_wb_chatbot_update_cart_item_number', 'qcld_wb_chatbot_update_cart_item_number');
function qcld_wb_chatbot_update_cart_item_number(){
    check_ajax_referer( 'wp_chatbot', 'nonce' );
    //getting cart items n
    $cart_item_key = sanitize_text_field(wp_unslash($_POST['cart_item_key']));
    $qnty = sanitize_text_field(wp_unslash($_POST['qnty']));
    global $wpcommerce;
    $result = $wpcommerce->cart->set_quantity($cart_item_key, $qnty);
    wp_send_json($result);
}
//Show item after removing from cart page.
add_action('wp_ajax_qcld_wb_chatbot_cart_item_remove', 'qcld_wb_chatbot_cart_item_remove');
add_action('wp_ajax_nopriv_qcld_wb_chatbot_cart_item_remove', 'qcld_wb_chatbot_cart_item_remove');
function qcld_wb_chatbot_cart_item_remove(){
    check_ajax_referer( 'wp_chatbot', 'nonce' );
    //getting cart items n
    $cart_item_key = sanitize_text_field(wp_unslash($_POST['cart_item']));
    global $wpcommerce;
    $result = $wpcommerce->cart->remove_cart_item($cart_item_key);
    wp_send_json($result);
}
//Show cart page by shortcode.
add_action('wp_ajax_qcld_wb_chatbot_cart_page', 'qcld_wb_chatbot_cart_page');
add_action('wp_ajax_nopriv_qcld_wb_chatbot_cart_page', 'qcld_wb_chatbot_cart_page');
function qcld_wb_chatbot_cart_page(){
    global $wpcommerce;
    $itemCount = $wpcommerce->cart->cart_contents_count;
    $html = "";
    if ($itemCount < 0) {
        $html .= wp_kses_post(wpb_randmom_message_handle(unserialize(get_option('qlcd_wp_chatbot_no_cart_items'))));
    } else {
        $html .= do_shortcode("[wpcommerce_cart]");
    }
    wp_send_json($html);
}
//Show checkout page by shortcode.
add_action('wp_ajax_qcld_wb_chatbot_checkout_page', 'qcld_wb_chatbot_checkout_page');
add_action('wp_ajax_nopriv_qcld_wb_chatbot_checkout_page', 'qcld_wb_chatbot_checkout_page');
function qcld_wb_chatbot_checkout_page(){
    global $wpcommerce;
    $itemCount = $wpcommerce->cart->cart_contents_count;
    $html = "";
    if ($itemCount < 0) {
        $status = 'no';
        $html .= wp_kses_post(wpb_randmom_message_handle(unserialize(get_option('qlcd_wp_chatbot_no_cart_items'))));
    } else {
        $status = 'yes';
        $checkout_page_id = get_option('wp_chatbot_app_checkout');
        $checkout_parmanlink = esc_url(get_permalink($checkout_page_id));
        $html .= $checkout_parmanlink;
    }
    $response = array('status' => $status, 'html' => $html);
    wp_send_json($response);
}
//_dynamic_intent
function qcld_dynamic_intent(){
    global $wpdb;
    $intents = array();
 
    $ai_df = get_option('enable_wp_chatbot_dailogflow');
    $custom_intent_labels = maybe_unserialize( get_option('qlcd_wp_custon_intent_label'));
    if($ai_df==1 && isset($custom_intent_labels[0]) && trim($custom_intent_labels[0])!=''):

        foreach($custom_intent_labels as $custom_intent_label):
            $intents[] = $custom_intent_label;
        endforeach;
        
    endif;

    

    if(class_exists('Qcformbuilder_Forms_Admin')){
        

        $results = $wpdb->get_results($wpdb->prepare("SELECT * FROM ". $wpdb->prefix."wfb_forms WHERE type= %s",'primary')); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

        if(!empty($results)){

            foreach($results as $result){
                $form = maybe_unserialize($result->config);
                $intents[] = $form['name'];
            }

        }
    }
    
    if(!empty($intents)){
        return implode(", ", $intents);
    }else{
        return '';
    }

}

//User login on Checkout page.
// add_action('wp_ajax_qcld_wb_chatbot_checkout_user_login', 'qcld_wb_chatbot_checkout_user_login');
// add_action('wp_ajax_nopriv_qcld_wb_chatbot_checkout_user_login', 'qcld_wb_chatbot_checkout_user_login');
// function qcld_wb_chatbot_checkout_user_login(){
//     // Nonce is checked, get the POST data and sign user on
//     $info = array();
//     //$info['nonce'] = $_POST['nonce_val'];
//     $info['user_login'] = trim(sanitize_text_field(wp_unslash($_POST['user_name'])));
//     $info['user_password'] = trim(sanitize_text_field(wp_unslash($_POST['user_pass'])));
//     $info['remember'] = true;
//     $user_signon = wp_signon($info, false);
//     // $response=$info;
//     $response = array();
//     if (is_wp_error($user_signon)) {
//         // $response['status'] = "fail";
//         $response = "no";
//     } else {
//         $response = "yes";
//     }
//     wp_send_json($response);
// }
// Load template for App Order Thank You page url
function wp_chatbot_load_app_template($template){
    if (is_page('wpwbot-mobile-app')) {
        return dirname(__FILE__) . '/templates/app-templates/app.php';
    }
    return $template;
}
add_filter('template_include', 'wp_chatbot_load_app_template', 99);
// Load template for App Order Thank You page url
function wp_chatbot_load_app_order_thankyou_template($template){
    if (is_page('wpwbot-app-order-thankyou')) {
        return dirname(__FILE__) . '/templates/app-templates/app-order-thankyou.php';
    }
    return $template;
}
add_filter('template_include', 'wp_chatbot_load_app_order_thankyou_template', 99);
// Load template for App checkout
function wp_chatbot_load_app_checkout_template($template){
    if (is_page('wpwbot-app-checkout')) {
        return dirname(__FILE__) . '/templates/app-templates/app-checkout.php';
    }
    return $template;
}
add_filter('template_include', 'wp_chatbot_load_app_checkout_template', 99);
// Create embed page when plugin install or activate
//register_activation_hook(__FILE__, 'wp_chatbot_create_app_order_thankyou_page');
// Create the app pages only when the setting is saved, not on every request.
add_action('add_option_wp_chatbot_app_pages', 'wp_chatbot_create_app_checkout_thankyou_page');
add_action('update_option_wp_chatbot_app_pages', 'wp_chatbot_create_app_checkout_thankyou_page');
function wp_chatbot_create_app_checkout_thankyou_page(){
    if (get_option('wp_chatbot_app_pages') == 1) {
        //Mobile App page create
        $existing_app = new WP_Query( array( 'post_type' => 'page', 'name' => 'wpwbot-mobile-app', 'post_status' => 'publish', 'posts_per_page' => 1 ) );
        if ( ! $existing_app->have_posts() ) {
            //post status and options
            $app_page = array(
                'comment_status' => 'closed',
                'ping_status' => 'closed',
                'post_author' => get_current_user_id(),
                'post_date' => gmdate('Y-m-d H:i:s'),
                'post_status' => 'publish',
                'post_title' => 'wpwBot Mobile App',
                'post_name' => 'wpwbot-mobile-app',
                'post_type' => 'page',
            );
            //insert page and save the id
            $wpwbot_app = wp_insert_post($app_page, false);
            //save the id in the database
            update_option('wp_chatbot_app_checkout', $wpwbot_app);
        }
        //App checkout page create
        $existing_checkout = new WP_Query( array( 'post_type' => 'page', 'name' => 'wpwbot-app-checkout', 'post_status' => 'publish', 'posts_per_page' => 1 ) );
        if ( ! $existing_checkout->have_posts() ) {
            //post status and options
            $checkout_page = array(
                'comment_status' => 'closed',
                'ping_status' => 'closed',
                'post_author' => get_current_user_id(),
                'post_date' => gmdate('Y-m-d H:i:s'),
                'post_status' => 'publish',
                'post_title' => 'wpwBot App Checkout',
                'post_name' => 'wpwbot-app-checkout',
                'post_type' => 'page',
            );
            //insert page and save the id
            $app_checkout = wp_insert_post($checkout_page, false);
            //save the id in the database
            update_option('wp_chatbot_app_checkout', $app_checkout);
        }
        //App Order thank you page create
        $existing_thankyou = new WP_Query( array( 'post_type' => 'page', 'name' => 'wpwbot-app-order-thankyou', 'post_status' => 'publish', 'posts_per_page' => 1 ) );
        if ( ! $existing_thankyou->have_posts() ) {
            //post status and options
            $thankyou_page = array(
                'comment_status' => 'closed',
                'ping_status' => 'closed',
                'post_author' => get_current_user_id(),
                'post_date' => gmdate('Y-m-d H:i:s'),
                'post_status' => 'publish',
                'post_title' => 'wpwBot App Order Thank You',
                'post_name' => 'wpwbot-app-order-thankyou',
                'post_type' => 'page',
            );
            //insert page and save the id
            $app_order_thankyou = wp_insert_post($thankyou_page, false);
            //save the id in the database
            update_option('wp_chatbot_app_order_thankyou', $app_order_thankyou);
        }
    }
}
//Keep tracking from App by cookies
add_action('init', 'wp_chatbot_track_from_app');
function wp_chatbot_track_from_app(){
    if ( isset( $_GET['from'] ) && sanitize_text_field( wp_unslash( $_GET['from'] ) ) === 'app' ) { // phpcs:ignore WordPress.Security.NonceVerification
        if (!isset($_COOKIE['from_app'])) {
            setcookie('from_app', 'yes', (time() + 3600), '/');
        }
    }
}
/***
 * Override order Thank page for mobile app
 */
add_action('wpcommerce_thankyou', 'qcld_wb_chatbot__redirect_after_purchase', 10, 1);
function qcld_wb_chatbot__redirect_after_purchase($order_get_id){
    if (isset($_COOKIE['from_app']) && $_COOKIE['from_app'] == 'yes') {
        global $wp;
        if (is_checkout() && !empty($wp->query_vars['order-received'])) {
            $thanks_page_id = get_option('wp_chatbot_app_order_thankyou');
            $thanks_parmanlink = esc_url(get_permalink($thanks_page_id));
            wp_safe_redirect($thanks_parmanlink . '?order_id=' . $order_get_id);
            exit;
        }
    } else {
        remove_action('wpcommerce_thankyou', 'qcld_wb_chatbot__redirect_after_purchase');
        do_action('wpcommerce_thankyou', $order_get_id);
    }
}

function qcld_choose_random($array){
    if (is_array($array) && !empty($array)) {
        return $array[array_rand($array)];
    }
    return $array;
}

//User session count
add_action('wp_ajax_qcld_wb_chatbot_session_count', 'qcld_wb_chatbot_session_count');
add_action('wp_ajax_nopriv_qcld_wb_chatbot_session_count', 'qcld_wb_chatbot_session_count');
function qcld_wb_chatbot_session_count(){
    // Nonce is checked, get the POST data and sign user on
    check_ajax_referer( 'wp_chatbot', 'nonce' );
    global $wpdb;
    $wpdb->show_errors = true;
    $tableuser    = $wpdb->prefix.'wpbot_sessions';
    $response = array();
    

    $session_exists = $wpdb->get_row($wpdb->prepare("select * from {$tableuser} where 1 and id = %d",1)); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter

		if(empty($session_exists)){
			$wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
				$tableuser,
				array(
					'session'   => 1,
				)
			);
		}else{

			$session_id = $session_exists->id;

			$wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
				$tableuser,
				array(
					'session'=>($session_exists->session+1),
				),
				array('id'=>$session_id),
				array(
					'%d',
				),
				array('%d')
			);

		}
	
    wp_send_json($response);
}

/* WPBot Chat History Addon check */
function qcld_wpbot_is_active_chat_history(){

    if(function_exists('qcwp_chat_session_menu_fnc') || function_exists('qcwp_chat_session_menu_fnc_free')  || function_exists( 'qcpdcs_chat_session_menu_fnc' ) ){
        return 1;
    }else{
        return 0;
    }

}

/**
 * Safely sanitize chatbot conversation input.
 *
 * SECURITY FIX (CVE WPBot Stored XSS ≤ 8.6.9):
 * The previous order was: wp_kses() → html_entity_decode() → htmlspecialchars().
 * An attacker could submit entity-encoded payloads (&lt;img onerror=...&gt;) that
 * bypassed wp_kses (which saw inert text), were then decoded back into live markup
 * by html_entity_decode(), and survived into storage and the admin UI.
 *
 * Correct order: html_entity_decode() FIRST → wp_kses() → htmlspecialchars().
 * wp_kses() now sees the real decoded markup and strips forbidden tags/attributes.
 *
 * @param  string $data Raw conversation string (already wp_unslash'd by caller).
 * @return string Sanitized, entity-encoded string safe for DB storage.
 */
function qcld_wpbot_input_validation( $data ) {
	// 1. Decode any entity-encoded HTML so wp_kses sees the real markup.
	$data = html_entity_decode( $data, ENT_QUOTES | ENT_HTML5, 'UTF-8' );
	$data = trim( $data );
	$data = stripslashes( $data );
	// 2. Sanitize with a strict allowlist — NOW operating on decoded markup.
	$data = wp_kses( $data, wpbot_get_safe_conversation_tags() );
	// 3. Re-encode for safe DB storage; admin.js decodes for rendering.
	$data = htmlspecialchars( $data, ENT_QUOTES | ENT_HTML5, 'UTF-8' );
	return $data;
}

/**
 * Returns the strict HTML allowlist for chatbot conversation content.
 *
 * Critically: no event-handler attributes (onerror, onclick, onload, etc.) are
 * allowed — wp_kses strips any attribute not explicitly listed here.
 * 'img' is intentionally omitted; bot responses that include images should use
 * safe URLs only and can be re-added with only 'src', 'alt', 'class' if needed.
 *
 * @return array<string, array<string, bool>>
 */
function wpbot_get_safe_conversation_tags() {
	return array(
		'ul'     => array( 'class' => true ),
		'ol'     => array( 'class' => true ),
		'li'     => array( 'class' => true, 'id' => true ),
		'div'    => array( 'class' => true, 'id' => true ),
		'span'   => array( 'class' => true, 'id' => true ),
		'p'      => array( 'class' => true ),
		'br'     => array(),
		'strong' => array(),
		'em'     => array(),
		'b'      => array(),
		'i'      => array(),
		'a'      => array(
			'href'   => true,
			'target' => true,
			'rel'    => true,
			'class'  => true,
		),
		// 'img' intentionally excluded — prevents onerror/onload injection.
		// Add back with only 'src','alt','class' if bot image responses are needed.
	);
}
add_action('wp_ajax_qcld_small_talk_import', 'qcld_small_talk_import');
function qcld_small_talk_import(){
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_die();
    }
    global $wpdb;

    $table = $wpdb->prefix.'wpbot_response';
    
    $csvFile = file(QCLD_wpCHATBOT_PLUGIN_DIR_PATH . 'small_talk.csv');

    foreach ($csvFile as $line) {
        $line = str_getcsv($line, ',', '"');
        $wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
            $table, array(
            'query' => $line[0],
            'keyword' => $line[1],
            'response' => $line[2],
            'category'=> $line[3],
            'intent'=> '',
            //'lang'=> 'en_US',
        ));
    }

    $table2 = $wpdb->prefix.'wpbot_response_category';

    $wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
        $table2, array(
        'name' => 'smalltalk',
    ));

   update_option( 'qcld_small_talk_imported', 'yes' );
    
}
function sanitize_array( &$array ) {
    if (!empty($array)) {
        foreach ($array as &$value) {	
            if( !is_array($value) )	
                // sanitize if value is not an array
                $value = sanitize_text_field( esc_html($value) );
            else
                // go inside this function again
                sanitize_array(esc_html($value));
        }
    }
	return $array;
}
if ( ! function_exists( 'qcld_wpbot_meta_tags' ) ) {
    function qcld_wpbot_meta_tags() {
        echo '<!-- "This site uses ChatBot for WordPress - WPBot from https://www.wpbot.pro/" -->';
    }
}
add_action('wp_footer', 'qcld_wpbot_meta_tags', 100);

if ( ! function_exists( 'qcld_change_language_from_center' ) ) {
	add_action( 'wp_ajax_qcld_change_language_from_center', 'qcld_change_language_from_center' );
	add_action( 'wp_ajax_nopriv_qcld_change_language_from_center', 'qcld_change_language_from_center' );
	function qcld_change_language_from_center() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'Unauthorized.' ) );
		}
		$nonce = isset( $_POST['nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['nonce'] ) ) : '';
		if ( ! wp_verify_nonce( $nonce, 'wp_chatbot' ) ) {
			wp_send_json_error( array( 'message' => 'Invalid nonce.' ) );
		}
		$plugin_path = plugin_dir_path( __FILE__ );
		include $plugin_path . 'includes/admin/settings-fields.php';
		$json_file_path = $plugin_path . 'includes/language-center.json';
		
   		$json_string = file_get_contents( $json_file_path );
		   if ( isset( $_POST['language'] ) ) {
			    $language = sanitize_text_field( wp_unslash( $_POST['language'] ) );
			    $language_array= json_decode($json_string)->$language;
                update_option( 'wp_chatbot_language_center_language', $language );
			    wp_send_json_success( array( 'message' => true, 'data' => $language_array, 'language' => $language ) );
		    } else {
			    wp_send_json_error( array( 'message' => 'Language not specified.' ) );

		    }
	}
}

// AI Actions Chat Preview

if ( ! function_exists( 'qcld_wpbot_is_public_post' ) ) {
	/**
	 * Security: only published, publicly viewable posts without a password may be used as
	 * page context for AI answers. The post id comes from the visitor-controlled Referer.
	 *
	 * @param int $post_id Post ID.
	 * @return bool
	 */
	function qcld_wpbot_is_public_post( $post_id ) {
		$post = get_post( $post_id );
		if ( ! $post || '' !== (string) $post->post_password ) {
			return false;
		}
		if ( function_exists( 'is_post_publicly_viewable' ) ) {
			return is_post_publicly_viewable( $post );
		}
		return 'publish' === get_post_status( $post );
	}
}

if ( ! function_exists( 'qcld_wpbot_csv_safe_cell' ) ) {
	/**
	 * Security: neutralize spreadsheet formulas in a CSV cell. A value that starts with =, +, -, @,
	 * a tab or a carriage return gets a leading single quote, so Excel, LibreOffice and Google Sheets
	 * show it as text instead of evaluating it (CSV formula injection, e.g. =WEBSERVICE(...) in a lead name).
	 *
	 * @param mixed $value Cell value.
	 * @return string
	 */
	function qcld_wpbot_csv_safe_cell( $value ) {
		if ( is_array( $value ) || is_object( $value ) ) {
			$value = implode( ', ', array_map( 'strval', array_filter( (array) $value, 'is_scalar' ) ) );
		}
		$value = (string) $value;
		if ( '' !== $value && false !== strpos( "=+-@\t\r", $value[0] ) ) {
			$value = "'" . $value;
		}
		return $value;
	}
}

if ( ! function_exists( 'qcld_wpbot_csv_safe_row' ) ) {
	/**
	 * Apply qcld_wpbot_csv_safe_cell() to every cell of a CSV row, keeping its keys.
	 *
	 * @param array $row Row.
	 * @return array
	 */
	function qcld_wpbot_csv_safe_row( $row ) {
		return array_map( 'qcld_wpbot_csv_safe_cell', (array) $row );
	}
}

if ( ! function_exists( 'qcld_wpbot_clean_api_key' ) ) {
	/**
	 * Clean an AI provider API key pasted by the site owner. Keys are plain ASCII, so anything else (non-breaking
	 * or zero-width spaces copied from a web page, line breaks) is removed, and a pasted "Bearer " prefix is dropped.
	 * A key damaged this way makes the provider reject the request as unauthenticated.
	 *
	 * @param mixed $key Raw key.
	 * @return string
	 */
	function qcld_wpbot_clean_api_key( $key ) {
		$key = preg_replace( '/[^\x21-\x7E]/', '', (string) $key );
		return (string) preg_replace( '/^Bearer(?=sk-)/i', '', $key );
	}
}

if ( ! function_exists( 'qcld_wpbot_gemini_reply_text' ) ) {
	/**
	 * Join the answer text of a Gemini generateContent response. Newer Gemini models can split one answer over
	 * several parts (and add parts that only carry a thought signature), so reading only the first part cuts the
	 * reply off mid-sentence. Thought parts are skipped.
	 *
	 * @param mixed $response Decoded response (array or object).
	 * @return string The reply text, or '' when there is none.
	 */
	function qcld_wpbot_gemini_reply_text( $response ) {
		$response = json_decode( wp_json_encode( $response ), true );
		$parts    = isset( $response['candidates'][0]['content']['parts'] ) ? $response['candidates'][0]['content']['parts'] : array();
		$text     = '';
		if ( is_array( $parts ) ) {
			foreach ( $parts as $part ) {
				if ( is_array( $part ) && isset( $part['text'] ) && is_string( $part['text'] ) && empty( $part['thought'] ) ) {
					$text .= $part['text'];
				}
			}
		}
		return $text;
	}
}

if ( ! function_exists( 'qcld_wpbot_free_move_permissions_menu' ) ) {
	/**
	 * Show Permissions just below Automator (above Upgrade to Pro) in the WPBot menu. The Permissions page is added by the
	 * synced WPBot Core module (admin_menu priority 60), so it is moved here, after every item is in.
	 */
	function qcld_wpbot_free_move_permissions_menu() {
		global $submenu;
		$parent = 'wpbot_openAi';
		if ( empty( $submenu[ $parent ] ) || ! is_array( $submenu[ $parent ] ) ) {
			return;
		}
		$items       = array_values( $submenu[ $parent ] );
		$permissions = null;
		foreach ( $items as $index => $item ) {
			if ( isset( $item[2] ) && 'wpbot-permissions' === $item[2] ) {
				$permissions = $index;
				break;
			}
		}
		if ( null === $permissions ) {
			return;
		}
		$entry = $items[ $permissions ];
		array_splice( $items, $permissions, 1 );
		foreach ( $items as $index => $item ) {
			if ( isset( $item[2] ) && 'wpbot-automator' === $item[2] ) {
				array_splice( $items, $index + 1, 0, array( $entry ) );
				$submenu[ $parent ] = $items; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
				return;
			}
		}
	}
	add_action( 'admin_menu', 'qcld_wpbot_free_move_permissions_menu', 9999 );
}

if ( ! function_exists( 'qcld_wpbot_free_pro_lists' ) ) {
	/**
	 * Print a WPBot Pro feature's Benefits and Features lists (teasers in WPBot Free).
	 *
	 * @param string $id   Feature id (QCLD_WPBot_Free_Pro_Catalogue::pro_features()).
	 * @param array  $args heading, compact, button.
	 * @return void
	 */
	function qcld_wpbot_free_pro_lists( $id, array $args = array() ) {
		if ( class_exists( 'QCLD_WPBot_Free_Pro_Catalogue' ) ) {
			QCLD_WPBot_Free_Pro_Catalogue::print_feature( $id, $args );
		}
	}
}
