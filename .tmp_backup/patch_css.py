import io

p = 'style.css'
s = io.open(p, encoding='utf-8', newline='').read().replace(chr(13)+chr(10), chr(10))

anchor = '''.book-read:hover{
    border-color: #0f766e;
    background: #e7eee9;
}'''

addition = '''.book-read:hover{
    border-color: #0f766e;
    background: #e7eee9;
}
/* --- Libros de pago: boton de compra, insignia de comprado y aviso del lector --- */
.book-buy{
    display: inline-flex;
    align-items: center;
    justify-content: center;
    align-self: flex-start;
    margin-top: 0.55rem;
    padding: 0.45rem 0.7rem;
    border: 1px solid #0f766e;
    border-radius: 4px;
    color: #fff;
    background: #0f766e;
    font-family: inherit;
    font-size: 0.875rem;
    font-weight: 700;
    cursor: pointer;
}
.book-buy:hover{
    background: #0b5d57;
    border-color: #0b5d57;
}
.book-buy:focus-visible{
    outline: 3px solid rgb(15 118 110 / 25%);
    outline-offset: 2px;
}
.book-buy:disabled{
    opacity: 0.6;
    cursor: progress;
}
.book-card__owned-badge{
    display: inline-block;
    align-self: flex-start;
    margin-top: 0.55rem;
    padding: 0.2rem 0.55rem;
    border: 1px solid #b7e0cd;
    border-radius: 999px;
    color: #0b5d57;
    background: #e7f5ee;
    font-size: 0.78rem;
    font-weight: 700;
}
.book-card--owned .book-read{
    border-color: #0f766e;
    color: #fff;
    background: #0f766e;
}
.reader__locked{
    display: flex;
    flex-direction: column;
    align-items: flex-start;
    gap: 0.75rem;
    margin: 1rem 0;
    padding: 1.25rem;
    border: 1px dashed #c9a227;
    border-radius: 8px;
    background: #fdf8e7;
}
.reader__locked[hidden]{
    display: none;
}
.reader__locked-text{
    margin: 0;
    color: #6b5510;
    font-size: 1rem;
}
.toast{
    position: fixed;
    left: 50%;
    bottom: 1.5rem;
    z-index: 60;
    transform: translate(-50%, 1rem);
    max-width: min(90vw, 30rem);
    padding: 0.75rem 1.1rem;
    border-radius: 6px;
    color: #fff;
    font-size: 0.9rem;
    font-weight: 600;
    box-shadow: 0 8px 24px rgb(0 0 0 / 25%);
    opacity: 0;
    pointer-events: none;
    transition: opacity 0.2s ease, transform 0.2s ease;
}
.toast.is-visible{
    opacity: 1;
    transform: translate(-50%, 0);
}
.toast--success{
    background: #0f766e;
}
.toast--error{
    background: #b91c1c;
}'''

assert s.count(anchor) == 1, ('css anchor', s.count(anchor))
s = s.replace(anchor, addition)

io.open(p, 'w', encoding='utf-8', newline='').write(s)
print('css ok')