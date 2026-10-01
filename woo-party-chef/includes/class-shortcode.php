<?php
/**
 * [woo_party_chef] shortcode: Chef's Dinner Party planner and comparison.
 *
 * Renders the complete default state server-side (WP Rocket delays JS until
 * the first interaction). State is styled through data/ARIA attributes, never
 * through JS-added classes, so WP Rocket's Remove Unused CSS keeps the rules.
 *
 * Usage:
 *   [woo_party_chef]
 *   [woo_party_chef default_color="wit" persons="6" discount="percentage"]
 *
 * Attributes:
 *   default_color  grey|wit                 Default: grey
 *   persons        1-12                     Default: 4
 *   show_planner   yes|no                   Default: yes
 *   show_prices    yes|no                   Default: yes
 *   show_sale      yes|no                   Default: yes (strike-through and savings)
 *   discount       amount|percentage        Default: amount
 *   standalone     yes|no                   Default: no  (section background and padding)
 *   anchor         HTML id                  Default: kies-jouw-chefs-dinner-party
 *   image_grey     attachment ID            Fallback for Grey products without a featured image
 *   image_wit      attachment ID            Fallback for Wit products without a featured image
 *
 * The planner image shows the featured image of the recommended product.
 *
 * @package Woo_Party_Chef
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Shortcode registration, assets and markup.
 */
class WOOPC_Shortcode {

	const TAG    = 'woo_party_chef';
	const HANDLE = 'woo-party-chef';

	/**
	 * Fixed per-column copy.
	 */
	const COLUMNS = array(
		'4'   => array( 'big' => '4', 'unit' => 'personen', 'stations' => 4, 'ideal' => 'Etentje met z’n vieren' ),
		'5'   => array( 'big' => '5', 'unit' => 'personen', 'stations' => 5, 'ideal' => 'Gezin met een extra gast' ),
		'6'   => array( 'big' => '6', 'unit' => 'personen', 'stations' => 6, 'ideal' => 'Groter gezin of vrienden' ),
		'8'   => array( 'big' => '8', 'unit' => 'personen', 'stations' => 8, 'ideal' => 'Grote gezelschappen en feestdagen' ),
		'ext' => array( 'big' => '+1', 'unit' => 'extra station', 'stations' => 1, 'ideal' => 'Bestaande set uitbreiden' ),
	);

	const FEATURES = array(
		'Eigen kookstation met keramisch bord en bakplaat per persoon',
		'PFAS-vrije keramische anti-aanbaklaag',
		'Borden en bakplaten zijn vaatwasserbestendig',
		'Aan-uitschakelaar en powerlampje per station',
		'250 W per station, snel op temperatuur',
		'Uit te breiden met losse kookstations',
	);

	/**
	 * Leaf icon path, identical to Woo Card Chef's PFAS-vrij badge icon.
	 */
	const LEAF_PATH = 'M 0.11367774,3.3348666 C 0.02320601,3.2929796 0.00890051,3.1786555 0.08832631,3.1322685 0.10391023,3.1231682 0.13260497,3.1181068 0.19506976,3.1134495 0.29652413,3.1058845 0.33914794,3.0987479 0.41633848,3.0764122 0.58186152,3.0285133 0.78884167,2.9229141 1.0409714,2.7577303 l 0.1132021,-0.074165 -0.029791,-0.0056 C 0.89175531,2.6342447 0.64950905,2.4417489 0.53054831,2.2060889 0.47187517,2.0898579 0.4486248,1.9900765 0.44822565,1.8527917 0.44779733,1.705458 0.47530363,1.5906616 0.54655753,1.4424094 0.68311158,1.1582923 0.92335288,0.89743049 1.2030447,0.72957453 1.3955841,0.61402279 1.5537682,0.54886654 1.9505712,0.42166842 2.2190293,0.33561226 2.3110565,0.30175807 2.421363,0.24847776 2.5480886,0.18726658 2.6454532,0.11580257 2.7124265,0.0348413 2.7323853,0.0107136 2.7506579,-0.00675553 2.7530322,-0.00397989 2.760681,0.00496356 2.8264941,0.23670288 2.8672214,0.39810327 2.9817216,0.85186416 3.0333776,1.2295717 3.0331945,1.6116957 3.0331031,1.8023051 3.0278164,1.8945448 3.0092418,2.0296231 2.9230828,2.6561979 2.6237012,3.0912894 2.1998487,3.2059148 2.1429872,3.2212921 2.1232265,3.2232829 2.0287943,3.2231472 1.9312935,3.2230117 1.9153688,3.2212167 1.8452901,3.2026511 1.6564554,3.1525904 1.5237147,3.0750108 1.4428613,2.9674522 L 1.4125374,2.9271126 1.534072,2.805339 C 1.9661414,2.372421 2.2887695,1.81821 2.4826704,1.1758359 2.5025544,1.1099621 2.5178789,1.0551211 2.5167249,1.053967 2.5155666,1.0528208 2.4986456,1.088693 2.4791133,1.1337001 2.3377437,1.4594496 2.1378337,1.7941674 1.9161261,2.0763332 1.7675395,2.2654387 1.5155865,2.5302833 1.3481611,2.6733585 1.086292,2.8971422 0.66337924,3.1659788 0.37329167,3.2930623 0.24743492,3.3481985 0.16955853,3.3607387 0.11367774,3.3348666 Z';

