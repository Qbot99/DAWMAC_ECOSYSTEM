/**
 * Powiększenie zdjęć klientów: przeglądanie strzałkami, klawiaturą i gestem.
 *
 * Na karcie produktu ładują się wyłącznie miniatury (thumb700_, ~70 KB).
 * Pełny plik (~400 KB) pobiera się dopiero po kliknięciu — i tylko ten,
 * który akurat oglądasz.
 */
( function () {
	'use strict';

	var box = null;
	var img = null;
	var opis = null;
	var licznik = null;
	var wyzwalacz = null;
	var indeks = 0;

	function kafelki() {
		return Array.prototype.slice.call( document.querySelectorAll( '.dmg-klik[data-pelne]' ) );
	}

	function zbuduj() {
		if ( box ) {
			return;
		}

		box = document.createElement( 'div' );
		box.className = 'dmg-lightbox';
		box.setAttribute( 'role', 'dialog' );
		box.setAttribute( 'aria-modal', 'true' );
		box.setAttribute( 'aria-label', 'Zdjęcia klientów' );
		box.hidden = true;

		box.innerHTML =
			'<button type="button" class="dmg-lb-zamknij" aria-label="Zamknij">&times;</button>' +
			'<button type="button" class="dmg-lb-nav dmg-lb-poprzedni" aria-label="Poprzednie zdjęcie">&#8249;</button>' +
			'<figure class="dmg-lb-scena"><img alt=""><figcaption class="dmg-lb-opis"></figcaption></figure>' +
			'<button type="button" class="dmg-lb-nav dmg-lb-nastepny" aria-label="Następne zdjęcie">&#8250;</button>' +
			'<span class="dmg-lb-licznik"></span>';

		document.body.appendChild( box );

		img     = box.querySelector( 'img' );
		opis    = box.querySelector( '.dmg-lb-opis' );
		licznik = box.querySelector( '.dmg-lb-licznik' );

		box.querySelector( '.dmg-lb-zamknij' ).addEventListener( 'click', zamknij );
		box.querySelector( '.dmg-lb-poprzedni' ).addEventListener( 'click', function ( e ) {
			e.stopPropagation();
			przesun( -1 );
		} );
		box.querySelector( '.dmg-lb-nastepny' ).addEventListener( 'click', function ( e ) {
			e.stopPropagation();
			przesun( 1 );
		} );

		// Klik w tło (a nie w zdjęcie ani przycisk) zamyka.
		box.addEventListener( 'click', function ( ev ) {
			if ( ev.target === box || ev.target.classList.contains( 'dmg-lb-scena' ) ) {
				zamknij();
			}
		} );

		// Przesuwanie palcem po zdjęciu.
		var startX = null;
		box.addEventListener( 'touchstart', function ( ev ) {
			startX = ev.touches[ 0 ].clientX;
		}, { passive: true } );

		box.addEventListener( 'touchend', function ( ev ) {
			if ( null === startX ) {
				return;
			}
			var roznica = ev.changedTouches[ 0 ].clientX - startX;
			if ( Math.abs( roznica ) > 45 ) {
				przesun( roznica < 0 ? 1 : -1 );
			}
			startX = null;
		}, { passive: true } );
	}

	function pokaz( i ) {
		var lista = kafelki();

		if ( ! lista.length ) {
			return;
		}

		// Zawijanie: z ostatniego na pierwsze i odwrotnie.
		indeks = ( i + lista.length ) % lista.length;

		var btn = lista[ indeks ];

		img.src = btn.getAttribute( 'data-pelne' );
		img.alt = btn.getAttribute( 'data-opis' ) || '';
		opis.textContent = btn.getAttribute( 'data-opis' ) || '';
		licznik.textContent = ( indeks + 1 ) + ' / ' + lista.length;

		var wielo = lista.length > 1;
		box.querySelector( '.dmg-lb-poprzedni' ).hidden = ! wielo;
		box.querySelector( '.dmg-lb-nastepny' ).hidden = ! wielo;
		licznik.hidden = ! wielo;
	}

	function przesun( kierunek ) {
		pokaz( indeks + kierunek );
	}

	function otworz( btn ) {
		zbuduj();

		wyzwalacz = btn;
		pokaz( kafelki().indexOf( btn ) );

		box.hidden = false;
		document.body.classList.add( 'dmg-blokada' );
		box.querySelector( '.dmg-lb-zamknij' ).focus();
	}

	function zamknij() {
		if ( ! box || box.hidden ) {
			return;
		}

		box.hidden = true;
		// Czyścimy src, żeby przeglądarka nie trzymała pełnego zdjęcia w pamięci.
		img.removeAttribute( 'src' );
		document.body.classList.remove( 'dmg-blokada' );

		if ( wyzwalacz && document.contains( wyzwalacz ) ) {
			wyzwalacz.focus();
		}

		wyzwalacz = null;
	}

	document.addEventListener( 'click', function ( ev ) {
		var btn = ev.target.closest ? ev.target.closest( '.dmg-klik[data-pelne]' ) : null;

		if ( btn ) {
			ev.preventDefault();
			otworz( btn );
		}
	} );

	document.addEventListener( 'keydown', function ( ev ) {
		if ( ! box || box.hidden ) {
			return;
		}

		if ( 'Escape' === ev.key ) {
			zamknij();
		} else if ( 'ArrowLeft' === ev.key ) {
			ev.preventDefault();
			przesun( -1 );
		} else if ( 'ArrowRight' === ev.key ) {
			ev.preventDefault();
			przesun( 1 );
		}
	} );
}() );
