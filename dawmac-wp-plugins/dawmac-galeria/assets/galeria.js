/**
 * Powiększenie zdjęcia klienta po kliknięciu.
 *
 * Na karcie produktu ładują się wyłącznie miniatury (thumb700_, ~70 KB).
 * Pełny plik (~400 KB) pobiera się dopiero tutaj, po świadomym kliknięciu.
 */
( function () {
	'use strict';

	var box = null;
	var img = null;
	var opis = null;
	var wyzwalacz = null;

	function zbuduj() {
		if ( box ) {
			return;
		}

		box = document.createElement( 'div' );
		box.className = 'dmg-lightbox';
		box.setAttribute( 'role', 'dialog' );
		box.setAttribute( 'aria-modal', 'true' );
		box.setAttribute( 'aria-label', 'Powiększone zdjęcie' );
		box.hidden = true;
		box.innerHTML =
			'<button type="button" class="dmg-lightbox-zamknij" aria-label="Zamknij">&times;</button>' +
			'<img alt="">' +
			'<p class="dmg-lightbox-opis"></p>';

		document.body.appendChild( box );

		img = box.querySelector( 'img' );
		opis = box.querySelector( '.dmg-lightbox-opis' );

		box.querySelector( '.dmg-lightbox-zamknij' ).addEventListener( 'click', zamknij );
		box.addEventListener( 'click', function ( ev ) {
			if ( ev.target === box ) {
				zamknij();
			}
		} );
	}

	function otworz( btn ) {
		zbuduj();

		wyzwalacz = btn;
		img.src = btn.getAttribute( 'data-pelne' );
		img.alt = btn.getAttribute( 'data-opis' ) || '';
		opis.textContent = btn.getAttribute( 'data-opis' ) || '';

		box.hidden = false;
		document.body.classList.add( 'dmg-blokada' );
		box.querySelector( '.dmg-lightbox-zamknij' ).focus();
	}

	function zamknij() {
		if ( ! box || box.hidden ) {
			return;
		}

		box.hidden = true;
		// Czyścimy src, żeby przeglądarka nie trzymała pełnego zdjęcia w pamięci.
		img.src = '';
		document.body.classList.remove( 'dmg-blokada' );

		if ( wyzwalacz && document.contains( wyzwalacz ) ) {
			wyzwalacz.focus();
		}

		wyzwalacz = null;
	}

	document.addEventListener( 'click', function ( ev ) {
		var btn = ev.target.closest ? ev.target.closest( '.dmg-klik' ) : null;

		if ( btn ) {
			ev.preventDefault();
			otworz( btn );
		}
	} );

	document.addEventListener( 'keydown', function ( ev ) {
		if ( 'Escape' === ev.key ) {
			zamknij();
		}
	} );
}() );
