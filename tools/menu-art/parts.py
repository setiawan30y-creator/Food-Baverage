"""Komponen ilustrasi snack. @2026-Alifiandra"""
import math
import random
from PIL import Image, ImageDraw
from lib import (K, SW, SH, T, S, rgba, mix, grad, shape, new_layer, clip_to, shadow,
                 sprite_paste, specks, comp, text)

GOLD_L, GOLD_M, GOLD_D = '#FFEAA6', '#F5B533', '#D08A22'


def curve(p0, p1, p2, n=24):
    out = []
    for i in range(n + 1):
        u = i / n
        out.append(((1 - u) ** 2 * p0[0] + 2 * (1 - u) * u * p1[0] + u ** 2 * p2[0],
                    (1 - u) ** 2 * p0[1] + 2 * (1 - u) * u * p1[1] + u ** 2 * p2[1]))
    return out


# ---------- stik (kentang goreng, churros) ----------
def stick_sprite(w, h, light, mid, dark, tip=True, ridges=0, k=K):
    W2, H2 = max(4, round(w * k)), max(4, round(h * k))
    pad = 4
    spr = Image.new('RGBA', (W2 + pad * 2, H2 + pad * 2), (0, 0, 0, 0))
    r = round(min(w, h) * 0.38 * k)
    box = [pad, pad, pad + W2, pad + H2]
    shape(spr, [('rrect', box, r)], (mid, light, 'c'))
    if tip:
        shape(spr, [('rrect', [pad, pad, pad + W2, pad + round(H2 * 0.14)], r)], dark, alpha=70, blur=round(2 * k))
    if ridges:
        d = new_layer(spr.size)
        dd = ImageDraw.Draw(d)
        for i in range(1, ridges):
            x = pad + W2 * i / ridges
            dd.line([(x, pad + r * 0.6), (x, pad + H2 - r * 0.6)], fill=rgba(dark, 150), width=max(1, round(1.4 * k)))
        spr.alpha_composite(d)
    # garis tepi tipis
    o = new_layer(spr.size)
    ImageDraw.Draw(o).rounded_rectangle(box, radius=r, outline=rgba(dark, 90), width=max(1, round(1.0 * k)))
    spr.alpha_composite(o)
    return spr


def fries_layer(t, seed=3, count=26, spread=112, top=-140, length=(160, 200)):
    lay = new_layer()
    rnd = random.Random(seed)
    items = []
    for i in range(count):
        x = rnd.uniform(-spread, spread)
        items.append((rnd.uniform(top, top + 70), x, rnd.uniform(-15, 15), rnd.uniform(*length), rnd.uniform(25, 31), i % 3))
    items.sort(key=lambda it: it[0])
    for y, x, ang, ln, wd, shade in items:
        light = mix(GOLD_L, '#FFFFFF', 0.15) if shade == 0 else GOLD_L
        mid = mix(GOLD_M, '#B9771F', 0.25) if shade == 2 else GOLD_M
        spr = stick_sprite(wd * t.sc, ln * t.sc, light, mid, GOLD_D)
        cx, cy = t.p(x, y + ln / 2)
        sprite_paste(lay, spr, cx, cy, ang)
    return lay


def cup_back(target, t, color='#9E1B14'):
    shape(target, [('poly', [t.p(-136, -14), t.p(136, -14), t.p(120, 70), t.p(-120, 70)])], color)


