# Third-party notices

Packio's first-party source and the Packio branding assets are covered by the
repository's [MIT License](LICENSE). The branding assets listed below were
present at repository commit
[`f34e07925fced84f7111061a033207ef86898399`](https://github.com/packio-php/packio/commit/f34e07925fced84f7111061a033207ef86898399)
and their bytes have not changed since that revision. No external source is
recorded for them; the repository declares them under its MIT license, while
their separate creation or rasterization process is not recorded.

| Branding asset | SHA-256 |
| --- | --- |
| `public/favicon.svg` | `3bcabc7f4895cc39cf4acecda8d69003624fa1ca73c235cf826004240ffcec0e` |
| `public/favicon.ico` | `7bafd9d7de69ce5bd969cfe53ddbec83554aa8d767b4e377507f2c01d6983b49` |
| `public/favicon-16x16.png` | `cfcfddfb6cc317a79fa6e99e40496a700e1ff06375f876ee5fdabd7be7fbb5f7` |
| `public/favicon-32x32.png` | `7bafd9d7de69ce5bd969cfe53ddbec83554aa8d767b4e377507f2c01d6983b49` |
| `public/apple-touch-icon.png` | `295034017fa87a9b51593602f8ae158cb56a38adbe9913052c0ed2be2557fccb` |
| `public/android-chrome-192x192.png` | `bd68359d521e769b0283b42375e0baa3a97f6e175b87257d0e77e5020b8dab01` |
| `public/android-chrome-512x512.png` | `0f237a77af25c811b9bb0b74766c7f9b63fe31a7d85c0fc531f0985409ccc942` |
| `public/og-image.png` | `1a60c2ffb49c21d7e4c2a6aaa1bd2050d200944e43c6ca524aa38535ab9c3f1b` |

## Fonts

The application includes these unmodified WOFF2 font subsets. Their separate
SIL Open Font License 1.1 texts are included beside the files under
`public/fonts/`.

| Font | Source and version | Files |
| --- | --- | --- |
| JetBrains Mono | [`@fontsource-variable/jetbrains-mono` 5.3.0](https://github.com/fontsource/font-files/tree/02b28c06b126c852a5b966b44aef777ca923f6f6/fonts/variable/jetbrains-mono), Google Fonts asset revision v24 | `public/fonts/jetbrains-mono-{cyrillic,latin-ext,latin}.woff2` |
| Plus Jakarta Sans | [`@fontsource-variable/plus-jakarta-sans` 5.3.0](https://github.com/fontsource/font-files/tree/02b28c06b126c852a5b966b44aef777ca923f6f6/fonts/variable/plus-jakarta-sans), Google Fonts asset revision v12 | `public/fonts/plus-jakarta-sans-{cyrillic-ext,latin-ext,latin}.woff2` |

JetBrains Mono files were copied from the pinned package without changing font
data and kept their existing project filenames. The Plus Jakarta Sans files
match the pinned package byte for byte. The package tarball integrity values
from `package-lock.json` are `sha512-F32xpS2NsGYoQi2ADSkKTgpJj7ozajsGgDJ8woTnqjmIB+dxDIqImjl4pXZVEExu8UFZ2ndhmX18EBS/hdz3Lw==`
for JetBrains Mono and
`sha512-/l/4r0yyWK9JzAlmA0LiYgGgmJe/Gswt4jTJEzr5QhJfwMbvJZDmYWyW0M4X7yCK69BajMVlV/Lx8g7WDc1+sw==`
for Plus Jakarta Sans. The six installed subset hashes are:

| File | SHA-256 |
| --- | --- |
| `jetbrains-mono-cyrillic.woff2` | `d274604c40757f98f07df623a8b8fb5194508a6fa4f5413a7a107f09e3b7452a` |
| `jetbrains-mono-latin-ext.woff2` | `79bfdab9ba467e26eea4122e6f2567e188dd8a09a8c730d501fc487c4ab99c6e` |
| `jetbrains-mono-latin.woff2` | `18be452724bfdc236c074ca94a249a7f41a86752c7d04ab258ce9ed5651f6a7e` |
| `plus-jakarta-sans-cyrillic-ext.woff2` | `c46a510ab43925a55ecfe6c2d5fad0ce1902cd48ab276621d41f7afa42e4daee` |
| `plus-jakarta-sans-latin-ext.woff2` | `38e3b8fd8045048eb311d90170a4429ed2c8f405852dc3d91b5af8452758703f` |
| `plus-jakarta-sans-latin.woff2` | `153fc85b70298beeb1d61a5f723331649e7f23bb77302a66e61cb3e2fbdb5e79` |

## Swagger UI

The self-hosted API documentation uses the unmodified distribution assets
from [`swagger-ui-dist` 5.32.8](https://github.com/swagger-api/swagger-ui/tree/v5.32.8).
The Apache License 2.0, upstream `NOTICE`, and the bundle-specific third-party
license notices are copied from the installed package into
`public/api-doc/assets/` by `npm run openapi-assets`.
The [Swagger dependency supplement](public/api-doc/assets/THIRD_PARTY_NOTICES.md)
records the source-bound emitted dependency inventory, generated runtime and
embedded-code attributions, and their complete license terms for the exact
distributed JavaScript hashes.

## Browser and SSR bundles

Client and SSR source maps from successful standard Vite CLI builds identify
the package modules included in each output. Both builds used a fresh `npm ci`
from an exact copy of the locked frontend source, without dependency or tool
version changes. The browser output includes the first eight packages below;
the SSR output includes those packages plus `devalue`. Their license texts are
reproduced below. Package integrity values come from `package-lock.json`.
Build tools that do not contribute code to the shipped browser/SSR output are
not listed here.

| Package | Version | License | Package integrity (SRI) |
| --- | --- | --- | --- |
| `@inertiajs/core` | 3.6.1 | MIT | `sha512-h6+qqkKfpcoZvxWENy/F5yyiD00PIm8lK+ruQkt6HGk4d3N0LMS9GeUbWVQ4+TDZzIxP/vQLurPktnYvDhYeew==` |
| `@inertiajs/svelte` | 3.6.1 | MIT | `sha512-k1JLpyZhPIZlYs0vk86mcQ0WKJMTsObI2WA4gRx0dUmDJK+p2nOOG5cnjyrrzDuK6bdq3POVOg/kAfgUHpWPig==` |
| `clsx` | 2.1.1 | MIT | `sha512-eYm0QWBtUrBWZWG0d386OGAw16Z995PiOVo2B7bjWSbHedGl5e0ZWaq65kOGgUSNesEIDkB9ISbTg/JK9dhCZA==` |
| `es-toolkit` | 1.50.0 | MIT; includes Lodash-derived Unicode helper | `sha512-OyZKhUVvEep9ITEiwHn8GKnMRQIVqoSIX7WnRbkWgJkllCujilqP2rD0u979tkl8wqyc8ICwlc1UBVv/Sl1G6w==` |
| `laravel-precognition` | 2.0.0 | MIT | `sha512-dmA4HGc9m+TsVNsJs9/XQBI8u6j7coilN+qKkBuhuXQzH3HypwS/c5dFQ4UqUGjBbcxIM7zdk91kM/SRZwIvWQ==` |
| `svelte` | 5.56.5 | MIT | `sha512-P03YJmUy2JoOxYHb4Ka3oFat1hq2ko2it1MOItSjsJ4B6WgZPLc3+RyyU+57OGWhs3Ieq74EJpZtSnNXdAGgDw==` |
| `devalue` (SSR) | 5.9.4 | MIT | `sha512-sPAT4pztbu6586/hrhOnMKS17IJrvg12mXiSPSS3W5qDeN2RGgvZ0diZCm31dBbnevfVmujNO3IM2wrS4Y2Rhg==` |
| `@lucide/svelte` | 1.21.0 | ISC; included Feather-derived icons also carry MIT terms | `sha512-MEv//A7Jv3kHukZowv/DWp1MAtUzJKYwtJsmnQ7X98lCgtac3z3NbaToDl3Q6jO3gS9sougFpcD+t+YuxOkRMw==` |
| `simple-icons` | 16.22.0 | CC0-1.0 | `sha512-/raPcEWh89R2O2IMmHum6W2dF99NNX3yWikVXKUalkDIWz3Rw/S2S+oDPTgJcIGsRh+25m0y8vj4aBaV/KPz3Q==` |
| `rolldown` generated runtime | 1.1.5 | MIT | `sha512-t9z29cJjXf/vxQ8dyhCSpt6H6aSwHTk8cT5I3iy6SMXuFpk5mB6PL6XfC8PCwrPTx93udwKUm9HRteAlTGBLiA==` |
| `tailwindcss` generated styles | 4.3.0 | MIT | `sha512-y6nxMGB1nMW9R6k96e5gdIFzcfL/gTJRNaqGes1YvkLnPVXzWgbqFF2yLC0T8G774n24cx3Pe8XrKoniCOAH+Q==` |
| `normalize.css` styles embedded in Swagger UI CSS | 7.0.0 | MIT | Included in upstream Swagger CSS |

The `es-toolkit` retained compatibility helper links to Lodash's
`_hasUnicode.js`; its applicable MIT attribution and license text are included
below. Primary upstream license sources for the package versions listed above
are [`clsx` v2.1.1](https://raw.githubusercontent.com/lukeed/clsx/v2.1.1/license),
[`es-toolkit` v1.50.0](https://raw.githubusercontent.com/toss/es-toolkit/v1.50.0/LICENSE),
[`Laravel Precognition` v2.0.0](https://raw.githubusercontent.com/laravel/precognition/v2.0.0/LICENSE.md),
[`devalue` v5.9.4](https://raw.githubusercontent.com/sveltejs/devalue/v5.9.4/LICENSE),
and [Lodash 4.17.21-es](https://raw.githubusercontent.com/lodash/lodash/4.17.21-es/LICENSE).
The self-hosted Swagger stylesheet identifies the included normalize.css
version as 7.0.0. The fresh client CSS output did not contain normalize.css;
no application normalize.css license attribution is asserted here.

The Composer runtime dependencies are installed under `vendor/` in the app and
worker images. `composer.lock` records each package's exact version, declared
license expression, and source reference. Package license files remain with
their respective packages under `vendor/` when supplied. The lockfile's
license field is package metadata, not a list of license file paths. The
`sirix/cycle-orm-extensions` package's embedded notice identifies adapted
`vjik/cycle-typecast` components; the applicable BSD-3-Clause text is included
below because the package notice points to it without embedding it.

## Distribution and source snapshots

Retain the root `LICENSE` and this notice, both font license files, and the
Swagger `LICENSE`, `NOTICE`, and bundle-specific license notices in distributed
images and source snapshots. The app and worker images retain the Composer
package license files under `vendor/`. The source archive is an informational
application source snapshot; production builds are made from a Git checkout,
and building the application from the archive without Git is not supported.

The source snapshot also includes `third-party-sources/`: the complete official
PHP 8.5.11 source (including bundled libmbfl and generated configure/build
files), and the complete Debian source packages and patch sets for all 92
source-package/version pairs installed across the app, worker and SSR images.
The conservative whole-package set includes permissively licensed files as
well as copyleft components; per-file licenses remain in the source packages.
[third-party-sources.json](third-party-sources.json) lists the exact runtime
packages, source versions, source payload URLs, sizes and SHA-256 values,
retained copyright/license paths and hashes, and copied runtime binary hashes.
No package source was modified by Packio's image recipe; PHP and protobuf
were compiled with its stated build options, and RoadRunner/Node were copied.

Keep this corresponding source available alongside every distribution of the
images; a notice or a download-only helper does not replace the actual source
payloads. The inventory/package check rejects missing or altered payloads and
runtime versions or legal files that differ from this manifest. The existing
Docker recipe and [source reconstruction instructions](docs/testing.md#distribution-notices)
are part of the source snapshot. PHP's source companion permits rebuilding
mbstring and its statically included libmbfl with modified library source;
Packio imposes no extra restriction on modification or reverse engineering to
debug those modifications. This source material is independent of the Git
metadata required to build the Packio application.

Debian's per-package copyrights and complete common license texts remain under
`/usr/share/doc/*/copyright` and `/usr/share/common-licenses/` in every image.
Node.js 24.21.0 and its bundled external-library license text remain at
`/usr/local/LICENSE` in SSR. PECL protobuf 5.36.2's BSD license and bundled
utf8_range license remain at `/usr/local/lib/php/doc/protobuf/LICENSE` and
`/usr/local/lib/php/doc/protobuf/third_party/utf8_range/LICENSE` in app/worker.
Their exact retained file hashes are included in the runtime manifest.


## Included license texts

The texts below apply to code or generated styles in the shipped browser and SSR bundles. Swagger UI’s upstream license and bundled-dependency notices are copied verbatim under `public/api-doc/assets/`; font license texts are copied verbatim under `public/fonts/`.

### MIT — Inertia.js (both packages)

```text
MIT License

Copyright (c) Jonathan Reinink <jonathan@reinink.ca>

Permission is hereby granted, free of charge, to any person obtaining a copy
of this software and associated documentation files (the "Software"), to deal
in the Software without restriction, including without limitation the rights
to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
copies of the Software, and to permit persons to whom the Software is
furnished to do so, subject to the following conditions:

The above copyright notice and this permission notice shall be included in all
copies or substantial portions of the Software.

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
SOFTWARE.
```

### MIT — Svelte

```text
Copyright (c) 2016-2025 [Svelte Contributors](https://github.com/sveltejs/svelte/graphs/contributors)

Permission is hereby granted, free of charge, to any person obtaining a copy of this software and associated documentation files (the "Software"), to deal in the Software without restriction, including without limitation the rights to use, copy, modify, merge, publish, distribute, sublicense, and/or sell copies of the Software, and to permit persons to whom the Software is furnished to do so, subject to the following conditions:

The above copyright notice and this permission notice shall be included in all copies or substantial portions of the Software.

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM, OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE SOFTWARE.
```

### MIT — Rolldown

```text
MIT License

Copyright (c) 2024-present VoidZero Inc. & Contributors

Permission is hereby granted, free of charge, to any person obtaining a copy
of this software and associated documentation files (the "Software"), to deal
in the Software without restriction, including without limitation the rights
to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
copies of the Software, and to permit persons to whom the Software is
furnished to do so, subject to the following conditions:

The above copyright notice and this permission notice shall be included in all
copies or substantial portions of the Software.

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
SOFTWARE.

end of terms and conditions

The licenses of externally maintained libraries from which parts of the Software is derived are listed [here](https://github.com/rolldown/rolldown/blob/main/THIRD-PARTY-LICENSE).
```

### MIT — Tailwind CSS

```text
MIT License

Copyright (c) Tailwind Labs, Inc.

Permission is hereby granted, free of charge, to any person obtaining a copy
of this software and associated documentation files (the "Software"), to deal
in the Software without restriction, including without limitation the rights
to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
copies of the Software, and to permit persons to whom the Software is
furnished to do so, subject to the following conditions:

The above copyright notice and this permission notice shall be included in all
copies or substantial portions of the Software.

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
SOFTWARE.
```

### ISC and Feather-derived MIT — Lucide

```text
ISC License

Copyright (c) 2026 Lucide Icons and Contributors

Permission to use, copy, modify, and/or distribute this software for any
purpose with or without fee is hereby granted, provided that the above
copyright notice and this permission notice appear in all copies.

THE SOFTWARE IS PROVIDED "AS IS" AND THE AUTHOR DISCLAIMS ALL WARRANTIES
WITH REGARD TO THIS SOFTWARE INCLUDING ALL IMPLIED WARRANTIES OF
MERCHANTABILITY AND FITNESS. IN NO EVENT SHALL THE AUTHOR BE LIABLE FOR
ANY SPECIAL, DIRECT, INDIRECT, OR CONSEQUENTIAL DAMAGES OR ANY DAMAGES
WHATSOEVER RESULTING FROM LOSS OF USE, DATA OR PROFITS, WHETHER IN AN
ACTION OF CONTRACT, NEGLIGENCE OR OTHER TORTIOUS ACTION, ARISING OUT OF
OR IN CONNECTION WITH THE USE OR PERFORMANCE OF THIS SOFTWARE.

---

The following Lucide icons are derived from the Feather project:

airplay, alert-circle, alert-octagon, alert-triangle, aperture, arrow-down-circle, arrow-down-left, arrow-down-right, arrow-down, arrow-left-circle, arrow-left, arrow-right-circle, arrow-right, arrow-up-circle, arrow-up-left, arrow-up-right, arrow-up, at-sign, calendar, cast, check, chevron-down, chevron-left, chevron-right, chevron-up, chevrons-down, chevrons-left, chevrons-right, chevrons-up, circle, clipboard, clock, code, columns, command, compass, corner-down-left, corner-down-right, corner-left-down, corner-left-up, corner-right-down, corner-right-up, corner-up-left, corner-up-right, crosshair, database, divide-circle, divide-square, dollar-sign, download, external-link, feather, frown, hash, headphones, help-circle, info, italic, key, layout, life-buoy, link-2, link, loader, lock, log-in, log-out, maximize, meh, minimize, minimize-2, minus-circle, minus-square, minus, monitor, moon, more-horizontal, more-vertical, move, music, navigation-2, navigation, octagon, pause-circle, percent, plus-circle, plus-square, plus, power, radio, rss, search, server, share, shopping-bag, sidebar, smartphone, smile, square, table-2, tablet, target, terminal, trash-2, trash, triangle, tv, type, upload, x-circle, x-octagon, x-square, x, zoom-in, zoom-out

The MIT License (MIT) (for the icons listed above)

Copyright (c) 2013-present Cole Bemis

Permission is hereby granted, free of charge, to any person obtaining a copy
of this software and associated documentation files (the "Software"), to deal
in the Software without restriction, including without limitation the rights
to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
copies of the Software, and to permit persons to whom the Software is
furnished to do so, subject to the following conditions:

The above copyright notice and this permission notice shall be included in all
copies or substantial portions of the Software.

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
SOFTWARE.
```

### CC0-1.0 — Simple Icons

```text
# CC0 1.0 Universal

## Statement of Purpose

The laws of most jurisdictions throughout the world automatically confer exclusive Copyright and Related Rights (defined below) upon the creator and subsequent owner(s) (each and all, an “owner”) of an original work of authorship and/or a database (each, a “Work”).

Certain owners wish to permanently relinquish those rights to a Work for the purpose of contributing to a commons of creative, cultural and scientific works (“Commons”) that the public can reliably and without fear of later claims of infringement build upon, modify, incorporate in other works, reuse and redistribute as freely as possible in any form whatsoever and for any purposes, including without limitation commercial purposes. These owners may contribute to the Commons to promote the ideal of a free culture and the further production of creative, cultural and scientific works, or to gain reputation or greater distribution for their Work in part through the use and efforts of others.

For these and/or other purposes and motivations, and without any expectation of additional consideration or compensation, the person associating CC0 with a Work (the “Affirmer”), to the extent that he or she is an owner of Copyright and Related Rights in the Work, voluntarily elects to apply CC0 to the Work and publicly distribute the Work under its terms, with knowledge of his or her Copyright and Related Rights in the Work and the meaning and intended legal effect of CC0 on those rights.

1. Copyright and Related Rights. A Work made available under CC0 may be protected by copyright and related or neighboring rights (“Copyright and Related Rights”). Copyright and Related Rights include, but are not limited to, the following:
    1. the right to reproduce, adapt, distribute, perform, display, communicate, and translate a Work;
    2. moral rights retained by the original author(s) and/or performer(s);
    3. publicity and privacy rights pertaining to a person’s image or likeness depicted in a Work;
    4. rights protecting against unfair competition in regards to a Work, subject to the limitations in paragraph 4(i), below;
    5. rights protecting the extraction, dissemination, use and reuse of data in a Work;
    6. database rights (such as those arising under Directive 96/9/EC of the European Parliament and of the Council of 11 March 1996 on the legal protection of databases, and under any national implementation thereof, including any amended or successor version of such directive); and
    7. other similar, equivalent or corresponding rights throughout the world based on applicable law or treaty, and any national implementations thereof.

2. Waiver. To the greatest extent permitted by, but not in contravention of, applicable law, Affirmer hereby overtly, fully, permanently, irrevocably and unconditionally waives, abandons, and surrenders all of Affirmer’s Copyright and Related Rights and associated claims and causes of action, whether now known or unknown (including existing as well as future claims and causes of action), in the Work (i) in all territories worldwide, (ii) for the maximum duration provided by applicable law or treaty (including future time extensions), (iii) in any current or future medium and for any number of copies, and (iv) for any purpose whatsoever, including without limitation commercial, advertising or promotional purposes (the “Waiver”). Affirmer makes the Waiver for the benefit of each member of the public at large and to the detriment of Affirmer’s heirs and successors, fully intending that such Waiver shall not be subject to revocation, rescission, cancellation, termination, or any other legal or equitable action to disrupt the quiet enjoyment of the Work by the public as contemplated by Affirmer’s express Statement of Purpose.

3. Public License Fallback. Should any part of the Waiver for any reason be judged legally invalid or ineffective under applicable law, then the Waiver shall be preserved to the maximum extent permitted taking into account Affirmer’s express Statement of Purpose. In addition, to the extent the Waiver is so judged Affirmer hereby grants to each affected person a royalty-free, non transferable, non sublicensable, non exclusive, irrevocable and unconditional license to exercise Affirmer’s Copyright and Related Rights in the Work (i) in all territories worldwide, (ii) for the maximum duration provided by applicable law or treaty (including future time extensions), (iii) in any current or future medium and for any number of copies, and (iv) for any purpose whatsoever, including without limitation commercial, advertising or promotional purposes (the “License”). The License shall be deemed effective as of the date CC0 was applied by Affirmer to the Work. Should any part of the License for any reason be judged legally invalid or ineffective under applicable law, such partial invalidity or ineffectiveness shall not invalidate the remainder of the License, and in such case Affirmer hereby affirms that he or she will not (i) exercise any of his or her remaining Copyright and Related Rights in the Work or (ii) assert any associated claims and causes of action with respect to the Work, in either case contrary to Affirmer’s express Statement of Purpose.

4. Limitations and Disclaimers.
    1. No trademark or patent rights held by Affirmer are waived, abandoned, surrendered, licensed or otherwise affected by this document.
    2. Affirmer offers the Work as-is and makes no representations or warranties of any kind concerning the Work, express, implied, statutory or otherwise, including without limitation warranties of title, merchantability, fitness for a particular purpose, non infringement, or the absence of latent or other defects, accuracy, or the present or absence of errors, whether or not discoverable, all to the greatest extent permissible under applicable law.
    3. Affirmer disclaims responsibility for clearing rights of other persons that may apply to the Work or any use thereof, including without limitation any person’s Copyright and Related Rights in the Work. Further, Affirmer disclaims responsibility for obtaining any necessary consents, permissions or other rights required for any use of the Work.
    4. Affirmer understands and acknowledges that Creative Commons is not a party to this document and has no duty or obligation with respect to this CC0 or use of the Work.

For more information, please see <https://creativecommons.org/publicdomain/zero/1.0>.
```

### BSD-3-Clause — adapted Cycle Typecast components

The Composer package `sirix/cycle-orm-extensions` 5.0.0 includes a `THIRD_PARTY_NOTICES.md` identifying five adapted components from `vjik/cycle-typecast` and attributing them to Sergei Predvoditelev. The included package notice points to the upstream license rather than embedding it; the applicable text is reproduced here from the [upstream license](https://github.com/vjik/cycle-typecast/blob/master/LICENSE.md). The upstream notice does not record a source commit for those adaptations.

```text
Copyright © 2021 by Sergei Predvoditelev (https://predvoditelev.ru)
All rights reserved.

Redistribution and use in source and binary forms, with or without
modification, are permitted provided that the following conditions
are met:

* Redistributions of source code must retain the above copyright
  notice, this list of conditions and the following disclaimer.
* Redistributions in binary form must reproduce the above copyright
  notice, this list of conditions and the following disclaimer in
  the documentation and/or other materials provided with the
  distribution.
* Neither the name of the copyright holder nor the names of its
  contributors may be used to endorse or promote products derived
  from this software without specific prior written permission.

THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS
"AS IS" AND ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT
LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS
FOR A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE
COPYRIGHT HOLDER OR CONTRIBUTORS BE LIABLE FOR ANY DIRECT, INDIRECT,
INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING,
BUT NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES;
LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER
CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT
LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN
ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE
POSSIBILITY OF SUCH DAMAGE.
```

## Runtime binary notices

The PHP 8.5.11 and RoadRunner v2025.1.15 license texts below accompany the
runtime binaries copied into the app and worker images. PHP is compiled with
the extensions selected in `.docker/Dockerfile.roadrunner`; RoadRunner's
upstream binary is copied without modification. PHP includes this required
acknowledgment: This product includes PHP software, freely available from
<http://www.php.net/software/>.

The Node.js 24.21.0 runtime in the SSR image retains its complete upstream
license bundle at `/usr/local/LICENSE`. The protobuf 5.36.2 extension retains
`/usr/local/lib/php/doc/protobuf/LICENSE` and its bundled
`third_party/utf8_range/LICENSE`. Debian packages retain their copyright and
license files under `/usr/share/doc/*/copyright`. Keep these files when
redistributing images. The Docker recipe's floating PHP and PECL inputs must
be checked against the actual built versions before distribution; these
notices do not pin those inputs or certify a rebuilt image.

### PHP License v3.01 — PHP 8.5.11

Source: php-src tag [`php-8.5.11`](https://github.com/php/php-src/tree/php-8.5.11), `LICENSE`.

```text
--------------------------------------------------------------------
                  The PHP License, version 3.01
Copyright (c) 1999 - 2024 The PHP Group. All rights reserved.
--------------------------------------------------------------------

Redistribution and use in source and binary forms, with or without
modification, is permitted provided that the following conditions
are met:

  1. Redistributions of source code must retain the above copyright
     notice, this list of conditions and the following disclaimer.

  2. Redistributions in binary form must reproduce the above copyright
     notice, this list of conditions and the following disclaimer in
     the documentation and/or other materials provided with the
     distribution.

  3. The name "PHP" must not be used to endorse or promote products
     derived from this software without prior written permission. For
     written permission, please contact group@php.net.

  4. Products derived from this software may not be called "PHP", nor
     may "PHP" appear in their name, without prior written permission
     from group@php.net.  You may indicate that your software works in
     conjunction with PHP by saying "Foo for PHP" instead of calling
     it "PHP Foo" or "phpfoo"

  5. The PHP Group may publish revised and/or new versions of the
     license from time to time. Each version will be given a
     distinguishing version number.
     Once covered code has been published under a particular version
     of the license, you may always continue to use it under the terms
     of that version. You may also choose to use such covered code
     under the terms of any subsequent version of the license
     published by the PHP Group. No one other than the PHP Group has
     the right to modify the terms applicable to covered code created
     under this License.

  6. Redistributions of any form whatsoever must retain the following
     acknowledgment:
     "This product includes PHP software, freely available from
     <http://www.php.net/software/>".

THIS SOFTWARE IS PROVIDED BY THE PHP DEVELOPMENT TEAM ``AS IS'' AND
ANY EXPRESSED OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO,
THE IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A
PARTICULAR PURPOSE ARE DISCLAIMED.  IN NO EVENT SHALL THE PHP
DEVELOPMENT TEAM OR ITS CONTRIBUTORS BE LIABLE FOR ANY DIRECT,
INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES
(INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR
SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION)
HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN CONTRACT,
STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE)
ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED
OF THE POSSIBILITY OF SUCH DAMAGE.

--------------------------------------------------------------------

This software consists of voluntary contributions made by many
individuals on behalf of the PHP Group.

The PHP Group can be contacted via Email at group@php.net.

For more information on the PHP Group and the PHP project,
please see <http://www.php.net>.

PHP includes the Zend Engine, freely available at
<http://www.zend.com>.
```

### MIT — RoadRunner v2025.1.15

Source: [RoadRunner tag v2025.1.15](https://github.com/roadrunner-server/roadrunner/tree/v2025.1.15), `LICENSE`.

```text
MIT License

Copyright (c) 2020 Spiral Scout

Permission is hereby granted, free of charge, to any person obtaining a copy
of this software and associated documentation files (the "Software"), to deal
in the Software without restriction, including without limitation the rights
to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
copies of the Software, and to permit persons to whom the Software is
furnished to do so, subject to the following conditions:

The above copyright notice and this permission notice shall be included in all
copies or substantial portions of the Software.

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
SOFTWARE.
```

### MIT — `clsx` 2.1.1

Source: [`clsx` v2.1.1 license](https://raw.githubusercontent.com/lukeed/clsx/v2.1.1/license).

```text
MIT License

Copyright (c) Luke Edwards <luke.edwards05@gmail.com> (lukeed.com)

Permission is hereby granted, free of charge, to any person obtaining a copy of this software and associated documentation files (the "Software"), to deal in the Software without restriction, including without limitation the rights to use, copy, modify, merge, publish, distribute, sublicense, and/or sell copies of the Software, and to permit persons to whom the Software is furnished to do so, subject to the following conditions:

The above copyright notice and this permission notice shall be included in all copies or substantial portions of the Software.

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM, OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE SOFTWARE.
```

### MIT — `es-toolkit` 1.50.0

Source: [`es-toolkit` v1.50.0 license](https://raw.githubusercontent.com/toss/es-toolkit/v1.50.0/LICENSE).

```text
MIT License

Copyright (c) 2024 Viva Republica, Inc.

Permission is hereby granted, free of charge, to any person obtaining a copy
of this software and associated documentation files (the "Software"), to deal
in the Software without restriction, including without limitation the rights
to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
copies of the Software, and to permit persons to whom the Software is
furnished to do so, subject to the following conditions:

The above copyright notice and this permission notice shall be included in all
copies or substantial portions of the Software.

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
SOFTWARE.
```

### MIT — `laravel-precognition` 2.0.0

Source: [`laravel-precognition` v2.0.0 license](https://raw.githubusercontent.com/laravel/precognition/v2.0.0/LICENSE.md).

```text
The MIT License (MIT)

Copyright (c) Taylor Otwell

Permission is hereby granted, free of charge, to any person obtaining a copy
of this software and associated documentation files (the "Software"), to deal
in the Software without restriction, including without limitation the rights
to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
copies of the Software, and to permit persons to whom the Software is
furnished to do so, subject to the following conditions:

The above copyright notice and this permission notice shall be included in
all copies or substantial portions of the Software.

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
THE SOFTWARE.
```

### MIT — `devalue` 5.9.4

Source: [`devalue` v5.9.4 license](https://raw.githubusercontent.com/sveltejs/devalue/v5.9.4/LICENSE).

```text
Copyright (c) 2018-19 [these people](https://github.com/rich-harris/devalue/graphs/contributors)

Permission is hereby granted, free of charge, to any person obtaining a copy of this software and associated documentation files (the "Software"), to deal in the Software without restriction, including without limitation the rights to use, copy, modify, merge, publish, distribute, sublicense, and/or sell copies of the Software, and to permit persons to whom the Software is furnished to do so, subject to the following conditions:

The above copyright notice and this permission notice shall be included in all copies or substantial portions of the Software.

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM, OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE SOFTWARE.
```

### MIT — Lodash-derived Unicode helper

The `es-toolkit` source module `compat/_internal/regexMultiByte` links to
Lodash's `_hasUnicode.js` from the `4.17.21-es` source line. Source:
[Lodash 4.17.21-es LICENSE](https://raw.githubusercontent.com/lodash/lodash/4.17.21-es/LICENSE).

```text
Copyright OpenJS Foundation and other contributors <https://openjsf.org/>

Based on Underscore.js, copyright Jeremy Ashkenas,
DocumentCloud and Investigative Reporters & Editors <http://underscorejs.org/>

This software consists of voluntary contributions made by many
individuals. For exact contribution history, see the revision history
available at https://github.com/lodash/lodash

The following license applies to all parts of this software except as
documented below:

====
Permission is hereby granted, free of charge, to any person obtaining
a copy of this software and associated documentation files (the
"Software"), to deal in the Software without restriction, including
without limitation the rights to use, copy, modify, merge, publish,
distribute, sublicense, and/or sell copies of the Software, and to permit
persons to whom the Software is furnished to do so, subject to the
following conditions:
The above copyright notice and this permission notice shall be
included in all copies or substantial portions of the Software.
THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND,
EXPRESS OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF
MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE AND
NONINFRINGEMENT. IN NO EVENT SHALL THE AUTHORS OR COPYRIGHT HOLDERS BE
LIABLE FOR ANY CLAIM, DAMAGES OR OTHER LIABILITY, WHETHER IN AN ACTION
OF CONTRACT, TORT OR OTHERWISE, ARISING FROM, OUT OF OR IN CONNECTION
WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE SOFTWARE.

====
Copyright and related rights for sample code are waived via CC0. Sample
code is defined as all source code displayed within the prose of the
documentation.

CC0: http://creativecommons.org/publicdomain/zero/1.0/

====

Files located in the node_modules and vendor directories are externally
maintained libraries used by this software which have their own
licenses; we recommend you read them, as their terms may differ from the
terms above.
```

### MIT — normalize.css 7.0.0

The Swagger UI stylesheet includes normalize.css 7.0.0. Source:
[normalize.css v7.0.0 LICENSE.md](https://raw.githubusercontent.com/necolas/normalize.css/7.0.0/LICENSE.md).

```text
# The MIT License (MIT)

Copyright © Nicolas Gallagher and Jonathan Neal

Permission is hereby granted, free of charge, to any person obtaining a copy of
this software and associated documentation files (the "Software"), to deal in
the Software without restriction, including without limitation the rights to
use, copy, modify, merge, publish, distribute, sublicense, and/or sell copies
of the Software, and to permit persons to whom the Software is furnished to do
so, subject to the following conditions:

The above copyright notice and this permission notice shall be included in all
copies or substantial portions of the Software.

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
SOFTWARE.
```


## RoadRunner

RoadRunner v2025.1.15 binary is included unmodified. The executable SHA-256 is `6f01f41a54d07716d11ed2cd9259c221e2380a14db38e9eea18e93eef2eda7ba`. Source: [roadrunner-server/roadrunner, tag v2025.1.15](https://github.com/roadrunner-server/roadrunner/tree/v2025.1.15), `LICENSE`.

```text
MIT License

Copyright (c) 2020 Spiral Scout

Permission is hereby granted, free of charge, to any person obtaining a copy
of this software and associated documentation files (the "Software"), to deal
in the Software without restriction, including without limitation the rights
to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
copies of the Software, and to permit persons to whom the Software is
furnished to do so, subject to the following conditions:

The above copyright notice and this permission notice shall be included in all
copies or substantial portions of the Software.

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
SOFTWARE.
```


## RoadRunner: Go standard library and linked modules

The unmodified RoadRunner binary copied from the digest-pinned upstream image
reports Go 1.26.4. Its embedded build information identifies the 201 module
versions and Go content hashes below. The corresponding published module source
archives were checked against those exact hashes. Identical legal texts are
reproduced once, with every originating module and file identified. Some source
archives include legal material for optional components; retaining that material
does not assert that every optional component is linked or that its name/logo is
used by Packio. RoadRunner's own MIT text appears above.

The `nexus-proto-annotations` v0.1.0 archive omits a license file. The rights-holder
added the [MIT license](https://github.com/nexus-rpc/nexus-proto-annotations/blob/835bd8d49cb45c8efa22614164b90335f7e56918/LICENSE)
in its exact tag's direct child, with no change to any of the eight existing
files, including the proto and generated Go source. That explicit grant on
identical source is reproduced here. This does not claim the original archive
contained a license. `endure/v2` v2.6.2 similarly omits `LICENCE` from its module
archive; its exact upstream tag's MIT license is reproduced here.

### Go 1.26.4 standard-library license


Source: [Go 1.26.4 LICENSE](https://raw.githubusercontent.com/golang/go/go1.26.4/LICENSE)  
SHA-256: `911f8f5782931320f5b8d1160a76365b83aea6447ee6c04fa6d5591467db9dad`

```text
Copyright 2009 The Go Authors.

Redistribution and use in source and binary forms, with or without
modification, are permitted provided that the following conditions are
met:

   * Redistributions of source code must retain the above copyright
notice, this list of conditions and the following disclaimer.
   * Redistributions in binary form must reproduce the above
copyright notice, this list of conditions and the following disclaimer
in the documentation and/or other materials provided with the
distribution.
   * Neither the name of Google LLC nor the names of its
contributors may be used to endorse or promote products derived from
this software without specific prior written permission.

THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS
"AS IS" AND ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT
LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR
A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT
OWNER OR CONTRIBUTORS BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL,
SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT
LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES; LOSS OF USE,
DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND ON ANY
THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT
(INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE
OF THIS SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.
```


### Linked module inventory

| Module | Version / source | Verified source Go hash | Legal texts |
| --- | --- | --- | --- |
| `cloud.google.com/go` | [v0.123.0](https://proxy.golang.org/cloud.google.com/go/@v/v0.123.0.zip) | `h1:2NAUJwPR47q+E35uaJeYoNhuNEM9kM8SjgRgdeOJUSE=` | [G101](#go-legal-text-g101), [G015](#go-legal-text-g015) |
| `cloud.google.com/go/auth` | [v0.20.0](https://proxy.golang.org/cloud.google.com/go/auth/@v/v0.20.0.zip) | `h1:kXTssoVb4azsVDoUiF8KvxAqrsQcQtB53DcSgta74CA=` | [G101](#go-legal-text-g101) |
| `cloud.google.com/go/auth/oauth2adapt` | [v0.2.8](https://proxy.golang.org/cloud.google.com/go/auth/oauth2adapt/@v/v0.2.8.zip) | `h1:keo8NaayQZ6wimpNSmW5OPc283g65QNIiLpZnkHRbnc=` | [G101](#go-legal-text-g101) |
| `cloud.google.com/go/compute/metadata` | [v0.9.0](https://proxy.golang.org/cloud.google.com/go/compute/metadata/@v/v0.9.0.zip) | `h1:pDUj4QMoPejqq20dK0Pg2N4yG9zIkYGdBtwLoEkH9Zs=` | [G101](#go-legal-text-g101) |
| `cloud.google.com/go/iam` | [v1.11.0](https://proxy.golang.org/cloud.google.com/go/iam/@v/v1.11.0.zip) | `h1:KieQ9Pb+LLPak1O3Rv3GgCxhnmkYf7Xyh0P5HfF1jFM=` | [G101](#go-legal-text-g101) |
| `cloud.google.com/go/pubsub/v2` | [v2.6.0](https://proxy.golang.org/cloud.google.com/go/pubsub/v2/@v/v2.6.0.zip) | `h1:8pjR0id+GTB+krKx5G6AGJoYrHog58w2Q89PCOrfM64=` | [G101](#go-legal-text-g101) |
| `github.com/andybalholm/brotli` | [v1.2.1](https://proxy.golang.org/github.com/andybalholm/brotli/@v/v1.2.1.zip) | `h1:R+f5xP285VArJDRgowrfb9DqL18yVK0gKAW/F+eTWro=` | [G034](#go-legal-text-g034), [G029](#go-legal-text-g029) |
| `github.com/aws/aws-sdk-go-v2` | [v1.42.0](https://proxy.golang.org/github.com/aws/aws-sdk-go-v2/@v/v1.42.0.zip) | `h1:XvXMJTkFQtpBKIWZnmr9ZEOc2InWM2yldjXEJ/bymhA=` | [G101](#go-legal-text-g101), [G081](#go-legal-text-g081), [G117](#go-legal-text-g117) |
| `github.com/aws/aws-sdk-go-v2/config` | [v1.32.25](https://proxy.golang.org/github.com/aws/aws-sdk-go-v2/config/@v/v1.32.25.zip) | `h1:ACCejvStYoilgwrfegSt5ZntCbPrk52qfwyNcnl3omM=` | [G101](#go-legal-text-g101) |
| `github.com/aws/aws-sdk-go-v2/credentials` | [v1.19.24](https://proxy.golang.org/github.com/aws/aws-sdk-go-v2/credentials/@v/v1.19.24.zip) | `h1:2hQqYCV9yqyePQ9o6dCrZc/zO8U3TwPr9mIKlZnPu/I=` | [G101](#go-legal-text-g101) |
| `github.com/aws/aws-sdk-go-v2/feature/ec2/imds` | [v1.18.29](https://proxy.golang.org/github.com/aws/aws-sdk-go-v2/feature/ec2/imds/@v/v1.18.29.zip) | `h1:r6qZHbT+wxgWO/e9vYNUEtg7lv5+UN3pRqKhLXvnArg=` | [G101](#go-legal-text-g101) |
| `github.com/aws/aws-sdk-go-v2/internal/configsources` | [v1.4.29](https://proxy.golang.org/github.com/aws/aws-sdk-go-v2/internal/configsources/@v/v1.4.29.zip) | `h1:f3vKqSo13fhTYb+JEcXwXefZQE26I1FB5eTSniU67ko=` | [G101](#go-legal-text-g101) |
| `github.com/aws/aws-sdk-go-v2/internal/endpoints/v2` | [v2.7.29](https://proxy.golang.org/github.com/aws/aws-sdk-go-v2/internal/endpoints/v2/@v/v2.7.29.zip) | `h1:RdwIf/CuUsvJX3RgJagbOyotl/cxoLY4xviKuE7p2GY=` | [G101](#go-legal-text-g101) |
| `github.com/aws/aws-sdk-go-v2/internal/v4a` | [v1.4.30](https://proxy.golang.org/github.com/aws/aws-sdk-go-v2/internal/v4a/@v/v1.4.30.zip) | `h1:VTGy885W5DKBxWRUJbym9hytNaYzsyaPkCHGRRMAOhU=` | [G101](#go-legal-text-g101) |
| `github.com/aws/aws-sdk-go-v2/service/internal/accept-encoding` | [v1.13.12](https://proxy.golang.org/github.com/aws/aws-sdk-go-v2/service/internal/accept-encoding/@v/v1.13.12.zip) | `h1:ZD2+BSw9vFsNlKYIasSNt3uDbjqqXIBcM13UJv/Lx2k=` | [G101](#go-legal-text-g101) |
| `github.com/aws/aws-sdk-go-v2/service/internal/presigned-url` | [v1.13.29](https://proxy.golang.org/github.com/aws/aws-sdk-go-v2/service/internal/presigned-url/@v/v1.13.29.zip) | `h1:DRebniUGZ2MqiiIVmQJ04vIXr918hubdHMnarSLEWyU=` | [G101](#go-legal-text-g101) |
| `github.com/aws/aws-sdk-go-v2/service/signin` | [v1.2.0](https://proxy.golang.org/github.com/aws/aws-sdk-go-v2/service/signin/@v/v1.2.0.zip) | `h1:3nXpRcFwRCW8n7HgO2QGy0Dc20eQNfBuUemGQhpF8m8=` | [G101](#go-legal-text-g101) |
| `github.com/aws/aws-sdk-go-v2/service/sqs` | [v1.44.0](https://proxy.golang.org/github.com/aws/aws-sdk-go-v2/service/sqs/@v/v1.44.0.zip) | `h1:6DQ95Zq5xPUSYm6KWcrar7zvreqAMFmyynYCcmzv6yc=` | [G101](#go-legal-text-g101) |
| `github.com/aws/aws-sdk-go-v2/service/sso` | [v1.31.3](https://proxy.golang.org/github.com/aws/aws-sdk-go-v2/service/sso/@v/v1.31.3.zip) | `h1:ey1XLTYXb9PcLt4535632o5kCGXNXEhNb620Dqwuylo=` | [G101](#go-legal-text-g101) |
| `github.com/aws/aws-sdk-go-v2/service/ssooidc` | [v1.36.6](https://proxy.golang.org/github.com/aws/aws-sdk-go-v2/service/ssooidc/@v/v1.36.6.zip) | `h1:yLr03zQE/5Eu5l3QU0Si+xMbLMbSDF2YXsigqXngs6g=` | [G101](#go-legal-text-g101) |
| `github.com/aws/aws-sdk-go-v2/service/sts` | [v1.43.3](https://proxy.golang.org/github.com/aws/aws-sdk-go-v2/service/sts/@v/v1.43.3.zip) | `h1:VrIhKRCSK1umelSgB9RghvA9RTUYeQffyAS5ApXehNI=` | [G101](#go-legal-text-g101) |
| `github.com/aws/smithy-go` | [v1.27.2](https://proxy.golang.org/github.com/aws/smithy-go/@v/v1.27.2.zip) | `h1:y9NPmSE6am6LjEFPfqHqG/jJk7AauQvhCJONKh7kpzk=` | [G008](#go-legal-text-g008), [G103](#go-legal-text-g103), [G117](#go-legal-text-g117), [G029](#go-legal-text-g029) |
| `github.com/beanstalkd/go-beanstalk` | [v0.2.0](https://proxy.golang.org/github.com/beanstalkd/go-beanstalk/@v/v0.2.0.zip) | `h1:6UOJugnu47uNB2jJO/lxyDgeD1Yds7owYi1USELqexA=` | [G041](#go-legal-text-g041) |
| `github.com/beorn7/perks` | [v1.0.1](https://proxy.golang.org/github.com/beorn7/perks/@v/v1.0.1.zip) | `h1:VlbKKnNfV8bJzeqoa4cOKqO6bYr3WgKZxO8Z16+hsOM=` | [G011](#go-legal-text-g011) |
| `github.com/bmatcuk/doublestar/v4` | [v4.10.0](https://proxy.golang.org/github.com/bmatcuk/doublestar/v4/@v/v4.10.0.zip) | `h1:zU9WiOla1YA122oLM6i4EXvGW62DvKZVxIe6TYWexEs=` | [G025](#go-legal-text-g025) |
| `github.com/bradfitz/gomemcache` | [v0.0.0-20260422231931-4d751bb6e37c](https://proxy.golang.org/github.com/bradfitz/gomemcache/@v/v0.0.0-20260422231931-4d751bb6e37c.zip) | `h1:6Gpm9YYUEQx2T9zMsYolQhr6sjwwGtFitSA0pQsa7a8=` | [G101](#go-legal-text-g101) |
| `github.com/buger/goterm` | [v1.0.4](https://proxy.golang.org/github.com/buger/goterm/@v/v1.0.4.zip) | `h1:Z9YvGmOih81P0FbVtEYTFF6YsSgxSUKEhf/f9bTMXbY=` | [G050](#go-legal-text-g050) |
| `github.com/cactus/go-statsd-client/v5` | [v5.1.0](https://proxy.golang.org/github.com/cactus/go-statsd-client/v5/@v/v5.1.0.zip) | `h1:sbbdfIl9PgisjEoXzvXI1lwUKWElngsjJKaZeC021P4=` | [G026](#go-legal-text-g026) |
| `github.com/caddyserver/certmagic` | [v0.25.3](https://proxy.golang.org/github.com/caddyserver/certmagic/@v/v0.25.3.zip) | `h1:mGf5ba8F7xA4c5jfDZZbK2buY1VEkbnwpMDixaju94A=` | [G088](#go-legal-text-g088) |
| `github.com/caddyserver/zerossl` | [v0.1.5](https://proxy.golang.org/github.com/caddyserver/zerossl/@v/v0.1.5.zip) | `h1:dkvOjBAEEtY6LIGAHei7sw2UgqSD6TrWweXpV7lvEvE=` | [G020](#go-legal-text-g020) |
| `github.com/cenkalti/backoff/v4` | [v4.3.0](https://proxy.golang.org/github.com/cenkalti/backoff/v4/@v/v4.3.0.zip) | `h1:MyRJ/UdXutAwSAT+s3wNd7MfTIcy71VQueUuFK343L8=` | [G044](#go-legal-text-g044) |
| `github.com/cenkalti/backoff/v5` | [v5.0.3](https://proxy.golang.org/github.com/cenkalti/backoff/v5/@v/v5.0.3.zip) | `h1:ZN+IMa753KfX5hd8vVaMixjnqRZ3y8CuJKRKj1xcsSM=` | [G044](#go-legal-text-g044) |
| `github.com/cespare/xxhash/v2` | [v2.3.0](https://proxy.golang.org/github.com/cespare/xxhash/v2/@v/v2.3.0.zip) | `h1:UL815xU9SqsFlibzuggzjXhog7bL6oX9BbNZnL2UFvs=` | [G118](#go-legal-text-g118) |
| `github.com/clipperhouse/displaywidth` | [v0.11.0](https://proxy.golang.org/github.com/clipperhouse/displaywidth/@v/v0.11.0.zip) | `h1:lBc6kY44VFw+TDx4I8opi/EtL9m20WSEFgwIwO+UVM8=` | [G124](#go-legal-text-g124) |
| `github.com/clipperhouse/uax29/v2` | [v2.7.0](https://proxy.golang.org/github.com/clipperhouse/uax29/v2/@v/v2.7.0.zip) | `h1:+gs4oBZ2gPfVrKPthwbMzWZDaAFPGYK72F0NJv2v7Vk=` | [G005](#go-legal-text-g005) |
| `github.com/davecgh/go-spew` | [v1.1.2-0.20180830191138-d8f796af33cc](https://proxy.golang.org/github.com/davecgh/go-spew/@v/v1.1.2-0.20180830191138-d8f796af33cc.zip) | `h1:U9qPSI2PIWSS1VwoXQT9A3Wy9MM3WgvqSxFWenqJduM=` | [G019](#go-legal-text-g019) |
| `github.com/dustin/go-humanize` | [v1.0.1](https://proxy.golang.org/github.com/dustin/go-humanize/@v/v1.0.1.zip) | `h1:GzkhY7T5VNhEkwH0PVJgjz+fX1rhBrR7pRT3mDkpeCY=` | [G084](#go-legal-text-g084) |
| `github.com/emicklei/proto` | [v1.14.3](https://proxy.golang.org/github.com/emicklei/proto/@v/v1.14.3.zip) | `h1:zEhlzNkpP8kN6utonKMzlPfIvy82t5Kb9mufaJxSe1Q=` | [G112](#go-legal-text-g112) |
| `github.com/facebookgo/clock` | [v0.0.0-20150410010913-600d898af40a](https://proxy.golang.org/github.com/facebookgo/clock/@v/v0.0.0-20150410010913-600d898af40a.zip) | `h1:yDWHCSQ40h88yih2JAcL6Ls/kVkSE8GFACTGVnMPruw=` | [G040](#go-legal-text-g040) |
| `github.com/fatih/color` | [v1.19.0](https://proxy.golang.org/github.com/fatih/color/@v/v1.19.0.zip) | `h1:Zp3PiM21/9Ld6FzSKyL5c/BULoe/ONr9KlbYVOfG8+w=` | [G108](#go-legal-text-g108) |
| `github.com/felixge/httpsnoop` | [v1.1.0](https://proxy.golang.org/github.com/felixge/httpsnoop/@v/v1.1.0.zip) | `h1:3YtUj32ZZkqZtt3sZZsClsymw/QDuVfpNhoA31zeORc=` | [G062](#go-legal-text-g062) |
| `github.com/fsnotify/fsnotify` | [v1.10.1](https://proxy.golang.org/github.com/fsnotify/fsnotify/@v/v1.10.1.zip) | `h1:b0/UzAf9yR5rhf3RPm9gf3ehBPpf0oZKIjtpKrx59Ho=` | [G030](#go-legal-text-g030) |
| `github.com/go-logr/logr` | [v1.4.3](https://proxy.golang.org/github.com/go-logr/logr/@v/v1.4.3.zip) | `h1:CjnDlHq8ikf6E492q6eKboGOC0T8CDaOvkHCIg8idEI=` | [G088](#go-legal-text-g088) |
| `github.com/go-logr/stdr` | [v1.2.2](https://proxy.golang.org/github.com/go-logr/stdr/@v/v1.2.2.zip) | `h1:hSWxHoqTgW2S2qGc0LTAI563KZ5YKYRhT3MFKZMbjag=` | [G100](#go-legal-text-g100) |
| `github.com/go-viper/mapstructure/v2` | [v2.5.0](https://proxy.golang.org/github.com/go-viper/mapstructure/v2/@v/v2.5.0.zip) | `h1:vM5IJoUAy3d7zRSVtIwQgBj7BiWtMPfmPEgAXnvj1Ro=` | [G024](#go-legal-text-g024) |
| `github.com/goccy/go-json` | [v0.10.6](https://proxy.golang.org/github.com/goccy/go-json/@v/v0.10.6.zip) | `h1:p8HrPJzOakx/mn/bQtjgNjdTcN+/S6FcG2CTtQOrHVU=` | [G082](#go-legal-text-g082) |
| `github.com/gofiber/fiber/v2` | [v2.52.13](https://proxy.golang.org/github.com/gofiber/fiber/v2/@v/v2.52.13.zip) | `h1:TOKP64iqC9b5P49VrBW5tHhUOvDyrtJ0xePEfzJbCbk=` | [G045](#go-legal-text-g045), [G105](#go-legal-text-g105), [G071](#go-legal-text-g071), [G111](#go-legal-text-g111), [G052](#go-legal-text-g052) |
| `github.com/gogo/protobuf` | [v1.3.2](https://proxy.golang.org/github.com/gogo/protobuf/@v/v1.3.2.zip) | `h1:Ov1cvc58UF3b5XjBnZv7+opcTcQFZebYjWzi34vdm4Q=` | [G035](#go-legal-text-g035) |
| `github.com/golang/mock` | [v1.7.0-rc.1](https://proxy.golang.org/github.com/golang/mock/@v/v1.7.0-rc.1.zip) | `h1:YojYx61/OLFsiv6Rw1Z96LpldJIy31o+UHmwAUMJ6/U=` | [G101](#go-legal-text-g101), [G064](#go-legal-text-g064) |
| `github.com/google/s2a-go` | [v0.1.9](https://proxy.golang.org/github.com/google/s2a-go/@v/v0.1.9.zip) | `h1:LGD7gtMgezd8a/Xak7mEWL0PjoTQFvpRudN895yqKW0=` | [G101](#go-legal-text-g101) |
| `github.com/google/uuid` | [v1.6.0](https://proxy.golang.org/github.com/google/uuid/@v/v1.6.0.zip) | `h1:NIvaJDMOsjHA8n1jAhLSgzrAzy1Hgr+hNrb57e+94F0=` | [G009](#go-legal-text-g009) |
| `github.com/googleapis/enterprise-certificate-proxy` | [v0.3.16](https://proxy.golang.org/github.com/googleapis/enterprise-certificate-proxy/@v/v0.3.16.zip) | `h1:F/VPrx0YPBdksZJQdCAp0WUsqnNmZpUZszzfYt0M5Dw=` | [G101](#go-legal-text-g101) |
| `github.com/googleapis/gax-go/v2` | [v2.22.0](https://proxy.golang.org/github.com/googleapis/gax-go/v2/@v/v2.22.0.zip) | `h1:PjIWBpgGIVKGoCXuiCoP64altEJCj3/Ei+kSU5vlZD4=` | [G092](#go-legal-text-g092) |
| `github.com/grpc-ecosystem/go-grpc-middleware/v2` | [v2.3.3](https://proxy.golang.org/github.com/grpc-ecosystem/go-grpc-middleware/v2/@v/v2.3.3.zip) | `h1:B+8ClL/kCQkRiU82d9xajRPKYMrB7E0MbtzWVi1K4ns=` | [G069](#go-legal-text-g069), [G033](#go-legal-text-g033) |
| `github.com/grpc-ecosystem/grpc-gateway/v2` | [v2.29.0](https://proxy.golang.org/github.com/grpc-ecosystem/grpc-gateway/v2/@v/v2.29.0.zip) | `h1:5VipnvEpbqr2gA2VbM+nYVbkIF28c5ZQfqCBQ5g2xfk=` | [G077](#go-legal-text-g077), [G022](#go-legal-text-g022) |
| `github.com/joho/godotenv` | [v1.5.1](https://proxy.golang.org/github.com/joho/godotenv/@v/v1.5.1.zip) | `h1:7eLL/+HRGLY0ldzfGMeQkb7vMd0as4CfYvUVzLqw0N0=` | [G037](#go-legal-text-g037) |
| `github.com/klauspost/compress` | [v1.18.6](https://proxy.golang.org/github.com/klauspost/compress/@v/v1.18.6.zip) | `h1:2jupLlAwFm95+YDR+NwD2MEfFO9d4z4Prjl1XXDjuao=` | [G010](#go-legal-text-g010), [G023](#go-legal-text-g023), [G055](#go-legal-text-g055), [G119](#go-legal-text-g119), [G006](#go-legal-text-g006), [G066](#go-legal-text-g066), [G013](#go-legal-text-g013), [G104](#go-legal-text-g104), [G118](#go-legal-text-g118) |
| `github.com/klauspost/cpuid/v2` | [v2.3.0](https://proxy.golang.org/github.com/klauspost/cpuid/v2/@v/v2.3.0.zip) | `h1:S4CRMLnYUhGeDFDqkGriYKdfoFlDnMtqTiI/sFzhA9Y=` | [G046](#go-legal-text-g046) |
| `github.com/libdns/libdns` | [v1.1.1](https://proxy.golang.org/github.com/libdns/libdns/@v/v1.1.1.zip) | `h1:wPrHrXILoSHKWJKGd0EiAVmiJbFShguILTg9leS/P/U=` | [G114](#go-legal-text-g114) |
| `github.com/mattn/go-colorable` | [v0.1.15](https://proxy.golang.org/github.com/mattn/go-colorable/@v/v0.1.15.zip) | `h1:+u9SLTRGnXv73cEsnsmoZBom+dMU88B2M0aDcWy0/jY=` | [G067](#go-legal-text-g067) |
| `github.com/mattn/go-isatty` | [v0.0.22](https://proxy.golang.org/github.com/mattn/go-isatty/@v/v0.0.22.zip) | `h1:j8l17JJ9i6VGPUFUYoTUKPSgKe/83EYU2zBC7YNKMw4=` | [G007](#go-legal-text-g007) |
| `github.com/mattn/go-runewidth` | [v0.0.24](https://proxy.golang.org/github.com/mattn/go-runewidth/@v/v0.0.24.zip) | `h1:cpokDiIn0MGnhdHwuWnJBITySJ20QyNGnY2kR/ay2DU=` | [G067](#go-legal-text-g067) |
| `github.com/mholt/acmez` | [v1.2.0](https://proxy.golang.org/github.com/mholt/acmez/@v/v1.2.0.zip) | `h1:1hhLxSgY5FvH5HCnGUuwbKY2VQVo8IU7rxXKSnZ7F30=` | [G100](#go-legal-text-g100) |
| `github.com/mholt/acmez/v3` | [v3.1.6](https://proxy.golang.org/github.com/mholt/acmez/v3/@v/v3.1.6.zip) | `h1:eGVQNObP0pBN4sxqrXeg7MYqTOWyoiYpQqITVWlrevk=` | [G100](#go-legal-text-g100) |
| `github.com/miekg/dns` | [v1.1.72](https://proxy.golang.org/github.com/miekg/dns/@v/v1.1.72.zip) | `h1:vhmr+TF2A3tuoGNkLDFK9zi36F2LS+hKTRW0Uf8kbzI=` | [G051](#go-legal-text-g051), [G116](#go-legal-text-g116) |
| `github.com/munnerz/goautoneg` | [v0.0.0-20191010083416-a7dc8b61c822](https://proxy.golang.org/github.com/munnerz/goautoneg/@v/v0.0.0-20191010083416-a7dc8b61c822.zip) | `h1:C3w9PqII01/Oq1c1nUAm88MOHcQC9l5mIlSMApZMrHA=` | [G085](#go-legal-text-g085) |
| `github.com/nats-io/nats.go` | [v1.52.0](https://proxy.golang.org/github.com/nats-io/nats.go/@v/v1.52.0.zip) | `h1:n3avV4VBsCgsdwh71TppsTwtv+QdPs7ntSKM8qJLGsc=` | [G100](#go-legal-text-g100) |
| `github.com/nats-io/nkeys` | [v0.4.16](https://proxy.golang.org/github.com/nats-io/nkeys/@v/v0.4.16.zip) | `h1:rd5oAuLOb8mnAycB0xleuEBNS1pVVnN0fv/FF34Eypg=` | [G100](#go-legal-text-g100) |
| `github.com/nats-io/nuid` | [v1.0.1](https://proxy.golang.org/github.com/nats-io/nuid/@v/v1.0.1.zip) | `h1:5iA8DT8V7q8WK2EScv2padNa/rTESc1KdnPw4TC2paw=` | [G100](#go-legal-text-g100) |
| `github.com/nexus-rpc/nexus-proto-annotations` | [v0.1.0](https://proxy.golang.org/github.com/nexus-rpc/nexus-proto-annotations/@v/v0.1.0.zip) | `h1:2fELd+9sqUtNu6Fg//pw8YFsxOvp8vZ8hfP0nHhNI80=` | [G004](#go-legal-text-g004) |
| `github.com/nexus-rpc/sdk-go` | [v0.6.0](https://proxy.golang.org/github.com/nexus-rpc/sdk-go/@v/v0.6.0.zip) | `h1:QRgnP2zTbxEbiyWG/aXH8uSC5LV/Mg1fqb19jb4DBlo=` | [G059](#go-legal-text-g059) |
| `github.com/olekukonko/cat` | [v0.0.0-20250911104152-50322a0618f6](https://proxy.golang.org/github.com/olekukonko/cat/@v/v0.0.0-20250911104152-50322a0618f6.zip) | `h1:zrbMGy9YXpIeTnGj4EljqMiZsIcE09mmF8XsD5AYOJc=` | [G057](#go-legal-text-g057) |
| `github.com/olekukonko/errors` | [v1.3.0](https://proxy.golang.org/github.com/olekukonko/errors/@v/v1.3.0.zip) | `h1:teJvgLGUEqMzBUms+Dj3/3szNqCG/Jdw9iDbum8fR6U=` | [G057](#go-legal-text-g057) |
| `github.com/olekukonko/ll` | [v0.1.8](https://proxy.golang.org/github.com/olekukonko/ll/@v/v0.1.8.zip) | `h1:ysHCJRGHYKzmBSdz9w5AySztx7lG8SQY+naTGYUbsz8=` | [G057](#go-legal-text-g057) |
| `github.com/olekukonko/tablewriter` | [v1.1.4](https://proxy.golang.org/github.com/olekukonko/tablewriter/@v/v1.1.4.zip) | `h1:ORUMI3dXbMnRlRggJX3+q7OzQFDdvgbN9nVWj1drm6I=` | [G002](#go-legal-text-g002) |
| `github.com/openzipkin/zipkin-go` | [v0.4.3](https://proxy.golang.org/github.com/openzipkin/zipkin-go/@v/v0.4.3.zip) | `h1:9EGwpqkgnwdEIJ+Od7QVSEIH+ocmm5nPat0G7sjsSdg=` | [G090](#go-legal-text-g090) |
| `github.com/pelletier/go-toml/v2` | [v2.4.0](https://proxy.golang.org/github.com/pelletier/go-toml/v2/@v/v2.4.0.zip) | `h1:Mwu0mAkUKbittDs3/ADDWXqMmq3EOK2VHiuCkV00Row=` | [G027](#go-legal-text-g027) |
| `github.com/pierrec/lz4/v4` | [v4.1.27](https://proxy.golang.org/github.com/pierrec/lz4/v4/@v/v4.1.27.zip) | `h1:+PhzhWDrjRj89TH2sw43nE3+4+W8lSxIuQadEHZyjUk=` | [G055](#go-legal-text-g055) |
| `github.com/pkg/errors` | [v0.9.1](https://proxy.golang.org/github.com/pkg/errors/@v/v0.9.1.zip) | `h1:FEBLx1zS214owpjy7qsBeixbURkuhQAwrK5UwLGTwt4=` | [G070](#go-legal-text-g070) |
| `github.com/pmezard/go-difflib` | [v1.0.1-0.20181226105442-5d4384ee4fb2](https://proxy.golang.org/github.com/pmezard/go-difflib/@v/v1.0.1-0.20181226105442-5d4384ee4fb2.zip) | `h1:Jamvg5psRIccs7FGNTlIRMkT8wgtp5eCXdBlqhYGL6U=` | [G031](#go-legal-text-g031) |
| `github.com/prometheus/client_golang` | [v1.23.2](https://proxy.golang.org/github.com/prometheus/client_golang/@v/v1.23.2.zip) | `h1:Je96obch5RDVy3FDMndoUsjAhG5Edi49h0RJWRi/o0o=` | [G100](#go-legal-text-g100), [G065](#go-legal-text-g065), [G110](#go-legal-text-g110) |
| `github.com/prometheus/client_model` | [v0.6.2](https://proxy.golang.org/github.com/prometheus/client_model/@v/v0.6.2.zip) | `h1:oBsgwpGs7iVziMvrGhE53c/GrLUsZdHnqNwqPLxwZyk=` | [G100](#go-legal-text-g100), [G058](#go-legal-text-g058) |
| `github.com/prometheus/common` | [v0.69.0](https://proxy.golang.org/github.com/prometheus/common/@v/v0.69.0.zip) | `h1:OA85nJQS/T/MaYh/Q2CcgDKSGWqNIgrBDvDH85CuiNk=` | [G100](#go-legal-text-g100), [G049](#go-legal-text-g049) |
| `github.com/prometheus/procfs` | [v0.20.1](https://proxy.golang.org/github.com/prometheus/procfs/@v/v0.20.1.zip) | `h1:XwbrGOIplXW/AU3YhIhLODXMJYyC1isLFfYCsTEycfc=` | [G100](#go-legal-text-g100), [G039](#go-legal-text-g039) |
| `github.com/quic-go/qpack` | [v0.6.0](https://proxy.golang.org/github.com/quic-go/qpack/@v/v0.6.0.zip) | `h1:g7W+BMYynC1LbYLSqRt8PBg5Tgwxn214ZZR34VIOjz8=` | [G018](#go-legal-text-g018) |
| `github.com/quic-go/quic-go` | [v0.60.0](https://proxy.golang.org/github.com/quic-go/quic-go/@v/v0.60.0.zip) | `h1:xcQioE8OM66UQLeUMHltK1CCcOu3JbVB4JAQdDQSB+0=` | [G060](#go-legal-text-g060), [G028](#go-legal-text-g028) |
| `github.com/rabbitmq/amqp091-go` | [v1.12.0](https://proxy.golang.org/github.com/rabbitmq/amqp091-go/@v/v1.12.0.zip) | `h1:V0v14Iqfs+MwHWihJt/nGS5Ulu0vw572b2Co3mwunkI=` | [G032](#go-legal-text-g032) |
| `github.com/redis/go-redis/extra/rediscmd/v9` | [v9.20.1](https://proxy.golang.org/github.com/redis/go-redis/extra/rediscmd/v9/@v/v9.20.1.zip) | `h1:NK7vIlrbkz4xM01gzUe+SruDvAb6Brx/PdirNJPjIg4=` | [G078](#go-legal-text-g078) |
| `github.com/redis/go-redis/extra/redisotel/v9` | [v9.20.1](https://proxy.golang.org/github.com/redis/go-redis/extra/redisotel/v9/@v/v9.20.1.zip) | `h1:x1zIEhw3jvBHPcPrXLfrp3pGHLHAX4er6sjw7GsbkZk=` | [G078](#go-legal-text-g078) |
| `github.com/redis/go-redis/extra/redisprometheus/v9` | [v9.20.1](https://proxy.golang.org/github.com/redis/go-redis/extra/redisprometheus/v9/@v/v9.20.1.zip) | `h1:tEjdoUdIJfkXN2lC+Xyg/d/45RWpkSs9KlSaVC04yF0=` | [G078](#go-legal-text-g078) |
| `github.com/redis/go-redis/v9` | [v9.20.1](https://proxy.golang.org/github.com/redis/go-redis/v9/@v/v9.20.1.zip) | `h1:sfCU6A8P3dXbKyWes02uxA2baehGux9dZHfEKtsTB1w=` | [G078](#go-legal-text-g078) |
| `github.com/roadrunner-server/amqp/v5` | [v5.2.3](https://proxy.golang.org/github.com/roadrunner-server/amqp/v5/@v/v5.2.3.zip) | `h1:joOO8csUbv9IN2Q2E+dqlaMOkwi7fjmul9wNeG0AHHc=` | [G095](#go-legal-text-g095) |
| `github.com/roadrunner-server/api/v4` | [v4.24.0](https://proxy.golang.org/github.com/roadrunner-server/api/v4/@v/v4.24.0.zip) | `h1:99lN8nu7aD76d1fru4+MZkf9m8+YJ22Jy+qVoDwn4OY=` | [G047](#go-legal-text-g047) |
| `github.com/roadrunner-server/app-logger/v5` | [v5.1.9](https://proxy.golang.org/github.com/roadrunner-server/app-logger/v5/@v/v5.1.9.zip) | `h1:CNSBqdVli60d0n8LneqVyea52z1/esLpv5A0J6K1U8E=` | [G093](#go-legal-text-g093) |
| `github.com/roadrunner-server/beanstalk/v5` | [v5.1.9](https://proxy.golang.org/github.com/roadrunner-server/beanstalk/v5/@v/v5.1.9.zip) | `h1:PHY/K1oQVxFBswTu0B3KIj8xy4nEYbkumcrmvhv6orI=` | [G095](#go-legal-text-g095) |
| `github.com/roadrunner-server/boltdb/v5` | [v5.1.9](https://proxy.golang.org/github.com/roadrunner-server/boltdb/v5/@v/v5.1.9.zip) | `h1:UWpxBrY0SECbNl3jG7ZmNXSMGe5OQaKMKZvHYHkz+VE=` | [G047](#go-legal-text-g047) |
| `github.com/roadrunner-server/centrifuge/v5` | [v5.1.9](https://proxy.golang.org/github.com/roadrunner-server/centrifuge/v5/@v/v5.1.9.zip) | `h1:rkXRR6JFAUTHMQhqS0RiLciQDhF55VwAoN5SK7JaBqY=` | [G093](#go-legal-text-g093) |
| `github.com/roadrunner-server/config/v5` | [v5.1.9](https://proxy.golang.org/github.com/roadrunner-server/config/v5/@v/v5.1.9.zip) | `h1:ReWwts/prEvuC4yVJ0BRDmY5sxw/1c+hGTSdJ71hIQU=` | [G047](#go-legal-text-g047) |
| `github.com/roadrunner-server/context` | [v1.3.0](https://proxy.golang.org/github.com/roadrunner-server/context/@v/v1.3.0.zip) | `h1:iyTXVORhPU2/26z7kdzEaggwG5P8yhIKUDLiePjylFQ=` | [G054](#go-legal-text-g054) |
| `github.com/roadrunner-server/endure/v2` | [v2.6.2](https://proxy.golang.org/github.com/roadrunner-server/endure/v2/@v/v2.6.2.zip) | `h1:sIB4kTyE7gtT3fDhuYWUYn6Vt/dcPtiA6FoNS1eS+84=` | [G083](#go-legal-text-g083) |
| `github.com/roadrunner-server/errors` | [v1.5.0](https://proxy.golang.org/github.com/roadrunner-server/errors/@v/v1.5.0.zip) | `h1:unG7LKIZrSzkCCF3YLRLA5VyqE0KKomofXVJUXJe00g=` | [G061](#go-legal-text-g061) |
| `github.com/roadrunner-server/events` | [v1.0.1](https://proxy.golang.org/github.com/roadrunner-server/events/@v/v1.0.1.zip) | `h1:waCkKhxhzdK3VcI1xG22l+h+0J+Nfdpxjhyy01Un+kI=` | [G054](#go-legal-text-g054) |
| `github.com/roadrunner-server/fileserver/v5` | [v5.1.9](https://proxy.golang.org/github.com/roadrunner-server/fileserver/v5/@v/v5.1.9.zip) | `h1:IcEYqlB+jtyb1ZLnId3j4LyOKvifEbh2MJY6oiAGn3o=` | [G047](#go-legal-text-g047) |
| `github.com/roadrunner-server/google-pub-sub/v5` | [v5.1.9](https://proxy.golang.org/github.com/roadrunner-server/google-pub-sub/v5/@v/v5.1.9.zip) | `h1:D8s0JiTPP11HKaPKtcQ6R6+Y/rFJ83KFP4utbDOdgsE=` | [G107](#go-legal-text-g107) |
| `github.com/roadrunner-server/goridge/v3` | [v3.8.3](https://proxy.golang.org/github.com/roadrunner-server/goridge/v3/@v/v3.8.3.zip) | `h1:XmjrOFnI6ZbQTPaP39DEk8KwLUNTgjluK3pcZaW6ixQ=` | [G043](#go-legal-text-g043) |
| `github.com/roadrunner-server/grpc/v5` | [v5.3.0](https://proxy.golang.org/github.com/roadrunner-server/grpc/v5/@v/v5.3.0.zip) | `h1:DZj7b6vU+1bo5oAAUil75JUuovAwZzXh41ZS7rUE6xQ=` | [G047](#go-legal-text-g047) |
| `github.com/roadrunner-server/gzip/v5` | [v5.3.0](https://proxy.golang.org/github.com/roadrunner-server/gzip/v5/@v/v5.3.0.zip) | `h1:l6H4NQmX2RaaTWkeEt0q39ITLcMKL8FmBXm3ENUE4oI=` | [G047](#go-legal-text-g047) |
| `github.com/roadrunner-server/headers/v5` | [v5.2.0](https://proxy.golang.org/github.com/roadrunner-server/headers/v5/@v/v5.2.0.zip) | `h1:Q8SYxr+zhj5VskDPKq3JYyZA3XqcGxtYcb5uh4FytFY=` | [G047](#go-legal-text-g047) |
| `github.com/roadrunner-server/http/v5` | [v5.3.0](https://proxy.golang.org/github.com/roadrunner-server/http/v5/@v/v5.3.0.zip) | `h1:gsNdOQffWF3/hL7T+OTQat36SvCl5ZUjuotz41rONQ4=` | [G095](#go-legal-text-g095) |
| `github.com/roadrunner-server/informer/v5` | [v5.1.9](https://proxy.golang.org/github.com/roadrunner-server/informer/v5/@v/v5.1.9.zip) | `h1:yl334LMqUoWXfeP4299HgY9G7mq6kX6FVCSwT+cYdfQ=` | [G095](#go-legal-text-g095) |
| `github.com/roadrunner-server/jobs/v5` | [v5.1.9](https://proxy.golang.org/github.com/roadrunner-server/jobs/v5/@v/v5.1.9.zip) | `h1:28biT9tGTYSXV+FWJCLfEOCV3sJZ7VVd46oJLhIfJkM=` | [G095](#go-legal-text-g095) |
| `github.com/roadrunner-server/kafka/v5` | [v5.2.5](https://proxy.golang.org/github.com/roadrunner-server/kafka/v5/@v/v5.2.5.zip) | `h1:Abqb9AnFsx5VYsJqP5YwloWHaBbdbGrhX8NkWJk6s74=` | [G093](#go-legal-text-g093) |
| `github.com/roadrunner-server/kv/v5` | [v5.2.9](https://proxy.golang.org/github.com/roadrunner-server/kv/v5/@v/v5.2.9.zip) | `h1:vVUwmt2lxvdf6pILvfOFj57htFBedgQT8jnnwmI7pKU=` | [G047](#go-legal-text-g047) |
| `github.com/roadrunner-server/lock/v5` | [v5.1.9](https://proxy.golang.org/github.com/roadrunner-server/lock/v5/@v/v5.1.9.zip) | `h1:4juUwNvvS13mVnu131sHlgGRjuS5V0cfPPL+7fT6yt8=` | [G107](#go-legal-text-g107) |
| `github.com/roadrunner-server/logger/v5` | [v5.1.9](https://proxy.golang.org/github.com/roadrunner-server/logger/v5/@v/v5.1.9.zip) | `h1:3Kn+NYXF7Ww5LvkwMZwkv1q99t5qusIPBsreRJL08JI=` | [G047](#go-legal-text-g047) |
| `github.com/roadrunner-server/memcached/v5` | [v5.1.9](https://proxy.golang.org/github.com/roadrunner-server/memcached/v5/@v/v5.1.9.zip) | `h1:Pom9dZuywQK6ayWQYHbO7STar08/WeBl5WWckUFP51U=` | [G047](#go-legal-text-g047) |
| `github.com/roadrunner-server/memory/v5` | [v5.2.9](https://proxy.golang.org/github.com/roadrunner-server/memory/v5/@v/v5.2.9.zip) | `h1:niLep2dyUaYhrn+I12kTXjGUTUSbVJa+jyvNJrbt/hw=` | [G095](#go-legal-text-g095) |
| `github.com/roadrunner-server/metrics/v5` | [v5.2.0](https://proxy.golang.org/github.com/roadrunner-server/metrics/v5/@v/v5.2.0.zip) | `h1:54MWLO9t0tgx2It3sEx/Zz7rFwpYk6g9rjvApFBXTUQ=` | [G047](#go-legal-text-g047) |
| `github.com/roadrunner-server/nats/v5` | [v5.1.9](https://proxy.golang.org/github.com/roadrunner-server/nats/v5/@v/v5.1.9.zip) | `h1:ZWXMSt+za709hIK6jUBYv6X92REk1DgsYa6zU0jbeKo=` | [G047](#go-legal-text-g047) |
| `github.com/roadrunner-server/otel/v5` | [v5.6.0](https://proxy.golang.org/github.com/roadrunner-server/otel/v5/@v/v5.6.0.zip) | `h1:LtOEOhGE+XKDETTdz5Kz+j+eG6A9TXGd4E/7Vmdi2Rc=` | [G093](#go-legal-text-g093) |
| `github.com/roadrunner-server/pool` | [v1.1.3](https://proxy.golang.org/github.com/roadrunner-server/pool/@v/v1.1.3.zip) | `h1:KMsiL6yuYBWGk73bdO0akwP+fJ63bxDF972JukCGsxI=` | [G075](#go-legal-text-g075) |
| `github.com/roadrunner-server/priority_queue` | [v1.0.6](https://proxy.golang.org/github.com/roadrunner-server/priority_queue/@v/v1.0.6.zip) | `h1:x8bcMyjWs2Z4ySbO9BTP8Dzy2prCuazJY9HHrVTmUVY=` | [G054](#go-legal-text-g054) |
| `github.com/roadrunner-server/prometheus/v5` | [v5.2.0](https://proxy.golang.org/github.com/roadrunner-server/prometheus/v5/@v/v5.2.0.zip) | `h1:1KOacYOJL6ZN4vCf+SZIREFIqaYZ8WgWOiZifS88gns=` | [G047](#go-legal-text-g047) |
| `github.com/roadrunner-server/proxy_ip_parser/v5` | [v5.1.9](https://proxy.golang.org/github.com/roadrunner-server/proxy_ip_parser/v5/@v/v5.1.9.zip) | `h1:GoEgKJ97jozcJRecBMa5orMDWByaCwXUqg2zSUXl2M8=` | [G093](#go-legal-text-g093) |
| `github.com/roadrunner-server/redis/v5` | [v5.1.10](https://proxy.golang.org/github.com/roadrunner-server/redis/v5/@v/v5.1.10.zip) | `h1:DLjkYAqmX/08+X8NLI8oAsAE7/uDs1Kzpk5bOde5V6M=` | [G047](#go-legal-text-g047) |
| `github.com/roadrunner-server/resetter/v5` | [v5.1.9](https://proxy.golang.org/github.com/roadrunner-server/resetter/v5/@v/v5.1.9.zip) | `h1:rH1nxkgvItbMEp1/JFZqcijOkkav4zo0E4wcVXBcXa8=` | [G047](#go-legal-text-g047) |
| `github.com/roadrunner-server/rpc/v5` | [v5.1.9](https://proxy.golang.org/github.com/roadrunner-server/rpc/v5/@v/v5.1.9.zip) | `h1:AbRd2xEkWY8N3J4GUhoDeL+pnfwzKHazV4k40jZ+YDk=` | [G047](#go-legal-text-g047) |
| `github.com/roadrunner-server/send/v5` | [v5.2.0](https://proxy.golang.org/github.com/roadrunner-server/send/v5/@v/v5.2.0.zip) | `h1:nQMaO7u9z+wYD1bInF/6ctImEaCJZFHscd3cjC1zNOc=` | [G095](#go-legal-text-g095) |
| `github.com/roadrunner-server/server/v5` | [v5.2.10](https://proxy.golang.org/github.com/roadrunner-server/server/v5/@v/v5.2.10.zip) | `h1:IshR3mlXJ/fh+zY17tN3Q7GgaanMzA7H9wwEz4PxCSA=` | [G047](#go-legal-text-g047) |
| `github.com/roadrunner-server/service/v5` | [v5.1.9](https://proxy.golang.org/github.com/roadrunner-server/service/v5/@v/v5.1.9.zip) | `h1:0vVPSflWjAn2afj0QKs8vB1KIi1QQxKWiXWeZxxU15w=` | [G047](#go-legal-text-g047) |
| `github.com/roadrunner-server/sqs/v5` | [v5.1.9](https://proxy.golang.org/github.com/roadrunner-server/sqs/v5/@v/v5.1.9.zip) | `h1:ZmanM+52tt8cn7AkV/0Zp+9wUwpNjbGkd7riIf4t3ZA=` | [G047](#go-legal-text-g047) |
| `github.com/roadrunner-server/static/v5` | [v5.2.0](https://proxy.golang.org/github.com/roadrunner-server/static/v5/@v/v5.2.0.zip) | `h1:xpZgIlvKWdWUJ37BtbNLtL/kN1rNFMEwFZbBnzWDeEM=` | [G095](#go-legal-text-g095) |
| `github.com/roadrunner-server/status/v5` | [v5.1.9](https://proxy.golang.org/github.com/roadrunner-server/status/v5/@v/v5.1.9.zip) | `h1:TxDCj88Y7Bzcwbv8zwvNV1dE25n2x7IYw5M+kqadZWk=` | [G047](#go-legal-text-g047) |
| `github.com/roadrunner-server/tcp/v5` | [v5.1.9](https://proxy.golang.org/github.com/roadrunner-server/tcp/v5/@v/v5.1.9.zip) | `h1:Tnyjib87qWXvMdb5r/aMvwxz3t87o0bE8bsVg/lIOk0=` | [G095](#go-legal-text-g095) |
| `github.com/roadrunner-server/tcplisten` | [v1.5.2](https://proxy.golang.org/github.com/roadrunner-server/tcplisten/@v/v1.5.2.zip) | `h1:nn8yXYrhRDkfQ9AAu4V075uT4fZRmOnpxkawgE+bWPA=` | [G001](#go-legal-text-g001) |
| `github.com/robfig/cron` | [v1.2.0](https://proxy.golang.org/github.com/robfig/cron/@v/v1.2.0.zip) | `h1:ZjScXvvxeQ63Dbyxy76Fj3AT3Ut0aKsyd2/tl3DTMuQ=` | [G072](#go-legal-text-g072) |
| `github.com/rs/cors` | [v1.11.1](https://proxy.golang.org/github.com/rs/cors/@v/v1.11.1.zip) | `h1:eU3gRzXLRK57F5rKMGMZURNdIG4EoAmX8k94r9wXWHA=` | [G096](#go-legal-text-g096) |
| `github.com/sagikazarmark/locafero` | [v0.12.0](https://proxy.golang.org/github.com/sagikazarmark/locafero/@v/v0.12.0.zip) | `h1:/NQhBAkUb4+fH1jivKHWusDYFjMOOKU88eegjfxfHb4=` | [G098](#go-legal-text-g098) |
| `github.com/shirou/gopsutil` | [v3.21.11+incompatible](https://proxy.golang.org/github.com/shirou/gopsutil/@v/v3.21.11+incompatible.zip) | `h1:+1+c1VGhc88SSonWP6foOcLhvnKlUeu/erjjvaPEYiI=` | [G071](#go-legal-text-g071) |
| `github.com/spf13/afero` | [v1.15.0](https://proxy.golang.org/github.com/spf13/afero/@v/v1.15.0.zip) | `h1:b/YBCLWAJdFWJTN9cLhiXXcD7mzKn9Dm86dNnfyQw1I=` | [G048](#go-legal-text-g048) |
| `github.com/spf13/cast` | [v1.10.0](https://proxy.golang.org/github.com/spf13/cast/@v/v1.10.0.zip) | `h1:h2x0u2shc1QuLHfxi+cTJvs30+ZAHOGRic8uyGTDWxY=` | [G126](#go-legal-text-g126) |
| `github.com/spf13/cobra` | [v1.10.2](https://proxy.golang.org/github.com/spf13/cobra/@v/v1.10.2.zip) | `h1:DMTTonx5m65Ic0GOoRY2c16WCbHxOOw6xxezuLaBpcU=` | [G048](#go-legal-text-g048) |
| `github.com/spf13/pflag` | [v1.0.10](https://proxy.golang.org/github.com/spf13/pflag/@v/v1.0.10.zip) | `h1:4EBh2KAYBwaONj6b2Ye1GiHfwjqyROoF4RwYO+vPwFk=` | [G091](#go-legal-text-g091) |
| `github.com/spf13/viper` | [v1.21.0](https://proxy.golang.org/github.com/spf13/viper/@v/v1.21.0.zip) | `h1:x5S+0EU27Lbphp4UKm1C+1oQO+rKx36vfCoaVebLFSU=` | [G126](#go-legal-text-g126) |
| `github.com/stretchr/objx` | [v0.5.3](https://proxy.golang.org/github.com/stretchr/objx/@v/v0.5.3.zip) | `h1:jmXUvGomnU1o3W/V5h2VEradbpJDwGrzugQQvL0POH4=` | [G087](#go-legal-text-g087) |
| `github.com/stretchr/testify` | [v1.11.1](https://proxy.golang.org/github.com/stretchr/testify/@v/v1.11.1.zip) | `h1:7s2iGBzp5EwR7/aIZr8ao5+dra3wiQyKjjFuvgVKu7U=` | [G121](#go-legal-text-g121) |
| `github.com/subosito/gotenv` | [v1.6.0](https://proxy.golang.org/github.com/subosito/gotenv/@v/v1.6.0.zip) | `h1:9NlTDc1FTs4qu0DDq7AEtTPNw6SVm7uBMsUCUjABIf8=` | [G021](#go-legal-text-g021) |
| `github.com/temporalio/roadrunner-temporal/v5` | [v5.11.0](https://proxy.golang.org/github.com/temporalio/roadrunner-temporal/v5/@v/v5.11.0.zip) | `h1:0pIf4XUAl+v1OjT/q5FSF6R4V/OOHK5gpUCZhLbgZ8Y=` | [G086](#go-legal-text-g086) |
| `github.com/tklauser/go-sysconf` | [v0.4.0](https://proxy.golang.org/github.com/tklauser/go-sysconf/@v/v0.4.0.zip) | `h1:7H0uAN+7RkwWRaxhYXDLqa5V3LPrJeV8wmD9dRUgPQU=` | [G125](#go-legal-text-g125) |
| `github.com/tklauser/numcpus` | [v0.12.0](https://proxy.golang.org/github.com/tklauser/numcpus/@v/v0.12.0.zip) | `h1:NR85qdvHA9pFse3x3weVZ0r0ST8R6l5RHbZrlRaqob4=` | [G101](#go-legal-text-g101) |
| `github.com/twmb/franz-go` | [v1.21.3](https://proxy.golang.org/github.com/twmb/franz-go/@v/v1.21.3.zip) | `h1:q9Mo8ri+OwBQBjKqrerNCqNWJlJnUDe2qnYsj2V3hdI=` | [G076](#go-legal-text-g076) |
| `github.com/twmb/franz-go/pkg/kmsg` | [v1.13.1](https://proxy.golang.org/github.com/twmb/franz-go/pkg/kmsg/@v/v1.13.1.zip) | `h1:fG5kItwysTk5UXqVwb64EpQEy3TydF3vYYK21nUQ+bI=` | [G076](#go-legal-text-g076) |
| `github.com/twmb/murmur3` | [v1.1.8](https://proxy.golang.org/github.com/twmb/murmur3/@v/v1.1.8.zip) | `h1:8Yt9taO/WN3l08xErzjeschgZU2QSrwm1kclYq+0aRg=` | [G036](#go-legal-text-g036) |
| `github.com/uber-go/tally/v4` | [v4.1.17](https://proxy.golang.org/github.com/uber-go/tally/v4/@v/v4.1.17.zip) | `h1:C+U4BKtVDXTszuzU+WH8JVQvRVnaVKxzZrROFyDrvS8=` | [G079](#go-legal-text-g079) |
| `github.com/valyala/bytebufferpool` | [v1.0.0](https://proxy.golang.org/github.com/valyala/bytebufferpool/@v/v1.0.0.zip) | `h1:GqA5TC/0021Y/b9FG4Oi9Mr3q7XYx6KllzawFIhcdPw=` | [G099](#go-legal-text-g099) |
| `github.com/valyala/fasthttp` | [v1.71.0](https://proxy.golang.org/github.com/valyala/fasthttp/@v/v1.71.0.zip) | `h1:tepR7H+Guh9VUqxxcPggYi8R3lGUu2Rsdh+z7/FCY3k=` | [G068](#go-legal-text-g068), [G089](#go-legal-text-g089) |
| `github.com/vmihailenco/msgpack/v5` | [v5.4.1](https://proxy.golang.org/github.com/vmihailenco/msgpack/v5/@v/v5.4.1.zip) | `h1:cQriyiUvjTwOHg8QZaPihLWeRAAVoCpE00IUPn0Bjt8=` | [G016](#go-legal-text-g016) |
| `github.com/vmihailenco/tagparser/v2` | [v2.0.0](https://proxy.golang.org/github.com/vmihailenco/tagparser/v2/@v/v2.0.0.zip) | `h1:y09buUbR+b5aycVFQs/g70pqKVZNBmxwAhO7/IwNM9g=` | [G012](#go-legal-text-g012) |
| `github.com/zeebo/blake3` | [v0.2.4](https://proxy.golang.org/github.com/zeebo/blake3/@v/v0.2.4.zip) | `h1:KYQPkhpRtcqh0ssGYcKLG1JYvddkEA8QwCM/yBqhaZI=` | [G003](#go-legal-text-g003) |
| `go.etcd.io/bbolt` | [v1.4.3](https://proxy.golang.org/go.etcd.io/bbolt/@v/v1.4.3.zip) | `h1:dEadXpI6G79deX5prL3QRNP6JB8UxVkqo4UPnHaNXJo=` | [G094](#go-legal-text-g094) |
| `go.opencensus.io` | [v0.24.0](https://proxy.golang.org/go.opencensus.io/@v/v0.24.0.zip) | `h1:y73uSU6J157QMP2kn2r30vwW1A2W2WFwSCGnAVxeaD0=` | [G042](#go-legal-text-g042) |
| `go.opentelemetry.io/auto/sdk` | [v1.2.1](https://proxy.golang.org/go.opentelemetry.io/auto/sdk/@v/v1.2.1.zip) | `h1:jXsnJ4Lmnqd11kwkBV2LgLoFMZKizbCi5fNZ/ipaZ64=` | [G100](#go-legal-text-g100) |
| `go.opentelemetry.io/contrib/instrumentation/github.com/bradfitz/gomemcache/memcache/otelmemcache` | [v0.43.0](https://proxy.golang.org/go.opentelemetry.io/contrib/instrumentation/github.com/bradfitz/gomemcache/memcache/otelmemcache/@v/v0.43.0.zip) | `h1:UBMZi0bClix43Z5bGClUstfycTv5/GwGEpeXrkVCILw=` | [G100](#go-legal-text-g100) |
| `go.opentelemetry.io/contrib/instrumentation/google.golang.org/grpc/otelgrpc` | [v0.69.0](https://proxy.golang.org/go.opentelemetry.io/contrib/instrumentation/google.golang.org/grpc/otelgrpc/@v/v0.69.0.zip) | `h1:2yEATaop1/a1I4psnSLgWVPLWwCzkqWakgJy7xTDVy0=` | [G017](#go-legal-text-g017) |
| `go.opentelemetry.io/contrib/instrumentation/net/http/otelhttp` | [v0.69.0](https://proxy.golang.org/go.opentelemetry.io/contrib/instrumentation/net/http/otelhttp/@v/v0.69.0.zip) | `h1:8tvICD4vSTOOsNrsI4Ljf6C+6UKvpTEH5XY3JMoyPoo=` | [G017](#go-legal-text-g017) |
| `go.opentelemetry.io/contrib/propagators/jaeger` | [v1.44.0](https://proxy.golang.org/go.opentelemetry.io/contrib/propagators/jaeger/@v/v1.44.0.zip) | `h1:OyzvsAMc/zHt0DRPcfstn0wgfq8ApDkeY0ABMcueweM=` | [G017](#go-legal-text-g017) |
| `go.opentelemetry.io/otel` | [v1.44.0](https://proxy.golang.org/go.opentelemetry.io/otel/@v/v1.44.0.zip) | `h1:JjwHmHpA4iZ3wBxluu2fbbE7j4kqlE8jXyAyPXH7HqU=` | [G017](#go-legal-text-g017) |
| `go.opentelemetry.io/otel/exporters/otlp/otlptrace` | [v1.44.0](https://proxy.golang.org/go.opentelemetry.io/otel/exporters/otlp/otlptrace/@v/v1.44.0.zip) | `h1:4YsVu3B8+3qtWYYrsUYgn0OG78pN0rnNPRGX4SbokQI=` | [G017](#go-legal-text-g017) |
| `go.opentelemetry.io/otel/exporters/otlp/otlptrace/otlptracegrpc` | [v1.44.0](https://proxy.golang.org/go.opentelemetry.io/otel/exporters/otlp/otlptrace/otlptracegrpc/@v/v1.44.0.zip) | `h1:qazEJlUOQzhCpzQpFETGby7EdqjI1wsd0W+6Gg1SCTU=` | [G017](#go-legal-text-g017) |
| `go.opentelemetry.io/otel/exporters/otlp/otlptrace/otlptracehttp` | [v1.44.0](https://proxy.golang.org/go.opentelemetry.io/otel/exporters/otlp/otlptrace/otlptracehttp/@v/v1.44.0.zip) | `h1:lgh3PiVrRUWMLOVSkQicxzZll5NjF1r+AtsX1XRIHw0=` | [G017](#go-legal-text-g017) |
| `go.opentelemetry.io/otel/exporters/stdout/stdouttrace` | [v1.44.0](https://proxy.golang.org/go.opentelemetry.io/otel/exporters/stdout/stdouttrace/@v/v1.44.0.zip) | `h1:bl2S7Ubua0Nms+D/gAmznQTd4dxxMA93aKbcpKqiTCs=` | [G017](#go-legal-text-g017) |
| `go.opentelemetry.io/otel/exporters/zipkin` | [v1.44.0](https://proxy.golang.org/go.opentelemetry.io/otel/exporters/zipkin/@v/v1.44.0.zip) | `h1:zv7PRYGLrQHkdeZj0c5SNAZOJcw55XgaTezUkNpwA+w=` | [G017](#go-legal-text-g017) |
| `go.opentelemetry.io/otel/metric` | [v1.44.0](https://proxy.golang.org/go.opentelemetry.io/otel/metric/@v/v1.44.0.zip) | `h1:1w0gILTcHdr3YI+ixLyjemwrVnsMURbTZFrSYCdDdmc=` | [G017](#go-legal-text-g017) |
| `go.opentelemetry.io/otel/sdk` | [v1.44.0](https://proxy.golang.org/go.opentelemetry.io/otel/sdk/@v/v1.44.0.zip) | `h1:nHYwb9lK+fJPU/dnT6s7W7Z8itMWyqrnVfbheVYrZ58=` | [G017](#go-legal-text-g017) |
| `go.opentelemetry.io/otel/trace` | [v1.44.0](https://proxy.golang.org/go.opentelemetry.io/otel/trace/@v/v1.44.0.zip) | `h1:jxF5CsGYCe74MCRx2X4g7WsY/VBKRqqpNvXlX/6gtIk=` | [G017](#go-legal-text-g017) |
| `go.opentelemetry.io/proto/otlp` | [v1.10.0](https://proxy.golang.org/go.opentelemetry.io/proto/otlp/@v/v1.10.0.zip) | `h1:IQRWgT5srOCYfiWnpqUYz9CVmbO8bFmKcwYxpuCSL2g=` | [G100](#go-legal-text-g100) |
| `go.temporal.io/api` | [v1.62.14](https://proxy.golang.org/go.temporal.io/api/@v/v1.62.14.zip) | `h1:Tree3eqoKRt5Vv+nvYHMPp/ROiGmSOTtFUD9d0w8yJE=` | [G109](#go-legal-text-g109), [G038](#go-legal-text-g038) |
| `go.temporal.io/sdk` | [v1.44.1](https://proxy.golang.org/go.temporal.io/sdk/@v/v1.44.1.zip) | `h1:Mt2OZLZpqkzDIdg9YyQzO0Rb/HqCDnnqHlIAGAJ5gqM=` | [G113](#go-legal-text-g113) |
| `go.temporal.io/sdk/contrib/opentelemetry` | [v0.7.0](https://proxy.golang.org/go.temporal.io/sdk/contrib/opentelemetry/@v/v0.7.0.zip) | `h1:GSna1HP+1ibNXZ9xlVdQU2zFVqdt5VcdF0dzpeaYccQ=` | [G113](#go-legal-text-g113) |
| `go.temporal.io/sdk/contrib/tally` | [v0.2.0](https://proxy.golang.org/go.temporal.io/sdk/contrib/tally/@v/v0.2.0.zip) | `h1:XnTJIQcjOv+WuCJ1u8Ve2nq+s2H4i/fys34MnWDRrOo=` | [G113](#go-legal-text-g113) |
| `go.temporal.io/server` | [v1.31.1](https://proxy.golang.org/go.temporal.io/server/@v/v1.31.1.zip) | `h1:3rxA0Ls21hLOseKsyzm7e+IrVLuaDsuri0DGX+chIqU=` | [G056](#go-legal-text-g056), [G122](#go-legal-text-g122), [G063](#go-legal-text-g063), [G080](#go-legal-text-g080) |
| `go.uber.org/atomic` | [v1.11.0](https://proxy.golang.org/go.uber.org/atomic/@v/v1.11.0.zip) | `h1:ZvwS0R+56ePWxUNi+Atn9dWONBPp/AUETXlHW0DxSjE=` | [G115](#go-legal-text-g115) |
| `go.uber.org/multierr` | [v1.11.0](https://proxy.golang.org/go.uber.org/multierr/@v/v1.11.0.zip) | `h1:blXXJkSxSSfBVBlC76pxqeO+LN3aDfLQo+309xJstO0=` | [G106](#go-legal-text-g106) |
| `go.uber.org/zap` | [v1.28.0](https://proxy.golang.org/go.uber.org/zap/@v/v1.28.0.zip) | `h1:IZzaP1Fv73/T/pBMLk4VutPl36uNC+OSUh3JLG3FIjo=` | [G097](#go-legal-text-g097) |
| `go.uber.org/zap/exp` | [v0.3.0](https://proxy.golang.org/go.uber.org/zap/exp/@v/v0.3.0.zip) | `h1:6JYzdifzYkGmTdRR59oYH+Ng7k49H9qVpWwNSsGJj3U=` | [G097](#go-legal-text-g097) |
| `go.yaml.in/yaml/v3` | [v3.0.4](https://proxy.golang.org/go.yaml.in/yaml/v3/@v/v3.0.4.zip) | `h1:tfq32ie2Jv2UxXFdLJdh3jXuOzWiL1fo0bu/FbuKpbc=` | [G102](#go-legal-text-g102), [G120](#go-legal-text-g120) |
| `golang.org/x/crypto` | [v0.53.0](https://proxy.golang.org/golang.org/x/crypto/@v/v0.53.0.zip) | `h1:QZ4Muo8THX6CizN2vPPd5fBGHyogrdK9fG4wLPFUsto=` | [G073](#go-legal-text-g073) |
| `golang.org/x/net` | [v0.56.0](https://proxy.golang.org/golang.org/x/net/@v/v0.56.0.zip) | `h1:Rw8j/hFzGvJUZwNBXnAtf5sVDVt+65SK2C7IxCxZt5o=` | [G073](#go-legal-text-g073) |
| `golang.org/x/oauth2` | [v0.36.0](https://proxy.golang.org/golang.org/x/oauth2/@v/v0.36.0.zip) | `h1:peZ/1z27fi9hUOFCAZaHyrpWG5lwe0RJEEEeH0ThlIs=` | [G073](#go-legal-text-g073) |
| `golang.org/x/sync` | [v0.21.0](https://proxy.golang.org/golang.org/x/sync/@v/v0.21.0.zip) | `h1:HLII4xRRTtCRkxYp4HNFF0Js/Og6q2i++KXbg0gHCwM=` | [G073](#go-legal-text-g073) |
| `golang.org/x/sys` | [v0.46.0](https://proxy.golang.org/golang.org/x/sys/@v/v0.46.0.zip) | `h1:noSf2Fq6F8DBgS+LysIkx7rIExoNHJsxOAtPp4rthXw=` | [G073](#go-legal-text-g073) |
| `golang.org/x/text` | [v0.38.0](https://proxy.golang.org/golang.org/x/text/@v/v0.38.0.zip) | `h1:sXmwo9DwP3OK9EZ7PqAdaooSGozfl/3a6/xJcbzPRhE=` | [G073](#go-legal-text-g073) |
| `golang.org/x/time` | [v0.15.0](https://proxy.golang.org/golang.org/x/time/@v/v0.15.0.zip) | `h1:bbrp8t3bGUeFOx08pvsMYRTCVSMk89u4tKbNOZbp88U=` | [G073](#go-legal-text-g073) |
| `google.golang.org/api` | [v0.285.0](https://proxy.golang.org/google.golang.org/api/@v/v0.285.0.zip) | `h1:B7eHHoKGAX/LrPkQvhQqnGwjgWxofbdGwCTQvpm8FkM=` | [G014](#go-legal-text-g014), [G123](#go-legal-text-g123) |
| `google.golang.org/genproto` | [v0.0.0-20260615183401-62b3387ff324](https://proxy.golang.org/google.golang.org/genproto/@v/v0.0.0-20260615183401-62b3387ff324.zip) | `h1:r7/+bt4yKglJiN8eUY8enbRjglCvFm1eh8ezYdYoKTM=` | [G101](#go-legal-text-g101) |
| `google.golang.org/genproto/googleapis/api` | [v0.0.0-20260615183401-62b3387ff324](https://proxy.golang.org/google.golang.org/genproto/googleapis/api/@v/v0.0.0-20260615183401-62b3387ff324.zip) | `h1:g0RAkxK/smSu/iRwC/KIX1mwUoVJtk2OjbgaeS4DmUM=` | [G101](#go-legal-text-g101) |
| `google.golang.org/genproto/googleapis/rpc` | [v0.0.0-20260615183401-62b3387ff324](https://proxy.golang.org/google.golang.org/genproto/googleapis/rpc/@v/v0.0.0-20260615183401-62b3387ff324.zip) | `h1:9HZDLIdYBJXAnaFOr9WHrKVycfpY+75s9HGadC0305A=` | [G101](#go-legal-text-g101) |
| `google.golang.org/grpc` | [v1.81.1](https://proxy.golang.org/google.golang.org/grpc/@v/v1.81.1.zip) | `h1:VnnIIZ88UzOOKLukQi+ImGz8O1Wdp8nAGGnvOfEIWQQ=` | [G101](#go-legal-text-g101), [G053](#go-legal-text-g053) |
| `google.golang.org/protobuf` | [v1.36.11](https://proxy.golang.org/google.golang.org/protobuf/@v/v1.36.11.zip) | `h1:fV6ZwhNocDyBLK0dj+fg8ektcVegBBuEolpbTQyBNVE=` | [G038](#go-legal-text-g038) |
| `gopkg.in/natefinch/lumberjack.v2` | [v2.2.1](https://proxy.golang.org/gopkg.in/natefinch/lumberjack.v2/@v/v2.2.1.zip) | `h1:bBRl1b0OH9s/DuPhuXpNl+VtCaJXFZ5/uEFST95x9zc=` | [G074](#go-legal-text-g074) |
| `gopkg.in/yaml.v3` | [v3.0.1](https://proxy.golang.org/gopkg.in/yaml.v3/@v/v3.0.1.zip) | `h1:fxVm/GzAzEWqLHuvctI91KS9hhNmmWOoWu0XTYJS7CA=` | [G102](#go-legal-text-g102), [G120](#go-legal-text-g120) |

<a id="go-legal-text-g101"></a>

### Go legal text G101

SHA-256: `cfc7749b96f63bd31c3c42b5c471bf756814053e847c10f3eb003417bc523d30`. Origins:

- `cloud.google.com/go` v0.123.0: [LICENSE](https://proxy.golang.org/cloud.google.com/go/@v/v0.123.0.zip)
- `cloud.google.com/go/auth` v0.20.0: [LICENSE](https://proxy.golang.org/cloud.google.com/go/auth/@v/v0.20.0.zip)
- `cloud.google.com/go/auth/oauth2adapt` v0.2.8: [LICENSE](https://proxy.golang.org/cloud.google.com/go/auth/oauth2adapt/@v/v0.2.8.zip)
- `cloud.google.com/go/compute/metadata` v0.9.0: [LICENSE](https://proxy.golang.org/cloud.google.com/go/compute/metadata/@v/v0.9.0.zip)
- `cloud.google.com/go/iam` v1.11.0: [LICENSE](https://proxy.golang.org/cloud.google.com/go/iam/@v/v1.11.0.zip)
- `cloud.google.com/go/pubsub/v2` v2.6.0: [LICENSE](https://proxy.golang.org/cloud.google.com/go/pubsub/v2/@v/v2.6.0.zip)
- `github.com/aws/aws-sdk-go-v2` v1.42.0: [LICENSE.txt](https://proxy.golang.org/github.com/aws/aws-sdk-go-v2/@v/v1.42.0.zip)
- `github.com/aws/aws-sdk-go-v2/config` v1.32.25: [LICENSE.txt](https://proxy.golang.org/github.com/aws/aws-sdk-go-v2/config/@v/v1.32.25.zip)
- `github.com/aws/aws-sdk-go-v2/credentials` v1.19.24: [LICENSE.txt](https://proxy.golang.org/github.com/aws/aws-sdk-go-v2/credentials/@v/v1.19.24.zip)
- `github.com/aws/aws-sdk-go-v2/feature/ec2/imds` v1.18.29: [LICENSE.txt](https://proxy.golang.org/github.com/aws/aws-sdk-go-v2/feature/ec2/imds/@v/v1.18.29.zip)
- `github.com/aws/aws-sdk-go-v2/internal/configsources` v1.4.29: [LICENSE.txt](https://proxy.golang.org/github.com/aws/aws-sdk-go-v2/internal/configsources/@v/v1.4.29.zip)
- `github.com/aws/aws-sdk-go-v2/internal/endpoints/v2` v2.7.29: [LICENSE.txt](https://proxy.golang.org/github.com/aws/aws-sdk-go-v2/internal/endpoints/v2/@v/v2.7.29.zip)
- `github.com/aws/aws-sdk-go-v2/internal/v4a` v1.4.30: [LICENSE.txt](https://proxy.golang.org/github.com/aws/aws-sdk-go-v2/internal/v4a/@v/v1.4.30.zip)
- `github.com/aws/aws-sdk-go-v2/service/internal/accept-encoding` v1.13.12: [LICENSE.txt](https://proxy.golang.org/github.com/aws/aws-sdk-go-v2/service/internal/accept-encoding/@v/v1.13.12.zip)
- `github.com/aws/aws-sdk-go-v2/service/internal/presigned-url` v1.13.29: [LICENSE.txt](https://proxy.golang.org/github.com/aws/aws-sdk-go-v2/service/internal/presigned-url/@v/v1.13.29.zip)
- `github.com/aws/aws-sdk-go-v2/service/signin` v1.2.0: [LICENSE.txt](https://proxy.golang.org/github.com/aws/aws-sdk-go-v2/service/signin/@v/v1.2.0.zip)
- `github.com/aws/aws-sdk-go-v2/service/sqs` v1.44.0: [LICENSE.txt](https://proxy.golang.org/github.com/aws/aws-sdk-go-v2/service/sqs/@v/v1.44.0.zip)
- `github.com/aws/aws-sdk-go-v2/service/sso` v1.31.3: [LICENSE.txt](https://proxy.golang.org/github.com/aws/aws-sdk-go-v2/service/sso/@v/v1.31.3.zip)
- `github.com/aws/aws-sdk-go-v2/service/ssooidc` v1.36.6: [LICENSE.txt](https://proxy.golang.org/github.com/aws/aws-sdk-go-v2/service/ssooidc/@v/v1.36.6.zip)
- `github.com/aws/aws-sdk-go-v2/service/sts` v1.43.3: [LICENSE.txt](https://proxy.golang.org/github.com/aws/aws-sdk-go-v2/service/sts/@v/v1.43.3.zip)
- `github.com/bradfitz/gomemcache` v0.0.0-20260422231931-4d751bb6e37c: [LICENSE](https://proxy.golang.org/github.com/bradfitz/gomemcache/@v/v0.0.0-20260422231931-4d751bb6e37c.zip)
- `github.com/golang/mock` v1.7.0-rc.1: [LICENSE](https://proxy.golang.org/github.com/golang/mock/@v/v1.7.0-rc.1.zip)
- `github.com/google/s2a-go` v0.1.9: [LICENSE.md](https://proxy.golang.org/github.com/google/s2a-go/@v/v0.1.9.zip)
- `github.com/googleapis/enterprise-certificate-proxy` v0.3.16: [LICENSE](https://proxy.golang.org/github.com/googleapis/enterprise-certificate-proxy/@v/v0.3.16.zip)
- `github.com/tklauser/numcpus` v0.12.0: [LICENSE](https://proxy.golang.org/github.com/tklauser/numcpus/@v/v0.12.0.zip)
- `google.golang.org/genproto` v0.0.0-20260615183401-62b3387ff324: [LICENSE](https://proxy.golang.org/google.golang.org/genproto/@v/v0.0.0-20260615183401-62b3387ff324.zip)
- `google.golang.org/genproto/googleapis/api` v0.0.0-20260615183401-62b3387ff324: [LICENSE](https://proxy.golang.org/google.golang.org/genproto/googleapis/api/@v/v0.0.0-20260615183401-62b3387ff324.zip)
- `google.golang.org/genproto/googleapis/rpc` v0.0.0-20260615183401-62b3387ff324: [LICENSE](https://proxy.golang.org/google.golang.org/genproto/googleapis/rpc/@v/v0.0.0-20260615183401-62b3387ff324.zip)
- `google.golang.org/grpc` v1.81.1: [LICENSE](https://proxy.golang.org/google.golang.org/grpc/@v/v1.81.1.zip)

```text

                                 Apache License
                           Version 2.0, January 2004
                        http://www.apache.org/licenses/

   TERMS AND CONDITIONS FOR USE, REPRODUCTION, AND DISTRIBUTION

   1. Definitions.

      "License" shall mean the terms and conditions for use, reproduction,
      and distribution as defined by Sections 1 through 9 of this document.

      "Licensor" shall mean the copyright owner or entity authorized by
      the copyright owner that is granting the License.

      "Legal Entity" shall mean the union of the acting entity and all
      other entities that control, are controlled by, or are under common
      control with that entity. For the purposes of this definition,
      "control" means (i) the power, direct or indirect, to cause the
      direction or management of such entity, whether by contract or
      otherwise, or (ii) ownership of fifty percent (50%) or more of the
      outstanding shares, or (iii) beneficial ownership of such entity.

      "You" (or "Your") shall mean an individual or Legal Entity
      exercising permissions granted by this License.

      "Source" form shall mean the preferred form for making modifications,
      including but not limited to software source code, documentation
      source, and configuration files.

      "Object" form shall mean any form resulting from mechanical
      transformation or translation of a Source form, including but
      not limited to compiled object code, generated documentation,
      and conversions to other media types.

      "Work" shall mean the work of authorship, whether in Source or
      Object form, made available under the License, as indicated by a
      copyright notice that is included in or attached to the work
      (an example is provided in the Appendix below).

      "Derivative Works" shall mean any work, whether in Source or Object
      form, that is based on (or derived from) the Work and for which the
      editorial revisions, annotations, elaborations, or other modifications
      represent, as a whole, an original work of authorship. For the purposes
      of this License, Derivative Works shall not include works that remain
      separable from, or merely link (or bind by name) to the interfaces of,
      the Work and Derivative Works thereof.

      "Contribution" shall mean any work of authorship, including
      the original version of the Work and any modifications or additions
      to that Work or Derivative Works thereof, that is intentionally
      submitted to Licensor for inclusion in the Work by the copyright owner
      or by an individual or Legal Entity authorized to submit on behalf of
      the copyright owner. For the purposes of this definition, "submitted"
      means any form of electronic, verbal, or written communication sent
      to the Licensor or its representatives, including but not limited to
      communication on electronic mailing lists, source code control systems,
      and issue tracking systems that are managed by, or on behalf of, the
      Licensor for the purpose of discussing and improving the Work, but
      excluding communication that is conspicuously marked or otherwise
      designated in writing by the copyright owner as "Not a Contribution."

      "Contributor" shall mean Licensor and any individual or Legal Entity
      on behalf of whom a Contribution has been received by Licensor and
      subsequently incorporated within the Work.

   2. Grant of Copyright License. Subject to the terms and conditions of
      this License, each Contributor hereby grants to You a perpetual,
      worldwide, non-exclusive, no-charge, royalty-free, irrevocable
      copyright license to reproduce, prepare Derivative Works of,
      publicly display, publicly perform, sublicense, and distribute the
      Work and such Derivative Works in Source or Object form.

   3. Grant of Patent License. Subject to the terms and conditions of
      this License, each Contributor hereby grants to You a perpetual,
      worldwide, non-exclusive, no-charge, royalty-free, irrevocable
      (except as stated in this section) patent license to make, have made,
      use, offer to sell, sell, import, and otherwise transfer the Work,
      where such license applies only to those patent claims licensable
      by such Contributor that are necessarily infringed by their
      Contribution(s) alone or by combination of their Contribution(s)
      with the Work to which such Contribution(s) was submitted. If You
      institute patent litigation against any entity (including a
      cross-claim or counterclaim in a lawsuit) alleging that the Work
      or a Contribution incorporated within the Work constitutes direct
      or contributory patent infringement, then any patent licenses
      granted to You under this License for that Work shall terminate
      as of the date such litigation is filed.

   4. Redistribution. You may reproduce and distribute copies of the
      Work or Derivative Works thereof in any medium, with or without
      modifications, and in Source or Object form, provided that You
      meet the following conditions:

      (a) You must give any other recipients of the Work or
          Derivative Works a copy of this License; and

      (b) You must cause any modified files to carry prominent notices
          stating that You changed the files; and

      (c) You must retain, in the Source form of any Derivative Works
          that You distribute, all copyright, patent, trademark, and
          attribution notices from the Source form of the Work,
          excluding those notices that do not pertain to any part of
          the Derivative Works; and

      (d) If the Work includes a "NOTICE" text file as part of its
          distribution, then any Derivative Works that You distribute must
          include a readable copy of the attribution notices contained
          within such NOTICE file, excluding those notices that do not
          pertain to any part of the Derivative Works, in at least one
          of the following places: within a NOTICE text file distributed
          as part of the Derivative Works; within the Source form or
          documentation, if provided along with the Derivative Works; or,
          within a display generated by the Derivative Works, if and
          wherever such third-party notices normally appear. The contents
          of the NOTICE file are for informational purposes only and
          do not modify the License. You may add Your own attribution
          notices within Derivative Works that You distribute, alongside
          or as an addendum to the NOTICE text from the Work, provided
          that such additional attribution notices cannot be construed
          as modifying the License.

      You may add Your own copyright statement to Your modifications and
      may provide additional or different license terms and conditions
      for use, reproduction, or distribution of Your modifications, or
      for any such Derivative Works as a whole, provided Your use,
      reproduction, and distribution of the Work otherwise complies with
      the conditions stated in this License.

   5. Submission of Contributions. Unless You explicitly state otherwise,
      any Contribution intentionally submitted for inclusion in the Work
      by You to the Licensor shall be under the terms and conditions of
      this License, without any additional terms or conditions.
      Notwithstanding the above, nothing herein shall supersede or modify
      the terms of any separate license agreement you may have executed
      with Licensor regarding such Contributions.

   6. Trademarks. This License does not grant permission to use the trade
      names, trademarks, service marks, or product names of the Licensor,
      except as required for reasonable and customary use in describing the
      origin of the Work and reproducing the content of the NOTICE file.

   7. Disclaimer of Warranty. Unless required by applicable law or
      agreed to in writing, Licensor provides the Work (and each
      Contributor provides its Contributions) on an "AS IS" BASIS,
      WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or
      implied, including, without limitation, any warranties or conditions
      of TITLE, NON-INFRINGEMENT, MERCHANTABILITY, or FITNESS FOR A
      PARTICULAR PURPOSE. You are solely responsible for determining the
      appropriateness of using or redistributing the Work and assume any
      risks associated with Your exercise of permissions under this License.

   8. Limitation of Liability. In no event and under no legal theory,
      whether in tort (including negligence), contract, or otherwise,
      unless required by applicable law (such as deliberate and grossly
      negligent acts) or agreed to in writing, shall any Contributor be
      liable to You for damages, including any direct, indirect, special,
      incidental, or consequential damages of any character arising as a
      result of this License or out of the use or inability to use the
      Work (including but not limited to damages for loss of goodwill,
      work stoppage, computer failure or malfunction, or any and all
      other commercial damages or losses), even if such Contributor
      has been advised of the possibility of such damages.

   9. Accepting Warranty or Additional Liability. While redistributing
      the Work or Derivative Works thereof, You may choose to offer,
      and charge a fee for, acceptance of support, warranty, indemnity,
      or other liability obligations and/or rights consistent with this
      License. However, in accepting such obligations, You may act only
      on Your own behalf and on Your sole responsibility, not on behalf
      of any other Contributor, and only if You agree to indemnify,
      defend, and hold each Contributor harmless for any liability
      incurred by, or claims asserted against, such Contributor by reason
      of your accepting any such warranty or additional liability.

   END OF TERMS AND CONDITIONS

   APPENDIX: How to apply the Apache License to your work.

      To apply the Apache License to your work, attach the following
      boilerplate notice, with the fields enclosed by brackets "[]"
      replaced with your own identifying information. (Don't include
      the brackets!)  The text should be enclosed in the appropriate
      comment syntax for the file format. We also recommend that a
      file or class name and description of purpose be included on the
      same "printed page" as the copyright notice for easier
      identification within third-party archives.

   Copyright [yyyy] [name of copyright owner]

   Licensed under the Apache License, Version 2.0 (the "License");
   you may not use this file except in compliance with the License.
   You may obtain a copy of the License at

       http://www.apache.org/licenses/LICENSE-2.0

   Unless required by applicable law or agreed to in writing, software
   distributed under the License is distributed on an "AS IS" BASIS,
   WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
   See the License for the specific language governing permissions and
   limitations under the License.
```

<a id="go-legal-text-g015"></a>

### Go legal text G015

SHA-256: `14a34c4db2d21bf9cf80d028b802cd22fed9bf597a6c2db7ce30ee6ffd04967a`. Origins:

- `cloud.google.com/go` v0.123.0: [third_party/pkgsite/LICENSE](https://proxy.golang.org/cloud.google.com/go/@v/v0.123.0.zip)

```text
Copyright (c) 2020 The Go Authors. All rights reserved.

Redistribution and use in source and binary forms, with or without
modification, are permitted provided that the following conditions are
met:

   * Redistributions of source code must retain the above copyright
notice, this list of conditions and the following disclaimer.
   * Redistributions in binary form must reproduce the above
copyright notice, this list of conditions and the following disclaimer
in the documentation and/or other materials provided with the
distribution.
   * Neither the name of Google Inc. nor the names of its
contributors may be used to endorse or promote products derived from
this software without specific prior written permission.

THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS
"AS IS" AND ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT
LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR
A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT
OWNER OR CONTRIBUTORS BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL,
SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT
LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES; LOSS OF USE,
DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND ON ANY
THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT
(INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE
OF THIS SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.
```

<a id="go-legal-text-g034"></a>

### Go legal text G034

SHA-256: `3d180008e36922a4e8daec11c34c7af264fed5962d07924aea928c38e8663c94`. Origins:

- `github.com/andybalholm/brotli` v1.2.1: [LICENSE](https://proxy.golang.org/github.com/andybalholm/brotli/@v/v1.2.1.zip)

```text
Copyright (c) 2009, 2010, 2013-2016 by the Brotli Authors.

Permission is hereby granted, free of charge, to any person obtaining a copy
of this software and associated documentation files (the "Software"), to deal
in the Software without restriction, including without limitation the rights
to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
copies of the Software, and to permit persons to whom the Software is
furnished to do so, subject to the following conditions:

The above copyright notice and this permission notice shall be included in
all copies or substantial portions of the Software.

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT.  IN NO EVENT SHALL THE
AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
THE SOFTWARE.
```

<a id="go-legal-text-g029"></a>

### Go legal text G029

SHA-256: `2d36597f7117c38b006835ae7f537487207d8ec407aa9d9980794b2030cbc067`. Origins:

- `github.com/andybalholm/brotli` v1.2.1: [flate/LICENSE](https://proxy.golang.org/github.com/andybalholm/brotli/@v/v1.2.1.zip)
- `github.com/aws/smithy-go` v1.27.2: [transport/http/protocol/internal/json/internal/stdlib/LICENSE](https://proxy.golang.org/github.com/aws/smithy-go/@v/v1.27.2.zip)

```text
Copyright (c) 2009 The Go Authors. All rights reserved.

Redistribution and use in source and binary forms, with or without
modification, are permitted provided that the following conditions are
met:

   * Redistributions of source code must retain the above copyright
notice, this list of conditions and the following disclaimer.
   * Redistributions in binary form must reproduce the above
copyright notice, this list of conditions and the following disclaimer
in the documentation and/or other materials provided with the
distribution.
   * Neither the name of Google Inc. nor the names of its
contributors may be used to endorse or promote products derived from
this software without specific prior written permission.

THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS
"AS IS" AND ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT
LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR
A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT
OWNER OR CONTRIBUTORS BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL,
SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT
LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES; LOSS OF USE,
DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND ON ANY
THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT
(INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE
OF THIS SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.
```

<a id="go-legal-text-g081"></a>

### Go legal text G081

SHA-256: `a6e830174d62dafad3a718384772ea1f4c0e27989f050932dc442a5e3ddad080`. Origins:

- `github.com/aws/aws-sdk-go-v2` v1.42.0: [NOTICE.txt](https://proxy.golang.org/github.com/aws/aws-sdk-go-v2/@v/v1.42.0.zip)

```text
AWS SDK for Go
Copyright 2015 Amazon.com, Inc. or its affiliates. All Rights Reserved.
Copyright 2014-2015 Stripe, Inc.
```

<a id="go-legal-text-g117"></a>

### Go legal text G117

SHA-256: `f5122de47ce62d57dcd3e57cdf778ceacc3a847645de3332fea58903def93ecb`. Origins:

- `github.com/aws/aws-sdk-go-v2` v1.42.0: [internal/sync/singleflight/LICENSE](https://proxy.golang.org/github.com/aws/aws-sdk-go-v2/@v/v1.42.0.zip)
- `github.com/aws/smithy-go` v1.27.2: [internal/sync/singleflight/LICENSE](https://proxy.golang.org/github.com/aws/smithy-go/@v/v1.27.2.zip)

```text
Copyright (c) 2009 The Go Authors. All rights reserved.

Redistribution and use in source and binary forms, with or without
modification, are permitted provided that the following conditions are
met:

   * Redistributions of source code must retain the above copyright
notice, this list of conditions and the following disclaimer.
   * Redistributions in binary form must reproduce the above
copyright notice, this list of conditions and the following disclaimer
in the documentation and/or other materials provided with the
distribution.
   * Neither the name of Google Inc. nor the names of its
contributors may be used to endorse or promote products derived from
this software without specific prior written permission.

THIS SOFTWARE IS PROVIDED BY THE COPYIGHT HOLDERS AND CONTRIBUTORS
"AS IS" AND ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT
LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR
A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT
OWNER OR CONTRIBUTORS BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL,
SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT
LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES; LOSS OF USE,
DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND ON ANY
THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT
(INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE
OF THIS SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.

```

<a id="go-legal-text-g008"></a>

### Go legal text G008

SHA-256: `09e8a9bcec8067104652c168685ab0931e7868f9c8284b66f5ae6edae5f1130b`. Origins:

- `github.com/aws/smithy-go` v1.27.2: [LICENSE](https://proxy.golang.org/github.com/aws/smithy-go/@v/v1.27.2.zip)

```text

                                 Apache License
                           Version 2.0, January 2004
                        http://www.apache.org/licenses/

   TERMS AND CONDITIONS FOR USE, REPRODUCTION, AND DISTRIBUTION

   1. Definitions.

      "License" shall mean the terms and conditions for use, reproduction,
      and distribution as defined by Sections 1 through 9 of this document.

      "Licensor" shall mean the copyright owner or entity authorized by
      the copyright owner that is granting the License.

      "Legal Entity" shall mean the union of the acting entity and all
      other entities that control, are controlled by, or are under common
      control with that entity. For the purposes of this definition,
      "control" means (i) the power, direct or indirect, to cause the
      direction or management of such entity, whether by contract or
      otherwise, or (ii) ownership of fifty percent (50%) or more of the
      outstanding shares, or (iii) beneficial ownership of such entity.

      "You" (or "Your") shall mean an individual or Legal Entity
      exercising permissions granted by this License.

      "Source" form shall mean the preferred form for making modifications,
      including but not limited to software source code, documentation
      source, and configuration files.

      "Object" form shall mean any form resulting from mechanical
      transformation or translation of a Source form, including but
      not limited to compiled object code, generated documentation,
      and conversions to other media types.

      "Work" shall mean the work of authorship, whether in Source or
      Object form, made available under the License, as indicated by a
      copyright notice that is included in or attached to the work
      (an example is provided in the Appendix below).

      "Derivative Works" shall mean any work, whether in Source or Object
      form, that is based on (or derived from) the Work and for which the
      editorial revisions, annotations, elaborations, or other modifications
      represent, as a whole, an original work of authorship. For the purposes
      of this License, Derivative Works shall not include works that remain
      separable from, or merely link (or bind by name) to the interfaces of,
      the Work and Derivative Works thereof.

      "Contribution" shall mean any work of authorship, including
      the original version of the Work and any modifications or additions
      to that Work or Derivative Works thereof, that is intentionally
      submitted to Licensor for inclusion in the Work by the copyright owner
      or by an individual or Legal Entity authorized to submit on behalf of
      the copyright owner. For the purposes of this definition, "submitted"
      means any form of electronic, verbal, or written communication sent
      to the Licensor or its representatives, including but not limited to
      communication on electronic mailing lists, source code control systems,
      and issue tracking systems that are managed by, or on behalf of, the
      Licensor for the purpose of discussing and improving the Work, but
      excluding communication that is conspicuously marked or otherwise
      designated in writing by the copyright owner as "Not a Contribution."

      "Contributor" shall mean Licensor and any individual or Legal Entity
      on behalf of whom a Contribution has been received by Licensor and
      subsequently incorporated within the Work.

   2. Grant of Copyright License. Subject to the terms and conditions of
      this License, each Contributor hereby grants to You a perpetual,
      worldwide, non-exclusive, no-charge, royalty-free, irrevocable
      copyright license to reproduce, prepare Derivative Works of,
      publicly display, publicly perform, sublicense, and distribute the
      Work and such Derivative Works in Source or Object form.

   3. Grant of Patent License. Subject to the terms and conditions of
      this License, each Contributor hereby grants to You a perpetual,
      worldwide, non-exclusive, no-charge, royalty-free, irrevocable
      (except as stated in this section) patent license to make, have made,
      use, offer to sell, sell, import, and otherwise transfer the Work,
      where such license applies only to those patent claims licensable
      by such Contributor that are necessarily infringed by their
      Contribution(s) alone or by combination of their Contribution(s)
      with the Work to which such Contribution(s) was submitted. If You
      institute patent litigation against any entity (including a
      cross-claim or counterclaim in a lawsuit) alleging that the Work
      or a Contribution incorporated within the Work constitutes direct
      or contributory patent infringement, then any patent licenses
      granted to You under this License for that Work shall terminate
      as of the date such litigation is filed.

   4. Redistribution. You may reproduce and distribute copies of the
      Work or Derivative Works thereof in any medium, with or without
      modifications, and in Source or Object form, provided that You
      meet the following conditions:

      (a) You must give any other recipients of the Work or
          Derivative Works a copy of this License; and

      (b) You must cause any modified files to carry prominent notices
          stating that You changed the files; and

      (c) You must retain, in the Source form of any Derivative Works
          that You distribute, all copyright, patent, trademark, and
          attribution notices from the Source form of the Work,
          excluding those notices that do not pertain to any part of
          the Derivative Works; and

      (d) If the Work includes a "NOTICE" text file as part of its
          distribution, then any Derivative Works that You distribute must
          include a readable copy of the attribution notices contained
          within such NOTICE file, excluding those notices that do not
          pertain to any part of the Derivative Works, in at least one
          of the following places: within a NOTICE text file distributed
          as part of the Derivative Works; within the Source form or
          documentation, if provided along with the Derivative Works; or,
          within a display generated by the Derivative Works, if and
          wherever such third-party notices normally appear. The contents
          of the NOTICE file are for informational purposes only and
          do not modify the License. You may add Your own attribution
          notices within Derivative Works that You distribute, alongside
          or as an addendum to the NOTICE text from the Work, provided
          that such additional attribution notices cannot be construed
          as modifying the License.

      You may add Your own copyright statement to Your modifications and
      may provide additional or different license terms and conditions
      for use, reproduction, or distribution of Your modifications, or
      for any such Derivative Works as a whole, provided Your use,
      reproduction, and distribution of the Work otherwise complies with
      the conditions stated in this License.

   5. Submission of Contributions. Unless You explicitly state otherwise,
      any Contribution intentionally submitted for inclusion in the Work
      by You to the Licensor shall be under the terms and conditions of
      this License, without any additional terms or conditions.
      Notwithstanding the above, nothing herein shall supersede or modify
      the terms of any separate license agreement you may have executed
      with Licensor regarding such Contributions.

   6. Trademarks. This License does not grant permission to use the trade
      names, trademarks, service marks, or product names of the Licensor,
      except as required for reasonable and customary use in describing the
      origin of the Work and reproducing the content of the NOTICE file.

   7. Disclaimer of Warranty. Unless required by applicable law or
      agreed to in writing, Licensor provides the Work (and each
      Contributor provides its Contributions) on an "AS IS" BASIS,
      WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or
      implied, including, without limitation, any warranties or conditions
      of TITLE, NON-INFRINGEMENT, MERCHANTABILITY, or FITNESS FOR A
      PARTICULAR PURPOSE. You are solely responsible for determining the
      appropriateness of using or redistributing the Work and assume any
      risks associated with Your exercise of permissions under this License.

   8. Limitation of Liability. In no event and under no legal theory,
      whether in tort (including negligence), contract, or otherwise,
      unless required by applicable law (such as deliberate and grossly
      negligent acts) or agreed to in writing, shall any Contributor be
      liable to You for damages, including any direct, indirect, special,
      incidental, or consequential damages of any character arising as a
      result of this License or out of the use or inability to use the
      Work (including but not limited to damages for loss of goodwill,
      work stoppage, computer failure or malfunction, or any and all
      other commercial damages or losses), even if such Contributor
      has been advised of the possibility of such damages.

   9. Accepting Warranty or Additional Liability. While redistributing
      the Work or Derivative Works thereof, You may choose to offer,
      and charge a fee for, acceptance of support, warranty, indemnity,
      or other liability obligations and/or rights consistent with this
      License. However, in accepting such obligations, You may act only
      on Your own behalf and on Your sole responsibility, not on behalf
      of any other Contributor, and only if You agree to indemnify,
      defend, and hold each Contributor harmless for any liability
      incurred by, or claims asserted against, such Contributor by reason
      of your accepting any such warranty or additional liability.
```

<a id="go-legal-text-g103"></a>

### Go legal text G103

SHA-256: `d4290ed64c2edd0fce1d84e3f9dfb2881240fe534def76b8cd29ed6af683e287`. Origins:

- `github.com/aws/smithy-go` v1.27.2: [NOTICE](https://proxy.golang.org/github.com/aws/smithy-go/@v/v1.27.2.zip)

```text
Copyright Amazon.com, Inc. or its affiliates. All Rights Reserved.
```

<a id="go-legal-text-g041"></a>

### Go legal text G041

SHA-256: `4b6c0720c3a112229d5f8b8065641023db5cadf615bf98d3150c944adae283f2`. Origins:

- `github.com/beanstalkd/go-beanstalk` v0.2.0: [License](https://proxy.golang.org/github.com/beanstalkd/go-beanstalk/@v/v0.2.0.zip)

```text
Copyright 2012 Keith Rarick

Permission is hereby granted, free of charge, to any person
obtaining a copy of this software and associated documentation
files (the "Software"), to deal in the Software without
restriction, including without limitation the rights to use,
copy, modify, merge, publish, distribute, sublicense, and/or sell
copies of the Software, and to permit persons to whom the
Software is furnished to do so, subject to the following
conditions:

The above copyright notice and this permission notice shall be
included in all copies or substantial portions of the Software.

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND,
EXPRESS OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES
OF MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE AND
NONINFRINGEMENT. IN NO EVENT SHALL THE AUTHORS OR COPYRIGHT
HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER LIABILITY,
WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING
FROM, OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR
OTHER DEALINGS IN THE SOFTWARE.
```

<a id="go-legal-text-g011"></a>

### Go legal text G011

SHA-256: `0db7c9ebb3717e526f34f87dd1ee8bc77d36846e29cb0cec9246f7138fbe962b`. Origins:

- `github.com/beorn7/perks` v1.0.1: [LICENSE](https://proxy.golang.org/github.com/beorn7/perks/@v/v1.0.1.zip)

```text
Copyright (C) 2013 Blake Mizerany

Permission is hereby granted, free of charge, to any person obtaining
a copy of this software and associated documentation files (the
"Software"), to deal in the Software without restriction, including
without limitation the rights to use, copy, modify, merge, publish,
distribute, sublicense, and/or sell copies of the Software, and to
permit persons to whom the Software is furnished to do so, subject to
the following conditions:

The above copyright notice and this permission notice shall be
included in all copies or substantial portions of the Software.

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND,
EXPRESS OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF
MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE AND
NONINFRINGEMENT. IN NO EVENT SHALL THE AUTHORS OR COPYRIGHT HOLDERS BE
LIABLE FOR ANY CLAIM, DAMAGES OR OTHER LIABILITY, WHETHER IN AN ACTION
OF CONTRACT, TORT OR OTHERWISE, ARISING FROM, OUT OF OR IN CONNECTION
WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE SOFTWARE.
```

<a id="go-legal-text-g025"></a>

### Go legal text G025

SHA-256: `2391eb152e1f700051c1618a7aaa7ad86186585190ca9a30e7579c9b9d459332`. Origins:

- `github.com/bmatcuk/doublestar/v4` v4.10.0: [LICENSE](https://proxy.golang.org/github.com/bmatcuk/doublestar/v4/@v/v4.10.0.zip)

```text
The MIT License (MIT)

Copyright (c) 2014 Bob Matcuk

Permission is hereby granted, free of charge, to any person obtaining a copy
of this software and associated documentation files (the "Software"), to deal
in the Software without restriction, including without limitation the rights
to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
copies of the Software, and to permit persons to whom the Software is
furnished to do so, subject to the following conditions:

The above copyright notice and this permission notice shall be included in all
copies or substantial portions of the Software.

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
SOFTWARE.

```

<a id="go-legal-text-g050"></a>

### Go legal text G050

SHA-256: `632b59709152e1b5bfd3e2794848bcd2f4c70e3e175646726dbf53439ea1dbe1`. Origins:

- `github.com/buger/goterm` v1.0.4: [LICENSE](https://proxy.golang.org/github.com/buger/goterm/@v/v1.0.4.zip)

```text
MIT License

Copyright (c) 2016 Leonid Bugaev

Permission is hereby granted, free of charge, to any person obtaining a copy
of this software and associated documentation files (the "Software"), to deal
in the Software without restriction, including without limitation the rights
to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
copies of the Software, and to permit persons to whom the Software is
furnished to do so, subject to the following conditions:

The above copyright notice and this permission notice shall be included in all
copies or substantial portions of the Software.

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
SOFTWARE.
```

<a id="go-legal-text-g026"></a>

### Go legal text G026

SHA-256: `25ab55fda68ce7524e677254404f4573fd699538452949f0bfb3032a41debf68`. Origins:

- `github.com/cactus/go-statsd-client/v5` v5.1.0: [LICENSE.md](https://proxy.golang.org/github.com/cactus/go-statsd-client/v5/@v/v5.1.0.zip)

```text
Copyright (c) 2012-2016 Eli Janssen

Permission is hereby granted, free of charge, to any person obtaining a copy of
this software and associated documentation files (the "Software"), to deal in
the Software without restriction, including without limitation the rights to
use, copy, modify, merge, publish, distribute, sublicense, and/or sell copies
of the Software, and to permit persons to whom the Software is furnished to do
so, subject to the following conditions:

The above copyright notice and this permission notice shall be included in all
copies or substantial portions of the Software.

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
SOFTWARE.
```

<a id="go-legal-text-g088"></a>

### Go legal text G088

SHA-256: `b40930bbcf80744c86c46a12bc9da056641d722716c378f5659b9e555ef833e1`. Origins:

- `github.com/caddyserver/certmagic` v0.25.3: [LICENSE.txt](https://proxy.golang.org/github.com/caddyserver/certmagic/@v/v0.25.3.zip)
- `github.com/go-logr/logr` v1.4.3: [LICENSE](https://proxy.golang.org/github.com/go-logr/logr/@v/v1.4.3.zip)

```text
                                 Apache License
                           Version 2.0, January 2004
                        http://www.apache.org/licenses/

   TERMS AND CONDITIONS FOR USE, REPRODUCTION, AND DISTRIBUTION

   1. Definitions.

      "License" shall mean the terms and conditions for use, reproduction,
      and distribution as defined by Sections 1 through 9 of this document.

      "Licensor" shall mean the copyright owner or entity authorized by
      the copyright owner that is granting the License.

      "Legal Entity" shall mean the union of the acting entity and all
      other entities that control, are controlled by, or are under common
      control with that entity. For the purposes of this definition,
      "control" means (i) the power, direct or indirect, to cause the
      direction or management of such entity, whether by contract or
      otherwise, or (ii) ownership of fifty percent (50%) or more of the
      outstanding shares, or (iii) beneficial ownership of such entity.

      "You" (or "Your") shall mean an individual or Legal Entity
      exercising permissions granted by this License.

      "Source" form shall mean the preferred form for making modifications,
      including but not limited to software source code, documentation
      source, and configuration files.

      "Object" form shall mean any form resulting from mechanical
      transformation or translation of a Source form, including but
      not limited to compiled object code, generated documentation,
      and conversions to other media types.

      "Work" shall mean the work of authorship, whether in Source or
      Object form, made available under the License, as indicated by a
      copyright notice that is included in or attached to the work
      (an example is provided in the Appendix below).

      "Derivative Works" shall mean any work, whether in Source or Object
      form, that is based on (or derived from) the Work and for which the
      editorial revisions, annotations, elaborations, or other modifications
      represent, as a whole, an original work of authorship. For the purposes
      of this License, Derivative Works shall not include works that remain
      separable from, or merely link (or bind by name) to the interfaces of,
      the Work and Derivative Works thereof.

      "Contribution" shall mean any work of authorship, including
      the original version of the Work and any modifications or additions
      to that Work or Derivative Works thereof, that is intentionally
      submitted to Licensor for inclusion in the Work by the copyright owner
      or by an individual or Legal Entity authorized to submit on behalf of
      the copyright owner. For the purposes of this definition, "submitted"
      means any form of electronic, verbal, or written communication sent
      to the Licensor or its representatives, including but not limited to
      communication on electronic mailing lists, source code control systems,
      and issue tracking systems that are managed by, or on behalf of, the
      Licensor for the purpose of discussing and improving the Work, but
      excluding communication that is conspicuously marked or otherwise
      designated in writing by the copyright owner as "Not a Contribution."

      "Contributor" shall mean Licensor and any individual or Legal Entity
      on behalf of whom a Contribution has been received by Licensor and
      subsequently incorporated within the Work.

   2. Grant of Copyright License. Subject to the terms and conditions of
      this License, each Contributor hereby grants to You a perpetual,
      worldwide, non-exclusive, no-charge, royalty-free, irrevocable
      copyright license to reproduce, prepare Derivative Works of,
      publicly display, publicly perform, sublicense, and distribute the
      Work and such Derivative Works in Source or Object form.

   3. Grant of Patent License. Subject to the terms and conditions of
      this License, each Contributor hereby grants to You a perpetual,
      worldwide, non-exclusive, no-charge, royalty-free, irrevocable
      (except as stated in this section) patent license to make, have made,
      use, offer to sell, sell, import, and otherwise transfer the Work,
      where such license applies only to those patent claims licensable
      by such Contributor that are necessarily infringed by their
      Contribution(s) alone or by combination of their Contribution(s)
      with the Work to which such Contribution(s) was submitted. If You
      institute patent litigation against any entity (including a
      cross-claim or counterclaim in a lawsuit) alleging that the Work
      or a Contribution incorporated within the Work constitutes direct
      or contributory patent infringement, then any patent licenses
      granted to You under this License for that Work shall terminate
      as of the date such litigation is filed.

   4. Redistribution. You may reproduce and distribute copies of the
      Work or Derivative Works thereof in any medium, with or without
      modifications, and in Source or Object form, provided that You
      meet the following conditions:

      (a) You must give any other recipients of the Work or
          Derivative Works a copy of this License; and

      (b) You must cause any modified files to carry prominent notices
          stating that You changed the files; and

      (c) You must retain, in the Source form of any Derivative Works
          that You distribute, all copyright, patent, trademark, and
          attribution notices from the Source form of the Work,
          excluding those notices that do not pertain to any part of
          the Derivative Works; and

      (d) If the Work includes a "NOTICE" text file as part of its
          distribution, then any Derivative Works that You distribute must
          include a readable copy of the attribution notices contained
          within such NOTICE file, excluding those notices that do not
          pertain to any part of the Derivative Works, in at least one
          of the following places: within a NOTICE text file distributed
          as part of the Derivative Works; within the Source form or
          documentation, if provided along with the Derivative Works; or,
          within a display generated by the Derivative Works, if and
          wherever such third-party notices normally appear. The contents
          of the NOTICE file are for informational purposes only and
          do not modify the License. You may add Your own attribution
          notices within Derivative Works that You distribute, alongside
          or as an addendum to the NOTICE text from the Work, provided
          that such additional attribution notices cannot be construed
          as modifying the License.

      You may add Your own copyright statement to Your modifications and
      may provide additional or different license terms and conditions
      for use, reproduction, or distribution of Your modifications, or
      for any such Derivative Works as a whole, provided Your use,
      reproduction, and distribution of the Work otherwise complies with
      the conditions stated in this License.

   5. Submission of Contributions. Unless You explicitly state otherwise,
      any Contribution intentionally submitted for inclusion in the Work
      by You to the Licensor shall be under the terms and conditions of
      this License, without any additional terms or conditions.
      Notwithstanding the above, nothing herein shall supersede or modify
      the terms of any separate license agreement you may have executed
      with Licensor regarding such Contributions.

   6. Trademarks. This License does not grant permission to use the trade
      names, trademarks, service marks, or product names of the Licensor,
      except as required for reasonable and customary use in describing the
      origin of the Work and reproducing the content of the NOTICE file.

   7. Disclaimer of Warranty. Unless required by applicable law or
      agreed to in writing, Licensor provides the Work (and each
      Contributor provides its Contributions) on an "AS IS" BASIS,
      WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or
      implied, including, without limitation, any warranties or conditions
      of TITLE, NON-INFRINGEMENT, MERCHANTABILITY, or FITNESS FOR A
      PARTICULAR PURPOSE. You are solely responsible for determining the
      appropriateness of using or redistributing the Work and assume any
      risks associated with Your exercise of permissions under this License.

   8. Limitation of Liability. In no event and under no legal theory,
      whether in tort (including negligence), contract, or otherwise,
      unless required by applicable law (such as deliberate and grossly
      negligent acts) or agreed to in writing, shall any Contributor be
      liable to You for damages, including any direct, indirect, special,
      incidental, or consequential damages of any character arising as a
      result of this License or out of the use or inability to use the
      Work (including but not limited to damages for loss of goodwill,
      work stoppage, computer failure or malfunction, or any and all
      other commercial damages or losses), even if such Contributor
      has been advised of the possibility of such damages.

   9. Accepting Warranty or Additional Liability. While redistributing
      the Work or Derivative Works thereof, You may choose to offer,
      and charge a fee for, acceptance of support, warranty, indemnity,
      or other liability obligations and/or rights consistent with this
      License. However, in accepting such obligations, You may act only
      on Your own behalf and on Your sole responsibility, not on behalf
      of any other Contributor, and only if You agree to indemnify,
      defend, and hold each Contributor harmless for any liability
      incurred by, or claims asserted against, such Contributor by reason
      of your accepting any such warranty or additional liability.

   END OF TERMS AND CONDITIONS

   APPENDIX: How to apply the Apache License to your work.

      To apply the Apache License to your work, attach the following
      boilerplate notice, with the fields enclosed by brackets "{}"
      replaced with your own identifying information. (Don't include
      the brackets!)  The text should be enclosed in the appropriate
      comment syntax for the file format. We also recommend that a
      file or class name and description of purpose be included on the
      same "printed page" as the copyright notice for easier
      identification within third-party archives.

   Copyright {yyyy} {name of copyright owner}

   Licensed under the Apache License, Version 2.0 (the "License");
   you may not use this file except in compliance with the License.
   You may obtain a copy of the License at

       http://www.apache.org/licenses/LICENSE-2.0

   Unless required by applicable law or agreed to in writing, software
   distributed under the License is distributed on an "AS IS" BASIS,
   WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
   See the License for the specific language governing permissions and
   limitations under the License.
```

<a id="go-legal-text-g020"></a>

### Go legal text G020

SHA-256: `1ef7b0f3816510e2c4727a6e45b770ddb5ab123d73074e7f0b424767db0f9f7b`. Origins:

- `github.com/caddyserver/zerossl` v0.1.5: [LICENSE](https://proxy.golang.org/github.com/caddyserver/zerossl/@v/v0.1.5.zip)

```text
MIT License

Copyright (c) 2024 Matthew Holt

Permission is hereby granted, free of charge, to any person obtaining a copy
of this software and associated documentation files (the "Software"), to deal
in the Software without restriction, including without limitation the rights
to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
copies of the Software, and to permit persons to whom the Software is
furnished to do so, subject to the following conditions:

The above copyright notice and this permission notice shall be included in all
copies or substantial portions of the Software.

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
SOFTWARE.
```

<a id="go-legal-text-g044"></a>

### Go legal text G044

SHA-256: `5c0476add4c38b55d0ed5ac11b85e00c38f26e1caee20dfe3ab58190103d1fbc`. Origins:

- `github.com/cenkalti/backoff/v4` v4.3.0: [LICENSE](https://proxy.golang.org/github.com/cenkalti/backoff/v4/@v/v4.3.0.zip)
- `github.com/cenkalti/backoff/v5` v5.0.3: [LICENSE](https://proxy.golang.org/github.com/cenkalti/backoff/v5/@v/v5.0.3.zip)

```text
The MIT License (MIT)

Copyright (c) 2014 Cenk Altı

Permission is hereby granted, free of charge, to any person obtaining a copy of
this software and associated documentation files (the "Software"), to deal in
the Software without restriction, including without limitation the rights to
use, copy, modify, merge, publish, distribute, sublicense, and/or sell copies of
the Software, and to permit persons to whom the Software is furnished to do so,
subject to the following conditions:

The above copyright notice and this permission notice shall be included in all
copies or substantial portions of the Software.

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY, FITNESS
FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE AUTHORS OR
COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER LIABILITY, WHETHER
IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM, OUT OF OR IN
CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE SOFTWARE.
```

<a id="go-legal-text-g118"></a>

### Go legal text G118

SHA-256: `f566a9f97bacdaf00d9f21dd991e81dc11201c4e016c86b470799429a1c9a79c`. Origins:

- `github.com/cespare/xxhash/v2` v2.3.0: [LICENSE.txt](https://proxy.golang.org/github.com/cespare/xxhash/v2/@v/v2.3.0.zip)
- `github.com/klauspost/compress` v1.18.6: [zstd/internal/xxhash/LICENSE.txt](https://proxy.golang.org/github.com/klauspost/compress/@v/v1.18.6.zip)

```text
Copyright (c) 2016 Caleb Spare

MIT License

Permission is hereby granted, free of charge, to any person obtaining
a copy of this software and associated documentation files (the
"Software"), to deal in the Software without restriction, including
without limitation the rights to use, copy, modify, merge, publish,
distribute, sublicense, and/or sell copies of the Software, and to
permit persons to whom the Software is furnished to do so, subject to
the following conditions:

The above copyright notice and this permission notice shall be
included in all copies or substantial portions of the Software.

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND,
EXPRESS OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF
MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE AND
NONINFRINGEMENT. IN NO EVENT SHALL THE AUTHORS OR COPYRIGHT HOLDERS BE
LIABLE FOR ANY CLAIM, DAMAGES OR OTHER LIABILITY, WHETHER IN AN ACTION
OF CONTRACT, TORT OR OTHERWISE, ARISING FROM, OUT OF OR IN CONNECTION
WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE SOFTWARE.
```

<a id="go-legal-text-g124"></a>

### Go legal text G124

SHA-256: `fcdcc889a11c39c73d36019b838af41b418eb7811af12b746ace40c3074bb96c`. Origins:

- `github.com/clipperhouse/displaywidth` v0.11.0: [LICENSE](https://proxy.golang.org/github.com/clipperhouse/displaywidth/@v/v0.11.0.zip)

```text
MIT License

Copyright (c) 2025 Matt Sherman

Permission is hereby granted, free of charge, to any person obtaining a copy
of this software and associated documentation files (the "Software"), to deal
in the Software without restriction, including without limitation the rights
to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
copies of the Software, and to permit persons to whom the Software is
furnished to do so, subject to the following conditions:

The above copyright notice and this permission notice shall be included in all
copies or substantial portions of the Software.

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
SOFTWARE.
```

<a id="go-legal-text-g005"></a>

### Go legal text G005

SHA-256: `07e9e3daaccc707c9b7d535ed1405a65d37919fdd42197fe55e68820e974688c`. Origins:

- `github.com/clipperhouse/uax29/v2` v2.7.0: [LICENSE](https://proxy.golang.org/github.com/clipperhouse/uax29/v2/@v/v2.7.0.zip)

```text
MIT License

Copyright (c) 2020 Matt Sherman

Permission is hereby granted, free of charge, to any person obtaining a copy
of this software and associated documentation files (the "Software"), to deal
in the Software without restriction, including without limitation the rights
to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
copies of the Software, and to permit persons to whom the Software is
furnished to do so, subject to the following conditions:

The above copyright notice and this permission notice shall be included in all
copies or substantial portions of the Software.

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
SOFTWARE.
```

<a id="go-legal-text-g019"></a>

### Go legal text G019

SHA-256: `1b93a317849ee09d3d7e4f1d20c2b78ddb230b4becb12d7c224c927b9d470251`. Origins:

- `github.com/davecgh/go-spew` v1.1.2-0.20180830191138-d8f796af33cc: [LICENSE](https://proxy.golang.org/github.com/davecgh/go-spew/@v/v1.1.2-0.20180830191138-d8f796af33cc.zip)

```text
ISC License

Copyright (c) 2012-2016 Dave Collins <dave@davec.name>

Permission to use, copy, modify, and/or distribute this software for any
purpose with or without fee is hereby granted, provided that the above
copyright notice and this permission notice appear in all copies.

THE SOFTWARE IS PROVIDED "AS IS" AND THE AUTHOR DISCLAIMS ALL WARRANTIES
WITH REGARD TO THIS SOFTWARE INCLUDING ALL IMPLIED WARRANTIES OF
MERCHANTABILITY AND FITNESS. IN NO EVENT SHALL THE AUTHOR BE LIABLE FOR
ANY SPECIAL, DIRECT, INDIRECT, OR CONSEQUENTIAL DAMAGES OR ANY DAMAGES
WHATSOEVER RESULTING FROM LOSS OF USE, DATA OR PROFITS, WHETHER IN AN
ACTION OF CONTRACT, NEGLIGENCE OR OTHER TORTIOUS ACTION, ARISING OUT OF
OR IN CONNECTION WITH THE USE OR PERFORMANCE OF THIS SOFTWARE.
```

<a id="go-legal-text-g084"></a>

### Go legal text G084

SHA-256: `a973b4498c13eb74baa2a8e5c351426a6826f2fcdd909916dbe53ee2e755fd71`. Origins:

- `github.com/dustin/go-humanize` v1.0.1: [LICENSE](https://proxy.golang.org/github.com/dustin/go-humanize/@v/v1.0.1.zip)

```text
Copyright (c) 2005-2008  Dustin Sallings <dustin@spy.net>

Permission is hereby granted, free of charge, to any person obtaining a copy
of this software and associated documentation files (the "Software"), to deal
in the Software without restriction, including without limitation the rights
to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
copies of the Software, and to permit persons to whom the Software is
furnished to do so, subject to the following conditions:

The above copyright notice and this permission notice shall be included in
all copies or substantial portions of the Software.

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
SOFTWARE.

<http://www.opensource.org/licenses/mit-license.php>
```

<a id="go-legal-text-g112"></a>

### Go legal text G112

SHA-256: `e4cfadf64a07a1df073229ec7409f11db0b8b90f3cb73e555948ab762c886286`. Origins:

- `github.com/emicklei/proto` v1.14.3: [LICENSE](https://proxy.golang.org/github.com/emicklei/proto/@v/v1.14.3.zip)

```text
Copyright (c) 2017 Ernest Micklei

MIT License

Permission is hereby granted, free of charge, to any person obtaining
a copy of this software and associated documentation files (the
"Software"), to deal in the Software without restriction, including
without limitation the rights to use, copy, modify, merge, publish,
distribute, sublicense, and/or sell copies of the Software, and to
permit persons to whom the Software is furnished to do so, subject to
the following conditions:

The above copyright notice and this permission notice shall be
included in all copies or substantial portions of the Software.

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND,
EXPRESS OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF
MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE AND
NONINFRINGEMENT. IN NO EVENT SHALL THE AUTHORS OR COPYRIGHT HOLDERS BE
LIABLE FOR ANY CLAIM, DAMAGES OR OTHER LIABILITY, WHETHER IN AN ACTION
OF CONTRACT, TORT OR OTHERWISE, ARISING FROM, OUT OF OR IN CONNECTION
WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE SOFTWARE.
```

<a id="go-legal-text-g040"></a>

### Go legal text G040

SHA-256: `4a45268602cb0bb14943ff51689dbd1e4c87acd13b9cd3842a7e3de4c4b27f03`. Origins:

- `github.com/facebookgo/clock` v0.0.0-20150410010913-600d898af40a: [LICENSE](https://proxy.golang.org/github.com/facebookgo/clock/@v/v0.0.0-20150410010913-600d898af40a.zip)

```text
The MIT License (MIT)

Copyright (c) 2014 Ben Johnson

Permission is hereby granted, free of charge, to any person obtaining a copy
of this software and associated documentation files (the "Software"), to deal
in the Software without restriction, including without limitation the rights
to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
copies of the Software, and to permit persons to whom the Software is
furnished to do so, subject to the following conditions:

The above copyright notice and this permission notice shall be included in all
copies or substantial portions of the Software.

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
SOFTWARE.
```

<a id="go-legal-text-g108"></a>

### Go legal text G108

SHA-256: `de95ed802d2f90e3c72cb3c9a927cca5f2aec234a3ef1250232db0f8178b2d93`. Origins:

- `github.com/fatih/color` v1.19.0: [LICENSE.md](https://proxy.golang.org/github.com/fatih/color/@v/v1.19.0.zip)

```text
The MIT License (MIT)

Copyright (c) 2013 Fatih Arslan

Permission is hereby granted, free of charge, to any person obtaining a copy of
this software and associated documentation files (the "Software"), to deal in
the Software without restriction, including without limitation the rights to
use, copy, modify, merge, publish, distribute, sublicense, and/or sell copies of
the Software, and to permit persons to whom the Software is furnished to do so,
subject to the following conditions:

The above copyright notice and this permission notice shall be included in all
copies or substantial portions of the Software.

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY, FITNESS
FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE AUTHORS OR
COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER LIABILITY, WHETHER
IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM, OUT OF OR IN
CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE SOFTWARE.
```

<a id="go-legal-text-g062"></a>

### Go legal text G062

SHA-256: `8108e14b5cb2b6bcd51583d3849ef0c2b5c4de61758256c20308bc70d1775dbb`. Origins:

- `github.com/felixge/httpsnoop` v1.1.0: [LICENSE.txt](https://proxy.golang.org/github.com/felixge/httpsnoop/@v/v1.1.0.zip)

```text
Copyright (c) 2016 Felix Geisendörfer (felix@debuggable.com)

 Permission is hereby granted, free of charge, to any person obtaining a copy
 of this software and associated documentation files (the "Software"), to deal
 in the Software without restriction, including without limitation the rights
 to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 copies of the Software, and to permit persons to whom the Software is
 furnished to do so, subject to the following conditions:

 The above copyright notice and this permission notice shall be included in
 all copies or substantial portions of the Software.

 THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
 THE SOFTWARE.
```

<a id="go-legal-text-g030"></a>

### Go legal text G030

SHA-256: `2dfbe6d2b5eb18b55148f3805d88648a09e0d17e38428123259afb08c8996e37`. Origins:

- `github.com/fsnotify/fsnotify` v1.10.1: [LICENSE](https://proxy.golang.org/github.com/fsnotify/fsnotify/@v/v1.10.1.zip)

```text
Copyright © 2012 The Go Authors. All rights reserved.
Copyright © fsnotify Authors. All rights reserved.

Redistribution and use in source and binary forms, with or without modification,
are permitted provided that the following conditions are met:

* Redistributions of source code must retain the above copyright notice, this
  list of conditions and the following disclaimer.
* Redistributions in binary form must reproduce the above copyright notice, this
  list of conditions and the following disclaimer in the documentation and/or
  other materials provided with the distribution.
* Neither the name of Google Inc. nor the names of its contributors may be used
  to endorse or promote products derived from this software without specific
  prior written permission.

THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS "AS IS" AND
ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE IMPLIED
WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE ARE
DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT OWNER OR CONTRIBUTORS BE LIABLE FOR
ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES
(INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES;
LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND ON
ANY THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT
(INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE OF THIS
SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.
```

<a id="go-legal-text-g100"></a>

### Go legal text G100

SHA-256: `c71d239df91726fc519c6eb72d318ec65820627232b2f796219e87dcf35d0ab4`. Origins:

- `github.com/go-logr/stdr` v1.2.2: [LICENSE](https://proxy.golang.org/github.com/go-logr/stdr/@v/v1.2.2.zip)
- `github.com/mholt/acmez` v1.2.0: [LICENSE](https://proxy.golang.org/github.com/mholt/acmez/@v/v1.2.0.zip)
- `github.com/mholt/acmez/v3` v3.1.6: [LICENSE](https://proxy.golang.org/github.com/mholt/acmez/v3/@v/v3.1.6.zip)
- `github.com/nats-io/nats.go` v1.52.0: [LICENSE](https://proxy.golang.org/github.com/nats-io/nats.go/@v/v1.52.0.zip)
- `github.com/nats-io/nkeys` v0.4.16: [LICENSE](https://proxy.golang.org/github.com/nats-io/nkeys/@v/v0.4.16.zip)
- `github.com/nats-io/nuid` v1.0.1: [LICENSE](https://proxy.golang.org/github.com/nats-io/nuid/@v/v1.0.1.zip)
- `github.com/prometheus/client_golang` v1.23.2: [LICENSE](https://proxy.golang.org/github.com/prometheus/client_golang/@v/v1.23.2.zip)
- `github.com/prometheus/client_model` v0.6.2: [LICENSE](https://proxy.golang.org/github.com/prometheus/client_model/@v/v0.6.2.zip)
- `github.com/prometheus/common` v0.69.0: [LICENSE](https://proxy.golang.org/github.com/prometheus/common/@v/v0.69.0.zip)
- `github.com/prometheus/procfs` v0.20.1: [LICENSE](https://proxy.golang.org/github.com/prometheus/procfs/@v/v0.20.1.zip)
- `go.opentelemetry.io/auto/sdk` v1.2.1: [LICENSE](https://proxy.golang.org/go.opentelemetry.io/auto/sdk/@v/v1.2.1.zip)
- `go.opentelemetry.io/contrib/instrumentation/github.com/bradfitz/gomemcache/memcache/otelmemcache` v0.43.0: [LICENSE](https://proxy.golang.org/go.opentelemetry.io/contrib/instrumentation/github.com/bradfitz/gomemcache/memcache/otelmemcache/@v/v0.43.0.zip)
- `go.opentelemetry.io/proto/otlp` v1.10.0: [LICENSE](https://proxy.golang.org/go.opentelemetry.io/proto/otlp/@v/v1.10.0.zip)

```text
                                 Apache License
                           Version 2.0, January 2004
                        http://www.apache.org/licenses/

   TERMS AND CONDITIONS FOR USE, REPRODUCTION, AND DISTRIBUTION

   1. Definitions.

      "License" shall mean the terms and conditions for use, reproduction,
      and distribution as defined by Sections 1 through 9 of this document.

      "Licensor" shall mean the copyright owner or entity authorized by
      the copyright owner that is granting the License.

      "Legal Entity" shall mean the union of the acting entity and all
      other entities that control, are controlled by, or are under common
      control with that entity. For the purposes of this definition,
      "control" means (i) the power, direct or indirect, to cause the
      direction or management of such entity, whether by contract or
      otherwise, or (ii) ownership of fifty percent (50%) or more of the
      outstanding shares, or (iii) beneficial ownership of such entity.

      "You" (or "Your") shall mean an individual or Legal Entity
      exercising permissions granted by this License.

      "Source" form shall mean the preferred form for making modifications,
      including but not limited to software source code, documentation
      source, and configuration files.

      "Object" form shall mean any form resulting from mechanical
      transformation or translation of a Source form, including but
      not limited to compiled object code, generated documentation,
      and conversions to other media types.

      "Work" shall mean the work of authorship, whether in Source or
      Object form, made available under the License, as indicated by a
      copyright notice that is included in or attached to the work
      (an example is provided in the Appendix below).

      "Derivative Works" shall mean any work, whether in Source or Object
      form, that is based on (or derived from) the Work and for which the
      editorial revisions, annotations, elaborations, or other modifications
      represent, as a whole, an original work of authorship. For the purposes
      of this License, Derivative Works shall not include works that remain
      separable from, or merely link (or bind by name) to the interfaces of,
      the Work and Derivative Works thereof.

      "Contribution" shall mean any work of authorship, including
      the original version of the Work and any modifications or additions
      to that Work or Derivative Works thereof, that is intentionally
      submitted to Licensor for inclusion in the Work by the copyright owner
      or by an individual or Legal Entity authorized to submit on behalf of
      the copyright owner. For the purposes of this definition, "submitted"
      means any form of electronic, verbal, or written communication sent
      to the Licensor or its representatives, including but not limited to
      communication on electronic mailing lists, source code control systems,
      and issue tracking systems that are managed by, or on behalf of, the
      Licensor for the purpose of discussing and improving the Work, but
      excluding communication that is conspicuously marked or otherwise
      designated in writing by the copyright owner as "Not a Contribution."

      "Contributor" shall mean Licensor and any individual or Legal Entity
      on behalf of whom a Contribution has been received by Licensor and
      subsequently incorporated within the Work.

   2. Grant of Copyright License. Subject to the terms and conditions of
      this License, each Contributor hereby grants to You a perpetual,
      worldwide, non-exclusive, no-charge, royalty-free, irrevocable
      copyright license to reproduce, prepare Derivative Works of,
      publicly display, publicly perform, sublicense, and distribute the
      Work and such Derivative Works in Source or Object form.

   3. Grant of Patent License. Subject to the terms and conditions of
      this License, each Contributor hereby grants to You a perpetual,
      worldwide, non-exclusive, no-charge, royalty-free, irrevocable
      (except as stated in this section) patent license to make, have made,
      use, offer to sell, sell, import, and otherwise transfer the Work,
      where such license applies only to those patent claims licensable
      by such Contributor that are necessarily infringed by their
      Contribution(s) alone or by combination of their Contribution(s)
      with the Work to which such Contribution(s) was submitted. If You
      institute patent litigation against any entity (including a
      cross-claim or counterclaim in a lawsuit) alleging that the Work
      or a Contribution incorporated within the Work constitutes direct
      or contributory patent infringement, then any patent licenses
      granted to You under this License for that Work shall terminate
      as of the date such litigation is filed.

   4. Redistribution. You may reproduce and distribute copies of the
      Work or Derivative Works thereof in any medium, with or without
      modifications, and in Source or Object form, provided that You
      meet the following conditions:

      (a) You must give any other recipients of the Work or
          Derivative Works a copy of this License; and

      (b) You must cause any modified files to carry prominent notices
          stating that You changed the files; and

      (c) You must retain, in the Source form of any Derivative Works
          that You distribute, all copyright, patent, trademark, and
          attribution notices from the Source form of the Work,
          excluding those notices that do not pertain to any part of
          the Derivative Works; and

      (d) If the Work includes a "NOTICE" text file as part of its
          distribution, then any Derivative Works that You distribute must
          include a readable copy of the attribution notices contained
          within such NOTICE file, excluding those notices that do not
          pertain to any part of the Derivative Works, in at least one
          of the following places: within a NOTICE text file distributed
          as part of the Derivative Works; within the Source form or
          documentation, if provided along with the Derivative Works; or,
          within a display generated by the Derivative Works, if and
          wherever such third-party notices normally appear. The contents
          of the NOTICE file are for informational purposes only and
          do not modify the License. You may add Your own attribution
          notices within Derivative Works that You distribute, alongside
          or as an addendum to the NOTICE text from the Work, provided
          that such additional attribution notices cannot be construed
          as modifying the License.

      You may add Your own copyright statement to Your modifications and
      may provide additional or different license terms and conditions
      for use, reproduction, or distribution of Your modifications, or
      for any such Derivative Works as a whole, provided Your use,
      reproduction, and distribution of the Work otherwise complies with
      the conditions stated in this License.

   5. Submission of Contributions. Unless You explicitly state otherwise,
      any Contribution intentionally submitted for inclusion in the Work
      by You to the Licensor shall be under the terms and conditions of
      this License, without any additional terms or conditions.
      Notwithstanding the above, nothing herein shall supersede or modify
      the terms of any separate license agreement you may have executed
      with Licensor regarding such Contributions.

   6. Trademarks. This License does not grant permission to use the trade
      names, trademarks, service marks, or product names of the Licensor,
      except as required for reasonable and customary use in describing the
      origin of the Work and reproducing the content of the NOTICE file.

   7. Disclaimer of Warranty. Unless required by applicable law or
      agreed to in writing, Licensor provides the Work (and each
      Contributor provides its Contributions) on an "AS IS" BASIS,
      WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or
      implied, including, without limitation, any warranties or conditions
      of TITLE, NON-INFRINGEMENT, MERCHANTABILITY, or FITNESS FOR A
      PARTICULAR PURPOSE. You are solely responsible for determining the
      appropriateness of using or redistributing the Work and assume any
      risks associated with Your exercise of permissions under this License.

   8. Limitation of Liability. In no event and under no legal theory,
      whether in tort (including negligence), contract, or otherwise,
      unless required by applicable law (such as deliberate and grossly
      negligent acts) or agreed to in writing, shall any Contributor be
      liable to You for damages, including any direct, indirect, special,
      incidental, or consequential damages of any character arising as a
      result of this License or out of the use or inability to use the
      Work (including but not limited to damages for loss of goodwill,
      work stoppage, computer failure or malfunction, or any and all
      other commercial damages or losses), even if such Contributor
      has been advised of the possibility of such damages.

   9. Accepting Warranty or Additional Liability. While redistributing
      the Work or Derivative Works thereof, You may choose to offer,
      and charge a fee for, acceptance of support, warranty, indemnity,
      or other liability obligations and/or rights consistent with this
      License. However, in accepting such obligations, You may act only
      on Your own behalf and on Your sole responsibility, not on behalf
      of any other Contributor, and only if You agree to indemnify,
      defend, and hold each Contributor harmless for any liability
      incurred by, or claims asserted against, such Contributor by reason
      of your accepting any such warranty or additional liability.

   END OF TERMS AND CONDITIONS

   APPENDIX: How to apply the Apache License to your work.

      To apply the Apache License to your work, attach the following
      boilerplate notice, with the fields enclosed by brackets "[]"
      replaced with your own identifying information. (Don't include
      the brackets!)  The text should be enclosed in the appropriate
      comment syntax for the file format. We also recommend that a
      file or class name and description of purpose be included on the
      same "printed page" as the copyright notice for easier
      identification within third-party archives.

   Copyright [yyyy] [name of copyright owner]

   Licensed under the Apache License, Version 2.0 (the "License");
   you may not use this file except in compliance with the License.
   You may obtain a copy of the License at

       http://www.apache.org/licenses/LICENSE-2.0

   Unless required by applicable law or agreed to in writing, software
   distributed under the License is distributed on an "AS IS" BASIS,
   WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
   See the License for the specific language governing permissions and
   limitations under the License.
```

<a id="go-legal-text-g024"></a>

### Go legal text G024

SHA-256: `22adc4abdece712a737573672f082fd61ac2b21df878efb87ffcff4354a07f26`. Origins:

- `github.com/go-viper/mapstructure/v2` v2.5.0: [LICENSE](https://proxy.golang.org/github.com/go-viper/mapstructure/v2/@v/v2.5.0.zip)

```text
The MIT License (MIT)

Copyright (c) 2013 Mitchell Hashimoto

Permission is hereby granted, free of charge, to any person obtaining a copy
of this software and associated documentation files (the "Software"), to deal
in the Software without restriction, including without limitation the rights
to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
copies of the Software, and to permit persons to whom the Software is
furnished to do so, subject to the following conditions:

The above copyright notice and this permission notice shall be included in
all copies or substantial portions of the Software.

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
THE SOFTWARE.
```

<a id="go-legal-text-g082"></a>

### Go legal text G082

SHA-256: `a707cd425201945d57500566c47c74dc9fb8cd02ef91b9a761abddeb2401e6a7`. Origins:

- `github.com/goccy/go-json` v0.10.6: [LICENSE](https://proxy.golang.org/github.com/goccy/go-json/@v/v0.10.6.zip)

```text
MIT License

Copyright (c) 2020 Masaaki Goshima

Permission is hereby granted, free of charge, to any person obtaining a copy
of this software and associated documentation files (the "Software"), to deal
in the Software without restriction, including without limitation the rights
to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
copies of the Software, and to permit persons to whom the Software is
furnished to do so, subject to the following conditions:

The above copyright notice and this permission notice shall be included in all
copies or substantial portions of the Software.

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
SOFTWARE.
```

<a id="go-legal-text-g045"></a>

### Go legal text G045

SHA-256: `5c0e1bfb8a314043331e093c78154cc7870e276ffb64ac6ace6ee0087162b1e4`. Origins:

- `github.com/gofiber/fiber/v2` v2.52.13: [LICENSE](https://proxy.golang.org/github.com/gofiber/fiber/v2/@v/v2.52.13.zip)

```text
MIT License

Copyright (c) 2019-present Fenny and Contributors

Permission is hereby granted, free of charge, to any person obtaining a copy
of this software and associated documentation files (the "Software"), to deal
in the Software without restriction, including without limitation the rights
to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
copies of the Software, and to permit persons to whom the Software is
furnished to do so, subject to the following conditions:

The above copyright notice and this permission notice shall be included in all
copies or substantial portions of the Software.

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
SOFTWARE.
```

<a id="go-legal-text-g105"></a>

### Go legal text G105

SHA-256: `d883dbca28769ff23dd4368a8edb7ad9f9f23a8f86adde2875c6e41a828aac62`. Origins:

- `github.com/gofiber/fiber/v2` v2.52.13: [internal/go-ole/LICENSE](https://proxy.golang.org/github.com/gofiber/fiber/v2/@v/v2.52.13.zip)

```text
The MIT License (MIT)

Copyright © 2013-2017 Yasuhiro Matsumoto, <mattn.jp@gmail.com>

Permission is hereby granted, free of charge, to any person obtaining a copy of
this software and associated documentation files (the “Software”), to deal in
the Software without restriction, including without limitation the rights to
use, copy, modify, merge, publish, distribute, sublicense, and/or sell copies
of the Software, and to permit persons to whom the Software is furnished to do
so, subject to the following conditions:

The above copyright notice and this permission notice shall be included in all
copies or substantial portions of the Software.

THE SOFTWARE IS PROVIDED “AS IS”, WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
SOFTWARE.
```

<a id="go-legal-text-g071"></a>

### Go legal text G071

SHA-256: `8db64481897d4b1604821103bf9621244fa257c0a80eecb643630ae22e890289`. Origins:

- `github.com/gofiber/fiber/v2` v2.52.13: [internal/gopsutil/LICENSE](https://proxy.golang.org/github.com/gofiber/fiber/v2/@v/v2.52.13.zip)
- `github.com/shirou/gopsutil` v3.21.11+incompatible: [LICENSE](https://proxy.golang.org/github.com/shirou/gopsutil/@v/v3.21.11+incompatible.zip)

```text
gopsutil is distributed under BSD license reproduced below.

Copyright (c) 2014, WAKAYAMA Shirou
All rights reserved.

Redistribution and use in source and binary forms, with or without modification,
are permitted provided that the following conditions are met:

 * Redistributions of source code must retain the above copyright notice, this
   list of conditions and the following disclaimer.
 * Redistributions in binary form must reproduce the above copyright notice,
   this list of conditions and the following disclaimer in the documentation
   and/or other materials provided with the distribution.
 * Neither the name of the gopsutil authors nor the names of its contributors
   may be used to endorse or promote products derived from this software without
   specific prior written permission.

THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS "AS IS" AND
ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE IMPLIED
WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE ARE
DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT OWNER OR CONTRIBUTORS BE LIABLE FOR
ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES
(INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES;
LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND ON
ANY THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT
(INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE OF THIS
SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.


-------
internal/common/binary.go in the gopsutil is copied and modifid from golang/encoding/binary.go.



Copyright (c) 2009 The Go Authors. All rights reserved.

Redistribution and use in source and binary forms, with or without
modification, are permitted provided that the following conditions are
met:

   * Redistributions of source code must retain the above copyright
notice, this list of conditions and the following disclaimer.
   * Redistributions in binary form must reproduce the above
copyright notice, this list of conditions and the following disclaimer
in the documentation and/or other materials provided with the
distribution.
   * Neither the name of Google Inc. nor the names of its
contributors may be used to endorse or promote products derived from
this software without specific prior written permission.

THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS
"AS IS" AND ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT
LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR
A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT
OWNER OR CONTRIBUTORS BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL,
SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT
LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES; LOSS OF USE,
DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND ON ANY
THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT
(INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE
OF THIS SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.
```

<a id="go-legal-text-g111"></a>

### Go legal text G111

SHA-256: `e2b2df1c58a081f27c3b9fa9feec759f2bf2ce430ae02a4f274b2d9ac7e87abd`. Origins:

- `github.com/gofiber/fiber/v2` v2.52.13: [internal/schema/LICENSE](https://proxy.golang.org/github.com/gofiber/fiber/v2/@v/v2.52.13.zip)

```text
Copyright (c) 2012 Rodrigo Moraes. All rights reserved.

Redistribution and use in source and binary forms, with or without
modification, are permitted provided that the following conditions are
met:

	 * Redistributions of source code must retain the above copyright
notice, this list of conditions and the following disclaimer.
	 * Redistributions in binary form must reproduce the above
copyright notice, this list of conditions and the following disclaimer
in the documentation and/or other materials provided with the
distribution.
	 * Neither the name of Google Inc. nor the names of its
contributors may be used to endorse or promote products derived from
this software without specific prior written permission.

THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS
"AS IS" AND ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT
LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR
A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT
OWNER OR CONTRIBUTORS BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL,
SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT
LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES; LOSS OF USE,
DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND ON ANY
THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT
(INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE
OF THIS SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.
```

<a id="go-legal-text-g052"></a>

### Go legal text G052

SHA-256: `66e24ffb77bdafac86bd5c47ea1c3d44e2d7546ca9caa219ee66a110584864e7`. Origins:

- `github.com/gofiber/fiber/v2` v2.52.13: [internal/wmi/LICENSE](https://proxy.golang.org/github.com/gofiber/fiber/v2/@v/v2.52.13.zip)

```text
The MIT License (MIT)

Copyright (c) 2013 Stack Exchange

Permission is hereby granted, free of charge, to any person obtaining a copy of
this software and associated documentation files (the "Software"), to deal in
the Software without restriction, including without limitation the rights to
use, copy, modify, merge, publish, distribute, sublicense, and/or sell copies of
the Software, and to permit persons to whom the Software is furnished to do so,
subject to the following conditions:

The above copyright notice and this permission notice shall be included in all
copies or substantial portions of the Software.

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY, FITNESS
FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE AUTHORS OR
COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER LIABILITY, WHETHER
IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM, OUT OF OR IN
CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE SOFTWARE.
```

<a id="go-legal-text-g035"></a>

### Go legal text G035

SHA-256: `3f571768c0ba2f330e5fee90de89bfe5141f48c1939be8008fa61461c30ca07f`. Origins:

- `github.com/gogo/protobuf` v1.3.2: [LICENSE](https://proxy.golang.org/github.com/gogo/protobuf/@v/v1.3.2.zip)

```text
Copyright (c) 2013, The GoGo Authors. All rights reserved.

Protocol Buffers for Go with Gadgets

Go support for Protocol Buffers - Google's data interchange format

Copyright 2010 The Go Authors.  All rights reserved.
https://github.com/golang/protobuf

Redistribution and use in source and binary forms, with or without
modification, are permitted provided that the following conditions are
met:

    * Redistributions of source code must retain the above copyright
notice, this list of conditions and the following disclaimer.
    * Redistributions in binary form must reproduce the above
copyright notice, this list of conditions and the following disclaimer
in the documentation and/or other materials provided with the
distribution.
    * Neither the name of Google Inc. nor the names of its
contributors may be used to endorse or promote products derived from
this software without specific prior written permission.

THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS
"AS IS" AND ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT
LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR
A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT
OWNER OR CONTRIBUTORS BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL,
SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT
LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES; LOSS OF USE,
DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND ON ANY
THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT
(INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE
OF THIS SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.

```

<a id="go-legal-text-g064"></a>

### Go legal text G064

SHA-256: `823dcea7fae12387eb0e45de41cb4f815c4fe34276fd633c6fe29279a6311e63`. Origins:

- `github.com/golang/mock` v1.7.0-rc.1: [mockgen/internal/tests/copyright_file/mock_copyright_header](https://proxy.golang.org/github.com/golang/mock/@v/v1.7.0-rc.1.zip)

```text
This is a mock copyright header.

Lorem ipsum dolor sit amet, consectetur adipiscing elit,
sed do eiusmod tempor incididunt ut labore et dolore magna 
aliqua. Velit ut tortor pretium viverra suspendisse potenti.
```

<a id="go-legal-text-g009"></a>

### Go legal text G009

SHA-256: `0a8d61ed3cbfd5312326e8126c31ce9c627a283adc99131b56896d29ada04b2d`. Origins:

- `github.com/google/uuid` v1.6.0: [LICENSE](https://proxy.golang.org/github.com/google/uuid/@v/v1.6.0.zip)

```text
Copyright (c) 2009,2014 Google Inc. All rights reserved.

Redistribution and use in source and binary forms, with or without
modification, are permitted provided that the following conditions are
met:

   * Redistributions of source code must retain the above copyright
notice, this list of conditions and the following disclaimer.
   * Redistributions in binary form must reproduce the above
copyright notice, this list of conditions and the following disclaimer
in the documentation and/or other materials provided with the
distribution.
   * Neither the name of Google Inc. nor the names of its
contributors may be used to endorse or promote products derived from
this software without specific prior written permission.

THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS
"AS IS" AND ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT
LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR
A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT
OWNER OR CONTRIBUTORS BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL,
SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT
LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES; LOSS OF USE,
DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND ON ANY
THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT
(INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE
OF THIS SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.
```

<a id="go-legal-text-g092"></a>

### Go legal text G092

SHA-256: `b95218cd9607855a6536384c0262922b30a0c2bf56e4ced790240f3a3bac4722`. Origins:

- `github.com/googleapis/gax-go/v2` v2.22.0: [LICENSE](https://proxy.golang.org/github.com/googleapis/gax-go/v2/@v/v2.22.0.zip)

```text
Copyright 2016, Google Inc.
All rights reserved.
Redistribution and use in source and binary forms, with or without
modification, are permitted provided that the following conditions are
met:

   * Redistributions of source code must retain the above copyright
notice, this list of conditions and the following disclaimer.
   * Redistributions in binary form must reproduce the above
copyright notice, this list of conditions and the following disclaimer
in the documentation and/or other materials provided with the
distribution.
   * Neither the name of Google Inc. nor the names of its
contributors may be used to endorse or promote products derived from
this software without specific prior written permission.

THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS
"AS IS" AND ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT
LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR
A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT
OWNER OR CONTRIBUTORS BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL,
SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT
LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES; LOSS OF USE,
DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND ON ANY
THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT
(INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE
OF THIS SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.
```

<a id="go-legal-text-g069"></a>

### Go legal text G069

SHA-256: `8befb41eac649775143c81fddaf84b7d7e1197899d968b8b51cfec405f2ff72f`. Origins:

- `github.com/grpc-ecosystem/go-grpc-middleware/v2` v2.3.3: [COPYRIGHT](https://proxy.golang.org/github.com/grpc-ecosystem/go-grpc-middleware/v2/@v/v2.3.3.zip)

```text
Copyright (c) The go-grpc-middleware Authors.
Licensed under the Apache License 2.0.
```

<a id="go-legal-text-g033"></a>

### Go legal text G033

SHA-256: `332c4f0a1a657a8937ad0caf7a335a31ec72343821481e364d8894f568c8b010`. Origins:

- `github.com/grpc-ecosystem/go-grpc-middleware/v2` v2.3.3: [LICENSE](https://proxy.golang.org/github.com/grpc-ecosystem/go-grpc-middleware/v2/@v/v2.3.3.zip)

```text
                 Apache License
                           Version 2.0, January 2004
                        http://www.apache.org/licenses/

   TERMS AND CONDITIONS FOR USE, REPRODUCTION, AND DISTRIBUTION

   1. Definitions.

      "License" shall mean the terms and conditions for use, reproduction,
      and distribution as defined by Sections 1 through 9 of this document.

      "Licensor" shall mean the copyright owner or entity authorized by
      the copyright owner that is granting the License.

      "Legal Entity" shall mean the union of the acting entity and all
      other entities that control, are controlled by, or are under common
      control with that entity. For the purposes of this definition,
      "control" means (i) the power, direct or indirect, to cause the
      direction or management of such entity, whether by contract or
      otherwise, or (ii) ownership of fifty percent (50%) or more of the
      outstanding shares, or (iii) beneficial ownership of such entity.

      "You" (or "Your") shall mean an individual or Legal Entity
      exercising permissions granted by this License.

      "Source" form shall mean the preferred form for making modifications,
      including but not limited to software source code, documentation
      source, and configuration files.

      "Object" form shall mean any form resulting from mechanical
      transformation or translation of a Source form, including but
      not limited to compiled object code, generated documentation,
      and conversions to other media types.

      "Work" shall mean the work of authorship, whether in Source or
      Object form, made available under the License, as indicated by a
      copyright notice that is included in or attached to the work
      (an example is provided in the Appendix below).

      "Derivative Works" shall mean any work, whether in Source or Object
      form, that is based on (or derived from) the Work and for which the
      editorial revisions, annotations, elaborations, or other modifications
      represent, as a whole, an original work of authorship. For the purposes
      of this License, Derivative Works shall not include works that remain
      separable from, or merely link (or bind by name) to the interfaces of,
      the Work and Derivative Works thereof.

      "Contribution" shall mean any work of authorship, including
      the original version of the Work and any modifications or additions
      to that Work or Derivative Works thereof, that is intentionally
      submitted to Licensor for inclusion in the Work by the copyright owner
      or by an individual or Legal Entity authorized to submit on behalf of
      the copyright owner. For the purposes of this definition, "submitted"
      means any form of electronic, verbal, or written communication sent
      to the Licensor or its representatives, including but not limited to
      communication on electronic mailing lists, source code control systems,
      and issue tracking systems that are managed by, or on behalf of, the
      Licensor for the purpose of discussing and improving the Work, but
      excluding communication that is conspicuously marked or otherwise
      designated in writing by the copyright owner as "Not a Contribution."

      "Contributor" shall mean Licensor and any individual or Legal Entity
      on behalf of whom a Contribution has been received by Licensor and
      subsequently incorporated within the Work.

   2. Grant of Copyright License. Subject to the terms and conditions of
      this License, each Contributor hereby grants to You a perpetual,
      worldwide, non-exclusive, no-charge, royalty-free, irrevocable
      copyright license to reproduce, prepare Derivative Works of,
      publicly display, publicly perform, sublicense, and distribute the
      Work and such Derivative Works in Source or Object form.

   3. Grant of Patent License. Subject to the terms and conditions of
      this License, each Contributor hereby grants to You a perpetual,
      worldwide, non-exclusive, no-charge, royalty-free, irrevocable
      (except as stated in this section) patent license to make, have made,
      use, offer to sell, sell, import, and otherwise transfer the Work,
      where such license applies only to those patent claims licensable
      by such Contributor that are necessarily infringed by their
      Contribution(s) alone or by combination of their Contribution(s)
      with the Work to which such Contribution(s) was submitted. If You
      institute patent litigation against any entity (including a
      cross-claim or counterclaim in a lawsuit) alleging that the Work
      or a Contribution incorporated within the Work constitutes direct
      or contributory patent infringement, then any patent licenses
      granted to You under this License for that Work shall terminate
      as of the date such litigation is filed.

   4. Redistribution. You may reproduce and distribute copies of the
      Work or Derivative Works thereof in any medium, with or without
      modifications, and in Source or Object form, provided that You
      meet the following conditions:

      (a) You must give any other recipients of the Work or
          Derivative Works a copy of this License; and

      (b) You must cause any modified files to carry prominent notices
          stating that You changed the files; and

      (c) You must retain, in the Source form of any Derivative Works
          that You distribute, all copyright, patent, trademark, and
          attribution notices from the Source form of the Work,
          excluding those notices that do not pertain to any part of
          the Derivative Works; and

      (d) If the Work includes a "NOTICE" text file as part of its
          distribution, then any Derivative Works that You distribute must
          include a readable copy of the attribution notices contained
          within such NOTICE file, excluding those notices that do not
          pertain to any part of the Derivative Works, in at least one
          of the following places: within a NOTICE text file distributed
          as part of the Derivative Works; within the Source form or
          documentation, if provided along with the Derivative Works; or,
          within a display generated by the Derivative Works, if and
          wherever such third-party notices normally appear. The contents
          of the NOTICE file are for informational purposes only and
          do not modify the License. You may add Your own attribution
          notices within Derivative Works that You distribute, alongside
          or as an addendum to the NOTICE text from the Work, provided
          that such additional attribution notices cannot be construed
          as modifying the License.

      You may add Your own copyright statement to Your modifications and
      may provide additional or different license terms and conditions
      for use, reproduction, or distribution of Your modifications, or
      for any such Derivative Works as a whole, provided Your use,
      reproduction, and distribution of the Work otherwise complies with
      the conditions stated in this License.

   5. Submission of Contributions. Unless You explicitly state otherwise,
      any Contribution intentionally submitted for inclusion in the Work
      by You to the Licensor shall be under the terms and conditions of
      this License, without any additional terms or conditions.
      Notwithstanding the above, nothing herein shall supersede or modify
      the terms of any separate license agreement you may have executed
      with Licensor regarding such Contributions.

   6. Trademarks. This License does not grant permission to use the trade
      names, trademarks, service marks, or product names of the Licensor,
      except as required for reasonable and customary use in describing the
      origin of the Work and reproducing the content of the NOTICE file.

   7. Disclaimer of Warranty. Unless required by applicable law or
      agreed to in writing, Licensor provides the Work (and each
      Contributor provides its Contributions) on an "AS IS" BASIS,
      WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or
      implied, including, without limitation, any warranties or conditions
      of TITLE, NON-INFRINGEMENT, MERCHANTABILITY, or FITNESS FOR A
      PARTICULAR PURPOSE. You are solely responsible for determining the
      appropriateness of using or redistributing the Work and assume any
      risks associated with Your exercise of permissions under this License.

   8. Limitation of Liability. In no event and under no legal theory,
      whether in tort (including negligence), contract, or otherwise,
      unless required by applicable law (such as deliberate and grossly
      negligent acts) or agreed to in writing, shall any Contributor be
      liable to You for damages, including any direct, indirect, special,
      incidental, or consequential damages of any character arising as a
      result of this License or out of the use or inability to use the
      Work (including but not limited to damages for loss of goodwill,
      work stoppage, computer failure or malfunction, or any and all
      other commercial damages or losses), even if such Contributor
      has been advised of the possibility of such damages.

   9. Accepting Warranty or Additional Liability. While redistributing
      the Work or Derivative Works thereof, You may choose to offer,
      and charge a fee for, acceptance of support, warranty, indemnity,
      or other liability obligations and/or rights consistent with this
      License. However, in accepting such obligations, You may act only
      on Your own behalf and on Your sole responsibility, not on behalf
      of any other Contributor, and only if You agree to indemnify,
      defend, and hold each Contributor harmless for any liability
      incurred by, or claims asserted against, such Contributor by reason
      of your accepting any such warranty or additional liability.

   END OF TERMS AND CONDITIONS

   APPENDIX: How to apply the Apache License to your work.

      To apply the Apache License to your work, attach the following
      boilerplate notice, with the fields enclosed by brackets "[]"
      replaced with your own identifying information. (Don't include
      the brackets!)  The text should be enclosed in the appropriate
      comment syntax for the file format. We also recommend that a
      file or class name and description of purpose be included on the
      same "printed page" as the copyright notice for easier
      identification within third-party archives.

   Copyright [yyyy] [name of copyright owner]

   Licensed under the Apache License, Version 2.0 (the "License");
   you may not use this file except in compliance with the License.
   You may obtain a copy of the License at

       http://www.apache.org/licenses/LICENSE-2.0

   Unless required by applicable law or agreed to in writing, software
   distributed under the License is distributed on an "AS IS" BASIS,
   WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
   See the License for the specific language governing permissions and
   limitations under the License.
```

<a id="go-legal-text-g077"></a>

### Go legal text G077

SHA-256: `a15b1d1b168954c92ff7fb1620382418f7c72f4f4d251ee791d1098ad68ab0c4`. Origins:

- `github.com/grpc-ecosystem/grpc-gateway/v2` v2.29.0: [LICENSE](https://proxy.golang.org/github.com/grpc-ecosystem/grpc-gateway/v2/@v/v2.29.0.zip)

```text
Copyright (c) 2015, Gengo, Inc.
All rights reserved.

Redistribution and use in source and binary forms, with or without modification,
are permitted provided that the following conditions are met:

    * Redistributions of source code must retain the above copyright notice,
      this list of conditions and the following disclaimer.

    * Redistributions in binary form must reproduce the above copyright notice,
      this list of conditions and the following disclaimer in the documentation
      and/or other materials provided with the distribution.

    * Neither the name of Gengo, Inc. nor the names of its
      contributors may be used to endorse or promote products derived from this
      software without specific prior written permission.

THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS "AS IS" AND
ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE IMPLIED
WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE ARE
DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT OWNER OR CONTRIBUTORS BE LIABLE FOR
ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES
(INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES;
LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND ON
ANY THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT
(INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE OF THIS
SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.
```

<a id="go-legal-text-g022"></a>

### Go legal text G022

SHA-256: `20873bd48d04a4e4075ee881ec2a5f3a3b14bf4920018b1131a7c52ec9b4a318`. Origins:

- `github.com/grpc-ecosystem/grpc-gateway/v2` v2.29.0: [internal/casing/LICENSE.md](https://proxy.golang.org/github.com/grpc-ecosystem/grpc-gateway/v2/@v/v2.29.0.zip)

```text
Copyright 2010, 2019 The Go Authors.  All rights reserved.

Redistribution and use in source and binary forms, with or without
modification, are permitted provided that the following conditions are
met:

    * Redistributions of source code must retain the above copyright
notice, this list of conditions and the following disclaimer.
    * Redistributions in binary form must reproduce the above
copyright notice, this list of conditions and the following disclaimer
in the documentation and/or other materials provided with the
distribution.
    * Neither the name of Google Inc. nor the names of its
contributors may be used to endorse or promote products derived from
this software without specific prior written permission.

THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS
"AS IS" AND ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT
LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR
A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT
OWNER OR CONTRIBUTORS BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL,
SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT
LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES; LOSS OF USE,
DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND ON ANY
THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT
(INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE
OF THIS SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.

```

<a id="go-legal-text-g037"></a>

### Go legal text G037

SHA-256: `4334bf49b7e1a6736ce1b21814d5cce392000204d059ee6a845eeb966e7a1e31`. Origins:

- `github.com/joho/godotenv` v1.5.1: [LICENCE](https://proxy.golang.org/github.com/joho/godotenv/@v/v1.5.1.zip)

```text
Copyright (c) 2013 John Barton

MIT License

Permission is hereby granted, free of charge, to any person obtaining
a copy of this software and associated documentation files (the
"Software"), to deal in the Software without restriction, including
without limitation the rights to use, copy, modify, merge, publish,
distribute, sublicense, and/or sell copies of the Software, and to
permit persons to whom the Software is furnished to do so, subject to
the following conditions:

The above copyright notice and this permission notice shall be
included in all copies or substantial portions of the Software.

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND,
EXPRESS OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF
MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE AND
NONINFRINGEMENT. IN NO EVENT SHALL THE AUTHORS OR COPYRIGHT HOLDERS BE
LIABLE FOR ANY CLAIM, DAMAGES OR OTHER LIABILITY, WHETHER IN AN ACTION
OF CONTRACT, TORT OR OTHERWISE, ARISING FROM, OUT OF OR IN CONNECTION
WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE SOFTWARE.

```

<a id="go-legal-text-g010"></a>

### Go legal text G010

SHA-256: `0d9e582ee4bff57bf1189c9e514e6da7ce277f9cd3bc2d488b22fbb39a6d87cf`. Origins:

- `github.com/klauspost/compress` v1.18.6: [LICENSE](https://proxy.golang.org/github.com/klauspost/compress/@v/v1.18.6.zip)

```text
Copyright (c) 2012 The Go Authors. All rights reserved.
Copyright (c) 2019 Klaus Post. All rights reserved.

Redistribution and use in source and binary forms, with or without
modification, are permitted provided that the following conditions are
met:

   * Redistributions of source code must retain the above copyright
notice, this list of conditions and the following disclaimer.
   * Redistributions in binary form must reproduce the above
copyright notice, this list of conditions and the following disclaimer
in the documentation and/or other materials provided with the
distribution.
   * Neither the name of Google Inc. nor the names of its
contributors may be used to endorse or promote products derived from
this software without specific prior written permission.

THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS
"AS IS" AND ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT
LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR
A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT
OWNER OR CONTRIBUTORS BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL,
SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT
LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES; LOSS OF USE,
DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND ON ANY
THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT
(INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE
OF THIS SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.

------------------

Files: gzhttp/*

                                 Apache License
                           Version 2.0, January 2004
                        http://www.apache.org/licenses/

   TERMS AND CONDITIONS FOR USE, REPRODUCTION, AND DISTRIBUTION

   1. Definitions.

      "License" shall mean the terms and conditions for use, reproduction,
      and distribution as defined by Sections 1 through 9 of this document.

      "Licensor" shall mean the copyright owner or entity authorized by
      the copyright owner that is granting the License.

      "Legal Entity" shall mean the union of the acting entity and all
      other entities that control, are controlled by, or are under common
      control with that entity. For the purposes of this definition,
      "control" means (i) the power, direct or indirect, to cause the
      direction or management of such entity, whether by contract or
      otherwise, or (ii) ownership of fifty percent (50%) or more of the
      outstanding shares, or (iii) beneficial ownership of such entity.

      "You" (or "Your") shall mean an individual or Legal Entity
      exercising permissions granted by this License.

      "Source" form shall mean the preferred form for making modifications,
      including but not limited to software source code, documentation
      source, and configuration files.

      "Object" form shall mean any form resulting from mechanical
      transformation or translation of a Source form, including but
      not limited to compiled object code, generated documentation,
      and conversions to other media types.

      "Work" shall mean the work of authorship, whether in Source or
      Object form, made available under the License, as indicated by a
      copyright notice that is included in or attached to the work
      (an example is provided in the Appendix below).

      "Derivative Works" shall mean any work, whether in Source or Object
      form, that is based on (or derived from) the Work and for which the
      editorial revisions, annotations, elaborations, or other modifications
      represent, as a whole, an original work of authorship. For the purposes
      of this License, Derivative Works shall not include works that remain
      separable from, or merely link (or bind by name) to the interfaces of,
      the Work and Derivative Works thereof.

      "Contribution" shall mean any work of authorship, including
      the original version of the Work and any modifications or additions
      to that Work or Derivative Works thereof, that is intentionally
      submitted to Licensor for inclusion in the Work by the copyright owner
      or by an individual or Legal Entity authorized to submit on behalf of
      the copyright owner. For the purposes of this definition, "submitted"
      means any form of electronic, verbal, or written communication sent
      to the Licensor or its representatives, including but not limited to
      communication on electronic mailing lists, source code control systems,
      and issue tracking systems that are managed by, or on behalf of, the
      Licensor for the purpose of discussing and improving the Work, but
      excluding communication that is conspicuously marked or otherwise
      designated in writing by the copyright owner as "Not a Contribution."

      "Contributor" shall mean Licensor and any individual or Legal Entity
      on behalf of whom a Contribution has been received by Licensor and
      subsequently incorporated within the Work.

   2. Grant of Copyright License. Subject to the terms and conditions of
      this License, each Contributor hereby grants to You a perpetual,
      worldwide, non-exclusive, no-charge, royalty-free, irrevocable
      copyright license to reproduce, prepare Derivative Works of,
      publicly display, publicly perform, sublicense, and distribute the
      Work and such Derivative Works in Source or Object form.

   3. Grant of Patent License. Subject to the terms and conditions of
      this License, each Contributor hereby grants to You a perpetual,
      worldwide, non-exclusive, no-charge, royalty-free, irrevocable
      (except as stated in this section) patent license to make, have made,
      use, offer to sell, sell, import, and otherwise transfer the Work,
      where such license applies only to those patent claims licensable
      by such Contributor that are necessarily infringed by their
      Contribution(s) alone or by combination of their Contribution(s)
      with the Work to which such Contribution(s) was submitted. If You
      institute patent litigation against any entity (including a
      cross-claim or counterclaim in a lawsuit) alleging that the Work
      or a Contribution incorporated within the Work constitutes direct
      or contributory patent infringement, then any patent licenses
      granted to You under this License for that Work shall terminate
      as of the date such litigation is filed.

   4. Redistribution. You may reproduce and distribute copies of the
      Work or Derivative Works thereof in any medium, with or without
      modifications, and in Source or Object form, provided that You
      meet the following conditions:

      (a) You must give any other recipients of the Work or
          Derivative Works a copy of this License; and

      (b) You must cause any modified files to carry prominent notices
          stating that You changed the files; and

      (c) You must retain, in the Source form of any Derivative Works
          that You distribute, all copyright, patent, trademark, and
          attribution notices from the Source form of the Work,
          excluding those notices that do not pertain to any part of
          the Derivative Works; and

      (d) If the Work includes a "NOTICE" text file as part of its
          distribution, then any Derivative Works that You distribute must
          include a readable copy of the attribution notices contained
          within such NOTICE file, excluding those notices that do not
          pertain to any part of the Derivative Works, in at least one
          of the following places: within a NOTICE text file distributed
          as part of the Derivative Works; within the Source form or
          documentation, if provided along with the Derivative Works; or,
          within a display generated by the Derivative Works, if and
          wherever such third-party notices normally appear. The contents
          of the NOTICE file are for informational purposes only and
          do not modify the License. You may add Your own attribution
          notices within Derivative Works that You distribute, alongside
          or as an addendum to the NOTICE text from the Work, provided
          that such additional attribution notices cannot be construed
          as modifying the License.

      You may add Your own copyright statement to Your modifications and
      may provide additional or different license terms and conditions
      for use, reproduction, or distribution of Your modifications, or
      for any such Derivative Works as a whole, provided Your use,
      reproduction, and distribution of the Work otherwise complies with
      the conditions stated in this License.

   5. Submission of Contributions. Unless You explicitly state otherwise,
      any Contribution intentionally submitted for inclusion in the Work
      by You to the Licensor shall be under the terms and conditions of
      this License, without any additional terms or conditions.
      Notwithstanding the above, nothing herein shall supersede or modify
      the terms of any separate license agreement you may have executed
      with Licensor regarding such Contributions.

   6. Trademarks. This License does not grant permission to use the trade
      names, trademarks, service marks, or product names of the Licensor,
      except as required for reasonable and customary use in describing the
      origin of the Work and reproducing the content of the NOTICE file.

   7. Disclaimer of Warranty. Unless required by applicable law or
      agreed to in writing, Licensor provides the Work (and each
      Contributor provides its Contributions) on an "AS IS" BASIS,
      WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or
      implied, including, without limitation, any warranties or conditions
      of TITLE, NON-INFRINGEMENT, MERCHANTABILITY, or FITNESS FOR A
      PARTICULAR PURPOSE. You are solely responsible for determining the
      appropriateness of using or redistributing the Work and assume any
      risks associated with Your exercise of permissions under this License.

   8. Limitation of Liability. In no event and under no legal theory,
      whether in tort (including negligence), contract, or otherwise,
      unless required by applicable law (such as deliberate and grossly
      negligent acts) or agreed to in writing, shall any Contributor be
      liable to You for damages, including any direct, indirect, special,
      incidental, or consequential damages of any character arising as a
      result of this License or out of the use or inability to use the
      Work (including but not limited to damages for loss of goodwill,
      work stoppage, computer failure or malfunction, or any and all
      other commercial damages or losses), even if such Contributor
      has been advised of the possibility of such damages.

   9. Accepting Warranty or Additional Liability. While redistributing
      the Work or Derivative Works thereof, You may choose to offer,
      and charge a fee for, acceptance of support, warranty, indemnity,
      or other liability obligations and/or rights consistent with this
      License. However, in accepting such obligations, You may act only
      on Your own behalf and on Your sole responsibility, not on behalf
      of any other Contributor, and only if You agree to indemnify,
      defend, and hold each Contributor harmless for any liability
      incurred by, or claims asserted against, such Contributor by reason
      of your accepting any such warranty or additional liability.

   END OF TERMS AND CONDITIONS

   APPENDIX: How to apply the Apache License to your work.

      To apply the Apache License to your work, attach the following
      boilerplate notice, with the fields enclosed by brackets "[]"
      replaced with your own identifying information. (Don't include
      the brackets!)  The text should be enclosed in the appropriate
      comment syntax for the file format. We also recommend that a
      file or class name and description of purpose be included on the
      same "printed page" as the copyright notice for easier
      identification within third-party archives.

   Copyright 2016-2017 The New York Times Company

   Licensed under the Apache License, Version 2.0 (the "License");
   you may not use this file except in compliance with the License.
   You may obtain a copy of the License at

       http://www.apache.org/licenses/LICENSE-2.0

   Unless required by applicable law or agreed to in writing, software
   distributed under the License is distributed on an "AS IS" BASIS,
   WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
   See the License for the specific language governing permissions and
   limitations under the License.

------------------

Files: s2/cmd/internal/readahead/*

The MIT License (MIT)

Copyright (c) 2015 Klaus Post

Permission is hereby granted, free of charge, to any person obtaining a copy
of this software and associated documentation files (the "Software"), to deal
in the Software without restriction, including without limitation the rights
to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
copies of the Software, and to permit persons to whom the Software is
furnished to do so, subject to the following conditions:

The above copyright notice and this permission notice shall be included in all
copies or substantial portions of the Software.

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
SOFTWARE.

---------------------
Files: snappy/*
Files: internal/snapref/*

Copyright (c) 2011 The Snappy-Go Authors. All rights reserved.

Redistribution and use in source and binary forms, with or without
modification, are permitted provided that the following conditions are
met:

   * Redistributions of source code must retain the above copyright
notice, this list of conditions and the following disclaimer.
   * Redistributions in binary form must reproduce the above
copyright notice, this list of conditions and the following disclaimer
in the documentation and/or other materials provided with the
distribution.
   * Neither the name of Google Inc. nor the names of its
contributors may be used to endorse or promote products derived from
this software without specific prior written permission.

THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS
"AS IS" AND ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT
LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR
A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT
OWNER OR CONTRIBUTORS BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL,
SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT
LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES; LOSS OF USE,
DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND ON ANY
THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT
(INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE
OF THIS SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.

-----------------

Files: s2/cmd/internal/filepathx/*

Copyright 2016 The filepathx Authors

Permission is hereby granted, free of charge, to any person obtaining a copy of this software and associated documentation files (the "Software"), to deal in the Software without restriction, including without limitation the rights to use, copy, modify, merge, publish, distribute, sublicense, and/or sell copies of the Software, and to permit persons to whom the Software is furnished to do so, subject to the following conditions:

The above copyright notice and this permission notice shall be included in all copies or substantial portions of the Software.

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM, OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE SOFTWARE.
```

<a id="go-legal-text-g023"></a>

### Go legal text G023

SHA-256: `2252a5b1e2b1d3663b53ea77051b8ed065cb9270a3d1174d5d6ead8a2de051b6`. Origins:

- `github.com/klauspost/compress` v1.18.6: [gzhttp/LICENSE](https://proxy.golang.org/github.com/klauspost/compress/@v/v1.18.6.zip)

```text
                                 Apache License
                           Version 2.0, January 2004
                        http://www.apache.org/licenses/

   TERMS AND CONDITIONS FOR USE, REPRODUCTION, AND DISTRIBUTION

   1. Definitions.

      "License" shall mean the terms and conditions for use, reproduction,
      and distribution as defined by Sections 1 through 9 of this document.

      "Licensor" shall mean the copyright owner or entity authorized by
      the copyright owner that is granting the License.

      "Legal Entity" shall mean the union of the acting entity and all
      other entities that control, are controlled by, or are under common
      control with that entity. For the purposes of this definition,
      "control" means (i) the power, direct or indirect, to cause the
      direction or management of such entity, whether by contract or
      otherwise, or (ii) ownership of fifty percent (50%) or more of the
      outstanding shares, or (iii) beneficial ownership of such entity.

      "You" (or "Your") shall mean an individual or Legal Entity
      exercising permissions granted by this License.

      "Source" form shall mean the preferred form for making modifications,
      including but not limited to software source code, documentation
      source, and configuration files.

      "Object" form shall mean any form resulting from mechanical
      transformation or translation of a Source form, including but
      not limited to compiled object code, generated documentation,
      and conversions to other media types.

      "Work" shall mean the work of authorship, whether in Source or
      Object form, made available under the License, as indicated by a
      copyright notice that is included in or attached to the work
      (an example is provided in the Appendix below).

      "Derivative Works" shall mean any work, whether in Source or Object
      form, that is based on (or derived from) the Work and for which the
      editorial revisions, annotations, elaborations, or other modifications
      represent, as a whole, an original work of authorship. For the purposes
      of this License, Derivative Works shall not include works that remain
      separable from, or merely link (or bind by name) to the interfaces of,
      the Work and Derivative Works thereof.

      "Contribution" shall mean any work of authorship, including
      the original version of the Work and any modifications or additions
      to that Work or Derivative Works thereof, that is intentionally
      submitted to Licensor for inclusion in the Work by the copyright owner
      or by an individual or Legal Entity authorized to submit on behalf of
      the copyright owner. For the purposes of this definition, "submitted"
      means any form of electronic, verbal, or written communication sent
      to the Licensor or its representatives, including but not limited to
      communication on electronic mailing lists, source code control systems,
      and issue tracking systems that are managed by, or on behalf of, the
      Licensor for the purpose of discussing and improving the Work, but
      excluding communication that is conspicuously marked or otherwise
      designated in writing by the copyright owner as "Not a Contribution."

      "Contributor" shall mean Licensor and any individual or Legal Entity
      on behalf of whom a Contribution has been received by Licensor and
      subsequently incorporated within the Work.

   2. Grant of Copyright License. Subject to the terms and conditions of
      this License, each Contributor hereby grants to You a perpetual,
      worldwide, non-exclusive, no-charge, royalty-free, irrevocable
      copyright license to reproduce, prepare Derivative Works of,
      publicly display, publicly perform, sublicense, and distribute the
      Work and such Derivative Works in Source or Object form.

   3. Grant of Patent License. Subject to the terms and conditions of
      this License, each Contributor hereby grants to You a perpetual,
      worldwide, non-exclusive, no-charge, royalty-free, irrevocable
      (except as stated in this section) patent license to make, have made,
      use, offer to sell, sell, import, and otherwise transfer the Work,
      where such license applies only to those patent claims licensable
      by such Contributor that are necessarily infringed by their
      Contribution(s) alone or by combination of their Contribution(s)
      with the Work to which such Contribution(s) was submitted. If You
      institute patent litigation against any entity (including a
      cross-claim or counterclaim in a lawsuit) alleging that the Work
      or a Contribution incorporated within the Work constitutes direct
      or contributory patent infringement, then any patent licenses
      granted to You under this License for that Work shall terminate
      as of the date such litigation is filed.

   4. Redistribution. You may reproduce and distribute copies of the
      Work or Derivative Works thereof in any medium, with or without
      modifications, and in Source or Object form, provided that You
      meet the following conditions:

      (a) You must give any other recipients of the Work or
          Derivative Works a copy of this License; and

      (b) You must cause any modified files to carry prominent notices
          stating that You changed the files; and

      (c) You must retain, in the Source form of any Derivative Works
          that You distribute, all copyright, patent, trademark, and
          attribution notices from the Source form of the Work,
          excluding those notices that do not pertain to any part of
          the Derivative Works; and

      (d) If the Work includes a "NOTICE" text file as part of its
          distribution, then any Derivative Works that You distribute must
          include a readable copy of the attribution notices contained
          within such NOTICE file, excluding those notices that do not
          pertain to any part of the Derivative Works, in at least one
          of the following places: within a NOTICE text file distributed
          as part of the Derivative Works; within the Source form or
          documentation, if provided along with the Derivative Works; or,
          within a display generated by the Derivative Works, if and
          wherever such third-party notices normally appear. The contents
          of the NOTICE file are for informational purposes only and
          do not modify the License. You may add Your own attribution
          notices within Derivative Works that You distribute, alongside
          or as an addendum to the NOTICE text from the Work, provided
          that such additional attribution notices cannot be construed
          as modifying the License.

      You may add Your own copyright statement to Your modifications and
      may provide additional or different license terms and conditions
      for use, reproduction, or distribution of Your modifications, or
      for any such Derivative Works as a whole, provided Your use,
      reproduction, and distribution of the Work otherwise complies with
      the conditions stated in this License.

   5. Submission of Contributions. Unless You explicitly state otherwise,
      any Contribution intentionally submitted for inclusion in the Work
      by You to the Licensor shall be under the terms and conditions of
      this License, without any additional terms or conditions.
      Notwithstanding the above, nothing herein shall supersede or modify
      the terms of any separate license agreement you may have executed
      with Licensor regarding such Contributions.

   6. Trademarks. This License does not grant permission to use the trade
      names, trademarks, service marks, or product names of the Licensor,
      except as required for reasonable and customary use in describing the
      origin of the Work and reproducing the content of the NOTICE file.

   7. Disclaimer of Warranty. Unless required by applicable law or
      agreed to in writing, Licensor provides the Work (and each
      Contributor provides its Contributions) on an "AS IS" BASIS,
      WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or
      implied, including, without limitation, any warranties or conditions
      of TITLE, NON-INFRINGEMENT, MERCHANTABILITY, or FITNESS FOR A
      PARTICULAR PURPOSE. You are solely responsible for determining the
      appropriateness of using or redistributing the Work and assume any
      risks associated with Your exercise of permissions under this License.

   8. Limitation of Liability. In no event and under no legal theory,
      whether in tort (including negligence), contract, or otherwise,
      unless required by applicable law (such as deliberate and grossly
      negligent acts) or agreed to in writing, shall any Contributor be
      liable to You for damages, including any direct, indirect, special,
      incidental, or consequential damages of any character arising as a
      result of this License or out of the use or inability to use the
      Work (including but not limited to damages for loss of goodwill,
      work stoppage, computer failure or malfunction, or any and all
      other commercial damages or losses), even if such Contributor
      has been advised of the possibility of such damages.

   9. Accepting Warranty or Additional Liability. While redistributing
      the Work or Derivative Works thereof, You may choose to offer,
      and charge a fee for, acceptance of support, warranty, indemnity,
      or other liability obligations and/or rights consistent with this
      License. However, in accepting such obligations, You may act only
      on Your own behalf and on Your sole responsibility, not on behalf
      of any other Contributor, and only if You agree to indemnify,
      defend, and hold each Contributor harmless for any liability
      incurred by, or claims asserted against, such Contributor by reason
      of your accepting any such warranty or additional liability.

   END OF TERMS AND CONDITIONS

   APPENDIX: How to apply the Apache License to your work.

      To apply the Apache License to your work, attach the following
      boilerplate notice, with the fields enclosed by brackets "[]"
      replaced with your own identifying information. (Don't include
      the brackets!)  The text should be enclosed in the appropriate
      comment syntax for the file format. We also recommend that a
      file or class name and description of purpose be included on the
      same "printed page" as the copyright notice for easier
      identification within third-party archives.

   Copyright 2016-2017 The New York Times Company

   Licensed under the Apache License, Version 2.0 (the "License");
   you may not use this file except in compliance with the License.
   You may obtain a copy of the License at

       http://www.apache.org/licenses/LICENSE-2.0

   Unless required by applicable law or agreed to in writing, software
   distributed under the License is distributed on an "AS IS" BASIS,
   WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
   See the License for the specific language governing permissions and
   limitations under the License.
```

<a id="go-legal-text-g055"></a>

### Go legal text G055

SHA-256: `6a358d2540ca14048f02d366f23787c0a480157e58f058113f0e27168dd4e447`. Origins:

- `github.com/klauspost/compress` v1.18.6: [internal/lz4ref/LICENSE](https://proxy.golang.org/github.com/klauspost/compress/@v/v1.18.6.zip)
- `github.com/pierrec/lz4/v4` v4.1.27: [LICENSE](https://proxy.golang.org/github.com/pierrec/lz4/v4/@v/v4.1.27.zip)

```text
Copyright (c) 2015, Pierre Curto
All rights reserved.

Redistribution and use in source and binary forms, with or without
modification, are permitted provided that the following conditions are met:

* Redistributions of source code must retain the above copyright notice, this
  list of conditions and the following disclaimer.

* Redistributions in binary form must reproduce the above copyright notice,
  this list of conditions and the following disclaimer in the documentation
  and/or other materials provided with the distribution.

* Neither the name of xxHash nor the names of its
  contributors may be used to endorse or promote products derived from
  this software without specific prior written permission.

THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS "AS IS"
AND ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE
IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE ARE
DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT HOLDER OR CONTRIBUTORS BE LIABLE
FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL
DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR
SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER
CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY,
OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE
OF THIS SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.

```

<a id="go-legal-text-g119"></a>

### Go legal text G119

SHA-256: `f69f157b0be75da373605dbc8bbf142e8924ee82d8f44f11bcaf351335bf98cf`. Origins:

- `github.com/klauspost/compress` v1.18.6: [internal/snapref/LICENSE](https://proxy.golang.org/github.com/klauspost/compress/@v/v1.18.6.zip)
- `github.com/klauspost/compress` v1.18.6: [snappy/LICENSE](https://proxy.golang.org/github.com/klauspost/compress/@v/v1.18.6.zip)

```text
Copyright (c) 2011 The Snappy-Go Authors. All rights reserved.

Redistribution and use in source and binary forms, with or without
modification, are permitted provided that the following conditions are
met:

   * Redistributions of source code must retain the above copyright
notice, this list of conditions and the following disclaimer.
   * Redistributions in binary form must reproduce the above
copyright notice, this list of conditions and the following disclaimer
in the documentation and/or other materials provided with the
distribution.
   * Neither the name of Google Inc. nor the names of its
contributors may be used to endorse or promote products derived from
this software without specific prior written permission.

THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS
"AS IS" AND ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT
LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR
A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT
OWNER OR CONTRIBUTORS BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL,
SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT
LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES; LOSS OF USE,
DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND ON ANY
THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT
(INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE
OF THIS SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.
```

<a id="go-legal-text-g006"></a>

### Go legal text G006

SHA-256: `08683b14bda8ae3538abf19e1879e853a39e3b8276a8d673363b529d15a61c1a`. Origins:

- `github.com/klauspost/compress` v1.18.6: [s2/LICENSE](https://proxy.golang.org/github.com/klauspost/compress/@v/v1.18.6.zip)

```text
Copyright (c) 2011 The Snappy-Go Authors. All rights reserved.
Copyright (c) 2019 Klaus Post. All rights reserved.

Redistribution and use in source and binary forms, with or without
modification, are permitted provided that the following conditions are
met:

   * Redistributions of source code must retain the above copyright
notice, this list of conditions and the following disclaimer.
   * Redistributions in binary form must reproduce the above
copyright notice, this list of conditions and the following disclaimer
in the documentation and/or other materials provided with the
distribution.
   * Neither the name of Google Inc. nor the names of its
contributors may be used to endorse or promote products derived from
this software without specific prior written permission.

THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS
"AS IS" AND ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT
LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR
A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT
OWNER OR CONTRIBUTORS BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL,
SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT
LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES; LOSS OF USE,
DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND ON ANY
THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT
(INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE
OF THIS SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.
```

<a id="go-legal-text-g066"></a>

### Go legal text G066

SHA-256: `8582c87d9a6baa28f7827c5205b7d16c3179bc794ebd4c7cc287c49a89b7eaa1`. Origins:

- `github.com/klauspost/compress` v1.18.6: [s2/cmd/internal/filepathx/LICENSE](https://proxy.golang.org/github.com/klauspost/compress/@v/v1.18.6.zip)

```text
Copyright 2016 The filepathx Authors

Permission is hereby granted, free of charge, to any person obtaining a copy of this software and associated documentation files (the "Software"), to deal in the Software without restriction, including without limitation the rights to use, copy, modify, merge, publish, distribute, sublicense, and/or sell copies of the Software, and to permit persons to whom the Software is furnished to do so, subject to the following conditions:

The above copyright notice and this permission notice shall be included in all copies or substantial portions of the Software.

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM, OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE SOFTWARE.
```

<a id="go-legal-text-g013"></a>

### Go legal text G013

SHA-256: `10fa24a602fde465db603cd5d546e37e33642a73cd24ddf5910be81724fec8b2`. Origins:

- `github.com/klauspost/compress` v1.18.6: [s2/cmd/internal/readahead/LICENSE](https://proxy.golang.org/github.com/klauspost/compress/@v/v1.18.6.zip)

```text
The MIT License (MIT)

Copyright (c) 2015 Klaus Post

Permission is hereby granted, free of charge, to any person obtaining a copy
of this software and associated documentation files (the "Software"), to deal
in the Software without restriction, including without limitation the rights
to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
copies of the Software, and to permit persons to whom the Software is
furnished to do so, subject to the following conditions:

The above copyright notice and this permission notice shall be included in all
copies or substantial portions of the Software.

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
SOFTWARE.

```

<a id="go-legal-text-g104"></a>

### Go legal text G104

SHA-256: `d4ff111fa30ac75e28dca3868de957a3b73ff50b76bc4b8fe5c3a1f008d26703`. Origins:

- `github.com/klauspost/compress` v1.18.6: [snappy/xerial/LICENSE](https://proxy.golang.org/github.com/klauspost/compress/@v/v1.18.6.zip)

```text
The MIT License (MIT)

Copyright (c) 2016 Evan Huus
Copyright (c) 2023 Klaus Post

Permission is hereby granted, free of charge, to any person obtaining a copy
of this software and associated documentation files (the "Software"), to deal
in the Software without restriction, including without limitation the rights
to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
copies of the Software, and to permit persons to whom the Software is
furnished to do so, subject to the following conditions:

The above copyright notice and this permission notice shall be included in all
copies or substantial portions of the Software.

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
SOFTWARE.
```

<a id="go-legal-text-g046"></a>

### Go legal text G046

SHA-256: `5d966570d7a442d4e969892860a914e542c97f262c873baee8f0aa48e1f40212`. Origins:

- `github.com/klauspost/cpuid/v2` v2.3.0: [LICENSE](https://proxy.golang.org/github.com/klauspost/cpuid/v2/@v/v2.3.0.zip)

```text
The MIT License (MIT)

Copyright (c) 2015 Klaus Post

Permission is hereby granted, free of charge, to any person obtaining a copy
of this software and associated documentation files (the "Software"), to deal
in the Software without restriction, including without limitation the rights
to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
copies of the Software, and to permit persons to whom the Software is
furnished to do so, subject to the following conditions:

The above copyright notice and this permission notice shall be included in all
copies or substantial portions of the Software.

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
SOFTWARE.

```

<a id="go-legal-text-g114"></a>

### Go legal text G114

SHA-256: `e75b5c81dc881b74f84c306c2d0b7db1affbe78d9c0feb5722eb432fd534bea5`. Origins:

- `github.com/libdns/libdns` v1.1.1: [LICENSE](https://proxy.golang.org/github.com/libdns/libdns/@v/v1.1.1.zip)

```text
MIT License

Copyright (c) 2020 Matthew Holt

Permission is hereby granted, free of charge, to any person obtaining a copy
of this software and associated documentation files (the "Software"), to deal
in the Software without restriction, including without limitation the rights
to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
copies of the Software, and to permit persons to whom the Software is
furnished to do so, subject to the following conditions:

The above copyright notice and this permission notice shall be included in all
copies or substantial portions of the Software.

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
SOFTWARE.
```

<a id="go-legal-text-g067"></a>

### Go legal text G067

SHA-256: `88a2379b3ca34bf5c57127aff9dcb802bbb60ece0805cdbda65b3bd115f971d9`. Origins:

- `github.com/mattn/go-colorable` v0.1.15: [LICENSE](https://proxy.golang.org/github.com/mattn/go-colorable/@v/v0.1.15.zip)
- `github.com/mattn/go-runewidth` v0.0.24: [LICENSE](https://proxy.golang.org/github.com/mattn/go-runewidth/@v/v0.0.24.zip)

```text
The MIT License (MIT)

Copyright (c) 2016 Yasuhiro Matsumoto

Permission is hereby granted, free of charge, to any person obtaining a copy
of this software and associated documentation files (the "Software"), to deal
in the Software without restriction, including without limitation the rights
to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
copies of the Software, and to permit persons to whom the Software is
furnished to do so, subject to the following conditions:

The above copyright notice and this permission notice shall be included in all
copies or substantial portions of the Software.

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
SOFTWARE.
```

<a id="go-legal-text-g007"></a>

### Go legal text G007

SHA-256: `08eab1118c80885fa1fa6a6dd7303f65a379fcb3733e063d20d1bbc2c76e6fa1`. Origins:

- `github.com/mattn/go-isatty` v0.0.22: [LICENSE](https://proxy.golang.org/github.com/mattn/go-isatty/@v/v0.0.22.zip)

```text
Copyright (c) Yasuhiro MATSUMOTO <mattn.jp@gmail.com>

MIT License (Expat)

Permission is hereby granted, free of charge, to any person obtaining a copy of this software and associated documentation files (the "Software"), to deal in the Software without restriction, including without limitation the rights to use, copy, modify, merge, publish, distribute, sublicense, and/or sell copies of the Software, and to permit persons to whom the Software is furnished to do so, subject to the following conditions:

The above copyright notice and this permission notice shall be included in all copies or substantial portions of the Software.

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM, OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE SOFTWARE.
```

<a id="go-legal-text-g051"></a>

### Go legal text G051

SHA-256: `66550c0ad5ca7ec1e08683e5f872cc45c741f311eee3b8ee484206ecbf9c740d`. Origins:

- `github.com/miekg/dns` v1.1.72: [COPYRIGHT](https://proxy.golang.org/github.com/miekg/dns/@v/v1.1.72.zip)

```text
Copyright 2009 The Go Authors. All rights reserved. Use of this source code
is governed by a BSD-style license that can be found in the LICENSE file.
Extensions of the original work are copyright (c) 2011 Miek Gieben

Copyright 2011 Miek Gieben. All rights reserved. Use of this source code is
governed by a BSD-style license that can be found in the LICENSE file.

Copyright 2014 CloudFlare. All rights reserved. Use of this source code is
governed by a BSD-style license that can be found in the LICENSE file.
```

<a id="go-legal-text-g116"></a>

### Go legal text G116

SHA-256: `f200ff9bad73385db2ed81e23ee2c1fe069eb5776e17fae4ee85ad0a9e1361a5`. Origins:

- `github.com/miekg/dns` v1.1.72: [LICENSE](https://proxy.golang.org/github.com/miekg/dns/@v/v1.1.72.zip)

```text
BSD 3-Clause License

Copyright (c) 2009, The Go Authors. Extensions copyright (c) 2011, Miek Gieben. 
All rights reserved.

Redistribution and use in source and binary forms, with or without
modification, are permitted provided that the following conditions are met:

1. Redistributions of source code must retain the above copyright notice, this
   list of conditions and the following disclaimer.

2. Redistributions in binary form must reproduce the above copyright notice,
   this list of conditions and the following disclaimer in the documentation
   and/or other materials provided with the distribution.

3. Neither the name of the copyright holder nor the names of its
   contributors may be used to endorse or promote products derived from
   this software without specific prior written permission.

THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS "AS IS"
AND ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE
IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE ARE
DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT HOLDER OR CONTRIBUTORS BE LIABLE
FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL
DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR
SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER
CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY,
OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE
OF THIS SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.
```

<a id="go-legal-text-g085"></a>

### Go legal text G085

SHA-256: `aa1376b9bc5dea6f30cdefde40c176a254f247d2814d0a9929395138631b2ae0`. Origins:

- `github.com/munnerz/goautoneg` v0.0.0-20191010083416-a7dc8b61c822: [LICENSE](https://proxy.golang.org/github.com/munnerz/goautoneg/@v/v0.0.0-20191010083416-a7dc8b61c822.zip)

```text
Copyright (c) 2011, Open Knowledge Foundation Ltd.
All rights reserved.

Redistribution and use in source and binary forms, with or without
modification, are permitted provided that the following conditions are
met:

    Redistributions of source code must retain the above copyright
    notice, this list of conditions and the following disclaimer.

    Redistributions in binary form must reproduce the above copyright
    notice, this list of conditions and the following disclaimer in
    the documentation and/or other materials provided with the
    distribution.

    Neither the name of the Open Knowledge Foundation Ltd. nor the
    names of its contributors may be used to endorse or promote
    products derived from this software without specific prior written
    permission.

THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS
"AS IS" AND ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT
LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR
A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT
HOLDER OR CONTRIBUTORS BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL,
SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT
LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES; LOSS OF USE,
DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND ON ANY
THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT
(INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE
OF THIS SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.
```

<a id="go-legal-text-g004"></a>

### Go legal text G004

SHA-256: `06847bcc52e67fceae1691299bdd42dbfdff138c91e3ea89811a7aa2827988d6`. Origins:

- `github.com/nexus-rpc/nexus-proto-annotations` v0.1.0: [LICENSE (license-only direct child, exact identical code)](https://raw.githubusercontent.com/nexus-rpc/nexus-proto-annotations/835bd8d49cb45c8efa22614164b90335f7e56918/LICENSE)

```text
MIT License

Copyright (c) 2026 Temporal Technologies Inc. All Rights Reserved

Permission is hereby granted, free of charge, to any person obtaining a copy
of this software and associated documentation files (the "Software"), to deal
in the Software without restriction, including without limitation the rights
to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
copies of the Software, and to permit persons to whom the Software is
furnished to do so, subject to the following conditions:

The above copyright notice and this permission notice shall be included in all
copies or substantial portions of the Software.

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
SOFTWARE.
```

<a id="go-legal-text-g059"></a>

### Go legal text G059

SHA-256: `77b9d9cba009de957e8eef311eaaefa890ebf088fe3c5a930e9c282b053d9ea1`. Origins:

- `github.com/nexus-rpc/sdk-go` v0.6.0: [LICENSE](https://proxy.golang.org/github.com/nexus-rpc/sdk-go/@v/v0.6.0.zip)

```text
MIT License

Copyright (c) 2023 Temporal Technologies Inc. All Rights Reserved

Permission is hereby granted, free of charge, to any person obtaining a copy
of this software and associated documentation files (the "Software"), to deal
in the Software without restriction, including without limitation the rights
to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
copies of the Software, and to permit persons to whom the Software is
furnished to do so, subject to the following conditions:

The above copyright notice and this permission notice shall be included in all
copies or substantial portions of the Software.

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
SOFTWARE.
```

<a id="go-legal-text-g057"></a>

### Go legal text G057

SHA-256: `6b2a3fd39696e823479c310d353d30ba32d3c3de7c975c6b5d0718b989082099`. Origins:

- `github.com/olekukonko/cat` v0.0.0-20250911104152-50322a0618f6: [LICENSE](https://proxy.golang.org/github.com/olekukonko/cat/@v/v0.0.0-20250911104152-50322a0618f6.zip)
- `github.com/olekukonko/errors` v1.3.0: [LICENSE](https://proxy.golang.org/github.com/olekukonko/errors/@v/v1.3.0.zip)
- `github.com/olekukonko/ll` v0.1.8: [LICENSE](https://proxy.golang.org/github.com/olekukonko/ll/@v/v0.1.8.zip)

```text
MIT License

Copyright (c) 2025 Oleku Konko

Permission is hereby granted, free of charge, to any person obtaining a copy
of this software and associated documentation files (the "Software"), to deal
in the Software without restriction, including without limitation the rights
to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
copies of the Software, and to permit persons to whom the Software is
furnished to do so, subject to the following conditions:

The above copyright notice and this permission notice shall be included in all
copies or substantial portions of the Software.

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
SOFTWARE.
```

<a id="go-legal-text-g002"></a>

### Go legal text G002

SHA-256: `0583acca2b32a7e4f7bc2d98422b2f55097333273731b3ba2a16d66f9e422744`. Origins:

- `github.com/olekukonko/tablewriter` v1.1.4: [LICENSE.md](https://proxy.golang.org/github.com/olekukonko/tablewriter/@v/v1.1.4.zip)

```text
Copyright (C) 2014 by Oleku Konko

Permission is hereby granted, free of charge, to any person obtaining a copy
of this software and associated documentation files (the "Software"), to deal
in the Software without restriction, including without limitation the rights
to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
copies of the Software, and to permit persons to whom the Software is
furnished to do so, subject to the following conditions:

The above copyright notice and this permission notice shall be included in
all copies or substantial portions of the Software.

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
THE SOFTWARE.
```

<a id="go-legal-text-g090"></a>

### Go legal text G090

SHA-256: `b6e1c0e0078b720f92237bb3c5bfd21742cb78da8fe9759515b9d84ea0e96595`. Origins:

- `github.com/openzipkin/zipkin-go` v0.4.3: [LICENSE](https://proxy.golang.org/github.com/openzipkin/zipkin-go/@v/v0.4.3.zip)

```text
Apache License
Version 2.0, January 2004
http://www.apache.org/licenses/

TERMS AND CONDITIONS FOR USE, REPRODUCTION, AND DISTRIBUTION

1. Definitions.

"License" shall mean the terms and conditions for use, reproduction,
and distribution as defined by Sections 1 through 9 of this document.

"Licensor" shall mean the copyright owner or entity authorized by
the copyright owner that is granting the License.

"Legal Entity" shall mean the union of the acting entity and all
other entities that control, are controlled by, or are under common
control with that entity. For the purposes of this definition,
"control" means (i) the power, direct or indirect, to cause the
direction or management of such entity, whether by contract or
otherwise, or (ii) ownership of fifty percent (50%) or more of the
outstanding shares, or (iii) beneficial ownership of such entity.

"You" (or "Your") shall mean an individual or Legal Entity
exercising permissions granted by this License.

"Source" form shall mean the preferred form for making modifications,
including but not limited to software source code, documentation
source, and configuration files.

"Object" form shall mean any form resulting from mechanical
transformation or translation of a Source form, including but
not limited to compiled object code, generated documentation,
and conversions to other media types.

"Work" shall mean the work of authorship, whether in Source or
Object form, made available under the License, as indicated by a
copyright notice that is included in or attached to the work
(an example is provided in the Appendix below).

"Derivative Works" shall mean any work, whether in Source or Object
form, that is based on (or derived from) the Work and for which the
editorial revisions, annotations, elaborations, or other modifications
represent, as a whole, an original work of authorship. For the purposes
of this License, Derivative Works shall not include works that remain
separable from, or merely link (or bind by name) to the interfaces of,
the Work and Derivative Works thereof.

"Contribution" shall mean any work of authorship, including
the original version of the Work and any modifications or additions
to that Work or Derivative Works thereof, that is intentionally
submitted to Licensor for inclusion in the Work by the copyright owner
or by an individual or Legal Entity authorized to submit on behalf of
the copyright owner. For the purposes of this definition, "submitted"
means any form of electronic, verbal, or written communication sent
to the Licensor or its representatives, including but not limited to
communication on electronic mailing lists, source code control systems,
and issue tracking systems that are managed by, or on behalf of, the
Licensor for the purpose of discussing and improving the Work, but
excluding communication that is conspicuously marked or otherwise
designated in writing by the copyright owner as "Not a Contribution."

"Contributor" shall mean Licensor and any individual or Legal Entity
on behalf of whom a Contribution has been received by Licensor and
subsequently incorporated within the Work.

2. Grant of Copyright License. Subject to the terms and conditions of
this License, each Contributor hereby grants to You a perpetual,
worldwide, non-exclusive, no-charge, royalty-free, irrevocable
copyright license to reproduce, prepare Derivative Works of,
publicly display, publicly perform, sublicense, and distribute the
Work and such Derivative Works in Source or Object form.

3. Grant of Patent License. Subject to the terms and conditions of
this License, each Contributor hereby grants to You a perpetual,
worldwide, non-exclusive, no-charge, royalty-free, irrevocable
(except as stated in this section) patent license to make, have made,
use, offer to sell, sell, import, and otherwise transfer the Work,
where such license applies only to those patent claims licensable
by such Contributor that are necessarily infringed by their
Contribution(s) alone or by combination of their Contribution(s)
with the Work to which such Contribution(s) was submitted. If You
institute patent litigation against any entity (including a
cross-claim or counterclaim in a lawsuit) alleging that the Work
or a Contribution incorporated within the Work constitutes direct
or contributory patent infringement, then any patent licenses
granted to You under this License for that Work shall terminate
as of the date such litigation is filed.

4. Redistribution. You may reproduce and distribute copies of the
Work or Derivative Works thereof in any medium, with or without
modifications, and in Source or Object form, provided that You
meet the following conditions:

(a) You must give any other recipients of the Work or
Derivative Works a copy of this License; and

(b) You must cause any modified files to carry prominent notices
stating that You changed the files; and

(c) You must retain, in the Source form of any Derivative Works
that You distribute, all copyright, patent, trademark, and
attribution notices from the Source form of the Work,
excluding those notices that do not pertain to any part of
the Derivative Works; and

(d) If the Work includes a "NOTICE" text file as part of its
distribution, then any Derivative Works that You distribute must
include a readable copy of the attribution notices contained
within such NOTICE file, excluding those notices that do not
pertain to any part of the Derivative Works, in at least one
of the following places: within a NOTICE text file distributed
as part of the Derivative Works; within the Source form or
documentation, if provided along with the Derivative Works; or,
within a display generated by the Derivative Works, if and
wherever such third-party notices normally appear. The contents
of the NOTICE file are for informational purposes only and
do not modify the License. You may add Your own attribution
notices within Derivative Works that You distribute, alongside
or as an addendum to the NOTICE text from the Work, provided
that such additional attribution notices cannot be construed
as modifying the License.

You may add Your own copyright statement to Your modifications and
may provide additional or different license terms and conditions
for use, reproduction, or distribution of Your modifications, or
for any such Derivative Works as a whole, provided Your use,
reproduction, and distribution of the Work otherwise complies with
the conditions stated in this License.

5. Submission of Contributions. Unless You explicitly state otherwise,
any Contribution intentionally submitted for inclusion in the Work
by You to the Licensor shall be under the terms and conditions of
this License, without any additional terms or conditions.
Notwithstanding the above, nothing herein shall supersede or modify
the terms of any separate license agreement you may have executed
with Licensor regarding such Contributions.

6. Trademarks. This License does not grant permission to use the trade
names, trademarks, service marks, or product names of the Licensor,
except as required for reasonable and customary use in describing the
origin of the Work and reproducing the content of the NOTICE file.

7. Disclaimer of Warranty. Unless required by applicable law or
agreed to in writing, Licensor provides the Work (and each
Contributor provides its Contributions) on an "AS IS" BASIS,
WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or
implied, including, without limitation, any warranties or conditions
of TITLE, NON-INFRINGEMENT, MERCHANTABILITY, or FITNESS FOR A
PARTICULAR PURPOSE. You are solely responsible for determining the
appropriateness of using or redistributing the Work and assume any
risks associated with Your exercise of permissions under this License.

8. Limitation of Liability. In no event and under no legal theory,
whether in tort (including negligence), contract, or otherwise,
unless required by applicable law (such as deliberate and grossly
negligent acts) or agreed to in writing, shall any Contributor be
liable to You for damages, including any direct, indirect, special,
incidental, or consequential damages of any character arising as a
result of this License or out of the use or inability to use the
Work (including but not limited to damages for loss of goodwill,
work stoppage, computer failure or malfunction, or any and all
other commercial damages or losses), even if such Contributor
has been advised of the possibility of such damages.

9. Accepting Warranty or Additional Liability. While redistributing
the Work or Derivative Works thereof, You may choose to offer,
and charge a fee for, acceptance of support, warranty, indemnity,
or other liability obligations and/or rights consistent with this
License. However, in accepting such obligations, You may act only
on Your own behalf and on Your sole responsibility, not on behalf
of any other Contributor, and only if You agree to indemnify,
defend, and hold each Contributor harmless for any liability
incurred by, or claims asserted against, such Contributor by reason
of your accepting any such warranty or additional liability.

END OF TERMS AND CONDITIONS

APPENDIX: How to apply the Apache License to your work.

To apply the Apache License to your work, attach the following
boilerplate notice, with the fields enclosed by brackets "{}"
replaced with your own identifying information. (Don't include
the brackets!)  The text should be enclosed in the appropriate
comment syntax for the file format. We also recommend that a
file or class name and description of purpose be included on the
same "printed page" as the copyright notice for easier
identification within third-party archives.

Copyright 2017 The OpenZipkin Authors

Licensed under the Apache License, Version 2.0 (the "License");
you may not use this file except in compliance with the License.
You may obtain a copy of the License at

http://www.apache.org/licenses/LICENSE-2.0

Unless required by applicable law or agreed to in writing, software
distributed under the License is distributed on an "AS IS" BASIS,
WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
See the License for the specific language governing permissions and
limitations under the License.
```

<a id="go-legal-text-g027"></a>

### Go legal text G027

SHA-256: `26844e4b53c5adec04e557fd7dfef281cc0205a7d355626b1c68b778b99e9e7b`. Origins:

- `github.com/pelletier/go-toml/v2` v2.4.0: [LICENSE](https://proxy.golang.org/github.com/pelletier/go-toml/v2/@v/v2.4.0.zip)

```text
The MIT License (MIT)

go-toml v2
Copyright (c) 2021 - 2023 Thomas Pelletier

Permission is hereby granted, free of charge, to any person obtaining a copy
of this software and associated documentation files (the "Software"), to deal
in the Software without restriction, including without limitation the rights
to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
copies of the Software, and to permit persons to whom the Software is
furnished to do so, subject to the following conditions:

The above copyright notice and this permission notice shall be included in all
copies or substantial portions of the Software.

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
SOFTWARE.
```

<a id="go-legal-text-g070"></a>

### Go legal text G070

SHA-256: `8d427fd87bc9579ea368fde3d49f9ca22eac857f91a9dec7e3004bdfab7dee86`. Origins:

- `github.com/pkg/errors` v0.9.1: [LICENSE](https://proxy.golang.org/github.com/pkg/errors/@v/v0.9.1.zip)

```text
Copyright (c) 2015, Dave Cheney <dave@cheney.net>
All rights reserved.

Redistribution and use in source and binary forms, with or without
modification, are permitted provided that the following conditions are met:

* Redistributions of source code must retain the above copyright notice, this
  list of conditions and the following disclaimer.

* Redistributions in binary form must reproduce the above copyright notice,
  this list of conditions and the following disclaimer in the documentation
  and/or other materials provided with the distribution.

THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS "AS IS"
AND ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE
IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE ARE
DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT HOLDER OR CONTRIBUTORS BE LIABLE
FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL
DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR
SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER
CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY,
OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE
OF THIS SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.
```

<a id="go-legal-text-g031"></a>

### Go legal text G031

SHA-256: `2eb550be6801c1ea434feba53bf6d12e7c71c90253e0a9de4a4f46cf88b56477`. Origins:

- `github.com/pmezard/go-difflib` v1.0.1-0.20181226105442-5d4384ee4fb2: [LICENSE](https://proxy.golang.org/github.com/pmezard/go-difflib/@v/v1.0.1-0.20181226105442-5d4384ee4fb2.zip)

```text
Copyright (c) 2013, Patrick Mezard
All rights reserved.

Redistribution and use in source and binary forms, with or without
modification, are permitted provided that the following conditions are
met:

    Redistributions of source code must retain the above copyright
notice, this list of conditions and the following disclaimer.
    Redistributions in binary form must reproduce the above copyright
notice, this list of conditions and the following disclaimer in the
documentation and/or other materials provided with the distribution.
    The names of its contributors may not be used to endorse or promote
products derived from this software without specific prior written
permission.

THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS "AS
IS" AND ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED
TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A
PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT
HOLDER OR CONTRIBUTORS BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL,
SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT LIMITED
TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES; LOSS OF USE, DATA, OR
PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY OF
LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING
NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE OF THIS
SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.
```

<a id="go-legal-text-g065"></a>

### Go legal text G065

SHA-256: `8548bfb2bc1913396df38c548a6ec60b8dbcac3b0800485a40569c8bdd184471`. Origins:

- `github.com/prometheus/client_golang` v1.23.2: [NOTICE](https://proxy.golang.org/github.com/prometheus/client_golang/@v/v1.23.2.zip)

```text
Prometheus instrumentation library for Go applications
Copyright 2012-2015 The Prometheus Authors

This product includes software developed at
SoundCloud Ltd. (http://soundcloud.com/).


The following components are included in this product:

perks - a fork of https://github.com/bmizerany/perks
https://github.com/beorn7/perks
Copyright 2013-2015 Blake Mizerany, Björn Rabenstein
See https://github.com/beorn7/perks/blob/master/README.md for license details.

Go support for Protocol Buffers - Google's data interchange format
http://github.com/golang/protobuf/
Copyright 2010 The Go Authors
See source code for license details.
```

<a id="go-legal-text-g110"></a>

### Go legal text G110

SHA-256: `dfffb9ec737eaba985243e95a549bd8c3a4d462032cfe603e2a6704c346427b6`. Origins:

- `github.com/prometheus/client_golang` v1.23.2: [internal/github.com/golang/gddo/LICENSE](https://proxy.golang.org/github.com/prometheus/client_golang/@v/v1.23.2.zip)

```text
Copyright (c) 2013 The Go Authors. All rights reserved.

Redistribution and use in source and binary forms, with or without
modification, are permitted provided that the following conditions are
met:

   * Redistributions of source code must retain the above copyright
notice, this list of conditions and the following disclaimer.
   * Redistributions in binary form must reproduce the above
copyright notice, this list of conditions and the following disclaimer
in the documentation and/or other materials provided with the
distribution.
   * Neither the name of Google Inc. nor the names of its
contributors may be used to endorse or promote products derived from
this software without specific prior written permission.

THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS
"AS IS" AND ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT
LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR
A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT
OWNER OR CONTRIBUTORS BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL,
SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT
LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES; LOSS OF USE,
DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND ON ANY
THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT
(INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE
OF THIS SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.
```

<a id="go-legal-text-g058"></a>

### Go legal text G058

SHA-256: `6c79faa15168885fb88a316ae0df18f486deafddefdc16826cdc56dfbd421e24`. Origins:

- `github.com/prometheus/client_model` v0.6.2: [NOTICE](https://proxy.golang.org/github.com/prometheus/client_model/@v/v0.6.2.zip)

```text
Data model artifacts for Prometheus.
Copyright 2012-2015 The Prometheus Authors

This product includes software developed at
SoundCloud Ltd. (http://soundcloud.com/).
```

<a id="go-legal-text-g049"></a>

### Go legal text G049

SHA-256: `600244c8052c8c1d307043fafacbc83b429c171dd8e08f5a537c0a54014585ee`. Origins:

- `github.com/prometheus/common` v0.69.0: [NOTICE](https://proxy.golang.org/github.com/prometheus/common/@v/v0.69.0.zip)

```text
Common libraries shared by Prometheus Go components.
Copyright 2015 The Prometheus Authors

This product includes software developed at
SoundCloud Ltd. (http://soundcloud.com/).
```

<a id="go-legal-text-g039"></a>

### Go legal text G039

SHA-256: `497fcdb9fae55924c12f55f05cbe83a096662e5c591d79b74e4a8f23bab5ffee`. Origins:

- `github.com/prometheus/procfs` v0.20.1: [NOTICE](https://proxy.golang.org/github.com/prometheus/procfs/@v/v0.20.1.zip)

```text
procfs provides functions to retrieve system, kernel and process
metrics from the pseudo-filesystem proc.

Copyright 2014-2015 The Prometheus Authors

This product includes software developed at
SoundCloud Ltd. (http://soundcloud.com/).
```

<a id="go-legal-text-g018"></a>

### Go legal text G018

SHA-256: `1b6a897efd39b20b3cdce8cd306160d115dbded39d855ceeffe21dc11e4d53df`. Origins:

- `github.com/quic-go/qpack` v0.6.0: [LICENSE.md](https://proxy.golang.org/github.com/quic-go/qpack/@v/v0.6.0.zip)

```text
Copyright 2019 Marten Seemann

Permission is hereby granted, free of charge, to any person obtaining a copy of this software and associated documentation files (the "Software"), to deal in the Software without restriction, including without limitation the rights to use, copy, modify, merge, publish, distribute, sublicense, and/or sell copies of the Software, and to permit persons to whom the Software is furnished to do so, subject to the following conditions:

The above copyright notice and this permission notice shall be included in all copies or substantial portions of the Software.

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM, OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE SOFTWARE.
```

<a id="go-legal-text-g060"></a>

### Go legal text G060

SHA-256: `77d0b7b53e8abb84cf4dd3f9945a7fdf27044240d2e8023966a721a9a46fe96e`. Origins:

- `github.com/quic-go/quic-go` v0.60.0: [LICENSE](https://proxy.golang.org/github.com/quic-go/quic-go/@v/v0.60.0.zip)

```text
MIT License

Copyright (c) 2016 the quic-go authors & Google, Inc.

Permission is hereby granted, free of charge, to any person obtaining a copy
of this software and associated documentation files (the "Software"), to deal
in the Software without restriction, including without limitation the rights
to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
copies of the Software, and to permit persons to whom the Software is
furnished to do so, subject to the following conditions:

The above copyright notice and this permission notice shall be included in all
copies or substantial portions of the Software.

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
SOFTWARE.
```

<a id="go-legal-text-g028"></a>

### Go legal text G028

SHA-256: `2c8d4ff4244edf09d99dcd428d278e47a059d24f0030b38c1ab38e93b1c8030c`. Origins:

- `github.com/quic-go/quic-go` v0.60.0: [assets/LICENSE.md](https://proxy.golang.org/github.com/quic-go/quic-go/@v/v0.60.0.zip)

```text
# quic-go Logo and Trademark Usage Policy

## Exception to Main License

The files in this directory (collectively, "Brand Assets") are **excluded** from the quic-go project's main MIT License. These assets are protected by copyright and trademark laws.

## Permitted Use

You are granted a limited, non-exclusive license to use these Brand Assets solely for the following purposes:

- **Editorial and Press:** You may use the Brand Assets in blog posts, news articles, video reviews, and public presentations that discuss, review, or reference the quic-go project.

- **Reference:** You may use the Brand Assets to indicate your project's compatibility with or dependence on quic-go (e.g., "Powered by quic-go").

## Restricted Use

You may NOT:

- **Modification:** Modify the Brand Assets in any way (including changing colors, aspect ratio, or obscuring the image). Resizing the image while maintaining the original aspect ratio is permitted.

- **No Branding:** Use the Brand Assets as the logo, icon, or mascot for your own project, product, or service.

- **No Endorsement:** Use the Brand Assets in a way that suggests your project is officially sponsored by, endorsed by, or affiliated with the quic-go maintainers.

## Termination

The quic-go project reserves the right to revoke this authorization at any time if usage is found to be confusing, misleading, or detrimental to the project's reputation.
```

<a id="go-legal-text-g032"></a>

### Go legal text G032

SHA-256: `2fa3f380607b1591fa0e0962f70a1bd8b648427832abb647be85b141574be3c2`. Origins:

- `github.com/rabbitmq/amqp091-go` v1.12.0: [LICENSE](https://proxy.golang.org/github.com/rabbitmq/amqp091-go/@v/v1.12.0.zip)

```text
AMQP 0-9-1 Go Client
Copyright (c) 2021 VMware, Inc. or its affiliates. All Rights Reserved.

Copyright (c) 2012-2021, Sean Treadway, SoundCloud Ltd.

Redistribution and use in source and binary forms, with or without
modification, are permitted provided that the following conditions are met:

Redistributions of source code must retain the above copyright notice, this
list of conditions and the following disclaimer.

Redistributions in binary form must reproduce the above copyright notice, this
list of conditions and the following disclaimer in the documentation and/or
other materials provided with the distribution.

THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS "AS IS" AND
ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE IMPLIED
WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE ARE
DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT HOLDER OR CONTRIBUTORS BE LIABLE
FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL
DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR
SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER
CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY,
OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE
OF THIS SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.
```

<a id="go-legal-text-g078"></a>

### Go legal text G078

SHA-256: `a3a7dff87da3927db65cb4c87b1cfbc96ca2755704461a485d457be7ae300a86`. Origins:

- `github.com/redis/go-redis/extra/rediscmd/v9` v9.20.1: [LICENSE](https://proxy.golang.org/github.com/redis/go-redis/extra/rediscmd/v9/@v/v9.20.1.zip)
- `github.com/redis/go-redis/extra/redisotel/v9` v9.20.1: [LICENSE](https://proxy.golang.org/github.com/redis/go-redis/extra/redisotel/v9/@v/v9.20.1.zip)
- `github.com/redis/go-redis/extra/redisprometheus/v9` v9.20.1: [LICENSE](https://proxy.golang.org/github.com/redis/go-redis/extra/redisprometheus/v9/@v/v9.20.1.zip)
- `github.com/redis/go-redis/v9` v9.20.1: [LICENSE](https://proxy.golang.org/github.com/redis/go-redis/v9/@v/v9.20.1.zip)

```text
Copyright (c) 2013 The github.com/redis/go-redis Authors.
All rights reserved.

Redistribution and use in source and binary forms, with or without
modification, are permitted provided that the following conditions are
met:

   * Redistributions of source code must retain the above copyright
notice, this list of conditions and the following disclaimer.
   * Redistributions in binary form must reproduce the above
copyright notice, this list of conditions and the following disclaimer
in the documentation and/or other materials provided with the
distribution.

THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS
"AS IS" AND ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT
LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR
A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT
OWNER OR CONTRIBUTORS BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL,
SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT
LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES; LOSS OF USE,
DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND ON ANY
THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT
(INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE
OF THIS SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.
```

<a id="go-legal-text-g095"></a>

### Go legal text G095

SHA-256: `c20baf77c987828298a817cd1a23187185a8954efa5e782fc2b1c1e41b232d1a`. Origins:

- `github.com/roadrunner-server/amqp/v5` v5.2.3: [LICENSE](https://proxy.golang.org/github.com/roadrunner-server/amqp/v5/@v/v5.2.3.zip)
- `github.com/roadrunner-server/beanstalk/v5` v5.1.9: [LICENSE](https://proxy.golang.org/github.com/roadrunner-server/beanstalk/v5/@v/v5.1.9.zip)
- `github.com/roadrunner-server/http/v5` v5.3.0: [LICENSE](https://proxy.golang.org/github.com/roadrunner-server/http/v5/@v/v5.3.0.zip)
- `github.com/roadrunner-server/informer/v5` v5.1.9: [LICENSE](https://proxy.golang.org/github.com/roadrunner-server/informer/v5/@v/v5.1.9.zip)
- `github.com/roadrunner-server/jobs/v5` v5.1.9: [LICENSE](https://proxy.golang.org/github.com/roadrunner-server/jobs/v5/@v/v5.1.9.zip)
- `github.com/roadrunner-server/memory/v5` v5.2.9: [LICENSE](https://proxy.golang.org/github.com/roadrunner-server/memory/v5/@v/v5.2.9.zip)
- `github.com/roadrunner-server/send/v5` v5.2.0: [LICENSE](https://proxy.golang.org/github.com/roadrunner-server/send/v5/@v/v5.2.0.zip)
- `github.com/roadrunner-server/static/v5` v5.2.0: [LICENSE](https://proxy.golang.org/github.com/roadrunner-server/static/v5/@v/v5.2.0.zip)
- `github.com/roadrunner-server/tcp/v5` v5.1.9: [LICENSE](https://proxy.golang.org/github.com/roadrunner-server/tcp/v5/@v/v5.1.9.zip)

```text
MIT License

Copyright (c) 2023 Spiral Scout

Permission is hereby granted, free of charge, to any person obtaining a copy
of this software and associated documentation files (the "Software"), to deal
in the Software without restriction, including without limitation the rights
to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
copies of the Software, and to permit persons to whom the Software is
furnished to do so, subject to the following conditions:

The above copyright notice and this permission notice shall be included in all
copies or substantial portions of the Software.

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
SOFTWARE.
```

<a id="go-legal-text-g047"></a>

### Go legal text G047

SHA-256: `5dac095e4f23307ce47b2594cbc4c7aa076532a1cf403745b357f5bc20f0a9a8`. Origins:

- `github.com/roadrunner-server/api/v4` v4.24.0: [LICENSE](https://proxy.golang.org/github.com/roadrunner-server/api/v4/@v/v4.24.0.zip)
- `github.com/roadrunner-server/boltdb/v5` v5.1.9: [LICENSE](https://proxy.golang.org/github.com/roadrunner-server/boltdb/v5/@v/v5.1.9.zip)
- `github.com/roadrunner-server/config/v5` v5.1.9: [LICENSE](https://proxy.golang.org/github.com/roadrunner-server/config/v5/@v/v5.1.9.zip)
- `github.com/roadrunner-server/fileserver/v5` v5.1.9: [LICENSE](https://proxy.golang.org/github.com/roadrunner-server/fileserver/v5/@v/v5.1.9.zip)
- `github.com/roadrunner-server/grpc/v5` v5.3.0: [LICENSE](https://proxy.golang.org/github.com/roadrunner-server/grpc/v5/@v/v5.3.0.zip)
- `github.com/roadrunner-server/gzip/v5` v5.3.0: [LICENSE](https://proxy.golang.org/github.com/roadrunner-server/gzip/v5/@v/v5.3.0.zip)
- `github.com/roadrunner-server/headers/v5` v5.2.0: [LICENSE](https://proxy.golang.org/github.com/roadrunner-server/headers/v5/@v/v5.2.0.zip)
- `github.com/roadrunner-server/kv/v5` v5.2.9: [LICENSE](https://proxy.golang.org/github.com/roadrunner-server/kv/v5/@v/v5.2.9.zip)
- `github.com/roadrunner-server/logger/v5` v5.1.9: [LICENSE](https://proxy.golang.org/github.com/roadrunner-server/logger/v5/@v/v5.1.9.zip)
- `github.com/roadrunner-server/memcached/v5` v5.1.9: [LICENSE](https://proxy.golang.org/github.com/roadrunner-server/memcached/v5/@v/v5.1.9.zip)
- `github.com/roadrunner-server/metrics/v5` v5.2.0: [LICENSE](https://proxy.golang.org/github.com/roadrunner-server/metrics/v5/@v/v5.2.0.zip)
- `github.com/roadrunner-server/nats/v5` v5.1.9: [LICENSE](https://proxy.golang.org/github.com/roadrunner-server/nats/v5/@v/v5.1.9.zip)
- `github.com/roadrunner-server/prometheus/v5` v5.2.0: [LICENSE](https://proxy.golang.org/github.com/roadrunner-server/prometheus/v5/@v/v5.2.0.zip)
- `github.com/roadrunner-server/redis/v5` v5.1.10: [LICENSE](https://proxy.golang.org/github.com/roadrunner-server/redis/v5/@v/v5.1.10.zip)
- `github.com/roadrunner-server/resetter/v5` v5.1.9: [LICENSE](https://proxy.golang.org/github.com/roadrunner-server/resetter/v5/@v/v5.1.9.zip)
- `github.com/roadrunner-server/rpc/v5` v5.1.9: [LICENSE](https://proxy.golang.org/github.com/roadrunner-server/rpc/v5/@v/v5.1.9.zip)
- `github.com/roadrunner-server/server/v5` v5.2.10: [LICENSE](https://proxy.golang.org/github.com/roadrunner-server/server/v5/@v/v5.2.10.zip)
- `github.com/roadrunner-server/service/v5` v5.1.9: [LICENSE](https://proxy.golang.org/github.com/roadrunner-server/service/v5/@v/v5.1.9.zip)
- `github.com/roadrunner-server/sqs/v5` v5.1.9: [LICENSE](https://proxy.golang.org/github.com/roadrunner-server/sqs/v5/@v/v5.1.9.zip)
- `github.com/roadrunner-server/status/v5` v5.1.9: [LICENSE](https://proxy.golang.org/github.com/roadrunner-server/status/v5/@v/v5.1.9.zip)

```text
MIT License

Copyright (c) 2022 Spiral Scout

Permission is hereby granted, free of charge, to any person obtaining a copy
of this software and associated documentation files (the "Software"), to deal
in the Software without restriction, including without limitation the rights
to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
copies of the Software, and to permit persons to whom the Software is
furnished to do so, subject to the following conditions:

The above copyright notice and this permission notice shall be included in all
copies or substantial portions of the Software.

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
SOFTWARE.
```

<a id="go-legal-text-g093"></a>

### Go legal text G093

SHA-256: `bdb123cc903b3bbeff7bd42082947131e51cd657b09c3869b3ae15fab7a2afeb`. Origins:

- `github.com/roadrunner-server/app-logger/v5` v5.1.9: [LICENSE](https://proxy.golang.org/github.com/roadrunner-server/app-logger/v5/@v/v5.1.9.zip)
- `github.com/roadrunner-server/centrifuge/v5` v5.1.9: [LICENSE](https://proxy.golang.org/github.com/roadrunner-server/centrifuge/v5/@v/v5.1.9.zip)
- `github.com/roadrunner-server/kafka/v5` v5.2.5: [LICENSE](https://proxy.golang.org/github.com/roadrunner-server/kafka/v5/@v/v5.2.5.zip)
- `github.com/roadrunner-server/otel/v5` v5.6.0: [LICENSE](https://proxy.golang.org/github.com/roadrunner-server/otel/v5/@v/v5.6.0.zip)
- `github.com/roadrunner-server/proxy_ip_parser/v5` v5.1.9: [LICENSE](https://proxy.golang.org/github.com/roadrunner-server/proxy_ip_parser/v5/@v/v5.1.9.zip)

```text
MIT License

Copyright (c) 2022 RoadRunner

Permission is hereby granted, free of charge, to any person obtaining a copy
of this software and associated documentation files (the "Software"), to deal
in the Software without restriction, including without limitation the rights
to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
copies of the Software, and to permit persons to whom the Software is
furnished to do so, subject to the following conditions:

The above copyright notice and this permission notice shall be included in all
copies or substantial portions of the Software.

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
SOFTWARE.
```

<a id="go-legal-text-g054"></a>

### Go legal text G054

SHA-256: `6991cbbdbf6941be08754e78c30e1e99f4e46a7a76befee76def322a79780fd2`. Origins:

- `github.com/roadrunner-server/context` v1.3.0: [LICENSE](https://proxy.golang.org/github.com/roadrunner-server/context/@v/v1.3.0.zip)
- `github.com/roadrunner-server/events` v1.0.1: [LICENSE](https://proxy.golang.org/github.com/roadrunner-server/events/@v/v1.0.1.zip)
- `github.com/roadrunner-server/priority_queue` v1.0.6: [LICENSE](https://proxy.golang.org/github.com/roadrunner-server/priority_queue/@v/v1.0.6.zip)

```text
MIT License

Copyright (c) 2024 RoadRunner

Permission is hereby granted, free of charge, to any person obtaining a copy
of this software and associated documentation files (the "Software"), to deal
in the Software without restriction, including without limitation the rights
to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
copies of the Software, and to permit persons to whom the Software is
furnished to do so, subject to the following conditions:

The above copyright notice and this permission notice shall be included in all
copies or substantial portions of the Software.

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
SOFTWARE.
```

<a id="go-legal-text-g083"></a>

### Go legal text G083

SHA-256: `a7f715b531c14d765e07c5c814cf509391f1f32f4c1d648f7fd12dc275227cb4`. Origins:

- `github.com/roadrunner-server/endure/v2` v2.6.2: [LICENCE (exact upstream tag)](https://raw.githubusercontent.com/roadrunner-server/endure/9fab280347d79bd62951b431ce2f148e133e522b/LICENCE)

```text
MIT License

Copyright (c) 2020 Spiral Scout

Permission is hereby granted, free of charge, to any person obtaining a copy
of this software and associated documentation files (the "Software"), to deal
in the Software without restriction, including without limitation the rights
to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
copies of the Software, and to permit persons to whom the Software is
furnished to do so, subject to the following conditions:

The above copyright notice and this permission notice shall be included in all
copies or substantial portions of the Software.

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
SOFTWARE.
```

<a id="go-legal-text-g061"></a>

### Go legal text G061

SHA-256: `78089b5d974fa439ec6641d360966de9367d4267b1fac1cd0827ef7b0ee259de`. Origins:

- `github.com/roadrunner-server/errors` v1.5.0: [LICENSE](https://proxy.golang.org/github.com/roadrunner-server/errors/@v/v1.5.0.zip)

```text
MIT License

Copyright (c) 2025 Spiral Scout

Permission is hereby granted, free of charge, to any person obtaining a copy
of this software and associated documentation files (the "Software"), to deal
in the Software without restriction, including without limitation the rights
to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
copies of the Software, and to permit persons to whom the Software is
furnished to do so, subject to the following conditions:

The above copyright notice and this permission notice shall be included in all
copies or substantial portions of the Software.

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
SOFTWARE.
```

<a id="go-legal-text-g107"></a>

### Go legal text G107

SHA-256: `dd197d5e4fb013c4c38a89887a9e29ca4e94d0ccc4842123cf26ca315637781c`. Origins:

- `github.com/roadrunner-server/google-pub-sub/v5` v5.1.9: [LICENSE](https://proxy.golang.org/github.com/roadrunner-server/google-pub-sub/v5/@v/v5.1.9.zip)
- `github.com/roadrunner-server/lock/v5` v5.1.9: [LICENSE](https://proxy.golang.org/github.com/roadrunner-server/lock/v5/@v/v5.1.9.zip)

```text
MIT License

Copyright (c) 2023 RoadRunner

Permission is hereby granted, free of charge, to any person obtaining a copy
of this software and associated documentation files (the "Software"), to deal
in the Software without restriction, including without limitation the rights
to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
copies of the Software, and to permit persons to whom the Software is
furnished to do so, subject to the following conditions:

The above copyright notice and this permission notice shall be included in all
copies or substantial portions of the Software.

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
SOFTWARE.
```

<a id="go-legal-text-g043"></a>

### Go legal text G043

SHA-256: `5b099532a96996d06fa0f3fc9ec75554ba6f6bbe1a7a370b570601ebba6092a7`. Origins:

- `github.com/roadrunner-server/goridge/v3` v3.8.3: [LICENSE](https://proxy.golang.org/github.com/roadrunner-server/goridge/v3/@v/v3.8.3.zip)

```text
MIT License

Copyright (c) 2023 SpiralScout

Permission is hereby granted, free of charge, to any person obtaining a copy
of this software and associated documentation files (the "Software"), to deal
in the Software without restriction, including without limitation the rights
to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
copies of the Software, and to permit persons to whom the Software is
furnished to do so, subject to the following conditions:

The above copyright notice and this permission notice shall be included in all
copies or substantial portions of the Software.

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
SOFTWARE.
```

<a id="go-legal-text-g075"></a>

### Go legal text G075

SHA-256: `99dfc67160f59949580bda32e2e246d868afb9195b70d555f8840ba6c37b6b57`. Origins:

- `github.com/roadrunner-server/pool` v1.1.3: [LICENSE](https://proxy.golang.org/github.com/roadrunner-server/pool/@v/v1.1.3.zip)

```text
MIT License

Copyright (c) 2024 Spiral Scout

Permission is hereby granted, free of charge, to any person obtaining a copy
of this software and associated documentation files (the "Software"), to deal
in the Software without restriction, including without limitation the rights
to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
copies of the Software, and to permit persons to whom the Software is
furnished to do so, subject to the following conditions:

The above copyright notice and this permission notice shall be included in all
copies or substantial portions of the Software.

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
SOFTWARE.
```

<a id="go-legal-text-g001"></a>

### Go legal text G001

SHA-256: `0474e29e21700ee7c3d4acd068dca7f51ecb97f03b14c01fe24198978246164b`. Origins:

- `github.com/roadrunner-server/tcplisten` v1.5.2: [LICENSE](https://proxy.golang.org/github.com/roadrunner-server/tcplisten/@v/v1.5.2.zip)

```text
MIT License

Copyright (c) 2021 Spiral Scout

Permission is hereby granted, free of charge, to any person obtaining a copy
of this software and associated documentation files (the "Software"), to deal
in the Software without restriction, including without limitation the rights
to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
copies of the Software, and to permit persons to whom the Software is
furnished to do so, subject to the following conditions:

The above copyright notice and this permission notice shall be included in all
copies or substantial portions of the Software.

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
SOFTWARE.
```

<a id="go-legal-text-g072"></a>

### Go legal text G072

SHA-256: `8f650581478707182f3306d9a86676d4e03e2d4edc868344378cf4d6fe6c927a`. Origins:

- `github.com/robfig/cron` v1.2.0: [LICENSE](https://proxy.golang.org/github.com/robfig/cron/@v/v1.2.0.zip)

```text
Copyright (C) 2012 Rob Figueiredo
All Rights Reserved.

MIT LICENSE

Permission is hereby granted, free of charge, to any person obtaining a copy of
this software and associated documentation files (the "Software"), to deal in
the Software without restriction, including without limitation the rights to
use, copy, modify, merge, publish, distribute, sublicense, and/or sell copies of
the Software, and to permit persons to whom the Software is furnished to do so,
subject to the following conditions:

The above copyright notice and this permission notice shall be included in all
copies or substantial portions of the Software.

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY, FITNESS
FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE AUTHORS OR
COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER LIABILITY, WHETHER
IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM, OUT OF OR IN
CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE SOFTWARE.
```

<a id="go-legal-text-g096"></a>

### Go legal text G096

SHA-256: `c21f3b628875c093885ba085d13959b5e78841d64a8fe76c10ba7293aec32fc7`. Origins:

- `github.com/rs/cors` v1.11.1: [LICENSE](https://proxy.golang.org/github.com/rs/cors/@v/v1.11.1.zip)

```text
Copyright (c) 2014 Olivier Poitrey <rs@dailymotion.com>

Permission is hereby granted, free of charge, to any person obtaining a copy
of this software and associated documentation files (the "Software"), to deal
in the Software without restriction, including without limitation the rights
to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
copies of the Software, and to permit persons to whom the Software is furnished
to do so, subject to the following conditions:

The above copyright notice and this permission notice shall be included in all
copies or substantial portions of the Software.

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
THE SOFTWARE.
```

<a id="go-legal-text-g098"></a>

### Go legal text G098

SHA-256: `c4129f2c2a5eb20e4ac7b2535dab899fde485865106a15189726e2db68ff3483`. Origins:

- `github.com/sagikazarmark/locafero` v0.12.0: [LICENSE](https://proxy.golang.org/github.com/sagikazarmark/locafero/@v/v0.12.0.zip)

```text
Copyright (c) 2023 Márk Sági-Kazár <mark.sagikazar@gmail.com>

Permission is hereby granted, free of charge, to any person obtaining a copy
of this software and associated documentation files (the "Software"), to deal
in the Software without restriction, including without limitation the rights
to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
copies of the Software, and to permit persons to whom the Software is furnished
to do so, subject to the following conditions:

The above copyright notice and this permission notice shall be included in all
copies or substantial portions of the Software.

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
THE SOFTWARE.
```

<a id="go-legal-text-g048"></a>

### Go legal text G048

SHA-256: `5e3400b93bbb099e83e52bab885e7441750673c21f97988ca3f1240639b63283`. Origins:

- `github.com/spf13/afero` v1.15.0: [LICENSE.txt](https://proxy.golang.org/github.com/spf13/afero/@v/v1.15.0.zip)
- `github.com/spf13/cobra` v1.10.2: [LICENSE.txt](https://proxy.golang.org/github.com/spf13/cobra/@v/v1.10.2.zip)

```text
                                Apache License
                           Version 2.0, January 2004
                        http://www.apache.org/licenses/

   TERMS AND CONDITIONS FOR USE, REPRODUCTION, AND DISTRIBUTION

   1. Definitions.

      "License" shall mean the terms and conditions for use, reproduction,
      and distribution as defined by Sections 1 through 9 of this document.

      "Licensor" shall mean the copyright owner or entity authorized by
      the copyright owner that is granting the License.

      "Legal Entity" shall mean the union of the acting entity and all
      other entities that control, are controlled by, or are under common
      control with that entity. For the purposes of this definition,
      "control" means (i) the power, direct or indirect, to cause the
      direction or management of such entity, whether by contract or
      otherwise, or (ii) ownership of fifty percent (50%) or more of the
      outstanding shares, or (iii) beneficial ownership of such entity.

      "You" (or "Your") shall mean an individual or Legal Entity
      exercising permissions granted by this License.

      "Source" form shall mean the preferred form for making modifications,
      including but not limited to software source code, documentation
      source, and configuration files.

      "Object" form shall mean any form resulting from mechanical
      transformation or translation of a Source form, including but
      not limited to compiled object code, generated documentation,
      and conversions to other media types.

      "Work" shall mean the work of authorship, whether in Source or
      Object form, made available under the License, as indicated by a
      copyright notice that is included in or attached to the work
      (an example is provided in the Appendix below).

      "Derivative Works" shall mean any work, whether in Source or Object
      form, that is based on (or derived from) the Work and for which the
      editorial revisions, annotations, elaborations, or other modifications
      represent, as a whole, an original work of authorship. For the purposes
      of this License, Derivative Works shall not include works that remain
      separable from, or merely link (or bind by name) to the interfaces of,
      the Work and Derivative Works thereof.

      "Contribution" shall mean any work of authorship, including
      the original version of the Work and any modifications or additions
      to that Work or Derivative Works thereof, that is intentionally
      submitted to Licensor for inclusion in the Work by the copyright owner
      or by an individual or Legal Entity authorized to submit on behalf of
      the copyright owner. For the purposes of this definition, "submitted"
      means any form of electronic, verbal, or written communication sent
      to the Licensor or its representatives, including but not limited to
      communication on electronic mailing lists, source code control systems,
      and issue tracking systems that are managed by, or on behalf of, the
      Licensor for the purpose of discussing and improving the Work, but
      excluding communication that is conspicuously marked or otherwise
      designated in writing by the copyright owner as "Not a Contribution."

      "Contributor" shall mean Licensor and any individual or Legal Entity
      on behalf of whom a Contribution has been received by Licensor and
      subsequently incorporated within the Work.

   2. Grant of Copyright License. Subject to the terms and conditions of
      this License, each Contributor hereby grants to You a perpetual,
      worldwide, non-exclusive, no-charge, royalty-free, irrevocable
      copyright license to reproduce, prepare Derivative Works of,
      publicly display, publicly perform, sublicense, and distribute the
      Work and such Derivative Works in Source or Object form.

   3. Grant of Patent License. Subject to the terms and conditions of
      this License, each Contributor hereby grants to You a perpetual,
      worldwide, non-exclusive, no-charge, royalty-free, irrevocable
      (except as stated in this section) patent license to make, have made,
      use, offer to sell, sell, import, and otherwise transfer the Work,
      where such license applies only to those patent claims licensable
      by such Contributor that are necessarily infringed by their
      Contribution(s) alone or by combination of their Contribution(s)
      with the Work to which such Contribution(s) was submitted. If You
      institute patent litigation against any entity (including a
      cross-claim or counterclaim in a lawsuit) alleging that the Work
      or a Contribution incorporated within the Work constitutes direct
      or contributory patent infringement, then any patent licenses
      granted to You under this License for that Work shall terminate
      as of the date such litigation is filed.

   4. Redistribution. You may reproduce and distribute copies of the
      Work or Derivative Works thereof in any medium, with or without
      modifications, and in Source or Object form, provided that You
      meet the following conditions:

      (a) You must give any other recipients of the Work or
          Derivative Works a copy of this License; and

      (b) You must cause any modified files to carry prominent notices
          stating that You changed the files; and

      (c) You must retain, in the Source form of any Derivative Works
          that You distribute, all copyright, patent, trademark, and
          attribution notices from the Source form of the Work,
          excluding those notices that do not pertain to any part of
          the Derivative Works; and

      (d) If the Work includes a "NOTICE" text file as part of its
          distribution, then any Derivative Works that You distribute must
          include a readable copy of the attribution notices contained
          within such NOTICE file, excluding those notices that do not
          pertain to any part of the Derivative Works, in at least one
          of the following places: within a NOTICE text file distributed
          as part of the Derivative Works; within the Source form or
          documentation, if provided along with the Derivative Works; or,
          within a display generated by the Derivative Works, if and
          wherever such third-party notices normally appear. The contents
          of the NOTICE file are for informational purposes only and
          do not modify the License. You may add Your own attribution
          notices within Derivative Works that You distribute, alongside
          or as an addendum to the NOTICE text from the Work, provided
          that such additional attribution notices cannot be construed
          as modifying the License.

      You may add Your own copyright statement to Your modifications and
      may provide additional or different license terms and conditions
      for use, reproduction, or distribution of Your modifications, or
      for any such Derivative Works as a whole, provided Your use,
      reproduction, and distribution of the Work otherwise complies with
      the conditions stated in this License.

   5. Submission of Contributions. Unless You explicitly state otherwise,
      any Contribution intentionally submitted for inclusion in the Work
      by You to the Licensor shall be under the terms and conditions of
      this License, without any additional terms or conditions.
      Notwithstanding the above, nothing herein shall supersede or modify
      the terms of any separate license agreement you may have executed
      with Licensor regarding such Contributions.

   6. Trademarks. This License does not grant permission to use the trade
      names, trademarks, service marks, or product names of the Licensor,
      except as required for reasonable and customary use in describing the
      origin of the Work and reproducing the content of the NOTICE file.

   7. Disclaimer of Warranty. Unless required by applicable law or
      agreed to in writing, Licensor provides the Work (and each
      Contributor provides its Contributions) on an "AS IS" BASIS,
      WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or
      implied, including, without limitation, any warranties or conditions
      of TITLE, NON-INFRINGEMENT, MERCHANTABILITY, or FITNESS FOR A
      PARTICULAR PURPOSE. You are solely responsible for determining the
      appropriateness of using or redistributing the Work and assume any
      risks associated with Your exercise of permissions under this License.

   8. Limitation of Liability. In no event and under no legal theory,
      whether in tort (including negligence), contract, or otherwise,
      unless required by applicable law (such as deliberate and grossly
      negligent acts) or agreed to in writing, shall any Contributor be
      liable to You for damages, including any direct, indirect, special,
      incidental, or consequential damages of any character arising as a
      result of this License or out of the use or inability to use the
      Work (including but not limited to damages for loss of goodwill,
      work stoppage, computer failure or malfunction, or any and all
      other commercial damages or losses), even if such Contributor
      has been advised of the possibility of such damages.

   9. Accepting Warranty or Additional Liability. While redistributing
      the Work or Derivative Works thereof, You may choose to offer,
      and charge a fee for, acceptance of support, warranty, indemnity,
      or other liability obligations and/or rights consistent with this
      License. However, in accepting such obligations, You may act only
      on Your own behalf and on Your sole responsibility, not on behalf
      of any other Contributor, and only if You agree to indemnify,
      defend, and hold each Contributor harmless for any liability
      incurred by, or claims asserted against, such Contributor by reason
      of your accepting any such warranty or additional liability.
```

<a id="go-legal-text-g126"></a>

### Go legal text G126

SHA-256: `feb6d17a0e7a64e5ab0f7e2b0e0ee3e69c1a6396626fd554dc0cddaa06851b44`. Origins:

- `github.com/spf13/cast` v1.10.0: [LICENSE](https://proxy.golang.org/github.com/spf13/cast/@v/v1.10.0.zip)
- `github.com/spf13/viper` v1.21.0: [LICENSE](https://proxy.golang.org/github.com/spf13/viper/@v/v1.21.0.zip)

```text
The MIT License (MIT)

Copyright (c) 2014 Steve Francia

Permission is hereby granted, free of charge, to any person obtaining a copy
of this software and associated documentation files (the "Software"), to deal
in the Software without restriction, including without limitation the rights
to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
copies of the Software, and to permit persons to whom the Software is
furnished to do so, subject to the following conditions:

The above copyright notice and this permission notice shall be included in all
copies or substantial portions of the Software.

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
SOFTWARE.
```

<a id="go-legal-text-g091"></a>

### Go legal text G091

SHA-256: `b8514c577c1c4b46cee454d5a882b15fa411e72c5bd7f801f241591789fce61a`. Origins:

- `github.com/spf13/pflag` v1.0.10: [LICENSE](https://proxy.golang.org/github.com/spf13/pflag/@v/v1.0.10.zip)

```text
Copyright (c) 2012 Alex Ogier. All rights reserved.
Copyright (c) 2012 The Go Authors. All rights reserved.

Redistribution and use in source and binary forms, with or without
modification, are permitted provided that the following conditions are
met:

   * Redistributions of source code must retain the above copyright
notice, this list of conditions and the following disclaimer.
   * Redistributions in binary form must reproduce the above
copyright notice, this list of conditions and the following disclaimer
in the documentation and/or other materials provided with the
distribution.
   * Neither the name of Google Inc. nor the names of its
contributors may be used to endorse or promote products derived from
this software without specific prior written permission.

THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS
"AS IS" AND ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT
LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR
A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT
OWNER OR CONTRIBUTORS BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL,
SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT
LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES; LOSS OF USE,
DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND ON ANY
THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT
(INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE
OF THIS SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.
```

<a id="go-legal-text-g087"></a>

### Go legal text G087

SHA-256: `b2663894033a05fd80261176cd8da1d72546e25842d5c1abcc852ca23b6b61b0`. Origins:

- `github.com/stretchr/objx` v0.5.3: [LICENSE](https://proxy.golang.org/github.com/stretchr/objx/@v/v0.5.3.zip)

```text
The MIT License

Copyright (c) 2014 Stretchr, Inc.
Copyright (c) 2017-2018 objx contributors

Permission is hereby granted, free of charge, to any person obtaining a copy
of this software and associated documentation files (the "Software"), to deal
in the Software without restriction, including without limitation the rights
to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
copies of the Software, and to permit persons to whom the Software is
furnished to do so, subject to the following conditions:

The above copyright notice and this permission notice shall be included in all
copies or substantial portions of the Software.

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
SOFTWARE.
```

<a id="go-legal-text-g121"></a>

### Go legal text G121

SHA-256: `f8e536c1c7b695810427095dc85f5f80d44ff7c10535e8a9486cf393e2599189`. Origins:

- `github.com/stretchr/testify` v1.11.1: [LICENSE](https://proxy.golang.org/github.com/stretchr/testify/@v/v1.11.1.zip)

```text
MIT License

Copyright (c) 2012-2020 Mat Ryer, Tyler Bunnell and contributors.

Permission is hereby granted, free of charge, to any person obtaining a copy
of this software and associated documentation files (the "Software"), to deal
in the Software without restriction, including without limitation the rights
to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
copies of the Software, and to permit persons to whom the Software is
furnished to do so, subject to the following conditions:

The above copyright notice and this permission notice shall be included in all
copies or substantial portions of the Software.

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
SOFTWARE.
```

<a id="go-legal-text-g021"></a>

### Go legal text G021

SHA-256: `1fd29c692f599736d92f7c02b7826836c0d2ccc838689b21822f4ce0253c39df`. Origins:

- `github.com/subosito/gotenv` v1.6.0: [LICENSE](https://proxy.golang.org/github.com/subosito/gotenv/@v/v1.6.0.zip)

```text
The MIT License (MIT)

Copyright (c) 2013 Alif Rachmawadi

Permission is hereby granted, free of charge, to any person obtaining a copy
of this software and associated documentation files (the "Software"), to deal
in the Software without restriction, including without limitation the rights
to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
copies of the Software, and to permit persons to whom the Software is
furnished to do so, subject to the following conditions:

The above copyright notice and this permission notice shall be included in
all copies or substantial portions of the Software.

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
THE SOFTWARE.
```

<a id="go-legal-text-g086"></a>

### Go legal text G086

SHA-256: `abaffe073f1c5b98bcfeaf9749c7fc9bc3d55e850307ebe1bce3363f5502b7a8`. Origins:

- `github.com/temporalio/roadrunner-temporal/v5` v5.11.0: [LICENSE](https://proxy.golang.org/github.com/temporalio/roadrunner-temporal/v5/@v/v5.11.0.zip)

```text
The MIT License

Copyright (c) 2020 Temporal Technologies Inc. All rights reserved.

Permission is hereby granted, free of charge, to any person obtaining a copy of this software and associated documentation files (the "Software"), to deal in the Software without restriction, including without limitation the rights to use, copy, modify, merge, publish, distribute, sublicense, and/or sell copies of the Software, and to permit persons to whom the Software is furnished to do so, subject to the following conditions:

The above copyright notice and this permission notice shall be included in all copies or substantial portions of the Software.

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM, OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE SOFTWARE.
```

<a id="go-legal-text-g125"></a>

### Go legal text G125

SHA-256: `fdaaa923d83d44f8b2ed1185600c5aa8c10673f11fba9feb78bd2816786fd6be`. Origins:

- `github.com/tklauser/go-sysconf` v0.4.0: [LICENSE](https://proxy.golang.org/github.com/tklauser/go-sysconf/@v/v0.4.0.zip)

```text
BSD 3-Clause License

Copyright (c) 2018-2022, Tobias Klauser
All rights reserved.

Redistribution and use in source and binary forms, with or without
modification, are permitted provided that the following conditions are met:

* Redistributions of source code must retain the above copyright notice, this
  list of conditions and the following disclaimer.

* Redistributions in binary form must reproduce the above copyright notice,
  this list of conditions and the following disclaimer in the documentation
  and/or other materials provided with the distribution.

* Neither the name of the copyright holder nor the names of its
  contributors may be used to endorse or promote products derived from
  this software without specific prior written permission.

THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS "AS IS"
AND ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE
IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE ARE
DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT HOLDER OR CONTRIBUTORS BE LIABLE
FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL
DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR
SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER
CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY,
OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE
OF THIS SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.
```

<a id="go-legal-text-g076"></a>

### Go legal text G076

SHA-256: `9ed9133de92870659a93ee70f11102dac696c23a65d68161fa292f1a23831be6`. Origins:

- `github.com/twmb/franz-go` v1.21.3: [LICENSE](https://proxy.golang.org/github.com/twmb/franz-go/@v/v1.21.3.zip)
- `github.com/twmb/franz-go/pkg/kmsg` v1.13.1: [LICENSE](https://proxy.golang.org/github.com/twmb/franz-go/pkg/kmsg/@v/v1.13.1.zip)

```text
Copyright 2020, Travis Bischel.
All rights reserved.

Redistribution and use in source and binary forms, with or without
modification, are permitted provided that the following conditions are met:
    * Redistributions of source code must retain the above copyright
      notice, this list of conditions and the following disclaimer.
    * Redistributions in binary form must reproduce the above copyright
      notice, this list of conditions and the following disclaimer in the
      documentation and/or other materials provided with the distribution.
    * Neither the name of the library nor the
      names of its contributors may be used to endorse or promote products
      derived from this software without specific prior written permission.

THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS "AS IS" AND
ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE IMPLIED
WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE ARE
DISCLAIMED. IN NO EVENT SHALL <COPYRIGHT HOLDER> BE LIABLE FOR ANY
DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES
(INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES;
LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND
ON ANY THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT
(INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE OF THIS
SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.
```

<a id="go-legal-text-g036"></a>

### Go legal text G036

SHA-256: `4228ef1819124b490d2e6a88e8c5e1348bedf32ff05b8fb5deed49921600e503`. Origins:

- `github.com/twmb/murmur3` v1.1.8: [LICENSE](https://proxy.golang.org/github.com/twmb/murmur3/@v/v1.1.8.zip)

```text
Copyright 2013, Sébastien Paolacci.
All rights reserved.

Redistribution and use in source and binary forms, with or without
modification, are permitted provided that the following conditions are met:
    * Redistributions of source code must retain the above copyright
      notice, this list of conditions and the following disclaimer.
    * Redistributions in binary form must reproduce the above copyright
      notice, this list of conditions and the following disclaimer in the
      documentation and/or other materials provided with the distribution.
    * Neither the name of the library nor the
      names of its contributors may be used to endorse or promote products
      derived from this software without specific prior written permission.

THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS "AS IS" AND
ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE IMPLIED
WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE ARE
DISCLAIMED. IN NO EVENT SHALL <COPYRIGHT HOLDER> BE LIABLE FOR ANY
DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES
(INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES;
LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND
ON ANY THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT
(INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE OF THIS
SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.

Copyright 2018, Travis Bischel.
All rights reserved.

Redistribution and use in source and binary forms, with or without
modification, are permitted provided that the following conditions are met:
    * Redistributions of source code must retain the above copyright
      notice, this list of conditions and the following disclaimer.
    * Redistributions in binary form must reproduce the above copyright
      notice, this list of conditions and the following disclaimer in the
      documentation and/or other materials provided with the distribution.
    * Neither the name of the library nor the
      names of its contributors may be used to endorse or promote products
      derived from this software without specific prior written permission.

THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS "AS IS" AND
ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE IMPLIED
WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE ARE
DISCLAIMED. IN NO EVENT SHALL <COPYRIGHT HOLDER> BE LIABLE FOR ANY
DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES
(INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES;
LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND
ON ANY THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT
(INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE OF THIS
SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.
```

<a id="go-legal-text-g079"></a>

### Go legal text G079

SHA-256: `a446f219aabe3667850444bbd5f11b7e931889b4d5dbf3bc074fe00f25f1124c`. Origins:

- `github.com/uber-go/tally/v4` v4.1.17: [LICENSE](https://proxy.golang.org/github.com/uber-go/tally/v4/@v/v4.1.17.zip)

```text
MIT License

Copyright (c) 2016 

Permission is hereby granted, free of charge, to any person obtaining a copy
of this software and associated documentation files (the "Software"), to deal
in the Software without restriction, including without limitation the rights
to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
copies of the Software, and to permit persons to whom the Software is
furnished to do so, subject to the following conditions:

The above copyright notice and this permission notice shall be included in all
copies or substantial portions of the Software.

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
SOFTWARE.
```

<a id="go-legal-text-g099"></a>

### Go legal text G099

SHA-256: `c4c2923938be3bf985290743398835cb6097d277663eeb1addec34b4df28d806`. Origins:

- `github.com/valyala/bytebufferpool` v1.0.0: [LICENSE](https://proxy.golang.org/github.com/valyala/bytebufferpool/@v/v1.0.0.zip)

```text
The MIT License (MIT)

Copyright (c) 2016 Aliaksandr Valialkin, VertaMedia

Permission is hereby granted, free of charge, to any person obtaining a copy
of this software and associated documentation files (the "Software"), to deal
in the Software without restriction, including without limitation the rights
to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
copies of the Software, and to permit persons to whom the Software is
furnished to do so, subject to the following conditions:

The above copyright notice and this permission notice shall be included in all
copies or substantial portions of the Software.

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
SOFTWARE.

```

<a id="go-legal-text-g068"></a>

### Go legal text G068

SHA-256: `891a84acaa8f66e247a3b7de506e31e3802c5030412dd491f7c2fb41b5a10a92`. Origins:

- `github.com/valyala/fasthttp` v1.71.0: [LICENSE](https://proxy.golang.org/github.com/valyala/fasthttp/@v/v1.71.0.zip)

```text
The MIT License (MIT)

Copyright (c) 2015-present Aliaksandr Valialkin, VertaMedia, Kirill Danshin, Erik Dubbelboer, FastHTTP Authors

Permission is hereby granted, free of charge, to any person obtaining a copy of this software and associated documentation files (the "Software"), to deal in the Software without restriction, including without limitation the rights to use, copy, modify, merge, publish, distribute, sublicense, and/or sell copies of the Software, and to permit persons to whom the Software is furnished to do so, subject to the following conditions:

The above copyright notice and this permission notice shall be included in all copies or substantial portions of the Software.

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM, OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE SOFTWARE.
```

<a id="go-legal-text-g089"></a>

### Go legal text G089

SHA-256: `b526d0b27475b775508debeb9099bed6d5e26ea99060290294d7280dfd03a656`. Origins:

- `github.com/valyala/fasthttp` v1.71.0: [reuseport/LICENSE](https://proxy.golang.org/github.com/valyala/fasthttp/@v/v1.71.0.zip)

```text
The MIT License (MIT)

Copyright (c) 2014 Max Riveiro

Permission is hereby granted, free of charge, to any person obtaining a copy
of this software and associated documentation files (the "Software"), to deal
in the Software without restriction, including without limitation the rights
to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
copies of the Software, and to permit persons to whom the Software is
furnished to do so, subject to the following conditions:

The above copyright notice and this permission notice shall be included in all
copies or substantial portions of the Software.

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
SOFTWARE.
```

<a id="go-legal-text-g016"></a>

### Go legal text G016

SHA-256: `1530185dd39cdde7cb2f786a7a5fcf8bc34368b6ae73c15e72bafe88fd80b9a2`. Origins:

- `github.com/vmihailenco/msgpack/v5` v5.4.1: [LICENSE](https://proxy.golang.org/github.com/vmihailenco/msgpack/v5/@v/v5.4.1.zip)

```text
Copyright (c) 2013 The github.com/vmihailenco/msgpack Authors.
All rights reserved.

Redistribution and use in source and binary forms, with or without
modification, are permitted provided that the following conditions are
met:

   * Redistributions of source code must retain the above copyright
notice, this list of conditions and the following disclaimer.
   * Redistributions in binary form must reproduce the above
copyright notice, this list of conditions and the following disclaimer
in the documentation and/or other materials provided with the
distribution.

THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS
"AS IS" AND ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT
LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR
A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT
OWNER OR CONTRIBUTORS BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL,
SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT
LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES; LOSS OF USE,
DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND ON ANY
THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT
(INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE
OF THIS SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.
```

<a id="go-legal-text-g012"></a>

### Go legal text G012

SHA-256: `0f02aac22084ef463aa5f42f566feeec6aa4c99653519744a8c25cb079da5b5d`. Origins:

- `github.com/vmihailenco/tagparser/v2` v2.0.0: [LICENSE](https://proxy.golang.org/github.com/vmihailenco/tagparser/v2/@v/v2.0.0.zip)

```text
Copyright (c) 2019 The github.com/vmihailenco/tagparser Authors.
All rights reserved.

Redistribution and use in source and binary forms, with or without
modification, are permitted provided that the following conditions are
met:

   * Redistributions of source code must retain the above copyright
notice, this list of conditions and the following disclaimer.
   * Redistributions in binary form must reproduce the above
copyright notice, this list of conditions and the following disclaimer
in the documentation and/or other materials provided with the
distribution.

THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS
"AS IS" AND ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT
LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR
A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT
OWNER OR CONTRIBUTORS BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL,
SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT
LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES; LOSS OF USE,
DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND ON ANY
THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT
(INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE
OF THIS SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.
```

<a id="go-legal-text-g003"></a>

### Go legal text G003

SHA-256: `0589f544f68ffc436e6e21efec2cf7cc2dbb2ac09ce6cb8a8cdb75ab74489716`. Origins:

- `github.com/zeebo/blake3` v0.2.4: [LICENSE](https://proxy.golang.org/github.com/zeebo/blake3/@v/v0.2.4.zip)

```text
This work is released into the public domain with CC0 1.0.

-------------------------------------------------------------------------------

Creative Commons Legal Code

CC0 1.0 Universal

    CREATIVE COMMONS CORPORATION IS NOT A LAW FIRM AND DOES NOT PROVIDE
    LEGAL SERVICES. DISTRIBUTION OF THIS DOCUMENT DOES NOT CREATE AN
    ATTORNEY-CLIENT RELATIONSHIP. CREATIVE COMMONS PROVIDES THIS
    INFORMATION ON AN "AS-IS" BASIS. CREATIVE COMMONS MAKES NO WARRANTIES
    REGARDING THE USE OF THIS DOCUMENT OR THE INFORMATION OR WORKS
    PROVIDED HEREUNDER, AND DISCLAIMS LIABILITY FOR DAMAGES RESULTING FROM
    THE USE OF THIS DOCUMENT OR THE INFORMATION OR WORKS PROVIDED
    HEREUNDER.

Statement of Purpose

The laws of most jurisdictions throughout the world automatically confer
exclusive Copyright and Related Rights (defined below) upon the creator
and subsequent owner(s) (each and all, an "owner") of an original work of
authorship and/or a database (each, a "Work").

Certain owners wish to permanently relinquish those rights to a Work for
the purpose of contributing to a commons of creative, cultural and
scientific works ("Commons") that the public can reliably and without fear
of later claims of infringement build upon, modify, incorporate in other
works, reuse and redistribute as freely as possible in any form whatsoever
and for any purposes, including without limitation commercial purposes.
These owners may contribute to the Commons to promote the ideal of a free
culture and the further production of creative, cultural and scientific
works, or to gain reputation or greater distribution for their Work in
part through the use and efforts of others.

For these and/or other purposes and motivations, and without any
expectation of additional consideration or compensation, the person
associating CC0 with a Work (the "Affirmer"), to the extent that he or she
is an owner of Copyright and Related Rights in the Work, voluntarily
elects to apply CC0 to the Work and publicly distribute the Work under its
terms, with knowledge of his or her Copyright and Related Rights in the
Work and the meaning and intended legal effect of CC0 on those rights.

1. Copyright and Related Rights. A Work made available under CC0 may be
protected by copyright and related or neighboring rights ("Copyright and
Related Rights"). Copyright and Related Rights include, but are not
limited to, the following:

  i. the right to reproduce, adapt, distribute, perform, display,
     communicate, and translate a Work;
 ii. moral rights retained by the original author(s) and/or performer(s);
iii. publicity and privacy rights pertaining to a person's image or
     likeness depicted in a Work;
 iv. rights protecting against unfair competition in regards to a Work,
     subject to the limitations in paragraph 4(a), below;
  v. rights protecting the extraction, dissemination, use and reuse of data
     in a Work;
 vi. database rights (such as those arising under Directive 96/9/EC of the
     European Parliament and of the Council of 11 March 1996 on the legal
     protection of databases, and under any national implementation
     thereof, including any amended or successor version of such
     directive); and
vii. other similar, equivalent or corresponding rights throughout the
     world based on applicable law or treaty, and any national
     implementations thereof.

2. Waiver. To the greatest extent permitted by, but not in contravention
of, applicable law, Affirmer hereby overtly, fully, permanently,
irrevocably and unconditionally waives, abandons, and surrenders all of
Affirmer's Copyright and Related Rights and associated claims and causes
of action, whether now known or unknown (including existing as well as
future claims and causes of action), in the Work (i) in all territories
worldwide, (ii) for the maximum duration provided by applicable law or
treaty (including future time extensions), (iii) in any current or future
medium and for any number of copies, and (iv) for any purpose whatsoever,
including without limitation commercial, advertising or promotional
purposes (the "Waiver"). Affirmer makes the Waiver for the benefit of each
member of the public at large and to the detriment of Affirmer's heirs and
successors, fully intending that such Waiver shall not be subject to
revocation, rescission, cancellation, termination, or any other legal or
equitable action to disrupt the quiet enjoyment of the Work by the public
as contemplated by Affirmer's express Statement of Purpose.

3. Public License Fallback. Should any part of the Waiver for any reason
be judged legally invalid or ineffective under applicable law, then the
Waiver shall be preserved to the maximum extent permitted taking into
account Affirmer's express Statement of Purpose. In addition, to the
extent the Waiver is so judged Affirmer hereby grants to each affected
person a royalty-free, non transferable, non sublicensable, non exclusive,
irrevocable and unconditional license to exercise Affirmer's Copyright and
Related Rights in the Work (i) in all territories worldwide, (ii) for the
maximum duration provided by applicable law or treaty (including future
time extensions), (iii) in any current or future medium and for any number
of copies, and (iv) for any purpose whatsoever, including without
limitation commercial, advertising or promotional purposes (the
"License"). The License shall be deemed effective as of the date CC0 was
applied by Affirmer to the Work. Should any part of the License for any
reason be judged legally invalid or ineffective under applicable law, such
partial invalidity or ineffectiveness shall not invalidate the remainder
of the License, and in such case Affirmer hereby affirms that he or she
will not (i) exercise any of his or her remaining Copyright and Related
Rights in the Work or (ii) assert any associated claims and causes of
action with respect to the Work, in either case contrary to Affirmer's
express Statement of Purpose.

4. Limitations and Disclaimers.

 a. No trademark or patent rights held by Affirmer are waived, abandoned,
    surrendered, licensed or otherwise affected by this document.
 b. Affirmer offers the Work as-is and makes no representations or
    warranties of any kind concerning the Work, express, implied,
    statutory or otherwise, including without limitation warranties of
    title, merchantability, fitness for a particular purpose, non
    infringement, or the absence of latent or other defects, accuracy, or
    the present or absence of errors, whether or not discoverable, all to
    the greatest extent permissible under applicable law.
 c. Affirmer disclaims responsibility for clearing rights of other persons
    that may apply to the Work or any use thereof, including without
    limitation any person's Copyright and Related Rights in the Work.
    Further, Affirmer disclaims responsibility for obtaining any necessary
    consents, permissions or other rights required for any use of the
    Work.
 d. Affirmer understands and acknowledges that Creative Commons is not a
    party to this document and has no duty or obligation with respect to
    this CC0 or use of the Work.
```

<a id="go-legal-text-g094"></a>

### Go legal text G094

SHA-256: `c15d721c37e277a11584547de6d618541501f7aa10c4e32a945a4f9ff36cb0f6`. Origins:

- `go.etcd.io/bbolt` v1.4.3: [LICENSE](https://proxy.golang.org/go.etcd.io/bbolt/@v/v1.4.3.zip)

```text
The MIT License (MIT)

Copyright (c) 2013 Ben Johnson

Permission is hereby granted, free of charge, to any person obtaining a copy of
this software and associated documentation files (the "Software"), to deal in
the Software without restriction, including without limitation the rights to
use, copy, modify, merge, publish, distribute, sublicense, and/or sell copies of
the Software, and to permit persons to whom the Software is furnished to do so,
subject to the following conditions:

The above copyright notice and this permission notice shall be included in all
copies or substantial portions of the Software.

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY, FITNESS
FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE AUTHORS OR
COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER LIABILITY, WHETHER
IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM, OUT OF OR IN
CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE SOFTWARE.
```

<a id="go-legal-text-g042"></a>

### Go legal text G042

SHA-256: `58d1e17ffe5109a7ae296caafcadfdbe6a7d176f0bc4ab01e12a689b0499d8bd`. Origins:

- `go.opencensus.io` v0.24.0: [LICENSE](https://proxy.golang.org/go.opencensus.io/@v/v0.24.0.zip)

```text

                                 Apache License
                           Version 2.0, January 2004
                        http://www.apache.org/licenses/

   TERMS AND CONDITIONS FOR USE, REPRODUCTION, AND DISTRIBUTION

   1. Definitions.

      "License" shall mean the terms and conditions for use, reproduction,
      and distribution as defined by Sections 1 through 9 of this document.

      "Licensor" shall mean the copyright owner or entity authorized by
      the copyright owner that is granting the License.

      "Legal Entity" shall mean the union of the acting entity and all
      other entities that control, are controlled by, or are under common
      control with that entity. For the purposes of this definition,
      "control" means (i) the power, direct or indirect, to cause the
      direction or management of such entity, whether by contract or
      otherwise, or (ii) ownership of fifty percent (50%) or more of the
      outstanding shares, or (iii) beneficial ownership of such entity.

      "You" (or "Your") shall mean an individual or Legal Entity
      exercising permissions granted by this License.

      "Source" form shall mean the preferred form for making modifications,
      including but not limited to software source code, documentation
      source, and configuration files.

      "Object" form shall mean any form resulting from mechanical
      transformation or translation of a Source form, including but
      not limited to compiled object code, generated documentation,
      and conversions to other media types.

      "Work" shall mean the work of authorship, whether in Source or
      Object form, made available under the License, as indicated by a
      copyright notice that is included in or attached to the work
      (an example is provided in the Appendix below).

      "Derivative Works" shall mean any work, whether in Source or Object
      form, that is based on (or derived from) the Work and for which the
      editorial revisions, annotations, elaborations, or other modifications
      represent, as a whole, an original work of authorship. For the purposes
      of this License, Derivative Works shall not include works that remain
      separable from, or merely link (or bind by name) to the interfaces of,
      the Work and Derivative Works thereof.

      "Contribution" shall mean any work of authorship, including
      the original version of the Work and any modifications or additions
      to that Work or Derivative Works thereof, that is intentionally
      submitted to Licensor for inclusion in the Work by the copyright owner
      or by an individual or Legal Entity authorized to submit on behalf of
      the copyright owner. For the purposes of this definition, "submitted"
      means any form of electronic, verbal, or written communication sent
      to the Licensor or its representatives, including but not limited to
      communication on electronic mailing lists, source code control systems,
      and issue tracking systems that are managed by, or on behalf of, the
      Licensor for the purpose of discussing and improving the Work, but
      excluding communication that is conspicuously marked or otherwise
      designated in writing by the copyright owner as "Not a Contribution."

      "Contributor" shall mean Licensor and any individual or Legal Entity
      on behalf of whom a Contribution has been received by Licensor and
      subsequently incorporated within the Work.

   2. Grant of Copyright License. Subject to the terms and conditions of
      this License, each Contributor hereby grants to You a perpetual,
      worldwide, non-exclusive, no-charge, royalty-free, irrevocable
      copyright license to reproduce, prepare Derivative Works of,
      publicly display, publicly perform, sublicense, and distribute the
      Work and such Derivative Works in Source or Object form.

   3. Grant of Patent License. Subject to the terms and conditions of
      this License, each Contributor hereby grants to You a perpetual,
      worldwide, non-exclusive, no-charge, royalty-free, irrevocable
      (except as stated in this section) patent license to make, have made,
      use, offer to sell, sell, import, and otherwise transfer the Work,
      where such license applies only to those patent claims licensable
      by such Contributor that are necessarily infringed by their
      Contribution(s) alone or by combination of their Contribution(s)
      with the Work to which such Contribution(s) was submitted. If You
      institute patent litigation against any entity (including a
      cross-claim or counterclaim in a lawsuit) alleging that the Work
      or a Contribution incorporated within the Work constitutes direct
      or contributory patent infringement, then any patent licenses
      granted to You under this License for that Work shall terminate
      as of the date such litigation is filed.

   4. Redistribution. You may reproduce and distribute copies of the
      Work or Derivative Works thereof in any medium, with or without
      modifications, and in Source or Object form, provided that You
      meet the following conditions:

      (a) You must give any other recipients of the Work or
          Derivative Works a copy of this License; and

      (b) You must cause any modified files to carry prominent notices
          stating that You changed the files; and

      (c) You must retain, in the Source form of any Derivative Works
          that You distribute, all copyright, patent, trademark, and
          attribution notices from the Source form of the Work,
          excluding those notices that do not pertain to any part of
          the Derivative Works; and

      (d) If the Work includes a "NOTICE" text file as part of its
          distribution, then any Derivative Works that You distribute must
          include a readable copy of the attribution notices contained
          within such NOTICE file, excluding those notices that do not
          pertain to any part of the Derivative Works, in at least one
          of the following places: within a NOTICE text file distributed
          as part of the Derivative Works; within the Source form or
          documentation, if provided along with the Derivative Works; or,
          within a display generated by the Derivative Works, if and
          wherever such third-party notices normally appear. The contents
          of the NOTICE file are for informational purposes only and
          do not modify the License. You may add Your own attribution
          notices within Derivative Works that You distribute, alongside
          or as an addendum to the NOTICE text from the Work, provided
          that such additional attribution notices cannot be construed
          as modifying the License.

      You may add Your own copyright statement to Your modifications and
      may provide additional or different license terms and conditions
      for use, reproduction, or distribution of Your modifications, or
      for any such Derivative Works as a whole, provided Your use,
      reproduction, and distribution of the Work otherwise complies with
      the conditions stated in this License.

   5. Submission of Contributions. Unless You explicitly state otherwise,
      any Contribution intentionally submitted for inclusion in the Work
      by You to the Licensor shall be under the terms and conditions of
      this License, without any additional terms or conditions.
      Notwithstanding the above, nothing herein shall supersede or modify
      the terms of any separate license agreement you may have executed
      with Licensor regarding such Contributions.

   6. Trademarks. This License does not grant permission to use the trade
      names, trademarks, service marks, or product names of the Licensor,
      except as required for reasonable and customary use in describing the
      origin of the Work and reproducing the content of the NOTICE file.

   7. Disclaimer of Warranty. Unless required by applicable law or
      agreed to in writing, Licensor provides the Work (and each
      Contributor provides its Contributions) on an "AS IS" BASIS,
      WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or
      implied, including, without limitation, any warranties or conditions
      of TITLE, NON-INFRINGEMENT, MERCHANTABILITY, or FITNESS FOR A
      PARTICULAR PURPOSE. You are solely responsible for determining the
      appropriateness of using or redistributing the Work and assume any
      risks associated with Your exercise of permissions under this License.

   8. Limitation of Liability. In no event and under no legal theory,
      whether in tort (including negligence), contract, or otherwise,
      unless required by applicable law (such as deliberate and grossly
      negligent acts) or agreed to in writing, shall any Contributor be
      liable to You for damages, including any direct, indirect, special,
      incidental, or consequential damages of any character arising as a
      result of this License or out of the use or inability to use the
      Work (including but not limited to damages for loss of goodwill,
      work stoppage, computer failure or malfunction, or any and all
      other commercial damages or losses), even if such Contributor
      has been advised of the possibility of such damages.

   9. Accepting Warranty or Additional Liability. While redistributing
      the Work or Derivative Works thereof, You may choose to offer,
      and charge a fee for, acceptance of support, warranty, indemnity,
      or other liability obligations and/or rights consistent with this
      License. However, in accepting such obligations, You may act only
      on Your own behalf and on Your sole responsibility, not on behalf
      of any other Contributor, and only if You agree to indemnify,
      defend, and hold each Contributor harmless for any liability
      incurred by, or claims asserted against, such Contributor by reason
      of your accepting any such warranty or additional liability.

   END OF TERMS AND CONDITIONS

   APPENDIX: How to apply the Apache License to your work.

      To apply the Apache License to your work, attach the following
      boilerplate notice, with the fields enclosed by brackets "[]"
      replaced with your own identifying information. (Don't include
      the brackets!)  The text should be enclosed in the appropriate
      comment syntax for the file format. We also recommend that a
      file or class name and description of purpose be included on the
      same "printed page" as the copyright notice for easier
      identification within third-party archives.

   Copyright [yyyy] [name of copyright owner]

   Licensed under the Apache License, Version 2.0 (the "License");
   you may not use this file except in compliance with the License.
   You may obtain a copy of the License at

       http://www.apache.org/licenses/LICENSE-2.0

   Unless required by applicable law or agreed to in writing, software
   distributed under the License is distributed on an "AS IS" BASIS,
   WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
   See the License for the specific language governing permissions and
   limitations under the License.
```

<a id="go-legal-text-g017"></a>

### Go legal text G017

SHA-256: `1ae07514be1d7bb33f0698f8d91fb51b8b9fe1463157ec1c72081a49b9bc6f40`. Origins:

- `go.opentelemetry.io/contrib/instrumentation/google.golang.org/grpc/otelgrpc` v0.69.0: [LICENSE](https://proxy.golang.org/go.opentelemetry.io/contrib/instrumentation/google.golang.org/grpc/otelgrpc/@v/v0.69.0.zip)
- `go.opentelemetry.io/contrib/instrumentation/net/http/otelhttp` v0.69.0: [LICENSE](https://proxy.golang.org/go.opentelemetry.io/contrib/instrumentation/net/http/otelhttp/@v/v0.69.0.zip)
- `go.opentelemetry.io/contrib/propagators/jaeger` v1.44.0: [LICENSE](https://proxy.golang.org/go.opentelemetry.io/contrib/propagators/jaeger/@v/v1.44.0.zip)
- `go.opentelemetry.io/otel` v1.44.0: [LICENSE](https://proxy.golang.org/go.opentelemetry.io/otel/@v/v1.44.0.zip)
- `go.opentelemetry.io/otel/exporters/otlp/otlptrace` v1.44.0: [LICENSE](https://proxy.golang.org/go.opentelemetry.io/otel/exporters/otlp/otlptrace/@v/v1.44.0.zip)
- `go.opentelemetry.io/otel/exporters/otlp/otlptrace/otlptracegrpc` v1.44.0: [LICENSE](https://proxy.golang.org/go.opentelemetry.io/otel/exporters/otlp/otlptrace/otlptracegrpc/@v/v1.44.0.zip)
- `go.opentelemetry.io/otel/exporters/otlp/otlptrace/otlptracehttp` v1.44.0: [LICENSE](https://proxy.golang.org/go.opentelemetry.io/otel/exporters/otlp/otlptrace/otlptracehttp/@v/v1.44.0.zip)
- `go.opentelemetry.io/otel/exporters/stdout/stdouttrace` v1.44.0: [LICENSE](https://proxy.golang.org/go.opentelemetry.io/otel/exporters/stdout/stdouttrace/@v/v1.44.0.zip)
- `go.opentelemetry.io/otel/exporters/zipkin` v1.44.0: [LICENSE](https://proxy.golang.org/go.opentelemetry.io/otel/exporters/zipkin/@v/v1.44.0.zip)
- `go.opentelemetry.io/otel/metric` v1.44.0: [LICENSE](https://proxy.golang.org/go.opentelemetry.io/otel/metric/@v/v1.44.0.zip)
- `go.opentelemetry.io/otel/sdk` v1.44.0: [LICENSE](https://proxy.golang.org/go.opentelemetry.io/otel/sdk/@v/v1.44.0.zip)
- `go.opentelemetry.io/otel/trace` v1.44.0: [LICENSE](https://proxy.golang.org/go.opentelemetry.io/otel/trace/@v/v1.44.0.zip)

```text
                                 Apache License
                           Version 2.0, January 2004
                        http://www.apache.org/licenses/

   TERMS AND CONDITIONS FOR USE, REPRODUCTION, AND DISTRIBUTION

   1. Definitions.

      "License" shall mean the terms and conditions for use, reproduction,
      and distribution as defined by Sections 1 through 9 of this document.

      "Licensor" shall mean the copyright owner or entity authorized by
      the copyright owner that is granting the License.

      "Legal Entity" shall mean the union of the acting entity and all
      other entities that control, are controlled by, or are under common
      control with that entity. For the purposes of this definition,
      "control" means (i) the power, direct or indirect, to cause the
      direction or management of such entity, whether by contract or
      otherwise, or (ii) ownership of fifty percent (50%) or more of the
      outstanding shares, or (iii) beneficial ownership of such entity.

      "You" (or "Your") shall mean an individual or Legal Entity
      exercising permissions granted by this License.

      "Source" form shall mean the preferred form for making modifications,
      including but not limited to software source code, documentation
      source, and configuration files.

      "Object" form shall mean any form resulting from mechanical
      transformation or translation of a Source form, including but
      not limited to compiled object code, generated documentation,
      and conversions to other media types.

      "Work" shall mean the work of authorship, whether in Source or
      Object form, made available under the License, as indicated by a
      copyright notice that is included in or attached to the work
      (an example is provided in the Appendix below).

      "Derivative Works" shall mean any work, whether in Source or Object
      form, that is based on (or derived from) the Work and for which the
      editorial revisions, annotations, elaborations, or other modifications
      represent, as a whole, an original work of authorship. For the purposes
      of this License, Derivative Works shall not include works that remain
      separable from, or merely link (or bind by name) to the interfaces of,
      the Work and Derivative Works thereof.

      "Contribution" shall mean any work of authorship, including
      the original version of the Work and any modifications or additions
      to that Work or Derivative Works thereof, that is intentionally
      submitted to Licensor for inclusion in the Work by the copyright owner
      or by an individual or Legal Entity authorized to submit on behalf of
      the copyright owner. For the purposes of this definition, "submitted"
      means any form of electronic, verbal, or written communication sent
      to the Licensor or its representatives, including but not limited to
      communication on electronic mailing lists, source code control systems,
      and issue tracking systems that are managed by, or on behalf of, the
      Licensor for the purpose of discussing and improving the Work, but
      excluding communication that is conspicuously marked or otherwise
      designated in writing by the copyright owner as "Not a Contribution."

      "Contributor" shall mean Licensor and any individual or Legal Entity
      on behalf of whom a Contribution has been received by Licensor and
      subsequently incorporated within the Work.

   2. Grant of Copyright License. Subject to the terms and conditions of
      this License, each Contributor hereby grants to You a perpetual,
      worldwide, non-exclusive, no-charge, royalty-free, irrevocable
      copyright license to reproduce, prepare Derivative Works of,
      publicly display, publicly perform, sublicense, and distribute the
      Work and such Derivative Works in Source or Object form.

   3. Grant of Patent License. Subject to the terms and conditions of
      this License, each Contributor hereby grants to You a perpetual,
      worldwide, non-exclusive, no-charge, royalty-free, irrevocable
      (except as stated in this section) patent license to make, have made,
      use, offer to sell, sell, import, and otherwise transfer the Work,
      where such license applies only to those patent claims licensable
      by such Contributor that are necessarily infringed by their
      Contribution(s) alone or by combination of their Contribution(s)
      with the Work to which such Contribution(s) was submitted. If You
      institute patent litigation against any entity (including a
      cross-claim or counterclaim in a lawsuit) alleging that the Work
      or a Contribution incorporated within the Work constitutes direct
      or contributory patent infringement, then any patent licenses
      granted to You under this License for that Work shall terminate
      as of the date such litigation is filed.

   4. Redistribution. You may reproduce and distribute copies of the
      Work or Derivative Works thereof in any medium, with or without
      modifications, and in Source or Object form, provided that You
      meet the following conditions:

      (a) You must give any other recipients of the Work or
          Derivative Works a copy of this License; and

      (b) You must cause any modified files to carry prominent notices
          stating that You changed the files; and

      (c) You must retain, in the Source form of any Derivative Works
          that You distribute, all copyright, patent, trademark, and
          attribution notices from the Source form of the Work,
          excluding those notices that do not pertain to any part of
          the Derivative Works; and

      (d) If the Work includes a "NOTICE" text file as part of its
          distribution, then any Derivative Works that You distribute must
          include a readable copy of the attribution notices contained
          within such NOTICE file, excluding those notices that do not
          pertain to any part of the Derivative Works, in at least one
          of the following places: within a NOTICE text file distributed
          as part of the Derivative Works; within the Source form or
          documentation, if provided along with the Derivative Works; or,
          within a display generated by the Derivative Works, if and
          wherever such third-party notices normally appear. The contents
          of the NOTICE file are for informational purposes only and
          do not modify the License. You may add Your own attribution
          notices within Derivative Works that You distribute, alongside
          or as an addendum to the NOTICE text from the Work, provided
          that such additional attribution notices cannot be construed
          as modifying the License.

      You may add Your own copyright statement to Your modifications and
      may provide additional or different license terms and conditions
      for use, reproduction, or distribution of Your modifications, or
      for any such Derivative Works as a whole, provided Your use,
      reproduction, and distribution of the Work otherwise complies with
      the conditions stated in this License.

   5. Submission of Contributions. Unless You explicitly state otherwise,
      any Contribution intentionally submitted for inclusion in the Work
      by You to the Licensor shall be under the terms and conditions of
      this License, without any additional terms or conditions.
      Notwithstanding the above, nothing herein shall supersede or modify
      the terms of any separate license agreement you may have executed
      with Licensor regarding such Contributions.

   6. Trademarks. This License does not grant permission to use the trade
      names, trademarks, service marks, or product names of the Licensor,
      except as required for reasonable and customary use in describing the
      origin of the Work and reproducing the content of the NOTICE file.

   7. Disclaimer of Warranty. Unless required by applicable law or
      agreed to in writing, Licensor provides the Work (and each
      Contributor provides its Contributions) on an "AS IS" BASIS,
      WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or
      implied, including, without limitation, any warranties or conditions
      of TITLE, NON-INFRINGEMENT, MERCHANTABILITY, or FITNESS FOR A
      PARTICULAR PURPOSE. You are solely responsible for determining the
      appropriateness of using or redistributing the Work and assume any
      risks associated with Your exercise of permissions under this License.

   8. Limitation of Liability. In no event and under no legal theory,
      whether in tort (including negligence), contract, or otherwise,
      unless required by applicable law (such as deliberate and grossly
      negligent acts) or agreed to in writing, shall any Contributor be
      liable to You for damages, including any direct, indirect, special,
      incidental, or consequential damages of any character arising as a
      result of this License or out of the use or inability to use the
      Work (including but not limited to damages for loss of goodwill,
      work stoppage, computer failure or malfunction, or any and all
      other commercial damages or losses), even if such Contributor
      has been advised of the possibility of such damages.

   9. Accepting Warranty or Additional Liability. While redistributing
      the Work or Derivative Works thereof, You may choose to offer,
      and charge a fee for, acceptance of support, warranty, indemnity,
      or other liability obligations and/or rights consistent with this
      License. However, in accepting such obligations, You may act only
      on Your own behalf and on Your sole responsibility, not on behalf
      of any other Contributor, and only if You agree to indemnify,
      defend, and hold each Contributor harmless for any liability
      incurred by, or claims asserted against, such Contributor by reason
      of your accepting any such warranty or additional liability.

   END OF TERMS AND CONDITIONS

   APPENDIX: How to apply the Apache License to your work.

      To apply the Apache License to your work, attach the following
      boilerplate notice, with the fields enclosed by brackets "[]"
      replaced with your own identifying information. (Don't include
      the brackets!)  The text should be enclosed in the appropriate
      comment syntax for the file format. We also recommend that a
      file or class name and description of purpose be included on the
      same "printed page" as the copyright notice for easier
      identification within third-party archives.

   Copyright [yyyy] [name of copyright owner]

   Licensed under the Apache License, Version 2.0 (the "License");
   you may not use this file except in compliance with the License.
   You may obtain a copy of the License at

       http://www.apache.org/licenses/LICENSE-2.0

   Unless required by applicable law or agreed to in writing, software
   distributed under the License is distributed on an "AS IS" BASIS,
   WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
   See the License for the specific language governing permissions and
   limitations under the License.

--------------------------------------------------------------------------------

Copyright 2009 The Go Authors.

Redistribution and use in source and binary forms, with or without
modification, are permitted provided that the following conditions are
met:

   * Redistributions of source code must retain the above copyright
notice, this list of conditions and the following disclaimer.
   * Redistributions in binary form must reproduce the above
copyright notice, this list of conditions and the following disclaimer
in the documentation and/or other materials provided with the
distribution.
   * Neither the name of Google LLC nor the names of its
contributors may be used to endorse or promote products derived from
this software without specific prior written permission.

THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS
"AS IS" AND ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT
LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR
A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT
OWNER OR CONTRIBUTORS BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL,
SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT
LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES; LOSS OF USE,
DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND ON ANY
THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT
(INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE
OF THIS SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.
```

<a id="go-legal-text-g109"></a>

### Go legal text G109

SHA-256: `dfab09792f0b0df7b589581f87654f7da8f6c191145c4d3e994f35027a85e193`. Origins:

- `go.temporal.io/api` v1.62.14: [LICENSE](https://proxy.golang.org/go.temporal.io/api/@v/v1.62.14.zip)

```text
The MIT License

Copyright (c) 2025 Temporal Technologies Inc.  All rights reserved.

Permission is hereby granted, free of charge, to any person obtaining a copy
of this software and associated documentation files (the "Software"), to deal
in the Software without restriction, including without limitation the rights
to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
copies of the Software, and to permit persons to whom the Software is
furnished to do so, subject to the following conditions:

The above copyright notice and this permission notice shall be included in
all copies or substantial portions of the Software.

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
THE SOFTWARE.
```

<a id="go-legal-text-g038"></a>

### Go legal text G038

SHA-256: `4835612df0098ca95f8e7d9e3bffcb02358d435dbb38057c844c99d7f725eb20`. Origins:

- `go.temporal.io/api` v1.62.14: [internal/protojson/LICENSE](https://proxy.golang.org/go.temporal.io/api/@v/v1.62.14.zip)
- `google.golang.org/protobuf` v1.36.11: [LICENSE](https://proxy.golang.org/google.golang.org/protobuf/@v/v1.36.11.zip)

```text
Copyright (c) 2018 The Go Authors. All rights reserved.

Redistribution and use in source and binary forms, with or without
modification, are permitted provided that the following conditions are
met:

   * Redistributions of source code must retain the above copyright
notice, this list of conditions and the following disclaimer.
   * Redistributions in binary form must reproduce the above
copyright notice, this list of conditions and the following disclaimer
in the documentation and/or other materials provided with the
distribution.
   * Neither the name of Google Inc. nor the names of its
contributors may be used to endorse or promote products derived from
this software without specific prior written permission.

THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS
"AS IS" AND ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT
LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR
A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT
OWNER OR CONTRIBUTORS BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL,
SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT
LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES; LOSS OF USE,
DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND ON ANY
THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT
(INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE
OF THIS SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.
```

<a id="go-legal-text-g113"></a>

### Go legal text G113

SHA-256: `e54f1257fb65239260831733ba0cafc4a702faa38636453fdd616ab1289eb419`. Origins:

- `go.temporal.io/sdk` v1.44.1: [LICENSE](https://proxy.golang.org/go.temporal.io/sdk/@v/v1.44.1.zip)
- `go.temporal.io/sdk/contrib/opentelemetry` v0.7.0: [LICENSE](https://proxy.golang.org/go.temporal.io/sdk/contrib/opentelemetry/@v/v0.7.0.zip)
- `go.temporal.io/sdk/contrib/tally` v0.2.0: [LICENSE](https://proxy.golang.org/go.temporal.io/sdk/contrib/tally/@v/v0.2.0.zip)

```text
The MIT License

Copyright (c) 2020 Temporal Technologies Inc.  All rights reserved.

Copyright (c) 2020 Uber Technologies, Inc.

Permission is hereby granted, free of charge, to any person obtaining a copy
of this software and associated documentation files (the "Software"), to deal
in the Software without restriction, including without limitation the rights
to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
copies of the Software, and to permit persons to whom the Software is
furnished to do so, subject to the following conditions:

The above copyright notice and this permission notice shall be included in
all copies or substantial portions of the Software.

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
THE SOFTWARE.
```

<a id="go-legal-text-g056"></a>

### Go legal text G056

SHA-256: `6aab9afd99ceb1dcaf6a0d91386b46baef9bce17ae4006044e0f231e31430b69`. Origins:

- `go.temporal.io/server` v1.31.1: [LICENSE](https://proxy.golang.org/go.temporal.io/server/@v/v1.31.1.zip)

```text
The MIT License

Copyright (c) 2025 Temporal Technologies Inc.  All rights reserved.

Copyright (c) 2020 Uber Technologies, Inc.

Permission is hereby granted, free of charge, to any person obtaining a copy
of this software and associated documentation files (the "Software"), to deal
in the Software without restriction, including without limitation the rights
to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
copies of the Software, and to permit persons to whom the Software is
furnished to do so, subject to the following conditions:

The above copyright notice and this permission notice shall be included in
all copies or substantial portions of the Software.

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
THE SOFTWARE.
```

<a id="go-legal-text-g122"></a>

### Go legal text G122

SHA-256: `fb65aa57b8a932ede6e6bfc2f8e034a69223f7a38cf4bacc4a59925ff26d18d3`. Origins:

- `go.temporal.io/server` v1.31.1: [common/persistence/sql/sqlplugin/sqlite/LICENSE](https://proxy.golang.org/go.temporal.io/server/@v/v1.31.1.zip)
- `go.temporal.io/server` v1.31.1: [schema/sqlite/LICENSE](https://proxy.golang.org/go.temporal.io/server/@v/v1.31.1.zip)
- `go.temporal.io/server` v1.31.1: [temporaltest/LICENSE](https://proxy.golang.org/go.temporal.io/server/@v/v1.31.1.zip)

```text
The MIT License

Copyright (c) 2025 Temporal Technologies Inc.  All rights reserved.

Copyright (c) 2020 Uber Technologies, Inc.

Copyright (c) 2021 Datadog, Inc.

Permission is hereby granted, free of charge, to any person obtaining a copy
of this software and associated documentation files (the "Software"), to deal
in the Software without restriction, including without limitation the rights
to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
copies of the Software, and to permit persons to whom the Software is
furnished to do so, subject to the following conditions:

The above copyright notice and this permission notice shall be included in
all copies or substantial portions of the Software.

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
THE SOFTWARE.
```

<a id="go-legal-text-g063"></a>

### Go legal text G063

SHA-256: `812129b1d9a7f8969ef7a23888d141feca9a952d292dfabd5a54ae4f8a58b278`. Origins:

- `go.temporal.io/server` v1.31.1: [common/persistence/visibility/store/query/LICENSE](https://proxy.golang.org/go.temporal.io/server/@v/v1.31.1.zip)

```text
The MIT License

Copyright (c) 2025 Temporal Technologies Inc.  All rights reserved.

Copyright (c) 2020 Uber Technologies, Inc.

Copyright (c) 2017 Xargin

Permission is hereby granted, free of charge, to any person obtaining a copy
of this software and associated documentation files (the "Software"), to deal
in the Software without restriction, including without limitation the rights
to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
copies of the Software, and to permit persons to whom the Software is
furnished to do so, subject to the following conditions:

The above copyright notice and this permission notice shall be included in
all copies or substantial portions of the Software.

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
THE SOFTWARE.
```

<a id="go-legal-text-g080"></a>

### Go legal text G080

SHA-256: `a45ab36ebb6a45fe0441cdd99d25398e3054029b10bf7092d42e1f33926eddd5`. Origins:

- `go.temporal.io/server` v1.31.1: [common/testing/protoassert/LICENSE](https://proxy.golang.org/go.temporal.io/server/@v/v1.31.1.zip)

```text
The MIT License

Copyright (c) 2025 Temporal Technologies Inc.  All rights reserved.

Copyright (c) 2012-2020 Mat Ryer, Tyler Bunnell and contributors.

Permission is hereby granted, free of charge, to any person obtaining a copy
of this software and associated documentation files (the "Software"), to deal
in the Software without restriction, including without limitation the rights
to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
copies of the Software, and to permit persons to whom the Software is
furnished to do so, subject to the following conditions:

The above copyright notice and this permission notice shall be included in
all copies or substantial portions of the Software.

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
THE SOFTWARE.
```

<a id="go-legal-text-g115"></a>

### Go legal text G115

SHA-256: `edbb5a4d165ac69376c765b551c0662ff42bea87e1f1eda85f42ac90c34b09d0`. Origins:

- `go.uber.org/atomic` v1.11.0: [LICENSE.txt](https://proxy.golang.org/go.uber.org/atomic/@v/v1.11.0.zip)

```text
Copyright (c) 2016 Uber Technologies, Inc.

Permission is hereby granted, free of charge, to any person obtaining a copy
of this software and associated documentation files (the "Software"), to deal
in the Software without restriction, including without limitation the rights
to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
copies of the Software, and to permit persons to whom the Software is
furnished to do so, subject to the following conditions:

The above copyright notice and this permission notice shall be included in
all copies or substantial portions of the Software.

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
THE SOFTWARE.
```

<a id="go-legal-text-g106"></a>

### Go legal text G106

SHA-256: `dcdabe03bef2382a130640d1c3a4cd5ec42aba1035095c38272fde694eb72405`. Origins:

- `go.uber.org/multierr` v1.11.0: [LICENSE.txt](https://proxy.golang.org/go.uber.org/multierr/@v/v1.11.0.zip)

```text
Copyright (c) 2017-2021 Uber Technologies, Inc.

Permission is hereby granted, free of charge, to any person obtaining a copy
of this software and associated documentation files (the "Software"), to deal
in the Software without restriction, including without limitation the rights
to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
copies of the Software, and to permit persons to whom the Software is
furnished to do so, subject to the following conditions:

The above copyright notice and this permission notice shall be included in
all copies or substantial portions of the Software.

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
THE SOFTWARE.
```

<a id="go-legal-text-g097"></a>

### Go legal text G097

SHA-256: `c2b97b3281be272711909076c8402499b4b5a3216196af47a25b1d3674d86152`. Origins:

- `go.uber.org/zap` v1.28.0: [LICENSE](https://proxy.golang.org/go.uber.org/zap/@v/v1.28.0.zip)
- `go.uber.org/zap/exp` v0.3.0: [LICENSE](https://proxy.golang.org/go.uber.org/zap/exp/@v/v0.3.0.zip)

```text
Copyright (c) 2016-2024 Uber Technologies, Inc.

Permission is hereby granted, free of charge, to any person obtaining a copy
of this software and associated documentation files (the "Software"), to deal
in the Software without restriction, including without limitation the rights
to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
copies of the Software, and to permit persons to whom the Software is
furnished to do so, subject to the following conditions:

The above copyright notice and this permission notice shall be included in
all copies or substantial portions of the Software.

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
THE SOFTWARE.
```

<a id="go-legal-text-g102"></a>

### Go legal text G102

SHA-256: `d18f6323b71b0b768bb5e9616e36da390fbd39369a81807cca352de4e4e6aa0b`. Origins:

- `go.yaml.in/yaml/v3` v3.0.4: [LICENSE](https://proxy.golang.org/go.yaml.in/yaml/v3/@v/v3.0.4.zip)
- `gopkg.in/yaml.v3` v3.0.1: [LICENSE](https://proxy.golang.org/gopkg.in/yaml.v3/@v/v3.0.1.zip)

```text

This project is covered by two different licenses: MIT and Apache.

#### MIT License ####

The following files were ported to Go from C files of libyaml, and thus
are still covered by their original MIT license, with the additional
copyright staring in 2011 when the project was ported over:

    apic.go emitterc.go parserc.go readerc.go scannerc.go
    writerc.go yamlh.go yamlprivateh.go

Copyright (c) 2006-2010 Kirill Simonov
Copyright (c) 2006-2011 Kirill Simonov

Permission is hereby granted, free of charge, to any person obtaining a copy of
this software and associated documentation files (the "Software"), to deal in
the Software without restriction, including without limitation the rights to
use, copy, modify, merge, publish, distribute, sublicense, and/or sell copies
of the Software, and to permit persons to whom the Software is furnished to do
so, subject to the following conditions:

The above copyright notice and this permission notice shall be included in all
copies or substantial portions of the Software.

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
SOFTWARE.

### Apache License ###

All the remaining project files are covered by the Apache license:

Copyright (c) 2011-2019 Canonical Ltd

Licensed under the Apache License, Version 2.0 (the "License");
you may not use this file except in compliance with the License.
You may obtain a copy of the License at

    http://www.apache.org/licenses/LICENSE-2.0

Unless required by applicable law or agreed to in writing, software
distributed under the License is distributed on an "AS IS" BASIS,
WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
See the License for the specific language governing permissions and
limitations under the License.
```

<a id="go-legal-text-g120"></a>

### Go legal text G120

SHA-256: `f6c2dd3a67b576eafb89b80200b8b1627230bf3821a0c14cb99a22ac19107d00`. Origins:

- `go.yaml.in/yaml/v3` v3.0.4: [NOTICE](https://proxy.golang.org/go.yaml.in/yaml/v3/@v/v3.0.4.zip)
- `gopkg.in/yaml.v3` v3.0.1: [NOTICE](https://proxy.golang.org/gopkg.in/yaml.v3/@v/v3.0.1.zip)

```text
Copyright 2011-2016 Canonical Ltd.

Licensed under the Apache License, Version 2.0 (the "License");
you may not use this file except in compliance with the License.
You may obtain a copy of the License at

    http://www.apache.org/licenses/LICENSE-2.0

Unless required by applicable law or agreed to in writing, software
distributed under the License is distributed on an "AS IS" BASIS,
WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
See the License for the specific language governing permissions and
limitations under the License.
```

<a id="go-legal-text-g073"></a>

### Go legal text G073

SHA-256: `911f8f5782931320f5b8d1160a76365b83aea6447ee6c04fa6d5591467db9dad`. Origins:

- `golang.org/x/crypto` v0.53.0: [LICENSE](https://proxy.golang.org/golang.org/x/crypto/@v/v0.53.0.zip)
- `golang.org/x/net` v0.56.0: [LICENSE](https://proxy.golang.org/golang.org/x/net/@v/v0.56.0.zip)
- `golang.org/x/oauth2` v0.36.0: [LICENSE](https://proxy.golang.org/golang.org/x/oauth2/@v/v0.36.0.zip)
- `golang.org/x/sync` v0.21.0: [LICENSE](https://proxy.golang.org/golang.org/x/sync/@v/v0.21.0.zip)
- `golang.org/x/sys` v0.46.0: [LICENSE](https://proxy.golang.org/golang.org/x/sys/@v/v0.46.0.zip)
- `golang.org/x/text` v0.38.0: [LICENSE](https://proxy.golang.org/golang.org/x/text/@v/v0.38.0.zip)
- `golang.org/x/time` v0.15.0: [LICENSE](https://proxy.golang.org/golang.org/x/time/@v/v0.15.0.zip)

```text
Copyright 2009 The Go Authors.

Redistribution and use in source and binary forms, with or without
modification, are permitted provided that the following conditions are
met:

   * Redistributions of source code must retain the above copyright
notice, this list of conditions and the following disclaimer.
   * Redistributions in binary form must reproduce the above
copyright notice, this list of conditions and the following disclaimer
in the documentation and/or other materials provided with the
distribution.
   * Neither the name of Google LLC nor the names of its
contributors may be used to endorse or promote products derived from
this software without specific prior written permission.

THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS
"AS IS" AND ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT
LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR
A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT
OWNER OR CONTRIBUTORS BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL,
SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT
LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES; LOSS OF USE,
DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND ON ANY
THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT
(INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE
OF THIS SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.
```

<a id="go-legal-text-g014"></a>

### Go legal text G014

SHA-256: `110244b02140866ee37d17fa7449436a377ec3b85a481fbb208f4c87964382de`. Origins:

- `google.golang.org/api` v0.285.0: [LICENSE](https://proxy.golang.org/google.golang.org/api/@v/v0.285.0.zip)

```text
Copyright (c) 2011 Google Inc. All rights reserved.

Redistribution and use in source and binary forms, with or without
modification, are permitted provided that the following conditions are
met:

   * Redistributions of source code must retain the above copyright
notice, this list of conditions and the following disclaimer.
   * Redistributions in binary form must reproduce the above
copyright notice, this list of conditions and the following disclaimer
in the documentation and/or other materials provided with the
distribution.
   * Neither the name of Google Inc. nor the names of its
contributors may be used to endorse or promote products derived from
this software without specific prior written permission.

THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS
"AS IS" AND ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT
LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR
A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT
OWNER OR CONTRIBUTORS BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL,
SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT
LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES; LOSS OF USE,
DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND ON ANY
THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT
(INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE
OF THIS SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.
```

<a id="go-legal-text-g123"></a>

### Go legal text G123

SHA-256: `fc0a2f71df4e8f047902da53d1f85301be43e0f360fc167057a2d04658ed2ba9`. Origins:

- `google.golang.org/api` v0.285.0: [internal/third_party/uritemplates/LICENSE](https://proxy.golang.org/google.golang.org/api/@v/v0.285.0.zip)

```text
Copyright (c) 2013 Joshua Tacoma. All rights reserved.

Redistribution and use in source and binary forms, with or without
modification, are permitted provided that the following conditions are
met:

   * Redistributions of source code must retain the above copyright
notice, this list of conditions and the following disclaimer.
   * Redistributions in binary form must reproduce the above
copyright notice, this list of conditions and the following disclaimer
in the documentation and/or other materials provided with the
distribution.
   * Neither the name of Google Inc. nor the names of its
contributors may be used to endorse or promote products derived from
this software without specific prior written permission.

THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS
"AS IS" AND ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT
LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR
A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT
OWNER OR CONTRIBUTORS BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL,
SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT
LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES; LOSS OF USE,
DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND ON ANY
THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT
(INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE
OF THIS SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.
```

<a id="go-legal-text-g053"></a>

### Go legal text G053

SHA-256: `693ff28ec216d5112ac1bbfe64ef539005867d1c7bd427b57d579683293b947f`. Origins:

- `google.golang.org/grpc` v1.81.1: [NOTICE.txt](https://proxy.golang.org/google.golang.org/grpc/@v/v1.81.1.zip)

```text
Copyright 2014 gRPC authors.

Licensed under the Apache License, Version 2.0 (the "License");
you may not use this file except in compliance with the License.
You may obtain a copy of the License at

    http://www.apache.org/licenses/LICENSE-2.0

Unless required by applicable law or agreed to in writing, software
distributed under the License is distributed on an "AS IS" BASIS,
WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
See the License for the specific language governing permissions and
limitations under the License.
```

<a id="go-legal-text-g074"></a>

### Go legal text G074

SHA-256: `9622cbfd055d92991dcf523a256840424cc6356eab93beb4a93a973dec046492`. Origins:

- `gopkg.in/natefinch/lumberjack.v2` v2.2.1: [LICENSE](https://proxy.golang.org/gopkg.in/natefinch/lumberjack.v2/@v/v2.2.1.zip)

```text
The MIT License (MIT)

Copyright (c) 2014 Nate Finch 

Permission is hereby granted, free of charge, to any person obtaining a copy
of this software and associated documentation files (the "Software"), to deal
in the Software without restriction, including without limitation the rights
to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
copies of the Software, and to permit persons to whom the Software is
furnished to do so, subject to the following conditions:

The above copyright notice and this permission notice shall be included in all
copies or substantial portions of the Software.

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
SOFTWARE.
```


## PHP and bundled components

The app and worker runtime includes PHP 8.5.11, compiled from the official
[PHP 8.5.11 source](https://www.php.net/distributions/php-8.5.11.tar.xz) with
the configure options in [the Docker recipe](.docker/Dockerfile.roadrunner).
The source archive SHA-256 is
`d9be75c08e8c316f4c8f4194d8fbe1750a15f6a6d9d4e3fe72082abeeb800360`.
The runtime enables GNU Readline 8.2 (Debian 8.2-1.3, GPL-3-or-later) and
mbstring with its bundled libmbfl 1.3.2 (LGPL-2.1).

For the code covered by PHP License 3.01 and Zend Engine License 2.00, this
runtime distribution uses the published PHP License 4 and Zend Engine License
3.0 options, respectively (BSD-3-Clause). The exact PHP source license §5 and
Zend source license §4 permit published subsequent versions. The
[PHP Group's published licensing information](https://www.php.net/license/)
expressly applies PHP License 4 to earlier PHP versions and records Zend
Technologies' publication of Zend Engine License 3.0 under that clause.
File-specific third-party terms reproduced below continue to apply.
Packio's first-party MIT license is unchanged.

PHP License 4 / Zend Engine License 3.0 effective BSD-3-Clause text as published by PHP.net:

~~~text
Copyright © The PHP Group and Contributors.
Copyright © Zend Technologies Ltd., a subsidiary company of Perforce Software, Inc.

Redistribution and use in source and binary forms, with or without modification, are permitted provided that the following conditions are met:

1. Redistributions of source code must retain the above copyright notice, this list of conditions and the following disclaimer.
2. Redistributions in binary form must reproduce the above copyright notice, this list of conditions and the following disclaimer in the documentation and/or other materials provided with the distribution.
3. Neither the name of the copyright holder nor the names of its contributors may be used to endorse or promote products derived from this software without specific prior written permission.

THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS "AS IS" AND ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE ARE DISCLAIMED.

IN NO EVENT SHALL THE COPYRIGHT HOLDER OR CONTRIBUTORS BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.
~~~

The original PHP and Zend license texts are retained below with their original
acknowledgments. GNU Readline's GPL terms apply to the linked runtime.
The corresponding-source payloads and reconstruction instructions accompany
the source snapshot; see the distribution section above.

### PHP License 3.01 (legacy source terms; §5 successor clause)

Upstream path `php-8.5.11/LICENSE`; SHA-256 `b42e4df5e50e6ecda1047d503d6d91d71032d09ed1027ba1ef29eed26f890c5a`.

~~~text
--------------------------------------------------------------------
                  The PHP License, version 3.01
Copyright (c) 1999 - 2024 The PHP Group. All rights reserved.
--------------------------------------------------------------------

Redistribution and use in source and binary forms, with or without
modification, is permitted provided that the following conditions
are met:

  1. Redistributions of source code must retain the above copyright
     notice, this list of conditions and the following disclaimer.

  2. Redistributions in binary form must reproduce the above copyright
     notice, this list of conditions and the following disclaimer in
     the documentation and/or other materials provided with the
     distribution.

  3. The name "PHP" must not be used to endorse or promote products
     derived from this software without prior written permission. For
     written permission, please contact group@php.net.

  4. Products derived from this software may not be called "PHP", nor
     may "PHP" appear in their name, without prior written permission
     from group@php.net.  You may indicate that your software works in
     conjunction with PHP by saying "Foo for PHP" instead of calling
     it "PHP Foo" or "phpfoo"

  5. The PHP Group may publish revised and/or new versions of the
     license from time to time. Each version will be given a
     distinguishing version number.
     Once covered code has been published under a particular version
     of the license, you may always continue to use it under the terms
     of that version. You may also choose to use such covered code
     under the terms of any subsequent version of the license
     published by the PHP Group. No one other than the PHP Group has
     the right to modify the terms applicable to covered code created
     under this License.

  6. Redistributions of any form whatsoever must retain the following
     acknowledgment:
     "This product includes PHP software, freely available from
     <http://www.php.net/software/>".

THIS SOFTWARE IS PROVIDED BY THE PHP DEVELOPMENT TEAM ``AS IS'' AND
ANY EXPRESSED OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO,
THE IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A
PARTICULAR PURPOSE ARE DISCLAIMED.  IN NO EVENT SHALL THE PHP
DEVELOPMENT TEAM OR ITS CONTRIBUTORS BE LIABLE FOR ANY DIRECT,
INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES
(INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR
SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION)
HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN CONTRACT,
STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE)
ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED
OF THE POSSIBILITY OF SUCH DAMAGE.

--------------------------------------------------------------------

This software consists of voluntary contributions made by many
individuals on behalf of the PHP Group.

The PHP Group can be contacted via Email at group@php.net.

For more information on the PHP Group and the PHP project,
please see <http://www.php.net>.

PHP includes the Zend Engine, freely available at
<http://www.zend.com>.
~~~

### TSRM bundled license

Upstream path `php-8.5.11/TSRM/LICENSE`; SHA-256 `fe97b8423e34199f7df3e65fd53c4df067a56ab6fe0f2296474ba7da0656460c`.

~~~text
Copyright (c) 1999-2006, Andi Gutmans, Sascha Schumann, Zeev Suraski.
All rights reserved.

Redistribution and use in source and binary forms, with or without
modification, are permitted provided that the following conditions are
met:

- Redistributions of source code must retain the above copyright notice,
this list of conditions and the following disclaimer.

- Neither name of the copyright holders nor the names of their contributors
may be used to endorse or promote products derived from this software
without specific prior written permission.


THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS ``AS
IS'' AND ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO,
THE IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR
PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT HOLDERS OR
CONTRIBUTORS BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL,
EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT LIMITED TO,
PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES; LOSS OF USE, DATA, OR
PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY OF
LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING
NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE OF THIS
SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.
~~~

### Zend Engine License 2.00 (legacy source terms; §4 successor clause)

Upstream path `php-8.5.11/Zend/LICENSE`; SHA-256 `509aeb3be64e428d7e0004ce224620ab01bf94adaacefbb449611939df56bc5b`.

~~~text
--------------------------------------------------------------------
                The Zend Engine License, Version 2.00
Copyright (c) 1999-2006 Zend Technologies Ltd. All rights reserved.
--------------------------------------------------------------------

Redistribution and use in source and binary forms, with or without
modification, is permitted provided that the following conditions
are met:

  1. Redistributions of source code must retain the above copyright
     notice, this list of conditions and the following disclaimer.

  2. Redistributions in binary form must reproduce the above
     copyright notice, this list of conditions and the following
     disclaimer in the documentation and/or other materials provided
     with the distribution.

  3. The names "Zend" and "Zend Engine" must not be used to endorse
     or promote products derived from this software without prior
     permission from Zend Technologies Ltd. For written permission,
     please contact license@zend.com.

  4. Zend Technologies Ltd. may publish revised and/or new versions
     of the license from time to time. Each version will be given a
     distinguishing version number.
     Once covered code has been published under a particular version
     of the license, you may always continue to use it under the
     terms of that version. You may also choose to use such covered
     code under the terms of any subsequent version of the license
     published by Zend Technologies Ltd. No one other than Zend
     Technologies Ltd. has the right to modify the terms applicable
     to covered code created under this License.

  5. Redistributions of any form whatsoever must retain the following
     acknowledgment:
     "This product includes the Zend Engine, freely available at
     http://www.zend.com"

  6. All advertising materials mentioning features or use of this
     software must display the following acknowledgment:
     "The Zend Engine is freely available at http://www.zend.com"

THIS SOFTWARE IS PROVIDED BY ZEND TECHNOLOGIES LTD. ``AS IS'' AND
ANY EXPRESSED OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO,
THE IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A
PARTICULAR PURPOSE ARE DISCLAIMED.  IN NO EVENT SHALL ZEND
TECHNOLOGIES LTD.  BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL,
SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT
LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES; LOSS OF
USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND
ON ANY THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY,
OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT
OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF
SUCH DAMAGE.

--------------------------------------------------------------------
~~~

### Zend asm Boost Software License 1.0

Upstream path `php-8.5.11/Zend/asm/LICENSE`; SHA-256 `c9bff75738922193e67fa726fa225535870d2aa1059f91452c411736284ad566`.

~~~text
Boost Software License - Version 1.0 - August 17th, 2003

Permission is hereby granted, free of charge, to any person or organization
obtaining a copy of the software and accompanying documentation covered by
this license (the "Software") to use, reproduce, display, distribute,
execute, and transmit the Software, and to prepare derivative works of the
Software, and to permit third-parties to whom the Software is furnished to
do so, all subject to the following:

The copyright notices in the Software and this entire statement, including
the above license grant, this restriction and the following disclaimer,
must be included in all copies of the Software, in whole or in part, and
all derivative works of the Software, unless such copies or derivative
works are solely in the form of machine-executable object code generated by
a source language processor.

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
FITNESS FOR A PARTICULAR PURPOSE, TITLE AND NON-INFRINGEMENT. IN NO EVENT
SHALL THE COPYRIGHT HOLDERS OR ANYONE DISTRIBUTING THE SOFTWARE BE LIABLE
FOR ANY DAMAGES OR OTHER LIABILITY, WHETHER IN CONTRACT, TORT OR OTHERWISE,
ARISING FROM, OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER
DEALINGS IN THE SOFTWARE.
~~~

### timelib license

Upstream path `php-8.5.11/ext/date/lib/LICENSE.rst`; SHA-256 `2120d029e7c67b9d08c1f665f1c3ed581206f00970b664cccdebe1419bf566e9`.

~~~text
The MIT License (MIT)

Copyright (c) 2015-2021 Derick Rethans
Copyright (c) 2017-2019,2021 MongoDB, Inc.

Permission is hereby granted, free of charge, to any person obtaining a copy
of this software and associated documentation files (the "Software"), to deal
in the Software without restriction, including without limitation the rights
to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
copies of the Software, and to permit persons to whom the Software is
furnished to do so, subject to the following conditions:

The above copyright notice and this permission notice shall be included in
all copies or substantial portions of the Software.

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
THE SOFTWARE.
~~~

### libmagic license

Upstream path `php-8.5.11/ext/fileinfo/libmagic/LICENSE`; SHA-256 `0bfa856a9930bddadbef95d1be1cf4e163c0be618e76ea3275caaf255283e274`.

~~~text
$File: COPYING,v 1.2 2018/09/09 20:33:28 christos Exp $
Copyright (c) Ian F. Darwin 1986, 1987, 1989, 1990, 1991, 1992, 1994, 1995.
Software written by Ian F. Darwin and others;
maintained 1994- Christos Zoulas.

This software is not subject to any export provision of the United States
Department of Commerce, and may be exported to any country or planet.

Redistribution and use in source and binary forms, with or without
modification, are permitted provided that the following conditions
are met:
1. Redistributions of source code must retain the above copyright
   notice immediately at the beginning of the file, without modification,
   this list of conditions, and the following disclaimer.
2. Redistributions in binary form must reproduce the above copyright
   notice, this list of conditions and the following disclaimer in the
   documentation and/or other materials provided with the distribution.

THIS SOFTWARE IS PROVIDED BY THE AUTHOR AND CONTRIBUTORS ``AS IS'' AND
ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE
IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE
ARE DISCLAIMED. IN NO EVENT SHALL THE AUTHOR OR CONTRIBUTORS BE LIABLE FOR
ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL
DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS
OR SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION)
HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT
LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY
OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF
SUCH DAMAGE.
~~~

### Lexbor Apache license

Upstream path `php-8.5.11/ext/lexbor/LICENSE`; SHA-256 `0d542e0c8804e39aa7f37eb00da5a762149dc682d7829451287e11b938e94594`.

~~~text

                                 Apache License
                           Version 2.0, January 2004
                        http://www.apache.org/licenses/

   TERMS AND CONDITIONS FOR USE, REPRODUCTION, AND DISTRIBUTION

   1. Definitions.

      "License" shall mean the terms and conditions for use, reproduction,
      and distribution as defined by Sections 1 through 9 of this document.

      "Licensor" shall mean the copyright owner or entity authorized by
      the copyright owner that is granting the License.

      "Legal Entity" shall mean the union of the acting entity and all
      other entities that control, are controlled by, or are under common
      control with that entity. For the purposes of this definition,
      "control" means (i) the power, direct or indirect, to cause the
      direction or management of such entity, whether by contract or
      otherwise, or (ii) ownership of fifty percent (50%) or more of the
      outstanding shares, or (iii) beneficial ownership of such entity.

      "You" (or "Your") shall mean an individual or Legal Entity
      exercising permissions granted by this License.

      "Source" form shall mean the preferred form for making modifications,
      including but not limited to software source code, documentation
      source, and configuration files.

      "Object" form shall mean any form resulting from mechanical
      transformation or translation of a Source form, including but
      not limited to compiled object code, generated documentation,
      and conversions to other media types.

      "Work" shall mean the work of authorship, whether in Source or
      Object form, made available under the License, as indicated by a
      copyright notice that is included in or attached to the work
      (an example is provided in the Appendix below).

      "Derivative Works" shall mean any work, whether in Source or Object
      form, that is based on (or derived from) the Work and for which the
      editorial revisions, annotations, elaborations, or other modifications
      represent, as a whole, an original work of authorship. For the purposes
      of this License, Derivative Works shall not include works that remain
      separable from, or merely link (or bind by name) to the interfaces of,
      the Work and Derivative Works thereof.

      "Contribution" shall mean any work of authorship, including
      the original version of the Work and any modifications or additions
      to that Work or Derivative Works thereof, that is intentionally
      submitted to Licensor for inclusion in the Work by the copyright owner
      or by an individual or Legal Entity authorized to submit on behalf of
      the copyright owner. For the purposes of this definition, "submitted"
      means any form of electronic, verbal, or written communication sent
      to the Licensor or its representatives, including but not limited to
      communication on electronic mailing lists, source code control systems,
      and issue tracking systems that are managed by, or on behalf of, the
      Licensor for the purpose of discussing and improving the Work, but
      excluding communication that is conspicuously marked or otherwise
      designated in writing by the copyright owner as "Not a Contribution."

      "Contributor" shall mean Licensor and any individual or Legal Entity
      on behalf of whom a Contribution has been received by Licensor and
      subsequently incorporated within the Work.

   2. Grant of Copyright License. Subject to the terms and conditions of
      this License, each Contributor hereby grants to You a perpetual,
      worldwide, non-exclusive, no-charge, royalty-free, irrevocable
      copyright license to reproduce, prepare Derivative Works of,
      publicly display, publicly perform, sublicense, and distribute the
      Work and such Derivative Works in Source or Object form.

   3. Grant of Patent License. Subject to the terms and conditions of
      this License, each Contributor hereby grants to You a perpetual,
      worldwide, non-exclusive, no-charge, royalty-free, irrevocable
      (except as stated in this section) patent license to make, have made,
      use, offer to sell, sell, import, and otherwise transfer the Work,
      where such license applies only to those patent claims licensable
      by such Contributor that are necessarily infringed by their
      Contribution(s) alone or by combination of their Contribution(s)
      with the Work to which such Contribution(s) was submitted. If You
      institute patent litigation against any entity (including a
      cross-claim or counterclaim in a lawsuit) alleging that the Work
      or a Contribution incorporated within the Work constitutes direct
      or contributory patent infringement, then any patent licenses
      granted to You under this License for that Work shall terminate
      as of the date such litigation is filed.

   4. Redistribution. You may reproduce and distribute copies of the
      Work or Derivative Works thereof in any medium, with or without
      modifications, and in Source or Object form, provided that You
      meet the following conditions:

      (a) You must give any other recipients of the Work or
          Derivative Works a copy of this License; and

      (b) You must cause any modified files to carry prominent notices
          stating that You changed the files; and

      (c) You must retain, in the Source form of any Derivative Works
          that You distribute, all copyright, patent, trademark, and
          attribution notices from the Source form of the Work,
          excluding those notices that do not pertain to any part of
          the Derivative Works; and

      (d) If the Work includes a "NOTICE" text file as part of its
          distribution, then any Derivative Works that You distribute must
          include a readable copy of the attribution notices contained
          within such NOTICE file, excluding those notices that do not
          pertain to any part of the Derivative Works, in at least one
          of the following places: within a NOTICE text file distributed
          as part of the Derivative Works; within the Source form or
          documentation, if provided along with the Derivative Works; or,
          within a display generated by the Derivative Works, if and
          wherever such third-party notices normally appear. The contents
          of the NOTICE file are for informational purposes only and
          do not modify the License. You may add Your own attribution
          notices within Derivative Works that You distribute, alongside
          or as an addendum to the NOTICE text from the Work, provided
          that such additional attribution notices cannot be construed
          as modifying the License.

      You may add Your own copyright statement to Your modifications and
      may provide additional or different license terms and conditions
      for use, reproduction, or distribution of Your modifications, or
      for any such Derivative Works as a whole, provided Your use,
      reproduction, and distribution of the Work otherwise complies with
      the conditions stated in this License.

   5. Submission of Contributions. Unless You explicitly state otherwise,
      any Contribution intentionally submitted for inclusion in the Work
      by You to the Licensor shall be under the terms and conditions of
      this License, without any additional terms or conditions.
      Notwithstanding the above, nothing herein shall supersede or modify
      the terms of any separate license agreement you may have executed
      with Licensor regarding such Contributions.

   6. Trademarks. This License does not grant permission to use the trade
      names, trademarks, service marks, or product names of the Licensor,
      except as required for reasonable and customary use in describing the
      origin of the Work and reproducing the content of the NOTICE file.

   7. Disclaimer of Warranty. Unless required by applicable law or
      agreed to in writing, Licensor provides the Work (and each
      Contributor provides its Contributions) on an "AS IS" BASIS,
      WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or
      implied, including, without limitation, any warranties or conditions
      of TITLE, NON-INFRINGEMENT, MERCHANTABILITY, or FITNESS FOR A
      PARTICULAR PURPOSE. You are solely responsible for determining the
      appropriateness of using or redistributing the Work and assume any
      risks associated with Your exercise of permissions under this License.

   8. Limitation of Liability. In no event and under no legal theory,
      whether in tort (including negligence), contract, or otherwise,
      unless required by applicable law (such as deliberate and grossly
      negligent acts) or agreed to in writing, shall any Contributor be
      liable to You for damages, including any direct, indirect, special,
      incidental, or consequential damages of any character arising as a
      result of this License or out of the use or inability to use the
      Work (including but not limited to damages for loss of goodwill,
      work stoppage, computer failure or malfunction, or any and all
      other commercial damages or losses), even if such Contributor
      has been advised of the possibility of such damages.

   9. Accepting Warranty or Additional Liability. While redistributing
      the Work or Derivative Works thereof, You may choose to offer,
      and charge a fee for, acceptance of support, warranty, indemnity,
      or other liability obligations and/or rights consistent with this
      License. However, in accepting such obligations, You may act only
      on Your own behalf and on Your sole responsibility, not on behalf
      of any other Contributor, and only if You agree to indemnify,
      defend, and hold each Contributor harmless for any liability
      incurred by, or claims asserted against, such Contributor by reason
      of your accepting any such warranty or additional liability.

   END OF TERMS AND CONDITIONS
~~~

### Lexbor NOTICE

Upstream path `php-8.5.11/ext/lexbor/NOTICE`; SHA-256 `b87f965fd2eba846a0a502d633dd7e7a680b93de5c514c404c948ccf1e5c9dc7`.

~~~text

   Lexbor.

   Copyright 2018-2020 Alexander Borisov

   Licensed under the Apache License, Version 2.0 (the "License");
   you may not use this file except in compliance with the License.
   You may obtain a copy of the License at

       http://www.apache.org/licenses/LICENSE-2.0

   Unless required by applicable law or agreed to in writing, software
   distributed under the License is distributed on an "AS IS" BASIS,
   WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
   See the License for the specific language governing permissions and
   limitations under the License.
~~~

### libmbfl GNU LGPL v2.1

Upstream path `php-8.5.11/ext/mbstring/libmbfl/LICENSE`; SHA-256 `31d9cadcc63c15b79fc59c70f043956104a34c75f23f0ac0ada53c1edfeb88ca`.

~~~text
                  GNU LESSER GENERAL PUBLIC LICENSE
                       Version 2.1, February 1999

 Copyright (C) 1991, 1999 Free Software Foundation, Inc.
     59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
 Everyone is permitted to copy and distribute verbatim copies
 of this license document, but changing it is not allowed.

[This is the first released version of the Lesser GPL.  It also counts
 as the successor of the GNU Library Public License, version 2, hence
 the version number 2.1.]

                            Preamble

  The licenses for most software are designed to take away your
freedom to share and change it.  By contrast, the GNU General Public
Licenses are intended to guarantee your freedom to share and change
free software--to make sure the software is free for all its users.

  This license, the Lesser General Public License, applies to some
specially designated software packages--typically libraries--of the
Free Software Foundation and other authors who decide to use it.  You
can use it too, but we suggest you first think carefully about whether
this license or the ordinary General Public License is the better
strategy to use in any particular case, based on the explanations below.

  When we speak of free software, we are referring to freedom of use,
not price.  Our General Public Licenses are designed to make sure that
you have the freedom to distribute copies of free software (and charge
for this service if you wish); that you receive source code or can get
it if you want it; that you can change the software and use pieces of
it in new free programs; and that you are informed that you can do
these things.

  To protect your rights, we need to make restrictions that forbid
distributors to deny you these rights or to ask you to surrender these
rights.  These restrictions translate to certain responsibilities for
you if you distribute copies of the library or if you modify it.

  For example, if you distribute copies of the library, whether gratis
or for a fee, you must give the recipients all the rights that we gave
you.  You must make sure that they, too, receive or can get the source
code.  If you link other code with the library, you must provide
complete object files to the recipients, so that they can relink them
with the library after making changes to the library and recompiling
it.  And you must show them these terms so they know their rights.

  We protect your rights with a two-step method: (1) we copyright the
library, and (2) we offer you this license, which gives you legal
permission to copy, distribute and/or modify the library.

  To protect each distributor, we want to make it very clear that
there is no warranty for the free library.  Also, if the library is
modified by someone else and passed on, the recipients should know
that what they have is not the original version, so that the original
author's reputation will not be affected by problems that might be
introduced by others.

  Finally, software patents pose a constant threat to the existence of
any free program.  We wish to make sure that a company cannot
effectively restrict the users of a free program by obtaining a
restrictive license from a patent holder.  Therefore, we insist that
any patent license obtained for a version of the library must be
consistent with the full freedom of use specified in this license.

  Most GNU software, including some libraries, is covered by the
ordinary GNU General Public License.  This license, the GNU Lesser
General Public License, applies to certain designated libraries, and
is quite different from the ordinary General Public License.  We use
this license for certain libraries in order to permit linking those
libraries into non-free programs.

  When a program is linked with a library, whether statically or using
a shared library, the combination of the two is legally speaking a
combined work, a derivative of the original library.  The ordinary
General Public License therefore permits such linking only if the
entire combination fits its criteria of freedom.  The Lesser General
Public License permits more lax criteria for linking other code with
the library.

  We call this license the "Lesser" General Public License because it
does Less to protect the user's freedom than the ordinary General
Public License.  It also provides other free software developers Less
of an advantage over competing non-free programs.  These disadvantages
are the reason we use the ordinary General Public License for many
libraries.  However, the Lesser license provides advantages in certain
special circumstances.

  For example, on rare occasions, there may be a special need to
encourage the widest possible use of a certain library, so that it becomes
a de-facto standard.  To achieve this, non-free programs must be
allowed to use the library.  A more frequent case is that a free
library does the same job as widely used non-free libraries.  In this
case, there is little to gain by limiting the free library to free
software only, so we use the Lesser General Public License.

  In other cases, permission to use a particular library in non-free
programs enables a greater number of people to use a large body of
free software.  For example, permission to use the GNU C Library in
non-free programs enables many more people to use the whole GNU
operating system, as well as its variant, the GNU/Linux operating
system.

  Although the Lesser General Public License is Less protective of the
users' freedom, it does ensure that the user of a program that is
linked with the Library has the freedom and the wherewithal to run
that program using a modified version of the Library.

  The precise terms and conditions for copying, distribution and
modification follow.  Pay close attention to the difference between a
"work based on the library" and a "work that uses the library".  The
former contains code derived from the library, whereas the latter must
be combined with the library in order to run.

                  GNU LESSER GENERAL PUBLIC LICENSE
   TERMS AND CONDITIONS FOR COPYING, DISTRIBUTION AND MODIFICATION

  0. This License Agreement applies to any software library or other
program which contains a notice placed by the copyright holder or
other authorized party saying it may be distributed under the terms of
this Lesser General Public License (also called "this License").
Each licensee is addressed as "you".

  A "library" means a collection of software functions and/or data
prepared so as to be conveniently linked with application programs
(which use some of those functions and data) to form executables.

  The "Library", below, refers to any such software library or work
which has been distributed under these terms.  A "work based on the
Library" means either the Library or any derivative work under
copyright law: that is to say, a work containing the Library or a
portion of it, either verbatim or with modifications and/or translated
straightforwardly into another language.  (Hereinafter, translation is
included without limitation in the term "modification".)

  "Source code" for a work means the preferred form of the work for
making modifications to it.  For a library, complete source code means
all the source code for all modules it contains, plus any associated
interface definition files, plus the scripts used to control compilation
and installation of the library.

  Activities other than copying, distribution and modification are not
covered by this License; they are outside its scope.  The act of
running a program using the Library is not restricted, and output from
such a program is covered only if its contents constitute a work based
on the Library (independent of the use of the Library in a tool for
writing it).  Whether that is true depends on what the Library does
and what the program that uses the Library does.

  1. You may copy and distribute verbatim copies of the Library's
complete source code as you receive it, in any medium, provided that
you conspicuously and appropriately publish on each copy an
appropriate copyright notice and disclaimer of warranty; keep intact
all the notices that refer to this License and to the absence of any
warranty; and distribute a copy of this License along with the
Library.

  You may charge a fee for the physical act of transferring a copy,
and you may at your option offer warranty protection in exchange for a
fee.

  2. You may modify your copy or copies of the Library or any portion
of it, thus forming a work based on the Library, and copy and
distribute such modifications or work under the terms of Section 1
above, provided that you also meet all of these conditions:

    a) The modified work must itself be a software library.

    b) You must cause the files modified to carry prominent notices
    stating that you changed the files and the date of any change.

    c) You must cause the whole of the work to be licensed at no
    charge to all third parties under the terms of this License.

    d) If a facility in the modified Library refers to a function or a
    table of data to be supplied by an application program that uses
    the facility, other than as an argument passed when the facility
    is invoked, then you must make a good faith effort to ensure that,
    in the event an application does not supply such function or
    table, the facility still operates, and performs whatever part of
    its purpose remains meaningful.

    (For example, a function in a library to compute square roots has
    a purpose that is entirely well-defined independent of the
    application.  Therefore, Subsection 2d requires that any
    application-supplied function or table used by this function must
    be optional: if the application does not supply it, the square
    root function must still compute square roots.)

These requirements apply to the modified work as a whole.  If
identifiable sections of that work are not derived from the Library,
and can be reasonably considered independent and separate works in
themselves, then this License, and its terms, do not apply to those
sections when you distribute them as separate works.  But when you
distribute the same sections as part of a whole which is a work based
on the Library, the distribution of the whole must be on the terms of
this License, whose permissions for other licensees extend to the
entire whole, and thus to each and every part regardless of who wrote
it.

Thus, it is not the intent of this section to claim rights or contest
your rights to work written entirely by you; rather, the intent is to
exercise the right to control the distribution of derivative or
collective works based on the Library.

In addition, mere aggregation of another work not based on the Library
with the Library (or with a work based on the Library) on a volume of
a storage or distribution medium does not bring the other work under
the scope of this License.

  3. You may opt to apply the terms of the ordinary GNU General Public
License instead of this License to a given copy of the Library.  To do
this, you must alter all the notices that refer to this License, so
that they refer to the ordinary GNU General Public License, version 2,
instead of to this License.  (If a newer version than version 2 of the
ordinary GNU General Public License has appeared, then you can specify
that version instead if you wish.)  Do not make any other change in
these notices.

  Once this change is made in a given copy, it is irreversible for
that copy, so the ordinary GNU General Public License applies to all
subsequent copies and derivative works made from that copy.

  This option is useful when you wish to copy part of the code of
the Library into a program that is not a library.

  4. You may copy and distribute the Library (or a portion or
derivative of it, under Section 2) in object code or executable form
under the terms of Sections 1 and 2 above provided that you accompany
it with the complete corresponding machine-readable source code, which
must be distributed under the terms of Sections 1 and 2 above on a
medium customarily used for software interchange.

  If distribution of object code is made by offering access to copy
from a designated place, then offering equivalent access to copy the
source code from the same place satisfies the requirement to
distribute the source code, even though third parties are not
compelled to copy the source along with the object code.

  5. A program that contains no derivative of any portion of the
Library, but is designed to work with the Library by being compiled or
linked with it, is called a "work that uses the Library".  Such a
work, in isolation, is not a derivative work of the Library, and
therefore falls outside the scope of this License.

  However, linking a "work that uses the Library" with the Library
creates an executable that is a derivative of the Library (because it
contains portions of the Library), rather than a "work that uses the
library".  The executable is therefore covered by this License.
Section 6 states terms for distribution of such executables.

  When a "work that uses the Library" uses material from a header file
that is part of the Library, the object code for the work may be a
derivative work of the Library even though the source code is not.
Whether this is true is especially significant if the work can be
linked without the Library, or if the work is itself a library.  The
threshold for this to be true is not precisely defined by law.

  If such an object file uses only numerical parameters, data
structure layouts and accessors, and small macros and small inline
functions (ten lines or less in length), then the use of the object
file is unrestricted, regardless of whether it is legally a derivative
work.  (Executables containing this object code plus portions of the
Library will still fall under Section 6.)

  Otherwise, if the work is a derivative of the Library, you may
distribute the object code for the work under the terms of Section 6.
Any executables containing that work also fall under Section 6,
whether or not they are linked directly with the Library itself.

  6. As an exception to the Sections above, you may also combine or
link a "work that uses the Library" with the Library to produce a
work containing portions of the Library, and distribute that work
under terms of your choice, provided that the terms permit
modification of the work for the customer's own use and reverse
engineering for debugging such modifications.

  You must give prominent notice with each copy of the work that the
Library is used in it and that the Library and its use are covered by
this License.  You must supply a copy of this License.  If the work
during execution displays copyright notices, you must include the
copyright notice for the Library among them, as well as a reference
directing the user to the copy of this License.  Also, you must do one
of these things:

    a) Accompany the work with the complete corresponding
    machine-readable source code for the Library including whatever
    changes were used in the work (which must be distributed under
    Sections 1 and 2 above); and, if the work is an executable linked
    with the Library, with the complete machine-readable "work that
    uses the Library", as object code and/or source code, so that the
    user can modify the Library and then relink to produce a modified
    executable containing the modified Library.  (It is understood
    that the user who changes the contents of definitions files in the
    Library will not necessarily be able to recompile the application
    to use the modified definitions.)

    b) Use a suitable shared library mechanism for linking with the
    Library.  A suitable mechanism is one that (1) uses at run time a
    copy of the library already present on the user's computer system,
    rather than copying library functions into the executable, and (2)
    will operate properly with a modified version of the library, if
    the user installs one, as long as the modified version is
    interface-compatible with the version that the work was made with.

    c) Accompany the work with a written offer, valid for at
    least three years, to give the same user the materials
    specified in Subsection 6a, above, for a charge no more
    than the cost of performing this distribution.

    d) If distribution of the work is made by offering access to copy
    from a designated place, offer equivalent access to copy the above
    specified materials from the same place.

    e) Verify that the user has already received a copy of these
    materials or that you have already sent this user a copy.

  For an executable, the required form of the "work that uses the
Library" must include any data and utility programs needed for
reproducing the executable from it.  However, as a special exception,
the materials to be distributed need not include anything that is
normally distributed (in either source or binary form) with the major
components (compiler, kernel, and so on) of the operating system on
which the executable runs, unless that component itself accompanies
the executable.

  It may happen that this requirement contradicts the license
restrictions of other proprietary libraries that do not normally
accompany the operating system.  Such a contradiction means you cannot
use both them and the Library together in an executable that you
distribute.

  7. You may place library facilities that are a work based on the
Library side-by-side in a single library together with other library
facilities not covered by this License, and distribute such a combined
library, provided that the separate distribution of the work based on
the Library and of the other library facilities is otherwise
permitted, and provided that you do these two things:

    a) Accompany the combined library with a copy of the same work
    based on the Library, uncombined with any other library
    facilities.  This must be distributed under the terms of the
    Sections above.

    b) Give prominent notice with the combined library of the fact
    that part of it is a work based on the Library, and explaining
    where to find the accompanying uncombined form of the same work.

  8. You may not copy, modify, sublicense, link with, or distribute
the Library except as expressly provided under this License.  Any
attempt otherwise to copy, modify, sublicense, link with, or
distribute the Library is void, and will automatically terminate your
rights under this License.  However, parties who have received copies,
or rights, from you under this License will not have their licenses
terminated so long as such parties remain in full compliance.

  9. You are not required to accept this License, since you have not
signed it.  However, nothing else grants you permission to modify or
distribute the Library or its derivative works.  These actions are
prohibited by law if you do not accept this License.  Therefore, by
modifying or distributing the Library (or any work based on the
Library), you indicate your acceptance of this License to do so, and
all its terms and conditions for copying, distributing or modifying
the Library or works based on it.

  10. Each time you redistribute the Library (or any work based on the
Library), the recipient automatically receives a license from the
original licensor to copy, distribute, link with or modify the Library
subject to these terms and conditions.  You may not impose any further
restrictions on the recipients' exercise of the rights granted herein.
You are not responsible for enforcing compliance by third parties with
this License.

  11. If, as a consequence of a court judgment or allegation of patent
infringement or for any other reason (not limited to patent issues),
conditions are imposed on you (whether by court order, agreement or
otherwise) that contradict the conditions of this License, they do not
excuse you from the conditions of this License.  If you cannot
distribute so as to satisfy simultaneously your obligations under this
License and any other pertinent obligations, then as a consequence you
may not distribute the Library at all.  For example, if a patent
license would not permit royalty-free redistribution of the Library by
all those who receive copies directly or indirectly through you, then
the only way you could satisfy both it and this License would be to
refrain entirely from distribution of the Library.

If any portion of this section is held invalid or unenforceable under any
particular circumstance, the balance of the section is intended to apply,
and the section as a whole is intended to apply in other circumstances.

It is not the purpose of this section to induce you to infringe any
patents or other property right claims or to contest validity of any
such claims; this section has the sole purpose of protecting the
integrity of the free software distribution system which is
implemented by public license practices.  Many people have made
generous contributions to the wide range of software distributed
through that system in reliance on consistent application of that
system; it is up to the author/donor to decide if he or she is willing
to distribute software through any other system and a licensee cannot
impose that choice.

This section is intended to make thoroughly clear what is believed to
be a consequence of the rest of this License.

  12. If the distribution and/or use of the Library is restricted in
certain countries either by patents or by copyrighted interfaces, the
original copyright holder who places the Library under this License may add
an explicit geographical distribution limitation excluding those countries,
so that distribution is permitted only in or among countries not thus
excluded.  In such case, this License incorporates the limitation as if
written in the body of this License.

  13. The Free Software Foundation may publish revised and/or new
versions of the Lesser General Public License from time to time.
Such new versions will be similar in spirit to the present version,
but may differ in detail to address new problems or concerns.

Each version is given a distinguishing version number.  If the Library
specifies a version number of this License which applies to it and
"any later version", you have the option of following the terms and
conditions either of that version or of any later version published by
the Free Software Foundation.  If the Library does not specify a
license version number, you may choose any version ever published by
the Free Software Foundation.

  14. If you wish to incorporate parts of the Library into other free
programs whose distribution conditions are incompatible with these,
write to the author to ask for permission.  For software which is
copyrighted by the Free Software Foundation, write to the Free
Software Foundation; we sometimes make exceptions for this.  Our
decision will be guided by the two goals of preserving the free status
of all derivatives of our free software and of promoting the sharing
and reuse of software generally.

                            NO WARRANTY

  15. BECAUSE THE LIBRARY IS LICENSED FREE OF CHARGE, THERE IS NO
WARRANTY FOR THE LIBRARY, TO THE EXTENT PERMITTED BY APPLICABLE LAW.
EXCEPT WHEN OTHERWISE STATED IN WRITING THE COPYRIGHT HOLDERS AND/OR
OTHER PARTIES PROVIDE THE LIBRARY "AS IS" WITHOUT WARRANTY OF ANY
KIND, EITHER EXPRESSED OR IMPLIED, INCLUDING, BUT NOT LIMITED TO, THE
IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR
PURPOSE.  THE ENTIRE RISK AS TO THE QUALITY AND PERFORMANCE OF THE
LIBRARY IS WITH YOU.  SHOULD THE LIBRARY PROVE DEFECTIVE, YOU ASSUME
THE COST OF ALL NECESSARY SERVICING, REPAIR OR CORRECTION.

  16. IN NO EVENT UNLESS REQUIRED BY APPLICABLE LAW OR AGREED TO IN
WRITING WILL ANY COPYRIGHT HOLDER, OR ANY OTHER PARTY WHO MAY MODIFY
AND/OR REDISTRIBUTE THE LIBRARY AS PERMITTED ABOVE, BE LIABLE TO YOU
FOR DAMAGES, INCLUDING ANY GENERAL, SPECIAL, INCIDENTAL OR
CONSEQUENTIAL DAMAGES ARISING OUT OF THE USE OR INABILITY TO USE THE
LIBRARY (INCLUDING BUT NOT LIMITED TO LOSS OF DATA OR DATA BEING
RENDERED INACCURATE OR LOSSES SUSTAINED BY YOU OR THIRD PARTIES OR A
FAILURE OF THE LIBRARY TO OPERATE WITH ANY OTHER SOFTWARE), EVEN IF
SUCH HOLDER OR OTHER PARTY HAS BEEN ADVISED OF THE POSSIBILITY OF SUCH
DAMAGES.

                     END OF TERMS AND CONDITIONS

"streamable kanji code filter and converter"

Copyright (c) 1998,1999,2000,2001 HappySize, Inc. All rights reserved.

This software is released under the GNU Lesser General Public License.
(Version 2.1, February 1999)
Please read the following detail of the licence (in japanese).

◆使用許諾条件◆

このソフトウェアは株式会社ハッピーサイズによって開発されました。株式会社ハッ
ピーサイズは、著作権法および万国著作権条約の定めにより、このソフトウェアに関
するすべての権利を留保する権利を持ち、ここに行使します。株式会社ハッピーサイ
ズは以下に明記した条件に従って、このソフトウェアを使用する排他的ではない権利
をお客様に許諾します。何人たりとも、以下の条件に反してこのソフトウェアを使用
することはできません。

このソフトウェアを「GNU Lesser General Public License (Version 2.1, February
1999)」に示された条件で使用することを、全ての方に許諾します。「GNU Lesser
General Public License」を満たさない使用には、株式会社ハッピーサイズから書面
による許諾を得る必要があります。

「GNU Lesser General Public License」の全文は以下のウェブページから取得でき
ます。「GNU Lesser General Public License」とは、これまでLibrary General
Public Licenseと呼ばれていたものです。
    http://www.gnu.org/ --- GNUウェブサイト
    http://www.gnu.org/copyleft/lesser.html --- ライセンス文面
このライセンスの内容がわからない方、守れない方には使用を許諾しません。

しかしながら、当社とGNUプロジェクトとの特定の関係を示唆または主張するもので
はありません。

◆保証内容◆

このソフトウェアは、期待された動作・機能・性能を持つことを目標として設計され
開発されていますが、これを保証するものではありません。このソフトウェアは「こ
のまま」の状態で提供されており、たとえばこのソフトウェアの有用性ないし特定の
目的に合致することといった、何らかの保証内容が、明示されたり暗黙に示されてい
る場合であっても、その保証は無効です。このソフトウェアを使用した結果ないし使
用しなかった結果によって、直接あるいは間接に受けた身体的な傷害、財産上の損害
、データの損失あるいはその他の全ての損害については、その損害の可能性が使用者
、当社あるいは第三者によって警告されていた場合であっても、当社はその損害の賠
償および補填を行いません。この規定は他の全ての、書面上または書面に無い保証・
契約・規定に優先します。

◆著作権者の連絡先および使用条件についての問い合わせ先◆

〒102-0073
東京都千代田区九段北1-13-5日本地所第一ビル4F
株式会社ハッピーサイズ
Phone: 03-3512-3655, Fax: 03-3512-3656
Email: sales@happysize.co.jp
Web: http://happysize.com/

◆著者◆

金本　茂 <sgk@happysize.co.jp>
~~~

### OPcache JIT IR license

Upstream path `php-8.5.11/ext/opcache/jit/ir/LICENSE`; SHA-256 `852f2a779458e30d09852bdf6b9a775eabfd4c9f8ac9e5a4b182a2942ae4c9e9`.

~~~text
MIT License

Copyright (c) 2022 Zend by Perforce
Copyright (c) 2025 Dmitry Stogov

Permission is hereby granted, free of charge, to any person obtaining a copy
of this software and associated documentation files (the "Software"), to deal
in the Software without restriction, including without limitation the rights
to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
copies of the Software, and to permit persons to whom the Software is
furnished to do so, subject to the following conditions:

The above copyright notice and this permission notice shall be included in all
copies or substantial portions of the Software.

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
SOFTWARE.
~~~

### libavifinfo BSD 2-Clause license

Upstream path `php-8.5.11/ext/standard/libavifinfo/LICENSE`; SHA-256 `5d40dcb72f934c3d08e10ddb8ba4844de17bf6f9aabc1a382aacf8a8bf4567f8`.

~~~text
Copyright (c) 2021, Alliance for Open Media. All rights reserved.

Redistribution and use in source and binary forms, with or without
modification, are permitted provided that the following conditions
are met:

1. Redistributions of source code must retain the above copyright
   notice, this list of conditions and the following disclaimer.

2. Redistributions in binary form must reproduce the above copyright
   notice, this list of conditions and the following disclaimer in
   the documentation and/or other materials provided with the
   distribution.

THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS
"AS IS" AND ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT
LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS
FOR A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE
COPYRIGHT HOLDER OR CONTRIBUTORS BE LIABLE FOR ANY DIRECT, INDIRECT,
INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING,
BUT NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES;
LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER
CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT
LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN
ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE
POSSIBILITY OF SUCH DAMAGE.
~~~

### Alliance for Open Media Patent License 1.0 text

Upstream path `php-8.5.11/ext/standard/libavifinfo/PATENTS`; SHA-256 `aef98cc6105f279463c37ec8c46dec5f2d133b6a91531ae9ec274e80fece9a88`.

~~~text
Alliance for Open Media Patent License 1.0

1. License Terms.

1.1. Patent License. Subject to the terms and conditions of this License, each
     Licensor, on behalf of itself and successors in interest and assigns,
     grants Licensee a non-sublicensable, perpetual, worldwide, non-exclusive,
     no-charge, royalty-free, irrevocable (except as expressly stated in this
     License) patent license to its Necessary Claims to make, use, sell, offer
     for sale, import or distribute any Implementation.

1.2. Conditions.

1.2.1. Availability. As a condition to the grant of rights to Licensee to make,
       sell, offer for sale, import or distribute an Implementation under
       Section 1.1, Licensee must make its Necessary Claims available under
       this License, and must reproduce this License with any Implementation
       as follows:

       a. For distribution in source code, by including this License in the
          root directory of the source code with its Implementation.

       b. For distribution in any other form (including binary, object form,
          and/or hardware description code (e.g., HDL, RTL, Gate Level Netlist,
          GDSII, etc.)), by including this License in the documentation, legal
          notices, and/or other written materials provided with the
          Implementation.

1.2.2. Additional Conditions. This license is directly from Licensor to
       Licensee.  Licensee acknowledges as a condition of benefiting from it
       that no rights from Licensor are received from suppliers, distributors,
       or otherwise in connection with this License.

1.3. Defensive Termination. If any Licensee, its Affiliates, or its agents
     initiates patent litigation or files, maintains, or voluntarily
     participates in a lawsuit against another entity or any person asserting
     that any Implementation infringes Necessary Claims, any patent licenses
     granted under this License directly to the Licensee are immediately
     terminated as of the date of the initiation of action unless 1) that suit
     was in response to a corresponding suit regarding an Implementation first
     brought against an initiating entity, or 2) that suit was brought to
     enforce the terms of this License (including intervention in a third-party
     action by a Licensee).

1.4. Disclaimers. The Reference Implementation and Specification are provided
     "AS IS" and without warranty. The entire risk as to implementing or
     otherwise using the Reference Implementation or Specification is assumed
     by the implementer and user. Licensor expressly disclaims any warranties
     (express, implied, or otherwise), including implied warranties of
     merchantability, non-infringement, fitness for a particular purpose, or
     title, related to the material. IN NO EVENT WILL LICENSOR BE LIABLE TO
     ANY OTHER PARTY FOR LOST PROFITS OR ANY FORM OF INDIRECT, SPECIAL,
     INCIDENTAL, OR CONSEQUENTIAL DAMAGES OF ANY CHARACTER FROM ANY CAUSES OF
     ACTION OF ANY KIND WITH RESPECT TO THIS LICENSE, WHETHER BASED ON BREACH
     OF CONTRACT, TORT (INCLUDING NEGLIGENCE), OR OTHERWISE, AND WHETHER OR
     NOT THE OTHER PARTRY HAS BEEN ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.

2. Definitions.

2.1. Affiliate.  "Affiliate" means an entity that directly or indirectly
     Controls, is Controlled by, or is under common Control of that party.

2.2. Control. "Control" means direct or indirect control of more than 50% of
     the voting power to elect directors of that corporation, or for any other
     entity, the power to direct management of such entity.

2.3. Decoder.  "Decoder" means any decoder that conforms fully with all
     non-optional portions of the Specification.

2.4. Encoder.  "Encoder" means any encoder that produces a bitstream that can
     be decoded by a Decoder only to the extent it produces such a bitstream.

2.5. Final Deliverable.  "Final Deliverable" means the final version of a
     deliverable approved by the Alliance for Open Media as a Final
     Deliverable.

2.6. Implementation.  "Implementation" means any implementation, including the
     Reference Implementation, that is an Encoder and/or a Decoder. An
     Implementation also includes components of an Implementation only to the
     extent they are used as part of an Implementation.

2.7. License. "License" means this license.

2.8. Licensee. "Licensee" means any person or entity who exercises patent
     rights granted under this License.

2.9. Licensor.  "Licensor" means (i) any Licensee that makes, sells, offers
     for sale, imports or distributes any Implementation, or (ii) a person
     or entity that has a licensing obligation to the Implementation as a
     result of its membership and/or participation in the Alliance for Open
     Media working group that developed the Specification.

2.10. Necessary Claims.  "Necessary Claims" means all claims of patents or
      patent applications, (a) that currently or at any time in the future,
      are owned or controlled by the Licensor, and (b) (i) would be an
      Essential Claim as defined by the W3C Policy as of February 5, 2004
      (https://www.w3.org/Consortium/Patent-Policy-20040205/#def-essential)
      as if the Specification was a W3C Recommendation; or (ii) are infringed
      by the Reference Implementation.

2.11. Reference Implementation. "Reference Implementation" means an Encoder
      and/or Decoder released by the Alliance for Open Media as a Final
      Deliverable.

2.12. Specification. "Specification" means the specification designated by
      the Alliance for Open Media as a Final Deliverable for which this
      License was issued.
~~~

### uriparser BSD 3-Clause license

Upstream path `php-8.5.11/ext/uri/uriparser/COPYING.BSD-3-Clause`; SHA-256 `287f09e6546a9610f949f89e8fb937cacfeabd7bfaa8c8a0c18312193bf04ad3`.

~~~text
uriparser - RFC 3986 URI parsing library

Copyright (C) 2007, Weijia Song <songweijia@gmail.com>
Copyright (C) 2007, Sebastian Pipping <sebastian@pipping.org>
All rights reserved.

Redistribution and use in source  and binary forms, with or without
modification, are permitted provided  that the following conditions
are met:

    1. Redistributions  of  source  code   must  retain  the  above
       copyright notice, this list  of conditions and the following
       disclaimer.

    2. Redistributions  in binary  form  must  reproduce the  above
       copyright notice, this list  of conditions and the following
       disclaimer  in  the  documentation  and/or  other  materials
       provided with the distribution.

    3. Neither the  name of the  copyright holder nor the  names of
       its contributors may be used  to endorse or promote products
       derived from  this software  without specific  prior written
       permission.

THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS
"AS IS" AND  ANY EXPRESS OR IMPLIED WARRANTIES,  INCLUDING, BUT NOT
LIMITED TO,  THE IMPLIED WARRANTIES OF  MERCHANTABILITY AND FITNESS
FOR  A  PARTICULAR  PURPOSE  ARE  DISCLAIMED.  IN  NO  EVENT  SHALL
THE  COPYRIGHT HOLDER  OR CONTRIBUTORS  BE LIABLE  FOR ANY  DIRECT,
INDIRECT, INCIDENTAL, SPECIAL,  EXEMPLARY, OR CONSEQUENTIAL DAMAGES
(INCLUDING, BUT NOT LIMITED TO,  PROCUREMENT OF SUBSTITUTE GOODS OR
SERVICES; LOSS OF USE, DATA,  OR PROFITS; OR BUSINESS INTERRUPTION)
HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN CONTRACT,
STRICT  LIABILITY,  OR  TORT (INCLUDING  NEGLIGENCE  OR  OTHERWISE)
ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED
OF THE POSSIBILITY OF SUCH DAMAGE.
~~~

### Embedded source/header notices

The following distinct legal comment blocks are reproduced from enabled or bundled PHP implementation files. Identical blocks are included once; the inventory lists every source path observed for each block.

### Embedded notice 1

Source paths: `php-8.5.11/Zend/zend_strtod.c`. SHA-256 of comment bytes `45a0b19676f406b6aaeb9ebc04961ba711d38b13003a15b38ff7174b2568b0c4`.

~~~text
/****************************************************************
 *
 * The author of this software is David M. Gay.
 *
 * Copyright (c) 1991, 2000, 2001 by Lucent Technologies.
 *
 * Permission to use, copy, modify, and distribute this software for any
 * purpose without fee is hereby granted, provided that this entire notice
 * is included in all copies of any software which is or includes a copy
 * or modification of this software and in all copies of the supporting
 * documentation for such software.
 *
 * THIS SOFTWARE IS BEING PROVIDED "AS IS", WITHOUT ANY EXPRESS OR IMPLIED
 * WARRANTY.  IN PARTICULAR, NEITHER THE AUTHOR NOR LUCENT MAKES ANY
 * REPRESENTATION OR WARRANTY OF ANY KIND CONCERNING THE MERCHANTABILITY
 * OF THIS SOFTWARE OR ITS FITNESS FOR ANY PARTICULAR PURPOSE.
 *
 ***************************************************************/
~~~

### Embedded notice 2

Source paths: `php-8.5.11/ext/hash/hash_sha3.c`. SHA-256 of comment bytes `688ecb692687de22ee84f919e35c0014117ac76fc4d74399fba5de36109aa9a1`.

~~~text
/*
   +----------------------------------------------------------------------+
   | Copyright (c) The PHP Group                                          |
   +----------------------------------------------------------------------+
   | This source file is subject to version 3.01 of the PHP license,      |
   | that is bundled with this package in the file LICENSE, and is        |
   | available through the world-wide-web at the following url:           |
   | https://www.php.net/license/3_01.txt                                 |
   | If you did not receive a copy of the PHP license and are unable to   |
   | obtain it through the world-wide-web, please send a note to          |
   | license@php.net so we can mail you a copy immediately.               |
   +----------------------------------------------------------------------+
   | Author: Sara Golemon <pollita@php.net>                               |
   +----------------------------------------------------------------------+
*/
~~~

### Embedded notice 3

Source paths: `php-8.5.11/ext/hash/hash_sha_ni.c`. SHA-256 of comment bytes `0a9b3af0bee4d8bc51a73824956f0cc4985114f2dc124f749e0a518232765c66`.

~~~text
/*-
 * Copyright 2018 Tarsnap Backup Inc.
 * All rights reserved.
 *
 * Redistribution and use in source and binary forms, with or without
 * modification, are permitted provided that the following conditions
 * are met:
 * 1. Redistributions of source code must retain the above copyright
 *    notice, this list of conditions and the following disclaimer.
 * 2. Redistributions in binary form must reproduce the above copyright
 *    notice, this list of conditions and the following disclaimer in the
 *    documentation and/or other materials provided with the distribution.
 *
 * THIS SOFTWARE IS PROVIDED BY THE AUTHOR AND CONTRIBUTORS ``AS IS'' AND
 * ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE
 * IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE
 * ARE DISCLAIMED.  IN NO EVENT SHALL THE AUTHOR OR CONTRIBUTORS BE LIABLE
 * FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL
 * DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS
 * OR SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION)
 * HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT
 * LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY
 * OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF
 * SUCH DAMAGE.
 */
~~~

### Embedded notice 4

Source paths: `php-8.5.11/ext/hash/hash_sha_sse2.c`. SHA-256 of comment bytes `61b704adde0c640f99e14885083b1c3f9b8755ca452ca2bec41a7602f4933a85`.

~~~text
/*-
 * Copyright 2021 Tarsnap Backup Inc.
 * All rights reserved.
 *
 * Redistribution and use in source and binary forms, with or without
 * modification, are permitted provided that the following conditions
 * are met:
 * 1. Redistributions of source code must retain the above copyright
 *    notice, this list of conditions and the following disclaimer.
 * 2. Redistributions in binary form must reproduce the above copyright
 *    notice, this list of conditions and the following disclaimer in the
 *    documentation and/or other materials provided with the distribution.
 *
 * THIS SOFTWARE IS PROVIDED BY THE AUTHOR AND CONTRIBUTORS ``AS IS'' AND
 * ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE
 * IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE
 * ARE DISCLAIMED.  IN NO EVENT SHALL THE AUTHOR OR CONTRIBUTORS BE LIABLE
 * FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL
 * DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS
 * OR SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION)
 * HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT
 * LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY
 * OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF
 * SUCH DAMAGE.
 */
~~~

### Embedded notice 5

Source paths: `php-8.5.11/ext/hash/murmur/PMurHash.c`. SHA-256 of comment bytes `806a80f441c41dea76eaaf3789d03778fef24efc41325123fad6c7c5261c7114`.

~~~text
/*-----------------------------------------------------------------------------
 * MurmurHash3 was written by Austin Appleby, and is placed in the public
 * domain.
 *
 * This implementation was written by Shane Day, and is also public domain.
 *
 * This is a portable ANSI C implementation of MurmurHash3_x86_32 (Murmur3A)
 * with support for progressive processing.
 */
~~~

### Embedded notice 6

Source paths: `php-8.5.11/ext/hash/murmur/PMurHash.h`. SHA-256 of comment bytes `988f0e67216d2ee83bc23256dee47a19e6a837ef4ca43b719b5e09e98c837e8c`.

~~~text
/*-----------------------------------------------------------------------------
 * MurmurHash3 was written by Austin Appleby, and is placed in the public
 * domain.
 *
 * This implementation was written by Shane Day, and is also public domain.
 *
 * This implementation was modified to match PMurHash128.cpp.
 */
~~~

### Embedded notice 7

Source paths: `php-8.5.11/ext/hash/sha3/generic64lc/KeccakHash.c`, `php-8.5.11/ext/hash/sha3/generic64lc/KeccakHash.h`, `php-8.5.11/ext/hash/sha3/generic64lc/KeccakP-1600-SnP.h`, `php-8.5.11/ext/hash/sha3/generic64lc/KeccakP-1600-opt64.c`, `php-8.5.11/ext/hash/sha3/generic64lc/KeccakSponge.c`, `php-8.5.11/ext/hash/sha3/generic64lc/KeccakSponge.h`, `php-8.5.11/ext/hash/sha3/generic64lc/KeccakSponge.inc`, `php-8.5.11/ext/hash/sha3/generic64lc/SnP-Relaned.h`, `php-8.5.11/ext/hash/sha3/generic64lc/align.h`. SHA-256 of comment bytes `76895f5604a15dd91466c6a8beac504d6c35b56088e4fe1be399f41572a8c86e`.

~~~text
/*
Implementation by the Keccak, Keyak and Ketje Teams, namely, Guido Bertoni,
Joan Daemen, Michaël Peeters, Gilles Van Assche and Ronny Van Keer, hereby
denoted as "the implementer".

For more information, feedback or questions, please refer to our websites:
http://keccak.noekeon.org/
http://keyak.noekeon.org/
http://ketje.noekeon.org/

To the extent possible under law, the implementer has waived all copyright
and related or neighboring rights to the source code in this file.
http://creativecommons.org/publicdomain/zero/1.0/
*/
~~~

### Embedded notice 8

Source paths: `php-8.5.11/ext/hash/sha3/generic64lc/brg_endian.h`. SHA-256 of comment bytes `63297bc6a7dab3aacdc5a758d26c5f69cb34fe243500aa69d58b1eb1a174e861`.

~~~text
/*
 ---------------------------------------------------------------------------
 Copyright (c) 1998-2008, Brian Gladman, Worcester, UK. All rights reserved.

 LICENSE TERMS

 The redistribution and use of this software (with or without changes)
 is allowed without the payment of fees or royalties provided that:

  1. source code distributions include the above copyright notice, this
     list of conditions and the following disclaimer;

  2. binary distributions include the above copyright notice, this list
     of conditions and the following disclaimer in their documentation;

  3. the name of the copyright holder is not used to endorse products
     built using this software without specific written permission.

 DISCLAIMER

 This software is provided 'as is' with no explicit or implied warranties
 in respect of its properties, including, but not limited to, correctness
 and/or fitness for purpose.
 ---------------------------------------------------------------------------
 Issue Date: 20/12/2007
 Changes for ARM 9/9/2010
*/
~~~

### Embedded notice 9

Source paths: `php-8.5.11/ext/hash/xxhash/xxhash.h`. SHA-256 of comment bytes `680ae7d3902bd5235334f2bb1f7102671a5b957bcc70f6e986e8c848eac542a2`.

~~~text
/*
 * xxHash - Extremely Fast Hash algorithm
 * Header File
 * Copyright (C) 2012-2020 Yann Collet
 *
 * BSD 2-Clause License (https://www.opensource.org/licenses/bsd-license.php)
 *
 * Redistribution and use in source and binary forms, with or without
 * modification, are permitted provided that the following conditions are
 * met:
 *
 *    * Redistributions of source code must retain the above copyright
 *      notice, this list of conditions and the following disclaimer.
 *    * Redistributions in binary form must reproduce the above
 *      copyright notice, this list of conditions and the following disclaimer
 *      in the documentation and/or other materials provided with the
 *      distribution.
 *
 * THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS
 * "AS IS" AND ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT
 * LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR
 * A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT
 * OWNER OR CONTRIBUTORS BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL,
 * SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT
 * LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES; LOSS OF USE,
 * DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND ON ANY
 * THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT
 * (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE
 * OF THIS SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.
 *
 * You can contact the author at:
 *   - xxHash homepage: https://www.xxhash.com
 *   - xxHash source repository: https://github.com/Cyan4973/xxHash
 */
~~~

### Embedded notice 10

Source paths: `php-8.5.11/ext/mbstring/libmbfl/filters/cp932_table.h`, `php-8.5.11/ext/mbstring/libmbfl/filters/mbfilter_7bit.c`, `php-8.5.11/ext/mbstring/libmbfl/filters/mbfilter_7bit.h`, `php-8.5.11/ext/mbstring/libmbfl/filters/mbfilter_base64.c`, `php-8.5.11/ext/mbstring/libmbfl/filters/mbfilter_base64.h`, `php-8.5.11/ext/mbstring/libmbfl/filters/mbfilter_cp51932.h`, `php-8.5.11/ext/mbstring/libmbfl/filters/mbfilter_iso2022jp_2004.h`, `php-8.5.11/ext/mbstring/libmbfl/filters/mbfilter_qprint.c`, `php-8.5.11/ext/mbstring/libmbfl/filters/mbfilter_qprint.h`, `php-8.5.11/ext/mbstring/libmbfl/filters/mbfilter_ucs2.c`, `php-8.5.11/ext/mbstring/libmbfl/filters/mbfilter_ucs2.h`, `php-8.5.11/ext/mbstring/libmbfl/filters/mbfilter_ucs4.c`, `php-8.5.11/ext/mbstring/libmbfl/filters/mbfilter_ucs4.h`, `php-8.5.11/ext/mbstring/libmbfl/filters/mbfilter_utf16.c`, `php-8.5.11/ext/mbstring/libmbfl/filters/mbfilter_utf16.h`, `php-8.5.11/ext/mbstring/libmbfl/filters/mbfilter_utf32.c`, `php-8.5.11/ext/mbstring/libmbfl/filters/mbfilter_utf32.h`, `php-8.5.11/ext/mbstring/libmbfl/filters/mbfilter_utf7.c`, `php-8.5.11/ext/mbstring/libmbfl/filters/mbfilter_utf7.h`, `php-8.5.11/ext/mbstring/libmbfl/filters/mbfilter_utf7imap.c`, `php-8.5.11/ext/mbstring/libmbfl/filters/mbfilter_utf7imap.h`, `php-8.5.11/ext/mbstring/libmbfl/filters/mbfilter_utf8.c`, `php-8.5.11/ext/mbstring/libmbfl/filters/mbfilter_utf8.h`, `php-8.5.11/ext/mbstring/libmbfl/filters/mbfilter_uuencode.c`, `php-8.5.11/ext/mbstring/libmbfl/filters/mbfilter_uuencode.h`, `php-8.5.11/ext/mbstring/libmbfl/mbfl/mbfilter_8bit.c`, `php-8.5.11/ext/mbstring/libmbfl/mbfl/mbfilter_8bit.h`, `php-8.5.11/ext/mbstring/libmbfl/mbfl/mbfilter_pass.c`, `php-8.5.11/ext/mbstring/libmbfl/mbfl/mbfilter_pass.h`, `php-8.5.11/ext/mbstring/libmbfl/mbfl/mbfilter_wchar.c`, `php-8.5.11/ext/mbstring/libmbfl/mbfl/mbfilter_wchar.h`, `php-8.5.11/ext/mbstring/libmbfl/mbfl/mbfl_consts.h`, `php-8.5.11/ext/mbstring/libmbfl/mbfl/mbfl_convert.c`, `php-8.5.11/ext/mbstring/libmbfl/mbfl/mbfl_convert.h`, `php-8.5.11/ext/mbstring/libmbfl/mbfl/mbfl_defs.h`, `php-8.5.11/ext/mbstring/libmbfl/mbfl/mbfl_encoding.c`, `php-8.5.11/ext/mbstring/libmbfl/mbfl/mbfl_encoding.h`, `php-8.5.11/ext/mbstring/libmbfl/mbfl/mbfl_filter_output.c`, `php-8.5.11/ext/mbstring/libmbfl/mbfl/mbfl_filter_output.h`, `php-8.5.11/ext/mbstring/libmbfl/mbfl/mbfl_language.c`, `php-8.5.11/ext/mbstring/libmbfl/mbfl/mbfl_language.h`, `php-8.5.11/ext/mbstring/libmbfl/mbfl/mbfl_memory_device.c`, `php-8.5.11/ext/mbstring/libmbfl/mbfl/mbfl_memory_device.h`, `php-8.5.11/ext/mbstring/libmbfl/mbfl/mbfl_string.c`, `php-8.5.11/ext/mbstring/libmbfl/mbfl/mbfl_string.h`. SHA-256 of comment bytes `00268def36f8b0eee8215b2c0dd868cde83d046e1183f98b7a23f3b0d3ea0a73`.

~~~text
/*
 * "streamable kanji code filter and converter"
 * Copyright (c) 1998-2002 HappySize, Inc. All rights reserved.
 *
 * LICENSE NOTICES
 *
 * This file is part of "streamable kanji code filter and converter",
 * which is distributed under the terms of GNU Lesser General Public
 * License (version 2) as published by the Free Software Foundation.
 *
 * This software is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Lesser General Public License for more details.
 *
 * You should have received a copy of the GNU Lesser General Public
 * License along with "streamable kanji code filter and converter";
 * if not, write to the Free Software Foundation, Inc., 59 Temple Place,
 * Suite 330, Boston, MA  02111-1307  USA
 *
 * The author of this file:
 *
 */
~~~

### Embedded notice 11

Source paths: `php-8.5.11/ext/mbstring/libmbfl/filters/html_entities.c`, `php-8.5.11/ext/mbstring/libmbfl/filters/html_entities.h`, `php-8.5.11/ext/mbstring/libmbfl/filters/mbfilter_htmlent.c`, `php-8.5.11/ext/mbstring/libmbfl/filters/mbfilter_htmlent.h`. SHA-256 of comment bytes `c8854443f9faa0c3cd8f6297bc2c205ad659a851707d461b0a95ebde3a13c6e9`.

~~~text
/*
 * "streamable kanji code filter and converter"
 * Copyright (c) 1998-2002 HappySize, Inc. All rights reserved.
 *
 * LICENSE NOTICES
 *
 * This file is part of "streamable kanji code filter and converter",
 * which is distributed under the terms of GNU Lesser General Public
 * License (version 2) as published by the Free Software Foundation.
 *
 * This software is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Lesser General Public License for more details.
 *
 * You should have received a copy of the GNU Lesser General Public
 * License along with "streamable kanji code filter and converter";
 * if not, write to the Free Software Foundation, Inc., 59 Temple Place,
 * Suite 330, Boston, MA  02111-1307  USA
 *
 * The author of this part: Marcus Boerger <helly@php.net>
 *
 */
~~~

### Embedded notice 12

Source paths: `php-8.5.11/ext/mbstring/libmbfl/filters/mbfilter_singlebyte.c`, `php-8.5.11/ext/mbstring/libmbfl/filters/mbfilter_singlebyte.h`. SHA-256 of comment bytes `f6ac0d423a2f2d3a7f9812f2108545f2d84465ebea87ff954834a73000536352`.

~~~text
/*
   +----------------------------------------------------------------------+
   | Copyright (c) The PHP Group                                          |
   +----------------------------------------------------------------------+
   | This source file is subject to version 3.01 of the PHP license,      |
   | that is bundled with this package in the file LICENSE, and is        |
   | available through the world-wide-web at the following url:           |
   | https://www.php.net/license/3_01.txt                                 |
   | If you did not receive a copy of the PHP license and are unable to   |
   | obtain it through the world-wide-web, please send a note to          |
   | license@php.net so we can mail you a copy immediately.               |
   +----------------------------------------------------------------------+
*/
~~~

### Embedded notice 13

Source paths: `php-8.5.11/ext/mbstring/libmbfl/filters/translit_kana_jisx0201_jisx0208.h`. SHA-256 of comment bytes `24afaa88df9ed778d8f9fa0d4fb1524401a8b732fccc12e4397f6b4f43cce5db`.

~~~text
/*
 * "streamable kanji code filter and converter"
 * Copyright (c) 1998-2002 HappySize, Inc. All rights reserved.
 *
 * LICENSE NOTICES
 *
 * This file is part of "streamable kanji code filter and converter",
 * which is distributed under the terms of GNU Lesser General Public
 * License (version 2) as published by the Free Software Foundation.
 *
 * This software is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Lesser General Public License for more details.
 *
 * You should have received a copy of the GNU Lesser General Public
 * License along with "streamable kanji code filter and converter";
 * if not, write to the Free Software Foundation, Inc., 59 Temple Place,
 * Suite 330, Boston, MA  02111-1307  USA
 *
 * The author of this file: Moriyoshi Koizumi <koizumi@gree.co.jp>
 *
 */
~~~

### Embedded notice 14

Source paths: `php-8.5.11/ext/mbstring/libmbfl/filters/unicode_prop.h`. SHA-256 of comment bytes `8d1d9d6eb7e67e9ff89504ff80f17d96fab4f67ac1167893c0c32087bbe8279c`.

~~~text
/*
 * "streamable kanji code filter and converter"
 * Copyright (c) 1998-2002 HappySize, Inc. All rights reserved.
 *
 * LICENSE NOTICES
 *
 * This file is part of "streamable kanji code filter and converter",
 * which is distributed under the terms of GNU Lesser General Public
 * License (version 2) as published by the Free Software Foundation.
 *
 * This software is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Lesser General Public License for more details.
 *
 * You should have received a copy of the GNU Lesser General Public
 * License along with "streamable kanji code filter and converter";
 * if not, write to the Free Software Foundation, Inc., 59 Temple Place,
 * Suite 330, Boston, MA  02111-1307  USA
 *
 * The authors of this file: PHP3 Internationalization team
 */
~~~

### Embedded notice 15

Source paths: `php-8.5.11/ext/mbstring/libmbfl/filters/unicode_table_big5.h`, `php-8.5.11/ext/mbstring/libmbfl/filters/unicode_table_cns11643.h`, `php-8.5.11/ext/mbstring/libmbfl/filters/unicode_table_cp932_ext.h`, `php-8.5.11/ext/mbstring/libmbfl/filters/unicode_table_gb18030.h`, `php-8.5.11/ext/mbstring/libmbfl/filters/unicode_table_uhc.h`. SHA-256 of comment bytes `94afaaec30dd4556ce9cbea981023135389ae0614d1f6dbdba1b6a9dca5c173a`.

~~~text
/*
 * "streamable kanji code filter and converter"
 * Copyright (c) 1998-2002 HappySize, Inc. All rights reserved.
 *
 * LICENSE NOTICES
 *
 * This file is part of "streamable kanji code filter and converter",
 * which is distributed under the terms of GNU Lesser General Public
 * License (version 2) as published by the Free Software Foundation.
 *
 * This software is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Lesser General Public License for more details.
 *
 * You should have received a copy of the GNU Lesser General Public
 * License along with "streamable kanji code filter and converter";
 * if not, write to the Free Software Foundation, Inc., 59 Temple Place,
 * Suite 330, Boston, MA  02111-1307  USA
 *
 * The author of this file: Rui Hirokawa <hirokawa@php.net>
 *
 */
~~~

### Embedded notice 16

Source paths: `php-8.5.11/ext/mbstring/libmbfl/filters/unicode_table_cp1252.h`. SHA-256 of comment bytes `074a7b00bca70a39926b2a0822e2d75889da616e9baf40795a9a493f67545794`.

~~~text
/*
 * "streamable kanji code filter and converter"
 * Copyright (c) 1998-2002 HappySize, Inc. All rights reserved.
 *
 * LICENSE NOTICES
 *
 * This file is part of "streamable kanji code filter and converter",
 * which is distributed under the terms of GNU Lesser General Public
 * License (version 2) as published by the Free Software Foundation.
 *
 * This software is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Lesser General Public License for more details.
 *
 * You should have received a copy of the GNU Lesser General Public
 * License along with "streamable kanji code filter and converter";
 * if not, write to the Free Software Foundation, Inc., 59 Temple Place,
 * Suite 330, Boston, MA  02111-1307  USA
 *
 * The authors of this file: PHP3 internationalization team
 * You can contact the primary author 金本　茂 <sgk@happysize.co.jp>.
 *
 */
~~~

### Embedded notice 17

Source paths: `php-8.5.11/ext/mbstring/libmbfl/filters/unicode_table_cp936.h`. SHA-256 of comment bytes `6975a0a8f85be2c5fad3497cdd7b17d98b3b2f4fd698a00a297f2c26c811c612`.

~~~text
/*
 * "streamable kanji code filter and converter"
 * Copyright (c) 1998-2002 HappySize, Inc. All rights reserved.
 *
 * LICENSE NOTICES
 *
 * This file is part of "streamable kanji code filter and converter",
 * which is distributed under the terms of GNU Lesser General Public
 * License (version 2) as published by the Free Software Foundation.
 *
 * This software is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Lesser General Public License for more details.
 *
 * You should have received a copy of the GNU Lesser General Public
 * License along with "streamable kanji code filter and converter";
 * if not, write to the Free Software Foundation, Inc., 59 Temple Place,
 * Suite 330, Boston, MA  02111-1307  USA
 *
 * The author of this file: Rui Hirokawa <hirokawa@php.net>
 */
~~~

### Embedded notice 18

Source paths: `php-8.5.11/ext/mbstring/libmbfl/filters/unicode_table_jis.h`. SHA-256 of comment bytes `b4b93840e2ba54b92be33d112d0e1957118f98aab6b62674295c5d043c199dee`.

~~~text
/*
 * "streamable kanji code filter and converter"
 * Copyright (c) 1998-2002 HappySize, Inc. All rights reserved.
 *
 * LICENSE NOTICES
 *
 * This file is part of "streamable kanji code filter and converter",
 * which is distributed under the terms of GNU Lesser General Public
 * License (version 2) as published by the Free Software Foundation.
 *
 * This software is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Lesser General Public License for more details.
 *
 * You should have received a copy of the GNU Lesser General Public
 * License along with "streamable kanji code filter and converter";
 * if not, write to the Free Software Foundation, Inc., 59 Temple Place,
 * Suite 330, Boston, MA  02111-1307  USA
 *
 * The authors of this file: PHP3 internationalization team
 * You can contact the primary authors; 金本　茂 <sgk@happysize.co.jp>,
 * Tsukada Takuya <tsukada@fminn.nagano.nagano.jp>.
 */
~~~

### Embedded notice 19

Source paths: `php-8.5.11/ext/mbstring/libmbfl/filters/unicode_table_jis2004.h`. SHA-256 of comment bytes `e050f374352fd4bc53846e070251127ed2c4c187aa7822611f9a06c1879b6c13`.

~~~text
/*
 * "streamable kanji code filter and converter"
 * Copyright (c) 1998-2002 HappySize, Inc. All rights reserved.
 *
 * LICENSE NOTICES
 *
 * This file is part of "streamable kanji code filter and converter",
 * which is distributed under the terms of GNU Lesser General Public
 * License (version 2) as published by the Free Software Foundation.
 *
 * This software is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Lesser General Public License for more details.
 *
 * You should have received a copy of the GNU Lesser General Public
 * License along with "streamable kanji code filter and converter";
 * if not, write to the Free Software Foundation, Inc., 59 Temple Place,
 * Suite 330, Boston, MA  02111-1307  USA
 *
 *  The author of this file: Rui Hirokawa <hirokawa@php.net>
 */
~~~

### Embedded notice 20

Source paths: `php-8.5.11/ext/mbstring/libmbfl/mbfl/mbfilter.c`, `php-8.5.11/ext/mbstring/libmbfl/mbfl/mbfilter.h`. SHA-256 of comment bytes `3cf17cbbc29536b088122dc65e1e294dbd8a72905ce740e72c345005b2a71ef8`.

~~~text
/*
 * "streamable kanji code filter and converter"
 *
 * Copyright (c) 1998,1999,2000,2001 HappySize, Inc. All rights reserved.
 *
 * This software is released under the GNU Lesser General Public License.
 * (Version 2.1, February 1999)
 * Please read the following detail of the licence (in japanese).
 *
 * ◆使用許諾条件◆
 *
 * このソフトウェアは株式会社ハッピーサイズによって開発されました。株式会社ハッ
 * ピーサイズは、著作権法および万国著作権条約の定めにより、このソフトウェアに関
 * するすべての権利を留保する権利を持ち、ここに行使します。株式会社ハッピーサイ
 * ズは以下に明記した条件に従って、このソフトウェアを使用する排他的ではない権利
 * をお客様に許諾します。何人たりとも、以下の条件に反してこのソフトウェアを使用
 * することはできません。
 *
 * このソフトウェアを「GNU Lesser General Public License (Version 2.1, February
 * 1999)」に示された条件で使用することを、全ての方に許諾します。「GNU Lesser
 * General Public License」を満たさない使用には、株式会社ハッピーサイズから書面
 * による許諾を得る必要があります。
 *
 * 「GNU Lesser General Public License」の全文は以下のウェブページから取得でき
 * ます。「GNU Lesser General Public License」とは、これまでLibrary General
 * Public Licenseと呼ばれていたものです。
 *     http://www.gnu.org/ --- GNUウェブサイト
 *     http://www.gnu.org/copyleft/lesser.html --- ライセンス文面
 * このライセンスの内容がわからない方、守れない方には使用を許諾しません。
 *
 * しかしながら、当社とGNUプロジェクトとの特定の関係を示唆または主張するもので
 * はありません。
 *
 * ◆保証内容◆
 *
 * このソフトウェアは、期待された動作・機能・性能を持つことを目標として設計され
 * 開発されていますが、これを保証するものではありません。このソフトウェアは「こ
 * のまま」の状態で提供されており、たとえばこのソフトウェアの有用性ないし特定の
 * 目的に合致することといった、何らかの保証内容が、明示されたり暗黙に示されてい
 * る場合であっても、その保証は無効です。このソフトウェアを使用した結果ないし使
 * 用しなかった結果によって、直接あるいは間接に受けた身体的な傷害、財産上の損害
 * 、データの損失あるいはその他の全ての損害については、その損害の可能性が使用者
 * 、当社あるいは第三者によって警告されていた場合であっても、当社はその損害の賠
 * 償および補填を行いません。この規定は他の全ての、書面上または書面に無い保証・
 * 契約・規定に優先します。
 *
 * ◆著作権者の連絡先および使用条件についての問い合わせ先◆
 *
 * 〒102-0073
 * 東京都千代田区九段北1-13-5日本地所第一ビル4F
 * 株式会社ハッピーサイズ
 * Phone: 03-3512-3655, Fax: 03-3512-3656
 * Email: sales@happysize.co.jp
 * Web: http://happysize.com/
 *
 * ◆著者◆
 *
 * 金本　茂 <sgk@happysize.co.jp>
 *
 * ◆履歴◆
 *
 * 1998/11/10 sgk implementation in C++
 * 1999/4/25  sgk Cで書きなおし。
 * 1999/4/26  sgk 入力フィルタを実装。漢字コードを推定しながらフィルタを追加。
 * 1999/6/??      Unicodeサポート。
 * 1999/6/22  sgk ライセンスをLGPLに変更。
 *
 */
~~~

### Embedded notice 21

Source paths: `php-8.5.11/ext/mbstring/libmbfl/mbfl/mbfilter.c`, `php-8.5.11/ext/mbstring/libmbfl/mbfl/mbfilter.h`. SHA-256 of comment bytes `f34c78aef16b2fd39ee2a7128a34825ebe2c135c830864b0fe248e7300e48795`.

~~~text
/*
 * Unicode support
 *
 * Portions copyright (c) 1999,2000,2001 by the PHP3 internationalization team.
 * All rights reserved.
 *
 */
~~~

### Embedded notice 22

Source paths: `php-8.5.11/ext/pcre/pcre2lib/pcre2.h`. SHA-256 of comment bytes `db65dc7d019845f32b88dcf952f8b59f48c5da36a540cd7a2ccc7672f2513a3e`.

~~~text
/* This is the public header file for the PCRE library, second API, to be
#included by applications that call PCRE2 functions.

           Copyright (c) 2016-2024 University of Cambridge

-----------------------------------------------------------------------------
Redistribution and use in source and binary forms, with or without
modification, are permitted provided that the following conditions are met:

    * Redistributions of source code must retain the above copyright notice,
      this list of conditions and the following disclaimer.

    * Redistributions in binary form must reproduce the above copyright
      notice, this list of conditions and the following disclaimer in the
      documentation and/or other materials provided with the distribution.

    * Neither the name of the University of Cambridge nor the names of its
      contributors may be used to endorse or promote products derived from
      this software without specific prior written permission.

THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS "AS IS"
AND ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE
IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE
ARE DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT OWNER OR CONTRIBUTORS BE
LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY, OR
CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF
SUBSTITUTE GOODS OR SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS
INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN
CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE)
ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE
POSSIBILITY OF SUCH DAMAGE.
-----------------------------------------------------------------------------
*/
~~~

### Embedded notice 23

Source paths: `php-8.5.11/ext/pcre/pcre2lib/pcre2_auto_possess.c`, `php-8.5.11/ext/pcre/pcre2lib/pcre2_convert.c`, `php-8.5.11/ext/pcre/pcre2lib/pcre2_match_data.c`, `php-8.5.11/ext/pcre/pcre2lib/pcre2_substitute.c`. SHA-256 of comment bytes `84fa7975de402fc1460b645dbd145b8a79fb398ec5a1b385d9bf351309a14238`.

~~~text
/* PCRE is a library of functions to support regular expressions whose syntax
and semantics are as close as possible to those of the Perl 5 language.

                       Written by Philip Hazel
     Original API code Copyright (c) 1997-2012 University of Cambridge
          New API code Copyright (c) 2016-2022 University of Cambridge

-----------------------------------------------------------------------------
Redistribution and use in source and binary forms, with or without
modification, are permitted provided that the following conditions are met:

    * Redistributions of source code must retain the above copyright notice,
      this list of conditions and the following disclaimer.

    * Redistributions in binary form must reproduce the above copyright
      notice, this list of conditions and the following disclaimer in the
      documentation and/or other materials provided with the distribution.

    * Neither the name of the University of Cambridge nor the names of its
      contributors may be used to endorse or promote products derived from
      this software without specific prior written permission.

THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS "AS IS"
AND ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE
IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE
ARE DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT OWNER OR CONTRIBUTORS BE
LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY, OR
CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF
SUBSTITUTE GOODS OR SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS
INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN
CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE)
ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE
POSSIBILITY OF SUCH DAMAGE.
-----------------------------------------------------------------------------
*/
~~~

### Embedded notice 24

Source paths: `php-8.5.11/ext/pcre/pcre2lib/pcre2_chkdint.c`. SHA-256 of comment bytes `e4fa0fc6c69fd249fae1d6fbbcefea8f46f2e0a1a4c65df90928a642afef70fd`.

~~~text
/* PCRE is a library of functions to support regular expressions whose syntax
and semantics are as close as possible to those of the Perl 5 language.

                     Written by Philip Hazel
            Copyright (c) 2023 University of Cambridge

-----------------------------------------------------------------------------
Redistribution and use in source and binary forms, with or without
modification, are permitted provided that the following conditions are met:

    * Redistributions of source code must retain the above copyright notice,
      this list of conditions and the following disclaimer.

    * Redistributions in binary form must reproduce the above copyright
      notice, this list of conditions and the following disclaimer in the
      documentation and/or other materials provided with the distribution.

    * Neither the name of the University of Cambridge nor the names of its
      contributors may be used to endorse or promote products derived from
      this software without specific prior written permission.

THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS "AS IS"
AND ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE
IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE
ARE DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT OWNER OR CONTRIBUTORS BE
LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY, OR
CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF
SUBSTITUTE GOODS OR SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS
INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN
CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE)
ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE
POSSIBILITY OF SUCH DAMAGE.
-----------------------------------------------------------------------------
*/
~~~

### Embedded notice 25

Source paths: `php-8.5.11/ext/pcre/pcre2lib/pcre2_compile.c`, `php-8.5.11/ext/pcre/pcre2lib/pcre2_context.c`, `php-8.5.11/ext/pcre/pcre2lib/pcre2_error.c`, `php-8.5.11/ext/pcre/pcre2lib/pcre2_extuni.c`, `php-8.5.11/ext/pcre/pcre2lib/pcre2_intmodedep.h`, `php-8.5.11/ext/pcre/pcre2lib/pcre2_tables.c`. SHA-256 of comment bytes `9564526977f8ea8bdb3ff6c4cf95f20a4c8def1a3c3115c2d20495b049375b8b`.

~~~text
/* PCRE is a library of functions to support regular expressions whose syntax
and semantics are as close as possible to those of the Perl 5 language.

                       Written by Philip Hazel
     Original API code Copyright (c) 1997-2012 University of Cambridge
          New API code Copyright (c) 2016-2024 University of Cambridge

-----------------------------------------------------------------------------
Redistribution and use in source and binary forms, with or without
modification, are permitted provided that the following conditions are met:

    * Redistributions of source code must retain the above copyright notice,
      this list of conditions and the following disclaimer.

    * Redistributions in binary form must reproduce the above copyright
      notice, this list of conditions and the following disclaimer in the
      documentation and/or other materials provided with the distribution.

    * Neither the name of the University of Cambridge nor the names of its
      contributors may be used to endorse or promote products derived from
      this software without specific prior written permission.

THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS "AS IS"
AND ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE
IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE
ARE DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT OWNER OR CONTRIBUTORS BE
LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY, OR
CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF
SUBSTITUTE GOODS OR SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS
INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN
CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE)
ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE
POSSIBILITY OF SUCH DAMAGE.
-----------------------------------------------------------------------------
*/
~~~

### Embedded notice 26

Source paths: `php-8.5.11/ext/pcre/pcre2lib/pcre2_config.c`, `php-8.5.11/ext/pcre/pcre2lib/pcre2_maketables.c`, `php-8.5.11/ext/pcre/pcre2lib/pcre2_serialize.c`, `php-8.5.11/ext/pcre/pcre2lib/pcre2_valid_utf.c`. SHA-256 of comment bytes `beca3f7e40f15c77da58156cfaf35f93b2dcfc9d820fb8408446595c053faf92`.

~~~text
/* PCRE is a library of functions to support regular expressions whose syntax
and semantics are as close as possible to those of the Perl 5 language.

                       Written by Philip Hazel
     Original API code Copyright (c) 1997-2012 University of Cambridge
          New API code Copyright (c) 2016-2020 University of Cambridge

-----------------------------------------------------------------------------
Redistribution and use in source and binary forms, with or without
modification, are permitted provided that the following conditions are met:

    * Redistributions of source code must retain the above copyright notice,
      this list of conditions and the following disclaimer.

    * Redistributions in binary form must reproduce the above copyright
      notice, this list of conditions and the following disclaimer in the
      documentation and/or other materials provided with the distribution.

    * Neither the name of the University of Cambridge nor the names of its
      contributors may be used to endorse or promote products derived from
      this software without specific prior written permission.

THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS "AS IS"
AND ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE
IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE
ARE DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT OWNER OR CONTRIBUTORS BE
LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY, OR
CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF
SUBSTITUTE GOODS OR SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS
INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN
CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE)
ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE
POSSIBILITY OF SUCH DAMAGE.
-----------------------------------------------------------------------------
*/
~~~

### Embedded notice 27

Source paths: `php-8.5.11/ext/pcre/pcre2lib/pcre2_dfa_match.c`, `php-8.5.11/ext/pcre/pcre2lib/pcre2_find_bracket.c`, `php-8.5.11/ext/pcre/pcre2lib/pcre2_jit_match.c`, `php-8.5.11/ext/pcre/pcre2lib/pcre2_printint.c`, `php-8.5.11/ext/pcre/pcre2lib/pcre2_study.c`, `php-8.5.11/ext/pcre/pcre2lib/pcre2_substring.c`, `php-8.5.11/ext/pcre/pcre2lib/pcre2_xclass.c`. SHA-256 of comment bytes `e7121284655d30976977060bb0e9c57fcdf5c2194947c56316808008be82b6f5`.

~~~text
/* PCRE is a library of functions to support regular expressions whose syntax
and semantics are as close as possible to those of the Perl 5 language.

                       Written by Philip Hazel
     Original API code Copyright (c) 1997-2012 University of Cambridge
          New API code Copyright (c) 2016-2023 University of Cambridge

-----------------------------------------------------------------------------
Redistribution and use in source and binary forms, with or without
modification, are permitted provided that the following conditions are met:

    * Redistributions of source code must retain the above copyright notice,
      this list of conditions and the following disclaimer.

    * Redistributions in binary form must reproduce the above copyright
      notice, this list of conditions and the following disclaimer in the
      documentation and/or other materials provided with the distribution.

    * Neither the name of the University of Cambridge nor the names of its
      contributors may be used to endorse or promote products derived from
      this software without specific prior written permission.

THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS "AS IS"
AND ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE
IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE
ARE DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT OWNER OR CONTRIBUTORS BE
LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY, OR
CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF
SUBSTITUTE GOODS OR SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS
INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN
CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE)
ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE
POSSIBILITY OF SUCH DAMAGE.
-----------------------------------------------------------------------------
*/
~~~

### Embedded notice 28

Source paths: `php-8.5.11/ext/pcre/pcre2lib/pcre2_internal.h`. SHA-256 of comment bytes `e6792454e410117765f52775f64cfee1a32c1dfe8af651d6776cf69eb5862f41`.

~~~text
/* PCRE2 is a library of functions to support regular expressions whose syntax
and semantics are as close as possible to those of the Perl 5 language.

                       Written by Philip Hazel
     Original API code Copyright (c) 1997-2012 University of Cambridge
          New API code Copyright (c) 2016-2023 University of Cambridge

-----------------------------------------------------------------------------
Redistribution and use in source and binary forms, with or without
modification, are permitted provided that the following conditions are met:

    * Redistributions of source code must retain the above copyright notice,
      this list of conditions and the following disclaimer.

    * Redistributions in binary form must reproduce the above copyright
      notice, this list of conditions and the following disclaimer in the
      documentation and/or other materials provided with the distribution.

    * Neither the name of the University of Cambridge nor the names of its
      contributors may be used to endorse or promote products derived from
      this software without specific prior written permission.

THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS "AS IS"
AND ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE
IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE
ARE DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT OWNER OR CONTRIBUTORS BE
LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY, OR
CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF
SUBSTITUTE GOODS OR SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS
INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN
CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE)
ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE
POSSIBILITY OF SUCH DAMAGE.
-----------------------------------------------------------------------------
*/
~~~

### Embedded notice 29

Source paths: `php-8.5.11/ext/pcre/pcre2lib/pcre2_jit_compile.c`. SHA-256 of comment bytes `66f7ad3161311cab00c413b172b470ee4983a591ea234c97c4ac2f8d3297ecec`.

~~~text
/* PCRE is a library of functions to support regular expressions whose syntax
and semantics are as close as possible to those of the Perl 5 language.

                       Written by Philip Hazel
                    This module by Zoltan Herczeg
     Original API code Copyright (c) 1997-2012 University of Cambridge
          New API code Copyright (c) 2016-2024 University of Cambridge

-----------------------------------------------------------------------------
Redistribution and use in source and binary forms, with or without
modification, are permitted provided that the following conditions are met:

    * Redistributions of source code must retain the above copyright notice,
      this list of conditions and the following disclaimer.

    * Redistributions in binary form must reproduce the above copyright
      notice, this list of conditions and the following disclaimer in the
      documentation and/or other materials provided with the distribution.

    * Neither the name of the University of Cambridge nor the names of its
      contributors may be used to endorse or promote products derived from
      this software without specific prior written permission.

THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS "AS IS"
AND ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE
IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE
ARE DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT OWNER OR CONTRIBUTORS BE
LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY, OR
CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF
SUBSTITUTE GOODS OR SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS
INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN
CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE)
ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE
POSSIBILITY OF SUCH DAMAGE.
-----------------------------------------------------------------------------
*/
~~~

### Embedded notice 30

Source paths: `php-8.5.11/ext/pcre/pcre2lib/pcre2_jit_misc.c`, `php-8.5.11/ext/pcre/pcre2lib/pcre2_newline.c`, `php-8.5.11/ext/pcre/pcre2lib/pcre2_ord2utf.c`. SHA-256 of comment bytes `61fcf6e0379f20e4af03671d3ace07585f6ac00c102ee3de8aa283e27a44ecc0`.

~~~text
/* PCRE is a library of functions to support regular expressions whose syntax
and semantics are as close as possible to those of the Perl 5 language.

                       Written by Philip Hazel
     Original API code Copyright (c) 1997-2012 University of Cambridge
         New API code Copyright (c) 2016 University of Cambridge

-----------------------------------------------------------------------------
Redistribution and use in source and binary forms, with or without
modification, are permitted provided that the following conditions are met:

    * Redistributions of source code must retain the above copyright notice,
      this list of conditions and the following disclaimer.

    * Redistributions in binary form must reproduce the above copyright
      notice, this list of conditions and the following disclaimer in the
      documentation and/or other materials provided with the distribution.

    * Neither the name of the University of Cambridge nor the names of its
      contributors may be used to endorse or promote products derived from
      this software without specific prior written permission.

THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS "AS IS"
AND ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE
IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE
ARE DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT OWNER OR CONTRIBUTORS BE
LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY, OR
CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF
SUBSTITUTE GOODS OR SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS
INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN
CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE)
ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE
POSSIBILITY OF SUCH DAMAGE.
-----------------------------------------------------------------------------
*/
~~~

### Embedded notice 31

Source paths: `php-8.5.11/ext/pcre/pcre2lib/pcre2_jit_neon_inc.h`. SHA-256 of comment bytes `2441e695d5bc2afd0f5b893aa8ae3c5ab4b436f65365962fddb3219f72f0b72f`.

~~~text
/* PCRE is a library of functions to support regular expressions whose syntax
and semantics are as close as possible to those of the Perl 5 language.

                       Written by Philip Hazel
            This module by Zoltan Herczeg and Sebastian Pop
     Original API code Copyright (c) 1997-2012 University of Cambridge
          New API code Copyright (c) 2016-2019 University of Cambridge

-----------------------------------------------------------------------------
Redistribution and use in source and binary forms, with or without
modification, are permitted provided that the following conditions are met:

    * Redistributions of source code must retain the above copyright notice,
      this list of conditions and the following disclaimer.

    * Redistributions in binary form must reproduce the above copyright
      notice, this list of conditions and the following disclaimer in the
      documentation and/or other materials provided with the distribution.

    * Neither the name of the University of Cambridge nor the names of its
      contributors may be used to endorse or promote products derived from
      this software without specific prior written permission.

THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS "AS IS"
AND ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE
IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE
ARE DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT OWNER OR CONTRIBUTORS BE
LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY, OR
CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF
SUBSTITUTE GOODS OR SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS
INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN
CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE)
ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE
POSSIBILITY OF SUCH DAMAGE.
-----------------------------------------------------------------------------
*/
~~~

### Embedded notice 32

Source paths: `php-8.5.11/ext/pcre/pcre2lib/pcre2_jit_simd_inc.h`. SHA-256 of comment bytes `865ce6e3edfde1c54a8029200788fff85fe9e0ea7ddd12f82aba73b60b6f8c69`.

~~~text
/* PCRE is a library of functions to support regular expressions whose syntax
and semantics are as close as possible to those of the Perl 5 language.

                       Written by Philip Hazel
                    This module by Zoltan Herczeg
     Original API code Copyright (c) 1997-2012 University of Cambridge
          New API code Copyright (c) 2016-2019 University of Cambridge

-----------------------------------------------------------------------------
Redistribution and use in source and binary forms, with or without
modification, are permitted provided that the following conditions are met:

    * Redistributions of source code must retain the above copyright notice,
      this list of conditions and the following disclaimer.

    * Redistributions in binary form must reproduce the above copyright
      notice, this list of conditions and the following disclaimer in the
      documentation and/or other materials provided with the distribution.

    * Neither the name of the University of Cambridge nor the names of its
      contributors may be used to endorse or promote products derived from
      this software without specific prior written permission.

THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS "AS IS"
AND ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE
IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE
ARE DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT OWNER OR CONTRIBUTORS BE
LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY, OR
CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF
SUBSTITUTE GOODS OR SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS
INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN
CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE)
ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE
POSSIBILITY OF SUCH DAMAGE.
-----------------------------------------------------------------------------
*/
~~~

### Embedded notice 33

Source paths: `php-8.5.11/ext/pcre/pcre2lib/pcre2_match.c`. SHA-256 of comment bytes `c716ed84ce1dcdd31406ef21f054f865d4de1207e2b92fc8f6de847f9e39cb86`.

~~~text
/* PCRE is a library of functions to support regular expressions whose syntax
and semantics are as close as possible to those of the Perl 5 language.

                       Written by Philip Hazel
     Original API code Copyright (c) 1997-2012 University of Cambridge
          New API code Copyright (c) 2015-2024 University of Cambridge

-----------------------------------------------------------------------------
Redistribution and use in source and binary forms, with or without
modification, are permitted provided that the following conditions are met:

    * Redistributions of source code must retain the above copyright notice,
      this list of conditions and the following disclaimer.

    * Redistributions in binary form must reproduce the above copyright
      notice, this list of conditions and the following disclaimer in the
      documentation and/or other materials provided with the distribution.

    * Neither the name of the University of Cambridge nor the names of its
      contributors may be used to endorse or promote products derived from
      this software without specific prior written permission.

THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS "AS IS"
AND ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE
IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE
ARE DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT OWNER OR CONTRIBUTORS BE
LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY, OR
CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF
SUBSTITUTE GOODS OR SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS
INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN
CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE)
ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE
POSSIBILITY OF SUCH DAMAGE.
-----------------------------------------------------------------------------
*/
~~~

### Embedded notice 34

Source paths: `php-8.5.11/ext/pcre/pcre2lib/pcre2_pattern_info.c`. SHA-256 of comment bytes `efbffba03857b39bf59f147071ed20b62bdcba36a27e8f3ef96be67aeffe4a97`.

~~~text
/* PCRE is a library of functions to support regular expressions whose syntax
and semantics are as close as possible to those of the Perl 5 language.

                       Written by Philip Hazel
     Original API code Copyright (c) 1997-2012 University of Cambridge
          New API code Copyright (c) 2016-2018 University of Cambridge

-----------------------------------------------------------------------------
Redistribution and use in source and binary forms, with or without
modification, are permitted provided that the following conditions are met:

    * Redistributions of source code must retain the above copyright notice,
      this list of conditions and the following disclaimer.

    * Redistributions in binary form must reproduce the above copyright
      notice, this list of conditions and the following disclaimer in the
      documentation and/or other materials provided with the distribution.

    * Neither the name of the University of Cambridge nor the names of its
      contributors may be used to endorse or promote products derived from
      this software without specific prior written permission.

THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS "AS IS"
AND ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE
IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE
ARE DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT OWNER OR CONTRIBUTORS BE
LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY, OR
CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF
SUBSTITUTE GOODS OR SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS
INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN
CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE)
ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE
POSSIBILITY OF SUCH DAMAGE.
-----------------------------------------------------------------------------
*/
~~~

### Embedded notice 35

Source paths: `php-8.5.11/ext/pcre/pcre2lib/pcre2_script_run.c`. SHA-256 of comment bytes `6fd45672ba016bd0a7647b711646debeec1723383fc831adda8acaef2760dad5`.

~~~text
/* PCRE is a library of functions to support regular expressions whose syntax
and semantics are as close as possible to those of the Perl 5 language.

                       Written by Philip Hazel
     Original API code Copyright (c) 1997-2012 University of Cambridge
          New API code Copyright (c) 2016-2021 University of Cambridge

-----------------------------------------------------------------------------
Redistribution and use in source and binary forms, with or without
modification, are permitted provided that the following conditions are met:

    * Redistributions of source code must retain the above copyright notice,
      this list of conditions and the following disclaimer.

    * Redistributions in binary form must reproduce the above copyright
      notice, this list of conditions and the following disclaimer in the
      documentation and/or other materials provided with the distribution.

    * Neither the name of the University of Cambridge nor the names of its
      contributors may be used to endorse or promote products derived from
      this software without specific prior written permission.

THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS "AS IS"
AND ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE
IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE
ARE DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT OWNER OR CONTRIBUTORS BE
LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY, OR
CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF
SUBSTITUTE GOODS OR SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS
INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN
CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE)
ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE
POSSIBILITY OF SUCH DAMAGE.
-----------------------------------------------------------------------------
*/
~~~

### Embedded notice 36

Source paths: `php-8.5.11/ext/pcre/pcre2lib/pcre2_string_utils.c`. SHA-256 of comment bytes `2fd2d99686d8b3afb36b7ff28c85e09751dc1c95048b13759b1b9afc90eefd83`.

~~~text
/* PCRE is a library of functions to support regular expressions whose syntax
and semantics are as close as possible to those of the Perl 5 language.

                       Written by Philip Hazel
     Original API code Copyright (c) 1997-2012 University of Cambridge
          New API code Copyright (c) 2018-2021 University of Cambridge

-----------------------------------------------------------------------------
Redistribution and use in source and binary forms, with or without
modification, are permitted provided that the following conditions are met:

    * Redistributions of source code must retain the above copyright notice,
      this list of conditions and the following disclaimer.

    * Redistributions in binary form must reproduce the above copyright
      notice, this list of conditions and the following disclaimer in the
      documentation and/or other materials provided with the distribution.

    * Neither the name of the University of Cambridge nor the names of its
      contributors may be used to endorse or promote products derived from
      this software without specific prior written permission.

THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS "AS IS"
AND ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE
IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE
ARE DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT OWNER OR CONTRIBUTORS BE
LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY, OR
CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF
SUBSTITUTE GOODS OR SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS
INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN
CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE)
ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE
POSSIBILITY OF SUCH DAMAGE.
-----------------------------------------------------------------------------
*/
~~~

### Embedded notice 37

Source paths: `php-8.5.11/ext/pcre/pcre2lib/pcre2_ucd.c`. SHA-256 of comment bytes `fc252e21ef6bc6f05d93334ead3b979f3dfb5eedb7e50078acad7d13e0f488fc`.

~~~text
/* PCRE is a library of functions to support regular expressions whose syntax
and semantics are as close as possible to those of the Perl 5 language.

                       Written by Philip Hazel
     Original API code Copyright (c) 1997-2012 University of Cambridge
          New API code Copyright (c) 2016-2022 University of Cambridge

This module is auto-generated from Unicode data files. DO NOT EDIT MANUALLY!
Instead, modify the maint/GenerateUcd.py script and run it to generate
a new version of this code.

-----------------------------------------------------------------------------
Redistribution and use in source and binary forms, with or without
modification, are permitted provided that the following conditions are met:

    * Redistributions of source code must retain the above copyright notice,
      this list of conditions and the following disclaimer.

    * Redistributions in binary form must reproduce the above copyright
      notice, this list of conditions and the following disclaimer in the
      documentation and/or other materials provided with the distribution.

    * Neither the name of the University of Cambridge nor the names of its
      contributors may be used to endorse or promote products derived from
      this software without specific prior written permission.

THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS "AS IS"
AND ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE
IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE
ARE DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT OWNER OR CONTRIBUTORS BE
LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY, OR
CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF
SUBSTITUTE GOODS OR SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS
INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN
CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE)
ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE
POSSIBILITY OF SUCH DAMAGE.
-----------------------------------------------------------------------------
*/
~~~

### Embedded notice 38

Source paths: `php-8.5.11/ext/pcre/pcre2lib/pcre2_ucp.h`. SHA-256 of comment bytes `3f98d80eecaebce48c3d58940616046cb1b24318d8b2920515d088c3c5172b43`.

~~~text
/* PCRE is a library of functions to support regular expressions whose syntax
and semantics are as close as possible to those of the Perl 5 language.

                       Written by Philip Hazel
     Original API code Copyright (c) 1997-2012 University of Cambridge
          New API code Copyright (c) 2016-2022 University of Cambridge

This module is auto-generated from Unicode data files. DO NOT EDIT MANUALLY!
Instead, modify the maint/GenerateUcpHeader.py script and run it to generate
a new version of this code.

-----------------------------------------------------------------------------
Redistribution and use in source and binary forms, with or without
modification, are permitted provided that the following conditions are met:

    * Redistributions of source code must retain the above copyright notice,
      this list of conditions and the following disclaimer.

    * Redistributions in binary form must reproduce the above copyright
      notice, this list of conditions and the following disclaimer in the
      documentation and/or other materials provided with the distribution.

    * Neither the name of the University of Cambridge nor the names of its
      contributors may be used to endorse or promote products derived from
      this software without specific prior written permission.

THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS "AS IS"
AND ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE
IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE
ARE DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT OWNER OR CONTRIBUTORS BE
LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY, OR
CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF
SUBSTITUTE GOODS OR SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS
INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN
CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE)
ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE
POSSIBILITY OF SUCH DAMAGE.
-----------------------------------------------------------------------------
*/
~~~

### Embedded notice 39

Source paths: `php-8.5.11/ext/pcre/pcre2lib/pcre2_ucptables.c`. SHA-256 of comment bytes `b88089095cb09d68a9eede02203869c7d145bc5811753e8560930695d68c4427`.

~~~text
/* PCRE is a library of functions to support regular expressions whose syntax
and semantics are as close as possible to those of the Perl 5 language.

                       Written by Philip Hazel
     Original API code Copyright (c) 1997-2012 University of Cambridge
          New API code Copyright (c) 2016-2022 University of Cambridge

This module is auto-generated from Unicode data files. DO NOT EDIT MANUALLY!
Instead, modify the maint/GenerateUcpTables.py script and run it to generate
a new version of this code.

-----------------------------------------------------------------------------
Redistribution and use in source and binary forms, with or without
modification, are permitted provided that the following conditions are met:

    * Redistributions of source code must retain the above copyright notice,
      this list of conditions and the following disclaimer.

    * Redistributions in binary form must reproduce the above copyright
      notice, this list of conditions and the following disclaimer in the
      documentation and/or other materials provided with the distribution.

    * Neither the name of the University of Cambridge nor the names of its
      contributors may be used to endorse or promote products derived from
      this software without specific prior written permission.

THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS "AS IS"
AND ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE
IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE
ARE DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT OWNER OR CONTRIBUTORS BE
LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY, OR
CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF
SUBSTITUTE GOODS OR SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS
INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN
CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE)
ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE
POSSIBILITY OF SUCH DAMAGE.
-----------------------------------------------------------------------------
*/
~~~

### Embedded notice 40

Source paths: `php-8.5.11/ext/pcre/pcre2lib/sljit/allocator_src/sljitExecAllocatorApple.c`, `php-8.5.11/ext/pcre/pcre2lib/sljit/allocator_src/sljitExecAllocatorCore.c`, `php-8.5.11/ext/pcre/pcre2lib/sljit/allocator_src/sljitExecAllocatorFreeBSD.c`, `php-8.5.11/ext/pcre/pcre2lib/sljit/allocator_src/sljitExecAllocatorPosix.c`, `php-8.5.11/ext/pcre/pcre2lib/sljit/allocator_src/sljitExecAllocatorWindows.c`, `php-8.5.11/ext/pcre/pcre2lib/sljit/allocator_src/sljitProtExecAllocatorNetBSD.c`, `php-8.5.11/ext/pcre/pcre2lib/sljit/allocator_src/sljitProtExecAllocatorPosix.c`, `php-8.5.11/ext/pcre/pcre2lib/sljit/allocator_src/sljitWXExecAllocatorPosix.c`, `php-8.5.11/ext/pcre/pcre2lib/sljit/allocator_src/sljitWXExecAllocatorWindows.c`, `php-8.5.11/ext/pcre/pcre2lib/sljit/sljitConfig.h`, `php-8.5.11/ext/pcre/pcre2lib/sljit/sljitConfigCPU.h`, `php-8.5.11/ext/pcre/pcre2lib/sljit/sljitConfigInternal.h`, `php-8.5.11/ext/pcre/pcre2lib/sljit/sljitLir.c`, `php-8.5.11/ext/pcre/pcre2lib/sljit/sljitLir.h`, `php-8.5.11/ext/pcre/pcre2lib/sljit/sljitNativeARM_32.c`, `php-8.5.11/ext/pcre/pcre2lib/sljit/sljitNativeARM_64.c`, `php-8.5.11/ext/pcre/pcre2lib/sljit/sljitNativeARM_T2_32.c`, `php-8.5.11/ext/pcre/pcre2lib/sljit/sljitNativeLOONGARCH_64.c`, `php-8.5.11/ext/pcre/pcre2lib/sljit/sljitNativeMIPS_32.c`, `php-8.5.11/ext/pcre/pcre2lib/sljit/sljitNativeMIPS_64.c`, `php-8.5.11/ext/pcre/pcre2lib/sljit/sljitNativeMIPS_common.c`, `php-8.5.11/ext/pcre/pcre2lib/sljit/sljitNativePPC_32.c`, `php-8.5.11/ext/pcre/pcre2lib/sljit/sljitNativePPC_64.c`, `php-8.5.11/ext/pcre/pcre2lib/sljit/sljitNativePPC_common.c`, `php-8.5.11/ext/pcre/pcre2lib/sljit/sljitNativeRISCV_32.c`, `php-8.5.11/ext/pcre/pcre2lib/sljit/sljitNativeRISCV_64.c`, `php-8.5.11/ext/pcre/pcre2lib/sljit/sljitNativeRISCV_common.c`, `php-8.5.11/ext/pcre/pcre2lib/sljit/sljitNativeS390X.c`, `php-8.5.11/ext/pcre/pcre2lib/sljit/sljitNativeX86_32.c`, `php-8.5.11/ext/pcre/pcre2lib/sljit/sljitNativeX86_64.c`, `php-8.5.11/ext/pcre/pcre2lib/sljit/sljitNativeX86_common.c`, `php-8.5.11/ext/pcre/pcre2lib/sljit/sljitSerialize.c`, `php-8.5.11/ext/pcre/pcre2lib/sljit/sljitUtils.c`. SHA-256 of comment bytes `79cf721079325247058a340de8dd7e4b219d2135856d67c6714c16db338ddf7a`.

~~~text
/*
 *    Stack-less Just-In-Time compiler
 *
 *    Copyright Zoltan Herczeg (hzmester@freemail.hu). All rights reserved.
 *
 * Redistribution and use in source and binary forms, with or without modification, are
 * permitted provided that the following conditions are met:
 *
 *   1. Redistributions of source code must retain the above copyright notice, this list of
 *      conditions and the following disclaimer.
 *
 *   2. Redistributions in binary form must reproduce the above copyright notice, this list
 *      of conditions and the following disclaimer in the documentation and/or other materials
 *      provided with the distribution.
 *
 * THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDER(S) AND CONTRIBUTORS ``AS IS'' AND ANY
 * EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE IMPLIED WARRANTIES
 * OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT
 * SHALL THE COPYRIGHT HOLDER(S) OR CONTRIBUTORS BE LIABLE FOR ANY DIRECT, INDIRECT,
 * INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT LIMITED
 * TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES; LOSS OF USE, DATA, OR PROFITS; OR
 * BUSINESS INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN
 * CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN
 * ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.
 */
~~~

### Embedded notice 41

Source paths: `php-8.5.11/ext/standard/crypt_blowfish.c`. SHA-256 of comment bytes `888be4b79e497ad6d84c9bfca61c3e5890d562ceeadeef0202d8fbe6f8bd61f2`.

~~~text
/*
 * The crypt_blowfish homepage is:
 *
 *	http://www.openwall.com/crypt/
 *
 * This code comes from John the Ripper password cracker, with reentrant
 * and crypt(3) interfaces added, but optimizations specific to password
 * cracking removed.
 *
 * Written by Solar Designer <solar at openwall.com> in 1998-2015.
 * No copyright is claimed, and the software is hereby placed in the public
 * domain. In case this attempt to disclaim copyright and place the software
 * in the public domain is deemed null and void, then the software is
 * Copyright (c) 1998-2015 Solar Designer and it is hereby released to the
 * general public under the following terms:
 *
 * Redistribution and use in source and binary forms, with or without
 * modification, are permitted.
 *
 * There's ABSOLUTELY NO WARRANTY, express or implied.
 *
 * It is my intent that you should be able to use this on your system,
 * as part of a software package, or anywhere else to improve security,
 * ensure compatibility, or for any other purpose. I would appreciate
 * it if you give credit where it is due and keep your modifications in
 * the public domain as well, but I don't require that in order to let
 * you place this code and any modifications you make under a license
 * of your choice.
 *
 * This implementation is fully compatible with OpenBSD's bcrypt.c for prefix
 * "$2b$", originally by Niels Provos <provos at citi.umich.edu>, and it uses
 * some of his ideas. The password hashing algorithm was designed by David
 * Mazieres <dm at lcs.mit.edu>. For information on the level of
 * compatibility for bcrypt hash prefixes other than "$2b$", please refer to
 * the comments in BF_set_key() below and to the included crypt(3) man page.
 *
 * There's a paper on the algorithm that explains its design decisions:
 *
 *	http://www.usenix.org/events/usenix99/provos.html
 *
 * Some of the tricks in BF_ROUND might be inspired by Eric Young's
 * Blowfish library (I can't be sure if I would think of something if I
 * hadn't seen his code).
 */
~~~

### Embedded notice 42

Source paths: `php-8.5.11/ext/standard/crypt_blowfish.h`. SHA-256 of comment bytes `5c57e94820d093b466858e73066b01be8442044694880a095cf4cfd92bb05e6b`.

~~~text
/*
 * Written by Solar Designer <solar at openwall.com> in 2000-2011.
 * No copyright is claimed, and the software is hereby placed in the public
 * domain. In case this attempt to disclaim copyright and place the software
 * in the public domain is deemed null and void, then the software is
 * Copyright (c) 2000-2011 Solar Designer and it is hereby released to the
 * general public under the following terms:
 *
 * Redistribution and use in source and binary forms, with or without
 * modification, are permitted.
 *
 * There's ABSOLUTELY NO WARRANTY, express or implied.
 *
 * See crypt_blowfish.c for more information.
 */
~~~

### Embedded notice 43

Source paths: `php-8.5.11/ext/standard/crypt_freesec.c`. SHA-256 of comment bytes `d34efa7ae33cf16ccadf640b521337b94e64b15e2a9533b1de846ced319bce5c`.

~~~text
/*
 * FreeSec: libcrypt for NetBSD
 *
 * Copyright (c) 1994 David Burren
 * All rights reserved.
 *
 * Redistribution and use in source and binary forms, with or without
 * modification, are permitted provided that the following conditions
 * are met:
 * 1. Redistributions of source code must retain the above copyright
 *    notice, this list of conditions and the following disclaimer.
 * 2. Redistributions in binary form must reproduce the above copyright
 *    notice, this list of conditions and the following disclaimer in the
 *    documentation and/or other materials provided with the distribution.
 * 3. Neither the name of the author nor the names of other contributors
 *    may be used to endorse or promote products derived from this software
 *    without specific prior written permission.
 *
 * THIS SOFTWARE IS PROVIDED BY THE AUTHOR AND CONTRIBUTORS ``AS IS'' AND
 * ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE
 * IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE
 * ARE DISCLAIMED.  IN NO EVENT SHALL THE AUTHOR OR CONTRIBUTORS BE LIABLE
 * FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL
 * DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS
 * OR SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION)
 * HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT
 * LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY
 * OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF
 * SUCH DAMAGE.
 *
 *	$Owl: Owl/packages/glibc/crypt_freesec.c,v 1.4 2005/11/16 13:08:32 solar Exp $
 *
 * This is an original implementation of the DES and the crypt(3) interfaces
 * by David Burren <davidb at werj.com.au>.
 *
 * An excellent reference on the underlying algorithm (and related
 * algorithms) is:
 *
 *	B. Schneier, Applied Cryptography: protocols, algorithms,
 *	and source code in C, John Wiley & Sons, 1994.
 *
 * Note that in that book's description of DES the lookups for the initial,
 * pbox, and final permutations are inverted (this has been brought to the
 * attention of the author).  A list of errata for this book has been
 * posted to the sci.crypt newsgroup by the author and is available for FTP.
 *
 * ARCHITECTURE ASSUMPTIONS:
 *	This code used to have some nasty ones, but these have been removed
 *	by now.  The code requires a 32-bit integer type, though.
 */
~~~

### Embedded notice 44

Source paths: `php-8.5.11/ext/standard/crypt_sha256.c`. SHA-256 of comment bytes `b49c5b34448604cb98b52c2ab06d2fbbb6264568c4ec7f79c0e309b277523a46`.

~~~text
/* SHA256-based Unix crypt implementation.
   Released into the Public Domain by Ulrich Drepper <drepper@redhat.com>.  */
~~~

### Embedded notice 45

Source paths: `php-8.5.11/ext/standard/crypt_sha512.c`. SHA-256 of comment bytes `96bca70d154b4ced8c94d110537f7693ca821f3e6bfb28457c22af797035b559`.

~~~text
/* SHA512-based Unix crypt implementation.
   Released into the Public Domain by Ulrich Drepper <drepper@redhat.com>.  */
~~~

### Embedded notice 46

Source paths: `php-8.5.11/ext/standard/libavifinfo/avifinfo.c`, `php-8.5.11/ext/standard/libavifinfo/avifinfo.h`. SHA-256 of comment bytes `53a7584df661a3555cf0e5eb86b2349282d80e1f08b801e663d5570ddfa28b34`.

~~~text
// Copyright (c) 2021, Alliance for Open Media. All rights reserved
//
// This source code is subject to the terms of the BSD 2 Clause License and
// the Alliance for Open Media Patent License 1.0. If the BSD 2 Clause License
// was not distributed with this source code in the LICENSE file, you can
// obtain it at www.aomedia.org/license/software. If the Alliance for Open
// Media Patent License 1.0 was not distributed with this source code in the
// PATENTS file, you can obtain it at www.aomedia.org/license/patent.
~~~

### Embedded notice 47

Source paths: `php-8.5.11/ext/standard/strnatcmp.c`. SHA-256 of comment bytes `fa87d5f5aaee20966db871cbf68aa3cae390e560d37cff66d7a40d9bad94ac3f`.

~~~text
/*

  Modified for PHP by Andrei Zmievski <andrei@ispi.net>

  strnatcmp.c -- Perform 'natural order' comparisons of strings in C.
  Copyright (C) 2000 by Martin Pool <mbp@humbug.org.au>

  This software is provided 'as-is', without any express or implied
  warranty.  In no event will the authors be held liable for any damages
  arising from the use of this software.

  Permission is granted to anyone to use this software for any purpose,
  including commercial applications, and to alter it and redistribute it
  freely, subject to the following restrictions:

  1. The origin of this software must not be misrepresented; you must not
     claim that you wrote the original software. If you use this software
     in a product, an acknowledgment in the product documentation would be
     appreciated but is not required.
  2. Altered source versions must be plainly marked as such, and must not be
     misrepresented as being the original software.
  3. This notice may not be removed or altered from any source distribution.
*/
~~~

### Embedded notice 48

Source paths: `php-8.5.11/main/charclass.h`. SHA-256 of comment bytes `e3e75961042f5ae1788de480e363b4ad6d2afda3cfa0e709fc281e6997ca2a08`.

~~~text
/*
 * Public domain, 2008, Todd C. Miller <millert@openbsd.org>
 *
 * $OpenBSD: charclass.h,v 1.3 2020/10/13 04:42:28 guenther Exp $
 */
~~~

### Embedded notice 49

Source paths: `php-8.5.11/main/php_glob.c`. SHA-256 of comment bytes `23ff386cce478ddcf444cbfbf00d96caaaf0e21e06ed444c87a8a00ba051ad52`.

~~~text
/*
 * Copyright (c) 1989, 1993
 *	The Regents of the University of California.  All rights reserved.
 *
 * This code is derived from software contributed to Berkeley by
 * Guido van Rossum.
 *
 * Redistribution and use in source and binary forms, with or without
 * modification, are permitted provided that the following conditions
 * are met:
 * 1. Redistributions of source code must retain the above copyright
 *    notice, this list of conditions and the following disclaimer.
 * 2. Redistributions in binary form must reproduce the above copyright
 *    notice, this list of conditions and the following disclaimer in the
 *    documentation and/or other materials provided with the distribution.
 * 3. Neither the name of the University nor the names of its contributors
 *    may be used to endorse or promote products derived from this software
 *    without specific prior written permission.
 *
 * THIS SOFTWARE IS PROVIDED BY THE REGENTS AND CONTRIBUTORS ``AS IS'' AND
 * ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE
 * IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE
 * ARE DISCLAIMED.  IN NO EVENT SHALL THE REGENTS OR CONTRIBUTORS BE LIABLE
 * FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL
 * DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS
 * OR SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION)
 * HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT
 * LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY
 * OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF
 * SUCH DAMAGE.
 */
~~~

### Embedded notice 50

Source paths: `php-8.5.11/main/php_glob.c`. SHA-256 of comment bytes `b40d1c66ea7f0073f92bd78f34beb5535c1b8a3d1dadfb1b723d9751bcd22e45`.

~~~text
/*
 * Copyright (c) 2008 Otto Moerbeek <otto@drijf.net>
 *
 * Permission to use, copy, modify, and distribute this software for any
 * purpose with or without fee is hereby granted, provided that the above
 * copyright notice and this permission notice appear in all copies.
 *
 * THE SOFTWARE IS PROVIDED "AS IS" AND THE AUTHOR DISCLAIMS ALL WARRANTIES
 * WITH REGARD TO THIS SOFTWARE INCLUDING ALL IMPLIED WARRANTIES OF
 * MERCHANTABILITY AND FITNESS. IN NO EVENT SHALL THE AUTHOR BE LIABLE FOR
 * ANY SPECIAL, DIRECT, INDIRECT, OR CONSEQUENTIAL DAMAGES OR ANY DAMAGES
 * WHATSOEVER RESULTING FROM LOSS OF USE, DATA OR PROFITS, WHETHER IN AN
 * ACTION OF CONTRACT, NEGLIGENCE OR OTHER TORTIOUS ACTION, ARISING OUT OF
 * OR IN CONNECTION WITH THE USE OR PERFORMANCE OF THIS SOFTWARE.
 */
~~~

### Embedded notice 51

Source paths: `php-8.5.11/main/php_glob.h`. SHA-256 of comment bytes `c5cd90901d0dde5192562e6c4035109b26c9d7db285979b72cde97441271ac7c`.

~~~text
/*
 * Copyright (c) 1989, 1993
 *	The Regents of the University of California.  All rights reserved.
 *
 * This code is derived from software contributed to Berkeley by
 * Guido van Rossum.
 *
 * Redistribution and use in source and binary forms, with or without
 * modification, are permitted provided that the following conditions
 * are met:
 * 1. Redistributions of source code must retain the above copyright
 *    notice, this list of conditions and the following disclaimer.
 * 2. Redistributions in binary form must reproduce the above copyright
 *    notice, this list of conditions and the following disclaimer in the
 *    documentation and/or other materials provided with the distribution.
 * 3. Neither the name of the University nor the names of its contributors
 *    may be used to endorse or promote products derived from this software
 *    without specific prior written permission.
 *
 * THIS SOFTWARE IS PROVIDED BY THE REGENTS AND CONTRIBUTORS ``AS IS'' AND
 * ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE
 * IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE
 * ARE DISCLAIMED.  IN NO EVENT SHALL THE REGENTS OR CONTRIBUTORS BE LIABLE
 * FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL
 * DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS
 * OR SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION)
 * HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT
 * LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY
 * OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF
 * SUCH DAMAGE.
 *
 *	@(#)glob.h	8.1 (Berkeley) 6/2/93
 */
~~~
