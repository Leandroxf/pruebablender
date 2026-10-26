const $ = (sel, root = document) => root.querySelector(sel);
const $$ = (sel, root = document) => [...root.querySelectorAll(sel)];
document.documentElement.classList.add("js");

const registerEndpoint = new URL("api/registro.php", document.baseURI);
const registerCsrfToken = document.querySelector('meta[name="register-csrf-token"]')?.content || "";
const purchaseEndpoint = new URL("api/compra.php", document.baseURI);
const purchaseCsrfToken = document.querySelector('meta[name="purchases-crf-token"]')?.content || "";
const bookEndpoint = new URL("api/libro.php", document.baseURI);
const userLoggedIn = document.querySelector('meta[name="user-logged-in"]')?.content === "1";

// Libros de pago que el usuario ya compro, indexados por "id:idioma".
const purchasedBooks = new Map();
const registerForm = $("#register-form");
const registerStatus = $("#register-status");

$$("[data-modal-open]").forEach((button) => {
	const modal = document.getElementById(button.dataset.modalOpen);

	button.addEventListener("click", () => {
		$$("dialog[open]").forEach((openDialog) => openDialog.close());
		modal?.showModal();
	});
});

function setRegisterStatus(message, type) {
	if (!registerStatus) return;
	registerStatus.textContent = message;
	registerStatus.className = type ? `admin-message admin-message--${type}` : "admin-help";
}

registerForm?.addEventListener("submit", async (event) => {
	event.preventDefault();
	if (!registerStatus) return;
	setRegisterStatus("Creando cuenta...", null);
	const submitButton = registerForm.querySelector('button[type="submit"]');
	if (submitButton) submitButton.disabled = true;

	const formData = new FormData(registerForm);
	try {
		const response = await fetch(registerEndpoint, {
			method: "POST",
			credentials: "same-origin",
			headers: {
				Accept: "application/json",
				"Content-Type": "application/json",
				"X-CSRF-Token": registerCsrfToken
			},
			body: JSON.stringify({
				name: String(formData.get("name") || "").trim(),
				email: String(formData.get("email") || "").trim(),
				password: String(formData.get("password") || "")
			})
		});
		const data = await response.json();
		if (!response.ok) {
			throw new Error(data.error || "No se pudo crear la cuenta.");
		}
		setRegisterStatus(data.mensaje || "Cuenta creada correctamente. Redirigiendo...", "success");
		registerForm.reset();
		setTimeout(() => window.location.reload(), 900);
	} catch (error) {
		console.error("Error al registrar usuario:", error);
		setRegisterStatus(error.message || "Ocurrió un error al crear la cuenta.", "error");
	} finally {
		if (submitButton) submitButton.disabled = false;
	}
});

const catalog = $(".catalog");

if (catalog && "IntersectionObserver" in window) {
	const catalogObserver = new IntersectionObserver((entries, observer) => {
		if (entries.some((entry) => entry.isIntersecting)) {
			catalog.classList.add("is-visible");
			observer.disconnect();
		}
	}, { threshold: 0.12 });

	catalogObserver.observe(catalog);
	setTimeout(() => catalog.classList.add("is-visible"), 1200);
} else {
	catalog?.classList.add("is-visible");
}

const searchInput = $("#book-search");
const genreButtons = $$("[data-genre-filter]");
const languageButtons = $$("[data-language-filter]");
const bookCards = $$(".book-card");
const bookResults = $("#book-results");
const emptyMessage = $("#book-empty");
let selectedGenre = "all";
let selectedLanguage = "all";

function normalizeText(value) {
	return String(value ?? "").normalize("NFD").replace(/[\u0300-\u036f]/g, "").toLowerCase();
}

function filterBooks() {
	const query = normalizeText(searchInput?.value.trim());
	let visibleCount = 0;

	bookCards.forEach((card) => {
		const searchableText = [
			card.querySelector("h3")?.textContent,
			card.querySelector(".book-card__info > p:last-of-type")?.textContent,
			card.querySelector(".book-card__genre")?.textContent,
			card.dataset.isbn || ""
		].join(" ");
		const matchesSearch = normalizeText(searchableText).includes(query);
		const genres = (card.dataset.genres || card.dataset.genre || "")
			.split(/[,\s]+/)
			.map(normalizeText);
		const matchesGenre = selectedGenre === "all" || genres.includes(normalizeText(selectedGenre));
		const matchesLanguage = selectedLanguage === "all" || (card.dataset.language || "en") === selectedLanguage;

		card.hidden = !matchesSearch || !matchesGenre || !matchesLanguage;
		if (!card.hidden) visibleCount += 1;
	});

	if (bookResults) {
		bookResults.textContent = `Se muestran ${visibleCount} ${visibleCount === 1 ? "libro" : "libros"}`;
	}
	if (emptyMessage) emptyMessage.hidden = visibleCount > 0;
}