def cup_front(target, t, c_mid='#EF4A3A', c_edge='#B81F17', band='#FFFFFF', emblem=True, stripes=False):
    top = curve((-142, 0), (0, 40), (142, 0))
    body = [t.p(*p) for p in top] + [t.p(100, 222), t.p(-100, 222)]
    shape(target, [('poly', body)], (c_edge, c_mid, 'c'))
    if stripes:
        lay = new_layer()
        for i in range(-3, 4):
            x = i * 34
            pts = [t.p(x - 9, 10 + abs(x) * 0.18), t.p(x + 9, 10 + abs(x) * 0.18), t.p(x * 0.72 + 7, 222), t.p(x * 0.72 - 7, 222)]
            shape(lay, [('poly', pts)], band, alpha=235)
        lay = clip_to(lay, _mask_of(body))
        target.alpha_composite(lay)
    else:
        # pita putih bergelombang
        up = [(x, y + 34) for x, y in curve((-137, 0), (0, 40), (137, 0))]
        lo = [(x * 0.97, y + 18) for x, y in reversed(up)]
        shape(target, [('poly', [t.p(*p) for p in up + lo])], band, alpha=240)
    if emblem:
        shape(target, [('ell', t.box(-30, 108, 30, 168))], '#FFFFFF', alpha=245)
        shape(target, [('ell', t.box(-20, 118, 20, 158))], c_mid)
        shape(target, [('poly', _star(t, 0, 138, 13, 6))], '#FFD34E')
    # bayangan bawah & kilau
    shape(target, [('poly', [t.p(-110, 150), t.p(110, 150), t.p(100, 222), t.p(-100, 222)])], ('#00000000', '#00000055', 'v'))
    shape(target, [('poly', [t.p(-118, 30), t.p(-100, 34), t.p(-84, 214), t.p(-94, 214)])], '#FFFFFF', alpha=60)


def _star(t, cx, cy, r, ri, n=5):
    pts = []
    for i in range(n * 2):
        a = -math.pi / 2 + i * math.pi / n
        rr = r if i % 2 == 0 else ri
        pts.append(t.p(cx + rr * math.cos(a), cy + rr * math.sin(a)))
    return pts


def _mask_of(poly_pts):
    m = new_layer()
    ImageDraw.Draw(m).polygon(poly_pts, fill=(255, 255, 255, 255))
    return m


def fries_cup(target, t, seasoning=None, seed=3, salt=True):
    shadow(target, t, 0, 226, 140, 22, alpha=95)
    cup_back(target, t)
    fl = fries_layer(t, seed=seed)
    target.alpha_composite(fl)
    if seasoning or salt:
        d = new_layer()
        if salt:
            specks(d, t, (-130, -160, 130, 40), 70, ['#FFFFFF'], 1.2, 2.2, seed=seed + 9)
        if seasoning:
            cols, n = seasoning
            specks(d, t, (-135, -160, 135, 40), n, cols, 2.0, 4.8, seed=seed + 5)
        target.alpha_composite(clip_to(d, fl))
    cup_front(target, t)


def dip_cup(target, t, sauce, cup='#FFFFFF', rim='#EDE6DD'):
    shadow(target, t, 0, 30, 58, 12, alpha=80)
    shape(target, [('poly', [t.p(-52, 0), t.p(52, 0), t.p(42, 34), t.p(-42, 34)]), ('ell', t.box(-42, 26, 42, 40))], (rim, cup, 'c'))
    shape(target, [('ell', t.box(-54, -12, 54, 12))], cup)
    shape(target, [('ell', t.box(-46, -8, 46, 8))], (sauce, mix(sauce, '#000000', 0.18), 'v'))
    shape(target, [('ell', t.box(-28, -6, -6, -1))], '#FFFFFF', alpha=130)


# ---------- kentang spiral ----------
def spiral(target, t, seasoning=None, cheese=False):
    shadow(target, t, 0, 210, 120, 20, alpha=90)
    shape(target, [('line', [t.p(0, -230), t.p(0, 215)], t.l(10))], ('#E2C18F', '#B98A52', 'c'))
    rings = 13
    lay = new_layer()
    for i in range(rings):
        y = -180 + i * 29
        u = i / (rings - 1)
        rx = 84 + 42 * math.sin(math.pi * (0.12 + 0.76 * u))
        ry = 30
        ang = -12 if i % 2 == 0 else -6
        w2, h2 = S((rx * 2 + 30) * t.sc), S((ry * 2 + 30) * t.sc)
        spr = Image.new('RGBA', (w2, h2), (0, 0, 0, 0))
        lt = T(w2 / 2 / K, h2 / 2 / K, t.sc)
        outer = lt.box(-rx, -ry, rx, ry)
        inner = lt.box(-14, -6, 14, 6)
        shape(spr, [('ell', outer)], (GOLD_L, GOLD_D, 'v'), cut=[('ell', inner)])
        shape(spr, [('ell', lt.box(-rx, -ry + 10, rx, ry))], GOLD_D, alpha=70, cut=[('ell', lt.box(-rx + 6, -ry - 4, rx - 6, ry - 8))])
        o = new_layer(spr.size)
        ImageDraw.Draw(o).ellipse(outer, outline=rgba('#B06E1C', 150), width=S(1.5 * t.sc))
        spr.alpha_composite(o)
        cx, cy = t.p(math.sin(i * 1.3) * 4, y)
        sprite_paste(lay, spr, cx, cy, ang)
    target.alpha_composite(lay)
    if seasoning:
        d = new_layer()
        specks(d, t, (-140, -210, 140, 190), seasoning[1], seasoning[0], 2.0, 4.2, seed=21)
        target.alpha_composite(clip_to(d, lay))
    if cheese:
        cheese_drip(target, t, -165, 105, 150)


