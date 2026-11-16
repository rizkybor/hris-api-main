// Certificate background generator (the built-in "Sertifikat" design).
//
//   node background.mjs <outDir> [scale] [--png]
//
// scale 1 = 150 dpi A4 (what the PDF uses, public/images/certificate-default-bg*.jpg),
// scale 2 = 300 dpi for print. --png writes lossless PNGs instead of JPEGs.
// Needs playwright-core and a local Chrome (CHROME_PATH to override).
import { chromium } from "playwright-core";
const OUT = process.argv[2] || ".";
const SCALE = Number(process.argv[3]) || 1;
const PNG = process.argv.includes("--png");
const NAVY = "#0c1c3c", INK = "#1d2b4f", BLUE = "#0c51d9", SKY = "#5b93f5", GOLD = "#c4a052";

// One tile of the Bauhaus column. c = cell size, x/y = cell origin.
const tile = (kind, x, y, c, fill) => {
  const t = `transform="translate(${x},${y})"`;
  const shapes = {
    qTL: `M0,0 H${c} A${c},${c} 0 0 1 0,${c} Z`,
    qTR: `M${c},0 V${c} A${c},${c} 0 0 1 0,0 Z`,
    qBL: `M0,${c} V0 A${c},${c} 0 0 1 ${c},${c} Z`,
    qBR: `M${c},${c} H0 A${c},${c} 0 0 1 ${c},0 Z`,
    leafA: `M0,${c} A${c},${c} 0 0 1 ${c},0 A${c},${c} 0 0 1 0,${c} Z`,
    leafB: `M0,0 A${c},${c} 0 0 1 ${c},${c} A${c},${c} 0 0 1 0,0 Z`,
    halfL: `M0,0 A${c / 2},${c / 2} 0 0 1 0,${c} Z`,
    halfR: `M${c},0 A${c / 2},${c / 2} 0 0 0 ${c},${c} Z`,
    halfT: `M0,0 A${c / 2},${c / 2} 0 0 0 ${c},0 Z`,
    halfB: `M0,${c} A${c / 2},${c / 2} 0 0 1 ${c},${c} Z`,
    square: `M0,0 H${c} V${c} H0 Z`,
  };
  const engrave = (() => {
    const q = { qTL: [0, 0], qTR: [c, 0], qBL: [0, c], qBR: [c, c] }[kind];
    if (q) return [0.78, 0.56, 0.34].map((f) => `<circle cx="${x + q[0]}" cy="${y + q[1]}" r="${c * f}" fill="none" stroke="#ffffff" stroke-width="1.1" opacity="${fill === GOLD ? 0.35 : 0.16}"/>`).join("");
    if (kind === "leafA") return `<line x1="${x + c * 0.12}" y1="${y + c * 0.88}" x2="${x + c * 0.88}" y2="${y + c * 0.12}" stroke="#ffffff" stroke-width="1.3" opacity=".22"/>`;
    if (kind === "leafB") return `<line x1="${x + c * 0.12}" y1="${y + c * 0.12}" x2="${x + c * 0.88}" y2="${y + c * 0.88}" stroke="#ffffff" stroke-width="1.3" opacity=".22"/>`;
    if (kind === "square") return `<rect x="${x + c * 0.14}" y="${y + c * 0.14}" width="${c * 0.72}" height="${c * 0.72}" fill="none" stroke="${GOLD}" stroke-width="1.4" opacity=".7"/><rect x="${x + c * 0.22}" y="${y + c * 0.22}" width="${c * 0.56}" height="${c * 0.56}" fill="none" stroke="#ffffff" stroke-width="0.9" opacity=".16"/>`;
    return "";
  })();
  const clip = `clip-path="url(#cl-${Math.round(x)}-${Math.round(y)})"`;
  if (kind !== "dot" && kind !== "ring") {
    const d = shapes[kind];
    return `<clipPath id="cl-${Math.round(x)}-${Math.round(y)}"><path ${t} d="${d}"/></clipPath>
      <path ${t} d="${d}" fill="${fill}"/><g ${clip}>${engrave}</g>
      <path ${t} d="${d}" fill="none" stroke="url(#gold)" stroke-width="1.6" opacity=".75"/>`;
  }
  if (kind === "dot") return `<circle cx="${x + c / 2}" cy="${y + c / 2}" r="${c / 2}" fill="${fill}"/>`;
  if (kind === "ring") return `<circle cx="${x + c / 2}" cy="${y + c / 2}" r="${c * 0.36}" fill="none" stroke="${fill}" stroke-width="${c * 0.2}"/>`;
  return `<path ${t} d="${shapes[kind]}" fill="${fill}"/>`;
};

