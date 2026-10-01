<?php
/**
 * AyudaWP EU Withdrawal Promotional Banner.
 *
 * Promotional banner that rotates AyudaWP's WordPress services on the plugin
 * settings screen. Mirror of ayudawp-promo-banner-catalog-servicios.md (the
 * single source of truth). Plugin cross-promotion was dropped in 2.1.0: the
 * banner shows only services, so there is no host/sibling plugin to exclude
 * and no wordpress.org install modal (Thickbox) to load for it.
 *
 * Class name is unique to avoid collisions with other AyudaWP plugins.
 *
 * @package AyudaWP_EU_Withdrawal
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * EU Withdrawal Promo Banner class.
 */
class Ayudawp_Euw_Promo_Banner {

	/**
	 * How many service cards the banner shows at once.
	 *
	 * @var int
	 */
	const CARDS = 3;

	/**
	 * CSS class prefix used in markup and styles.
	 *
	 * @var string
	 */
	private $css_prefix;

	/**
	 * Constructor.
	 *
	 * @param string $css_prefix CSS class prefix.
	 */
	public function __construct( $css_prefix ) {
		$this->css_prefix = $css_prefix;
	}

	/**
	 * Get AyudaWP services catalog.
	 *
	 * Mirror of ayudawp-promo-banner-catalog-servicios.md. All strings use the
	 * literal text domain (WPCS / make-pot requirement).
	 *
	 * @return array
	 */
	private function get_services_catalog() {
		return array(
			'maintenance' => array(
				'icon'        => 'dashicons-admin-tools',
				'title'       => __( 'Need help with your website?', 'eu-withdrawal-compliance' ),
				'description' => __( 'Professional WordPress maintenance: security monitoring, regular backups, performance optimization, and priority support.', 'eu-withdrawal-compliance' ),
				'button'      => __( 'Learn more', 'eu-withdrawal-compliance' ),
				/* translators: AyudaWP maintenance service URL. Change this URL in translations to use a localized landing page. */
				'url'         => __( 'https://mantenimiento.ayudawp.com/en/', 'eu-withdrawal-compliance' ),
			),
			'consultancy' => array(
				'icon'        => 'dashicons-businessman',
				'title'       => __( 'WordPress consultancy', 'eu-withdrawal-compliance' ),
				'description' => __( 'One-on-one online sessions to solve your WordPress doubts, get expert advice, and make better decisions for your project.', 'eu-withdrawal-compliance' ),
				'button'      => __( 'Book a session', 'eu-withdrawal-compliance' ),
				'url'         => 'https://servicios.ayudawp.com/producto/consultoria-online-wordpress/',
			),
			'hacked'      => array(
				'icon'        => 'dashicons-sos',
				'title'       => __( 'Hacked website?', 'eu-withdrawal-compliance' ),
				'description' => __( 'Fast recovery service for compromised WordPress sites. We clean malware, fix vulnerabilities, and restore your site security.', 'eu-withdrawal-compliance' ),
				'button'      => __( 'Get help now', 'eu-withdrawal-compliance' ),
				'url'         => 'https://servicios.ayudawp.com/producto/wordpress-hackeado/',
			),
			'development' => array(
				'icon'        => 'dashicons-editor-code',
				'title'       => __( 'Custom development', 'eu-withdrawal-compliance' ),
				'description' => __( 'Need a custom plugin, theme modifications, or specific functionality? We build tailored WordPress solutions for your needs.', 'eu-withdrawal-compliance' ),
				'button'      => __( 'Request a quote', 'eu-withdrawal-compliance' ),
				'url'         => 'https://servicios.ayudawp.com/producto/desarrollo-wordpress/',
			),
			'hosting'     => array(
				'icon'        => 'dashicons-cloud-saved',
				'title'       => __( 'Hosting built for WordPress', 'eu-withdrawal-compliance' ),
				'description' => __( 'Google Cloud servers, automatic geo-located daily backups, and 24/7 expert support. Speed, security, and migration tools included.', 'eu-withdrawal-compliance' ),
				'button'      => __( 'Learn more', 'eu-withdrawal-compliance' ),
				/* translators: SiteGround affiliate URL. Change this URL in translations to use a localized landing page. */
				'url'         => __( 'https://stgrnd.co/telladowpbox', 'eu-withdrawal-compliance' ),
			),
			'plugins'     => array(
				'icon'        => 'dashicons-admin-plugins',
				'title'       => __( 'Premium WordPress plugins', 'eu-withdrawal-compliance' ),
				'description' => __( 'Focused plugins for WordPress and WooCommerce, each solving one problem well. No bloated suites, no upsell nags, no telemetry.', 'eu-withdrawal-compliance' ),
				'button'      => __( 'Browse plugins', 'eu-withdrawal-compliance' ),
				/* translators: AyudaWP plugin store URL. Change this URL in translations to use a localized landing page. */
				'url'         => __( 'https://plugins.ayudawp.com/en/', 'eu-withdrawal-compliance' ),
			),
		);
	}

