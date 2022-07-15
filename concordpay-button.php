<?php
/**
 * Plugin Name:  ConcordPay Button
 * Plugin URI:   https://concordpay.concord.ua/
 * Description:  This plugin allows you to create a button that lets the customers pay via ConcordPay.
 * Version:      1.1.2
 * Author:       MustPay
 * Author URI:   https://mustpay.tech
 * Domain Path:  /lang
 * Text Domain:  concordpay-button
 * License:      GPLv3
 * License URI:  http://www.gnu.org/licenses/gpl-3.0.html
 *
 * @package      concordpay-button
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Variables for translate plugin header.
$plugin_name        = esc_html__( 'ConcordPay Button', 'concordpay-button' );
$plugin_description = esc_html__( 'This plugin allows you to create a button that lets the customers pay via ConcordPay.', 'concordpay-button' );

// Plugin management methods.
register_activation_hook( __FILE__, array( 'ConcordPay_Button', 'cpb_activate' ) );
register_deactivation_hook( __FILE__, array( 'ConcordPay_Button', 'cpb_deactivate' ) );
register_uninstall_hook( __FILE__, array( 'ConcordPay_Button', 'cpb_uninstall' ) );
require_once 'ConcordPayApi.php';

add_action( 'plugins_loaded', array( 'ConcordPay_Button', 'cpb_init' ), 0 );

/**
 * ConcordPay_Button class.
 */
class ConcordPay_Button {

	public const CPB_PLUGIN_VERSION = '1.1.0';

	public const CPB_MODE_NONE        = 'none';
	public const CPB_MODE_PHONE       = 'phone';
	public const CPB_MODE_EMAIL       = 'email';
	public const CPB_MODE_PHONE_EMAIL = 'phone_email';

	/**
	 * Plugin instance.
	 *
	 * @var ConcordPay_Button
	 */
	protected static $instance;

	/**
	 * List of Plugin settings params.
	 *
	 * @var array
	 */
	protected $fillable = array(
		'merchant_id',
		'secret_key',
		'currency',
		'order_prefix',
		'language',
		'mode',
	);

	protected $checkout_params = array(
		self::CPB_MODE_NONE => array(
			'_wpnonce',
			'cpb_product_name',
			'cpb_product_price',
		),
		self::CPB_MODE_PHONE => array(
			'_wpnonce',
			'cpb_client_name',
			'cpb_phone',
			'cpb_product_name',
			'cpb_product_price',
		),
		self::CPB_MODE_EMAIL => array(
			'_wpnonce',
			'cpb_client_name',
			'cpb_email',
			'cpb_product_name',
			'cpb_product_price',
		),
		self::CPB_MODE_PHONE_EMAIL => array(
			'_wpnonce',
			'cpb_client_name',
			'cpb_phone',
			'cpb_email',
			'cpb_product_name',
			'cpb_product_price',
		),
	);

	/**
	 * Create plugin instance.
	 *
	 * @return ConcordPay_Button
	 */
	public static function cpb_init() {
		is_null( self::$instance ) && self::$instance = new self();
		return self::$instance;
	}