searchInput?.addEventListener("input", filterBooks);
$("#barcode-form")?.addEventListener("submit", (event) => {
	event.preventDefault();
	const input = $("#barcode-input");
	const status = $("#barcode-status");
	const code = String(input?.value ?? "").replace(/[^0-9Xx]/g, "");
	if (!code) {
		if (status) status.textContent = "Ingresá un ISBN o ID válido.";
		return;
	}
	const digits = code.replace(/[^0-9]/g, "");
	let found = null;
	for (const card of bookCards) {
		const readerId = card.querySelector("[data-reader-url]")?.dataset.readerUrl.match(/(\d+)\.html$/)?.[1] || "";
		const coverSrc = card.querySelector(".book-cover")?.src || "";
		const isbnInCover = coverSrc.match(/isbn\/([0-9Xx]+)/i)?.[1] || "";
		const isbnInCatalog = (card.dataset.isbn || "").replace(/[^0-9Xx]/g, "");
		const isbnMatches =
			(digits && isbnInCover.replace(/[^0-9]/g, "") === digits) ||
			(digits && isbnInCatalog.replace(/[^0-9]/g, "") === digits);
		if ((digits && readerId === digits) || isbnMatches) {
			found = card;
			break;
		}
	}
	if (found) {
		if (status) status.textContent = "Libro encontrado en tu catálogo. Abriendo…";
		$("#barcode-modal")?.close();
		found.scrollIntoView({ behavior: "smooth", block: "center" });
		found.style.outline = "3px solid #0f766e";
		setTimeout(() => { found.style.outline = ""; }, 2500);
		openBookReader(found.querySelector("[data-reader-url]")?.dataset.readerUrl, found);
	} else if (status) {
		status.textContent = `No se encontró "${code}" en tu catálogo local.`;
	}
});
genreButtons.forEach((button) => {
	button.addEventListener("click", () => {
		selectedGenre = button.dataset.genreFilter;
		genreButtons.forEach((genreButton) => {
			genreButton.setAttribute("aria-pressed", String(genreButton === button));
		});
		filterBooks();
	});
});
languageButtons.forEach((button) => {
	button.addEventListener("click", () => {
		selectedLanguage = button.dataset.languageFilter;
		languageButtons.forEach((languageButton) => {
			languageButton.setAttribute("aria-pressed", String(languageButton === button));
		});
		filterBooks();
	});
});

const ratingEndpoint = new URL("api/valoraciones.php", document.baseURI);
const ratingCsrfToken = document.querySelector('meta[name="rating-csrf-token"]')?.content || "";
const isLoggedIn = document.querySelector('meta[name="user-logged-in"]')?.content === "1";
const ratings = new Map();
const ratingErrors = new Map();
const pendingRatingKeys = new Set();

function getBookRatingKey(card) {
	const language = card.dataset.language === "es" ? "es" : "en";
	const readerUrl = card.querySelector("[data-reader-url]")?.dataset.readerUrl || "";
	const rawId = card.dataset.bookId || readerUrl.match(/(\d+)\.html$/)?.[1] || "";
	const title = card.querySelector("h3")?.textContent.trim() || "Libro";
	const id = rawId || normalizeText(title).replace(/[^a-z0-9]+/g, "-");
	return `${language}:${id}`;
}

function addBookRatingControls(card) {
	const info = card.querySelector(".book-card__info");
	if (!info || (!card.querySelector("[data-reader-url]") && !card.dataset.bookId) || info.querySelector(".book-rating-controls")) return;

	const bookKey = getBookRatingKey(card);
	card.dataset.bookKey = bookKey;
	const controls = document.createElement("div");
	controls.className = "book-rating-controls";

	const summary = document.createElement("p");
	summary.className = "book-rating__summary";
	summary.textContent = "Cargando valoración general…";
	summary.setAttribute("aria-live", "polite");
	controls.append(summary);

	const rating = document.createElement("div");
	rating.className = "book-rating";
	const label = document.createElement("span");
	label.className = "book-rating__label";
	label.textContent = isLoggedIn ? "Tu valoración" : "Inicia sesión para valorar";
	label.id = `rating-label-${bookKey.replace(/[^a-z0-9-]/gi, "-")}`;
	rating.setAttribute("role", "group");
	rating.setAttribute("aria-labelledby", label.id);
	rating.append(label);

	const stars = document.createElement("span");
	stars.className = "book-rating__stars";
	const savedRating = ratings.get(bookKey)?.mi_valoracion || 0;
	for (let value = 1; value <= 5; value += 1) {
		const star = document.createElement("button");
		star.type = "button";
		star.className = "book-rating__star";
		star.dataset.ratingAction = "rate";
		star.dataset.rating = String(value);
		star.dataset.active = String(value <= savedRating);
		star.setAttribute("aria-label", `${value} ${value === 1 ? "estrella" : "estrellas"}`);
		star.setAttribute("aria-pressed", String(savedRating === value));
		if (!isLoggedIn) {
			star.disabled = true;
			star.title = "Debes iniciar sesión para valorar";
		}
		star.textContent = "★";
		stars.append(star);
	}
	rating.append(stars);
	controls.append(rating);
	info.insertBefore(controls, info.querySelector(".book-read"));
}

function formatRatingSummary(summary) {
	if (!summary || summary.cantidad === 0) {
		return "Todavía no hay valoraciones";
	}
	const average = Number(summary.promedio).toLocaleString("es-ES", {
		minimumFractionDigits: 1,
		maximumFractionDigits: 1
	});
	const voteLabel = summary.cantidad === 1 ? "valoración" : "valoraciones";
	return `★ ${average} de 5 · ${summary.cantidad} ${voteLabel}`;
}