	/**
	 * Get a random subset of services to display.
	 *
	 * @param int $count Number of services to return.
	 * @return array Subset of the catalog, keyed by service key.
	 */
	private function get_random_services( $count = self::CARDS ) {
		$services = $this->get_services_catalog();

		$count       = max( 1, min( (int) $count, count( $services ) ) );
		$random_keys = array_rand( $services, $count );
		if ( ! is_array( $random_keys ) ) {
			$random_keys = array( $random_keys );
		}

		$result = array();
		foreach ( $random_keys as $key ) {
			$result[ $key ] = $services[ $key ];
		}

		return $result;
	}

	/**
	 * Render the promotional banner.
	 *
	 * @param string $layout Layout: 'horizontal' (3 columns) or 'vertical' (sidebar widgets).
	 */
	public function render( $layout = 'horizontal' ) {
		if ( 'vertical' === $layout ) {
			$this->render_vertical();
			return;
		}

		$this->render_horizontal();
	}

	/**
	 * Render the horizontal layout (3-column grid).
	 */
	private function render_horizontal() {
		$services = $this->get_random_services();
		$prefix   = $this->css_prefix;
		?>
		<div class="<?php echo esc_attr( $prefix ); ?>-promo-notice">
			<div class="<?php echo esc_attr( $prefix ); ?>-promo-columns">

				<?php foreach ( $services as $service ) : ?>
					<div class="<?php echo esc_attr( $prefix ); ?>-promo-column">
						<span class="dashicons <?php echo esc_attr( $service['icon'] ); ?>"></span>
						<h5><?php echo esc_html( $service['title'] ); ?></h5>
						<p><?php echo esc_html( $service['description'] ); ?></p>
						<a href="<?php echo esc_url( $service['url'] ); ?>" target="_blank" rel="noopener noreferrer" class="button button-primary">
							<?php echo esc_html( $service['button'] ); ?>
						</a>
					</div>
				<?php endforeach; ?>

			</div>
		</div>
		<?php
	}

	/**
	 * Render the vertical layout (stacked sidebar widgets).
	 */
	private function render_vertical() {
		$services = $this->get_random_services();
		$prefix   = $this->css_prefix;

		foreach ( $services as $service ) :
			?>
			<div class="<?php echo esc_attr( $prefix ); ?>-sidebar-widget <?php echo esc_attr( $prefix ); ?>-promo-widget">
				<span class="dashicons <?php echo esc_attr( $service['icon'] ); ?>"></span>
				<h3><?php echo esc_html( $service['title'] ); ?></h3>
				<p><?php echo esc_html( $service['description'] ); ?></p>
				<a href="<?php echo esc_url( $service['url'] ); ?>" target="_blank" rel="noopener noreferrer" class="button button-primary">
					<?php echo esc_html( $service['button'] ); ?>
				</a>
			</div>
			<?php
		endforeach;
	}
}
