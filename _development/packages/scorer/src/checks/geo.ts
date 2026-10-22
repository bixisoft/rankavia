import type { Check } from '../types';
import { attr, byTag, findInJson, metaContent, pass, warn } from '../dom';

export const geoChecks: Check[] = [
  {
    id: 'geo.semantic.article',
    run(ctx) {
      const count = byTag(ctx, 'article').length + byTag(ctx, 'main').length;
      return count > 0
        ? pass(this.id, 'Semantic <article>/<main> wrappers make the main content easy to extract.', { count })
        : warn(this.id, 'No <article> or <main> element; wrap the primary content in semantic HTML.', { count });
    },
  },
  {
    id: 'geo.updated.date',
    run(ctx) {
      const inJsonLd = findInJson(ctx.jsonLd, (key) => key === 'dateModified');
      const inMeta = Boolean(metaContent(ctx, 'property', 'article:modified_time'));
      const inTime = byTag(ctx, 'time').some((time) => (attr(time, 'datetime') ?? '') !== '');
      if (inJsonLd || inMeta || inTime) {
        return pass(this.id, 'A machine-readable modified date is present (freshness signal).', {
          jsonLd: inJsonLd,
          meta: inMeta,
          time: inTime,
        });
      }
      return warn(this.id, 'No dateModified, article:modified_time or <time datetime>; show when content was updated.');
    },
  },
];