	/**
	 * Registers the shortcode and asset hooks.
	 */
	public static function init(): void {
		add_shortcode( self::TAG, array( __CLASS__, 'render' ) );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'register_assets' ) );

		// WP Rocket Remove Unused CSS: keep this small stylesheet intact.
		add_filter( 'rocket_rucss_external_exclusions', array( __CLASS__, 'rucss_exclude_file' ) );
		add_filter( 'rocket_rucss_safelist', array( __CLASS__, 'rucss_safelist' ) );
	}

	/**
	 * Registers assets; enqueues CSS in the head on remembered pages.
	 */
	public static function register_assets(): void {
		wp_register_style( self::HANDLE, WOOPC_URL . 'assets/woo-party-chef.css', array(), WOOPC_VERSION );
		wp_register_script(
			self::HANDLE,
			WOOPC_URL . 'assets/woo-party-chef.js',
			array(),
			WOOPC_VERSION,
			array(
				'in_footer' => true,
				'strategy'  => 'defer',
			)
		);

		// Known comparison page: load CSS in <head> to avoid a late-style flash.
		if ( is_singular() && in_array( (int) get_queried_object_id(), WOOPC_Cache_Purger::get_pages(), true ) ) {
			wp_enqueue_style( self::HANDLE );
		}
	}

	/**
	 * Excludes the stylesheet from WP Rocket's Remove Unused CSS.
	 *
	 * @param mixed $excluded Excluded file paths.
	 * @return array
	 */
	public static function rucss_exclude_file( $excluded ): array {
		$excluded   = (array) $excluded;
		$excluded[] = '/woo-party-chef/assets/woo-party-chef.css';
		return $excluded;
	}

	/**
	 * Safelists the component selectors in case the stylesheet gets processed anyway.
	 *
	 * @param mixed $safelist Safelisted selectors.
	 * @return array
	 */
	public static function rucss_safelist( $safelist ): array {
		$safelist   = (array) $safelist;
		$safelist[] = '.woopc(.*)';
		return $safelist;
	}

	/**
	 * Renders the shortcode.
	 *
	 * @param array|string $atts Shortcode attributes.
	 * @return string
	 */
	public static function render( $atts ): string {
		$atts = shortcode_atts(
			array(
				'default_color' => 'grey',
				'persons'       => 4,
				'show_planner'  => 'yes',
				'show_prices'   => 'yes',
				'show_sale'     => 'yes',
				'discount'      => 'amount',
				'standalone'    => 'no',
				'anchor'        => 'kies-jouw-chefs-dinner-party',
				'image_grey'    => 0,
				'image_wit'     => 0,
			),
			(array) $atts,
			self::TAG
		);

		$colors = WOOPC_Products::get_colors_data(
			array(
				'grey' => absint( $atts['image_grey'] ),
				'wit'  => absint( $atts['image_wit'] ),
			)
		);

		if ( empty( $colors ) ) {
			// Never show visitors made-up prices. Editors get a hint.
			if ( current_user_can( 'edit_products' ) ) {
				return '<p class="woopc-notice">' . esc_html__( 'Woo Party Chef: de Chef’s Dinner Party-producten zijn niet gevonden of niet gepubliceerd. Controleer de SKU’s.', 'woo-party-chef' ) . '</p>';
			}
			return '';
		}

		$settings = array(
			'show_prices'  => self::is_yes( $atts['show_prices'] ),
			'show_sale'    => self::is_yes( $atts['show_sale'] ),
			'discount_pct' => 'percentage' === sanitize_key( $atts['discount'] ),
		);
		$planner  = self::is_yes( $atts['show_planner'] );

		$color_key = sanitize_key( $atts['default_color'] );
		if ( ! isset( $colors[ $color_key ] ) ) {
			$color_key = (string) array_key_first( $colors );
		}
		$persons = max( 1, min( WOOPC_Products::MAX_PERSONS, absint( $atts['persons'] ) ) );
		$state   = WOOPC_Products::compute_state( $colors[ $color_key ], $persons, $settings );

		self::enqueue();
		self::maybe_remember_page();

		$config = array(
			'colors'      => array_map(
				static function ( array $color ): array {
					return array(
						'label' => $color['label'],
						'name'  => $color['name'],
						'items' => $color['items'],
					);
				},
				$colors
			),
			'showPrices'  => $settings['show_prices'],
			'showSale'    => $settings['show_sale'],
			'discountPct' => $settings['discount_pct'],
			'max'         => WOOPC_Products::MAX_PERSONS,
		);

		ob_start();

		// Elementor renders widgets via AJAX while editing; enqueued styles
		// are not printed there, so link the stylesheet directly.
		if ( wp_doing_ajax() || isset( $_GET['elementor-preview'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only check.
			echo '<link rel="stylesheet" href="' . esc_url( WOOPC_URL . 'assets/woo-party-chef.css?ver=' . WOOPC_VERSION ) . '">'; // phpcs:ignore WordPress.WP.EnqueuedResources.NonEnqueuedStylesheet
		}
		?>
		<section class="woopc" id="<?php echo esc_attr( sanitize_html_class( $atts['anchor'] ) ); ?>" data-standalone="<?php echo self::is_yes( $atts['standalone'] ) ? 'yes' : 'no'; ?>" data-color="<?php echo esc_attr( $color_key ); ?>" data-persons="<?php echo esc_attr( (string) $state['n'] ); ?>" data-config="<?php echo esc_attr( (string) wp_json_encode( $config ) ); ?>">
			<div class="woopc__inner">
				<?php
				if ( $planner ) {
					self::render_planner( $colors, $color_key, $state, $settings );
				}
				self::render_compare( $colors, $color_key, $state, $settings );
				self::render_features();
				?>
			</div>
		</section>
		<?php
		return (string) ob_get_clean();
	}

	/**
	 * Planner card: image, color, persons and advice.
	 *
	 * @param array  $colors    Colors data.
	 * @param string $color_key Active color.
	 * @param array  $state     Computed state.
	 * @param array  $settings  Display settings.
	 */
	private static function render_planner( array $colors, string $color_key, array $state, array $settings ): void {
		$shown = $colors[ $color_key ]['items'][ $state['image'] ];
		?>
		<div class="woopc__planner">
			<div class="woopc__media">
				<?php self::render_product_images( $colors, (int) $shown['image'] ); ?>
				<span class="woopc__pfas" data-ref="pfas"<?php echo $shown['pfas'] ? '' : ' hidden'; ?>>
					<svg class="woopc__pfas-icon" viewBox="0 0 2.996769 3.3520503" aria-hidden="true" focusable="false"><path fill="currentColor" d="<?php echo esc_attr( self::LEAF_PATH ); ?>"/></svg>PFAS-vrij
				</span>
			</div>
			<div class="woopc__controls">
				<div class="woopc__step">
					<div class="woopc__step-label" id="woopc-step-color">1. Uitvoering</div>
					<div class="woopc__colors" role="group" aria-labelledby="woopc-step-color">
						<?php self::render_color_buttons( $colors, $color_key, false ); ?>
					</div>
				</div>
				<div class="woopc__step">
					<div class="woopc__step-label">2. Met hoeveel personen eet je?</div>
					<div class="woopc__stepper">
						<button type="button" class="woopc__step-btn" data-step="-1" aria-label="Minder personen"<?php disabled( $state['n'] <= 1 ); ?>>−</button>
						<div class="woopc__count" aria-live="polite">
							<span class="woopc__count-num" data-ref="n"><?php echo esc_html( (string) $state['n'] ); ?></span>
							<span class="woopc__count-unit">personen</span>
						</div>
						<button type="button" class="woopc__step-btn" data-step="1" aria-label="Meer personen"<?php disabled( $state['n'] >= WOOPC_Products::MAX_PERSONS ); ?>>+</button>
					</div>
					<div class="woopc__dots" data-ref="dots" aria-hidden="true">
						<?php foreach ( $state['dots'] as $kind ) : ?>
							<span class="woopc__dot" data-kind="<?php echo esc_attr( $kind ); ?>"></span>
						<?php endforeach; ?>
					</div>
					<div class="woopc__legend">
						<span class="woopc__legend-item"><span class="woopc__legend-dot" data-kind="guest"></span>Kookstation in de set</span>
						<span class="woopc__legend-item" data-ref="legend-ext"<?php echo $state['has_ext'] ? '' : ' hidden'; ?>><span class="woopc__legend-dot" data-kind="ext"></span>Uitbreidingsset</span>
						<span class="woopc__legend-item" data-ref="legend-spare"<?php echo $state['has_spare'] ? '' : ' hidden'; ?>><span class="woopc__legend-dot" data-kind="spare"></span>Extra plek voor een gast</span>
					</div>
				</div>
				<div class="woopc__advice">
					<div class="woopc__advice-head">
						<div class="woopc__advice-label">Ons advies</div>
						<div class="woopc__advice-title" data-ref="title"><?php echo esc_html( $state['title'] ); ?></div>
						<div class="woopc__advice-body">
							<span data-ref="text"><?php echo esc_html( $state['text'] ); ?></span>
							<span class="woopc__alt" data-ref="alt"<?php echo '' === $state['alt'] ? ' hidden' : ''; ?>><?php echo esc_html( $state['alt'] ); ?></span>
							<a class="woopc__ext-link" data-ref="ext-link" href="<?php echo esc_url( $state['ext_url'] ); ?>"<?php echo $state['ext_link'] ? '' : ' hidden'; ?>><?php echo esc_html( $state['ext_label'] ); ?></a>
						</div>
					</div>
					<div class="woopc__advice-foot">
						<?php if ( $settings['show_prices'] ) : ?>
							<div class="woopc__total">
								<span class="woopc__total-label">Totaal</span>
								<span class="woopc__total-row">
									<span class="woopc__total-price" data-ref="total" data-sale="<?php echo $state['on_sale'] ? 'true' : 'false'; ?>"><?php echo esc_html( $state['total'] ); ?></span>
									<s class="woopc__total-was" data-ref="total-was"<?php echo $state['on_sale'] ? '' : ' hidden'; ?>><?php echo esc_html( $state['total_was'] ); ?></s>
								</span>
								<span class="woopc__save" data-ref="save"<?php echo $state['on_sale'] ? '' : ' hidden'; ?>><?php echo esc_html( $state['save'] ); ?></span>
							</div>
						<?php endif; ?>
						<div class="woopc__cta-wrap">
							<a class="woopc__cta" data-ref="cta" href="<?php echo esc_url( $state['cta_url'] ); ?>">Bekijk deze set</a>
						</div>
					</div>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * Renders each distinct product image once; only the active one is visible.
	 *
	 * Hidden images are not downloaded by the browser (lazy + display:none),
	 * so the extra cost is markup only. JS toggles [hidden] by attachment ID,
	 * which keeps Imagify <picture> markup and WP Rocket lazyload intact.
	 *
	 * @param array $colors    Colors data.
	 * @param int   $active_id Attachment ID to show.
	 */
	private static function render_product_images( array $colors, int $active_id ): void {
		$images = array();
		foreach ( $colors as $color ) {
			foreach ( $color['items'] as $key => $item ) {
				$image_id = (int) $item['image'];
				if ( $image_id > 0 && ! isset( $images[ $image_id ] ) ) {
					$images[ $image_id ] = 'ext' === (string) $key
						? $color['name'] . ' uitbreidingsset'
						: $color['name'] . ' voor ' . $key . ' personen';
				}
			}
		}

		foreach ( $images as $image_id => $alt ) {
			$attr = array(
				'class'         => 'woopc__img',
				'alt'           => $alt,
				'sizes'         => '(max-width: 800px) 62vw, 520px',
				'data-image-id' => (string) $image_id,
				'loading'       => 'lazy',
			);
			// wp_get_attachment_image() prints every key, so only add
			// "hidden" when the image must actually be hidden.
			if ( $image_id !== $active_id ) {
				$attr['hidden'] = 'hidden';
			}
			echo wp_get_attachment_image( $image_id, 'woocommerce_single', false, $attr );
		}
	}

	/**
	 * Comparison: heading, desktop table and mobile cards.
	 *
	 * @param array  $colors    Colors data.
	 * @param string $color_key Active color.
	 * @param array  $state     Computed state.
	 * @param array  $settings  Display settings.
	 */
	private static function render_compare( array $colors, string $color_key, array $state, array $settings ): void {
		$show_prices = $settings['show_prices'];
		?>
		<div class="woopc__compare">
			<div class="woopc__compare-head">
				<h3 class="woopc__compare-title">Alle sets naast elkaar <span class="woopc__compare-color" data-ref="color-label">· <?php echo esc_html( $colors[ $color_key ]['label'] ); ?></span></h3>
				<div class="woopc__colors woopc__colors--small" role="group" aria-label="Uitvoering">
					<?php self::render_color_buttons( $colors, $color_key, true ); ?>
				</div>
			</div>

			<div class="woopc__table-wrap">
				<div class="woopc__table">
					<div class="woopc__labels" aria-hidden="true">
						<div class="woopc__label woopc__label--head"></div>
						<div class="woopc__label woopc__label--ideal">Ideaal voor</div>
						<div class="woopc__label woopc__label--stations">Kookstations</div>
						<div class="woopc__label woopc__label--watt">Vermogen</div>
						<?php if ( $show_prices ) : ?>
							<div class="woopc__label woopc__label--price">Prijs</div>
						<?php endif; ?>
						<div class="woopc__label woopc__label--cta"></div>
					</div>
					<?php foreach ( self::COLUMNS as $key => $column ) : ?>
						<?php $col = $state['cols'][ $key ]; ?>
						<div class="woopc__col" data-col="<?php echo esc_attr( $key ); ?>" data-kind="<?php echo 'ext' === $key ? 'ext' : 'set'; ?>" data-active="<?php echo $col['active'] ? 'true' : 'false'; ?>">
							<div class="woopc__cell woopc__cell--head">
								<span class="woopc__chosen">Jouw keuze</span>
								<?php if ( $show_prices ) : ?>
									<span class="woopc__badge" data-ref="badge"<?php echo $col['sale'] ? '' : ' hidden'; ?>><?php echo esc_html( $col['badge'] ); ?></span>
								<?php endif; ?>
								<button type="button" class="woopc__pick" data-pick="<?php echo esc_attr( $key ); ?>" aria-label="<?php echo esc_attr( self::pick_label( $key ) ); ?>">
									<span class="woopc__big"><?php echo esc_html( $column['big'] ); ?></span>
									<span class="woopc__unit"><?php echo esc_html( $column['unit'] ); ?></span>
								</button>
							</div>
							<div class="woopc__cell woopc__cell--ideal"><?php echo esc_html( $column['ideal'] ); ?></div>
							<div class="woopc__cell woopc__cell--stations"><?php echo esc_html( self::stations_label( $column['stations'] ) ); ?></div>
							<div class="woopc__cell woopc__cell--watt"><?php echo esc_html( WOOPC_Products::format_watt( $column['stations'] * WOOPC_Products::WATT_PER_STATION ) ); ?></div>
							<?php if ( $show_prices ) : ?>
								<div class="woopc__cell woopc__cell--price">
									<s class="woopc__col-was" data-ref="was"<?php echo $col['sale'] ? '' : ' hidden'; ?>><?php echo esc_html( $col['was'] ); ?></s>
									<span class="woopc__col-price" data-ref="price" data-sale="<?php echo $col['sale'] ? 'true' : 'false'; ?>"><?php echo esc_html( $col['price'] ); ?></span>
								</div>
							<?php endif; ?>
							<div class="woopc__cell woopc__cell--cta">
								<a class="woopc__col-cta" data-ref="url" href="<?php echo esc_url( $col['url'] ); ?>">Bekijk</a>
							</div>
						</div>
					<?php endforeach; ?>
				</div>
			</div>

			<div class="woopc__cards">
				<?php foreach ( self::COLUMNS as $key => $column ) : ?>
					<?php $col = $state['cols'][ $key ]; ?>
					<div class="woopc__card" data-col="<?php echo esc_attr( $key ); ?>" data-kind="<?php echo 'ext' === $key ? 'ext' : 'set'; ?>" data-active="<?php echo $col['active'] ? 'true' : 'false'; ?>">
						<button type="button" class="woopc__pick woopc__card-num" data-pick="<?php echo esc_attr( $key ); ?>" aria-label="<?php echo esc_attr( self::pick_label( $key ) ); ?>">
							<span class="woopc__card-big"><?php echo esc_html( $column['big'] ); ?></span>
							<span class="woopc__card-unit"><?php echo esc_html( $column['unit'] ); ?></span>
						</button>
						<div class="woopc__card-info">
							<span class="woopc__card-ideal"><?php echo esc_html( $column['ideal'] ); ?></span>
							<span class="woopc__card-meta"><?php echo esc_html( self::stations_label( $column['stations'] ) . ' · ' . WOOPC_Products::format_watt( $column['stations'] * WOOPC_Products::WATT_PER_STATION ) ); ?></span>
							<span class="woopc__chosen woopc__chosen--card">Jouw keuze</span>
						</div>
						<div class="woopc__card-buy">
							<?php if ( $show_prices ) : ?>
								<span class="woopc__card-sale" data-ref="sale-row"<?php echo $col['sale'] ? '' : ' hidden'; ?>>
									<span class="woopc__badge woopc__badge--card" data-ref="badge"><?php echo esc_html( $col['badge'] ); ?></span>
									<s class="woopc__card-was" data-ref="was"><?php echo esc_html( $col['was'] ); ?></s>
								</span>
								<span class="woopc__card-price" data-ref="price" data-sale="<?php echo $col['sale'] ? 'true' : 'false'; ?>"><?php echo esc_html( $col['price'] ); ?></span>
							<?php endif; ?>
							<a class="woopc__card-link" data-ref="url" href="<?php echo esc_url( $col['url'] ); ?>">Bekijk →</a>
						</div>
					</div>
				<?php endforeach; ?>
			</div>
		</div>
		<?php
	}

	/**
	 * "In elke Chef's Dinner Party" feature box.
	 */
	private static function render_features(): void {
		?>
		<div class="woopc__features">
			<h3 class="woopc__features-title">In elke Chef’s Dinner Party</h3>
			<ul class="woopc__feature-list">
				<?php foreach ( self::FEATURES as $feature ) : ?>
					<li class="woopc__feature"><span class="woopc__check" aria-hidden="true">✓</span><span><?php echo esc_html( $feature ); ?></span></li>
				<?php endforeach; ?>
			</ul>
		</div>
		<?php
	}

	/**
	 * Color pill buttons.
	 *
	 * @param array  $colors    Colors data.
	 * @param string $color_key Active color.
	 * @param bool   $small     Small variant (comparison heading).
	 */
	private static function render_color_buttons( array $colors, string $color_key, bool $small ): void {
		// Fixed order from the design: Wit first.
		$order = array_unique( array_merge( array( 'wit', 'grey' ), array_keys( $colors ) ) );
		foreach ( $order as $key ) {
			if ( ! isset( $colors[ $key ] ) ) {
				continue;
			}
			printf(
				'<button type="button" class="woopc__color%1$s" data-pick-color="%2$s" aria-pressed="%3$s"><span class="woopc__swatch" style="background:%4$s"></span>%5$s</button>',
				$small ? ' woopc__color--small' : '',
				esc_attr( $key ),
				$key === $color_key ? 'true' : 'false',
				esc_attr( sanitize_hex_color( $colors[ $key ]['swatch'] ) ?: '#FFFFFF' ),
				esc_html( $colors[ $key ]['label'] )
			);
		}
	}

	/**
	 * Accessible label for a column pick button.
	 *
	 * @param string $key Column key.
	 * @return string
	 */
	private static function pick_label( string $key ): string {
		return 'ext' === $key ? 'Kies 1 extra station' : 'Kies de set voor ' . $key . ' personen';
	}

	/**
	 * "1 station" / "N stations".
	 *
	 * @param int $count Stations.
	 * @return string
	 */
	private static function stations_label( int $count ): string {
		return 1 === $count ? '1 station' : $count . ' stations';
	}

	/**
	 * Enqueues assets; in the Elementor editor the stylesheet is printed inline-linked.
	 */
	private static function enqueue(): void {
		if ( ! wp_style_is( self::HANDLE, 'registered' ) ) {
			self::register_assets();
		}
		wp_enqueue_style( self::HANDLE );
		wp_enqueue_script( self::HANDLE );
	}

	/**
	 * Remembers the current page for targeted cache purging.
	 */
	private static function maybe_remember_page(): void {
		if ( is_admin() || wp_doing_ajax() || is_preview() || ! is_singular() ) {
			return;
		}
		if ( isset( $_GET['elementor-preview'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only check.
			return;
		}
		WOOPC_Cache_Purger::remember_page( (int) get_queried_object_id() );
	}

	/**
	 * Yes/no attribute parser.
	 *
	 * @param mixed $value Value.
	 * @return bool
	 */
	private static function is_yes( $value ): bool {
		return in_array( strtolower( (string) $value ), array( 'yes', '1', 'true', 'ja', 'on' ), true );
	}
}
