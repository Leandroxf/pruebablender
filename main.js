document.documentElement.classList.add("js");

document.querySelectorAll("[data-modal-open]").forEach((button) => {
	const modal = document.getElementById(button.dataset.modalOpen);

	button.addEventListener("click", () => modal?.showModal());
});

document.querySelector("#register-form")?.addEventListener("submit", (event) => {
	event.preventDefault();
	const status = document.querySelector("#register-status");
	if (status) status.textContent = "El registro de cuentas todavía no está habilitado.";
});

const catalog = document.querySelector(".catalog");

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

const searchInput = document.querySelector("#book-search");
const genreButtons = [...document.querySelectorAll("[data-genre-filter]")];
const languageButtons = [...document.querySelectorAll("[data-language-filter]")];
const bookCards = [...document.querySelectorAll(".book-card")];
const bookResults = document.querySelector("#book-results");
const emptyMessage = document.querySelector("#book-empty");
let selectedGenre = "all";
let selectedLanguage = "all";

function normalizeText(value) {
	return String(value ?? "").normalize("NFD").replace(/[\u0300-\u036f]/g, "").toLowerCase();
}

function filterBooks() {
	const query = normalizeText(searchInput?.value.trim());
	let visibleCount = 0;

	bookCards.forEach((card) => {
		const matchesSearch = normalizeText(card.textContent).includes(query);
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

async function loadAdditionalCatalogBooks() {
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
		info.append(genre, title, author, readButton);
		card.append(cover, info);
		catalog.querySelector(".book-grid")?.append(card);
		bookCards.push(card);
		existingBooks.add(key);
	});

	const spanishBooks = books.filter((book) => book.idioma === "es").length;
	const introEyebrow = document.querySelector(".intro__eyebrow");
	if (introEyebrow) introEyebrow.textContent = `${bookCards.length} clásicos · Texto íntegro · Inglés y español`;
	const introMeta = document.querySelector(".intro__meta");
	if (introMeta) introMeta.textContent = `${bookCards.length} títulos · ${spanishBooks} libros íntegros en español · lectura en voz alta`;
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

	try {
		if (!readerDialog.open) readerDialog.showModal();

		const idMatch = bookUrl.match(/(\d+)\.html$/);
		const bookId = idMatch ? idMatch[1] : null;
		if (!bookId) throw new Error("Sin ID");
		if (requestId !== readerRequestId) return;

		const catalog = await loadBookCatalog(bookLang);
		const data = catalog[bookId];
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