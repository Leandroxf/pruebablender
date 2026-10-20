import io

p = '.tmp_backup/test_flow.js'
s = io.open(p, encoding='utf-8', newline='').read()

old = '''\tconst purchasedBooks = new Map();'''
new = '''\tconst purchasedBooks = new Map();
\tconst bookCards = [];'''
assert s.count(old) == 1, ('bookcards', s.count(old))
s = s.replace(old, new)

io.open(p, 'w', encoding='utf-8', newline='').write(s)
print('ok')