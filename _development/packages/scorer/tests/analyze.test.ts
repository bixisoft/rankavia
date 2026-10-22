import { describe, expect, it } from 'vitest';
import { analyze, buildContext, checks, computeScores, metas } from '../src/index';

const GOOD_HTML = `<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>How to Speed Up WordPress in 2026: A Practical Guide</title>
  <meta name="description" content="A practical, tested guide to faster WordPress: caching, images, fonts and scripts, with the exact settings that reach a 100 Lighthouse score.">
  <link rel="canonical" href="https://example.com/speed-up-wordpress/">
  <meta property="og:title" content="How to Speed Up WordPress in 2026">
  <meta property="og:description" content="Caching, images, fonts and scripts.">
  <meta property="og:image" content="https://example.com/og.jpg">
  <script src="/app.js" defer></script>
  <script type="application/ld+json">{"@context":"https://schema.org","@type":"Article","headline":"How to Speed Up WordPress","dateModified":"2026-10-01","author":{"@type":"Person","name":"Jane Doe"}}</script>
</head>
<body>
  <main><article>
    <h1>How to Speed Up WordPress in 2026</h1>
    <h2>Why is my WordPress site slow?</h2>
    <p>Because of render-blocking scripts and unoptimised images.</p>
    <img src="/a.jpg" alt="Lighthouse report" width="800" height="400">
    <img src="/b.jpg" alt="Caching settings" width="800" height="400">
    <img src="/c.jpg" alt="Image settings" width="800" height="400" loading="lazy">
  </article></main>
</body>
</html>`;

const BAD_HTML = `<html><head>
<meta name="robots" content="noindex, nofollow">
<script src="/a.js"></script><script src="/b.js"></script><script src="/c.js"></script>
<script type="application/ld+json">{not json}</script>
</head><body>
<div><h1>One</h1><h1>Two</h1><img src="/x.jpg"><img src="/y.jpg"><img src="/z.jpg"><img src="/w.jpg"><img src="/v.jpg"></div>
</body></html>`;

describe('catalogue', () => {
  it('has a catalogue entry for every implemented check and vice versa', () => {
    const implemented = new Set(checks.map((c) => c.id));
    const catalogued = new Set(metas.map((m) => m.id));
    expect([...implemented].sort()).toEqual([...catalogued].sort());
  });

  it('uses the severity to weight mapping', () => {
    const weights = { critical: 4, high: 3, medium: 2, low: 1 };
    for (const meta of metas) expect(meta.weight).toBe(weights[meta.severity]);
  });
});

describe('analyze', () => {
  it('passes a well-formed page', () => {
    const report = analyze({ url: 'https://example.com/speed-up-wordpress/', html: GOOD_HTML });
    const failed = report.results.filter((r) => r.status === 'fail').map((r) => r.id);
    expect(failed).toEqual([]);
    expect(report.scores.overall).toBeGreaterThanOrEqual(90);
    expect(report.scores.categories.seo?.score).toBe(100);
    expect(report.scores.categories.aeo?.score).toBe(100);
    expect(report.scores.categories.geo?.score).toBe(100);
  });

  it('fails a broken page on the important checks', () => {
    const report = analyze({ url: 'https://example.com/', html: BAD_HTML });
    const status = Object.fromEntries(report.results.map((r) => [r.id, r.status]));
    expect(status['seo.title.present']).toBe('fail');
    expect(status['seo.description.present']).toBe('fail');
    expect(status['seo.canonical.present']).toBe('fail');
    expect(status['seo.viewport.present']).toBe('fail');
    expect(status['seo.html.lang']).toBe('fail');
    expect(status['seo.robots.indexable']).toBe('fail');
    expect(status['seo.jsonld.valid']).toBe('fail');
    expect(status['seo.h1.single']).toBe('warn');
    expect(status['seo.images.alt']).toBe('fail');
    expect(status['speed.scripts.blocking']).toBe('fail');
    expect(status['speed.images.dimensions']).toBe('fail');
    expect(status['speed.images.lazy']).toBe('warn');
    expect(report.scores.overall).toBeLessThan(40);
  });

  it('treats tag and attribute names case-insensitively', () => {
    const upper = GOOD_HTML.replace(
      /<(\/?)(html|head|title|meta|link|script|body|main|article|h1|h2|p|img)\b/g,
      (_match, slash: string, tag: string) => `<${slash}${tag.toUpperCase()}`,
    ).replace(
      /\b(lang|name|content|property|rel|href|src|alt|width|height|loading|type|defer)=/g,
      (_match, name: string) => `${name.toUpperCase()}=`,
    );
    expect(upper).not.toBe(GOOD_HTML);
    const expected = analyze({ url: 'https://example.com/', html: GOOD_HTML });
    const actual = analyze({ url: 'https://example.com/', html: upper });
    expect(actual.results.map((r) => [r.id, r.status, r.message])).toEqual(
      expected.results.map((r) => [r.id, r.status, r.message]),
    );
    expect(actual.scores).toEqual(expected.scores);
  });

  it('reads X-Robots-Tag from response headers case-insensitively', () => {
    const report = analyze({
      url: 'https://example.com/',
      html: GOOD_HTML,
      headers: { 'X-Robots-Tag': 'noindex' },
    });
    expect(report.results.find((r) => r.id === 'seo.robots.indexable')?.status).toBe('fail');
  });
});

