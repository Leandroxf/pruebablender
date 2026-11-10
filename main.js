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
const cartStorageKey = "audiolibreria-cart-v1";
const cartItems = new Map();
const cartCount = $("#cart-count");
const cartLines = $("#cart-items");
const cartEmpty = $("#cart-empty");
const cartTotal = $("#cart-total");
const cartStatus = $("#cart-status");
const cartModal = $("#cart-modal");
const cartCheckout = $("#cart-checkout");
let cartCheckoutInProgress = false;
let cartCatalogReady = false;
const registerForm = $("#register-form");
const registerStatus = $("#register-status");

$$("[data-modal-open]").forEach((button) => {
	const modal = document.getElementById(button.dataset.modalOpen);

	button.addEventListener("click", () => {
		$$("dialog[open]").forEach((openDialog) => openDialog.close());
		modal?.showModal();
	});
});

if (new URLSearchParams(window.location.search).get("login") === "error") {
	$("#login-modal")?.showModal();
}

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
let lastSearchSentToArduino = "";

function normalizeText(value) {
	return String(value ?? "").normalize("NFD").replace(/[\u0300-\u036f]/g, "").toLowerCase();
}

function filterBooks() {
	const queryRaw = searchInput?.value.trim() || "";
	const query = normalizeText(queryRaw);
	let visibleCount = 0;
	let firstMatchTitle = "";

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

		const isVisible = matchesSearch && matchesGenre && matchesLanguage;
		card.hidden = !isVisible;
		if (isVisible && visibleCount === 0) {
			firstMatchTitle = card.querySelector("h3")?.textContent || "";
		}
		if (isVisible) visibleCount += 1;
	});

	if (bookResults) {
		bookResults.textContent = `Se muestran ${visibleCount} ${visibleCount === 1 ? "libro" : "libros"}`;
	}
	if (emptyMessage) emptyMessage.hidden = visibleCount > 0;

	// Enviar al Arduino solo si hay resultado único o primer resultado y cambió la búsqueda
	if (queryRaw && visibleCount > 0 && firstMatchTitle) {
		if (lastSearchSentToArduino !== queryRaw.toLowerCase()) {
			lastSearchSentToArduino = queryRaw.toLowerCase();
			sendBookToArduino(firstMatchTitle);
		}
	}
}

const barcodeSerialButton = $("#barcode-serial-button");
const barcodeSerialStatus = $("#barcode-serial-status");
let barcodeSerialPort = null;

// Conexión para mostrar nombre del libro en pantalla Arduino por COM3 (PHP)

const arduinoDisplayButton = $("#arduino-display-button");
const arduinoDisplayStatus = $("#arduino-display-status");
let arduinoSerialPort = null;
let barcodeSerialReader = null;

function showSerialStatus(element, message, clearAfter = 0) {
	if (!element) return;
	clearTimeout(element.statusClearTimer);
	element.textContent = message;
	if (clearAfter > 0 && message) {
		element.statusClearTimer = setTimeout(() => {
			element.textContent = "";
		}, clearAfter);
	}
}
async function ensureArduinoPort() {
	if (!("serial" in navigator)) throw new Error("Web Serial no est? disponible. Abr? el sitio en Chrome o Edge desde http://localhost:8080.");
	if (!window.isSecureContext) throw new Error("Web Serial est? bloqueado por el contexto actual. Abr? http://localhost:8080.");
	if (!arduinoSerialPort) arduinoSerialPort = await navigator.serial.requestPort();
	if (!arduinoSerialPort.writable) {
		await arduinoSerialPort.open({ baudRate: 9600 });
		// Abrir el puerto reinicia muchas placas Arduino.
		await new Promise((resolve) => setTimeout(resolve, 2000));
	}
}