function updateBookRatingControls(bookKey, summary = ratings.get(bookKey)) {
	bookCards.forEach((card) => {
		if (card.dataset.bookKey !== bookKey) return;
		const savedRating = summary?.mi_valoracion || 0;
		const summaryElement = card.querySelector(".book-rating__summary");
		if (summaryElement) {
			summaryElement.textContent = ratingErrors.get(bookKey) || formatRatingSummary(summary);
		}
		card.querySelectorAll('[data-rating-action="rate"]').forEach((star) => {
			const value = Number(star.dataset.rating);
			star.setAttribute("aria-pressed", String(savedRating === value));
			star.dataset.active = String(value <= savedRating);
			star.disabled = !isLoggedIn || pendingRatingKeys.has(bookKey);
		});
	});
}

bookCards.forEach(addBookRatingControls);

async function loadBookRatings() {
	try {
		const response = await fetch(ratingEndpoint, {
			headers: { Accept: "application/json" },
			credentials: "same-origin",
			cache: "no-store"
		});
		if (!response.ok) throw new Error(`HTTP ${response.status}`);
		const data = await response.json();
		if (!Array.isArray(data.valoraciones)) {
			throw new Error("La respuesta de valoraciones tiene un formato inválido.");
		}
		data.valoraciones.forEach((summary) => {
			const key = `${summary.idioma}:${summary.id}`;
			if (
				!["en", "es"].includes(summary.idioma) ||
				!Number.isInteger(Number(summary.id)) ||
				!Number.isFinite(Number(summary.promedio)) ||
				!Number.isInteger(Number(summary.cantidad)) ||
				!Number.isInteger(Number(summary.mi_valoracion))
			) {
				throw new Error("El resumen de una valoración tiene un formato inválido.");
			}
			ratings.set(key, {
				promedio: Number(summary.promedio),
				cantidad: Number(summary.cantidad),
				mi_valoracion: Number(summary.mi_valoracion)
			});
			updateBookRatingControls(key);
		});
		bookCards.forEach((card) => {
			const key = card.dataset.bookKey || getBookRatingKey(card);
			if (!ratings.has(key)) updateBookRatingControls(key, null);
		});
	} catch (error) {
		console.error("No se pudo cargar la valoración general de los libros:", error);
		bookCards.forEach((card) => {
			const summary = card.querySelector(".book-rating__summary");
			if (summary) summary.textContent = "No se pudo cargar la valoración general";
		});
	}
}

async function saveBookRating(card, rating) {
	const bookKey = getBookRatingKey(card);
	if (!isLoggedIn) {
		ratingErrors.set(bookKey, "Debes iniciar sesión para valorar los libros.");
		updateBookRatingControls(bookKey);
		document.querySelector("#login-modal")?.showModal();
		return;
	}
	if (!ratingCsrfToken || pendingRatingKeys.has(bookKey)) return;
	pendingRatingKeys.add(bookKey);
	updateBookRatingControls(bookKey);

	try {
		const response = await fetch(ratingEndpoint, {
			method: "POST",
			credentials: "same-origin",
			headers: {
				Accept: "application/json",
				"Content-Type": "application/json",
				"X-CSRF-Token": ratingCsrfToken
			},
			body: JSON.stringify({
				id: Number(bookKey.split(":")[1]),
				idioma: card.dataset.language === "es" ? "es" : "en",
				valoracion: rating
			})
		});
		const data = await response.json();
		if (!response.ok) throw new Error(data.error || `HTTP ${response.status}`);
		const language = card.dataset.language === "es" ? "es" : "en";
		if (
			!data.valoracion ||
			Number(data.valoracion.id) !== Number(bookKey.split(":")[1]) ||
			data.valoracion.idioma !== language ||
			!Number.isFinite(Number(data.valoracion.promedio)) ||
			!Number.isInteger(Number(data.valoracion.cantidad)) ||
			!Number.isInteger(Number(data.valoracion.mi_valoracion))
		) {
			throw new Error("La respuesta de valoración tiene un formato inválido.");
		}
		ratingErrors.delete(bookKey);
		ratings.set(bookKey, {
			promedio: Number(data.valoracion.promedio),
			cantidad: Number(data.valoracion.cantidad),
			mi_valoracion: Number(data.valoracion.mi_valoracion)
		});
	} catch (error) {
		console.error("No se pudo guardar la valoración:", error);
		ratingErrors.set(bookKey, "No se pudo guardar tu valoración. Inténtalo de nuevo.");
	} finally {
		pendingRatingKeys.delete(bookKey);
		updateBookRatingControls(bookKey);
	}
}

catalog?.addEventListener("click", async (event) => {
	const button = event.target.closest('[data-rating-action="rate"]');
	if (button) {
		const card = button.closest(".book-card");
		if (!card) return;

		await saveBookRating(card, Number(button.dataset.rating));
		return;
	}

	const buyButton = event.target.closest('[data-purchase-action="buy"]');
	if (buyButton) {
		await buyBook(
			buyButton.dataset.bookId,
			buyButton.dataset.bookLanguage,
			Number(buyButton.dataset.bookPrice),
			buyButton
		);
	}
});