describe('buildContext', () => {
  it('indexes the page in one pass', () => {
    const ctx = buildContext({ url: 'https://example.com/', html: GOOD_HTML, headers: { 'X-Robots-Tag': 'all' } });
    expect(ctx.title).toBe('How to Speed Up WordPress in 2026: A Practical Guide');
    expect(ctx.lang).toBe('en');
    expect(ctx.headers).toEqual({ 'x-robots-tag': 'all' });
    expect(ctx.metas.map((m) => m.name ?? m.property)).toEqual([
      undefined,
      'viewport',
      'description',
      'og:title',
      'og:description',
      'og:image',
    ]);
    expect(ctx.links).toHaveLength(1);
    expect(ctx.links[0]?.rel).toEqual(['canonical']);
    expect(ctx.links[0]?.href).toBe('https://example.com/speed-up-wordpress/');
    expect(ctx.headings).toEqual([
      { level: 1, text: 'How to Speed Up WordPress in 2026' },
      { level: 2, text: 'Why is my WordPress site slow?' },
    ]);
    expect(ctx.images).toHaveLength(3);
    expect(ctx.headScripts).toHaveLength(2);
    expect(ctx.elements.get('article')).toHaveLength(1);
    expect(ctx.elements.get('main')).toHaveLength(1);
    expect(ctx.elements.has('time')).toBe(false);
    expect(ctx.jsonLd).toHaveLength(1);
    expect(ctx.jsonLdErrors).toBe(0);
  });

  it('only counts scripts inside <head> as head scripts and indexes every element by tag', () => {
    const ctx = buildContext({
      url: 'https://example.com/',
      html: '<html><head><script src="/a.js"></script></head><body><script src="/b.js"></script><time datetime="2026-01-01">Jan</time><time>no date</time></body></html>',
    });
    expect(ctx.headScripts).toHaveLength(1);
    expect(ctx.elements.get('script')).toHaveLength(2);
    expect(ctx.elements.get('time')).toHaveLength(2);
  });
});

describe('computeScores', () => {
  it('weights results, gives warnings half credit and ignores info', () => {
    const scores = computeScores([
      { category: 'seo', weight: 4, status: 'pass' },
      { category: 'seo', weight: 1, status: 'fail' },
      { category: 'speed', weight: 3, status: 'warn' },
      { category: 'aeo', weight: 2, status: 'info' },
    ]);
    expect(scores.categories.seo?.score).toBe(80);
    expect(scores.categories.speed?.score).toBe(50);
    expect(scores.categories.aeo?.score).toBeNull();
    expect(scores.overall).toBe(65);
  });

  it('returns zero for no results', () => {
    expect(computeScores([])).toEqual({ overall: 0, categories: {} });
  });
});
