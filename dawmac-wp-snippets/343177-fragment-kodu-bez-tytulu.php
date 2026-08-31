/* ==========================================================================
   DAWMAC - Open Graph / Twitter Card
   Dziala niezaleznie od ustawien Yoast. Wykrywa, czy modul OG Yoasta
   jest wlaczony i dostosowuje sie, zeby nie duplikowac tagow.
   ========================================================================== */

if ( ! function_exists( 'dawmac_og_default_image' ) ) {

	/** Domyslny kafel 1200x630. */
	function dawmac_og_default_image() {
		return 'https://dawmac.pl/wp-content/uploads/2026/08/dawmac-og-C-jasny.jpg';
	}

	/** Czy Yoast sam wypisuje tagi og:? */
	function dawmac_yoast_og_active() {
		if ( ! class_exists( 'WPSEO_Options' ) ) {
			return false;
		}
		return (bool) WPSEO_Options::get( 'opengraph', false );
	}

	/** Skraca tekst do dlugosci bezpiecznej dla podgladow. */
	function dawmac_og_trim( $text, $limit = 200 ) {
		$text = trim( preg_replace( '/\s+/', ' ', wp_strip_all_tags( (string) $text ) ) );
		if ( function_exists( 'mb_strlen' ) && mb_strlen( $text ) > $limit ) {
			$text = mb_substr( $text, 0, $limit - 3 ) . '...';
		}
		return $text;
	}

	/**
	 * Buduje komplet danych dla biezacego widoku.
	 * Produkty i wpisy dostaja wlasne zdjecie glowne, reszta kafel domyslny.
	 */
	function dawmac_og_data() {

		$site  = get_bloginfo( 'name' );
		$image = dawmac_og_default_image();
		$width  = 1200;
		$height = 630;
		$type   = 'website';

		$title = 'Felgi aluminiowe - ponad 6500 sztuk w magazynie | Dawmac';
		$desc  = 'Przyjedz i przymierz. BBS, Vossen, OZ Racing, Rotiform, Concaver, Japan Racing. 18 lat na rynku, showroom w Perzowie.';
		$url   = home_url( '/' );

		if ( is_front_page() || is_home() ) {

			// wartosci domyslne powyzej

		} elseif ( is_singular() ) {

			$post_id = get_queried_object_id();
			$title   = get_the_title( $post_id ) . ' | ' . $site;
			$url     = get_permalink( $post_id );
			$type    = ( function_exists( 'is_product' ) && is_product() ) ? 'product' : 'article';

			$raw  = has_excerpt( $post_id )
				? get_the_excerpt( $post_id )
				: get_post_field( 'post_content', $post_id );
			$trim = dawmac_og_trim( $raw );
			if ( '' !== $trim ) {
				$desc = $trim;
			}

			if ( has_post_thumbnail( $post_id ) ) {
				$src = wp_get_attachment_image_src( get_post_thumbnail_id( $post_id ), 'full' );
				if ( ! empty( $src[0] ) ) {
					$image  = $src[0];
					$width  = ! empty( $src[1] ) ? (int) $src[1] : 0;
					$height = ! empty( $src[2] ) ? (int) $src[2] : 0;
				}
			}
		} elseif ( is_tax() || is_category() || is_tag() ) {

			$term = get_queried_object();

			if ( $term && ! is_wp_error( $term ) ) {
				$title    = $term->name . ' | ' . $site;
				$term_url = get_term_link( $term );
				if ( ! is_wp_error( $term_url ) ) {
					$url = $term_url;
				}
				$trim = dawmac_og_trim( term_description( $term ) );
				if ( '' !== $trim ) {
					$desc = $trim;
				}
			}
		} elseif ( is_shop() || is_post_type_archive() ) {

			$title = 'Felgi aluminiowe - katalog | ' . $site;
			if ( function_exists( 'wc_get_page_id' ) && is_shop() ) {
				$shop = get_permalink( wc_get_page_id( 'shop' ) );
				if ( $shop ) {
					$url = $shop;
				}
			}
		} else {
			return false; // 404, wyszukiwarka, feed - nie wypisujemy nic
		}

		$ext  = strtolower( pathinfo( parse_url( $image, PHP_URL_PATH ), PATHINFO_EXTENSION ) );
		$mime = ( 'png' === $ext ) ? 'image/png' : ( ( 'webp' === $ext ) ? 'image/webp' : 'image/jpeg' );

		return compact( 'title', 'desc', 'image', 'width', 'height', 'type', 'url', 'site', 'mime' );
	}

	/* ---------------------------------------------------------------------
	   1. Wlasne tagi og: - tylko gdy Yoast ich nie wypisuje
	   --------------------------------------------------------------------- */
	function dawmac_print_og_tags() {

		if ( is_admin() || is_feed() || is_404() || dawmac_yoast_og_active() ) {
			return;
		}

		$d = dawmac_og_data();
		if ( ! $d ) {
			return;
		}

		$out = array(
			'<meta property="og:type" content="' . esc_attr( $d['type'] ) . '">',
			'<meta property="og:site_name" content="' . esc_attr( $d['site'] ) . '">',
			'<meta property="og:locale" content="pl_PL">',
			'<meta property="og:url" content="' . esc_url( $d['url'] ) . '">',
			'<meta property="og:title" content="' . esc_attr( $d['title'] ) . '">',
			'<meta property="og:description" content="' . esc_attr( $d['desc'] ) . '">',
			'<meta property="og:image" content="' . esc_url( $d['image'] ) . '">',
			'<meta property="og:image:secure_url" content="' . esc_url( $d['image'] ) . '">',
			'<meta property="og:image:type" content="' . esc_attr( $d['mime'] ) . '">',
			'<meta property="og:image:alt" content="' . esc_attr( $d['title'] ) . '">',
		);

		if ( $d['width'] && $d['height'] ) {
			$out[] = '<meta property="og:image:width" content="' . (int) $d['width'] . '">';
			$out[] = '<meta property="og:image:height" content="' . (int) $d['height'] . '">';
		}

		echo "\n<!-- Dawmac OG -->\n" . implode( "\n", $out ) . "\n<!-- /Dawmac OG -->\n";
	}
	add_action( 'wp_head', 'dawmac_print_og_tags', 6 );

	/* ---------------------------------------------------------------------
	   2. Przejecie tagow twitter:, ktore Yoast juz generuje
	   --------------------------------------------------------------------- */
	function dawmac_filter_twitter_image( $image ) {
		$d = dawmac_og_data();
		return $d ? $d['image'] : $image;
	}
	add_filter( 'wpseo_twitter_image', 'dawmac_filter_twitter_image', 999 );

	function dawmac_filter_twitter_title( $title ) {
		$d = dawmac_og_data();
		return $d ? $d['title'] : $title;
	}
	add_filter( 'wpseo_twitter_title', 'dawmac_filter_twitter_title', 999 );

	function dawmac_filter_twitter_desc( $desc ) {
		$d = dawmac_og_data();
		return $d ? $d['desc'] : $desc;
	}
	add_filter( 'wpseo_twitter_description', 'dawmac_filter_twitter_desc', 999 );

	/* ---------------------------------------------------------------------
	   3. Gdyby modul OG Yoasta zostal kiedys wlaczony - podmiana obrazka
	   --------------------------------------------------------------------- */
	function dawmac_filter_yoast_og_image( $image ) {
		$d = dawmac_og_data();
		return $d ? $d['image'] : $image;
	}
	add_filter( 'wpseo_opengraph_image', 'dawmac_filter_yoast_og_image', 999 );
}
