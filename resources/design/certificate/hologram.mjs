// Holographic seal generator (optional on certificates).
//
//   node hologram.mjs <out.png>
//
// Transparent 600x600 PNG; public/images/certificate-hologram.png is this
// scaled to 420 px. Needs playwright-core and a local Chrome (CHROME_PATH).
import { chromium } from "playwright-core";
import fs from "node:fs";
import path from "node:path";
import { fileURLToPath } from "node:url";
const LOGO = fs.readFileSync(path.join(path.dirname(fileURLToPath(import.meta.url)), "../../../public/images/jcd-only-color.png")).toString("base64");
const OUT = process.argv[2] || "hologram.png";
const N = 600, c = 300;
let engr = "";
for (let k = 0; k < 360; k++) {
  const a = (k / 360) * Math.PI * 2;
  engr += '<line x1="' + (c + 158 * Math.cos(a)).toFixed(1) + '" y1="' + (c + 158 * Math.sin(a)).toFixed(1) + '" x2="' + (c + 236 * Math.cos(a)).toFixed(1) + '" y2="' + (c + 236 * Math.sin(a)).toFixed(1) + '" stroke="' + (k % 2 ? "#ffffff" : "#8d96a6") + '" stroke-width="0.7" opacity="' + (k % 2 ? 0.5 : 0.28) + '"/>';
}
let guil = "";
for (let k = 0; k < 14; k++) {
  let d = "";
  for (let i = 0; i <= 1800; i++) {
    const t = (i / 1800) * Math.PI * 2, r = 198 + 16 * Math.sin(18 * t + (k * Math.PI) / 7);
    d += (i ? "L" : "M") + (c + r * Math.cos(t)).toFixed(1) + "," + (c + r * Math.sin(t)).toFixed(1);
  }
  guil += '<path d="' + d + 'Z" fill="none" stroke="#ffffff" stroke-width="0.9" opacity=".6"/>';
}
const micro = "JENDELA CAKRA DIGITAL  ·  JCD  ·  ".repeat(3);
const html = `<html><body style="margin:0;background:transparent">
<div style="position:relative;width:${N}px;height:${N}px;border-radius:50%;overflow:hidden;
  background:radial-gradient(circle at 35% 28%,#ffffff 0%,#eef1f5 22%,#c9cfd8 55%,#e9ecf1 72%,#aeb6c3 100%);">
  <div style="position:absolute;inset:0;mix-blend-mode:color;opacity:.55;
    background:conic-gradient(from 210deg,#9fd8ff,#d2b8ff,#ffb8dc,#ffe7a3,#b8f5d1,#9fd8ff,#d2b8ff,#ffb8dc,#ffe7a3,#b8f5d1,#9fd8ff)"></div>
  <div style="position:absolute;inset:0;background:linear-gradient(118deg,rgba(255,255,255,0) 28%,rgba(255,255,255,.7) 42%,rgba(255,255,255,0) 54%)"></div>
  <svg width="${N}" height="${N}" viewBox="0 0 600 600" style="position:absolute;inset:0">
    <defs><path id="ring" d="M300,300 m-262,0 a262,262 0 1,1 524,0 a262,262 0 1,1 -524,0"/>
      <filter id="emb"><feColorMatrix type="saturate" values="0"/><feComponentTransfer><feFuncR type="linear" slope=".55" intercept=".18"/><feFuncG type="linear" slope=".55" intercept=".2"/><feFuncB type="linear" slope=".55" intercept=".24"/></feComponentTransfer></filter></defs>
    ${engr}${guil}
    <circle cx="300" cy="300" r="296" fill="none" stroke="#7f8899" stroke-width="3" opacity=".7"/>
    <circle cx="300" cy="300" r="288" fill="none" stroke="#ffffff" stroke-width="3" opacity=".9"/>
    <circle cx="300" cy="300" r="244" fill="none" stroke="#7f8899" stroke-width="1.5" opacity=".6"/>
    <circle cx="300" cy="300" r="240" fill="none" stroke="#ffffff" stroke-width="1.5" opacity=".9"/>
    <text font-family="Helvetica,Arial" font-size="17" font-weight="700" letter-spacing="2.2" fill="#5d6677" opacity=".85"><textPath href="#ring" textLength="1640" lengthAdjust="spacing">${micro}</textPath></text>
    <circle cx="300" cy="300" r="152" fill="rgba(255,255,255,.45)"/>
    <circle cx="300" cy="300" r="152" fill="none" stroke="#ffffff" stroke-width="2.5"/>
    <circle cx="300" cy="300" r="156" fill="none" stroke="#8d96a6" stroke-width="1.2" opacity=".7"/>
    <image href="data:image/png;base64,${LOGO}" x="190" y="190" width="220" height="220" filter="url(#emb)" opacity=".18" transform="translate(3,3)"/>
    <image href="data:image/png;base64,${LOGO}" x="190" y="190" width="220" height="220" filter="url(#emb)"/>
  </svg>
</div></body></html>`;
const b = await chromium.launch({ executablePath: process.env.CHROME_PATH || "/Applications/Google Chrome.app/Contents/MacOS/Google Chrome" });
const p = await b.newPage({ viewport: { width: N, height: N } });
await p.setContent(html);
await p.screenshot({ path: OUT, omitBackground: true, clip: { x: 0, y: 0, width: N, height: N } });
await b.close();
