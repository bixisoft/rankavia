import type { Check } from '../types';
import { attr, fail, info, linkByRel, metaContent, pass, truncate, warn } from '../dom';

const TITLE_MIN = 30;
const TITLE_MAX = 60;
const DESC_MIN = 70;
const DESC_MAX = 160;

export const seoChecks: Check[] = [
  {
    id: 'seo.title.present',
    run(ctx) {
      const title = ctx.title;
      return title
        ? pass(this.id, `Title: "${truncate(title)}"`, { title })
        : fail(this.id, 'The page has no <title>.');
    },
  },
  {
    id: 'seo.title.length',
    run(ctx) {
      const length = ctx.title.length;
      if (length === 0) return fail(this.id, 'No title to measure.', { length });
      if (length >= TITLE_MIN && length <= TITLE_MAX) {
        return pass(this.id, `Title is ${length} characters.`, { length });
      }
      if (length >= TITLE_MIN - 10 && length <= TITLE_MAX + 10) {
        return warn(this.id, `Title is ${length} characters; aim for ${TITLE_MIN}–${TITLE_MAX}.`, { length });
      }
      return fail(this.id, `Title is ${length} characters; aim for ${TITLE_MIN}–${TITLE_MAX}.`, { length });
    },
  },
  {
    id: 'seo.description.present',
    run(ctx) {
      const description = metaContent(ctx, 'name', 'description') ?? '';
      return description
        ? pass(this.id, `Meta description: "${truncate(description)}"`, { description })
        : fail(this.id, 'The page has no meta description.');
    },
  },
  {
    id: 'seo.description.length',
    run(ctx) {
      const length = (metaContent(ctx, 'name', 'description') ?? '').length;
      if (length === 0) return fail(this.id, 'No meta description to measure.', { length });
      if (length >= DESC_MIN && length <= DESC_MAX) {
        return pass(this.id, `Meta description is ${length} characters.`, { length });
      }
      if (length >= DESC_MIN - 20 && length <= DESC_MAX + 20) {
        return warn(this.id, `Meta description is ${length} characters; aim for ${DESC_MIN}–${DESC_MAX}.`, { length });
      }
      return fail(this.id, `Meta description is ${length} characters; aim for ${DESC_MIN}–${DESC_MAX}.`, { length });
    },
  },
  {
    id: 'seo.canonical.present',
    run(ctx) {
      const href = linkByRel(ctx, 'canonical')?.href ?? '';
      if (!href) return fail(this.id, 'No canonical link found.');
      if (!/^https?:\/\//i.test(href)) {
        return warn(this.id, 'Canonical href should be an absolute URL.', { href });
      }
      return pass(this.id, `Canonical: ${href}`, { href });
    },
  },
  {
    id: 'seo.h1.single',
    run(ctx) {
      const count = ctx.headings.filter((heading) => heading.level === 1).length;
      if (count === 1) return pass(this.id, 'Exactly one H1.', { count });
      if (count === 0) return fail(this.id, 'The page has no H1.', { count });
      return warn(this.id, `The page has ${count} H1 headings; use one.`, { count });
    },
  },
  {
    id: 'seo.viewport.present',
    run(ctx) {
      const viewport = metaContent(ctx, 'name', 'viewport') ?? '';
      if (!viewport) return fail(this.id, 'No viewport meta tag; the page is not mobile-friendly.');
      if (!/width\s*=\s*device-width/i.test(viewport)) {
        return warn(this.id, 'Viewport meta should include width=device-width.', { viewport });
      }
      return pass(this.id, 'Responsive viewport is set.', { viewport });
    },
  },
  {
    id: 'seo.html.lang',
    run(ctx) {
      const lang = ctx.lang;
      return lang
        ? pass(this.id, `Language declared: ${lang}.`, { lang })
        : fail(this.id, 'The <html> element has no lang attribute.');
    },
  },
  {
    id: 'seo.images.alt',
    run(ctx) {
      const images = ctx.images;
      if (images.length === 0) return info(this.id, 'No images on the page.', { total: 0 });
      const missing = images.filter((img) => attr(img, 'alt') === undefined).length;
      const details = { total: images.length, missing };
      if (missing === 0) return pass(this.id, `All ${images.length} images have alt attributes.`, details);
      if (missing / images.length <= 0.2) {
        return warn(this.id, `${missing} of ${images.length} images lack an alt attribute.`, details);
      }
      return fail(this.id, `${missing} of ${images.length} images lack an alt attribute.`, details);
    },
  },
  {
    id: 'seo.og.basic',
    run(ctx) {
      const wanted = ['og:title', 'og:description', 'og:image'];
      const missing = wanted.filter((name) => !metaContent(ctx, 'property', name));
      if (missing.length === 0) return pass(this.id, 'Open Graph title, description and image are set.');
      if (missing.length < wanted.length) {
        return warn(this.id, `Missing Open Graph tags: ${missing.join(', ')}.`, { missing });
      }
      return fail(this.id, 'No Open Graph tags found.', { missing });
    },
  },
  {
    id: 'seo.robots.indexable',
    run(ctx) {
      const sources = [
        metaContent(ctx, 'name', 'robots'),
        metaContent(ctx, 'name', 'googlebot'),
        ctx.headers?.['x-robots-tag'],
      ];
      const blocking = sources.find((value) => value !== undefined && /\bnoindex\b/i.test(value));
      if (blocking !== undefined) {
        return fail(this.id, 'The page is marked noindex and will not appear in search.', { directive: blocking });
      }
      return pass(this.id, 'The page is indexable.');
    },
  },
  {
    id: 'seo.jsonld.valid',
    run(ctx) {
      if (ctx.jsonLdErrors > 0) {
        return fail(this.id, `${ctx.jsonLdErrors} JSON-LD block(s) failed to parse.`, { errors: ctx.jsonLdErrors });
      }
      if (ctx.jsonLd.length === 0) return warn(this.id, 'No JSON-LD structured data found.', { blocks: 0 });
      return pass(this.id, `${ctx.jsonLd.length} valid JSON-LD block(s).`, { blocks: ctx.jsonLd.length });
    },
  },
];
