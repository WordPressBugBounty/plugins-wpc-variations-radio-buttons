<?php
defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'WPClever_Woovr_Backend' ) ) {
    class WPClever_Woovr_Backend {
        protected static $instance = null;

        public static function instance() {
            if ( is_null( self::$instance ) ) {
                self::$instance = new self();
            }

            return self::$instance;
        }

        function __construct() {
            // settings page
            add_action( 'admin_init', [ $this, 'register_settings' ] );
            add_filter( 'pre_update_option', [ $this, 'last_saved' ], 10, 2 );
            add_action( 'admin_menu', [ $this, 'admin_menu' ] );

            // settings link
            add_filter( 'plugin_action_links', [ $this, 'action_links' ], 10, 2 );
            add_filter( 'plugin_row_meta', [ $this, 'row_meta' ], 10, 2 );

            // enqueue backend scripts
            add_action( 'admin_enqueue_scripts', [ $this, 'admin_enqueue_scripts' ], 99 );

            // product data tabs
            add_filter( 'woocommerce_product_data_tabs', [ $this, 'product_data_tabs' ] );
            add_action( 'woocommerce_product_data_panels', [ $this, 'product_data_panels' ] );
            add_action( 'woocommerce_process_product_meta', [ $this, 'process_product_meta' ] );

            // custom variation name & image
            add_action( 'woocommerce_product_after_variable_attributes', [ $this, 'variation_settings' ], 10, 3 );
            add_action( 'woocommerce_save_product_variation', [ $this, 'save_variation_settings' ], 10, 2 );

            // WPC Variation Duplicator
            add_action( 'wpcvd_duplicated', [ $this, 'duplicate_variation' ], 99, 2 );

            // WPC Variation Bulk Editor
            add_action( 'wpcvb_bulk_update_variation', [ $this, 'bulk_update_variation' ], 99, 2 );
        }

        function register_settings() {
            // settings
            register_setting( 'woovr_settings', 'woovr_settings', [
                    'type'              => 'array',
                    'sanitize_callback' => [ $this, 'sanitize_array' ],
            ] );
        }

        function last_saved( $value, $option ) {
            if ( $option == 'woovr_settings' ) {
                $value['_last_saved']    = current_time( 'timestamp' );
                $value['_last_saved_by'] = get_current_user_id();
            }

            return $value;
        }

        function admin_menu() {
            add_submenu_page( 'wpclever', esc_html__( 'WPC Variations Radio Buttons', 'wpc-variations-radio-buttons' ), esc_html__( 'Variations Radio Buttons', 'wpc-variations-radio-buttons' ), 'manage_options', 'wpclever-woovr', [
                    $this,
                    'admin_menu_content'
            ] );
        }

        function admin_menu_content() {
            $active_tab = sanitize_key( wp_unslash( $_GET['tab'] ?? 'settings' ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
            ?>
            <div class="wpclever_settings_page wrap">
                <div class="wpclever_settings_page_header">
                    <a class="wpclever_settings_page_header_logo" href="https://wpclever.net/"
                       target="_blank" title="Visit wpclever.net"></a>
                    <div class="wpclever_settings_page_header_text">
                        <div class="wpclever_settings_page_title"><?php echo esc_html__( 'WPC Variations Radio Buttons', 'wpc-variations-radio-buttons' ) . ' ' . esc_html( WOOVR_VERSION ) . ' ' . ( defined( 'WOOVR_PREMIUM' ) ? '<span class="premium" style="display: none">' . esc_html__( 'Premium', 'wpc-variations-radio-buttons' ) . '</span>' : '' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div>
                        <div class="wpclever_settings_page_desc about-text">
                            <p>
                                <?php printf( /* translators: stars */ esc_html__( 'Thank you for using our plugin! If you are satisfied, please reward it a full five-star %s rating.', 'wpc-variations-radio-buttons' ), '<span style="color:#ffb900">&#9733;&#9733;&#9733;&#9733;&#9733;</span>' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                                <br/>
                                <a href="<?php echo esc_url( WOOVR_REVIEWS ); ?>"
                                   target="_blank"><?php esc_html_e( 'Reviews', 'wpc-variations-radio-buttons' ); ?></a>
                                |
                                <a href="<?php echo esc_url( WOOVR_CHANGELOG ); ?>"
                                   target="_blank"><?php esc_html_e( 'Changelog', 'wpc-variations-radio-buttons' ); ?></a>
                                |
                                <a href="<?php echo esc_url( WOOVR_DISCUSSION ); ?>"
                                   target="_blank"><?php esc_html_e( 'Discussion', 'wpc-variations-radio-buttons' ); ?></a>
                            </p>
                        </div>
                    </div>
                </div>
                <h2></h2>
                <?php if ( isset( $_GET['settings-updated'] ) && sanitize_text_field( wp_unslash( $_GET['settings-updated'] ?? '' ) ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended ?>
                    <div class="notice notice-success is-dismissible">
                        <p><?php esc_html_e( 'Settings updated.', 'wpc-variations-radio-buttons' ); ?></p>
                    </div>
                <?php } ?>
                <div class="wpclever_settings_page_nav">
                    <h2 class="nav-tab-wrapper">
                        <a href="<?php echo esc_url( admin_url( 'admin.php?page=wpclever-woovr&tab=settings' ) ); ?>"
                           class="<?php echo esc_attr( $active_tab === 'settings' ? 'nav-tab nav-tab-active' : 'nav-tab' ); ?>">
                            <?php esc_html_e( 'Settings', 'wpc-variations-radio-buttons' ); ?>
                        </a>
                        <a href="<?php echo esc_url( admin_url( 'admin.php?page=wpclever-woovr&tab=premium' ) ); ?>"
                           class="<?php echo esc_attr( $active_tab === 'premium' ? 'nav-tab nav-tab-active' : 'nav-tab' ); ?>"
                           style="color: #c9356e">
                            <?php esc_html_e( 'Premium Version', 'wpc-variations-radio-buttons' ); ?>
                        </a>
                        <a href="<?php echo esc_url( admin_url( 'admin.php?page=wpclever-kit' ) ); ?>"
                           class="nav-tab">
                            <?php esc_html_e( 'Essential Kit', 'wpc-variations-radio-buttons' ); ?>
                        </a>
                    </h2>
                </div>
                <div class="wpclever_settings_page_content">
                    <?php if ( $active_tab === 'settings' ) {
                        $active             = WPClever_Woovr::get_setting( 'active', 'yes' );
                        $hide_unpurchasable = WPClever_Woovr::get_setting( 'hide_unpurchasable', 'no' );
                        $selector           = WPClever_Woovr::get_setting( 'selector', 'default' );
                        $orderby            = WPClever_Woovr::get_setting( 'orderby', 'default' );
                        $order              = WPClever_Woovr::get_setting( 'order', 'default' );
                        $show_name          = WPClever_Woovr::get_setting( 'variation_name', 'formatted' );
                        $product_name       = WPClever_Woovr::get_setting( 'product_name', 'yes' );
                        $show_clear         = WPClever_Woovr::get_setting( 'show_clear', 'yes' );
                        $show_image         = WPClever_Woovr::get_setting( 'show_image', 'yes' );
                        $show_price         = WPClever_Woovr::get_setting( 'show_price', 'yes' );
                        $show_availability  = WPClever_Woovr::get_setting( 'show_availability', 'yes' );
                        $show_description   = WPClever_Woovr::get_setting( 'show_description', 'yes' );
                        $clear_label        = WPClever_Woovr::get_setting( 'clear_label' );
                        $clear_image        = WPClever_Woovr::get_setting( 'clear_image', 'placeholder' );
                        $clear_image_id     = WPClever_Woovr::get_setting( 'clear_image_id', '' );
                        ?>
                        <form method="post" action="options.php">
                            <table class="form-table">
                                <tr>
                                    <th>
                                        <?php esc_html_e( 'Active', 'wpc-variations-radio-buttons' ); ?>
                                    </th>
                                    <td>
                                        <label> <select name="woovr_settings[active]">
                                                <option value="no" <?php echo esc_attr( $active === 'no' || $active === 'yes_wpc' ? 'selected' : '' ); ?>><?php esc_html_e( 'No', 'wpc-variations-radio-buttons' ); ?></option>
                                                <option value="yes" <?php selected( $active, 'yes' ); ?>><?php esc_html_e( 'Yes', 'wpc-variations-radio-buttons' ); ?></option>
                                            </select> </label>
                                        <span class="description"><?php esc_html_e( 'This is the default status, you can set status for individual product in the its settings.', 'wpc-variations-radio-buttons' ); ?></span>
                                    </td>
                                </tr>
                                <tr>
                                    <th>
                                        <?php esc_html_e( 'Hide unpurchasable variation', 'wpc-variations-radio-buttons' ); ?>
                                    </th>
                                    <td>
                                        <label> <select name="woovr_settings[hide_unpurchasable]">
                                                <option value="no" <?php selected( $hide_unpurchasable, 'no' ); ?>><?php esc_html_e( 'No', 'wpc-variations-radio-buttons' ); ?></option>
                                                <option value="yes" <?php selected( $hide_unpurchasable, 'yes' ); ?>><?php esc_html_e( 'Yes', 'wpc-variations-radio-buttons' ); ?></option>
                                            </select> </label>
                                    </td>
                                </tr>
                                <tr>
                                    <th><?php esc_html_e( 'Selector interface', 'wpc-variations-radio-buttons' ); ?></th>
                                    <td>
                                        <label> <select name="woovr_settings[selector]">
                                                <option value="default" <?php selected( $selector, 'default' ); ?>><?php esc_html_e( 'Radio buttons (default)', 'wpc-variations-radio-buttons' ); ?></option>
                                                <option value="ddslick" <?php selected( $selector, 'ddslick' ); ?>><?php esc_html_e( 'ddSlick', 'wpc-variations-radio-buttons' ); ?></option>
                                                <option value="select2" <?php selected( $selector, 'select2' ); ?>><?php esc_html_e( 'Select2', 'wpc-variations-radio-buttons' ); ?></option>
                                                <option value="select" <?php selected( $selector, 'select' ); ?>><?php esc_html_e( 'HTML select tag', 'wpc-variations-radio-buttons' ); ?></option>
                                                <option value="grid-2" <?php selected( $selector, 'grid-2' ); ?>><?php esc_html_e( 'Grid - 2 columns', 'wpc-variations-radio-buttons' ); ?></option>
                                                <option value="grid-3" <?php selected( $selector, 'grid-3' ); ?> <?php selected( $selector, 'grid' ); ?>><?php esc_html_e( 'Grid - 3 columns', 'wpc-variations-radio-buttons' ); ?></option>
                                                <option value="grid-4" <?php selected( $selector, 'grid-4' ); ?>><?php esc_html_e( 'Grid - 4 columns', 'wpc-variations-radio-buttons' ); ?></option>
                                            </select> </label> <span class="description">
                                                    Read more about ddSlick, Select2 and HTML select tag <a
                                                    href="https://wpclever.net/downloads/variations-radio-buttons"
                                                    target="_blank">here</a>.
                                                </span>
                                    </td>
                                </tr>
                                <tr>
                                    <th>
                                        <?php esc_html_e( 'Show "Option none"', 'wpc-variations-radio-buttons' ); ?>
                                    </th>
                                    <td>
                                        <label> <select name="woovr_settings[show_clear]">
                                                <option value="no" <?php selected( $show_clear, 'no' ); ?>><?php esc_html_e( 'No', 'wpc-variations-radio-buttons' ); ?></option>
                                                <option value="yes" <?php selected( $show_clear, 'yes' ); ?>><?php esc_html_e( 'Yes', 'wpc-variations-radio-buttons' ); ?></option>
                                            </select> </label>
                                    </td>
                                </tr>
                                <tr>
                                    <th>
                                        <?php esc_html_e( '"Option none" label', 'wpc-variations-radio-buttons' ); ?>
                                    </th>
                                    <td>
                                        <label>
                                            <input type="text" class="regular-text"
                                                   name="woovr_settings[clear_label]"
                                                   placeholder="<?php esc_html_e( 'Choose an option', 'wpc-variations-radio-buttons' ); ?>"
                                                   value="<?php echo esc_attr( $clear_label ); ?>"/>
                                        </label>
                                        <p class="description"><?php esc_html_e( 'Leave blank to use the default text and its equivalent translation in multiple languages.', 'wpc-variations-radio-buttons' ); ?></p>
                                    </td>
                                </tr>
                                <tr>
                                    <th><?php esc_html_e( '"Option none" image', 'wpc-variations-radio-buttons' ); ?></th>
                                    <td>
                                        <label>
                                            <select name="woovr_settings[clear_image]"
                                                    class="woovr_clear_image">
                                                <option value="placeholder" <?php selected( $clear_image, 'placeholder' ); ?>><?php esc_html_e( 'Placeholder image', 'wpc-variations-radio-buttons' ); ?></option>
                                                <option value="product" <?php selected( $clear_image, 'product' ); ?>><?php esc_html_e( 'Main product\'s image', 'wpc-variations-radio-buttons' ); ?></option>
                                                <option value="custom" <?php selected( $clear_image, 'custom' ); ?>><?php esc_html_e( 'Custom image', 'wpc-variations-radio-buttons' ); ?></option>
                                                <option value="none" <?php selected( $clear_image, 'none' ); ?>><?php esc_html_e( 'No image', 'wpc-variations-radio-buttons' ); ?></option>
                                            </select> </label>
                                        <p class="description"><?php esc_html_e( 'If you choose "Placeholder image", you can change it in WooCommerce > Settings > Products > Placeholder image.', 'wpc-variations-radio-buttons' ); ?></p>
                                        <div class="woovr_clear_image_custom" style="display: none">
                                            <?php wp_enqueue_media(); ?>
                                            <span class="woovr_image_selector">
														<input type="hidden" class="woovr_image_id"
                                                               name="woovr_settings[clear_image_id]"
                                                               value="<?php echo esc_attr( $clear_image_id ); ?>">
														<span class="woovr_image_preview">
															<?php if ( $clear_image_id ) {
                                                                echo '<span class="woovr_image_preview">' . wp_get_attachment_image( $clear_image_id ) . '<a class="woovr_image_remove button" href="#">' . esc_html__( 'Remove', 'wpc-variations-radio-buttons' ) . '</a></span>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                                                            } else {
                                                                echo '<span class="woovr_image_preview">' . wc_placeholder_img() . '</span>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                                                            } ?>
														</span>
														<a href="#"
                                                           class="woovr_image_add button"><?php esc_attr_e( 'Choose Image', 'wpc-variations-radio-buttons' ); ?></a>
													</span>
                                        </div>
                                    </td>
                                </tr>
                                <tr>
                                    <th><?php esc_html_e( 'Order by', 'wpc-variations-radio-buttons' ); ?></th>
                                    <td>
                                        <label> <select name="woovr_settings[orderby]">
                                                <option value="default" <?php selected( $orderby, 'default' ); ?>><?php esc_html_e( 'Default', 'wpc-variations-radio-buttons' ); ?></option>
                                                <option value="name" <?php selected( $orderby, 'name' ); ?>><?php esc_html_e( 'Name', 'wpc-variations-radio-buttons' ); ?></option>
                                                <option value="price" <?php selected( $orderby, 'price' ); ?>><?php esc_html_e( 'Price', 'wpc-variations-radio-buttons' ); ?></option>
                                            </select> </label>
                                    </td>
                                </tr>
                                <tr>
                                    <th><?php esc_html_e( 'Order', 'wpc-variations-radio-buttons' ); ?></th>
                                    <td>
                                        <label> <select name="woovr_settings[order]">
                                                <option value="default" <?php selected( $order, 'default' ); ?>><?php esc_html_e( 'Default', 'wpc-variations-radio-buttons' ); ?></option>
                                                <option value="asc" <?php selected( $order, 'asc' ); ?>><?php esc_html_e( 'ASC', 'wpc-variations-radio-buttons' ); ?></option>
                                                <option value="desc" <?php selected( $order, 'desc' ); ?>><?php esc_html_e( 'DESC', 'wpc-variations-radio-buttons' ); ?></option>
                                            </select> </label>
                                    </td>
                                </tr>
                                <tr>
                                    <th><?php esc_html_e( 'Variation name', 'wpc-variations-radio-buttons' ); ?></th>
                                    <td>
                                        <label> <select name="woovr_settings[variation_name]">
                                                <option value="formatted" <?php selected( $show_name, 'formatted' ); ?>><?php esc_html_e( 'Formatted without attribute label (e.g Green, M)', 'wpc-variations-radio-buttons' ); ?></option>
                                                <option value="formatted_label" <?php selected( $show_name, 'formatted_label' ); ?>><?php esc_html_e( 'Formatted with attribute label (e.g Color: Green, Size: M)', 'wpc-variations-radio-buttons' ); ?></option>
                                            </select> </label>
                                    </td>
                                </tr>
                                <tr>
                                    <th>
                                        <?php esc_html_e( 'Include product name', 'wpc-variations-radio-buttons' ); ?>
                                    </th>
                                    <td>
                                        <label> <select name="woovr_settings[product_name]">
                                                <option value="no" <?php selected( $product_name, 'no' ); ?>><?php esc_html_e( 'No', 'wpc-variations-radio-buttons' ); ?></option>
                                                <option value="yes" <?php selected( $product_name, 'yes' ); ?>><?php esc_html_e( 'Yes', 'wpc-variations-radio-buttons' ); ?></option>
                                            </select> </label>
                                        <span class="description"><?php esc_html_e( 'Include the product name before variation name.', 'wpc-variations-radio-buttons' ); ?></span>
                                    </td>
                                </tr>
                                <tr>
                                    <th>
                                        <?php esc_html_e( 'Show image', 'wpc-variations-radio-buttons' ); ?>
                                    </th>
                                    <td>
                                        <label> <select name="woovr_settings[show_image]">
                                                <option value="no" <?php selected( $show_image, 'no' ); ?>><?php esc_html_e( 'No', 'wpc-variations-radio-buttons' ); ?></option>
                                                <option value="yes" <?php selected( $show_image, 'yes' ); ?>><?php esc_html_e( 'Yes', 'wpc-variations-radio-buttons' ); ?></option>
                                            </select> </label>
                                    </td>
                                </tr>
                                <tr>
                                    <th>
                                        <?php esc_html_e( 'Show price', 'wpc-variations-radio-buttons' ); ?>
                                    </th>
                                    <td>
                                        <label> <select name="woovr_settings[show_price]">
                                                <option value="no" <?php selected( $show_price, 'no' ); ?>><?php esc_html_e( 'No', 'wpc-variations-radio-buttons' ); ?></option>
                                                <option value="yes" <?php selected( $show_price, 'yes' ); ?>><?php esc_html_e( 'Yes', 'wpc-variations-radio-buttons' ); ?></option>
                                            </select> </label>
                                    </td>
                                </tr>
                                <tr>
                                    <th>
                                        <?php esc_html_e( 'Show availability', 'wpc-variations-radio-buttons' ); ?>
                                    </th>
                                    <td>
                                        <label> <select name="woovr_settings[show_availability]">
                                                <option value="no" <?php selected( $show_availability, 'no' ); ?>><?php esc_html_e( 'No', 'wpc-variations-radio-buttons' ); ?></option>
                                                <option value="yes" <?php selected( $show_availability, 'yes' ); ?>><?php esc_html_e( 'Yes', 'wpc-variations-radio-buttons' ); ?></option>
                                            </select> </label>
                                    </td>
                                </tr>
                                <tr>
                                    <th>
                                        <?php esc_html_e( 'Show description', 'wpc-variations-radio-buttons' ); ?>
                                    </th>
                                    <td>
                                        <label> <select name="woovr_settings[show_description]">
                                                <option value="no" <?php selected( $show_description, 'no' ); ?>><?php esc_html_e( 'No', 'wpc-variations-radio-buttons' ); ?></option>
                                                <option value="yes" <?php selected( $show_description, 'yes' ); ?>><?php esc_html_e( 'Yes', 'wpc-variations-radio-buttons' ); ?></option>
                                            </select> </label>
                                    </td>
                                </tr>
                                <tr class="submit">
                                    <th colspan="2">
                                        <div class="wpclever_submit">
                                            <?php
                                            settings_fields( 'woovr_settings' );
                                            submit_button( '', 'primary', 'submit', false );

                                            if ( function_exists( 'wpc_last_saved' ) ) {
                                                wpc_last_saved( WPClever_Woovr::get_settings() );
                                            }
                                            ?>
                                        </div>
                                        <a style="display: none;" class="wpclever_export"
                                           data-key="woovr_settings"
                                           data-name="settings"
                                           href="#"><?php esc_html_e( 'import / export', 'wpc-variations-radio-buttons' ); ?></a>
                                    </th>
                                </tr>
                            </table>
                        </form>
                    <?php } elseif ( $active_tab === 'premium' ) { ?>
                        <div class="wpclever_settings_page_content_text">
                            <p>
                                Get the Premium Version just $29!
                                <a href="https://wpclever.net/downloads/variations-radio-buttons?utm_source=pro&utm_medium=woovr&utm_campaign=wporg"
                                   target="_blank">https://wpclever.net/downloads/variations-radio-buttons</a>
                            </p>
                            <p><strong>Extra features for Premium Version:</strong></p>
                            <ul style="margin-bottom: 0">
                                <li>- Settings for individual product.</li>
                                <li>- Get the lifetime update & premium support.</li>
                            </ul>
                        </div>
                    <?php } ?>
                </div><!-- /.wpclever_settings_page_content -->
                <div class="wpclever_settings_page_suggestion">
                    <div class="wpclever_settings_page_suggestion_label">
                        <span class="dashicons dashicons-yes-alt"></span> Suggestion
                    </div>
                    <div class="wpclever_settings_page_suggestion_content">
                        <div>
                            To display custom engaging real-time messages on any wished positions, please
                            install
                            <a href="https://wordpress.org/plugins/wpc-smart-messages/" target="_blank">WPC
                                Smart Messages</a> plugin. It's free!
                        </div>
                        <div>
                            Wanna save your precious time working on variations? Try our brand-new free plugin
                            <a href="https://wordpress.org/plugins/wpc-variation-bulk-editor/" target="_blank">WPC
                                Variation Bulk Editor</a> and
                            <a href="https://wordpress.org/plugins/wpc-variation-duplicator/" target="_blank">WPC
                                Variation Duplicator</a>.
                        </div>
                    </div>
                </div>
            </div>
            <?php
        }

        function action_links( $links, $file ) {
            static $plugin;

            if ( ! isset( $plugin ) ) {
                $plugin = plugin_basename( WOOVR_FILE );
            }

            if ( $plugin === $file ) {
                $settings             = '<a href="' . esc_url( admin_url( 'admin.php?page=wpclever-woovr&tab=settings' ) ) . '">' . esc_html__( 'Settings', 'wpc-variations-radio-buttons' ) . '</a>';
                $links['wpc-premium'] = '<a href="' . esc_url( admin_url( 'admin.php?page=wpclever-woovr&tab=premium' ) ) . '">' . esc_html__( 'Premium Version', 'wpc-variations-radio-buttons' ) . '</a>';
                array_unshift( $links, $settings );
            }

            return (array) $links;
        }

        function row_meta( $links, $file ) {
            static $plugin;

            if ( ! isset( $plugin ) ) {
                $plugin = plugin_basename( WOOVR_FILE );
            }

            if ( $plugin === $file ) {
                $row_meta = [
                        'support' => '<a href="' . esc_url( WOOVR_DISCUSSION ) . '" target="_blank">' . esc_html__( 'Community support', 'wpc-variations-radio-buttons' ) . '</a>',
                ];

                return array_merge( $links, $row_meta );
            }

            return (array) $links;
        }

        function admin_enqueue_scripts( $hook ) {
            if ( apply_filters( 'woovr_ignore_backend_scripts', false, $hook ) ) {
                return null;
            }

            wp_enqueue_style( 'woovr-backend', WOOVR_URI . 'assets/css/backend.css', [], WOOVR_VERSION );
            wp_enqueue_script( 'woovr-backend', WOOVR_URI . 'assets/js/backend.js', [ 'jquery' ], WOOVR_VERSION, true );
            wp_localize_script( 'woovr-backend', 'woovr_vars', [
                    'media_add_text' => esc_html__( 'Add to Variation', 'wpc-variations-radio-buttons' ),
                    'media_title'    => esc_html__( 'Custom Image', 'wpc-variations-radio-buttons' ),
                    'media_remove'   => esc_html__( 'Remove', 'wpc-variations-radio-buttons' )
            ] );
        }

        function product_data_tabs( $tabs ) {
            $tabs['woovr'] = [
                    'label'  => esc_html__( 'Radio Buttons', 'wpc-variations-radio-buttons' ),
                    'target' => 'woovr_settings',
                    'class'  => [ 'show_if_variable' ]
            ];

            return $tabs;
        }

        function product_data_panels() {
            global $post, $thepostid, $product_object;

            if ( $product_object instanceof WC_Product ) {
                $product_id = $product_object->get_id();
            } elseif ( is_numeric( $thepostid ) ) {
                $product_id = $thepostid;
            } elseif ( $post instanceof WP_Post ) {
                $product_id = $post->ID;
            } else {
                $product_id = 0;
            }

            if ( ! $product_id ) {
                ?>
                <div id='woovr_settings' class='panel woocommerce_options_panel woovr_table'>
                    <p style="padding: 0 12px; color: #c9356e"><?php esc_html_e( 'Product wasn\'t returned.', 'wpc-variations-radio-buttons' ); ?></p>
                </div>
                <?php
                return;
            }

            $active = get_post_meta( $product_id, '_woovr_active', true ) ?: 'default';
            ?>
            <div id='woovr_settings' class='panel woocommerce_options_panel woovr_table'>
                <div class="woovr_tr">
                    <div class="woovr_td"><?php esc_html_e( 'Active', 'wpc-variations-radio-buttons' ); ?></div>
                    <div class="woovr_td">
                        <div class="woovr_active">
                            <label>
                                <input name="_woovr_active" type="radio"
                                       value="default" <?php checked( $active, 'default' ); ?>/>
                                <?php esc_html_e( 'Default', 'wpc-variations-radio-buttons' ); ?> (<a
                                        href="<?php echo esc_url( admin_url( 'admin.php?page=wpclever-woovr&tab=settings' ) ); ?>"
                                        target="_blank"><?php esc_html_e( 'settings', 'wpc-variations-radio-buttons' ); ?></a>)
                            </label> <label>
                                <input name="_woovr_active" type="radio"
                                       value="no" <?php checked( $active, 'no' ); ?>/>
                                <?php esc_html_e( 'No', 'wpc-variations-radio-buttons' ); ?>
                            </label> <label>
                                <input name="_woovr_active" type="radio" disabled
                                       value="yes" <?php checked( $active, 'yes' ); ?>/>
                                <?php esc_html_e( 'Yes (Overwrite)', 'wpc-variations-radio-buttons' ); ?>
                            </label>
                        </div>
                        <div style="color: #c9356e; margin-top: 10px">
                            You only can use the
                            <a href="<?php echo esc_url( admin_url( 'admin.php?page=wpclever-woovr&tab=settings' ) ); ?>"
                               target="_blank">default settings</a> for all products.<br/> To overwrite for
                            individual product, please use the premium version. Click
                            <a href="https://wpclever.net/downloads/variations-radio-buttons?utm_source=pro&utm_medium=woovr&utm_campaign=wporg"
                               target="_blank">here</a> to buy, just $29.
                        </div>
                    </div>
                </div>
            </div>
            <?php
        }

        function process_product_meta( $post_id ) {
            // phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce verified by WooCommerce
            if ( isset( $_POST['_woovr_active'] ) ) {
                update_post_meta( $post_id, '_woovr_active', sanitize_text_field( wp_unslash( $_POST['_woovr_active'] ?? '' ) ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
            } else {
                delete_post_meta( $post_id, '_woovr_active' );
            }

            WPClever_Woovr::delete_cache( $post_id );
        }

        function variation_settings( $loop, $variation_data, $variation ) {
            $variation_id = $variation->ID;
            $name         = get_post_meta( $variation_id, 'woovr_name', true );
            $image        = get_post_meta( $variation_id, 'woovr_image', true );
            $image_id     = get_post_meta( $variation_id, 'woovr_image_id', true );

            echo '<div class="form-row form-row-full woovr-variation-settings">';
            echo '<label>' . esc_html__( 'WPC Variations Radio Buttons', 'wpc-variations-radio-buttons' ) . '</label>';
            echo '<div class="woovr-variation-wrap">';

            echo '<p class="form-field form-row">';
            echo '<label>' . esc_html__( 'Custom name', 'wpc-variations-radio-buttons' ) . '</label>';
            echo '<input type="text" class="woovr_name" name="' . esc_attr( 'woovr_name[' . $variation_id . ']' ) . '" value="' . esc_attr( $name ) . '"/>';
            echo '</p>';

            echo '<p class="form-field form-row woovr_custom_image">';
            echo '<label>' . esc_html__( 'Custom image', 'wpc-variations-radio-buttons' ) . '</label>';
            echo '<span class="woovr_image_selector">';
            echo '<input type="hidden" class="woovr_image_id" name="' . esc_attr( 'woovr_image_id[' . $variation_id . ']' ) . '" value="' . esc_attr( $image_id ) . '"/>';

            if ( $image_id ) {
                echo '<span class="woovr_image_preview">' . wp_get_attachment_image( $image_id ) . '<a class="woovr_image_remove button" href="#">' . esc_html__( 'Remove', 'wpc-variations-radio-buttons' ) . '</a></span>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
            } else {
                echo '<span class="woovr_image_preview">' . wc_placeholder_img() . '</span>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
            }

            echo '<a href="#" class="woovr_image_add button" rel="' . esc_attr( $variation_id ) . '">' . esc_html__( 'Choose Image', 'wpc-variations-radio-buttons' ) . '</a>';
            echo '</span>';
            echo '</p>';

            echo '<p class="form-field form-row">';
            echo '<label>' . esc_html__( '- OR - Custom image URL', 'wpc-variations-radio-buttons' ) . '</label>';
            echo '<input type="url" class="woovr_image_url" name="' . esc_attr( 'woovr_image[' . $variation_id . ']' ) . '" value="' . esc_attr( $image ) . '"/>';
            echo '</p>';

            echo '</div></div>';
        }

        function save_variation_settings( $post_id ) {
            // phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce verified by WooCommerce
            if ( isset( $_POST['woovr_name'][ $post_id ] ) ) {
                update_post_meta( $post_id, 'woovr_name', sanitize_text_field( wp_unslash( ( $_POST['woovr_name'] ?? [] )[ $post_id ] ?? '' ) ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
            } else {
                delete_post_meta( $post_id, 'woovr_name' );
            }

            // phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce verified by WooCommerce
            if ( isset( $_POST['woovr_image'][ $post_id ] ) ) {
                update_post_meta( $post_id, 'woovr_image', sanitize_url( wp_unslash( ( $_POST['woovr_image'] ?? [] )[ $post_id ] ?? '' ) ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
            } else {
                delete_post_meta( $post_id, 'woovr_image' );
            }

            // phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce verified by WooCommerce
            if ( isset( $_POST['woovr_image_id'][ $post_id ] ) ) {
                update_post_meta( $post_id, 'woovr_image_id', sanitize_text_field( wp_unslash( ( $_POST['woovr_image_id'] ?? [] )[ $post_id ] ?? '' ) ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
            } else {
                delete_post_meta( $post_id, 'woovr_image_id' );
            }
        }

        function bulk_update_variation( $variation_id, $fields ) {
            if ( ! empty( $fields['woovr_name'] ) ) {
                update_post_meta( $variation_id, 'woovr_name', sanitize_text_field( $fields['woovr_name'] ) );
            }

            if ( ! empty( $fields['woovr_image'] ) ) {
                update_post_meta( $variation_id, 'woovr_image', sanitize_text_field( $fields['woovr_image'] ) );
            }

            if ( ! empty( $fields['woovr_image_id'] ) ) {
                update_post_meta( $variation_id, 'woovr_image_id', sanitize_text_field( $fields['woovr_image_id'] ) );
            }
        }

        function duplicate_variation( $old_variation_id, $new_variation_id ) {
            if ( $name = get_post_meta( $old_variation_id, 'woovr_name', true ) ) {
                update_post_meta( $new_variation_id, 'woovr_name', $name );
            }

            if ( $image = get_post_meta( $old_variation_id, 'woovr_image', true ) ) {
                update_post_meta( $new_variation_id, 'woovr_image', $image );
            }

            if ( $image_id = get_post_meta( $old_variation_id, 'woovr_image_id', true ) ) {
                update_post_meta( $new_variation_id, 'woovr_image_id', $image_id );
            }
        }

        public function sanitize_array( $arr ) {
            foreach ( (array) $arr as $k => $v ) {
                if ( is_array( $v ) ) {
                    $arr[ $k ] = $this->sanitize_array( $v );
                } else {
                    $arr[ $k ] = sanitize_post_field( 'post_content', $v, 0, 'db' );
                }
            }

            return $arr;
        }
    }
}
