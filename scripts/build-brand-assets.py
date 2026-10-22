"""Regenerate the published brand assets from the masters in `_brand-source/`.

Usage:  python scripts/build-brand-assets.py

Outputs:
  assets/logos/logo-ynsumedic.png  full lockup (mark + wordmark), transparent, 658px tall
  assets/img/og-cover.jpg          social share cover, 1200x630
"""

from pathlib import Path

import numpy as np
from PIL import Image, ImageDraw, ImageFilter

ROOT = Path(__file__).resolve().parent.parent
MASTER_LOGO = ROOT / "_brand-source/logo-master-6000.png"

NAVY_TOP = (10, 20, 40)
NAVY_BOTTOM = (18, 41, 74)
GREEN = (59, 174, 34)


def alpha_bbox(img: Image.Image) -> tuple[int, int, int, int]:
    a = np.array(img)[:, :, 3]
    ys, xs = np.where(a > 10)
    return xs.min(), ys.min(), xs.max() + 1, ys.max() + 1


def band_bboxes(img: Image.Image, min_gap: int = 40):
    """Split the artwork into horizontal bands (mark and wordmark)."""
    a = np.array(img)[:, :, 3]
    ink_rows = np.where((a > 10).any(axis=1))[0]
    bands, start, prev = [], ink_rows[0], ink_rows[0]
    for row in ink_rows[1:]:
        if row - prev > min_gap:
            bands.append((start, prev + 1))
            start = row
        prev = row
    bands.append((start, prev + 1))

    out = []
    for y0, y1 in bands:
        cols = np.where((a[y0:y1] > 10).any(axis=0))[0]
        out.append((cols.min(), y0, cols.max() + 1, y1))
    return out


def to_white(img: Image.Image) -> Image.Image:
    """Keep the alpha shape, force RGB to pure white."""
    arr = np.array(img)
    arr[:, :, :3] = 255
    return Image.fromarray(arr, "RGBA")


def fit_width(img: Image.Image, width: int) -> Image.Image:
    height = round(img.height * width / img.width)
    return img.resize((width, height), Image.LANCZOS)


def build_logo(master: Image.Image, target_height: int = 658) -> Image.Image:
    logo = master.crop(alpha_bbox(master))
    pad = round(max(logo.size) * 0.04)
    canvas = Image.new("RGBA", (logo.width + 2 * pad, logo.height + 2 * pad), (0, 0, 0, 0))
    canvas.paste(logo, (pad, pad), logo)
    return canvas.resize(
        (round(canvas.width * target_height / canvas.height), target_height), Image.LANCZOS
    )


def build_cover(mark: Image.Image, wordmark: Image.Image) -> Image.Image:
    width, height = 1200, 630

    # Diagonal navy gradient.
    t = np.minimum(
        1.0,
        (np.arange(width)[None, :] / width) * 0.35 + (np.arange(height)[:, None] / height) * 0.65,
    )
    gradient = np.stack(
        [NAVY_TOP[i] + (NAVY_BOTTOM[i] - NAVY_TOP[i]) * t for i in range(3)], axis=-1
    ).round().astype(np.uint8)
    canvas = Image.fromarray(gradient, "RGB").convert("RGBA")

    # Soft green glow behind the mark.
    glow = Image.new("RGBA", (width, height), (0, 0, 0, 0))
    ImageDraw.Draw(glow).ellipse(
        (width / 2 - 260, 40, width / 2 + 260, 420), fill=GREEN + (70,)
    )
    canvas = Image.alpha_composite(canvas, glow.filter(ImageFilter.GaussianBlur(90)))

    # White lockup: mark on top, wordmark below.
    m = fit_width(mark, 250)
    w = fit_width(wordmark, 640)
    y = (height - (m.height + 42 + w.height)) // 2
    canvas.alpha_composite(m, ((width - m.width) // 2, y))
    canvas.alpha_composite(w, ((width - w.width) // 2, y + m.height + 42))

    # Thin green accent line at the bottom.
    bar = Image.new("RGBA", (width, height), (0, 0, 0, 0))
    ImageDraw.Draw(bar).rectangle((0, height - 10, width, height), fill=GREEN + (255,))
    canvas.alpha_composite(bar)

    return canvas.convert("RGB")


def main() -> None:
    master = Image.open(MASTER_LOGO).convert("RGBA")
    bands = band_bboxes(master)
    if len(bands) < 2:
        raise SystemExit(f"Expected mark + wordmark bands, got {len(bands)}")

    logo = build_logo(master)
    logo_path = ROOT / "assets/logos/logo-ynsumedic.png"
    logo.save(logo_path, optimize=True)
    print(f"logo-ynsumedic.png {logo.size} {logo_path.stat().st_size // 1024}KB")

    cover = build_cover(to_white(master.crop(bands[0])), to_white(master.crop(bands[-1])))
    cover_path = ROOT / "assets/img/og-cover.jpg"
    cover_path.parent.mkdir(parents=True, exist_ok=True)
    cover.save(cover_path, "JPEG", quality=90, optimize=True, progressive=True)
    print(f"og-cover.jpg {cover.size} {cover_path.stat().st_size // 1024}KB")


if __name__ == "__main__":
    main()
