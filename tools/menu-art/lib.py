"""Mini ilustrator makanan (Pillow) untuk gambar contoh menu. @2026-Alifiandra"""
import math
import random
from PIL import Image, ImageDraw, ImageFilter, ImageFont

W, H, K = 800, 600, 3
SW, SH = W * K, H * K
FONT_BOLD = '/usr/share/fonts/truetype/dejavu/DejaVuSans-Bold.ttf'


def rgba(h, a=255):
    h = h.lstrip('#')
    if len(h) == 8:
        a = int(h[6:8], 16)
    return (int(h[0:2], 16), int(h[2:4], 16), int(h[4:6], 16), a)


def mix(c1, c2, t):
    a, b = rgba(c1) if isinstance(c1, str) else c1, rgba(c2) if isinstance(c2, str) else c2
    return tuple(int(a[i] + (b[i] - a[i]) * t) for i in range(4))


def S(v):
    return int(round(v * K))


# ---------- gradien ----------
def grad(w, h, c1, c2, mode='v'):
    """mode: v (atas→bawah), h (kiri→kanan), c (tepi→tengah horizontal), r (radial tengah→tepi)."""
    w, h = max(1, w), max(1, h)
    if mode == 'r':
        g = Image.radial_gradient('L').resize((w, h))
    else:
        g = Image.linear_gradient('L')
        if mode in ('h', 'c'):
            g = g.rotate(90, expand=True).transpose(Image.FLIP_LEFT_RIGHT)
        if mode == 'c':
            g = g.point(lambda v: 255 - abs(2 * v - 255))
        g = g.resize((w, h))
    a = Image.new('RGBA', (w, h), rgba(c1) if isinstance(c1, str) else c1)
    b = Image.new('RGBA', (w, h), rgba(c2) if isinstance(c2, str) else c2)
    return Image.composite(b, a, g)


class T:
    """Transformasi koordinat dasar (800x600) → kanvas besar, dengan pusat & skala."""

    def __init__(self, cx=0, cy=0, sc=1.0, k=K):
        self.cx, self.cy, self.sc, self.k = cx, cy, sc, k

    def p(self, x, y):
        return (round((self.cx + x * self.sc) * self.k), round((self.cy + y * self.sc) * self.k))

    def l(self, v):
        return max(1, round(v * self.sc * self.k))

    def box(self, x0, y0, x1, y1):
        a, b = self.p(x0, y0), self.p(x1, y1)
        return [min(a[0], b[0]), min(a[1], b[1]), max(a[0], b[0]), max(a[1], b[1])]


def comp(target, layer, x, y):
    """alpha_composite aman untuk posisi negatif / keluar kanvas."""
    sx, sy = max(0, -x), max(0, -y)
    x, y = max(0, x), max(0, y)
    if sx >= layer.width or sy >= layer.height or x >= target.width or y >= target.height:
        return
    target.alpha_composite(layer, (x, y), (sx, sy))


def _bbox(prims):
    xs, ys = [], []
    for kind, data, *extra in prims:
        pad = extra[0] if (kind == 'line' and extra) else 0
        if kind in ('ell', 'rrect', 'rect'):
            xs += [data[0], data[2]]
            ys += [data[1], data[3]]
        else:
            xs += [p[0] - pad for p in data] + [p[0] + pad for p in data]
            ys += [p[1] - pad for p in data] + [p[1] + pad for p in data]
    return int(min(xs)) - 4, int(min(ys)) - 4, int(max(xs)) + 5, int(max(ys)) + 5


def shape(target, prims, paint, alpha=255, blur=0, cut=None):
    """Gambar gabungan primitif (koordinat kanvas besar) dengan cat solid/gradien.
    prims: ('poly', pts) | ('ell', box) | ('rrect', box, r) | ('rect', box) | ('line', pts, width)
    paint: warna hex / tuple, atau (c1, c2, mode) untuk gradien. cut: prims yang dihapus dari bentuk."""
    x0, y0, x1, y1 = _bbox(prims)
    w, h = x1 - x0, y1 - y0
    mask = Image.new('L', (w, h), 0)
    d = ImageDraw.Draw(mask)

    def draw(ps, val):
        for kind, data, *extra in ps:
            if kind == 'poly':
                d.polygon([(px - x0, py - y0) for px, py in data], fill=val)
            elif kind == 'ell':
                d.ellipse([data[0] - x0, data[1] - y0, data[2] - x0, data[3] - y0], fill=val)
            elif kind == 'rect':
                d.rectangle([data[0] - x0, data[1] - y0, data[2] - x0, data[3] - y0], fill=val)
            elif kind == 'rrect':
                d.rounded_rectangle([data[0] - x0, data[1] - y0, data[2] - x0, data[3] - y0], radius=extra[0], fill=val)
            elif kind == 'line':
                pts = [(px - x0, py - y0) for px, py in data]
                d.line(pts, fill=val, width=extra[0], joint='curve')
                r = extra[0] / 2
                for px, py in (pts[0], pts[-1]):
                    d.ellipse([px - r, py - r, px + r, py + r], fill=val)

    draw(prims, alpha)
    if cut:
        draw(cut, 0)
    if blur:
        mask = mask.filter(ImageFilter.GaussianBlur(blur))
    if isinstance(paint, tuple) and len(paint) == 3 and isinstance(paint[2], str) and len(paint[2]) == 1:
        img = grad(w, h, paint[0], paint[1], paint[2])
    else:
        img = Image.new('RGBA', (w, h), rgba(paint) if isinstance(paint, str) else paint)
    layer = Image.new('RGBA', (w, h), (0, 0, 0, 0))
    layer.paste(img, (0, 0), mask)
    comp(target, layer, x0, y0)


