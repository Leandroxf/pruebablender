import io

p = '.tmp_backup/test_flow.js'
s = io.open(p, encoding='utf-8', newline='').read()

old = '''\tfunction ownsBook(id, lang){ return purchasedBooks.has(bookKey(id, lang)); }
'''
new = '''\tfunction ownsBook(id, lang){ return purchasedBooks.has(bookKey(id, lang)); }
\tfunction openLoginModal() { global.__loginOpened = true; }
'''
assert s.count(old) == 1, ('harness', s.count(old))
io.open(p, 'w', encoding='utf-8', newline='').write(s.replace(old, new))
print('harness ok')