def cheese_drip(target, t, y_top, half_w, y_max, seed=4):
    rnd = random.Random(seed)
    lay = new_layer()
    blobs = []
    for i in range(9):
        x = -half_w + i * (half_w * 2 / 8) + rnd.uniform(-8, 8)
        r = rnd.uniform(26, 38)
        blobs.append(('ell', t.box(x - r, y_top - r * 0.7, x + r, y_top + r * 0.7)))
    for i in range(6):
        x = -half_w * 0.8 + i * (half_w * 1.6 / 5) + rnd.uniform(-6, 6)
        ln = rnd.uniform(60, y_max - y_top)
        wd = rnd.uniform(14, 22)
        blobs.append(('line', [t.p(x, y_top), t.p(x, y_top + ln)], t.l(wd)))
    shape(lay, blobs, ('#FFD84D', '#F3A512', 'v'))
    specks(lay, t, (-half_w, y_top - 25, half_w, y_top + 10), 10, [(255, 255, 255, 150)], 3, 6, seed=seed)
    target.alpha_composite(lay)


# ---------- corndog ----------
def corndog(target, t, angle=8, kind='sosis', sauce=True, seed=5):
    """kind: sosis | mozza | mix | kentang"""
    sc = t.sc
    bw, bh = 150, 320
    stretch = kind == 'mozza'
    top_space = 150 if stretch else 0
    sw2, sh2 = S((bw + 80) * sc), S((bh + 200 + top_space) * sc)
    spr = Image.new('RGBA', (sw2, sh2), (0, 0, 0, 0))
    lt = T(sw2 / 2 / K, (top_space + 40) * sc, sc)  # y=0 → atas badan
    # tusuk
    shape(spr, [('rrect', lt.box(-9, bh - 30, 9, bh + 150), lt.l(9))], ('#D9B47E', '#F3DDB5', 'c'))
    cut_y = 46 if kind in ('mozza', 'mix') else 0
    body_box = lt.box(-bw / 2, 0, bw / 2, bh)
    body = new_layer(spr.size)
    shape(body, [('rrect', body_box, lt.l(bw / 2))], ('#B9711E', '#F5C565', 'c'))
    if cut_y:
        # hapus bagian atas yang tergigit
        cl = new_layer(spr.size)
        shape(cl, [('rect', lt.box(-bw, cut_y, bw, bh + 10))], '#FFFFFF')
        body = clip_to(body, cl)
    tex = new_layer(spr.size)
    if kind == 'kentang':
        specks(tex, lt, (-bw / 2, cut_y, bw / 2, bh), 120, ['#FFE28F', '#F0B347', '#D18A2A', '#E9A53A'], 7, 11, seed=seed, shape_kind='rect')
        specks(tex, lt, (-bw / 2, cut_y, bw / 2, bh), 60, ['#A8621B'], 1.5, 3, seed=seed + 1)
    else:
        specks(tex, lt, (-bw / 2, cut_y, bw / 2, bh), 230, ['#FAD58A', '#FFE7B0', '#FFE7B0', '#D18A2E'], 1.5, 3.4, seed=seed)
        specks(tex, lt, (-bw / 2, cut_y, bw / 2, bh), 40, ['#A9631C'], 1.2, 2.2, seed=seed + 2)
    body.alpha_composite(clip_to(tex, body))
    # kilau
    hl = new_layer(spr.size)
    shape(hl, [('rrect', lt.box(-bw / 2 + 16, cut_y + 30, -bw / 2 + 34, bh - 40), lt.l(9))], '#FFFFFF', alpha=70, blur=lt.l(3))
    body.alpha_composite(clip_to(hl, body))
    spr.alpha_composite(body)
    if cut_y:
        face = lt.box(-bw / 2 + 2, cut_y - 20, bw / 2 - 2, cut_y + 20)
        shape(spr, [('ell', face)], '#E7B35C')
        shape(spr, [('ell', lt.box(-bw / 2 + 12, cut_y - 14, bw / 2 - 12, cut_y + 14))], '#F9E4B6')
        if kind == 'mozza':
            shape(spr, [('ell', lt.box(-36, cut_y - 10, 36, cut_y + 10))], '#FFF7DC')
        else:
            shape(spr, [('ell', lt.box(-38, cut_y - 10, 0, cut_y + 10))], '#E88A78')
            shape(spr, [('ell', lt.box(0, cut_y - 10, 38, cut_y + 10))], '#FFF7DC')
    if stretch:
        # potongan yang tergigit di atas + tarikan keju
        pc = new_layer(spr.size)
        shape(pc, [('rrect', lt.box(-bw / 2 + 8, -top_space - 10, bw / 2 - 8, -top_space + 70), lt.l(52))], ('#B9711E', '#F5C565', 'c'))
        specks(pc, lt, (-bw / 2, -top_space - 10, bw / 2, -top_space + 70), 80, ['#FAD58A', '#A9631C'], 1.5, 3.4, seed=seed + 3)
        pcm = new_layer(spr.size)
        shape(pcm, [('rrect', lt.box(-bw / 2 + 8, -top_space - 10, bw / 2 - 8, -top_space + 70), lt.l(52))], '#FFFFFF')
        pc = clip_to(pc, pcm)
        strands = []
        rnd = random.Random(seed)
        for i in range(5):
            x0 = -28 + i * 14
            xm = x0 + rnd.uniform(-10, 10)
            pts = [lt.p(*p) for p in curve((x0, cut_y), (xm, (cut_y - top_space + 60) / 2), (x0 * 0.8, -top_space + 66), 16)]
            strands.append(('line', pts, lt.l(rnd.uniform(7, 12))))
        shape(spr, strands, ('#FFF4C6', '#FFE07A', 'c'))
        spr.alpha_composite(pc)
        shape(spr, [('ell', lt.box(-bw / 2 + 12, -top_space + 54, bw / 2 - 12, -top_space + 80))], '#FFF1C4')
    if sauce:
        zz, zz2 = [], []
        y = cut_y + 34
        i = 0
        while y < bh - 40:
            x = (-bw / 2 + 14) if i % 2 == 0 else (bw / 2 - 14)
            zz.append(lt.p(x, y))
            zz2.append(lt.p(x * 0.9, y + 22))
            y += 34
            i += 1
        sc_layer = new_layer(spr.size)
        shape(sc_layer, [('line', zz, lt.l(11))], '#D9261C')
        shape(sc_layer, [('line', zz2, lt.l(6))], '#F5C518')
        bm = new_layer(spr.size)
        shape(bm, [('rrect', lt.box(-bw / 2 - 4, 0, bw / 2 + 4, bh), lt.l(bw / 2))], '#FFFFFF')
        spr.alpha_composite(clip_to(sc_layer, bm))
    cx, cy = t.p(0, 0)
    oy = round((sh2 / 2) - (top_space + 40 + bh / 2) * K * sc)
    shadow(target, t, 0, bh / 2 + 150, 70, 14, alpha=80)
    sprite_paste(target, spr, cx, cy + oy, angle)