loadBookRatings();

/* ------------------------------------------------------------------ *
 * Libros de pago: comprar, estado de compra y acceso al texto.
 * ------------------------------------------------------------------ */

function formatPrice(value) {
	return `$${Number(value).toLocaleString("es-UY", { minimumFractionDigits: 0, maximumFractionDigits: 2 })}`;
}

function bookKey(bookId, language) {
	return `${language}:${bookId}`;
}

async function loadUserPurchases() {
	if (!userLoggedIn) return;

	try {
		const response = await fetch(purchaseEndpoint, {
			headers: { Accept: "application/json" },
			credentials: "same-origin",
			cache: "no-store"
		});
		if (!response.ok) return;
		const data = await response.json();
		if (!Array.isArray(data?.compras)) return;

		data.compras.forEach((purchase) => {
			purchasedBooks.set(bookKey(purchase.id, purchase.idioma), purchase);
		});
	} catch (error) {
		console.error("No se pudieron cargar las compras:", error);
	}
}

function ownsBook(bookId, language) {
	return purchasedBooks.has(bookKey(bookId, language));
}

/**
 * Actualiza la tarjeta de un libro de pago segun si el usuario lo compro.
 *
 * La tarjeta muestra una sola accion: "Comprar libro" mientras no se compro
 * y "Leer libro" una vez que el libro esta en la biblioteca del usuario.
 */
function refreshPaidCard(card, bookId, language) {
	const owned = ownsBook(bookId, language);
	card.classList.toggle("book-card--owned", owned);

	const buyButton = card.querySelector('[data-purchase-action="buy"]');
	const readButton = card.querySelector('[data-reader-url]');
	const badge = card.querySelector(".book-card__owned-badge");

	if (buyButton) {
		if (owned) {
			if (card.dataset.presentation === "1") {
				buyButton.className = "book-read";
				buyButton.removeAttribute("data-purchase-action");
				buyButton.removeAttribute("data-book-price");
				buyButton.dataset.readerUrl = `books/${bookId}.html`;
				buyButton.textContent = "Leer libro";
				buyButton.hidden = false;
			} else {
				buyButton.className = "book-read";
				buyButton.removeAttribute("data-purchase-action");
				buyButton.removeAttribute("data-book-price");
				buyButton.dataset.readerUrl = `books/${bookId}.html`;
				buyButton.textContent = "Leer libro";
				buyButton.hidden = false;
			}
		} else {
			buyButton.hidden = false;
			buyButton.disabled = false;
		}
	}
	if (badge) {
		if (!userLoggedIn) {
			badge.remove();
		} else {
			badge.hidden = !owned;
		}
	}
	if (readButton && card.dataset.requiresPurchase === "1" && card.dataset.presentation !== "1") {
		readButton.hidden = !owned;
		readButton.textContent = "Leer libro";
	} else if (readButton && card.dataset.presentation === "1") {
		readButton.hidden = true;
	}
}

/**
 * Compra un libro de pago y actualiza la interfaz.
 */
async function buyBook(bookId, language, price, button) {
	if (!userLoggedIn) {
		openLoginModal();
		return;
	}

	button.disabled = true;
	button.textContent = "Comprar libro";

	try {
		const response = await fetch(purchaseEndpoint, {
			method: "POST",
			credentials: "same-origin",
			headers: {
				Accept: "application/json",
				"Content-Type": "application/json",
				"X-CSRF-Token": purchaseCsrfToken
			},
			body: JSON.stringify({ id: Number(bookId), idioma: language })
		});
		const data = await response.json();
		if (!response.ok) {
			throw new Error(data?.error || "No se pudo completar la compra.");
		}

		(Array.isArray(data?.compras) ? data.compras : []).forEach((purchase) => {
			purchasedBooks.set(bookKey(purchase.id, purchase.idioma), purchase);
		});

		bookCards.forEach((card) => {
			const id = card.dataset.bookId || card.querySelector("[data-reader-url]")?.dataset.readerUrl.match(/(\d+)\.html$/)?.[1];
			if (id) refreshPaidCard(card, id, card.dataset.language === "es" ? "es" : "en");
		});

		if (typeof data?.mensaje === "string") {
			showToast(data.mensaje, "success");
		}
	} catch (error) {
		console.error("Error al comprar el libro:", error);
		showToast(error.message || "No se pudo completar la compra.", "error");
		button.disabled = false;
		button.textContent = "Comprar libro";
	}
}

function showToast(message, type) {
	let toast = document.querySelector(".toast");
	if (!toast) {
		toast = document.createElement("div");
		toast.className = "toast";
		toast.setAttribute("role", "status");
		document.body.append(toast);
	}
	toast.textContent = message;
	toast.className = `toast toast--${type === "error" ? "error" : "success"}`;
	toast.classList.add("is-visible");
	clearTimeout(showToast.timer);
	showToast.timer = setTimeout(() => toast.classList.remove("is-visible"), 4200);
}

function openLoginModal() {
	document.querySelector("#login-modal")?.showModal();
}

