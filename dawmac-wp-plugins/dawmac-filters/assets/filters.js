/**
 * Dawmac Filters - frontend.
 *
 * Zasada działania: stan filtrów w obiekcie `state`; każda zmiana woła lekki
 * endpoint i podmienia siatkę. Listy opcji sidebar dostaje RAZ przy pierwszym
 * requeście (options=1, serwowane z cache) - kolejne kliknięcia zwracają
 * wyłącznie wyniki, więc są błyskawiczne. Bez counterów (decyzja projektowa).
 * Stan jest lustrzany w URL, więc linki z filtrami dają się udostępniać.
 */
(function () {
	'use strict';

	const cfgEl = document.getElementById('dawmac-filters-config');
	const root  = document.getElementById('dawmac-filters');
	if (!cfgEl || !root) return;
	const cfg = JSON.parse(cfgEl.textContent);

	const sidebar  = root.querySelector('.dawmac-sidebar');
	const searchEl = root.querySelector('.dawmac-search-input');
	const chipsEl  = root.querySelector('.dawmac-chips');
	const grid    = root.querySelector('.dawmac-grid');
	const totalEl = root.querySelector('.dawmac-total');
	const sortEl  = root.querySelector('.dawmac-sort');
	const moreBtn = root.querySelector('.dawmac-more');

	const state = {
		f: {},            // { pa_srednica: ['17'], ... }  (checkboxy)
		s: '',            // fraza z pola SZUKAJ
		price_min: '', price_max: '',
		et_min: '', et_max: '',
		instock: false,
		orderby: '',
		page: 1,
	};

	// Pełne listy opcji (slug -> {label}) - przychodzą raz z endpointu.
	let options = null;

	// ---- URL <-> stan --------------------------------------------------------

	// Parametry endpointu (s, page, price_min...) NIE mogą lądować w URL strony
	// jako gołe klucze - 's' i 'page' to zarezerwowane zmienne zapytania
	// WordPressa (hard reload/udostępniony link dawałby wyszukiwarkę WP / 404).
	// W URL strony prefiksujemy wszystko 'df_', endpoint dostaje czyste klucze.
	const RANGE_KEYS = ['price_min', 'price_max', 'et_min', 'et_max'];

	function readStateFromUrl() {
		const p = new URLSearchParams(location.search);
		for (const [key, val] of p.entries()) {
			const m = key.match(/^df_f\[(pa_[a-z0-9_-]+)\]$/);
			if (m) state.f[m[1]] = val.split(',');
		}
		for (const k of RANGE_KEYS) {
			state[k] = p.get('df_' + k) || '';
		}
		state.s       = p.get('df_s') || '';
		state.instock = p.get('df_instock') === '1';
		state.orderby = p.get('df_orderby') || '';
	}

	// Buduje parametry; prefix '' dla endpointu, 'df_' dla URL strony.
	function buildParams(prefix) {
		prefix = prefix || '';
		const p = new URLSearchParams();
		for (const attr in state.f) {
			if (state.f[attr].length) p.set(prefix + 'f[' + attr + ']', state.f[attr].join(','));
		}
		for (const k of RANGE_KEYS) {
			if (state[k]) p.set(prefix + k, state[k]);
		}
		if (state.s) p.set(prefix + 's', state.s);
		if (state.instock) p.set(prefix + 'instock', '1');
		if (state.orderby) p.set(prefix + 'orderby', state.orderby);
		return p;
	}

	function syncUrl() {
		const q = buildParams('df_').toString();
		history.replaceState(null, '', q ? '?' + q : location.pathname);
	}

	// ---- Zapytanie do endpointu ---------------------------------------------

	let inflight = null;
	let reqSeq   = 0;      // monotoniczny numer żądania - chroni przed wyścigami
	let bootFailed = false;

	async function fetchResults(append) {
		const p = buildParams();
		p.set('page', String(state.page));
		p.set('per_page', String(cfg.perPage));
		if (!options) p.set('options', '1'); // listy opcji tylko za pierwszym razem

		if (inflight) inflight.abort();
		inflight = new AbortController();
		const myId = ++reqSeq;

		try {
			const res  = await fetch(cfg.endpoint + '?' + p.toString(), { signal: inflight.signal });
			const data = await res.json();
			// Odrzuć spóźnioną odpowiedź: w międzyczasie wystartowało nowsze
			// żądanie (np. filtr kliknięty w trakcie ładowania "Pokaż więcej").
			if (myId !== reqSeq) return;
			bootFailed = false;
			if (data.options) {
				options = data.options;
				renderSidebar();
			}
			render(data, append);
		} catch (e) {
			if (e.name === 'AbortError') return;
			console.error('dawmac-filters:', e);
			// Błąd pierwszego żądania zostawiłby sidebar pusty na zawsze
			// (options=null) - pokaż komunikat z ponowieniem.
			if (!options && !bootFailed) {
				bootFailed = true;
				grid.innerHTML = '<p class="dawmac-empty">Nie udało się wczytać felg. '
					+ '<button type="button" class="dawmac-retry">Spróbuj ponownie</button></p>';
			}
		}
	}

	// Zmiana filtrów: NIE przebudowujemy całego sidebara (to gubiło fokus
	// i kursor w polach zakresu ET/Cena). Aktualizujemy tylko chipy i liczniki
	// „ile aktywnych" w miejscu; pełny renderSidebar robimy wyłącznie tam,
	// gdzie trzeba przełączyć checkboxy (start, chip-remove, Wyczyść).
	function apply() {
		state.page = 1;
		moreBtn.hidden = true;   // ukryj natychmiast - dojdzie po odpowiedzi
		syncUrl();
		renderChips();
		updateBadges();
		fetchResults(false);
	}

	// Wariant z pełną przebudową sidebara - dla akcji, które muszą zmienić
	// stan checkboxów/pól (usunięcie chipa, „Wyczyść"). Fokus nie jest wtedy
	// w polu zakresu (klik był na chipie/przycisku), więc rebuild jest bezpieczny.
	function applyFull() {
		state.page = 1;
		moreBtn.hidden = true;
		syncUrl();
		renderSidebar();
		fetchResults(false);
	}

	// Aktualizacja liczników „ile aktywnych" w nagłówkach sekcji bez rebuildu.
	function updateBadges() {
		sidebar.querySelectorAll('details[data-key]').forEach(d => {
			const key = d.dataset.key;
			let count = 0;
			if (key === 'pa_et')       count = (state.et_min || state.et_max) ? 1 : 0;
			else if (key === '_price') count = (state.price_min || state.price_max) ? 1 : 0;
			else                       count = (state.f[key] || []).length;

			const sum = d.querySelector('summary');
			if (!sum) return;
			let badge = sum.querySelector('.df-active');
			if (count) {
				if (!badge) {
					badge = document.createElement('span');
					badge.className = 'df-active';
					sum.appendChild(badge);
				}
				badge.textContent = count;
			} else if (badge) {
				badge.remove();
			}
		});
	}

	// ---- Render wyników ------------------------------------------------------

	function render(data, append) {
		totalEl.innerHTML = 'Znaleziono: <strong>' + data.total + '</strong> felg';

		const cards = data.products.map(cardHtml).join('');
		if (append) {
			grid.insertAdjacentHTML('beforeend', cards);
		} else {
			grid.innerHTML = cards || '<p class="dawmac-empty">Brak felg spełniających kryteria.</p>';
		}

		moreBtn.hidden = data.page * data.per_page >= data.total;
	}

	// ---- Kafelek "Nowa Wizja" - markup i formatery 1:1 ze snippetu nwk_*
	//      sklepu (WPCode "test nowy wygląd"); styluje go sekcja 20
	//      Additional CSS motywu. Niczego nie stylujemy po swojemu. ----

	const NWK_SALE_IMG = 'https://dawmac.pl/wp-content/uploads/2026/02/computer-icons-discounts-and-allowances-sales-red-sale-lable-dc3ddc0a1526425805bbcb50cf90b819.png';

	// nwk_fmt_cena: number_format(0, ',', ' ') + ' zł'
	function nwkCena(v) {
		return Math.round(v).toString().replace(/\B(?=(\d{3})+(?!\d))/g, ' ') + ' zł';
	}
	// nwk_fmt_et: >=4 wartości -> "min-max", inaczej lista po przecinku
	function nwkEt(vals) {
		if (!vals || !vals.length) return '';
		if (vals.length >= 4) {
			const nums = vals.map(v => parseInt(String(v).replace(/[^0-9-]/g, ''), 10)).filter(n => !isNaN(n));
			if (nums.length) return Math.min.apply(null, nums) + '-' + Math.max.apply(null, nums);
		}
		return vals.join(', ');
	}
	// nwk_fmt_rozstaw: >=3 wartości -> 'BLANK'
	function nwkRozstaw(vals) {
		if (!vals || !vals.length) return '';
		return vals.length >= 3 ? 'BLANK' : vals.join(', ');
	}
	// nwk_fmt_normal: dłuższe niż 18 znaków -> 'Custom'
	function nwkNormal(vals) {
		if (!vals || !vals.length) return '';
		const v = vals.join(', ');
		return v.length > 18 ? 'Custom' : v;
	}

	function cardHtml(pr) {
		const promo = pr.regular_price && pr.price && pr.regular_price > pr.price;
		const a = pr.attrs || {};

		// Tytuł jak w nwk_card_html: Producent + Model, fallback nazwa produktu.
		const tytul = (((a.producent || []).join(' ') + ' ' + (a.model || []).join(' ')).trim()) || pr.title;

		// Specs jak nwk_specs_for (felgi): Średnica/Szerokość/Rozstaw/ET; puste pomijane.
		const specs = [
			['Średnica', nwkNormal(a.srednica)],
			['Szerokość', nwkNormal(a.szerokosc)],
			['Rozstaw', nwkRozstaw(a.rozstaw)],
			['ET', nwkEt(a.et)],
		].filter(s => s[1] !== '');

		let html = '<article class="nwk-card">';
		if (promo) {
			html += '<img class="nwk-sale" src="' + NWK_SALE_IMG + '" alt="Promocja" loading="lazy" decoding="async" onerror="this.remove()">';
		}
		if (pr.stock === 'instock') {
			html += '<span class="nwk-stock" role="img" aria-label="Dostępne w magazynie"></span>';
		}
		html += '<a href="' + esc(pr.url) + '" class="nwk-imglink">'
			+ (pr.thumb ? '<img class="nwk-img" loading="lazy" src="' + esc(pr.thumb) + '" alt="" onerror="this.remove()">' : '')
			+ '</a>';
		html += '<a href="' + esc(pr.url) + '" class="nwk-title">' + esc(tytul) + '</a>';
		if (specs.length) {
			html += '<dl class="nwk-specs">'
				+ specs.map(s => '<div class="nwk-spec"><dt>' + s[0] + '</dt><dd>' + esc(s[1]) + '</dd></div>').join('')
				+ '</dl>';
		}
		if (pr.price != null) {
			html += '<div class="nwk-price">'
				+ (promo ? '<span class="nwk-old">' + nwkCena(pr.regular_price) + '</span>' : '')
				+ '<span class="nwk-new">' + nwkCena(pr.price) + '</span>'
				+ '</div>';
		}
		html += '</article>';
		return html;
	}

	// ---- Chipy aktywnych filtrów (nad wynikami) ------------------------------

	function labelFor(attr, slug) {
		return (options && options[attr] && options[attr][slug]) ? options[attr][slug].label : slug;
	}

	function renderChips() {
		const chips = [];

		for (const attr in state.f) {
			for (const slug of state.f[attr]) {
				chips.push({ t: labelFor(attr, slug), attr, slug });
			}
		}
		if (state.et_min || state.et_max) {
			chips.push({ t: 'ET ' + (state.et_min || '…') + '-' + (state.et_max || '…'), range: 'et' });
		}
		if (state.price_min || state.price_max) {
			chips.push({ t: 'Cena ' + (state.price_min || '…') + '-' + (state.price_max || '…') + ' zł', range: 'price' });
		}
		if (state.instock) {
			chips.push({ t: 'W magazynie', range: 'instock' });
		}
		if (state.s) {
			chips.push({ t: 'Szukaj: „' + state.s + '”', range: 's' });
		}

		if (!chips.length) {
			chipsEl.innerHTML = '';
			chipsEl.hidden = true;
			return;
		}

		chipsEl.hidden = false;
		chipsEl.innerHTML = chips.map((c, i) =>
			'<button type="button" class="dawmac-chip" data-i="' + i + '">'
			+ esc(c.t) + '<span aria-hidden="true">×</span></button>'
		).join('') + '<button type="button" class="dawmac-chip dawmac-chip-clear">Wyczyść wszystko</button>';

		chipsEl.querySelectorAll('.dawmac-chip[data-i]').forEach(btn => {
			btn.addEventListener('click', () => {
				const c = chips[+btn.dataset.i];
				if (c.attr) {
					state.f[c.attr] = state.f[c.attr].filter(s => s !== c.slug);
					if (!state.f[c.attr].length) delete state.f[c.attr];
				} else if (c.range === 'et') {
					state.et_min = state.et_max = '';
				} else if (c.range === 'price') {
					state.price_min = state.price_max = '';
				} else if (c.range === 'instock') {
					state.instock = false;
				} else if (c.range === 's') {
					state.s = '';
					searchEl.value = '';
				}
				applyFull();  // trzeba odznaczyć checkbox/wyczyścić pole zakresu
			});
		});
		chipsEl.querySelector('.dawmac-chip-clear').addEventListener('click', clearAll);
	}

	function clearAll() {
		state.f = {};
		state.price_min = state.price_max = state.et_min = state.et_max = '';
		state.s = '';
		searchEl.value = '';
		state.instock = false;
		applyFull();  // odznacz wszystkie checkboxy i wyczyść pola zakresów
	}

	// ---- Sidebar -------------------------------------------------------------

	// Które sekcje są rozwinięte - pamiętamy między przerysowaniami.
	const openState = {};
	const isOpen = (key, dflt) => (key in openState ? openState[key] : dflt);

	function renderSidebar() {
		renderChips();
		if (!options) return;

		sidebar.querySelectorAll('details[data-key]').forEach(d => {
			openState[d.dataset.key] = d.open;
		});

		let html = '';

		for (const attr in cfg.attributes) {
			if (attr === 'pa_et') {
				html += rangeGroup('pa_et', 'ET', 'et', '', state.et_min, state.et_max);
				continue;
			}

			const label  = cfg.attributes[attr];
			const opts   = options[attr] || {};
			const chosen = state.f[attr] || [];

			// Sortowanie: numerycznie jeśli się da (średnice), inaczej alfabetycznie.
			const slugs = Object.keys(opts).sort((a, b) => {
				const na = parseFloat(opts[a].label), nb = parseFloat(opts[b].label);
				if (!isNaN(na) && !isNaN(nb) && na !== nb) return na - nb;
				return opts[a].label.localeCompare(opts[b].label, 'pl');
			});

			html += '<details class="dawmac-group" data-key="' + esc(attr) + '"'
				+ (isOpen(attr, true) ? ' open' : '') + '>'
				+ '<summary>' + esc(label)
				+ (chosen.length ? '<span class="df-active">' + chosen.length + '</span>' : '')
				+ '</summary><ul>';
			for (const slug of slugs) {
				const on = chosen.includes(slug);
				html += '<li><label>'
					+ '<input type="checkbox" data-attr="' + esc(attr) + '" value="' + esc(slug) + '"'
					+ (on ? ' checked' : '') + '>'
					+ '<span>' + esc(opts[slug].label) + '</span></label></li>';
			}
			html += '</ul></details>';
		}

		html += rangeGroup('_price', 'Cena', 'price', ' zł', state.price_min, state.price_max);

		html += '<div class="dawmac-group"><label class="dawmac-stock">'
			+ '<input type="checkbox" class="dawmac-instock"' + (state.instock ? ' checked' : '') + '>'
			+ '<span>W magazynie</span></label></div>';

		html += '<button type="button" class="dawmac-clear">Wyczyść filtry</button>';

		sidebar.innerHTML = html;
	}

	function rangeGroup(key, label, prefix, unit, vMin, vMax) {
		const active = vMin || vMax;
		return '<details class="dawmac-group" data-key="' + key + '"' + (isOpen(key, true) ? ' open' : '') + '>'
			+ '<summary>' + label + (active ? '<span class="df-active">1</span>' : '') + '</summary>'
			+ '<div class="dawmac-price">'
			+ '<input type="number" placeholder="od" data-range="' + prefix + '_min" value="' + esc(vMin) + '">'
			+ '<span>-</span>'
			+ '<input type="number" placeholder="do" data-range="' + prefix + '_max" value="' + esc(vMax) + '">'
			+ '</div></details>';
	}

	function esc(s) {
		return String(s).replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
	}

	// ---- Zdarzenia (delegacja - sidebar jest przerysowywany) ----------------

	sidebar.addEventListener('change', (e) => {
		const t = e.target;
		if (t.matches('input[data-attr]')) {
			const attr = t.dataset.attr;
			const set  = new Set(state.f[attr] || []);
			t.checked ? set.add(t.value) : set.delete(t.value);
			state.f[attr] = [...set];
			if (!state.f[attr].length) delete state.f[attr];
			apply();
		} else if (t.matches('.dawmac-instock')) {
			state.instock = t.checked;
			apply();
		} else if (t.matches('input[data-range]')) {
			state[t.dataset.range] = t.value;
			apply();
		}
	});

	sidebar.addEventListener('click', (e) => {
		if (e.target.matches('.dawmac-clear')) clearAll();
	});

	sortEl.addEventListener('change', () => {
		state.orderby = sortEl.value;
		apply();
	});

	// SZUKAJ: debounce 300 ms, żeby nie strzelać requestem na każdą literę.
	let searchTimer = null;
	searchEl.addEventListener('input', () => {
		clearTimeout(searchTimer);
		searchTimer = setTimeout(() => {
			state.s = searchEl.value.trim();
			apply();
		}, 300);
	});

	moreBtn.addEventListener('click', () => {
		// Blokada na czas doładowania - bez niej podwójny klik zwiększa page
		// dwa razy i pierwsze (przerwane) żądanie gubi całą stronę wyników.
		if (moreBtn.disabled) return;
		moreBtn.disabled = true;
		state.page++;
		fetchResults(true).finally(() => { moreBtn.disabled = false; });
	});

	// Ponowienie po nieudanym pierwszym załadowaniu.
	grid.addEventListener('click', (e) => {
		if (e.target.matches('.dawmac-retry')) {
			bootFailed = false;   // bez resetu drugi błąd zostawiał pusty ekran
			grid.innerHTML = '';
			fetchResults(false);
		}
	});

	// ---- Start ---------------------------------------------------------------

	readStateFromUrl();
	if (state.orderby) sortEl.value = state.orderby;
	if (state.s) searchEl.value = state.s;
	fetchResults(false);
})();