async function sendBookToArduino(title) {
	if (!title) return false;
	try {
		if (!arduinoSerialPort?.writable) throw new Error("Primero conect? el LCD con el bot?n Conectar Arduino LCD por USB.");
		if (arduinoDisplayStatus) arduinoDisplayStatus.textContent = `Enviando al LCD: ${title}`;
		const writer = arduinoSerialPort.writable.getWriter();
		try {
			await writer.write(new TextEncoder().encode(`${title.slice(0, 32)}.`));
		} finally {
			writer.releaseLock();
		}
		if (arduinoDisplayStatus) arduinoDisplayStatus.textContent = `Arduino conectado. T?tulo enviado: ${title.slice(0, 32)}`;
		if (arduinoDisplayButton) arduinoDisplayButton.textContent = "Arduino LCD conectado";
		return true;
	} catch (error) {
		console.error("Error enviando al Arduino por USB:", error);
		const message = error?.name === "NotFoundError"
			? "No se seleccion? el puerto del Arduino. Volv? a buscar el ISBN y eleg? su puerto."
			: error.message || "No se pudo enviar al Arduino. Cerr? el Monitor Serie y revis? la conexi?n.";
		showSerialStatus(arduinoDisplayStatus, message, 10000);
		return false;
	}
}

arduinoDisplayButton?.addEventListener("click", async () => {
	try {
		await ensureArduinoPort();
		await sendBookToArduino("CONECTADO");
	} catch (error) {
		if (error?.name !== "NotFoundError") console.error("No se pudo abrir el puerto del Arduino:", error);
		const message = error?.name === "NotFoundError"
			? "No se seleccion? ning?n puerto del Arduino."
			: error.message || "No se pudo abrir el puerto. Cerr? el Monitor Serie y reconect? la placa.";
		showSerialStatus(arduinoDisplayStatus, message, 10000);
	}
});

barcodeSerialButton?.addEventListener("click", async () => {
	if (!("serial" in navigator)) {
		showSerialStatus(barcodeSerialStatus, "Web Serial requiere Chrome o Edge desde localhost.", 10000);
		return;
	}

	try {
		showSerialStatus(barcodeSerialStatus, "Eleg? el COM del scanner (distinto al Arduino).");
		barcodeSerialPort = await navigator.serial.requestPort();
		await barcodeSerialPort.open({ baudRate: 9600 });
		showSerialStatus(barcodeSerialStatus, "Scanner conectado. Escane? un ISBN.");
		const decoder = new TextDecoderStream();
		barcodeSerialPort.readable.pipeTo(decoder.writable);
		const reader = decoder.readable.getReader();
		barcodeSerialReader = reader;
		let buffer = "";
		while (true) {
			const { value, done } = await reader.read();
			if (done) break;
			buffer += value || "";
			const lines = buffer.split(/[\r\n]+/);
			buffer = lines.pop() || "";
			for (const line of lines) {
				const isbn = line.replace(/[^0-9Xx]/g, "");
				if (!isbn) continue;
				$("#barcode-input").value = isbn;
				showSerialStatus(barcodeSerialStatus, `ISBN le?do del scanner: ${isbn}`);
				$("#barcode-form")?.requestSubmit();
			}
		}
	} catch (error) {
		if (error?.name !== "NotFoundError") console.error("Error del COM del scanner:", error);
		const message = error?.name === "NotFoundError"
			? "No se seleccion? el COM del scanner. No se ley? ning?n ISBN."
			: "No se pudo abrir el COM del scanner. Cerr? su monitor serie y revis? los baudios.";
		showSerialStatus(barcodeSerialStatus, message, 10000);
	} finally {
		barcodeSerialReader = null;
		if (barcodeSerialPort?.readable) {
			try { await barcodeSerialPort.close(); } catch (error) { console.warn("No se pudo cerrar el COM del scanner:", error); }
		}
		barcodeSerialPort = null;
	}
});

function normalizeBookCode(value) {
	return String(value ?? "").trim().toUpperCase().replace(/[^0-9X]/g, "");
}

function cardMatchesBookCode(card, code) {
	const digits = code.replace(/[^0-9]/g, "");
	const readerUrl = card.querySelector("[data-reader-url]")?.dataset.readerUrl || "";
	const readerId = readerUrl.match(/(\d+)\.html$/)?.[1] || "";
	const cover = card.querySelector(".book-cover")?.getAttribute("src") || "";
	const coverIsbn = cover.match(/isbn\/([0-9X]+)/i)?.[1] || "";
	const cardIsbn = card.dataset.isbn || "";
	return normalizeBookCode(readerId) === code ||
		(Boolean(digits) && normalizeBookCode(readerId) === digits) ||
		normalizeBookCode(coverIsbn) === code ||
		normalizeBookCode(cardIsbn) === code;
}