async function loadAdditionalCatalogBooks() {
	// Las compras se cargan primero: asi las tarjetas ya muestran el estado real.
	await loadUserPurchases();

	const catalogUrl = new URL("api/lista.php", document.baseURI);
	catalogUrl.searchParams.set("_", String(Date.now()));
	const response = await fetch(catalogUrl, { headers: { Accept: "application/json" }, cache: "no-store" });
	if (!response.ok) throw new Error(`No se pudo actualizar el catálogo (HTTP ${response.status})`);
	const books = await response.json();
	if (!Array.isArray(books)) throw new Error("La respuesta del catálogo tiene un formato inválido");

	const existingBooks = new Set(bookCards.map((card) => {
		const id = card.querySelector("[data-reader-url]")?.dataset.readerUrl.match(/(\d+)\.html$/)?.[1];
		return `${card.dataset.language || "en"}:${id}`;
	}));
	const genreLabels = {
		aventura: "Aventura",
		"ciencia-ficcion": "Ciencia ficción",
		cuentos: "Cuentos",
		fantasia: "Fantasía",
		filosofia: "Filosofía",
		historia: "Historia",
		misterio: "Misterio",
		novela: "Novela",
		poesia: "Poesía",
		romance: "Romance",
		terror: "Terror"
	};

	books.forEach((book) => {
		const language = book.idioma === "es" ? "es" : "en";
		const key = `${language}:${book.id}`;
		if (existingBooks.has(key)) return;

		const card = document.createElement("article");
		card.className = "book-card";
		card.dataset.genre = book.genero || "";
		card.dataset.language = language;
		card.dataset.bookId = String(book.id);
		card.dataset.isbn = book.isbn || "";

		const cover = document.createElement("img");
		cover.className = "book-cover";
		cover.src = book.cover || "https://placehold.co/420x600?text=Libro";
		cover.alt = `Portada de ${book.titulo}`;
		cover.loading = "lazy";

		const info = document.createElement("div");
		info.className = "book-card__info";
		const genre = document.createElement("p");
		genre.className = "book-card__genre";
		genre.textContent = `${genreLabels[book.genero] || book.genero || "Clásico"} · ${language === "es" ? "Español" : "English"}`;
		const title = document.createElement("h3");
		title.textContent = book.titulo;
		const author = document.createElement("p");
		author.textContent = book.autor;
		const readButton = document.createElement("button");
		readButton.className = "book-read";
		readButton.type = "button";
		readButton.dataset.readerUrl = `books/${book.id}.html`;
		readButton.textContent = "Leer libro";
		info.append(genre, title, author);

		const isPaid = book.precio !== null && book.precio !== undefined && book.precio !== "";
		if (isPaid) {
			const price = document.createElement("span");
			price.className = "book-card__price";
			price.textContent = formatPrice(book.precio);
			info.append(price);
		}

		if (isPaid) {
		card.dataset.requiresPurchase = "1";
		if (book.presentacion) card.dataset.presentation = "1";

			const ownedBadge = document.createElement("span");
			ownedBadge.className = "book-card__owned-badge";
			ownedBadge.textContent = "Comprado";
			ownedBadge.hidden = !isLoggedIn;
			info.append(ownedBadge);

			const buyButton = document.createElement("button");
			buyButton.className = "book-buy";
			buyButton.type = "button";
			buyButton.dataset.purchaseAction = "buy";
			buyButton.dataset.bookId = String(book.id);
			buyButton.dataset.bookLanguage = language;
			buyButton.dataset.bookPrice = String(book.precio);
			buyButton.textContent = "Comprar libro";
			info.append(buyButton);
		}

		if (!book.presentacion) {
			info.append(readButton);
		}
		card.append(cover, info);
		catalog.querySelector(".book-grid")?.append(card);
		bookCards.push(card);
		existingBooks.add(key);

		if (isPaid) {
			refreshPaidCard(card, book.id, language);
		}
		addBookRatingControls(card);
	});

	const spanishBooks = books.filter((book) => book.idioma === "es").length;
	const introEyebrow = document.querySelector(".intro__eyebrow");
	if (introEyebrow) introEyebrow.textContent = `${bookCards.length} clásicos · Texto íntegro · Inglés y español`;
	const introMeta = document.querySelector(".intro__meta");
	if (introMeta) introMeta.textContent = `${bookCards.length} títulos · ${spanishBooks} libros íntegros en español · lectura en voz alta`;
	await loadBookRatings();
	filterBooks();
}

loadAdditionalCatalogBooks().catch((error) => {
	console.error("loadAdditionalCatalogBooks error:", error);
	if (bookResults) bookResults.textContent = "No se pudieron actualizar los libros agregados desde el panel de administración.";
});

const readerDialog = document.querySelector("#reader-modal");
const readerTitle = document.querySelector("#reader-title");
const readerAuthor = document.querySelector("#reader-author");
const readerContent = document.querySelector("#reader-content");
const readerChapterList = document.querySelector("#reader-chapter-list");
const readerChaptersTitle = document.querySelector("#reader-chapters-title");
const readerStatus = document.querySelector("#reader-status");
const readerSource = document.querySelector("#reader-source");
const readerSourceLink = document.querySelector("#reader-source-link");
const readerLocked = document.querySelector("#reader-locked");
const readerLockedText = document.querySelector("#reader-locked-text");
const readerBuyButton = document.querySelector("#reader-buy");
const readerSpeechControls = document.querySelector("#reader-speech-controls");
const listenButton = document.querySelector("#reader-listen");
const pauseButton = document.querySelector("#reader-pause");
const stopButton = document.querySelector("#reader-stop");
const speedInput = document.querySelector("#reader-speed");
const speedValue = document.querySelector("#reader-speed-value");
let readerText = "";
let speechChunks = [];
let speechChunkIndex = 0;
let readerFontSize = 1.1;
let readerRequestId = 0;
let isSpeaking = false;
let bookLang = "en";
const bookCatalogRequests = new Map();
const speechAvailable = "speechSynthesis" in window && "SpeechSynthesisUtterance" in window;

