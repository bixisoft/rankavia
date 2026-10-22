import { describe, expect, it } from 'vitest';
import { analyze, buildContext } from '../src/index';
import { attr, byTag, linkByRel, metaContent } from '../src/dom';

const PAGE = `<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>  Upper &amp; lower
    case   </title>
  <meta name="Description" content=" First description ">
  <meta name="description" content="Second description">
  <meta property="og:title" content="OG title">
  <meta http-equiv="Content-Type" content="text/html; charset=utf-8">
  <link rel="Preload Stylesheet" href="/a.css">
  <link rel="canonical" href="https://example.com/">
  <link rel="canonical" href="https://example.com/second">
  <script src="/head.js"></script>
  <script type="application/ld+json">{"@type":"Article","dateModified":"2026-10-01"}</script>
  <style>body { color: red }</style>
</head>
<body>
  <main>
    <h1>Main heading</h1>
    <h2>Why   is it <em>fast</em>?</h2>
    <img src="/a.jpg" alt="A">
    <h3>Details</h3>
    <img src="/b.jpg">
    <p>Hello<b>,</b> world &amp; friends.</p><p>Second paragraph.</p>
    <script>document.write('ignored');</script>
    <noscript><img src="/n.jpg"></noscript>
    <template><p>not shown</p></template>
  </main>
  <script src="/body.js"></script>
  <time datetime="2026-10-01">1 Oct</time>
</body>
</html>`;

/** The analyze.test.ts "good" page with every tag and attribute name written in upper case. */
const UPPER_HTML = `<!DOCTYPE HTML>
<HTML LANG="en">
<HEAD>
  <META CHARSET="utf-8">
  <META NAME="viewport" CONTENT="width=device-width, initial-scale=1">
  <TITLE>How to Speed Up WordPress in 2026: A Practical Guide</TITLE>
  <META NAME="description" CONTENT="A practical, tested guide to faster WordPress: caching, images, fonts and scripts, with the exact settings that reach a 100 Lighthouse score.">
  <LINK REL="canonical" HREF="https://example.com/speed-up-wordpress/">
  <META PROPERTY="og:title" CONTENT="How to Speed Up WordPress in 2026">
  <META PROPERTY="og:description" CONTENT="Caching, images, fonts and scripts.">
  <META PROPERTY="og:image" CONTENT="https://example.com/og.jpg">
  <SCRIPT SRC="/app.js" DEFER></SCRIPT>
  <SCRIPT TYPE="application/ld+json">{"@context":"https://schema.org","@type":"Article","headline":"How to Speed Up WordPress","dateModified":"2026-10-01","author":{"@type":"Person","name":"Jane Doe"}}</SCRIPT>
</HEAD>
<BODY>
  <MAIN><ARTICLE>
    <H1>How to Speed Up WordPress in 2026</H1>
    <H2>Why is my WordPress site slow?</H2>
    <P>Because of render-blocking scripts and unoptimised images.</P>
    <IMG SRC="/a.jpg" ALT="Lighthouse report" WIDTH="800" HEIGHT="400">
    <IMG SRC="/b.jpg" ALT="Caching settings" WIDTH="800" HEIGHT="400">
    <IMG SRC="/c.jpg" ALT="Image settings" WIDTH="800" HEIGHT="400" LOADING="lazy">
  </ARTICLE></MAIN>
</BODY>
</HTML>`;

/** Same document with lower-cased tag names (attribute names and values untouched). */
const LOWER_HTML = UPPER_HTML.replace(/<(\/?)([A-Z][A-Z0-9]*)/g, (_, slash: string, name: string) => `<${slash}${name.toLowerCase()}`);