	/**
	 * Constructor method.
	 */
	public function __construct() {
		// Load plugin translations.
		load_plugin_textdomain( 'concordpay-button', false, basename( __DIR__ ) . '/lang' );

		// Register Gutenberg block.
		add_action( 'init', array( $this, 'cpb_register_gutenberg_block' ) );

		// Translation block script file.
		add_action( 'init', array( $this, 'cpb_set_script_translations' ) );

		// Plugin front scripts and styles.
		add_action( 'wp_enqueue_scripts', array( $this, 'cpb_link_front_scripts' ), 500 );

		// Plugin settings page styles.
		add_action( 'admin_enqueue_scripts', array( $this, 'cpb_link_admin_styles' ), 500 );

		// Settings page menu link.
		add_action( 'admin_menu', array( $this, 'cpb_plugin_menu' ) );

		self::add_media_button_on_editor_page();

		// Settings plugin link in plugin list.
		add_filter( 'plugin_action_links', array( $this, 'cpb_plugin_settings_link' ), 10, 2 );

		// Support plugin link in plugin list.
		$plugin = plugin_basename( __FILE__ );
		add_filter( "plugin_action_links_{$plugin}", array( $this, 'cpb_plugin_support_link' ) );

		// Shortcode handler.
		add_shortcode( 'cpb', array( $this, 'cpb_make_button_from_shortcode' ) );

		// Show payment result message.
		if ( isset( $_GET['concordpay_result'] ) && ! is_admin() ) {
			add_filter( 'wp_head', array( $this, 'cpb_show_payment_result_message' ) );
		}

		// Add popup checkout form.
		add_action( 'wp_footer', array( $this, 'cpb_checkout_form' ) );

		// Ajax popup handler.
		if ( wp_doing_ajax() ) {
			add_action( 'wp_ajax_popup_handler', array( $this, 'cpb_payment_handler' ) );
			add_action( 'wp_ajax_nopriv_popup_handler', array( $this, 'cpb_payment_handler' ) );
		}
	}

	/**
	 * Add media button on editor page.
	 *
	 * @return void
	 */
	public static function add_media_button_on_editor_page() {
		global $pagenow, $typenow;

		if ( 'download' !== $typenow && in_array( $pagenow, array( 'post.php', 'page.php', 'post-new.php', 'post-edit.php' ), true ) ) {

			add_action( 'media_buttons', array( self::class, 'cpb_add_my_media_button' ), 20 );
			add_action( 'admin_footer', array( self::class, 'cpb_add_inline_popup_content' ) );
		}
	}

	/**
	 * Add media button.
	 *
	 * @return void
	 */
	public function cpb_add_my_media_button() {
		echo '<a href="#TB_inline?width=600&height=400&inlineId=cpb_popup_container" title="ConcordPay Button" id="insert-my-media" class="button thickbox">ConcordPay Button</a>';
	}

	/**
	 * Add inline popup content.
	 *
	 * @return void
	 */
	public static function cpb_add_inline_popup_content() {
	}

	/**
	 * Activate plugin method.
	 *
	 * @return void
	 */
	public static function cpb_activate() {

		if ( ! current_user_can( 'activate_plugins' ) ) {
			return;
		}
		$plugin = isset( $_REQUEST['plugin'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['plugin'] ) ) : '';
		check_admin_referer( "activate-plugin_{$plugin}" );

		$cpb_settings = array(
			'merchant_id'  => '',
			'secret_key'   => '',
			'currency'     => 'UAH',
			'order_prefix' => 'cpb',
			'language'     => 'uk',
			'mode'         => self::CPB_MODE_PHONE,
		);

		add_option( 'cpb_settings', $cpb_settings );
	}

	/**
	 * Deactivate plugin method.
	 *
	 * @return void
	 */
	public static function cpb_deactivate() {
		if ( ! current_user_can( 'activate_plugins' ) ) {
			return;
		}

		$plugin = isset( $_REQUEST['plugin'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['plugin'] ) ) : '';
		check_admin_referer( "deactivate-plugin_{$plugin}" );
	}

	/**
	 * Uninstall plugin method.
	 *
	 * @return void
	 */
	public static function cpb_uninstall() {
	}

	/**
	 * Adds menu item to the settings.
	 *
	 * @return void
	 */
	public function cpb_plugin_menu() {
		$title = __( 'ConcordPay Button', 'concordpay-button' );
		add_options_page( $title, $title, 'manage_options', 'cpb-settings', array( $this, 'cpb_plugin_options' ) );
	}