async function findBookTitleByCode(code) {
	// Primero comprobar los datos de las tarjetas, incluidos libros de la base de datos.
	const card = bookCards.find((candidate) => cardMatchesBookCode(candidate, code));
	if (card) return { title: card.querySelector("h3")?.textContent.trim() || "", card };

	// Las tarjetas est?ticas en espa?ol no siempre incluyen ISBN en el HTML;
	// buscar tambi?n en ambos cat?logos JSON (ISBN y URL de portada).
	const catalogs = await Promise.all([
		loadBookCatalog("en").catch(() => ({})),
		loadBookCatalog("es").catch(() => ({}))
	]);
	for (const catalog of catalogs) {
		for (const [id, book] of Object.entries(catalog)) {
			const coverIsbn = String(book.cover || "").match(/isbn\/([0-9X]+)/i)?.[1] || "";
			if (normalizeBookCode(book.isbn) === code || normalizeBookCode(coverIsbn) === code) {
				return { title: String(book.titulo || "").trim(), card: null, id };
			}
		}
	}
	return null;
}

$("#barcode-form")?.addEventListener("submit", async (event) => {
	event.preventDefault();
	const input = $("#barcode-input");
	const status = $("#barcode-status");
	const code = normalizeBookCode(input?.value);
	if (!code) {
		if (status) status.textContent = "Ingres? un ISBN o ID v?lido.";
		return;
	}

	// El scanner y el Arduino usan puertos independientes. Solo consultar el
	// cat?logo cuando el COM del Arduino ya est? abierto para enviar la respuesta.
	if (!arduinoSerialPort?.writable) {
		if (status) status.textContent = "Conect? primero el COM del Arduino LCD; el scanner usa otro COM.";
		showSerialStatus(arduinoDisplayStatus, "Arduino LCD desconectado. Puls? Conectar COM del Arduino LCD y eleg? su puerto.", 10000);
		return;
	}

	if (status) status.textContent = "Buscando el libro en el cat?logo?";
	try {
		const match = await findBookTitleByCode(code);
		if (!match?.title) {
			const sent = arduinoSerialPort?.writable ? await sendBookToArduino("error") : false;
			if (status) status.textContent = sent
				? `No encontramos un libro para ${code}. Aviso enviado al LCD.`
				: `No encontramos un libro para ${code}.`;
			return;
		}

		const sent = arduinoSerialPort?.writable ? await sendBookToArduino(match.title) : false;
		if (status && arduinoSerialPort?.writable) status.textContent = sent
			? `Libro encontrado y enviado al LCD: ${match.title}.`
			: `Libro encontrado: ${match.title}; fall? el env?o al LCD.`;
		else if (status) status.textContent = `Libro encontrado: ${match.title}.`;
		if (match.card) {
			match.card.scrollIntoView({ behavior: "smooth", block: "center" });
			match.card.style.outline = "3px solid #0f766e";
			setTimeout(() => { match.card.style.outline = ""; }, 2500);
		}
	} catch (error) {
		console.error("Error buscando t?tulo por ISBN:", error);
		if (status) status.textContent = "No se pudo consultar el cat?logo. Intent? de nuevo.";
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

	const addButton = event.target.closest('[data-purchase-action="add"]');
	if (addButton) {
		const card = addButton.closest(".book-card");
		if (!card) return;
		addToCart({
			id: Number(addButton.dataset.bookId),
			language: addButton.dataset.bookLanguage,
			price: Number(addButton.dataset.bookPrice),
			title: card.querySelector("h3")?.textContent.trim() || "Libro"
		});
	}
});

loadBookRatings();

/* ------------------------------------------------------------------ *
 * Libros de pago: comprar, estado de compra y acceso al texto.
 * ------------------------------------------------------------------ */

function formatPrice(value) {
	return `$${Number(value).toLocaleString("es-UY", { minimumFractionDigits: 0, maximumFractionDigits: 2 })}`;
}

function purchasePrice(book) {
	if (book?.precio === null || book?.precio === undefined || book.precio === "") return null;
	const price = Number(book.precio);
	return Number.isFinite(price) && price >= 0 ? price : null;
}

function bookKey(bookId, language) {
	return `${language}:${bookId}`;
}

function persistCart() {
	try {
		sessionStorage.setItem(cartStorageKey, JSON.stringify([...cartItems.values()]));
	} catch (error) {
		console.error("No se pudo guardar el carrito para esta sesión:", error);
		showToast("No se pudo guardar el carrito para esta sesión.", "error");
	}
}

function renderCart() {
	const items = cartCatalogReady ? [...cartItems.values()] : [];
	const count = items.length;
	if (cartCount) cartCount.textContent = String(count);
	if (cartCount?.parentElement) {
		cartCount.parentElement.setAttribute(
			"aria-label",
			`Abrir carrito, ${count} ${count === 1 ? "artículo" : "artículos"}`
		);
	}
	if (cartEmpty) cartEmpty.hidden = count > 0;
	if (!count && cartStatus) {
		cartStatus.hidden = true;
		cartStatus.textContent = "";
	}
	if (cartLines) {
		cartLines.replaceChildren();
		items.forEach((item) => {
			const line = document.createElement("li");
			line.className = "cart-item";
			const details = document.createElement("div");
			details.className = "cart-item__details";
			const title = document.createElement("strong");
			title.textContent = item.title;
			const meta = document.createElement("span");
			meta.textContent = `${item.language === "es" ? "Español" : "English"} · ${formatPrice(item.price)}`;
			const remove = document.createElement("button");
			remove.className = "cart-item__remove";
			remove.type = "button";
			remove.dataset.cartRemove = "";
			remove.dataset.bookId = String(item.id);
			remove.dataset.bookLanguage = item.language;
			remove.textContent = "Quitar";
			details.append(title, meta);
			line.append(details, remove);
			cartLines.append(line);
		});
	}
	if (cartTotal) {
		cartTotal.textContent = formatPrice(items.reduce((total, item) => total + item.price, 0));
	}
	if (cartCheckout) cartCheckout.disabled = count === 0 || cartCheckoutInProgress;

	$$('[data-purchase-action="add"]').forEach((button) => {
		const key = bookKey(button.dataset.bookId, button.dataset.bookLanguage);
		button.textContent = cartItems.has(key) ? "En el carrito" : "Agregar al carrito";
	});
}

function restoreCart() {
	try {
		const stored = JSON.parse(sessionStorage.getItem(cartStorageKey) || "[]");
		if (!Array.isArray(stored)) throw new Error("El contenido guardado no es una lista.");
		stored.forEach((item) => {
			const id = Number(item?.id);
			const price = Number(item?.price);
			if (
				!Number.isInteger(id) ||
				id <= 0 ||
				(item?.language !== "en" && item?.language !== "es") ||
				!Number.isFinite(price) ||
				price < 0 ||
				typeof item?.title !== "string" ||
				!item.title.trim()
			) return;
			cartItems.set(bookKey(id, item.language), {
				id,
				language: item.language,
				price,
				title: item.title.trim()
			});
		});
	} catch (error) {
		console.error("No se pudo restaurar el carrito:", error);
	}
	renderCart();
}

function addToCart(item) {
	if (
		!Number.isInteger(item.id) ||
		item.id <= 0 ||
		(item.language !== "en" && item.language !== "es") ||
		!Number.isFinite(item.price) ||
		item.price < 0 ||
		typeof item.title !== "string" ||
		!item.title.trim()
	) {
		console.error("No se puede agregar al carrito un libro con datos inválidos.");
		showToast("No se pudo agregar este libro al carrito.", "error");
		return;
	}

	const key = bookKey(item.id, item.language);
	if (ownsBook(item.id, item.language)) {
		showToast("Este libro ya está en tu biblioteca.", "error");
		return;
	}
	if (cartItems.has(key)) {
		showToast("Este libro ya está en el carrito.", "success");
		return;
	}
	cartItems.set(key, item);
	persistCart();
	renderCart();
	showToast(`${item.title} se agregó al carrito.`, "success");
}

function removeFromCart(bookId, language) {
	if (!cartItems.delete(bookKey(bookId, language))) return;
	persistCart();
	renderCart();
}

restoreCart();

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
			cartItems.delete(bookKey(purchase.id, purchase.idioma));
		});
		persistCart();
		renderCart();
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
 * La tarjeta muestra una sola accion: agregar al carrito hasta la compra
 * y "Leer libro" una vez que el libro esta en la biblioteca del usuario.
 */