describe('buildContext index', () => {
  const ctx = buildContext({ url: 'https://example.com/', html: PAGE });

  it('finds the language and the title text', () => {
    expect(ctx.lang).toBe('en');
    expect(ctx.title).toBe('Upper & lower case');
  });

  it('indexes meta by name, property and http-equiv, first one wins, case-insensitively', () => {
    expect(metaContent(ctx, 'name', 'description')).toBe('First description');
    expect(metaContent(ctx, 'name', 'DESCRIPTION')).toBe('First description');
    expect(metaContent(ctx, 'property', 'og:title')).toBe('OG title');
    expect(metaContent(ctx, 'http-equiv', 'content-type')).toBe('text/html; charset=utf-8');
    expect(metaContent(ctx, 'name', 'og:title')).toBeUndefined();
    expect(metaContent(ctx, 'name', 'missing')).toBeUndefined();
  });

  it('indexes links with every rel token in document order', () => {
    expect(linkByRel(ctx, 'canonical')?.href).toBe('https://example.com/');
    expect(linkByRel(ctx, 'Canonical')?.href).toBe('https://example.com/');
    expect(linkByRel(ctx, 'stylesheet')?.href).toBe('/a.css');
    expect(linkByRel(ctx, 'preload')?.href).toBe('/a.css');
    expect(ctx.links.filter((link) => link.rel.includes('canonical')).map((link) => link.href)).toEqual([
      'https://example.com/',
      'https://example.com/second',
    ]);
    expect(linkByRel(ctx, 'icon')).toBeUndefined();
  });

  it('lists headings in order with level and normalised text', () => {
    expect(ctx.headings).toEqual([
      { level: 1, text: 'Main heading' },
      { level: 2, text: 'Why is it fast?' },
      { level: 3, text: 'Details' },
    ]);
  });

  it('lists images in order and separates head scripts from body scripts', () => {
    expect(ctx.images.map((img) => attr(img, 'src'))).toEqual(['/a.jpg', '/b.jpg']);
    expect(byTag(ctx, 'script')).toHaveLength(4);
    expect(ctx.headScripts.map((script) => attr(script, 'src'))).toEqual(['/head.js', undefined]);
  });

  it('indexes every element by tag name', () => {
    expect(byTag(ctx, 'time')).toHaveLength(1);
    expect(byTag(ctx, 'P')).toHaveLength(3); // two visible paragraphs plus the one inside <template>
    expect(byTag(ctx, 'meta')).toHaveLength(6);
    expect(byTag(ctx, 'nope')).toEqual([]);
  });

  it('extracts JSON-LD', () => {
    expect(ctx.jsonLd).toEqual([{ '@type': 'Article', dateModified: '2026-10-01' }]);
    expect(ctx.jsonLdErrors).toBe(0);
  });

  it('computes visible body text lazily, decoded and whitespace-normalised', () => {
    const descriptor = Object.getOwnPropertyDescriptor(ctx, 'bodyText');
    expect(typeof descriptor?.get).toBe('function');
    expect(ctx.bodyText).toBe('Main heading Why is it fast? Details Hello, world & friends. Second paragraph. 1 Oct');
    expect(ctx.bodyText).toBe(ctx.bodyText);
  });

  it('falls back to the whole document for text when there is no body', () => {
    const fragment = buildContext({ url: 'https://example.com/', html: '<h1>Only</h1><p>a   fragment</p>' });
    expect(byTag(fragment, 'body')).toEqual([]);
    expect(fragment.bodyText).toBe('Only a fragment');
  });

  it('lower-cases header names', () => {
    const withHeaders = buildContext({ url: 'https://example.com/', html: PAGE, headers: { 'X-Robots-Tag': 'all' } });
    expect(withHeaders.headers).toEqual({ 'x-robots-tag': 'all' });
  });
});

describe('attr', () => {
  const page = buildContext({
    url: 'https://example.com/',
    html: '<img src="/a.jpg?x=1&amp;y=2" ALT="Tom &amp; Jerry" srcset="/a.jpg?w=1&amp;q=2 1x"><script async src="/x.js"></script>',
  });
  const [img] = page.images;
  const [script] = byTag(page, 'script');

  it('decodes entities in the value asked for, case-insensitively', () => {
    expect(img && attr(img, 'alt')).toBe('Tom & Jerry');
    expect(img && attr(img, 'ALT')).toBe('Tom & Jerry');
    expect(img && attr(img, 'src')).toBe('/a.jpg?x=1&y=2');
  });

  it('returns "" for a valueless attribute and undefined for a missing one', () => {
    expect(script && attr(script, 'async')).toBe('');
    expect(script && attr(script, 'defer')).toBeUndefined();
    expect(img && attr(img, 'loading')).toBeUndefined();
  });
});

describe('upper-case markup', () => {
  it('is matched by tag selectors without lowerCaseTagName', () => {
    const { doc } = buildContext({ url: 'https://example.com/', html: UPPER_HTML });
    expect(doc.querySelectorAll('meta')).toHaveLength(6);
    expect(doc.querySelector('link')?.getAttribute('href')).toBe('https://example.com/speed-up-wordpress/');
    expect(doc.querySelectorAll('h1')).toHaveLength(1);
    expect(doc.querySelectorAll('img')).toHaveLength(3);
  });

  it('is indexed the same way as lower-case markup', () => {
    const ctx = buildContext({ url: 'https://example.com/', html: UPPER_HTML });
    expect(ctx.title).toBe('How to Speed Up WordPress in 2026: A Practical Guide');
    expect(ctx.lang).toBe('en');
    expect(metaContent(ctx, 'name', 'description')).toMatch(/^A practical, tested guide/);
    expect(metaContent(ctx, 'property', 'og:image')).toBe('https://example.com/og.jpg');
    expect(linkByRel(ctx, 'canonical')?.href).toBe('https://example.com/speed-up-wordpress/');
    expect(ctx.headings.map((heading) => heading.level)).toEqual([1, 2]);
    expect(ctx.images).toHaveLength(3);
    expect(ctx.headScripts).toHaveLength(2);
    expect(byTag(ctx, 'article')).toHaveLength(1);
    expect(ctx.jsonLd).toHaveLength(1);
  });

  it('produces identical check results for upper- and lower-case tags', () => {
    const upper = analyze({ url: 'https://example.com/', html: UPPER_HTML });
    const lower = analyze({ url: 'https://example.com/', html: LOWER_HTML });
    expect(upper.results).toEqual(lower.results);
    expect(upper.scores).toEqual(lower.scores);
    expect(upper.results.filter((result) => result.status === 'fail')).toEqual([]);
    expect(upper.scores.categories.seo?.score).toBe(100);
  });
});
