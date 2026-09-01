<?php
/**
 * OAuth2 do Allegro.
 *
 * Uzywamy Authorization Code - wtyczka dziala w imieniu konta sprzedawcy,
 * a autoryzacja odbywa sie raz, z panelu WordPressa.
 *
 * DWIE RZECZY, KTORE TU DECYDUJA O WSZYSTKIM:
 *
 * 1. REFRESH TOKEN JEST JEDNORAZOWY. Kazde odswiezenie zwraca nowy i uniewaznia
 *    stary. Jesli zapis nowego nie dojdzie do skutku (blad bazy, rownolegly
 *    proces, timeout w polowie), tracimy dostep i trzeba autoryzowac recznie.
 *    Stad blokada MySQL wokol calego odswiezenia - cron, panel i WP-CLI potrafia
 *    trafic w to samo okno, a przegrany zostalby ze skasowanym tokenem.
 *
 * 2. Kod autoryzacyjny zyje 10 SEKUND. Wymiana musi isc od razu po powrocie
 *    z Allegro, bez zadnego kroku posredniego.
 *
 * Access token: 12 godzin. Refresh token: 3 miesiace.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Dawmac_Allegro_Auth {

	const OPT_TOKENS  = 'dawmac_allegro_tokens';
	const OPT_ENV     = 'dawmac_allegro_env';
	const OPT_CLIENT  = 'dawmac_allegro_client';
	const LOCK_NAME   = 'dawmac_allegro_refresh';

	/** Odswiezamy z zapasem - nie czekamy, az token wygasnie w polowie synchronizacji. */
	const REFRESH_MARGIN = 300;

	const ENDPOINTS = [
		'sandbox'    => [
			'auth' => 'https://allegro.pl.allegrosandbox.pl',
			'api'  => 'https://api.allegro.pl.allegrosandbox.pl',
			'apps' => 'https://apps.developer.allegro.pl.allegrosandbox.pl',
		],
		'production' => [
			'auth' => 'https://allegro.pl',
			'api'  => 'https://api.allegro.pl',
			'apps' => 'https://apps.developer.allegro.pl',
		],
	];

	/** 'sandbox' albo 'production'. Domyslnie sandbox - produkcja swiadomie. */
	public static function env(): string {
		$env = get_option( self::OPT_ENV, 'sandbox' );

		return isset( self::ENDPOINTS[ $env ] ) ? $env : 'sandbox';
	}

	public static function api_base(): string {
		return self::ENDPOINTS[ self::env() ]['api'];
	}

	public static function auth_base(): string {
		return self::ENDPOINTS[ self::env() ]['auth'];
	}

	public static function apps_url(): string {
		return self::ENDPOINTS[ self::env() ]['apps'];
	}

	/**
	 * Dane aplikacji. Stale w wp-config.php maja pierwszenstwo - sekret
	 * nie musi wtedy w ogole lezec w bazie.
	 */
	public static function credentials(): array {
		if ( defined( 'DAWMAC_ALLEGRO_CLIENT_ID' ) && defined( 'DAWMAC_ALLEGRO_CLIENT_SECRET' ) ) {
			return [
				'client_id'     => (string) DAWMAC_ALLEGRO_CLIENT_ID,
				'client_secret' => (string) DAWMAC_ALLEGRO_CLIENT_SECRET,
			];
		}

		$stored = get_option( self::OPT_CLIENT, [] );

		return [
			'client_id'     => (string) ( $stored['client_id'] ?? '' ),
			'client_secret' => (string) ( $stored['client_secret'] ?? '' ),
		];
	}

	public static function is_configured(): bool {
		$c = self::credentials();

		return '' !== $c['client_id'] && '' !== $c['client_secret'];
	}

	public static function is_connected(): bool {
		$t = get_option( self::OPT_TOKENS, [] );

		return ! empty( $t['refresh_token'] );
	}

	/**
	 * URL zwrotny - strona ustawien wtyczki. Musi zgadzac sie co do znaku
	 * z adresem podanym przy rejestracji aplikacji.
	 *
	 * Dokumentacja Allegro wylicza, co jest w redirect_uri zabronione (adresy
	 * wewnetrzne, loopback, fragmenty URI), ale o parametrach query milczy.
	 * OAuth2 je dopuszcza i to typowy wzorzec w WordPressie, wiec zostaje.
	 * Filtr jest po to, zeby podmiana na czysta sciezke - gdyby Allegro
	 * grymasilo - nie wymagala edycji kodu. Serwis przyjmuje kilka adresow
	 * zwrotnych na aplikacje, wiec zapasowy mozna zarejestrowac od razu.
	 */
	public static function redirect_uri(): string {
		return (string) apply_filters(
			'dawmac_allegro_redirect_uri',
			admin_url( 'admin.php?page=dawmac-allegro' )
		);
	}

	/**
	 * Adres, na ktory wysylamy sprzedawce, zeby zgodzil sie na dostep.
	 * state chroni przed podrzuceniem cudzego kodu - sprawdzamy go po powrocie.
	 */
	public static function authorize_url(): string {
		$state = wp_generate_password( 24, false );
		set_transient( 'dawmac_allegro_state', $state, 15 * MINUTE_IN_SECONDS );

		return self::auth_base() . '/auth/oauth/authorize?' . http_build_query( [
			'response_type' => 'code',
			'client_id'     => self::credentials()['client_id'],
			'redirect_uri'  => self::redirect_uri(),
			'state'         => $state,
		] );
	}

	/**
	 * Powrot z Allegro. Kod zyje 10 sekund, wiec lecimy z wymiana od razu.
	 *
	 * @return true|WP_Error
	 */
	public static function handle_callback( string $code, string $state ) {
		$expected = get_transient( 'dawmac_allegro_state' );
		delete_transient( 'dawmac_allegro_state' );

		if ( ! $expected || ! hash_equals( (string) $expected, $state ) ) {
			return new WP_Error(
				'dawmac_allegro_state',
				'Nie zgadza się parametr state - autoryzacja przerwana. Zacznij od nowa.'
			);
		}

		return self::token_request( [
			'grant_type'   => 'authorization_code',
			'code'         => $code,
			'redirect_uri' => self::redirect_uri(),
		] );
	}

	/**
	 * Wazny access token. Odswieza sam, gdy zostalo mniej niz REFRESH_MARGIN.
	 *
	 * @return string|WP_Error
	 */
	public static function access_token() {
		$tokens = get_option( self::OPT_TOKENS, [] );

		if ( empty( $tokens['refresh_token'] ) ) {
			return new WP_Error(
				'dawmac_allegro_not_connected',
				'Konto Allegro nie jest połączone - autoryzuj w panelu wtyczki.'
			);
		}

		$expires = (int) ( $tokens['expires_at'] ?? 0 );

		if ( ! empty( $tokens['access_token'] ) && $expires - time() > self::REFRESH_MARGIN ) {
			return (string) $tokens['access_token'];
		}

		return self::refresh();
	}

	/**
	 * Odswiezenie pod blokada.
	 *
	 * Refresh token jest jednorazowy, wiec dwa rownolegle odswiezenia koncza sie
	 * tak, ze jedno dostaje nowa pare, a drugie leci ze skasowanym tokenem
	 * i wywala polaczenie. GET_LOCK jest atomowy po stronie MySQL, wiec dziala
	 * takze miedzy procesami (cron, panel, WP-CLI), czego transient nie zapewnia.
	 *
	 * @return string|WP_Error
	 */
	public static function refresh() {
		global $wpdb;

		$lock = $wpdb->get_var( $wpdb->prepare( 'SELECT GET_LOCK(%s, %d)', self::LOCK_NAME, 15 ) );

		if ( '1' !== (string) $lock ) {
			return new WP_Error(
				'dawmac_allegro_lock',
				'Nie udało się zająć blokady odświeżania tokenu - spróbuj za chwilę.'
			);
		}

		try {
			// Ktos mogl odswiezyc, kiedy czekalismy na blokade - wtedy nie ruszamy.
			wp_cache_delete( self::OPT_TOKENS, 'options' );
			$tokens = get_option( self::OPT_TOKENS, [] );

			if ( ! empty( $tokens['access_token'] )
				&& (int) ( $tokens['expires_at'] ?? 0 ) - time() > self::REFRESH_MARGIN ) {
				return (string) $tokens['access_token'];
			}

			if ( empty( $tokens['refresh_token'] ) ) {
				return new WP_Error( 'dawmac_allegro_not_connected', 'Brak refresh tokenu.' );
			}

			$result = self::token_request( [
				'grant_type'    => 'refresh_token',
				'refresh_token' => (string) $tokens['refresh_token'],
				'redirect_uri'  => self::redirect_uri(),
			] );

			if ( is_wp_error( $result ) ) {
				return $result;
			}

			return (string) ( get_option( self::OPT_TOKENS, [] )['access_token'] ?? '' );
		} finally {
			$wpdb->get_var( $wpdb->prepare( 'SELECT RELEASE_LOCK(%s)', self::LOCK_NAME ) );
		}
	}

	/**
	 * Strzal do /auth/oauth/token. Uwierzytelniamy sie Basic Auth
	 * (client_id:client_secret), tresc idzie jako form-urlencoded.
	 *
	 * @return true|WP_Error
	 */
	private static function token_request( array $body ) {
		$c = self::credentials();

		if ( '' === $c['client_id'] || '' === $c['client_secret'] ) {
			return new WP_Error( 'dawmac_allegro_no_client', 'Brak client_id / client_secret.' );
		}

		$response = wp_remote_post( self::auth_base() . '/auth/oauth/token', [
			'timeout' => 20,
			'headers' => [
				'Authorization' => 'Basic ' . base64_encode( $c['client_id'] . ':' . $c['client_secret'] ),
				'Content-Type'  => 'application/x-www-form-urlencoded',
			],
			'body'    => $body,
		] );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$code = wp_remote_retrieve_response_code( $response );
		$data = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( 200 !== $code || ! is_array( $data ) || empty( $data['access_token'] ) ) {
			$detail = is_array( $data )
				? ( $data['error_description'] ?? $data['error'] ?? '' )
				: '';

			// invalid_grant przy odswiezaniu = refresh token juz zuzyty albo cofnieto
			// zgode. Nie da sie tego naprawic ponowna proba - trzeba autoryzowac od nowa.
			if ( 'invalid_grant' === ( $data['error'] ?? '' ) ) {
				delete_option( self::OPT_TOKENS );

				return new WP_Error(
					'dawmac_allegro_invalid_grant',
					'Allegro odrzuciło refresh token (wygasł, został już użyty albo cofnięto zgodę). '
					. 'Trzeba połączyć konto od nowa w panelu wtyczki.'
				);
			}

			return new WP_Error(
				'dawmac_allegro_token',
				sprintf( 'Allegro odrzuciło żądanie tokenu (HTTP %d). %s', $code, $detail )
			);
		}

		self::store_tokens( $data );

		return true;
	}

	/**
	 * Zapis pary tokenow. autoload = 'no', zeby sekrety nie wisialy w pamieci
	 * przy kazdym zaladowaniu WordPressa.
	 */
	private static function store_tokens( array $data ): void {
		update_option(
			self::OPT_TOKENS,
			[
				'access_token'  => (string) $data['access_token'],
				'refresh_token' => (string) ( $data['refresh_token'] ?? '' ),
				'expires_at'    => time() + (int) ( $data['expires_in'] ?? 43199 ),
				'scope'         => (string) ( $data['scope'] ?? '' ),
				'saved_at'      => time(),
			],
			'no'
		);
	}

	/** Rozlaczenie - kasuje tokeny, zostawia dane aplikacji. */
	public static function disconnect(): void {
		delete_option( self::OPT_TOKENS );
	}
}
