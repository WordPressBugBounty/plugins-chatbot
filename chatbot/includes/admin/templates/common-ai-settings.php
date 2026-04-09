<?php

/**
 * AI settings Template for OpenAI, Gemini, OpenRouter and Grok
 * @package Botmaster
 */

$wpchatbot_license_valid            = get_option('wpchatbot_license_valid');
// $wpchatbot_license_valid            = 'starter';

?>
<style>
.qcl-openai .qcld-row {
    margin-right: 0;
    margin-left: 0;
    display: flex;
    flex-wrap: wrap;
}
</style>
    <div class="qcl-openai">
        <h2 class="nav-tab-wrapper">
            <a href="#qcld-rag-settings-tab" class="nav-tab"><?php esc_html_e('Rate Limit', 'wpchatbot'); ?></a>
            <!-- <a href="#qcld-other-common-tab" class="nav-tab"><?php//  esc_html_e('Other Common Settings', 'wpchatbot'); ?></a> -->
        </h2>
        <div id="qcld-rag-settings-tab" class="qcld-tab-content active">
            <div class="wrap my-4">
                <div class="qcld-row g-0">
                    <div class="form-check form-switch my-4">
                        <input class="form-check-input" type="checkbox" <?php echo ( get_option( 'is_rate_limiting_enabled' ) == 1 ) ? esc_attr( 'checked', 'wpchatbot' ) : ''; ?>  role="switch" value="" id="is_rate_limiting_enabled">
                        <label class="form-check-label" for="is_rate_limiting_enabled">
                            <?php esc_html_e( 'Enable Rate Limiting', 'wpchatbot' ); ?>
                        </label>
                    </div>
                </div>
                <div class="qcld-row g-0">
                    <p class="text-muted">
                        <?php esc_html_e( 'Set the maximum number of requests each user of a particular role can make to the OpenAI API within a specific period. Leave the field empty or set it to 0 for unlimited requests.', 'wpchatbot' ); ?>
                    </p>	
                </div>
                    <hr>
                 <div class="qcld-row g-0">
                    <div id="rate_limit_settings" <?php echo ( get_option( 'is_rate_limiting_enabled' ) != 1 ) ? esc_attr( 'style="display:none;"', 'wpchatbot' ) : ''; ?>>	
                        <p class="text-muted">
                            <?php esc_html_e( 'Set the rate limit for each user role:', 'wpchatbot' ); ?>
                        </p>										
                    </div>	
                </div>
                <div id="rate_limit_settings" <?php echo ( get_option( 'is_rate_limiting_enabled' ) != 1 ) ? esc_attr( 'style="display:none;"', 'wpchatbot' ) : ''; ?>>	</div> 
                	    <?php 
                            global $wp_roles;
                            // Check if the global object exists and has the roles property
                            if ( $wp_roles && property_exists( $wp_roles, 'roles' ) ) {
                                // Loop through each role in the 'roles' array
                                foreach ( $wp_roles->roles as $role_key => $role_details ) {
                                    $option_name = 'rate_limit_' . $role_key;
                                    $rate_limit_value = get_option( $option_name, '' );
                                    ?>
                                    <div class="qcld-row mb-4" style="margin-bottom:20px;">
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label for="<?php echo esc_attr( $option_name ); ?>" class="form-label">
                                                    <?php
                                                    /* translators: %s: Role name */
                                                    printf( esc_html__( 'Rate limit for %s', 'wpchatbot' ), esc_html( $role_details['name'] ) );
                                                    ?>
                                                </label>
                                                    <input type="number" class="form-control rate-limit-input" id="<?php echo esc_attr( $option_name ); ?>" value="<?php echo esc_attr( $rate_limit_value ); ?>" min="0" <?php echo ( get_option( 'is_rate_limiting_enabled' ) != 1 ) ? esc_attr( 'disabled', 'wpchatbot' ) : ''; ?>>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label for="<?php echo esc_attr( 'Timeframe:' ); ?>" class="form-label">
                                                    <?php
                                                        $timeframe = get_option( 'rate_limit_timeframe_' . $role_key, '24_hours' );
                                                        esc_html_e( 'Timeframe:', 'wpchatbot' );
                                                        $timeframe = intval( $timeframe ) / 3600 ; // Convert seconds back to hours for display
                                                    ?>
                                                </label></br>
                                                <select class="form-select rate-limit-timeframe" id="<?php echo esc_attr( 'rate_limit_timeframe_' . $role_key ); ?>" <?php echo ( get_option( 'is_rate_limiting_enabled' ) != 1 ) ? esc_attr( 'disabled', 'wpchatbot' ) : ''; ?>>
                                                    <option value="48" <?php if ( $timeframe == '48' ) echo 'selected'; ?>><?php esc_html_e( '48 Hours', 'wpchatbot' ); ?></option>
                                                    <option value="24" <?php if ( $timeframe == '24' ) echo 'selected'; ?>><?php esc_html_e( '24 Hours', 'wpchatbot' ); ?></option>
                                                    <option value="12" <?php if ( $timeframe == '12' ) echo 'selected'; ?>><?php esc_html_e( '12 Hours', 'wpchatbot' ); ?></option>
                                                    <option value="6" <?php if ( $timeframe == '6' ) echo 'selected'; ?>><?php esc_html_e( '6 Hours', 'wpchatbot' ); ?></option>
                                                </select>
                                            </div>	
                                        </div>
                                    </div>
                        <?php
                                }
                            }
                        ?>
                        <div class="qcld-row mb-4" style="margin-bottom:20px;">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="<?php echo esc_attr( '' ); ?>" class="form-label">
                                        <?php
                                        printf( esc_html__( 'Rate limit for ', 'wpchatbot' ) . esc_html__( 'Guest Users', 'wpchatbot' ) );
                                        ?>
                                    </label>
                                        <input type="number" class="form-control rate-limit-input" id="<?php echo esc_attr( 'rate_limit_guest' ); ?>" value="<?php echo esc_attr( get_option( 'rate_limit_guest', '' ) ); ?>" min="0" <?php echo ( get_option( 'is_rate_limiting_enabled' ) != 1 ) ? esc_attr( 'disabled', 'wpchatbot' ) : ''; ?>>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="<?php echo esc_attr( 'Timeframe:' ); ?>" class="form-label">
                                        <?php
                                        esc_html_e( 'Timeframe:', 'wpchatbot' );
                                        ?>
                                    </label></br>
                                    <select class="form-select" <?php echo ( get_option( 'is_rate_limiting_enabled' ) != 1 ) ? esc_attr( 'disabled', 'wpchatbot' ) : ''; ?>>
                                        <option value="48" disabled><?php esc_html_e( '48 Hours', 'wpchatbot' ); ?></option>
                                        <option value="24" selected><?php esc_html_e( '24 Hours', 'wpchatbot' ); ?></option>
                                        <option value="6" disabled><?php esc_html_e( '6 Hours', 'wpchatbot' ); ?></option>
                                        <option value="12" disabled><?php esc_html_e( '12 Hours', 'wpchatbot' ); ?></option>
                                    </select>
                                </div>	
                            </div>
                        </div>
                            
            </div>
            <div class="wrap">
                <button class="qcld-btn-primary" id="qcld_openai_rate_limit_save_setting">Save Settings</button>
            </div>
        </div>
        <!-- <div id="qcld-other-common-tab" class="qcld-tab-content">
            <div class="wrap my-4">
            </div>
        </div> -->

