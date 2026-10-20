import io

p = 'app/Vistas/home.php'
s = io.open(p, encoding='utf-8', newline='').read()

old = '''        <p class="reader__status" id="reader-status" aria-live="polite">Cada libro se lee en su idioma original.</p>'''
new = '''        <p class="reader__status" id="reader-status" aria-live="polite">Cada libro se lee en su idioma original.</p>
        <div class="reader__locked" id="reader-locked" hidden>
            <p class="reader__locked-text" id="reader-locked-text"></p>
            <button class="book-buy" type="button" id="reader-buy" hidden></button>
        </div>'''

assert s.count(old) == 1, ('reader locked markup', s.count(old))
io.open(p, 'w', encoding='utf-8', newline='').write(s.replace(old, new))
print('markup ok')