# ---------- ayam popcorn ----------
def nugget(layer, t, x, y, r, seed):
    rnd = random.Random(seed)
    parts = []
    for i in range(4):
        a = rnd.uniform(0, math.tau)
        rr = r * rnd.uniform(0.55, 0.8)
        px, py = x + math.cos(a) * r * 0.35, y + math.sin(a) * r * 0.3
        parts.append(('ell', t.box(px - rr, py - rr * 0.85, px + rr, py + rr * 0.85)))
    one = new_layer()
    shape(one, parts, ('#F7C66A', '#BD7424', 'r'))
    specks(one, t, (x - r, y - r, x + r, y + r), 50, ['#FFE3A0', '#9E5B17', '#D99540'], 1.3, 3.2, seed=seed)
    m = new_layer()
    shape(m, parts, '#FFFFFF')
    one = clip_to(one, m)
    o = new_layer()
    for kind, box in parts:
        ImageDraw.Draw(o).ellipse(box, outline=rgba('#9A5A1A', 70), width=t.l(1.4))
    layer.alpha_composite(one)
    layer.alpha_composite(clip_to(o, m))


def popcorn_cup(target, t):
    shadow(target, t, 0, 226, 140, 22, alpha=95)
    cup_back(target, t, '#8C6236')
    lay = new_layer()
    rnd = random.Random(12)
    spots = []
    for row, (yy, n, spread) in enumerate([(-70, 5, 105), (-28, 6, 120), (8, 6, 125)]):
        for i in range(n):
            x = -spread + i * (spread * 2 / (n - 1)) + rnd.uniform(-10, 10)
            spots.append((x, yy + rnd.uniform(-10, 10), rnd.uniform(30, 40)))
    for k, (x, y, r) in enumerate(spots):
        nugget(lay, t, x, y, r, seed=40 + k)
    target.alpha_composite(lay)
    shape(target, [('line', [t.p(52, -60), t.p(96, -170)], t.l(6))], ('#E9CFA2', '#C8A06A', 'c'))
    cup_front(target, t, c_mid='#D9A86B', c_edge='#A87842', band='#FFF6E8')


