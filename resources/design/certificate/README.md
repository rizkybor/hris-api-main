# Certificate design

Source files for the built-in certificate design (Documents → Certificates).

| File | What it is |
| --- | --- |
| `background-landscape.jpg` | Plain A4 landscape background, 300 dpi (3508 × 2480), no text |
| `background-portrait.jpg` | Plain A4 portrait background, 300 dpi (2480 × 3508), no text |
| `hologram.png` | Holographic seal, transparent, 600 × 600 |
| `background.mjs` | Generator for both backgrounds |
| `hologram.mjs` | Generator for the seal |

The PDF uses the web-sized copies in `public/images/`:
`certificate-default-bg.jpg`, `certificate-default-bg-portrait.jpg` (150 dpi)
and `certificate-hologram.png` (420 px). The text layout that goes on top is in
`resources/views/pdf/certificate.blade.php` (the `modernLayout` branch).

## Regenerating

Both scripts need `playwright-core` and a local Chrome (`CHROME_PATH` to point
elsewhere).

```sh
# 150 dpi JPEGs for the PDF -> copy to public/images/certificate-default-bg(-portrait).jpg
node background.mjs /tmp/cert 1
# 300 dpi files for print / editing (add --png for lossless)
node background.mjs . 2

node hologram.mjs hologram.png
# then: sips -Z 420 hologram.png --out ../../../public/images/certificate-hologram.png
```

Brand colours: navy `#0c1c3c`, blue `#0c51d9`, sky `#5b93f5`, gold `#c4a052`.
