<?php
if (defined('ABSPATH') === false) {
    exit;
}

?>
<?php
add_action( 'wp_ajax_qcld_recommend_support_function_ajax', 'qcld_recommend_support_function_ajax' );

if( !function_exists('qcld_recommend_support_function_ajax') ){

    function qcld_recommend_support_function_ajax(){

            check_ajax_referer( 'qc-clr', 'security');
           
            require_once ABSPATH . 'wp-admin/includes/plugin-install.php';
            remove_all_filters('plugins_api');

            /* Conversational Forms Plugins */
            $argus = [

                [
                    'slug'      => 'knowledgebase-helpdesk',
                    'fields'    => [
                        'short_description' => true,
                        'icons'             => true,
                        'reviews'           => true, // excludes all reviews
                    ],
        			'live_preview'   => 'https://dev.quantumcloud.com/knowledgebase/',
        			'update_to_pro'  => 'https://www.quantumcloud.com/products/knowledgebase-helpdesk/'
                ], 
                [
                    'slug'      => 'woowbot-woocommerce-chatbot',
                    'fields'    => [
                        'short_description' => true,
                        'icons'             => true,
                        'reviews'           => true, // excludes all reviews
                    ],
        			'live_preview'   => 'https://woowbot.pro/',
        			'update_to_pro'  => 'https://woowbot.pro/pricing/'
                ], 
                [
                    'slug'      => 'chatbot',
                    'fields'    => [
                        'short_description' => true,
                        'icons'             => true,
                        'reviews'           => true, // excludes all reviews
                    ],
        			'live_preview'   => 'https://www.wpbot.pro/',
        			'update_to_pro'  => 'https://www.wpbot.pro/pricing/'
                ], 
                [
                    'slug'      => 'phone-directory',
                    'fields'    => [
                        'short_description' => true,
                        'icons'             => true,
                        'reviews'           => false, // excludes all reviews
                    ],
        			'live_preview'   => 'https://dev.quantumcloud.com/simple-business-directory/',
        			'update_to_pro'  => 'https://www.quantumcloud.com/products/simple-business-directory/'
                ], 
                [
                    'slug'      => 'slider-hero',
                    'fields'    => [
                        'short_description' => true,
                        'icons'             => true,
                        'reviews'           => false, // excludes all reviews
                    ],
        			'live_preview'   => 'https://dev2.testversions.com/sliderhero/',
        			'update_to_pro'  => 'https://www.quantumcloud.com/products/slider-hero/'
                ],
                [
                    'slug'      => 'simple-link-directory',
                    'fields'    => [
                        'short_description' => true,
                        'icons'             => true,
                        'reviews'           => false, // excludes all reviews
                    ],
        			'live_preview'   => 'https://dev.quantumcloud.com/sld/',
        			'update_to_pro'  => 'https://www.quantumcloud.com/products/simple-link-directory/'
                ], 
                [
                    'slug'      => 'infographic-and-list-builder-ilist',
                    'fields'    => [
                        'short_description' => true,
                        'icons'             => true,
                        'reviews'           => false, // excludes all reviews
                    ],
        			'live_preview'   => 'https://dev.quantumcloud.com/iList/',
        			'update_to_pro'  => 'https://www.quantumcloud.com/products/infographic-maker-ilist/'
                ],
                [
                    'slug'      => 'ichart',
                    'fields'    => [
                        'short_description' => true,
                        'icons'             => true,
                        'reviews'           => false, // excludes all reviews
                    ],
        			'live_preview'   => 'https://dev.quantumcloud.com/ichart/',
        			'update_to_pro'  => 'https://www.quantumcloud.com/products/ichart/'
                ],
                [
                    'slug'      => 'comment-link-remove',
                    'fields'    => [
                        'short_description' => true,
                        'icons'             => true,
                        'reviews'           => false, // excludes all reviews
                    ],
        			'live_preview'   => 'https://dev.quantumcloud.com/comment-tools/',
        			'update_to_pro'  => 'https://www.quantumcloud.com/products/comment-tools/'
                ],
                [
                    'slug'      => 'shop-assistant-for-woocommerce-jarvis',
                    'fields'    => [
                        'short_description' => true,
                        'icons'             => true,
                        'reviews'           => false, // excludes all reviews
                    ],
        			'live_preview'   => 'https://dev.quantumcloud.com/JARVIS-woo/',
        			'update_to_pro'  => 'https://www.quantumcloud.com/products/woocommerce-shop-assistant-jarvis/'
                ], 
                [
                    'slug'      => 'express-shop',
                    'fields'    => [
                        'short_description' => true,
                        'icons'             => true,
                        'reviews'           => false, // excludes all reviews
                    ],
        			'live_preview'   => 'https://dev.quantumcloud.com/express-shop-pro/',
        			'update_to_pro'  => 'https://www.quantumcloud.com/products/express-shop/'
                ], 

                [
                    'slug'      => 'woo-tabbed-category-product-listing',
                    'fields'    => [
                        'short_description' => true,
                        'icons'             => true,
                        'reviews'           => false, // excludes all reviews
                    ],
        			'live_preview'   => 'https://dev.quantumcloud.com/woocommerce-tabbed-product-display/',
        			'update_to_pro'  => 'https://www.quantumcloud.com/products/woo-tabbed-category-product-listing/'
                ],
                [
                    'slug'      => 'portfolio-x',
                    'fields'    => [
                        'short_description' => true,
                        'icons'             => true,
                        'reviews'           => false, // excludes all reviews
                    ],
        			'live_preview'   => 'https://dev.quantumcloud.com/portfolio-x/',
        			'update_to_pro'  => 'https://www.quantumcloud.com/products/portfolio-x-plugin/'
                ],
                [
                    'slug'      => 'bargain',
                    'fields'    => [
                        'short_description' => true,
                        'icons'             => true,
                        'reviews'           => false, // excludes all reviews
                    ],
        			'live_preview'   => 'https://dev.quantumcloud.com/bargainbot/',
        			'update_to_pro'  => 'https://www.quantumcloud.com/products/bargain-bot/'
                ],

                [
                    'slug'      => 'highlight',
                    'fields'    => [
                        'short_description' => true,
                        'icons'             => true,
                        'reviews'           => false, // excludes all reviews
                    ],
        			'live_preview'   => 'https://dev.dna88.com/highlight/',
        			'update_to_pro'  => 'https://www.dna88.com/product/highlight/'
                ], 
                [
                    'slug'      => 'video-connect',
                    'fields'    => [
                        'short_description' => true,
                        'icons'             => true,
                        'reviews'           => false, // excludes all reviews
                    ],
        			'live_preview'   => 'https://dev.dna88.com/video-connect/',
        			'update_to_pro'  => 'https://www.dna88.com/product/video-connect/'
                ], 

                [
                    'slug'      => 'voice-widgets',
                    'fields'    => [
                        'short_description' => true,
                        'icons'             => true,
                        'reviews'           => false, // excludes all reviews
                    ],
        			'live_preview'   => 'https://dev.dna88.com/voice-widgets/',
        			'update_to_pro'  => 'https://www.dna88.com/product/voice-widgets-pro/'
                ], 

                [
                    'slug'      => 'seo-help',
                    'fields'    => [
                        'short_description' => true,
                        'icons'             => true,
                        'reviews'           => false, // excludes all reviews
                    ],
        			'live_preview'   => '',
        			'update_to_pro'  => 'https://www.dna88.com/product/seo-help-pro/'
                ], 
                [
                    'slug'      => 'logo-or-image-replace',
                    'fields'    => [
                        'short_description' => true,
                        'icons'             => true,
                        'reviews'           => false, // excludes all reviews
                    ],
        			'live_preview'   => '',
        			'update_to_pro'  => 'https://www.quantumcloud.com/products/image-tools-for-wordpress/'
                ], 
                [
                    'slug'      => 'simple-media-directory',
                    'fields'    => [
                        'short_description' => true,
                        'icons'             => true,
                        'reviews'           => false, // excludes all reviews
                    ],
        			'live_preview'   => 'https://dev.quantumcloud.com/simple-media-directory/',
        			'update_to_pro'  => ''
                ], 

                [
                    'slug'      => 'increase-sales',
                    'fields'    => [
                        'short_description' => true,
                        'icons'             => true,
                        'reviews'           => false, // excludes all reviews
                    ],
        			'live_preview'   => '',
        			'update_to_pro'  => ''
                ]



            ];



        ?>

        <div class="recommended-plugins">
            <div class="wp-list-table widefat plugin-install">
                <div class="the-list">
                    <?php

                    //$qcld_plugininstal = array();
                    foreach ( $argus as $arg ) {

                        
                        $data = plugins_api( 'plugin_information', $arg );

                        if ( $data && ! is_wp_error( $data ) ) {
                            $qcld_plugininstal['convers-form'] = $data;
                        }
                        foreach ( (array) $qcld_plugininstal as $key => $plugin ) {
                            if ( is_object( $plugin ) ) {
                                $plugin = (array) $plugin;
                            }

                            $qcld_chatplugintags = array(
                                'a'       => array(
                                    'href'   => array(),
                                    'title'  => array(),
                                    'target' => array(),
                                ),
                            );
                            
                            // Display the group heading if there is one.
                            if ( isset( $plugin['group'] ) && $plugin['group'] != $group ) {
                                if ( isset( $this->groups[ $plugin['group'] ] ) ) {
                                    $group_name = $this->groups[ $plugin['group'] ];
                                    if ( isset( $plugins_group_titles[ $group_name ] ) ) {
                                        $group_name = $plugins_group_titles[ $group_name ];
                                    }
                                } else {
                                    $group_name = $plugin['group'];
                                }

                                // Starting a new group, close off the divs of the last one.
                                if ( ! empty( $group ) ) {
                                    echo '</div></div>';
                                }

                                echo '<div class="plugin-group"><h3>' . esc_html( $group_name ) . '</h3>';
                                // Needs an extra wrapping div for nth-child selectors to work.
                                echo '<div class="plugin-items">';

                                $group = $plugin['group'];
                            }
                            $title = wp_kses( $plugin['name'], $qcld_chatplugintags );

                            // Remove any HTML from the description.
                            $description = strip_tags( $plugin['short_description'] );
                            $version     = wp_kses( $plugin['version'], $qcld_chatplugintags );

                            $name = strip_tags( $title . ' ' . $version );

                            $author = wp_kses( $plugin['author'], $qcld_chatplugintags );
                            if ( ! empty( $author ) ) {
                                /* translators: %s: Plugin author. */
                                $author = ' <cite>' . sprintf( __( 'By %s' ), $author ) . '</cite>';
                            }

                            $requires_php = isset( $plugin['requires_php'] ) ? $plugin['requires_php'] : null;
                            $requires_wp  = isset( $plugin['requires'] ) ? $plugin['requires'] : null;

                            $compatible_php = is_php_version_compatible( $requires_php );
                            $compatible_wp  = is_wp_version_compatible( $requires_wp );
                            $tested_wp      = ( empty( $plugin['tested'] ) || version_compare( get_bloginfo( 'version' ), $plugin['tested'], '<=' ) );

                            $action_links = array();

                            if ( current_user_can( 'install_plugins' ) || current_user_can( 'update_plugins' ) ) {
                                $status = install_plugin_install_status( $plugin );

                                switch ( $status['status'] ) {
                                    case 'install':
                                        if ( $status['url'] ) {
                                            if ( $compatible_php && $compatible_wp ) {
                                                $action_links[] = sprintf(
                                                    '<a class="install-now button" data-slug="%s" href="%s" aria-label="%s" data-name="%s">%s</a>',
                                                    esc_attr( $plugin['slug'] ),
                                                    esc_url( $status['url'] ),
                                                    /* translators: %s: Plugin name and version. */
                                                    esc_attr( sprintf( _x( 'Install %s now', 'plugin' ), $name ) ),
                                                    esc_attr( $name ),
                                                    __( 'Install Now' )
                                                );
                                            } else {
                                                $action_links[] = sprintf(
                                                    '<button type="button" class="button button-disabled" disabled="disabled">%s</button>',
                                                    _x( 'Cannot Install', 'plugin' )
                                                );
                                            }
                                        }
                                        break;

                                    case 'update_available':
                                        if ( $status['url'] ) {
                                            if ( $compatible_php && $compatible_wp ) {
                                                $action_links[] = sprintf(
                                                    '<a class="update-now button aria-button-if-js" data-plugin="%s" data-slug="%s" href="%s" aria-label="%s" data-name="%s">%s</a>',
                                                    esc_attr( $status['file'] ),
                                                    esc_attr( $plugin['slug'] ),
                                                    esc_url( $status['url'] ),
                                                    /* translators: %s: Plugin name and version. */
                                                    esc_attr( sprintf( _x( 'Update %s now', 'plugin' ), $name ) ),
                                                    esc_attr( $name ),
                                                    __( 'Update Now' )
                                                );
                                            } else {
                                                $action_links[] = sprintf(
                                                    '<button type="button" class="button button-disabled" disabled="disabled">%s</button>',
                                                    _x( 'Cannot Update', 'plugin' )
                                                );
                                            }
                                        }
                                        break;

                                    case 'latest_installed':
                                    case 'newer_installed':
                                        if ( is_plugin_active( $status['file'] ) ) {
                                            $action_links[] = sprintf(
                                                '<button type="button" class="button button-disabled" disabled="disabled">%s</button>',
                                                _x( 'Active', 'plugin' )
                                            );
                                        } elseif ( current_user_can( 'activate_plugin', $status['file'] ) ) {
                                            $button_text = __( 'Activate' );
                                            /* translators: %s: Plugin name. */
                                            $button_label = _x( 'Activate %s', 'plugin' );
                                            $activate_url = add_query_arg(
                                                array(
                                                    '_wpnonce' => wp_create_nonce( 'activate-plugin_' . $status['file'] ),
                                                    'action'   => 'activate',
                                                    'plugin'   => $status['file'],
                                                ),
                                                network_admin_url( 'plugins.php' )
                                            );

                                            if ( is_network_admin() ) {
                                                $button_text = __( 'Network Activate' );
                                                /* translators: %s: Plugin name. */
                                                $button_label = _x( 'Network Activate %s', 'plugin' );
                                                $activate_url = add_query_arg( array( 'networkwide' => 1 ), $activate_url );
                                            }

                                            $action_links[] = sprintf(
                                                '<a href="%1$s" class="button activate-now" aria-label="%2$s">%3$s</a>',
                                                esc_url( $activate_url ),
                                                esc_attr( sprintf( $button_label, $plugin['name'] ) ),
                                                $button_text
                                            );
                                        } else {
                                            $action_links[] = sprintf(
                                                '<button type="button" class="button button-disabled" disabled="disabled">%s</button>',
                                                _x( 'Installed', 'plugin' )
                                            );
                                        }
                                        break;
                                }
                            }

                            // $details_link = self_admin_url(
                            //     'plugin-install.php?tab=plugin-information&amp;plugin=' . $plugin['slug'] .
                            //     '&amp;width=700&amp;height=550'
                            // );
                            // $action_links[] = sprintf( '%s','<a href="#" data-toggle="modal" data-target="#myModal_'.$plugin['slug'].'">More Details</a><div class="modal fade" id="myModal_'.$plugin['slug'].'" tabindex="-1" role="dialog" aria-labelledby="myModalLabel"><div class="modal-dialog" role="document"><div class="modal-content"><div class="modal-header"><button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button></div><div class="modal-body"><iframe width="100%" height="550" src="'.$details_link.'"></iframe></div></div></div></div>');
                            $plugin_live_link = "https://wordpress.org/plugins/".$plugin['slug'];
                            $action_links[] = sprintf( '%s','<a href="'.esc_url($plugin_live_link).'" target="_blank">' . esc_html('More Details') . '</a>');
                            /*===show icon ==*/
                            if ( ! empty( $plugin['icons']['svg'] ) ) {
                                $plugin_icon_url = $plugin['icons']['svg'];
                            } elseif ( ! empty( $plugin['icons']['2x'] ) ) {
                                $plugin_icon_url = $plugin['icons']['2x'];
                            } elseif ( ! empty( $plugin['icons']['1x'] ) ) {
                                $plugin_icon_url = $plugin['icons']['1x'];
                            } else {
                                $plugin_icon_url = $plugin['icons']['default'];
                            }
                            /*
                             * $action_links An array of plugin action links. Defaults are links to Details and Install Now.
                             * $plugin The plugin currently being listed.
                             */
                            $action_links = apply_filters( 'plugin_install_action_links', $action_links, $plugin );

                            $last_updated_timestamp = strtotime( $plugin['last_updated'] );
                            ?>
                            <div class="plugin-card plugin-card-<?php echo sanitize_html_class( $plugin['slug'] ); ?>">
                                <?php
                                if ( ! $compatible_php || ! $compatible_wp ) {
                                    echo '<div class="notice inline notice-error notice-alt"><p>';
                                    if ( ! $compatible_php && ! $compatible_wp ) {
                                        _e( 'This plugin doesn&#8217;t work with your versions of WordPress and PHP.' );
                                        if ( current_user_can( 'update_core' ) && current_user_can( 'update_php' ) ) {
                                            printf(
                                            /* translators: 1: URL to WordPress Updates screen, 2: URL to Update PHP page. */
                                                ' ' . __( '<a href="%1$s">Please update WordPress</a>, and then <a href="%2$s">learn more about updating PHP</a>.' ),
                                                self_admin_url( 'update-core.php' ),
                                                esc_url( wp_get_update_php_url() )
                                            );
                                            wp_update_php_annotation( '</p><p><em>', '</em>' );
                                        } elseif ( current_user_can( 'update_core' ) ) {
                                            printf(
                                            /* translators: %s: URL to WordPress Updates screen. */
                                                ' ' . __( '<a href="%s">Please update WordPress</a>.' ),
                                                self_admin_url( 'update-core.php' )
                                            );
                                        } elseif ( current_user_can( 'update_php' ) ) {
                                            printf(
                                            /* translators: %s: URL to Update PHP page. */
                                                ' ' . __( '<a href="%s">Learn more about updating PHP</a>.' ),
                                                esc_url( wp_get_update_php_url() )
                                            );
                                            wp_update_php_annotation( '</p><p><em>', '</em>' );
                                        }
                                    } elseif ( ! $compatible_wp ) {
                                        _e( 'This plugin doesn&#8217;t work with your version of WordPress.' );
                                        if ( current_user_can( 'update_core' ) ) {
                                            printf(
                                            /* translators: %s: URL to WordPress Updates screen. */
                                                ' ' . __( '<a href="%s">Please update WordPress</a>.' ),
                                                self_admin_url( 'update-core.php' )
                                            );
                                        }
                                    } elseif ( ! $compatible_php ) {
                                        _e( 'This plugin doesn&#8217;t work with your version of PHP.' );
                                        if ( current_user_can( 'update_php' ) ) {
                                            printf(
                                            /* translators: %s: URL to Update PHP page. */
                                                ' ' . __( '<a href="%s">Learn more about updating PHP</a>.' ),
                                                esc_url( wp_get_update_php_url() )
                                            );
                                            wp_update_php_annotation( '</p><p><em>', '</em>' );
                                        }
                                    }
                                    echo '</p></div>';
                                }
                                ?>
                                <div class="plugin-card-top">
                                    <div class="name column-name">
                                        <h3>
                                            <a href="<?php echo esc_url( $plugin_live_link ); ?>" target="_blank" >
                                                <?php echo esc_attr($title); ?>
                                               
                                                <img src="<?php echo esc_attr( $plugin_icon_url ); ?>" class="plugin-icon" alt="" />
                                            </a>
                                        </h3>
                                    </div>
                                    <div class="action-links">
                                        <?php
                                        if ( $action_links ) {
                                            echo '<ul class="plugin-action-buttons"><li>' . implode( '</li><li>', $action_links ) . '</li></ul>';
                                        }
                                        ?>
                                    </div>
        							<?php 
                                    
                                    if ( ( isset( $arg['live_preview'] ) && !empty( $arg['live_preview'] ) ) || ( isset( $arg['update_to_pro'] ) && !empty( $arg['update_to_pro'] ) )  ) { ?>
                                    <div class="action-pro-links">
        								<ul class="plugin-action-pro-buttons">
                                        <?php
                                        if ( !empty( $arg['live_preview'] ) ) { 
                                            echo '<li><a href="'.esc_url( $arg['live_preview'] ).'" target="_blank">' . esc_html('Live Preview') . '</a></li>';
                                        }
                                        if ( !empty( $arg['update_to_pro'] ) ) { 
                                            echo '<li><a href="'.esc_url( $arg['update_to_pro'] ).'" target="_blank">' . esc_html('Update To Pro') . '</a></li>';
                                        }
                                        ?>
        								</ul>
                                    </div>
        							<?php } ?>
                                </div>
                            </div>
                            <?php
                        } 
                    }
                    ?>
                </div>
            </div>

        </div>
<?php 

    echo  ob_get_clean();
    exit();




        }

}