def new_layer(size=(SW, SH)):
    return Image.new('RGBA', size, (0, 0, 0, 0))


def clip_to(layer, clip):
    """Batasi alpha layer pada alpha clip (keduanya ukuran sama)."""
    from PIL import ImageChops
    r, g, b, al = layer.split()
    al = ImageChops.multiply(al, clip.getchannel('A'))
    return Image.merge('RGBA', (r, g, b, al))


def shadow(target, t, x, y, rx, ry, alpha=80, blur=14):
    w, h = t.l(rx * 2) + t.l(blur * 6), t.l(ry * 2) + t.l(blur * 6)
    m = Image.new('L', (w, h), 0)
    ImageDraw.Draw(m).ellipse([t.l(blur * 3), t.l(blur * 3), w - t.l(blur * 3), h - t.l(blur * 3)], fill=alpha)
    m = m.filter(ImageFilter.GaussianBlur(t.l(blur)))
    lay = Image.new('RGBA', (w, h), (40, 20, 10, 0))
    lay.putalpha(m)
    cx, cy = t.p(x, y)
    comp(target, lay, cx - w // 2, cy - h // 2)


def sprite_paste(target, spr, cx, cy, angle=0):
    if angle:
        spr = spr.rotate(angle, resample=Image.BICUBIC, expand=True)
    comp(target, spr, cx - spr.width // 2, cy - spr.height // 2)


def specks(layer, t, region, n, colors, rmin, rmax, seed=1, shape_kind='ell'):
    rnd = random.Random(seed)
    d = ImageDraw.Draw(layer)
    x0, y0, x1, y1 = region
    for _ in range(n):
        x, y = rnd.uniform(x0, x1), rnd.uniform(y0, y1)
        r = rnd.uniform(rmin, rmax)
        c = rgba(rnd.choice(colors)) if isinstance(colors[0], str) else rnd.choice(colors)
        a, b = t.p(x - r, y - r), t.p(x + r, y + r)
        if shape_kind == 'rect':
            d.rounded_rectangle([a, b], radius=t.l(r * 0.35), fill=c)
        else:
            d.ellipse([a, b], fill=c)


def text(target, t, x, y, s, size, color='#FFFFFF', anchor='mm'):
    f = ImageFont.truetype(FONT_BOLD, t.l(size))
    ImageDraw.Draw(target).text(t.p(x, y), s, font=f, fill=rgba(color), anchor=anchor)


# ---------- kanvas ----------
def canvas(top, bottom, table, table_edge, deco):
    im = grad(SW, SH, top, bottom, 'v')
    t = T()
    rnd = random.Random(7)
    d = new_layer()
    dd = ImageDraw.Draw(d)
    # ornamen lingkaran lembut di latar
    for _ in range(9):
        x, y, r = rnd.uniform(20, 780), rnd.uniform(10, 380), rnd.uniform(18, 70)
        dd.ellipse([t.p(x - r, y - r), t.p(x + r, y + r)], fill=rgba(deco, 34))
    for _ in range(26):
        x, y, r = rnd.uniform(10, 790), rnd.uniform(10, 400), rnd.uniform(3, 7)
        dd.ellipse([t.p(x - r, y - r), t.p(x + r, y + r)], fill=rgba(deco, 70))
    im.alpha_composite(d)
    # meja
    shape(im, [('rect', t.box(0, 430, 800, 600))], (table, table_edge, 'v'))
    shape(im, [('rect', t.box(0, 428, 800, 436))], '#FFFFFF', alpha=70)
    return im


def save(im, path):
    out = im.resize((W, H), Image.LANCZOS).convert('RGB')
    out.save(path, 'WEBP', quality=86, method=6)