# ---------- sosis bakar ----------
def sausage_skewer(target, t):
    shadow(target, t, 0, 120, 250, 26, alpha=80)
    # piring
    shape(target, [('ell', t.box(-270, 60, 270, 175))], ('#FFFFFF', '#E8E2DA', 'v'))
    shape(target, [('ell', t.box(-230, 76, 230, 160))], '#F6F2EC')
    ang = 24
    a = math.radians(ang)
    dx, dy = math.cos(a), -math.sin(a)
    shape(target, [('line', [t.p(-250 * dx, 95 + 250 * math.sin(a)), t.p(250 * dx, 95 - 250 * math.sin(a))], t.l(8))], ('#E6C99A', '#BC9157', 'c'))
    for off in (-95, 95):
        L, Wd = 170, 70
        spr = Image.new('RGBA', (S((L + 30) * t.sc), S((Wd + 30) * t.sc)), (0, 0, 0, 0))
        lt = T(spr.width / 2 / K, spr.height / 2 / K, t.sc)
        bx = lt.box(-L / 2, -Wd / 2, L / 2, Wd / 2)
        shape(spr, [('rrect', bx, lt.l(Wd / 2))], ('#E2643E', '#8E2A16', 'v'))
        marks = new_layer(spr.size)
        for i in range(-3, 4):
            x = i * 24
            shape(marks, [('line', [lt.p(x - 10, -Wd / 2), lt.p(x + 10, Wd / 2)], lt.l(7))], '#4A140A', alpha=170)
        shape(marks, [('rrect', lt.box(-L / 2 + 20, -Wd / 2 + 10, L / 2 - 20, -Wd / 2 + 20), lt.l(5))], '#FFFFFF', alpha=110)
        m = new_layer(spr.size)
        shape(m, [('rrect', bx, lt.l(Wd / 2))], '#FFFFFF')
        spr.alpha_composite(clip_to(marks, m))
        cx, cy = t.p(off * dx, 95 - off * math.sin(a))
        sprite_paste(target, spr, cx, cy, ang)


