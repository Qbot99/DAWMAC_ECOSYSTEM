# -*- coding: utf-8 -*-
"""Zlozenie wynikow z YouTube i Facebooka w jedna liste per model felgi."""
import io, json, re, csv
from collections import defaultdict
def norm(s): return re.sub(r'[\s\-_`´’\'".,:;]+', '', str(s).upper())[:64]

dispP, dispM, sklep = {}, {}, {}
for l in io.open('slownik.tsv', encoding='utf-8'):
    c = l.rstrip('\n').split('\t')
    if len(c) < 3: continue
    p, m = c[0].strip(), c[1].strip()
    dispP[norm(p)] = p
    dispM.setdefault(norm(p), {})[norm(m)] = m
    sklep[(norm(p), norm(m))] = int(c[2])

grupy = defaultdict(lambda: {'yt': [], 'fb': []})
for l in io.open('yt_dopasowane.tsv', encoding='utf-8'):
    c = l.rstrip('\n').split('\t')
    if len(c) < 5 or not c[2]: continue
    grupy[(norm(c[1]), norm(c[2]))]['yt'].append(
        {'id': c[0], 't': c[4][:110], 'pewny': c[3].startswith('producent+model')})
for l in io.open('fb_per_model.tsv', encoding='utf-8'):
    c = l.rstrip('\n').split('\t')
    if len(c) < 3: continue
    pn, mn = c[0].split('|')
    for poz in c[2].split(','):
        vid, typ, kod = poz.split(':')
        grupy[(pn, mn)]['fb'].append({'id': vid, 'typ': typ, 'pewny': kod == 'A'})

modele = []
for (pn, mn), g in grupy.items():
    modele.append({
        'p': dispP.get(pn, pn if pn != '?' else '(nierozpoznany)'),
        'm': dispM.get(pn, {}).get(mn, mn),
        'n': len(g['yt']) + len(g['fb']),
        'pewne': sum(1 for x in g['yt'] + g['fb'] if x['pewny']),
        'yt': len(g['yt']), 'fb': len(g['fb']),
        'sklep': sklep.get((pn, mn), 0),
        'forged': 'FORGED' in pn, 'nieznany': pn == '?',
        'v': [{'id': x['id'], 'z': 'yt', 't': x['t'], 'pewny': x['pewny']} for x in g['yt']]
           + [{'id': x['id'], 'z': 'fb' + x['typ'], 't': '', 'pewny': x['pewny']} for x in g['fb']]})
modele.sort(key=lambda x: (-x['n'], x['p'], x['m']))

brakYT = [{'id': c[0], 't': c[4][:120]} for c in
          (l.rstrip('\n').split('\t') for l in io.open('yt_dopasowane.tsv', encoding='utf-8'))
          if len(c) >= 5 and not c[2]]
d = {'modele': modele, 'brakYT': brakYT,
     'stat': {'ytRazem': 600, 'fbRazem': 650,
              'ytZModelem': sum(m['yt'] for m in modele),
              'fbZModelem': sum(m['fb'] for m in modele)}}
io.open('polaczone.json','w',encoding='utf-8').write(json.dumps(d, ensure_ascii=False))

def url(v):
    return ('https://youtu.be/'+v['id']) if v['z']=='yt' else \
           ('https://www.facebook.com/reel/'+v['id']) if v['z']=='fbr' else \
           ('https://www.facebook.com/dawmacpolska/videos/'+v['id'])
with io.open('filmiki-do-sprawdzenia.csv','w',encoding='utf-8-sig',newline='') as f:
    w = csv.writer(f, delimiter=';')
    w.writerow(['Producent','Model','Filmikow','YouTube','Facebook','Pewnych',
                'Do sprawdzenia','Produktow w sklepie','Pierwszy filmik','Wszystkie filmiki'])
    for m in modele:
        w.writerow([m['p'], m['m'], m['n'], m['yt'], m['fb'], m['pewne'],
                    m['n']-m['pewne'], m['sklep'], url(m['v'][0]),
                    ' '.join(url(v) for v in m['v'])])

pewnych = sum(1 for m in modele if m['pewne'] == m['n'])
zero    = sum(1 for m in modele if m['pewne'] == 0)
print(f"modeli: {len(modele)}   filmikow: {sum(m['n'] for m in modele)} "
      f"(YT {d['stat']['ytZModelem']} + FB {d['stat']['fbZModelem']})")
print(f"w pelni pewne: {pewnych}   czesciowo: {len(modele)-pewnych-zero}   "
      f"marka nigdzie nie padla: {zero}")