if (!speechAvailable) {
	readerSpeechControls.hidden = true;
}

function splitSpeechText(text, maxLength = 2400) {
	const chunks = [];

	text.split(/\n+/).map((paragraph) => paragraph.trim()).filter(Boolean).forEach((paragraph) => {
		while (paragraph.length > maxLength) {
			let splitAt = paragraph.lastIndexOf(" ", maxLength);
			if (splitAt < maxLength / 2) splitAt = maxLength;
			chunks.push(paragraph.slice(0, splitAt).trim());
			paragraph = paragraph.slice(splitAt).trim();
		}
		if (paragraph) chunks.push(paragraph);
	});

	return chunks;
}

function speakNextChunk() {
	if (!isSpeaking) return;
	if (speechChunkIndex >= speechChunks.length) {
		isSpeaking = false;
		listenButton.disabled = false;
		pauseButton.disabled = true;
		stopButton.disabled = true;
		pauseButton.textContent = "Pausar";
		readerStatus.textContent = "Terminó la lectura en voz alta.";
		return;
	}

	const utterance = new SpeechSynthesisUtterance(speechChunks[speechChunkIndex++]);
	utterance.lang = bookLang === "es" ? "es-ES" : "en-US";
	utterance.rate = Number(speedInput.value);
	const voices = window.speechSynthesis.getVoices();
	const voiceLanguage = bookLang === "es" ? "es" : "en";
	utterance.voice = voices.find((voice) => voice.lang.toLowerCase().startsWith(voiceLanguage)) || null;
	utterance.onend = speakNextChunk;
	utterance.onerror = () => {
		isSpeaking = false;
		listenButton.disabled = false;
		pauseButton.disabled = true;
		stopButton.disabled = true;
		readerStatus.textContent = "No se pudo continuar la lectura en voz alta.";
	};
	window.speechSynthesis.speak(utterance);
}

function stopSpeech() {
	if (speechAvailable) window.speechSynthesis.cancel();
	isSpeaking = false;
	listenButton.disabled = !readerText;
	pauseButton.disabled = true;
	stopButton.disabled = true;
	pauseButton.textContent = "Pausar";
}

async function renderReaderSections(sections, requestId) {
	readerContent.replaceChildren();
	readerChapterList.replaceChildren();
	readerChaptersTitle.textContent = sections.length === 1 ? "Contenido" : "Capítulos y secciones";

	for (let start = 0; start < sections.length; start += 8) {
		if (requestId !== readerRequestId) return false;
		const contentFragment = document.createDocumentFragment();
		const chapterFragment = document.createDocumentFragment();
		const end = Math.min(start + 8, sections.length);

		for (let index = start; index < end; index += 1) {
			const section = sections[index];
			const sectionElement = document.createElement("section");
			sectionElement.className = index === 0 ? "reader__section reader__section--intro" : "reader__section";
			sectionElement.id = `reader-section-${index}`;
			const sectionHeading = document.createElement("h3");
			sectionHeading.textContent = section.title;
			sectionElement.append(sectionHeading);

			section.blocks.forEach((block) => {
				const blockElement = document.createElement(block.heading ? "h4" : "p");
				blockElement.textContent = block.text;
				sectionElement.append(blockElement);
			});
			contentFragment.append(sectionElement);

			const chapterButton = document.createElement("button");
			chapterButton.className = "reader__chapter-button";
			chapterButton.type = "button";
			chapterButton.dataset.readerSection = String(index);
			chapterButton.textContent = section.title;
			if (index === 0) chapterButton.setAttribute("aria-current", "location");
			chapterFragment.append(chapterButton);
		}

		readerContent.append(contentFragment);
		readerChapterList.append(chapterFragment);
		readerStatus.textContent = `Mostrando ${end} de ${sections.length} capítulos o secciones...`;
		if (end < sections.length) {
			await new Promise((resolve) => requestAnimationFrame(resolve));
		}
	}

	return requestId === readerRequestId;
}

function customizeReaderIntroduction(sections, intro) {
	if (!intro) return sections;
	const idx = sections.findIndex((s) => ["introduction", "introduccion"].includes(normalizeText(s.title)));
	if (idx >= 0) sections.splice(idx, 1, intro);
	else sections.unshift(intro);
	return sections;
}