# ---------- churros ----------
def churros_cup(target, t):
    shadow(target, t, 0, 226, 140, 22, alpha=95)
    cup_back(target, t, '#C9C2B8')
    lay = new_layer()
    for i, (x, ang, ln) in enumerate([(-62, 14, 300), (-20, 5, 330), (24, -4, 320), (66, -13, 295)]):
        spr = stick_sprite(36 * t.sc, ln * t.sc, '#F4C777', '#B86F25', '#7E4413', tip=False, ridges=5)
        cx, cy = t.p(x, -150 + ln / 2 - 60)
        sprite_paste(lay, spr, cx, cy, ang)
    target.alpha_composite(lay)
    d = new_layer()
    specks(d, t, (-130, -220, 130, 60), 220, ['#FFFFFF', '#FFF4E0', '#8B4A1A'], 1.2, 2.6, seed=33)
    target.alpha_composite(clip_to(d, lay))
    cup_front(target, t, c_mid='#FFFFFF', c_edge='#E6E0D8', band='#D9342A', emblem=False, stripes=True)


# ---------- minuman ----------
def glass(target, t, liquid=('#C8641E', '#7E3410'), garnish=None, straw='#E94B3C', ice=4, level=70):
    shadow(target, t, 0, 236, 120, 18, alpha=90)
    # tatakan
    shape(target, [('ell', t.box(-130, 216, 130, 252))], ('#FFFFFF', '#EAE3DA', 'v'))
    top, bot = 112, 90
    y0, y1 = -150, 228
    glass_poly = [t.p(-top, y0), t.p(top, y0), t.p(bot, y1), t.p(-bot, y1)]

    def at(y, side):
        u = (y - y0) / (y1 - y0)
        return side * (top + (bot - top) * u)

    ly = y0 + level
    liq = [t.p(at(ly, -1) + 8, ly), t.p(at(ly, 1) - 8, ly), t.p(at(y1 - 12, 1) - 8, y1 - 12), t.p(at(y1 - 12, -1) + 8, y1 - 12)]
    # sedotan (di belakang cairan atas sedikit)
    shape(target, [('line', [t.p(28, 160), t.p(78, -255)], t.l(18))], '#FFFFFF')
    st = new_layer()
    for i in range(14):
        yy = 150 - i * 30
        xx = 28 + (78 - 28) * (160 - yy) / 415
        shape(st, [('line', [t.p(xx - 9, yy), t.p(xx + 9, yy - 14)], t.l(9))], straw)
    stm = new_layer()
    shape(stm, [('line', [t.p(28, 160), t.p(78, -255)], t.l(18))], '#FFFFFF')
    target.alpha_composite(clip_to(st, stm))
    shape(target, [('poly', liq)], (liquid[0], liquid[1], 'v'), alpha=250)
    shape(target, [('ell', t.box(at(ly, -1) + 8, ly - 10, at(ly, 1) - 8, ly + 10))], mix(liquid[0], '#FFFFFF', 0.25), alpha=235)
    rnd = random.Random(2)
    for i in range(ice):
        x = -60 + i * (120 / max(1, ice - 1)) + rnd.uniform(-8, 8)
        y = ly + 24 + rnd.uniform(0, 40)
        spr = Image.new('RGBA', (S(70 * t.sc), S(70 * t.sc)), (0, 0, 0, 0))
        lt = T(35 * t.sc, 35 * t.sc, t.sc)
        shape(spr, [('rrect', lt.box(-24, -24, 24, 24), lt.l(8))], (255, 255, 255, 120))
        shape(spr, [('rrect', lt.box(-18, -18, -2, -6), lt.l(4))], (255, 255, 255, 170))
        sprite_paste(target, spr, *t.p(x, y), rnd.uniform(-25, 25))
    # kaca
    shape(target, [('poly', glass_poly)], (255, 255, 255, 26))
    o = new_layer()
    ImageDraw.Draw(o).polygon(glass_poly, outline=rgba('#FFFFFF', 190), width=t.l(3))
    target.alpha_composite(o)
    shape(target, [('poly', [t.p(-top + 18, y0 + 20), t.p(-top + 36, y0 + 20), t.p(-bot + 30, y1 - 20), t.p(-bot + 16, y1 - 20)])], '#FFFFFF', alpha=95)
    shape(target, [('ell', t.box(-top, y0 - 10, top, y0 + 10))], (255, 255, 255, 40))
    o2 = new_layer()
    ImageDraw.Draw(o2).ellipse(t.box(-top, y0 - 10, top, y0 + 10), outline=rgba('#FFFFFF', 200), width=t.l(3))
    target.alpha_composite(o2)
    dr = new_layer()
    specks(dr, t, (-100, -100, 100, 210), 26, [(255, 255, 255, 150)], 2, 4.5, seed=6)
    target.alpha_composite(clip_to(dr, _mask_of(glass_poly)))
    if garnish:
        citrus_slice(target, t, -top + 10, y0 + 6, 52, *garnish)