	/**
	 * Adds ConcordPay plugin settings link in plugin list.
	 *
	 * @param array  $links Plugin links in menu list.
	 * @param string $file Plugin file path.
	 *
	 * @return array
	 */
	public function cpb_plugin_settings_link( $links, $file ) {
		static $this_plugin;

		if ( ! $this_plugin ) {
			$this_plugin = plugin_basename( __FILE__ );
		}

		if ( $file === $this_plugin ) {
			$settings_label = __( 'Settings', 'concordpay-button' );
			$settings_link  = "<a href='" . get_bloginfo( 'wpurl' ) . "/wp-admin/admin.php?page=cpb-settings'>{$settings_label}</a>";
			array_unshift( $links, $settings_link );
		}

		return $links;
	}

	/**
	 * Adds Concordpay support link in plugin list.
	 *
	 * @param array $links Plugin links in menu list.
	 *
	 * @return array
	 */
	public function cpb_plugin_support_link( $links ): array {
		unset( $links['edit'] );

		$links[] = '<a target="_blank" href="https://t.me/ConcordPaySupport">' . __( 'Support', 'concordpay-button' ) . '</a>';

		return $links;
	}

	/**
	 * Render plugin options page.
	 *
	 * @return void
	 */
	public function cpb_plugin_options() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'concordpay-button' ) );
		}

		// Settings page.
		echo '<div class="cpb-wrapper">';
		echo '<div class="cpb-header">';
		echo '<h1>' . esc_attr__( 'ConcordPay Button Settings', 'concordpay-button' ) . '</h1>';
		echo '</div>';
		echo '<div class="cpb-main"></div>';
		echo '</div>';

		echo '<table>';
		echo '<tr>';
		echo '<td class="cpb-td">';

		echo '<form method="POST" action="' . esc_attr( wp_unslash( $_SERVER['REQUEST_URI'] ) ) . '">';

		// Save and update options.
		if ( isset( $_POST['update'] ) ) {

			$this->cpb_update_options();

			echo "<br /><div class='updated'>";
			echo '<p><strong>' . esc_attr__( 'Settings updated', 'concordpay-button' ) . '</strong></p>';
			echo '</div>';
		}

		$settings = self::cpb_get_settings();

		echo '</td><td></td></tr><tr><td>';

		// Form.
		echo '<br />';
		?>

	  <div class="cpb-section-header" id="cpb-section-header"><?php _e( 'Usage', 'concordpay-button' ); ?></div>
	  <div class="cpb-section">
		<p>
		  <?php esc_html_e( 'By using this you can create shortcodes which will show up as "ConcordPay Button" on your site.', 'concordpay-button' ); ?>
	  </p>
		<p>
			<?php
			esc_html_e(
				'You can put the "ConcordPay Button" as many times in a page or post as you want, there is no limit. If you want to remove a "ConcordPay Button", just remove the shortcode text in your page or post.',
				'concordpay-button'
			);
			?>
		</p>
	  </div>

	  <div class="cpb-section-header"><?php _e( 'Account settings', 'concordpay-button' ); ?></div>
	  <div class="cpb-section">
		<div class="cpb-input-group">
		  <label for="merchant_id" class="cpb-label"><?php _e( 'Merchant ID', 'concordpay-button' ); ?></label>
		  <input type="text" name="merchant_id" id="merchant_id" class="cpb-input"
				 value="<?php echo $settings['merchant_id']; ?>">
		  <div class="cpb-description" id="merchant_id_description">
			  <?php _e( 'Given to Merchant by ConcordPay', 'concordpay-button' ); ?>
		  </div>
		</div>
		<div class="cpb-input-group">
		  <label for="secret_key" class="cpb-label"><?php _e( 'Secret key', 'concordpay-button' ); ?></label>
		  <input type="text" name="secret_key" id="secret_key" class="cpb-input"
				 value="<?php echo $settings['secret_key']; ?>">
		  <div class="cpb-description" id="merchant_id_description">
			  <?php _e( 'Given to Merchant by ConcordPay', 'concordpay-button' ); ?>
		  </div>
		</div>
		<div class="cpb-input-group">
		  <label for="currency" class="cpb-label"><?php _e( 'Currency', 'concordpay-button' ); ?></label>
		  <select type="text" name="currency" id="currency" class="cpb-input">
			<?php echo $this->cpb_get_select_options( self::cpb_get_currencies(), $settings['currency'] ); ?>
		  </select>
		  <div class="cpb-description" id="merchant_id_description">
			  <?php _e( 'Specify your currency', 'concordpay-button' ); ?>
		  </div>
		</div>
	  <div class="cpb-input-group">
	  <label for="language" class="cpb-label"><?php _e( 'Language', 'concordpay-button' ); ?></label>
	  <select type="text" name="language" id="language" class="cpb-input">
		<?php echo $this->cpb_get_select_options( self::cpb_get_languages(), $settings['language'] ); ?>
	  </select>
	  <div class="cpb-description" id="language_description">
		<?php _e( 'Specify ConcordPay payment page language', 'concordpay-button' ); ?>
	  </div>
	  </div>
	<div class="cpb-input-group">
	  <label for="mode" class="cpb-label"><?php _e( 'Required fields', 'concordpay-button' ); ?></label>
	  <select type="text" name="mode" id="mode" class="cpb-input">
		  <?php echo $this->cpb_get_select_options( self::cpb_get_required_fields(), $settings['mode'] ); ?>
	  </select>
	  <div class="cpb-description" id="mode_description">
		  <?php _e( 'Fields required to be entered by the buyer', 'concordpay-button' ); ?>
	  </div>
	</div>
		<div class="cpb-input-group">
		  <label for="order_prefix" class="cpb-label"><?php _e( 'Order prefix', 'concordpay-button' ); ?></label>
		  <input type="text" name="order_prefix" id="order_prefix" class="cpb-input"
				 value="<?php echo $settings['order_prefix']; ?>">
		  <div class="cpb-description" id="merchant_id_description">
			  <?php _e( 'Prefix for order', 'concordpay-button' ); ?>
		  </div>
		</div>
	  </div>
		<?php submit_button( __( 'Save Changes' ), 'primary', 'Save' ); ?>
	  <input type='hidden' name='update'>
		<?php wp_nonce_field( 'cpb_form_post' ); ?>
	  </form>

	  </td>
	  </tr>
	  </table>
		<?php
		// End settings page and required permissions.
	}

	/**
	 * Generate popup checkout form.
	 *
	 * @return void
	 */
	public function cpb_checkout_form() {

		$settings = self::cpb_get_settings();
		?>
	<div id="cpb_popup" class="cpb-popup">
	  <div class="cpb-popup-body">
	  <div class="cpb-popup-content">
		<a href="" class="cpb-popup-close" id="cpb-popup-close"><span>×</span></a>
		<div class="cpb-popup-title"> <?php _e( 'Buyer info', 'concordpay-button' ); ?></div>
		<form action="" id="cpb_checkout_form" class="cpb-checkout-form">
		<?php if ( $settings['mode'] !== self::CPB_MODE_NONE ) : ?>
		<div class="cpb-popup-input-group">
			<label for="cpb_client_name" class="cpb-popup-label"><?php _e( 'Name', 'concordpay-button' ); ?></label>
		<input type="text" name="cpb_client_name" id="cpb_client_name" class="cpb-popup-input js-cpb-client-name" value="">
		<div class="cpb-popup-description" id="cpb_client_name_description">
			<?php _e( 'Enter your name', 'concordpay-button' ); ?>
		</div>
		<div class="js-cpb-error-name"></div>
		</div>
	  <?php endif; ?>
		<?php if ( $settings['mode'] === self::CPB_MODE_PHONE || $settings['mode'] === self::CPB_MODE_PHONE_EMAIL ) : ?>
	  <div class="cpb-popup-input-group">
		<label for="cpb_phone" class="cpb-popup-label"><?php _e( 'Phone', 'concordpay-button' ); ?></label>
		<input type="text" name="cpb_phone" id="cpb_phone" class="cpb-popup-input js-cpb-client-phone" value="">
		<div class="cpb-popup-description" id="cpb_client_first_name_description">
			<?php _e( 'Your contact phone', 'concordpay-button' ); ?>
		</div>
		<div class="js-cpb-error-phone"></div>
	  </div>
	  <?php endif; ?>
		<?php if ( $settings['mode'] === self::CPB_MODE_EMAIL || $settings['mode'] === self::CPB_MODE_PHONE_EMAIL ) : ?>
	  <div class="cpb-popup-input-group">
		<label for="cpb_email" class="cpb-popup-label"><?php _e( 'Email', 'concordpay-button' ); ?></label>
		<input type="text" name="cpb_email" id="cpb_email" class="cpb-popup-input js-cpb-client-email" value="">
		<div class="cpb-popup-description" id="cpb_client_email_description">
			<?php _e( 'Your email', 'concordpay-button' ); ?>
		</div>
		<div class="js-cpb-error-email"></div>
	  </div>
	  <?php endif; ?>
	  <input type="hidden" class="js-cpb-product-name" name="cpb_product_name" value="">
	  <input type="hidden" class="js-cpb-product-price" name="cpb_product_price" value="">
		  <input type="hidden" name="action" value="popup_handler">
		  <?php wp_nonce_field( 'popup_handler' ); ?>
		  <div class="cpb-popup-footer">
			<button type="submit" class="cpb-popup-submit" id="cpb_popup_submit">
			  <img src="<?php echo plugin_dir_url( __FILE__ ) . 'assets/img/logo.svg'; ?>" alt="ConcordPay">
			  <span><?php _e( 'Pay Order', 'concordpay-button' ); ?></span>
			</button>
		  </div>
		</form>
	  </div>
	  </div>
	</div>
		<?php
	}

	/**
	 * Update plugin options.
	 *
	 * @return void
	 */
	public function cpb_update_options() {
		// Check nonce for security.
		if ( ! isset( $_REQUEST['_wpnonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_REQUEST['_wpnonce'] ) ), 'cpb_form_post' ) ) {
			echo esc_attr__( 'Nonce verification failed', 'concordpay-button' );
			exit;
		}

		$settings = array();

		foreach ( $this->fillable as $field ) {
			if ( isset( $_POST[ $field ] ) && ! empty( trim( $_POST[ $field ] ) ) ) {
				$settings[ $field ] = trim( sanitize_text_field( wp_unslash( $_POST[ $field ] ) ) );
			}
		}

		update_option( 'cpb_settings', $settings );
	}

	/**
	 * Generates ConcordPay Button markup from the shortcode on the public site.
	 *
	 * @param array $atts Shortcode attributes.
	 *
	 * @return string
	 */
	public function cpb_make_button_from_shortcode( $atts ) {

		// Get shortcode user fields.
		$atts = shortcode_atts(
			array(
				'name'  => 'Example Name',
				'price' => '0.00',
				'size'  => '',
				'align' => '',
			),
			$atts
		);

		$img       = plugin_dir_url( __FILE__ ) . 'assets/img/concordpay.svg';
		$atr_name  = $atts['name'];
		$atr_price = $atts['price'];
		$output    = '<div>';
		$output   .= "<a href='' class='cpb-button-image' data-type='cpb_submit' data-name='{$atr_name}' data-price='{$atr_price}' style='background:url({$img}) no-repeat center center content-box;'></a>";
		$output   .= '</div>';

		return $output;
	}

	/**
	 * Make payment form data.
	 *
	 * @return void
	 */
	public function cpb_payment_handler() {
		global $wp;

		$validation = $this->cpb_validate_checkout_form( $_POST );
		if ( ! $validation['result'] ) {
			echo wp_json_encode( $validation['errors'] );
			die();
		}

		// Get settings page values.
		$options  = get_option( 'cpb_settings' );
		$settings = array();
		foreach ( $options as $k => $v ) {
			if ( 'align' === $k ) {
				$settings[ $k ] = strtolower( $v );
			} else {
				$settings[ $k ] = $v;
			}
		}

		$output = '';
		if ( empty( $settings['merchant_id'] ) ) {
			$output .= __( 'Please enter your ConcordPay Merchant ID on the settings page.', 'concordpay-button' );
		}

		if ( ! empty( $settings['order_prefix'] ) ) {
			$order_id = $settings['order_prefix'] . '_' . self::generate_random_string();
		} else {
			$order_id = 'cpb_' . self::generate_random_string();
		}

		$amount = sanitize_text_field( wp_unslash( $_POST['cpb_product_price'] ) );

		$product_name = sanitize_text_field( wp_unslash( $_POST['cpb_product_name'] ) );

		$cpb_base_url = home_url( $wp->request );
		$approve_url  = add_query_arg( 'concordpay_result', 'success', $cpb_base_url );
		$decline_url  = add_query_arg( 'concordpay_result', 'fail', $cpb_base_url );
		$cancel_url   = add_query_arg( 'concordpay_result', 'cancel', $cpb_base_url );
		$callback_url = '';

		list( $client_first_name, $client_last_name ) = explode(
			' ',
			sanitize_text_field( wp_unslash( trim( $_POST['cpb_client_name'] ) ) )
		);

		$phone = isset( $_POST['cpb_phone'] ) ? self::sanitize_phone( $_POST['cpb_phone'] ) : '';
		$email = isset( $_POST['cpb_email'] ) ? sanitize_email( $_POST['cpb_email'] ) : '';

		$client_site_url = sanitize_text_field( wp_unslash( $_SERVER['HTTP_HOST'] ?? '' ) );

		$description = __( 'Payment by card on the site', 'concordpay-button' )
		  . rtrim( " $client_site_url, $client_first_name $client_last_name, $phone", ', .' );
		$concordpay  = new ConcordPayApi( $settings['secret_key'] );

		$request = array(
			'operation'         => 'Purchase',
			'merchant_id'       => $settings['merchant_id'],
			'amount'            => $amount,
			'order_id'          => $order_id,
			'currency_iso'      => $settings['currency'],
			'description'       => $description,
			'add_params'        => array( 'product_name' => $product_name ),
			'approve_url'       => $approve_url,
			'decline_url'       => $decline_url,
			'cancel_url'        => $cancel_url,
			'callback_url'      => $callback_url,
			'language'          => $settings['language'],
			// Statistics.
			'client_last_name'  => $client_last_name,
			'client_first_name' => $client_first_name,
			'phone'             => $phone,
			'email'             => $email,
		);
		$request['signature'] = $concordpay->getRequestSignature( $request );

		$url = ConcordPayApi::getApiUrl();

		$output .= "<form action={$url} method='post' id='cpb_payment_form'>";
		foreach ( $request as $key => $value ) {
			$output .= $this->print_input( $key, $value );
		}
		$output .= "<input type='submit' value='" . __( 'Pay', 'concordpay-button' ) . "' alt='Make your payments with ConcordPay'>";
		$output .= '</form>';

		echo $output;
		die();
	}

	/**
	 * Link admin styles.
	 */
	public function cpb_link_admin_styles() {
		wp_enqueue_style( 'cpb-admin-styles', plugin_dir_url( __FILE__ ) . 'assets/css/concordpay.css', array(), self::CPB_PLUGIN_VERSION );
	}

	/**
	 * Link front scripts and styles.
	 */
	public function cpb_link_front_scripts() {
		// Register script.
		wp_register_script(
			'cpb-script',
			plugin_dir_url( __FILE__ ) . 'assets/js/concordpay.js',
			array( 'wp-i18n' ),
			self::CPB_PLUGIN_VERSION,
			true
		);
		// Share PHP variable to JS code.
		wp_localize_script(
			'cpb-script',
			'cpb_ajax',
			array(
				'url' => admin_url( 'admin-ajax.php' ),
			)
		);
		// Link script.
		wp_enqueue_script( 'cpb-script' );
		// Translate script.
		wp_set_script_translations(
			'cpb-script',
			'concordpay-button',
			plugin_dir_path( __FILE__ ) . 'lang'
		);
		wp_enqueue_style(
			'cpb-styles',
			plugin_dir_url( __FILE__ ) . 'assets/css/concordpay.css',
			array(),
			self::CPB_PLUGIN_VERSION
		);
	}

	/**
	 * Register ConcordPay block in Gutenberg editor.
	 *
	 * @return void
	 */
	public function cpb_register_gutenberg_block() {

		if ( ! function_exists( 'register_block_type' ) ) {
			return;
		}
		wp_register_script(
			'cpb-block',
			plugins_url( 'assets/js/blocks/cpb.block.js', __FILE__ ),
			array( 'wp-blocks', 'wp-element', 'wp-components', 'wp-editor', 'wp-i18n' ),
			self::CPB_PLUGIN_VERSION,
			true
		);

		wp_register_style(
			'cpb-block',
			plugins_url( 'assets/css/concordpay.css', __FILE__ ),
			array(),
			self::CPB_PLUGIN_VERSION,
			true
		);

		register_block_type(
			'concordpay-button/cpb-block',
			array(
				'editor_script' => 'cpb-block',
			)
		);
	}

	/**
	 * Displays ConcordPay transaction result after redirect from payment page.
	 *
	 * @return void
	 */
	public function cpb_show_payment_result_message() {
		switch ( strtolower( $_GET['concordpay_result'] ) ) {
			case 'success':
				$message = '<div class="cpb-result-success">' . esc_html__( 'Congratulations! Your payment has been approved', 'concordpay-button' ) . '</div>';
				break;
			case 'fail':
				$message = '<div class="cpb-result-fail">' . esc_html__( 'Sorry, payment failed', 'concordpay-button' ) . '</div>';
				break;
			case 'cancel':
				$message = '<div class="cpb-result-cancel">' . esc_html__( 'Payment canceled', 'concordpay-button' ) . '</div>';
				break;
			default:
				$message = '';
		}

		echo $message;
	}

	/**
	 * Generate random string for Order ID.
	 *
	 * @param  int $length Random string length.
	 * @return string
	 */
	protected static function generate_random_string( $length = 10 ) {
		$characters        = 'abcdefghijklmnopqrstuvwxyz';
		$characters_length = strlen( $characters );
		$random_string     = '';
		for ( $i = 0; $i < $length; $i++ ) {
			$random_string .= $characters[ wp_rand( 0, $characters_length - 1 ) ];
		}
		return time() . '_' . $random_string;
	}

	/**
	 * Prints inputs in form.
	 *
	 * @param string       $name Attribute name.
	 * @param array|string $val Attribute value.
	 * @return string
	 */
	protected function print_input( $name, $val ) {
		$str = '';
		if ( ! is_array( $val ) ) {
			return "<input type='hidden' name='" . $name . "' value='" . htmlspecialchars( $val ) . "'>" . PHP_EOL;
		}
		foreach ( $val as $k => $v ) {
			$str .= $this->print_input( $name . '[' . $k . ']', $v );
		}
		return $str;
	}

	/**
	 * List allowed currencies.
	 *
	 * @return array
	 */
	protected static function cpb_get_currencies() {
		return array(
			'UAH' => __( 'Ukrainian hryvnia', 'concordpay-button' ),
			'USD' => __( 'U.S. Dollar', 'concordpay-button' ),
			'EUR' => __( 'Euro', 'concordpay-button' ),
		);
	}

	/**
	 * List of allowed payment page languages.
	 *
	 * @return array
	 */
	protected static function cpb_get_languages() {
		return array(
			'ua' => __( 'UA', 'concordpay-button' ),
			'ru' => __( 'RU', 'concordpay-button' ),
			'en' => __( 'EN', 'concordpay-button' ),
		);
	}

	/**
	 * List of fields required to be entered by the buyer.
	 *
	 * @return array
	 */
	protected static function cpb_get_required_fields() {
		return array(
			self::CPB_MODE_NONE        => __( 'Do not require', 'concordpay-button' ),
			self::CPB_MODE_PHONE       => __( 'Name + Phone', 'concordpay-button' ),
			self::CPB_MODE_EMAIL       => __( 'Name + Email', 'concordpay-button' ),
			self::CPB_MODE_PHONE_EMAIL => __( 'Name + Phone + Email', 'concordpay-button' ),
		);
	}

	/**
	 * Returns list of select options.
	 *
	 * @param array  $data     Input options array.
	 * @param string $selected Current selected value.
	 *
	 * @return string
	 */
	protected function cpb_get_select_options( $data, $selected ) {
		$options = '';
		foreach ( $data as $key => $value ) {
			$options .= "<option value='{$key}'" . ' ' . ( $key === $selected ? "selected='selected'" : '' ) . ">{$value}</option>";
		}

		return $options;
	}

	/**
	 * Translate block in Gutenberg Editor.
	 *
	 * @return void
	 */
	public function cpb_set_script_translations() {
		if ( function_exists( 'wp_set_script_translations' ) ) {
			wp_set_script_translations( 'cpb-block', 'concordpay-button', plugin_dir_path( __FILE__ ) . 'lang' );
		}
	}

	/**
	 * Checkout form validation.
	 *
	 * @param array $post_data $_POST data.
	 *
	 * @return array
	 */
	protected function cpb_validate_checkout_form( $post_data ) {
		$result               = array(
			'result' => false,
			'errors' => array(),
		);
		$checkout_params_keys = $this->cpb_get_checkout_params_keys();
		$isHasAllValues       = ! array_diff_key( $checkout_params_keys, $post_data );
		if ( ! $isHasAllValues && ! isset( $post_data['is_single_field'] ) ) {
			$result['errors'][] = __( 'Error: Not enough input parameters.', 'concordpay-button' );
			return $result;
		}

		// Check nonce code.
		if ( ! wp_verify_nonce( $post_data['_wpnonce'], 'popup_handler' ) ) {
			$result['errors']['nonce'] = __( 'Error: Request failed security check', 'concordpay-button' );
		}

		// Check client_name.
		if ( isset( $checkout_params_keys['cpb_client_name'] ) && empty( trim( $post_data['cpb_client_name'] ) ) ) {
			$result['errors']['name'] = __( 'Invalid name', 'concordpay-button' );
		}

		// Check phone.
		if ( isset( $checkout_params_keys['cpb_phone'] ) ) {
			$phone = self::sanitize_phone( $post_data['cpb_phone'] );
			if ( empty( $phone ) || mb_strlen( $phone ) < 10 ) {
				$result['errors']['phone'] = __( 'Invalid phone number', 'concordpay-button' );
			}
		}

		// Check email.
		if ( isset( $checkout_params_keys['cpb_email'] ) ) {
			$email = trim( $post_data['cpb_email'] );
			if ( ! filter_var( $email, FILTER_VALIDATE_EMAIL ) ) {
				$result['errors']['email'] = __( 'Invalid email', 'concordpay-button' );
			}
		}

		if ( empty( $result['errors'] ) ) {
			$result['result'] = true;
		}

		return $result;
	}

	/**
	 * Remove all non-numerical symbol from phone.
	 *
	 * @param string $phone Client phone.
	 *
	 * @return array|string|string[]|null
	 */
	protected static function sanitize_phone( $phone ) {
		return preg_replace( '/\D+/', '', $phone );
	}

	/**
	 * Return plugin settings.
	 *
	 * @return array
	 */
	protected static function cpb_get_settings() {
		$options  = get_option( 'cpb_settings' );
		$settings = array();
		foreach ( $options as $k => $v ) {
			$settings[ $k ] = $v;
		}

		return $settings;
	}

	/**
	 * Get required checkout fields.
	 *
	 * @return string[]
	 */
	protected function cpb_get_checkout_params_keys() {
		$settings = self::cpb_get_settings();

		$params = $settings['mode'] ? $this->checkout_params[ $settings['mode'] ] : $this->checkout_params[ self::CPB_MODE_PHONE ];

		return array_flip( $params );
	}
}