function loadBookCatalog(language) {
	if (!bookCatalogRequests.has(language)) {
		const filename = language === "es" ? "libros_es.json" : "libros.json";
		const catalogUrl = new URL(`api/${filename}`, document.baseURI).href;
		const catalogRequest = fetch(catalogUrl, {
			headers: { Accept: "application/json" },
			cache: "no-store"
		}).then(async (response) => {
			if (!response.ok) throw new Error(`Archivo ${filename}: HTTP ${response.status}`);
			const catalog = await response.json();
			if (!catalog || typeof catalog !== "object" || Array.isArray(catalog)) {
				throw new Error(`El archivo ${filename} no tiene un catálogo válido`);
			}
			return catalog;
		});
		bookCatalogRequests.set(language, catalogRequest);
		catalogRequest.catch(() => {
			if (bookCatalogRequests.get(language) === catalogRequest) bookCatalogRequests.delete(language);
		});
	}

	return bookCatalogRequests.get(language);
}

/**
 * Muestra el estado "bloqueado" en el lector, con la llamada a comprar.
 */
function showLockedBook(error) {
	const meta = error?.libro || {};
	readerStatus.textContent = "Este libro es de pago.";
	readerLocked.hidden = false;
	const reason = error?.message || "Necesitás comprar este libro para leerlo.";
	readerLockedText.textContent = meta.precio
		? `${reason} Cuesta ${formatPrice(meta.precio)}.`
		: reason;

	const canBuy = Boolean(meta.id) && Boolean(meta.precio);
	readerBuyButton.hidden = !canBuy;
	if (canBuy) {
		readerBuyButton.textContent = "Comprar libro";
		readerBuyButton.dataset.bookId = String(meta.id);
		readerBuyButton.dataset.bookLanguage = meta.idioma === "es" ? "es" : "en";
		readerBuyButton.dataset.bookPrice = String(meta.precio);
	}
}

/**
 * Carga el texto de un libro.
 *
 * Los libros de pago no viven en el JSON publico: su texto llega desde
 * api/libro.php, que solo lo entrega a quien lo compro.
 */
async function loadBookText(bookId, language) {
	const catalog = await loadBookCatalog(language);
	const entry = catalog[bookId];

	// Los libros de pago vienen marcados en el catalogo publico.
	const isPaid = Boolean(entry?.requiere_compra) || entry?.secciones?.length === 0;
	if (!isPaid) {
		return entry;
	}

	const url = new URL(bookEndpoint.href);
	url.searchParams.set("id", bookId);
	url.searchParams.set("lang", language);

	const response = await fetch(url, {
		headers: { Accept: "application/json" },
		credentials: "same-origin",
		cache: "no-store"
	});
	const data = await response.json().catch(() => null);

	if (!response.ok) {
		if (response.status === 401 && !data?.libro) {
			openLoginModal();
			throw new Error(data?.error || "Necesitás iniciar sesión para leer este libro.");
		}

		const error = new Error(data?.error || `No se pudo acceder al libro (HTTP ${response.status})`);
		error.requiresPurchase = true;
		error.libro = data?.libro || null;
		throw error;
	}

	if (data?.comprado === false || data?.requiere_compra) {
		throw new Error(data?.error || "Este libro es de pago. Compralo para poder leerlo.");
	}

	return data;
}

async function openBookReader(bookUrl, card) {
	const requestId = ++readerRequestId;
	bookLang = card.dataset.language === "es" ? "es" : "en";
	readerTitle.textContent = card.querySelector("h3").textContent;
	readerAuthor.textContent = card.querySelector(".book-card__info > p:last-of-type").textContent;
	readerContent.textContent = "";
	readerChapterList.replaceChildren();
	readerText = "";
	speechChunks = [];
	speechChunkIndex = 0;
	stopSpeech();
	readerSource.hidden = true;
	readerContent.replaceChildren();
	readerChapterList.replaceChildren();
	readerStatus.textContent = `Cargando el texto completo en ${bookLang === "es" ? "español" : "inglés"}...`;
	listenButton.disabled = true;
	readerLocked.hidden = true;

	try {
		if (!readerDialog.open) readerDialog.showModal();

		const idMatch = bookUrl.match(/(\d+)\.html$/);
		const bookId = idMatch ? idMatch[1] : null;
		if (!bookId) throw new Error("Sin ID");
		if (requestId !== readerRequestId) return;

		const data = await loadBookText(bookId, bookLang);
		if (!data) throw new Error(`El libro ${bookId} no está en el catálogo ${bookLang}`);
		if (!data.secciones || !Array.isArray(data.secciones) || !data.secciones.length) throw new Error("API sin secciones");
		if (data.idioma !== bookLang) throw new Error("El idioma del libro no coincide con el seleccionado");
		if (requestId !== readerRequestId) return;

		bookLang = data.idioma === "es" ? "es" : "en";
		const sections = customizeReaderIntroduction(data.secciones, data.introducciones?.[bookLang]);
		if (requestId !== readerRequestId) return;
		readerStatus.textContent = `Preparando ${sections.length} capítulos o secciones...`;
		await new Promise((resolve) => requestAnimationFrame(resolve));
		if (requestId !== readerRequestId) return;
		const rendered = await renderReaderSections(sections, requestId);
		if (!rendered) return;
		readerText = sections.flatMap((section) => [section.title, ...section.blocks.map((block) => block.text)]).join("\n\n");
		const wordCount = readerText.split(/\s+/).filter(Boolean).length;
		const languageLabel = bookLang === "es" ? "español" : "inglés";
		readerStatus.textContent = `${wordCount.toLocaleString("es-ES")} palabras · ${sections.length} capítulos o secciones · edición en ${languageLabel}.`;
		try {
			readerSourceLink.href = new URL(
				data.source || `api/${bookLang === "es" ? "libros_es.json" : "libros.json"}`,
				document.baseURI
			).href;
		} catch {
			readerSourceLink.href = data.source || `api/${bookLang === "es" ? "libros_es.json" : "libros.json"}`;
		}
		readerSource.hidden = false;
		listenButton.disabled = !speechAvailable || !readerText;
	} catch (error) {
		if (requestId !== readerRequestId) return;
		console.error("openBookReader error:", error);

		// Libro de pago sin compra: mostrar el aviso y el boton de compra.
		if (error?.requiresPurchase) {
			showLockedBook(error);
			return;
		}

		const isFile = window.location.protocol === "file:";
		const extra = isFile ? " Estás abriendo el archivo con doble clic (file://). Abrí http://localhost/proyectodetodo/ desde XAMPP." : "";
		readerStatus.textContent = `No se pudo cargar el libro (${error.message}). Verifica que el archivo api/libros${bookLang === "es" ? "_es" : ""}.json exista y que abras el sitio desde http://localhost/proyectodetodo/.${extra}`;
	}
}

