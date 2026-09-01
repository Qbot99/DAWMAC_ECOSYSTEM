<?php
/**
 * Klient HTTP do Allegro REST API.
 *
 * Bierze na siebie trzy rzeczy, ktore inaczej trzeba by powtarzac
 * w kazdym miejscu wolajacym API:
 *
 *  - naglowki wersjonowane (application/vnd.allegro.public.v1+json),
 *  - odswiezenie tokenu i JEDNA ponowna proba po 401,
 *  - limity i awarie: 429 z Retry-After oraz 5xx z narastajacym odstepem.
 *
 * Bledy wracaja jako WP_Error z trescia od Allegro. Serwis odpowiada
 * tablica errors[], gdzie userMessage jest po polsku i nadaje sie
 * wprost do pokazania w panelu - message bywa techniczne.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Dawmac_Allegro_Client {

	const CONTENT_TYPE = 'application/vnd.allegro.public.v1+json';

	/** Ile razy powtarzamy przy 429 i 5xx, zanim odpuscimy. */
	const MAX_RETRIES = 4;

	/** Gdy Allegro nie poda Retry-After - odstep rosnie 1s, 2s, 4s, 8s. */
	const BASE_DELAY = 1;

	/** Gorna granica czekania, zeby request z panelu nie wisial w nieskonczonosc. */
	const MAX_DELAY = 30;

	public static function get( string $path, array $query = [] ) {
		return self::request( 'GET', $path, [ 'query' => $query ] );
	}

	public static function post( string $path, array $body = [] ) {
		return self::request( 'POST', $path, [ 'body' => $body ] );
	}

	public static function put( string $path, array $body = [] ) {
		return self::request( 'PUT', $path, [ 'body' => $body ] );
	}

	public static function patch( string $path, array $body = [] ) {
		return self::request( 'PATCH', $path, [ 'body' => $body ] );
	}

	public static function delete( string $path ) {
		return self::request( 'DELETE', $path );
	}

	/**
	 * Wysylka surowych bajtow (wgrywanie pliku graficznego).
	 * Content-Type musi byc typem obrazka, nie wersjonowanym JSON-em.
	 */
	public static function post_binary( string $path, string $bytes, string $mime ) {
		return self::request( 'POST', $path, [
			'raw_body' => $bytes,
			'headers'  => [ 'Content-Type' => $mime ],
			'timeout'  => 60,
		] );
	}

	/**
	 * @param string $method  GET/POST/PUT/PATCH/DELETE
	 * @param string $path    Sciezka od korzenia API, np. /sale/offers
	 * @param array  $options query, body, headers, timeout
	 * @return array|WP_Error Zdekodowana odpowiedz (pusta tablica przy 204).
	 */
	public static function request( string $method, string $path, array $options = [] ) {
		$token = Dawmac_Allegro_Auth::access_token();

		if ( is_wp_error( $token ) ) {
			return $token;
		}

		$url = Dawmac_Allegro_Auth::api_base() . '/' . ltrim( $path, '/' );

		if ( ! empty( $options['query'] ) ) {
			$url .= '?' . http_build_query( $options['query'] );
		}

		$refreshed = false;
		$attempt   = 0;

		while ( true ) {
			$args = [
				'method'  => $method,
				'timeout' => (int) ( $options['timeout'] ?? 30 ),
				'headers' => array_merge( [
					'Authorization' => 'Bearer ' . $token,
					'Accept'        => self::CONTENT_TYPE,
					'Content-Type'  => self::CONTENT_TYPE,
				], $options['headers'] ?? [] ),
			];

			// raw_body idzie bajt w bajt - tak wgrywamy pliki graficzne,
			// gdzie tresc to same dane obrazka, a nie JSON.
			if ( isset( $options['raw_body'] ) ) {
				$args['body'] = $options['raw_body'];
			} elseif ( isset( $options['body'] ) ) {
				$args['body'] = wp_json_encode( $options['body'] );
			}

			$response = wp_remote_request( $url, $args );

			if ( is_wp_error( $response ) ) {
				// Awaria sieci tez zasluguje na ponowienie - hosting bywa kapryśny.
				if ( $attempt < self::MAX_RETRIES ) {
					self::wait( self::backoff( $attempt++ ) );
					continue;
				}

				return $response;
			}

			$code = wp_remote_retrieve_response_code( $response );
			$body = wp_remote_retrieve_body( $response );

			// 401: token mogl wygasnac wczesniej, niz mowilo expires_in.
			// Odswiezamy RAZ - drugie 401 z nowym tokenem to juz problem uprawnien.
			if ( 401 === $code && ! $refreshed ) {
				$refreshed = true;
				$fresh     = Dawmac_Allegro_Auth::refresh();

				if ( is_wp_error( $fresh ) ) {
					return $fresh;
				}

				$token = $fresh;
				continue;
			}

			// 429 i 5xx - czekamy tyle, ile kaze Allegro, albo wlasnym odstepem.
			if ( ( 429 === $code || $code >= 500 ) && $attempt < self::MAX_RETRIES ) {
				$after = wp_remote_retrieve_header( $response, 'retry-after' );
				$delay = is_numeric( $after ) ? (int) $after : self::backoff( $attempt );

				self::wait( min( $delay, self::MAX_DELAY ) );
				++$attempt;
				continue;
			}

			if ( $code >= 200 && $code < 300 ) {
				return '' === trim( $body ) ? [] : ( json_decode( $body, true ) ?? [] );
			}

			return self::error( $code, $body );
		}
	}

	/**
	 * Przelatuje przez strony i skleja wyniki. Allegro stronicuje przez
	 * offset/limit, a maksymalny limit to zwykle 100.
	 *
	 * @param string $key Nazwa tablicy z danymi w odpowiedzi (np. 'offers').
	 * @return array|WP_Error
	 */
	public static function get_all( string $path, array $query = [], string $key = '', int $cap = 5000 ) {
		$limit  = (int) ( $query['limit'] ?? 100 );
		$offset = 0;
		$out    = [];

		while ( $offset < $cap ) {
			$page = self::get( $path, array_merge( $query, [ 'limit' => $limit, 'offset' => $offset ] ) );

			if ( is_wp_error( $page ) ) {
				return $page;
			}

			$items = '' !== $key
				? ( $page[ $key ] ?? [] )
				: ( is_array( $page ) ? $page : [] );

			if ( ! $items ) {
				break;
			}

			$out     = array_merge( $out, $items );
			$offset += $limit;

			if ( count( $items ) < $limit ) {
				break;
			}
		}

		return $out;
	}

	/** 1s, 2s, 4s, 8s - z gornym ograniczeniem. */
	private static function backoff( int $attempt ): int {
		return (int) min( self::BASE_DELAY * ( 2 ** $attempt ), self::MAX_DELAY );
	}

	/**
	 * Czekanie miedzy probami. W CLI mozemy spac spokojnie; w zwyklym
	 * requescie tez, bo alternatywa jest utrata calej paczki zmian.
	 */
	private static function wait( int $seconds ): void {
		if ( $seconds > 0 ) {
			sleep( $seconds );
		}
	}

	/**
	 * Blad Allegro na WP_Error. userMessage jest po polsku i pisany
	 * pod czlowieka - bierzemy go, gdy jest; message bywa techniczne.
	 */
	private static function error( int $code, string $body ) {
		$data   = json_decode( $body, true );
		$errors = is_array( $data ) ? ( $data['errors'] ?? [] ) : [];

		if ( ! $errors ) {
			return new WP_Error(
				'dawmac_allegro_http_' . $code,
				sprintf( 'Allegro odpowiedziało HTTP %d.', $code ),
				[ 'status' => $code, 'body' => mb_substr( $body, 0, 500 ) ]
			);
		}

		$messages = [];

		foreach ( $errors as $e ) {
			$text = $e['userMessage'] ?? $e['message'] ?? '';
			$path = $e['path'] ?? '';

			if ( '' !== $text ) {
				$messages[] = '' !== $path ? "{$path}: {$text}" : $text;
			}
		}

		return new WP_Error(
			'dawmac_allegro_' . strtolower( (string) ( $errors[0]['code'] ?? 'error' ) ),
			sprintf( '[HTTP %d] %s', $code, implode( ' | ', $messages ) ),
			[ 'status' => $code, 'errors' => $errors ]
		);
	}
}