function refreshPaidCard(card, bookId, language) {
	const owned = ownsBook(bookId, language);
	card.classList.toggle("book-card--owned", owned);

	const buyButton = card.querySelector('[data-purchase-action="add"]');
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
		return false;
	}

	const buttonLabel = button.textContent;
	button.disabled = true;
	button.textContent = "Procesando...";

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
		removeFromCart(bookId, language);

		if (typeof data?.mensaje === "string") {
			showToast(data.mensaje, "success");
		}
		return true;
	} catch (error) {
		console.error("Error al comprar el libro:", error);
		showToast(error.message || "No se pudo completar la compra.", "error");
		return false;
	} finally {
		button.disabled = button === cartCheckout && (cartCheckoutInProgress || !cartItems.size);
		button.textContent = buttonLabel;
	}
}

async function checkoutCart() {
	if (!cartItems.size || !cartCheckout || cartCheckoutInProgress) return;
	if (!userLoggedIn) {
		cartModal?.close();
		openLoginModal();
		showToast("Inicia sesión para finalizar la compra.", "error");
		return;
	}

	cartCheckoutInProgress = true;
	cartCheckout.textContent = "Procesando compra...";
	if (cartStatus) {
		cartStatus.hidden = false;
		cartStatus.textContent = "Procesando los libros del carrito...";
	}
	renderCart();
	let completed = 0;
	let failed = false;

	for (const item of [...cartItems.values()]) {
		const success = await buyBook(item.id, item.language, item.price, cartCheckout);
		if (!success) {
			failed = true;
			break;
		}
		completed += 1;
	}

	cartCheckoutInProgress = false;
	cartCheckout.textContent = "Finalizar compra";
	renderCart();
	if (failed) {
		const message = completed
			? `Se completaron ${completed} compras; el resto sigue en el carrito.`
			: "No se pudo completar la compra. El carrito sigue intacto.";
		if (cartStatus) {
			cartStatus.hidden = false;
			cartStatus.textContent = message;
		}
		showToast(message, "error");
	} else if (completed) {
		if (cartStatus) {
			cartStatus.hidden = true;
			cartStatus.textContent = "";
		}
		showToast(`${completed} ${completed === 1 ? "libro comprado" : "libros comprados"} correctamente.`, "success");
		if (!cartItems.size) cartModal?.close();
	}
}