document.addEventListener("click", (event) => {
	const button = event.target.closest(".book-read[data-reader-url]");
	if (!button) return;
	openBookReader(button.dataset.readerUrl, button.closest(".book-card"));
});

// Comprar desde el lector: al terminar, se abre el libro ya comprado.
readerBuyButton?.addEventListener("click", async () => {
	const bookId = readerBuyButton.dataset.bookId;
	const language = readerBuyButton.dataset.bookLanguage;
	const price = Number(readerBuyButton.dataset.bookPrice);
	if (!bookId) return;

	await buyBook(bookId, language, price, readerBuyButton);

	if (ownsBook(bookId, language)) {
		readerLocked.hidden = true;
		const card = bookCards.find((candidate) => {
			const id = candidate.querySelector("[data-reader-url]")?.dataset.readerUrl.match(/(\d+)\.html$/)?.[1];
			return id === String(bookId) && (candidate.dataset.language === "es" ? "es" : "en") === language;
		});
		await openBookReader(`books/${bookId}.html`, card);
	}
});

readerChapterList.addEventListener("click", (event) => {
	const button = event.target.closest("[data-reader-section]");
	if (!button) return;

	readerChapterList.querySelectorAll("[data-reader-section]").forEach((chapterButton) => {
		chapterButton.removeAttribute("aria-current");
	});
	button.setAttribute("aria-current", "location");
	readerContent.querySelector(`#reader-section-${button.dataset.readerSection}`)?.scrollIntoView({behavior: "smooth", block: "start"});
});

listenButton.addEventListener("click", () => {
	if (!readerText || !speechAvailable) return;
	window.speechSynthesis.cancel();
	speechChunks = splitSpeechText(readerText);
	speechChunkIndex = 0;
	isSpeaking = true;
	listenButton.disabled = true;
	pauseButton.disabled = false;
	stopButton.disabled = false;
	readerStatus.textContent = `Lectura en voz alta en ${bookLang === "es" ? "español" : "inglés"}.`;
	speakNextChunk();
});

pauseButton.addEventListener("click", () => {
	if (!speechAvailable) return;
	if (window.speechSynthesis.paused) {
		window.speechSynthesis.resume();
		pauseButton.textContent = "Pausar";
		readerStatus.textContent = `Lectura en voz alta en ${bookLang === "es" ? "español" : "inglés"}.`;
	} else {
		window.speechSynthesis.pause();
		pauseButton.textContent = "Continuar";
		readerStatus.textContent = "Lectura pausada.";
	}
});

stopButton.addEventListener("click", () => {
	stopSpeech();
	readerStatus.textContent = "Lectura detenida.";
});

speedInput.addEventListener("input", () => {
	speedValue.value = `${Number(speedInput.value).toFixed(2).replace(/0+$/, "").replace(/\.$/, "")}x`;
});

document.querySelector("#reader-smaller").addEventListener("click", () => {
	readerFontSize = Math.max(0.9, readerFontSize - 0.1);
	readerDialog.style.setProperty("--reader-font-size", `${readerFontSize.toFixed(1)}rem`);
});

document.querySelector("#reader-larger").addEventListener("click", () => {
	readerFontSize = Math.min(1.8, readerFontSize + 0.1);
	readerDialog.style.setProperty("--reader-font-size", `${readerFontSize.toFixed(1)}rem`);
});

document.querySelector("#reader-theme").addEventListener("click", (event) => {
	const isDark = readerDialog.classList.toggle("reader--dark");
	event.currentTarget.textContent = isDark ? "Modo claro" : "Modo oscuro";
});

readerDialog.addEventListener("close", () => {
	readerRequestId += 1;
	stopSpeech();
});

readerDialog.addEventListener("click", (event) => {
	if (event.target === readerDialog) readerDialog.close();
});

document.querySelectorAll(".modal").forEach((modal) => {
	modal.addEventListener("click", (event) => {
		if (event.target === modal) {
			modal.close();
		}
	});
});