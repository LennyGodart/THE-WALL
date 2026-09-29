/* PX, a 5x7 bitmap font and LED panel renderer.
   Matches the firmware grid: 6px character advance (5 wide + 1 gap), 12px line pitch. */
(function () {
  var G = {
    ' ': '...../...../...../...../...../...../.....',
    'A': '.###./#...#/#...#/#####/#...#/#...#/#...#',
    'B': '####./#...#/#...#/####./#...#/#...#/####.',
    'C': '.###./#...#/#..../#..../#..../#...#/.###.',
    'D': '####./#...#/#...#/#...#/#...#/#...#/####.',
    'E': '#####/#..../#..../####./#..../#..../#####',
    'F': '#####/#..../#..../####./#..../#..../#....',
    'G': '.###./#...#/#..../#..##/#...#/#...#/.###.',
    'H': '#...#/#...#/#...#/#####/#...#/#...#/#...#',
    'I': '#####/..#../..#../..#../..#../..#../#####',
    'J': '..###/...#./...#./...#./...#./#..#./.##..',
    'K': '#...#/#..#./#.#../##.../#.#../#..#./#...#',
    'L': '#..../#..../#..../#..../#..../#..../#####',
    'M': '#...#/##.##/#.#.#/#...#/#...#/#...#/#...#',
    'N': '#...#/##..#/#.#.#/#..##/#...#/#...#/#...#',
    'O': '.###./#...#/#...#/#...#/#...#/#...#/.###.',
    'P': '####./#...#/#...#/####./#..../#..../#....',
    'Q': '.###./#...#/#...#/#...#/#.#.#/#..#./.##.#',
    'R': '####./#...#/#...#/####./#.#../#..#./#...#',
    'S': '.####/#..../#..../.###./....#/....#/####.',
    'T': '#####/..#../..#../..#../..#../..#../..#..',
    'U': '#...#/#...#/#...#/#...#/#...#/#...#/.###.',
    'V': '#...#/#...#/#...#/#...#/#...#/.#.#./..#..',
    'W': '#...#/#...#/#...#/#.#.#/#.#.#/##.##/#...#',
    'X': '#...#/#...#/.#.#./..#../.#.#./#...#/#...#',
    'Y': '#...#/#...#/.#.#./..#../..#../..#../..#..',
    'Z': '#####/....#/...#./..#../.#.../#..../#####',
    '0': '.###./#...#/#..##/#.#.#/##..#/#...#/.###.',
    '1': '..#../.##../..#../..#../..#../..#../.###.',
    '2': '.###./#...#/....#/...#./..#../.#.../#####',
    '3': '#####/...#./..#../...#./....#/#...#/.###.',
    '4': '...#./..##./.#.#./#..#./#####/...#./...#.',
    '5': '#####/#..../####./....#/....#/#...#/.###.',
    '6': '..##./.#.../#..../####./#...#/#...#/.###.',
    '7': '#####/....#/...#./..#../.#.../.#.../.#...',
    '8': '.###./#...#/#...#/.###./#...#/#...#/.###.',
    '9': '.###./#...#/#...#/.####/....#/...#./.##..',
    ':': '...../..#../...../...../..#../...../.....',
    '.': '...../...../...../...../...../..#../.....',
    ',': '...../...../...../...../..#../..#../.#...',
    '-': '...../...../...../.###./...../...../.....',
    '+': '...../..#../..#../#####/..#../..#../.....',
    '=': '...../...../#####/...../#####/...../.....',
    '_': '...../...../...../...../...../...../#####',
    '/': '....#/....#/...#./..#../.#.../#..../#....',
    '%': '#...#/...#./..#../..#../.#.../#...#/.....',
    '!': '..#../..#../..#../..#../..#../...../..#..',
    '?': '.###./#...#/....#/..##./..#../...../..#..',
    '(': '...#./..#../.#.../.#.../.#.../..#../...#.',
    ')': '.#.../..#../...#./...#./...#./..#../.#...',
    '#': '.#.#./#####/.#.#./.#.#./#####/.#.#./.....',
    "'": '..#../..#../...../...../...../...../.....',
    '"': '.#.#./.#.#./...../...../...../...../.....',
    '>': '...../.#.../..#../...#./..#../.#.../.....',
    '<': '...../...#./..#../.#.../..#../...#./.....',
    '*': '...../.#.#./..#../.###./..#../.#.#./.....',
    '@': '.###./#...#/#.###/#.#.#/#.###/#..../.###.',
    /* The rest of printable ASCII, because notes take free text. Without '~'
       the estimated arrival time lost the very mark that says it is estimated. */
    '~': '...../...../.#.../#.#.#/...#./...../.....',
    '$': '..#../.####/#.#../.###./..#.#/####./..#..',
    '&': '.##../#..#./#.#../.#.../#.#.#/#..#./.##.#',
    ';': '...../..#../...../...../..#../..#../.#...',
    '[': '.###./.#.../.#.../.#.../.#.../.#.../.###.',
    '\\': '#..../#..../.#.../..#../...#./....#/....#',
    ']': '.###./...#./...#./...#./...#./...#./.###.',
    '^': '..#../.#.#./#...#/...../...../...../.....',
    '`': '.#.../..#../...../...../...../...../.....',
    '{': '...#./..#../..#../.#.../..#../..#../...#.',
    '|': '..#../..#../..#../..#../..#../..#../..#..',
    '}': '.#.../..#../..#../...#./..#../..#../.#...',
    'a': '...../...../.###./....#/.####/#...#/.####',
    'b': '#..../#..../####./#...#/#...#/#...#/####.',
    'c': '...../...../.####/#..../#..../#..../.####',
    'd': '....#/....#/.####/#...#/#...#/#...#/.####',
    'e': '...../...../.###./#...#/#####/#..../.###.',
    'f': '..##./.#..#/.#.../###../.#.../.#.../.#...',
    'g': '...../.####/#...#/#...#/.####/....#/.###.',
    'h': '#..../#..../####./#...#/#...#/#...#/#...#',
    'i': '..#../...../.##../..#../..#../..#../.###.',
    'j': '...#./...../..##./...#./...#./#..#./.##..',
    'k': '#..../#..../#..#./#.#../##.../#.#../#..#.',
    'l': '.##../..#../..#../..#../..#../..#../.###.',
    'm': '...../...../##.#./#.#.#/#.#.#/#...#/#...#',
    'n': '...../...../####./#...#/#...#/#...#/#...#',
    'o': '...../...../.###./#...#/#...#/#...#/.###.',
    'p': '...../####./#...#/#...#/####./#..../#....',
    'q': '...../.####/#...#/#...#/.####/....#/....#',
    'r': '...../...../#.##./##..#/#..../#..../#....',
    's': '...../...../.####/#..../.###./....#/####.',
    't': '.#.../.#.../###../.#.../.#.../.#..#/..##.',
    'u': '...../...../#...#/#...#/#...#/#..##/.##.#',
    'v': '...../...../#...#/#...#/#...#/.#.#./..#..',
    'w': '...../...../#...#/#...#/#.#.#/#.#.#/.#.#.',
    'x': '...../...../#...#/.#.#./..#../.#.#./#...#',
    'y': '...../#...#/#...#/#...#/.####/....#/.###.',
    'z': '...../...../#####/...#./..#../.#.../#####',
    '\u00c4': '#...#/.###./#...#/#####/#...#/#...#/#...#',
    '\u00d6': '#...#/.###./#...#/#...#/#...#/#...#/.###.',
    '\u00dc': '#...#/...../#...#/#...#/#...#/#...#/.###.',
    '\u00df': '.##../#..#./#..#./##.../#...#/#...#/##...',
    '\u2192': '...../..#../...#./#####/...#./..#../.....',
    '\u00b0': '.##../.##../...../...../...../...../.....'
  };

  var glyphs = {};
  for (var k in G) glyphs[k] = G[k].split('/');

  function grid(w, h) {
    return { w: w, h: h, data: new Array(w * h).fill(null) };
  }

  function set(g, x, y, color) {
    x = x | 0; y = y | 0;
    if (x < 0 || y < 0 || x >= g.w || y >= g.h) return;
    g.data[y * g.w + x] = color;
  }

  function get(g, x, y) {
    if (x < 0 || y < 0 || x >= g.w || y >= g.h) return null;
    return g.data[y * g.w + x];
  }

  /* Draw a string. scale=1 -> 5x7 glyph, 6px advance. */
  function text(g, x, y, str, color, scale, mixed) {
    scale = scale || 1;
    str = mixed ? String(str) : String(str).toUpperCase();
    var cx = x;
    for (var i = 0; i < str.length; i++) {
      var rows = glyphs[str[i]] || glyphs[String(str[i]).toUpperCase()];
      if (rows) {
        for (var r = 0; r < 7; r++) {
          for (var c = 0; c < 5; c++) {
            if (rows[r][c] === '#') {
              for (var sy = 0; sy < scale; sy++)
                for (var sx = 0; sx < scale; sx++)
                  set(g, cx + c * scale + sx, y + r * scale + sy, color);
            }
          }
        }
      }
      cx += 6 * scale;
    }
    return cx - x - scale;
  }

  function width(str, scale) {
    return String(str).length * 6 * (scale || 1) - (scale || 1);
  }

  /* Break a string into lines that fit `cols` grid columns at `scale`.
     6 columns per character advance, so the last character needs no trailing gap. */
  function wrap(str, cols, scale) {
    scale = scale || 1;
    const per = Math.max(1, Math.floor((cols + scale) / (6 * scale)));
    const words = String(str).toUpperCase().split(/\s+/).filter(Boolean);
    const lines = [];
    let cur = '';
    for (const w of words) {
      const test = cur ? cur + ' ' + w : w;
      if (test.length <= per) { cur = test; continue; }
      if (cur) lines.push(cur);
      /* A word longer than the line gets hard-split rather than overflowing. */
      let rest = w;
      while (rest.length > per) { lines.push(rest.slice(0, per)); rest = rest.slice(per); }
      cur = rest;
    }
    if (cur) lines.push(cur);
    return lines;
  }

  /* Draw wrapped lines on a pitch of (7 + lead) rows per line. */
  function block(g, x, y, lines, color, scale, lead) {
    scale = scale || 1;
    lead = lead == null ? 2 : lead;
    const pitch = (7 + lead) * scale;
    lines.forEach((line, i) => text(g, x, y + i * pitch, line, color, scale));
    return lines.length * pitch - lead * scale;
  }

  function rect(g, x, y, w, h, color) {
    for (var yy = 0; yy < h; yy++) for (var xx = 0; xx < w; xx++) set(g, x + xx, y + yy, color);
  }

  function frame(g, x, y, w, h, color) {
    for (var xx = 0; xx < w; xx++) { set(g, x + xx, y, color); set(g, x + xx, y + h - 1, color); }
    for (var yy = 0; yy < h; yy++) { set(g, x, y + yy, color); set(g, x + w - 1, y + yy, color); }
  }

  /* Deterministic pseudo-random, so placeholder art is stable across renders. */
  function rnd(seed) {
    var s = seed;
    return function () { s = (s * 1103515245 + 12345) & 0x7fffffff; return s / 0x7fffffff; };
  }

  /* Paint a grid onto a canvas as discrete LEDs.
     opts: px (led size), gap, off (unlit color), glow (0..1), round (bool) */
  function paint(canvas, g, opts) {
    opts = opts || {};
    var px = opts.px || 6, gap = opts.gap == null ? 1 : opts.gap;
    var step = px + gap;
    var dpr = Math.min(window.devicePixelRatio || 1, 2);
    var W = g.w * step - gap, H = g.h * step - gap;
    if (canvas.width !== Math.round(W * dpr)) {
      canvas.width = Math.round(W * dpr);
      canvas.height = Math.round(H * dpr);
    }
    /* Explicit px width keeps the LED pitch exact; max-width + auto height keep it
       from overflowing a narrow container before a resize redraw lands. */
    canvas.style.width = W + 'px';
    canvas.style.height = 'auto';
    canvas.style.maxWidth = '100%';
    var ctx = canvas.getContext('2d');
    ctx.setTransform(dpr, 0, 0, dpr, 0, 0);
    ctx.clearRect(0, 0, W, H);

    var off = opts.off;
    if (off) {
      /* The unlit grid is 8192 identical rects on a full panel, which is too much
         per frame for an animation. Cache it once per geometry and blit it. */
      var key = g.w + 'x' + g.h + '_' + px + '_' + gap + '_' + off;
      var cache = canvas._pxOffCache;
      if (!cache || cache.key !== key) {
        var oc = document.createElement('canvas');
        oc.width = Math.round(W * dpr);
        oc.height = Math.round(H * dpr);
        var octx = oc.getContext('2d');
        octx.setTransform(dpr, 0, 0, dpr, 0, 0);
        octx.fillStyle = off;
        for (var oy = 0; oy < g.h; oy++)
          for (var ox = 0; ox < g.w; ox++)
            octx.fillRect(ox * step, oy * step, px, px);
        cache = canvas._pxOffCache = { key: key, canvas: oc };
      }
      ctx.drawImage(cache.canvas, 0, 0, W, H);
    }

    var glow = opts.glow == null ? 0.5 : opts.glow;
    if (glow > 0) {
      ctx.globalAlpha = 0.22 * glow;
      for (var y2 = 0; y2 < g.h; y2++) {
        for (var x2 = 0; x2 < g.w; x2++) {
          var c = g.data[y2 * g.w + x2];
          if (c) { ctx.fillStyle = c; ctx.fillRect(x2 * step - step, y2 * step - step, px + step * 2, px + step * 2); }
        }
      }
      ctx.globalAlpha = 1;
    }

    for (var y3 = 0; y3 < g.h; y3++) {
      for (var x3 = 0; x3 < g.w; x3++) {
        var col = g.data[y3 * g.w + x3];
        if (col) { ctx.fillStyle = col; ctx.fillRect(x3 * step, y3 * step, px, px); }
      }
    }
    return { w: W, h: H };
  }

  window.PX = {
    grid: grid, set: set, get: get, text: text, width: width,
    wrap: wrap, block: block,
    rect: rect, frame: frame, paint: paint, rnd: rnd, glyphs: glyphs
  };
})();
