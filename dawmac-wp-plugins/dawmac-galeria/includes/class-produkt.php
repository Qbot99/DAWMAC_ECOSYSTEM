<?php
/**
 * Sekcja na karcie produktu: auta klientów jeżdżące na tej feldze.
 *
 * Świadomie NIE ładujemy nic z galerii, dopóki produkt nie ma dopasowania —
 * większość kart nie ma zdjęć i nie może przez to zwolnić.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Dawmac_Galeria_Produkt {

	const HANDLE      = 'dawmac-galeria';
	const OPT_MIEJSCE = 'dawmac_galeria_miejsce';
	const OPT_ILE     = 'dawmac_galeria_ile';

	public static function init(): void {
		add_action( 'wp_enqueue_scripts', [ __CLASS__, 'zarejestruj_zasoby' ] );
		add_action( 'wp', [ __CLASS__, 'podepnij' ] );
		add_shortcode( 'dawmac_galeria_produktu', [ __CLASS__, 'shortcode' ] );
	}

	public static function zarejestruj_zasoby(): void {
		wp_register_style(
			self::HANDLE,
			DAWMAC_GALERIA_URL . 'assets/galeria.css',
			[],
			DAWMAC_GALERIA_VERSION
		);
		wp_register_script(
			self::HANDLE,
			DAWMAC_GALERIA_URL . 'assets/galeria.js',
			[],
			DAWMAC_GALERIA_VERSION,
			true
		);
	}

	/** Gdzie sekcja ma się pojawić na karcie produktu. */
	public static function miejsce(): string {
		$m = (string) get_option( self::OPT_MIEJSCE, 'zakladka' );

		return in_array( $m, [ 'zakladka', 'pod_zdjeciami', 'pod_cena', 'pod_opisem', 'nie' ], true ) ? $m : 'zakladka';
	}

	/**
	 * Ile zdjęć pokazać. 0 = wszystkie, jakie galeria ma dla tej felgi.
	 *
	 * Przy pasku przewijanym w poziomie liczba zdjęć nie kosztuje wysokości
	 * strony, więc obcinanie do kilkunastu nie ma sensu — Forzza Titan ma
	 * w galerii 31 aut, a pokazywaliśmy 16. Górna granica 60 pochodzi
	 * z endpointu galerii.
	 */
	public static function ile(): int {
		$n = (int) get_option( self::OPT_ILE, 24 );

		return $n > 0 ? min( $n, 60 ) : 60;
	}

	public static function podepnij(): void {
		if ( ! function_exists( 'is_product' ) || ! is_product() ) {
			return;
		}

		$miejsce = self::miejsce();

		if ( 'nie' === $miejsce ) {
			return;
		}

		if ( 'zakladka' === $miejsce ) {
			add_filter( 'woocommerce_product_tabs', [ __CLASS__, 'zakladka' ] );
			return;
		}

		if ( 'pod_zdjeciami' === $miejsce ) {
			/*
			 * Zaraz pod zdjęciami produktu. WooCommerce wypisuje galerię
			 * zdjęć na tym haku z priorytetem 20, więc wchodzimy na 21.
			 */
			add_action( 'woocommerce_before_single_product_summary', [ __CLASS__, 'wypisz' ], 21 );
			return;
		}

		if ( 'pod_cena' === $miejsce ) {
			/*
			 * Zaraz pod ceną i formularzem, PRZED opisem i zakładkami
			 * (WooCommerce wypisuje zakładki na tym haku z priorytetem 10).
			 *
			 * Przy pozycji "w zakładce" sekcja lądowała 2900 px w dół na
			 * stronie mającej 8500 px — technicznie widoczna, praktycznie
			 * nie do znalezienia.
			 */
			add_action( 'woocommerce_after_single_product_summary', [ __CLASS__, 'wypisz' ], 9 );
			return;
		}

		// Pod opisem i zakładkami, przed produktami powiązanymi.
		add_action( 'woocommerce_after_single_product_summary', [ __CLASS__, 'wypisz' ], 16 );
	}

	public static function zakladka( array $zakladki ): array {
		$projekty = self::projekty_biezacego_produktu();

		if ( ! $projekty ) {
			return $zakladki;
		}

		$zakladki['dawmac_galeria'] = [
			'title'    => sprintf( 'Na autach klientów (%d)', count( $projekty ) ),
			// Zaraz pod opisem: WooCommerce daje opisowi 10, informacjom
			// dodatkowym 20, opiniom 30. Zdjęcia klientów są ciekawsze niż
			// tabelka parametrów, więc wchodzą przed nią.
			'priority' => 15,
			'callback' => static function () use ( $projekty ): void {
				// Nagłówek renderujemy ZAWSZE, także w trybie zakładki.
				// Motyw tego sklepu ukrywa nawigację zakładek i wypisuje panele
				// jeden pod drugim, więc bez własnego tytułu sekcja wyglądałaby
				// jak przypadkowa siatka zdjęć doklejona pod opisem.
				echo self::html( $projekty, true ); // phpcs:ignore WordPress.Security.EscapeOutput
			},
		];

		return $zakladki;
	}

	public static function wypisz(): void {
		$projekty = self::projekty_biezacego_produktu();

		if ( $projekty ) {
			echo self::html( $projekty, true ); // phpcs:ignore WordPress.Security.EscapeOutput
		}
	}

	public static function shortcode( $atts ): string {
		$atts = shortcode_atts( [ 'id' => 0, 'ile' => 0 ], $atts, 'dawmac_galeria_produktu' );

		$id = (int) $atts['id'] ?: get_the_ID();
		$projekty = self::projekty_produktu( (int) $id, (int) $atts['ile'] ?: self::ile() );

		return $projekty ? self::html( $projekty, true ) : '';
	}

	private static function projekty_biezacego_produktu(): array {
		static $cache = null;

		if ( null !== $cache ) {
			return $cache;
		}

		$cache = self::projekty_produktu( (int) get_the_ID(), self::ile() );

		return $cache;
	}

	private static function projekty_produktu( int $product_id, int $ile ): array {
		if ( ! $product_id ) {
			return [];
		}

		$felga = Dawmac_Galeria_Norm::felga_produktu( $product_id );

		if ( null === $felga ) {
			return [];
		}

		return Dawmac_Galeria_API::projekty_dla_felgi( $felga['brand'], $felga['model'], $ile );
	}

	/**
	 * Siatka miniatur. Zdjęcie w pełnym rozmiarze ładuje się dopiero po
	 * kliknięciu — na karcie produktu leci wyłącznie thumb700_.
	 */
	private static function html( array $projekty, bool $z_naglowkiem ): string {
		ob_start();

		wp_enqueue_style( self::HANDLE );
		wp_enqueue_script( self::HANDLE );
		?>
		<section class="dmg">
			<?php if ( $z_naglowkiem ) : ?>
				<h2 class="dmg-naglowek">
					Tak wyglądają na autach klientów
					<span class="dmg-licznik"><?php echo (int) count( $projekty ); ?></span>
				</h2>
			<?php endif; ?>

			<p class="dmg-przewin">Przesuń w bok, żeby zobaczyć więcej &rarr;</p>

			<div class="dmg-siatka">
				<?php foreach ( $projekty as $p ) : ?>
					<?php
					$zdjecia = is_array( $p['images'] ?? null ) ? $p['images'] : [];
					if ( ! $zdjecia ) {
						continue;
					}

					$pierwsze  = (string) $zdjecia[0];
					$miniatura = Dawmac_Galeria_API::url_miniatury( $pierwsze );
					$pelne     = Dawmac_Galeria_API::url_zdjecia( $pierwsze );
					$auto      = trim( (string) ( $p['car'] ?? '' ) );
					$parametry = trim( (string) ( $p['params'] ?? '' ) );
					$opis      = $auto !== '' ? $auto : 'Auto klienta';
					?>
					<figure class="dmg-kafel">
						<button type="button"
							class="dmg-klik"
							data-pelne="<?php echo esc_url( $pelne ); ?>"
							data-opis="<?php echo esc_attr( trim( $opis . ' · ' . $parametry, ' ·' ) ); ?>"
							aria-label="<?php echo esc_attr( 'Powiększ: ' . $opis ); ?>">
							<img src="<?php echo esc_url( $miniatura ); ?>"
								alt="<?php echo esc_attr( $opis ); ?>"
								loading="lazy"
								decoding="async"
								onerror="this.onerror=null;this.src='<?php echo esc_url( $pelne ); ?>'">
						</button>
						<?php if ( '' !== $auto || '' !== $parametry ) : ?>
							<figcaption class="dmg-podpis">
								<?php if ( '' !== $auto ) : ?>
									<strong><?php echo esc_html( $auto ); ?></strong>
								<?php endif; ?>
								<?php if ( '' !== $parametry ) : ?>
									<span><?php echo esc_html( $parametry ); ?></span>
								<?php endif; ?>
							</figcaption>
						<?php endif; ?>
					</figure>
				<?php endforeach; ?>
			</div>

			<a class="dmg-wiecej" href="<?php echo esc_url( home_url( '/galeria/' ) ); ?>">
				Zobacz całą galerię realizacji
			</a>
		</section>
		<?php
		return (string) ob_get_clean();
	}
}
