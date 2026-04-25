<?php
/**
 * WPK_Settings — admin settings page for WP Passkey.
 *
 * Registers the "Settings > WP Passkey" submenu and all option fields.
 * All options are prefixed `wpk_`.
 *
 * REDESIGNED UI (v1.1) — keeps all original options/sanitize logic intact;
 * only the rendered HTML and the wpk-admin.css stylesheet have changed.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class WPK_Settings {

    const PAGE_SLUG    = 'wp-passkeys';
    const OPTION_GROUP = 'wpk_settings';

    public function __construct() {
        add_action( 'admin_menu',            array( $this, 'register_menu' ) );
        add_action( 'admin_init',            array( $this, 'register_settings' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_settings_assets' ) );
    }

    // ──────────────────────────────────────────────────────────
    // Menu
    // ──────────────────────────────────────────────────────────

    public function register_menu(): void {
        add_options_page(
            __( 'WP Passkey', 'wp-passkeys' ),
            __( 'WP Passkey', 'wp-passkeys' ),
            'manage_options',
            self::PAGE_SLUG,
            array( $this, 'render_settings_page' )
        );
    }

    // ──────────────────────────────────────────────────────────
    // Assets
    // ──────────────────────────────────────────────────────────

    public function enqueue_settings_assets( string $hook ): void {
        if ( $hook !== 'settings_page_wp-passkeys' ) {
            return;
        }
        wp_enqueue_style( 'wpk-admin', WPK_PLUGIN_URL . 'admin/css/wpk-admin.css', array(), WPK_VERSION );
    }

    // ──────────────────────────────────────────────────────────
    // Settings registration  (UNCHANGED from v1.0)
    // ──────────────────────────────────────────────────────────

    public function register_settings(): void {

        // ── General ────────────────────────────────────────────
        add_settings_section( 'wpk_general', __( 'General', 'wp-passkeys' ), array( $this, 'section_intro_general' ), self::PAGE_SLUG );

        register_setting( self::OPTION_GROUP, 'wpk_enabled', array(
            'type'              => 'integer',
            'default'           => 1,
            'sanitize_callback' => 'absint',
        ) );
        add_settings_field( 'wpk_enabled', __( 'Enable Passkeys', 'wp-passkeys' ),
            array( $this, 'field_checkbox' ), self::PAGE_SLUG, 'wpk_general',
            array( 'option' => 'wpk_enabled', 'description' => __( 'Allow users to register and sign in with passkeys.', 'wp-passkeys' ) )
        );

        register_setting( self::OPTION_GROUP, 'wpk_show_separator', array(
            'type'              => 'integer',
            'default'           => 1,
            'sanitize_callback' => 'absint',
        ) );
        add_settings_field( 'wpk_show_separator', __( 'Show "OR" Separator', 'wp-passkeys' ),
            array( $this, 'field_checkbox' ), self::PAGE_SLUG, 'wpk_general',
            array( 'option' => 'wpk_show_separator', 'description' => __( 'Display the "OR" divider line above the passkey button on the login page.', 'wp-passkeys' ) )
        );

        register_setting( self::OPTION_GROUP, 'wpk_eligible_roles', array(
            'type'              => 'array',
            'default'           => array( 'administrator' ),
            'sanitize_callback' => array( $this, 'sanitize_roles' ),
        ) );
        add_settings_field( 'wpk_eligible_roles', __( 'Eligible Roles', 'wp-passkeys' ),
            array( $this, 'field_roles' ), self::PAGE_SLUG, 'wpk_general',
            array( 'option' => 'wpk_eligible_roles', 'description' => __( 'Which user roles may register and use passkeys.', 'wp-passkeys' ) )
        );

        register_setting( self::OPTION_GROUP, 'wpk_max_passkeys_per_user', array(
            'type'              => 'integer',
            'default'           => WPK_Passkeys::LITE_MAX_PASSKEYS,
            'sanitize_callback' => array( $this, 'sanitize_max_passkeys' ),
        ) );
        add_settings_field( 'wpk_max_passkeys_per_user', __( 'Passkeys per User', 'wp-passkeys' ),
            array( $this, 'field_max_passkeys' ), self::PAGE_SLUG, 'wpk_general',
            array( 'option' => 'wpk_max_passkeys_per_user' )
        );

        // ── Security ───────────────────────────────────────────
        add_settings_section( 'wpk_security', __( 'Security', 'wp-passkeys' ), array( $this, 'section_intro_security' ), self::PAGE_SLUG );

        register_setting( self::OPTION_GROUP, 'wpk_user_verification', array(
            'type'              => 'string',
            'default'           => 'required',
            'sanitize_callback' => array( $this, 'sanitize_user_verification' ),
        ) );
        add_settings_field( 'wpk_user_verification', __( 'User Verification', 'wp-passkeys' ),
            array( $this, 'field_user_verification' ), self::PAGE_SLUG, 'wpk_security',
            array( 'option' => 'wpk_user_verification' )
        );

        // ── Rate Limiting ──────────────────────────────────────
        add_settings_section( 'wpk_rate_limiting', __( 'Rate Limiting', 'wp-passkeys' ), array( $this, 'section_intro_rate' ), self::PAGE_SLUG );

        register_setting( self::OPTION_GROUP, 'wpk_rate_window', array(
            'type'              => 'integer',
            'default'           => 300,
            'sanitize_callback' => array( $this, 'sanitize_rate_window' ),
        ) );
        add_settings_field( 'wpk_rate_window', __( 'Failure Window (seconds)', 'wp-passkeys' ),
            array( $this, 'field_number' ), self::PAGE_SLUG, 'wpk_rate_limiting',
            array( 'option' => 'wpk_rate_window', 'min' => 60, 'max' => 3600, 'default' => 300,
                   'description' => __( 'Time window in which failures are counted before a lockout is triggered.', 'wp-passkeys' ) )
        );

        register_setting( self::OPTION_GROUP, 'wpk_rate_max_attempts', array(
            'type'              => 'integer',
            'default'           => 8,
            'sanitize_callback' => array( $this, 'sanitize_rate_attempts' ),
        ) );
        add_settings_field( 'wpk_rate_max_attempts', __( 'Max Failures Before Lockout', 'wp-passkeys' ),
            array( $this, 'field_number' ), self::PAGE_SLUG, 'wpk_rate_limiting',
            array( 'option' => 'wpk_rate_max_attempts', 'min' => 1, 'max' => 50, 'default' => 8,
                   'description' => __( 'Number of failures allowed within the window before the IP/user is locked out.', 'wp-passkeys' ) )
        );

        register_setting( self::OPTION_GROUP, 'wpk_rate_lockout', array(
            'type'              => 'integer',
            'default'           => 900,
            'sanitize_callback' => array( $this, 'sanitize_rate_lockout' ),
        ) );
        add_settings_field( 'wpk_rate_lockout', __( 'Lockout Duration (seconds)', 'wp-passkeys' ),
            array( $this, 'field_number' ), self::PAGE_SLUG, 'wpk_rate_limiting',
            array( 'option' => 'wpk_rate_lockout', 'min' => 60, 'max' => 86400, 'default' => 900,
                   'description' => __( 'How long an IP or user is locked out after exceeding the failure threshold.', 'wp-passkeys' ) )
        );

        // ── Advanced ───────────────────────────────────────────
        add_settings_section( 'wpk_advanced', __( 'Advanced', 'wp-passkeys' ), array( $this, 'section_intro_advanced' ), self::PAGE_SLUG );

        register_setting( self::OPTION_GROUP, 'wpk_rp_name', array(
            'type'              => 'string',
            'default'           => '',
            'sanitize_callback' => 'sanitize_text_field',
        ) );
        add_settings_field( 'wpk_rp_name', __( 'Relying Party Name', 'wp-passkeys' ),
            array( $this, 'field_text' ), self::PAGE_SLUG, 'wpk_advanced',
            array( 'option' => 'wpk_rp_name', 'placeholder' => get_bloginfo( 'name' ),
                   'description' => __( 'Your site\'s display name, sent to the authenticator during passkey registration. Some platforms (e.g. Chrome on Windows, Android) show this in the "Create a passkey" dialog. Note: the sign-in prompt always shows your domain (RP ID) — that is a browser/OS requirement and cannot be changed. Defaults to site name.', 'wp-passkeys' ) )
        );
    }

    // Section intros (small descriptive paragraphs above each section)
    public function section_intro_general(): void {
        echo '<p>' . esc_html__( 'Core behavior and which users can use passkeys.', 'wp-passkeys' ) . '</p>';
    }
    public function section_intro_security(): void {
        echo '<p>' . esc_html__( 'Choose how strictly users must prove their presence.', 'wp-passkeys' ) . '</p>';
    }
    public function section_intro_rate(): void {
        echo '<p>' . esc_html__( 'Protects against brute-force attacks. Defaults are secure for most sites.', 'wp-passkeys' ) . '</p>';
    }
    public function section_intro_advanced(): void {
        echo '<p>' . esc_html__( 'Leave blank to use site defaults. These can also be set via PHP constants (WPK_RP_ID, WPK_RP_NAME) in wp-config.php. Note: browsers always show your domain in sign-in prompts — this is a WebAuthn spec requirement.', 'wp-passkeys' ) . '</p>';
    }

    // ──────────────────────────────────────────────────────────
    // Page render  (REDESIGNED)
    // ──────────────────────────────────────────────────────────

    public function render_settings_page(): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }
        $is_enabled = (int) get_option( 'wpk_enabled', 1 ) === 1;
        ?>
        <div class="wrap wpk-settings-wrap">

            <header class="wpk-page-header">
                <h1>
                    <?php esc_html_e( 'WP Passkey', 'wp-passkeys' ); ?>
                    <?php if ( $is_enabled ) : ?>
                        <span class="wpk-status-pill"><?php esc_html_e( 'Active', 'wp-passkeys' ); ?></span>
                    <?php endif; ?>
                </h1>
                <p class="wpk-tagline">
                    <?php esc_html_e( 'Passwordless login for WordPress — powered by WebAuthn / FIDO2.', 'wp-passkeys' ); ?>
                </p>
            </header>

            <?php settings_errors( self::OPTION_GROUP ); ?>

            <div class="wpk-settings-body">

                <div class="wpk-settings-main">
                    <div class="wpk-settings-card">
                        <div class="wpk-settings-card__header">
                            <h2><?php esc_html_e( 'Configuration', 'wp-passkeys' ); ?></h2>
                            <p><?php esc_html_e( 'Control how passkeys behave across your site.', 'wp-passkeys' ); ?></p>
                        </div>

                        <form method="post" action="options.php">
                            <?php
                            settings_fields( self::OPTION_GROUP );
                            do_settings_sections( self::PAGE_SLUG );
                            submit_button( __( 'Save changes', 'wp-passkeys' ) );
                            ?>
                        </form>
                    </div>
                </div>

                <aside class="wpk-settings-sidebar">
                    <?php $this->render_pro_card(); ?>
                    <?php $this->render_quick_setup_card(); ?>
                    <?php $this->render_compatibility_card(); ?>
                </aside>

            </div>
        </div>
        <?php
    }

    // ──────────────────────────────────────────────────────────
    // Sidebar cards (REDESIGNED markup)
    // ──────────────────────────────────────────────────────────

    private function render_pro_card(): void {
        $features = array(
            __( 'Unlimited passkeys per user', 'wp-passkeys' ),
            __( 'Passkey-only mode by role', 'wp-passkeys' ),
            __( 'Magic link recovery flow', 'wp-passkeys' ),
            __( 'WooCommerce checkout integration', 'wp-passkeys' ),
            __( 'Gutenberg & Elementor blocks', 'wp-passkeys' ),
            __( 'Device health dashboard', 'wp-passkeys' ),
            __( 'Audit log with export', 'wp-passkeys' ),
            __( 'Conditional access rules', 'wp-passkeys' ),
            __( 'WP-CLI support', 'wp-passkeys' ),
            __( 'White-label & agency tools', 'wp-passkeys' ),
        );
        ?>
        <div class="wpk-card wpk-card-pro">
            <h3>
                <span aria-hidden="true">✨</span>
                <?php esc_html_e( 'WP Passkey Pro', 'wp-passkeys' ); ?>
            </h3>
            <p class="description">
                <?php esc_html_e( 'Upgrade to unlock everything you need to deploy passkeys at scale.', 'wp-passkeys' ); ?>
            </p>
            <ul class="wpk-pro-features">
                <?php foreach ( $features as $f ) : ?>
                    <li>✓ <?php echo esc_html( $f ); ?></li>
                <?php endforeach; ?>
            </ul>
            <a href="https://wppasskey.com/pro" class="button button-primary wpk-btn-pro" target="_blank" rel="noopener noreferrer">
                <?php esc_html_e( 'Get Pro — from $79/year', 'wp-passkeys' ); ?>
            </a>
            <p style="text-align:center;margin:8px 0 0;font-size:11px;color:var(--wpk-pro-deep);opacity:.7;">
                <?php esc_html_e( '30-day money-back guarantee', 'wp-passkeys' ); ?>
            </p>
        </div>
        <?php
    }

    private function render_quick_setup_card(): void {
        ?>
        <div class="wpk-card">
            <h3><?php esc_html_e( 'Quick setup', 'wp-passkeys' ); ?></h3>
            <ol class="wpk-setup-steps">
                <li><?php esc_html_e( 'Enable passkeys above and save.', 'wp-passkeys' ); ?></li>
                <li>
                    <?php
                    printf(
                        /* translators: %s is a link to the user profile */
                        wp_kses(
                            __( 'Go to <a href="%s">Your Profile</a> and register your first passkey.', 'wp-passkeys' ),
                            array( 'a' => array( 'href' => array() ) )
                        ),
                        esc_url( admin_url( 'profile.php#wpk-profile-section' ) )
                    );
                    ?>
                </li>
                <li><?php esc_html_e( 'Sign out and use the "Sign in with Passkey" button on the login page.', 'wp-passkeys' ); ?></li>
                <li><?php esc_html_e( 'Register a backup passkey on a second device.', 'wp-passkeys' ); ?></li>
            </ol>
        </div>
        <?php
    }

    private function render_compatibility_card(): void {
        // Inline SVG brand icons — no external requests, no emoji font dependency.
        $apple_icon = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M7 3H5a2 2 0 0 0-2 2v2"/><path d="M17 3h2a2 2 0 0 1 2 2v2"/><path d="M7 21H5a2 2 0 0 1-2-2v-2"/><path d="M17 21h2a2 2 0 0 0 2-2v-2"/><path d="M9 9h.01"/><path d="M15 9h.01"/><path d="M9.5 14.5a3.5 3.5 0 0 0 5 0"/><line x1="12" y1="7" x2="12" y2="9"/></svg>';

        $windows_icon = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 88 88" fill="currentColor" aria-hidden="true"><path d="M0 12.4L35.7 7.6l.002 34.43-35.66.204L0 12.4zm35.67 33.62l.003 34.44L.002 75.6l-.001-29.82 35.67.242zM40.29 6.882L87.986 0v41.677l-47.695.378.001-35.173zm47.699 36.739l-.011 41.644-47.695-6.672-.066-35.052 47.772.08z"/></svg>';

        $yubikey_icon = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100" fill="none" stroke="currentColor" stroke-width="6" aria-hidden="true"><rect x="30" y="8" width="40" height="60" rx="8"/><circle cx="50" cy="35" r="10"/><rect x="44" y="68" width="12" height="24" rx="4"/></svg>';

        $icloud_icon = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M19.35 10.04A7.49 7.49 0 0 0 12 4C9.11 4 6.6 5.64 5.35 8.04A5.994 5.994 0 0 0 0 14c0 3.31 2.69 6 6 6h13c2.76 0 5-2.24 5-5 0-2.64-2.05-4.78-4.65-4.96z"/></svg>';

        $android_icon = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M6 18c0 .55.45 1 1 1h1v3.5c0 .83.67 1.5 1.5 1.5s1.5-.67 1.5-1.5V19h2v3.5c0 .83.67 1.5 1.5 1.5s1.5-.67 1.5-1.5V19h1c.55 0 1-.45 1-1V8H6v10zm-2.5-1C2.67 17 2 17.67 2 18.5v-9C2 8.67 2.67 8 3.5 8S5 8.67 5 9.5v9c0 .83-.67 1.5-1.5 1.5zm17 0c-.83 0-1.5-.67-1.5-1.5v-9C19 8.67 19.67 8 20.5 8S22 8.67 22 9.5v9c0 .83-.67 1.5-1.5 1.5zM15.53 2.16l1.3-1.3c.2-.2.2-.51 0-.71-.2-.2-.51-.2-.71 0l-1.48 1.48A5.952 5.952 0 0 0 12 1c-.96 0-1.86.23-2.66.63L7.88.15c-.2-.2-.51-.2-.71 0-.2.2-.2.51 0 .71l1.31 1.31A5.965 5.965 0 0 0 6 7h12a5.96 5.96 0 0 0-2.47-4.84zM10 5H9V4h1v1zm5 0h-1V4h1v1z"/></svg>';

        $items = array(
            array( $apple_icon,   __( 'Face ID / Touch ID', 'wp-passkeys' ),      __( 'iPhone, iPad, Mac', 'wp-passkeys' ) ),
            array( $windows_icon, __( 'Windows Hello', 'wp-passkeys' ),            __( 'Windows 10 / 11', 'wp-passkeys' ) ),
            array( $yubikey_icon, __( 'Hardware security keys', 'wp-passkeys' ),   __( 'YubiKey, Titan &amp; others', 'wp-passkeys' ) ),
            array( $icloud_icon,  __( 'Cloud password managers', 'wp-passkeys' ),  __( 'iCloud Keychain, Google', 'wp-passkeys' ) ),
            array( $android_icon, __( 'Android biometrics', 'wp-passkeys' ),       __( 'Fingerprint, Face unlock', 'wp-passkeys' ) ),
        );

        $svg_allowed = array(
            'svg'     => array( 'xmlns' => true, 'viewbox' => true, 'fill' => true, 'stroke' => true, 'stroke-width' => true, 'stroke-linecap' => true, 'stroke-linejoin' => true, 'aria-hidden' => true ),
            'path'    => array( 'd' => true, 'fill' => true, 'stroke' => true ),
            'rect'    => array( 'x' => true, 'y' => true, 'width' => true, 'height' => true, 'rx' => true, 'fill' => true ),
            'circle'  => array( 'cx' => true, 'cy' => true, 'r' => true, 'fill' => true ),
            'line'    => array( 'x1' => true, 'y1' => true, 'x2' => true, 'y2' => true, 'stroke' => true ),
        );
        ?>
        <div class="wpk-card">
            <h3><?php esc_html_e( 'Compatibility', 'wp-passkeys' ); ?></h3>
            <ul class="wpk-compat">
                <?php foreach ( $items as $item ) : ?>
                    <li>
                        <span class="wpk-compat__icon" aria-hidden="true"><?php echo wp_kses( $item[0], $svg_allowed ); ?></span>
                        <span>
                            <span class="wpk-compat__label"><?php echo esc_html( $item[1] ); ?></span>
                            <span class="wpk-compat__note"><?php echo wp_kses( $item[2], array() ); ?></span>
                        </span>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>
        <?php
    }

    // ──────────────────────────────────────────────────────────
    // Field renderers  (UNCHANGED logic, classes used by new CSS)
    // ──────────────────────────────────────────────────────────

    public function field_checkbox( array $args ): void {
        $value = (int) get_option( $args['option'], 1 );
        printf(
            '<label><input type="checkbox" name="%s" value="1"%s> %s</label>',
            esc_attr( $args['option'] ),
            checked( 1, $value, false ),
            isset( $args['description'] ) ? esc_html( $args['description'] ) : ''
        );
    }

    public function field_roles( array $args ): void {
        $saved = (array) get_option( $args['option'], array( 'administrator' ) );
        $roles = wp_roles()->get_names();
        echo '<fieldset>';
        foreach ( $roles as $role_key => $role_name ) {
            $checked = in_array( $role_key, $saved, true ) ? ' checked' : '';
            printf(
                '<label><input type="checkbox" name="%s[]" value="%s"%s> %s</label>',
                esc_attr( $args['option'] ),
                esc_attr( $role_key ),
                $checked,
                esc_html( translate_user_role( $role_name ) )
            );
        }
        echo '</fieldset>';
        if ( ! empty( $args['description'] ) ) {
            echo '<p class="description">' . esc_html( $args['description'] ) . '</p>';
        }
    }

    public function field_max_passkeys( array $args ): void {
        $value = (int) get_option( $args['option'], WPK_Passkeys::LITE_MAX_PASSKEYS );
        $value = max( 1, min( WPK_Passkeys::LITE_MAX_PASSKEYS, $value ) );
        printf(
            '<input type="number" name="%s" id="%s" value="%d" min="1" max="%d" class="small-text"> <span class="description" style="margin-left:8px;">%s</span>',
            esc_attr( $args['option'] ),
            esc_attr( $args['option'] ),
            $value,
            WPK_Passkeys::LITE_MAX_PASSKEYS,
            sprintf(
                /* translators: %d lite max */
                esc_html__( 'of %d (Lite limit)', 'wp-passkeys' ),
                WPK_Passkeys::LITE_MAX_PASSKEYS
            )
        );
        echo '<p class="description">' .
            sprintf(
                esc_html__( 'Maximum number of passkeys a single user may register (Lite: 1–%d). Pro removes this cap.', 'wp-passkeys' ),
                WPK_Passkeys::LITE_MAX_PASSKEYS
            ) .
            '</p>';
    }

    public function field_user_verification( array $args ): void {
        $value   = (string) get_option( $args['option'], 'required' );
        $options = array(
            'required'    => __( 'Required — biometric/PIN always requested (recommended)', 'wp-passkeys' ),
            'preferred'   => __( 'Preferred — biometric requested where available', 'wp-passkeys' ),
            'discouraged' => __( 'Discouraged — presence-only (not recommended)', 'wp-passkeys' ),
        );
        echo '<select name="' . esc_attr( $args['option'] ) . '" id="' . esc_attr( $args['option'] ) . '" style="min-width:380px;">';
        foreach ( $options as $k => $label ) {
            printf(
                '<option value="%s"%s>%s</option>',
                esc_attr( $k ),
                selected( $value, $k, false ),
                esc_html( $label )
            );
        }
        echo '</select>';
    }

    public function field_number( array $args ): void {
        $default = $args['default'] ?? 0;
        $value   = (int) get_option( $args['option'], $default );
        printf(
            '<input type="number" name="%s" id="%s" value="%d" min="%d" max="%d" class="small-text">',
            esc_attr( $args['option'] ),
            esc_attr( $args['option'] ),
            $value,
            $args['min'] ?? 0,
            $args['max'] ?? 9999
        );
        if ( ! empty( $args['description'] ) ) {
            echo '<p class="description">' . esc_html( $args['description'] ) . '</p>';
        }
    }

    public function field_text( array $args ): void {
        $value = (string) get_option( $args['option'], '' );
        printf(
            '<input type="text" name="%s" id="%s" value="%s" placeholder="%s" class="regular-text">',
            esc_attr( $args['option'] ),
            esc_attr( $args['option'] ),
            esc_attr( $value ),
            esc_attr( $args['placeholder'] ?? '' )
        );
        if ( ! empty( $args['description'] ) ) {
            echo '<p class="description">' . esc_html( $args['description'] ) . '</p>';
        }
    }

    // ──────────────────────────────────────────────────────────
    // Sanitize callbacks  (UNCHANGED)
    // ──────────────────────────────────────────────────────────

    public function sanitize_roles( $value ): array {
        if ( ! is_array( $value ) ) {
            return array( 'administrator' );
        }
        $valid = array_keys( wp_roles()->get_names() );
        $clean = array_intersect( array_map( 'sanitize_key', $value ), $valid );
        return ! empty( $clean ) ? array_values( $clean ) : array( 'administrator' );
    }

    public function sanitize_max_passkeys( $value ): int {
        return max( 1, min( WPK_Passkeys::LITE_MAX_PASSKEYS, (int) $value ) );
    }

    public function sanitize_user_verification( $value ): string {
        return in_array( $value, array( 'required', 'preferred', 'discouraged' ), true ) ? $value : 'required';
    }

    public function sanitize_rate_window( $value ): int {
        return max( 60, min( 3600, (int) $value ) );
    }

    public function sanitize_rate_attempts( $value ): int {
        return max( 1, min( 50, (int) $value ) );
    }

    public function sanitize_rate_lockout( $value ): int {
        return max( 60, min( 86400, (int) $value ) );
    }
}
