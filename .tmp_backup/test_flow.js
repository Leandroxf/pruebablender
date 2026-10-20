/**
 * Prueba del flujo de compra de El Principito con jsdom.
 * Cubre: usuario anonimo (debe pedir login) y usuario registrado (compra).
 */
const fs = require("fs");
const path = require("path");
const { JSDOM } = require("jsdom");

const root = process.cwd();
const html = fs.readFileSync(path.join(root, "app/Vistas/home.php"), "utf8")
	.replace(/<\?php.*?\?>/gs, "")
	.replace(/<\?=.*?\?>/gs, "");

const dom = new JSDOM(html, { url: "http://127.0.0.1:8080/" });
const { window } = dom;
const { document } = window;

global.window = window;
global.document = document;

let loggedIn = false;
global.__compras = [];
global.__loginOpened = false;

global.fetch = async (url, options = {}) => {
	const href = String(url);
	const method = (options.method || "GET").toUpperCase();

	if (href.includes("api/compra.php")) {
		if (method === "GET") {
			return { ok: true, status: 200, json: async () => ({ compras: global.__compras }) };
		}
		const body = JSON.parse(options.body);
		global.__compras = [{ id: body.id, idioma: body.idioma, precio_pagado: 450, fecha: "2026-10-20" }];
		return { ok: true, status: 201, json: async () => ({ mensaje: "¡Compra realizada!", compras: global.__compras }) };
	}
	return { ok: true, status: 200, json: async () => ({}) };
};

const meta = document.createElement("meta");
meta.setAttribute("name", "user-logged-in");
meta.setAttribute("content", "0");
document.head.append(meta);

const mainJs = fs.readFileSync(path.join(root, "main.js"), "utf8");

function buildApi() {
	const fn = new Function("window", "document", "fetch", "console", "setTimeout", "clearTimeout", "URL", `
	const userLoggedIn = document.querySelector('meta[name="user-logged-in"]')?.content === "1";
	const purchasedBooks = new Map();
	const bookCards = [];
	const purchaseEndpoint = new URL("api/compra.php", document.baseURI);
	const purchaseCsrfToken = "tok";
	function formatPrice(v){ return "$" + Number(v).toLocaleString("es-UY", {minimumFractionDigits:0, maximumFractionDigits:2}); }
	function bookKey(id, lang){ return lang + ":" + id; }
	function ownsBook(id, lang){ return purchasedBooks.has(bookKey(id, lang)); }
	function openLoginModal() { global.__loginOpened = true; }
	function showToast(message, type) { global.__toast = message; }
	${mainJs.match(/async function loadUserPurchases[\s\S]*?\n}\n/)[0]}
	${mainJs.match(/function refreshPaidCard[\s\S]*?\n}\n/)[0]}
	${mainJs.match(/async function buyBook[\s\S]*?\n}\n/)[0]}
	return { loadUserPurchases, refreshPaidCard, buyBook, ownsBook, purchasedBooks };
	`);
	return fn(window, document, global.fetch, console, setTimeout, clearTimeout, URL);
}

function buildCard() {
	const card = document.createElement("article");
	card.className = "book-card";
	card.dataset.language = "es";
	card.dataset.requiresPurchase = "1";

	const info = document.createElement("div");
	info.className = "book-card__info";

	const badge = document.createElement("span");
	badge.className = "book-card__owned-badge";
	badge.textContent = "Comprado";
	badge.hidden = true;

	const buyButton = document.createElement("button");
	buyButton.className = "book-buy";
	buyButton.dataset.purchaseAction = "buy";
	buyButton.dataset.bookId = "86001";
	buyButton.dataset.bookLanguage = "es";
	buyButton.dataset.bookPrice = "450";
	buyButton.textContent = "Comprar libro";

	const readButton = document.createElement("button");
	readButton.className = "book-read";
	readButton.dataset.readerUrl = "books/86001.html";
	readButton.textContent = "Leer libro";

	info.append(badge, buyButton, readButton);
	card.append(info);
	document.body.append(card);

	return { card, badge, buyButton, readButton };
}

let failures = 0;
const check = (label, cond) => {
	if (!cond) failures += 1;
	console.log(`${cond ? "PASS" : "FAIL"}  ${label}`);
};

(async () => {
	// ---- Escenario 1: visitante anonimo ----
	console.log("--- Escenario 1: visitante anonimo ---");
	meta.setAttribute("content", "0");
	global.__loginOpened = false;
	global.__compras = [];

	let api = buildApi();
	let { card, badge, buyButton, readButton } = buildCard();

	api.refreshPaidCard(card, 86001, "es");
	check("boton comprar visible", buyButton.hidden === false);
	check("boton comprar dice 'Comprar libro'", buyButton.textContent === "Comprar libro");
	check("boton leer oculto hasta comprar", readButton.hidden === true);
	check("insignia 'Comprado' oculta", badge.hidden === true);

	await api.buyBook(86001, "es", 450, buyButton);
	check("anonimo: se abre el modal de login", global.__loginOpened === true);
	check("anonimo: no se registra compra", global.__compras.length === 0);

	// ---- Escenario 2: usuario registrado que compra ----
	console.log("\n--- Escenario 2: usuario registrado ---");
	meta.setAttribute("content", "1");
	global.__loginOpened = false;
	global.__compras = [];

	api = buildApi();
	({ card, badge, buyButton, readButton } = buildCard());

	api.refreshPaidCard(card, 86001, "es");
	check("antes de comprar: boton 'Comprar libro'", buyButton.textContent === "Comprar libro");
	check("antes de comprar: boton leer oculto", readButton.hidden === true);

	await api.buyBook(86001, "es", 450, buyButton);
	api.refreshPaidCard(card, 86001, "es");

	check("compra registrada", api.ownsBook(86001, "es"));
	check("boton comprar oculto tras comprar", buyButton.hidden === true);
	check("boton leer visible tras comprar", readButton.hidden === false);
	check("boton leer dice 'Leer libro'", readButton.textContent === "Leer libro");
	check("insignia 'Comprado' visible", badge.hidden === false);
	check("tarjeta con clase owned", card.classList.contains("book-card--owned"));
	check("mensaje de compra mostrado", typeof global.__toast === "string" && global.__toast.length > 0);

	console.log(`\n${failures === 0 ? "TODO OK" : failures + " FALLAS"}`);
	process.exit(failures === 0 ? 0 : 1);
})();