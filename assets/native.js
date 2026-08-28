/**
 * Dawmac Filters - tryb natywny, ścieżka szybka.
 *
 * Pierwsze wejście renderuje NATYWNY WordPress (kafelki ze snippetu nwk,
 * CSS sklepu - zero zmian). Dopiero interakcje z filtrami omijają pełny
 * render strony (~2,7 s na hostingu): pobierają JSON z lekkiego endpointu
 * (~0,2 s) i budują kafelki DOKŁADNIE w markupie nwk ze snippetu sklepu,
 * podmieniając zawartość natywnej siatki ul.products.
 *
 * Lustrzane zachowania snippetów sklepu:
 *  - "ukryj opony": na stronie sklepu endpoint dostaje not_cat=opony,
 *  - "Sortowanie tylko po cenie": przechwytujemy natywny select (price/price-desc),
 *  - "licznik produktów": usunięty w sklepie - więc niczego nie doliczamy,
 *  - 32 produkty na stronę (loop_shop_per_page ze snippetu nwk).
 *
 * Każdy błąd = zwykła nawigacja z parametrami df_* - serwerowa ścieżka
 * post__in renderuje wtedy identyczny wynik natywnie.
 */
(function () {
	'use strict';

	const form = document.getElementById('dawmac-native');
	if (!form) return;

	const ENDPOINT = form.dataset.endpoint;
	const IS_SHOP  = form.dataset.shop === '1';
	const CAT      = form.dataset.cat || '';
	const PER_PAGE = 32; // parytet ze snippetem nwk (loop_shop_per_page)

	const NWK_SALE_IMG = 'https://dawmac.pl/wp-content/uploads/2026/02/computer-icons-discounts-and-allowances-sales-red-sale-lable-dc3ddc0a1526425805bbcb50cf90b819.png';

	let page    = 1;
	let orderby = detectNativeOrderby();

	// ---------------------------------------------------------------------
	// Stan formularza -> parametry
	// ---------------------------------------------------------------------

	function formParams() {
		const data = new FormData(form);
		const p = new URLSearchParams();
		for (const [k, v] of data.entries()) {
			if (String(v).trim() !== '') p.append(k, v);
		}
		return p;
	}

	function hasAnyFilter(p) {
		for (const k of p.keys()) {
			if (k.startsWith('df_')) return true;
		}
		return false;
	}

	// URL strony (do history/pushState i fallbacku) - czyste parametry df_*.
	function pageUrl() {
		const p = formParams();
		if (orderby) p.set('orderby', orderby === 'price_asc' ? 'price' : 'price-desc');
		const qs = p.toString();
		return location.pathname + (qs ? '?' + qs : '');
	}

	// Parametry endpointu (df_f[..] -> f[..] itd.).
	function endpointParams() {
		const src = formParams();
		const p = new URLSearchParams();
		for (const [k, v] of src.entries()) {
			if (k.startsWith('df_f[')) p.append(k.slice(3), v);           // df_f[pa_x][] -> f[pa_x][]
			else if (k === 'df_s') p.set('s', v);
			else if (k === 'df_instock') p.set('instock', '1');
			else if (k.startsWith('df_')) p.set(k.slice(3), v);            // df_price_min -> price_min
		}
		if (IS_SHOP) p.set('not_cat', 'opony');                            // mirror: "ukryj opony"
		if (CAT) p.append('f[product_cat]', CAT);                          // kategoria: zostań w niej
		if (orderby) p.set('orderby', orderby);
		p.set('page', String(page));
		p.set('per_page', String(PER_PAGE));
		return p;
	}

	function detectNativeOrderby() {
		const sel = document.querySelector('.woocommerce-ordering select.orderby');
		if (!sel) return '';
		if (sel.value === 'price') return 'price_asc';
		if (sel.value === 'price-desc') return 'price_desc';
		return '';
	}

	// ---------------------------------------------------------------------
	// Kafelek nwk - markup 1:1 ze snippetu sklepu (styluje ARKUSZ GLOWNY)
	// ---------------------------------------------------------------------

	function esc(s) {
		return String(s).replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
	}
	function nwkCena(v) {
		return Math.round(v).toString().replace(/\B(?=(\d{3})+(?!\d))/g, ' ') + ' zł';
	}
	function nwkEt(vals) {
		if (!vals || !vals.length) return '';
		if (vals.length >= 4) {
			const nums = vals.map(v => parseInt(String(v).replace(/[^0-9-]/g, ''), 10)).filter(n => !isNaN(n));
			if (nums.length) return Math.min.apply(null, nums) + '-' + Math.max.apply(null, nums);
		}
		return vals.join(', ');
	}
	function nwkRozstaw(vals) {
		if (!vals || !vals.length) return '';
		return vals.length >= 3 ? 'BLANK' : vals.join(', ');
	}
	function nwkNormal(vals) {
		if (!vals || !vals.length) return '';
		const v = vals.join(', ');
		return v.length > 18 ? 'Custom' : v;
	}

	function cardHtml(pr) {
		const promo = pr.regular_price && pr.price && pr.regular_price > pr.price;
		const a = pr.attrs || {};
		const tytul = (((a.producent || []).join(' ') + ' ' + (a.model || []).join(' ')).trim()) || pr.title;

		// Opona czy felga? Jak nwk_is_opona() w snippecie sklepu: po kategorii.
		const isOpona = (a.cat || []).indexOf('opony') !== -1;
		const specs = (isOpona
			? [
				['Szerokość', nwkNormal(a.szerokosc_opony)],
				['Profil', nwkNormal(a.profil)],
				['Średnica', nwkNormal(a.srednica_opony)],
			]
			: [
				['Średnica', nwkNormal(a.srednica)],
				['Szerokość', nwkNormal(a.szerokosc)],
				['Rozstaw', nwkRozstaw(a.rozstaw)],
				['ET', nwkEt(a.et)],
			]
		).filter(s => s[1] !== '');

		let h = '<article class="nwk-card">';
		if (promo) h += '<img class="nwk-sale" src="' + NWK_SALE_IMG + '" alt="Promocja" loading="lazy" decoding="async" onerror="this.remove()">';
		if (pr.stock === 'instock') h += '<span class="nwk-stock" role="img" aria-label="Dostępne w magazynie"></span>';
		h += '<a href="' + esc(pr.url) + '" class="nwk-imglink">'
			+ (pr.thumb ? '<img class="nwk-img" loading="lazy" src="' + esc(pr.thumb) + '" alt="" onerror="this.remove()">' : '')
			+ '</a>'
			+ '<a href="' + esc(pr.url) + '" class="nwk-title">' + esc(tytul) + '</a>';
		if (specs.length) {
			h += '<dl class="nwk-specs">'
				+ specs.map(s => '<div class="nwk-spec"><dt>' + s[0] + '</dt><dd>' + esc(s[1]) + '</dd></div>').join('')
				+ '</dl>';
		}
		if (pr.price != null) {
			h += '<div class="nwk-price">'
				+ (promo ? '<span class="nwk-old">' + nwkCena(pr.regular_price) + '</span>' : '')
				+ '<span class="nwk-new">' + nwkCena(pr.price) + '</span>'
				+ '</div>';
		}
		return h + '</article>';
	}

	// ---------------------------------------------------------------------
	// Chipy wybranych filtrów nad siatką - nasz design (.dawmac-chip
	// z filters.css). Celowo NIE markup wpc-* Filter Everything: jego
	// bazowe style (obramowanie/ikonka) żyły w CSS wtyczki FE, która po
	// przełączeniu jest wyłączona - zostawały gołe/nachodzące style.
	// ---------------------------------------------------------------------

	function labelForInput(input) {
		const span = input.closest('label') && input.closest('label').querySelector('span');
		return span ? span.textContent.trim() : input.value;
	}

	function chipsData() {
		const chips = [];
		form.querySelectorAll('input[type="checkbox"][name^="df_f"]:checked').forEach(b => {
			chips.push({ t: labelForInput(b), clear: () => { b.checked = false; } });
		});
		const rng = (minName, maxName, label, unit) => {
			const mi = form.querySelector('[name="' + minName + '"]');
			const ma = form.querySelector('[name="' + maxName + '"]');
			if ((mi && mi.value) || (ma && ma.value)) {
				chips.push({
					t: label + ' ' + ((mi && mi.value) || '…') + '-' + ((ma && ma.value) || '…') + (unit || ''),
					clear: () => { if (mi) mi.value = ''; if (ma) ma.value = ''; },
				});
			}
		};
		rng('df_et_min', 'df_et_max', 'ET', '');
		rng('df_price_min', 'df_price_max', 'Cena', ' zł');
		const s = form.querySelector('[name="df_s"]');
		if (s && s.value.trim()) chips.push({ t: 'Szukaj: „' + s.value.trim() + '”', clear: () => { s.value = ''; } });
		const st = form.querySelector('[name="df_instock"]');
		if (st && st.checked) chips.push({ t: 'W magazynie', clear: () => { st.checked = false; } });
		return chips;
	}

	function renderChips() {
		document.querySelectorAll('.dawmac-chips-bar').forEach(n => n.remove());
		const chips = chipsData();
		const list = grid();
		if (!chips.length || !list) return;

		const wrap = document.createElement('div');
		wrap.className = 'dawmac-chips-bar';
		wrap.innerHTML = chips.map((c, i) =>
				'<button type="button" class="dawmac-chip" data-chip="' + i + '">'
				+ esc(c.t) + '<span aria-hidden="true">×</span></button>').join('')
			+ '<button type="button" class="dawmac-chip dawmac-chip-clear" data-chip-reset="1">Wyczyść wszystko</button>';
		list.parentNode.insertBefore(wrap, list);

		wrap.addEventListener('click', (e) => {
			const btn = e.target.closest('button');
			if (!btn) return;
			if (btn.dataset.chipReset) {
				const clear = form.querySelector('.dawmac-clear');
				if (clear) clear.click();
				return;
			}
			const c = chips[+btn.dataset.chip];
			if (c) { c.clear(); applyFilters(); }
		});
	}

	// Paginacja w markupie WooCommerce (styluje ją CSS sklepu).
	function paginationHtml(current, totalPages) {
		if (totalPages <= 1) return '';
		const li = [];
		const add = (n) => li.push(n === current
			? '<li><span aria-current="page" class="page-numbers current">' + n + '</span></li>'
			: '<li><a class="page-numbers" data-page="' + n + '" href="#">' + n + '</a></li>');
		const dots = () => li.push('<li><span class="page-numbers dots">…</span></li>');

		add(1);
		if (current > 3) dots();
		for (let n = Math.max(2, current - 1); n <= Math.min(totalPages - 1, current + 1); n++) add(n);
		if (current < totalPages - 2) dots();
		if (totalPages > 1) add(totalPages);
		if (current < totalPages) li.push('<li><a class="next page-numbers" data-page="' + (current + 1) + '" href="#">→</a></li>');
		return '<nav class="woocommerce-pagination"><ul class="page-numbers">' + li.join('') + '</ul></nav>';
	}

	// ---------------------------------------------------------------------
	// Podmiana siatki
	// ---------------------------------------------------------------------

	function grid() {
		const g = document.querySelector('#primary ul.products') || document.querySelector('ul.products');
		if (g) {
			// Te same klasy dokleja serwerowo snippet nwk sklepu
			// (woocommerce_product_loop_start + body_class) - dublujemy je
			// idempotentnie, żeby siatka/odstępy działały też tam, gdzie
			// snippet nie zdążył (np. środowisko dev bez WPCode).
			g.classList.add('nwk-wrap');
			document.body.classList.add('nowa-wizja-sklepu');
		}
		return g;
	}

	let inflight = null;

	async function refresh() {
		const url  = pageUrl();
		const list = grid();
		const p    = endpointParams();

		// Bez żadnych filtrów wracamy do w pełni natywnej strony (cache
		// LiteSpeed ją serwuje) - czysty stan = czysty WordPress.
		if (!hasAnyFilter(formParams()) && !orderby) { location.href = url; return; }

		if (!list || !ENDPOINT || !window.fetch) { location.href = url; return; }

		if (inflight) inflight.abort();
		inflight = new AbortController();
		list.style.opacity = '.4';

		try {
			const res  = await fetch(ENDPOINT + '?' + p.toString(), { signal: inflight.signal });
			const data = await res.json();

			list.innerHTML = data.products.map(cardHtml).join('')
				|| '<li class="woocommerce-info" style="grid-column:1/-1;list-style:none">Brak felg spełniających kryteria.</li>';
			list.style.opacity = '';

			// Paginacja: nasza, w markupie Woo; natywną chowamy.
			document.querySelectorAll('#primary .woocommerce-pagination').forEach(n => n.remove());
			const totalPages = Math.max(1, Math.ceil(data.total / data.per_page));
			if (totalPages > 1) {
				list.insertAdjacentHTML('afterend', paginationHtml(data.page, totalPages));
			}

			renderChips();
			history.pushState({ dawmac: 1 }, '', url);
			window.scrollTo({ top: 0, behavior: 'smooth' });
		} catch (e) {
			if (e.name !== 'AbortError') location.href = url; // pełny fallback
		}
	}

	function applyFilters() {
		page = 1;
		refresh();
	}

	// ---------------------------------------------------------------------
	// Zdarzenia
	// ---------------------------------------------------------------------

	let timer = null;
	form.addEventListener('change', (e) => {
		if (e.target.matches('input[type="checkbox"]')) applyFilters();
		else { clearTimeout(timer); timer = setTimeout(applyFilters, 250); }
	});
	form.addEventListener('input', (e) => {
		if (e.target.matches('input[type="search"]')) {
			clearTimeout(timer);
			timer = setTimeout(applyFilters, 350);
		}
	});
	form.addEventListener('submit', (e) => { e.preventDefault(); applyFilters(); });

	form.querySelector('.dawmac-clear')?.addEventListener('click', () => {
		const keep = new URLSearchParams();
		if (new URLSearchParams(location.search).get('dawmac_preview')) keep.set('dawmac_preview', '1');
		location.href = location.pathname + (keep.toString() ? '?' + keep.toString() : '');
	});

	// Natywny select sortowania ("Sortowanie tylko po cenie"): przechwyć.
	document.addEventListener('change', (e) => {
		if (!e.target.matches('.woocommerce-ordering select.orderby')) return;
		e.preventDefault();
		e.stopPropagation();
		orderby = e.target.value === 'price-desc' ? 'price_desc'
			: ( e.target.value === 'price' ? 'price_asc' : '' );
		page = 1;
		refresh();
	}, true);
	// Zablokuj submit natywnego formularza sortowania (robimy to sami).
	document.addEventListener('submit', (e) => {
		if (e.target.closest && e.target.closest('.woocommerce-ordering')) e.preventDefault();
	}, true);

	// Nasza paginacja (delegacja - jest przerysowywana).
	document.addEventListener('click', (e) => {
		const a = e.target.closest && e.target.closest('.woocommerce-pagination a[data-page]');
		if (!a) return;
		e.preventDefault();
		page = parseInt(a.dataset.page, 10) || 1;
		refresh();
	});

	// Wstecz/dalej: pełne przeładowanie = spójny stan (serwer odtworzy df_*).
	window.addEventListener('popstate', () => location.reload());

	// Start: jeśli strona przyszła z serwera już przefiltrowana (df_* w URL),
	// pokaż chipy wybranych opcji od razu.
	renderChips();
})();