cartLines?.addEventListener("click", (event) => {
	const button = event.target.closest("[data-cart-remove]");
	if (button) removeFromCart(button.dataset.bookId, button.dataset.bookLanguage);
});
cartCheckout?.addEventListener("click", checkoutCart);

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

	const purchasableBooks = new Map();
	books.forEach((book) => {
		const id = Number(book.id);
		const price = purchasePrice(book);
		if (!Number.isInteger(id) || id <= 0 || price === null) return;
		const language = book.idioma === "es" ? "es" : "en";
		purchasableBooks.set(bookKey(id, language), {
			id,
			language,
			price,
			title: typeof book.titulo === "string" && book.titulo.trim() ? book.titulo.trim() : "Libro"
		});
	});

	cartItems.forEach((item, key) => {
		const purchasable = purchasableBooks.get(key);
		if (!purchasable || ownsBook(item.id, item.language)) {
			cartItems.delete(key);
			return;
		}
		item.price = purchasable.price;
		item.title = purchasable.title;
	});

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

		const priceAmount = purchasePrice(book);
		const isPaid = priceAmount !== null;
		if (isPaid) {
			const price = document.createElement("span");
			price.className = "book-card__price";
			price.textContent = formatPrice(priceAmount);
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
			buyButton.dataset.purchaseAction = "add";
			buyButton.dataset.bookId = String(book.id);
			buyButton.dataset.bookLanguage = language;
			buyButton.dataset.bookPrice = String(priceAmount);
			buyButton.textContent = "Agregar al carrito";
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
	cartCatalogReady = true;
	persistCart();
	renderCart();

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
	const bookTitle = card.querySelector("h3")?.textContent || "";
	readerTitle.textContent = bookTitle;
	sendBookToArduino(bookTitle);
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
	const card = button.closest(".book-card");
	openBookReader(button.dataset.readerUrl, card);
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