// 2x2 motifs stacked down the column (rows of [left, right] cells), so
// neighbouring tiles join into larger forms: a circle, crossed leaves, a
// four-petal flower, an arch over blocks.
const MOTIFS = {
  circle: (a, b) => [[["qBR", a], ["qBL", b]], [["qTR", b], ["qTL", a]]],
  leaves: (a, b) => [[["leafA", a], ["leafB", b]], [["leafB", b], ["leafA", a]]],
  petals: (a, b, accent) => [[["qTL", a], ["qTR", b]], [["qBL", b], ["qBR", accent]]],
  arch: (a, b) => [[["qBR", a], ["qBL", a]], [["square", b], ["square", a]]],
};
const SEQUENCE = [
  MOTIFS.circle(NAVY, BLUE),
  MOTIFS.leaves(SKY, NAVY),
  MOTIFS.petals(BLUE, NAVY, GOLD),
  MOTIFS.arch(NAVY, BLUE),
  MOTIFS.circle(BLUE, INK),
  MOTIFS.leaves(NAVY, BLUE),
  MOTIFS.petals(NAVY, SKY, BLUE),
  MOTIFS.circle(INK, SKY),
  MOTIFS.petals(BLUE, NAVY, GOLD),
];
const PATTERN = SEQUENCE.flat().flat();

// Classic security-paper background: bands of interlaced sine lines.
const waves = (W, H) => {
  let out = "";
  const band = 46;
  for (let y0 = -band; y0 < H + band; y0 += band) {
    for (let k = 0; k < 3; k++) {
      let d = "";
      for (let x = 0; x <= W; x += 6) {
        const y = y0 + (band / 2) * Math.sin((x / 140) * Math.PI + k * 2.1 + y0 * 0.013);
        d += `${x ? "L" : "M"}${x},${y.toFixed(1)}`;
      }
      out += `<path d="${d}" fill="none" stroke="#0c1c3c" stroke-width="0.8" opacity=".055"/>`;
    }
  }
  return out;
};
// Small gold diamonds where one 2x2 motif meets the next.
const diamonds = (cx, y0, cell, rows) => {
  let out = "";
  for (let r = 2; r < rows; r += 2) {
    const y = y0 + r * cell, s = cell * 0.13;
    out += `<path d="M${cx},${y - s} L${cx + s},${y} L${cx},${y + s} L${cx - s},${y} Z" fill="url(#gold)" stroke="#f7f6f2" stroke-width="2"/>`;
  }
  return out;
};

const svg = (W, H, mm, x0, y0, cell, rows) => {
  let cells = "";
  for (let r = 0; r < rows; r++) for (let col = 0; col < 2; col++) {
    const [kind, fill] = PATTERN[(r * 2 + col) % PATTERN.length];
    cells += tile(kind, x0 + col * cell, y0 + r * cell, cell, fill);
  }
  return `<svg xmlns="http://www.w3.org/2000/svg" width="${W}" height="${H}">
    <defs><filter id="g"><feTurbulence type="fractalNoise" baseFrequency="0.9" numOctaves="2" seed="5"/><feColorMatrix values="0 0 0 0 0.3 0 0 0 0 0.32 0 0 0 0 0.38 0 0 0 0.035 0"/></filter>
    <linearGradient id="gold" x1="0" x2="1"><stop offset="0" stop-color="#a8832f"/><stop offset=".5" stop-color="#ead08a"/><stop offset="1" stop-color="#a8832f"/></linearGradient></defs>
    <rect width="${W}" height="${H}" fill="#f7f6f2"/>
    ${waves(W, H)}
    <rect width="${W}" height="${H}" filter="url(#g)"/>
    <rect x="${4 * mm}" y="${4 * mm}" width="${W - 8 * mm}" height="${H - 8 * mm}" fill="none" stroke="url(#gold)" stroke-width="${0.6 * mm}"/>
    <rect x="${5.3 * mm}" y="${5.3 * mm}" width="${W - 10.6 * mm}" height="${H - 10.6 * mm}" fill="none" stroke="${GOLD}" stroke-width="${0.15 * mm}" opacity=".9"/>
    ${cells}
    ${diamonds(x0 + cell, y0, cell, rows)}
  </svg>`;
};

const b = await chromium.launch({ executablePath: process.env.CHROME_PATH || "/Applications/Google Chrome.app/Contents/MacOS/Google Chrome" });
const mm = 1754 / 297;
for (const [o, W, H, x0, y0, cell, rows] of [
  ["landscape", 1754, 1240, 14 * mm, 25 * mm, 20 * mm, 8],
  ["portrait", 1240, 1754, 13 * mm, 23.5 * mm, 12.5 * mm, 20],
]) {
  const p = await b.newPage({ viewport: { width: W, height: H }, deviceScaleFactor: SCALE });
  await p.setContent(`<html><body style="margin:0">${svg(W, H, mm, x0, y0, cell, rows)}</body></html>`);
  await p.screenshot(PNG ? { path: `${OUT}/background-${o}.png`, type: "png" } : { path: `${OUT}/background-${o}.jpg`, type: "jpeg", quality: 86 });
  await p.close();
}
await b.close();