def citrus_slice(target, t, x, y, r, peel, flesh):
    shape(target, [('ell', t.box(x - r, y - r, x + r, y + r))], peel)
    shape(target, [('ell', t.box(x - r + 6, y - r + 6, x + r - 6, y + r - 6))], '#FFF6DA')
    for i in range(8):
        a0 = i * math.tau / 8 + 0.06
        a1 = (i + 1) * math.tau / 8 - 0.06
        rr = r - 11
        pts = [t.p(x, y)] + [t.p(x + rr * math.cos(a0 + (a1 - a0) * k / 6), y + rr * math.sin(a0 + (a1 - a0) * k / 6)) for k in range(7)]
        shape(target, [('poly', pts)], flesh)
    shape(target, [('ell', t.box(x - 4, y - 4, x + 4, y + 4))], '#FFF6DA')


def bottle(target, t):
    shadow(target, t, 0, 236, 90, 16, alpha=90)
    shape(target, [('rrect', t.box(-30, -235, 30, -190), t.l(8))], ('#1E5FC8', '#3D86F0', 'c'))
    for i in range(5):
        shape(target, [('rect', t.box(-30 + i * 13, -232, -26 + i * 13, -193))], '#164AA0', alpha=120)
    neck = [t.p(-34, -188), t.p(34, -188), t.p(40, -150), t.p(-40, -150)]
    shape(target, [('poly', neck)], (190, 225, 250, 170))
    shoulder = [*[t.p(*p) for p in curve((-40, -150), (-82, -130), (-82, -80), 12)], *[t.p(*p) for p in curve((82, -80), (82, -130), (40, -150), 12)]]
    shape(target, [('poly', shoulder)], (195, 228, 252, 190))
    body = t.box(-82, -90, 82, 236)
    shape(target, [('rrect', body, t.l(30))], (('#BFE3FF'), ('#E9F6FF'), 'c'), alpha=215)
    shape(target, [('rrect', t.box(-76, -20, 76, 230), t.l(26))], ('#7CC3F5', '#4C9FE0', 'v'), alpha=120)
    rib = new_layer()
    for y in (-70, 160, 190):
        shape(rib, [('rect', t.box(-82, y, 82, y + 5))], '#FFFFFF', alpha=110)
    target.alpha_composite(rib)
    shape(target, [('rect', t.box(-82, 10, 82, 120))], ('#FFFFFF', '#E3F2FF', 'v'))
    drop = [t.p(*p) for p in curve((0, 30), (-26, 66), (-20, 82), 10)] + [t.p(*p) for p in curve((-20, 82), (0, 104), (20, 82), 10)] + [t.p(*p) for p in curve((20, 82), (26, 66), (0, 30), 10)]
    shape(target, [('poly', drop)], ('#58B0F0', '#1E6FD0', 'v'))
    text(target, t, 0, 112, '600 ml', 13, '#1E5FC8')
    shape(target, [('rrect', t.box(-66, -80, -52, 220), t.l(7))], '#FFFFFF', alpha=110)


def badge(target, t, label, sub=None, color='#E8412F'):
    shape(target, [('poly', _star(t, 0, 0, 74, 62, 16))], color)
    shape(target, [('ell', t.box(-56, -56, 56, 56))], '#FFFFFF', alpha=50)
    text(target, t, 0, -8 if sub else 0, label, 24)
    if sub:
        text(target, t, 0, 20, sub, 14)
