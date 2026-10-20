import io

p = '.tmp_backup/patch_button_label.py'
s = io.open(p, encoding='utf-8', newline='').read()

old = '''old = \'\'\'\\treaderLockedText.textContent = error?.message || "Necesitás comprar este libro para leerlo.";\'\'\'
new = \'\'\'\\tconst priceText = meta.precio ? ` por ${formatPrice(meta.precio)}` : "";
\\treaderLockedText.textContent = `${error?.message || "Necesitás comprar este libro para leerlo."}${meta.precio ? ` Cuesta ${formatPrice(meta.precio)}${priceText ? "" : ""}.` : ""}`;\'\'\''''

new = '''old = \'\'\'\\treaderLockedText.textContent = error?.message || "Necesitás comprar este libro para leerlo.";\'\'\'
new = \'\'\'\\tconst reason = error?.message || "Necesitás comprar este libro para leerlo.";
\\treaderLockedText.textContent = meta.precio
\\t\\t? `${reason} Cuesta ${formatPrice(meta.precio)}.`
\\t\\t: reason;\'\'\''''

assert s.count(old) == 1, ('step4', s.count(old))
io.open(p, 'w', encoding='utf-8', newline='').write(s.replace(old, new))
print('step4 simplified')