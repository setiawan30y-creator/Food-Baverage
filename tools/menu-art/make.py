"""Generator gambar contoh menu snack. @2026-Alifiandra"""
import os, sys
from lib import canvas, save, T
import parts as P

OUT = sys.argv[1] if len(sys.argv) > 1 else 'out'
os.makedirs(OUT, exist_ok=True)
ONLY = set(sys.argv[2:])

BG = {
    'kentang': ('#FFF3DC', '#FFE1B8', '#D9A06A', '#B97F4C', '#F6B65C'),
    'corndog': ('#FFF7D6', '#FFE6A6', '#C98E5C', '#A9703F', '#F3C04A'),
    'camilan': ('#FFEFE6', '#FFD9C7', '#C98B6E', '#A66B50', '#F59A7A'),
    'paket': ('#FFF0E0', '#FFD6B0', '#C9875A', '#A56738', '#F28C4B'),
    'minuman': ('#EAF6FF', '#CFE9FB', '#D2A475', '#AF8150', '#8CC8F0'),
}


def job(name, cat):
    def deco(fn):
        JOBS.append((name, cat, fn))
        return fn
    return deco


JOBS = []


@job('kentang-crispy-original', 'kentang')
def _(im):
    P.fries_cup(im, T(380, 250), salt=True)
    P.dip_cup(im, T(640, 470), '#D7261E')


@job('kentang-crispy-keju', 'kentang')
def _(im):
    P.fries_cup(im, T(400, 250), seasoning=(['#FFC21A', '#FFD95A', '#F2A20E'], 340), salt=False, seed=4)


@job('kentang-crispy-balado', 'kentang')
def _(im):
    P.fries_cup(im, T(400, 250), seasoning=(['#E2421C', '#C42A12', '#F2702E', '#E2421C', '#5E8F2E'], 300), salt=False, seed=6)


@job('kentang-spiral', 'kentang')
def _(im):
    P.spiral(im, T(400, 280), seasoning=(['#F5B21A', '#FFD34E', '#E07A1F'], 260))


@job('kentang-spiral-keju-lumer', 'kentang')
def _(im):
    P.spiral(im, T(400, 280), cheese=True)


@job('corndog-sosis', 'corndog')
def _(im):
    P.corndog(im, T(400, 245, 1.1), angle=10, kind='sosis')


@job('corndog-mozzarella', 'corndog')
def _(im):
    P.corndog(im, T(400, 305, 0.88), angle=6, kind='mozza')


@job('corndog-mix-sosis-mozza', 'corndog')
def _(im):
    P.corndog(im, T(400, 255, 1.05), angle=-8, kind='mix')


@job('corndog-kentang', 'corndog')
def _(im):
    P.corndog(im, T(400, 268, 1.05), angle=9, kind='kentang')


@job('chicken-popcorn', 'camilan')
def _(im):
    P.popcorn_cup(im, T(380, 250))
    P.dip_cup(im, T(640, 470), '#F7EBC8')


@job('sosis-bakar-jumbo', 'camilan')
def _(im):
    P.sausage_skewer(im, T(400, 330, 1.12))


@job('churros-gula-kayu-manis', 'camilan')
def _(im):
    P.churros_cup(im, T(380, 250))
    P.dip_cup(im, T(640, 470), '#FFC93C')


@job('paket-hemat-snack', 'paket')
def _(im):
    P.glass(im, T(640, 300, 0.72), liquid=('#F0A94E', '#B8621F'))
    P.fries_cup(im, T(215, 290, 0.78), salt=True)
    P.corndog(im, T(432, 248, 0.82), angle=8, kind='sosis')
    P.badge(im, T(105, 95, 0.95), 'HEMAT', '3 in 1')


@job('es-teh-manis', 'minuman')
def _(im):
    P.glass(im, T(400, 300), liquid=('#F0A94E', '#B8621F'))


@job('es-jeruk', 'minuman')
def _(im):
    P.glass(im, T(400, 300), liquid=('#FFC04D', '#F7891E'), garnish=('#F28A1A', '#FFB13B'), straw='#2F9E4A')


@job('lemon-tea', 'minuman')
def _(im):
    P.glass(im, T(400, 300), liquid=('#E2A447', '#A25E18'), garnish=('#E8C21A', '#FFE45C'), straw='#F2B705')


@job('air-mineral-600-ml', 'minuman')
def _(im):
    P.bottle(im, T(400, 300))


for name, cat, fn in JOBS:
    if ONLY and name not in ONLY:
        continue
    im = canvas(*BG[cat])
    fn(im)
    save(im, os.path.join(OUT, name + '.webp'))
    print('ok', name)
