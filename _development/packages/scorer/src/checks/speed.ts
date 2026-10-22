import type { Check } from '../types';
import { attr, fail, info, pass, warn } from '../dom';

export const speedChecks: Check[] = [
  {
    id: 'speed.images.dimensions',
    run(ctx) {
      const images = ctx.images;
      if (images.length === 0) return info(this.id, 'No images on the page.', { total: 0 });
      const missing = images.filter((img) => !attr(img, 'width') || !attr(img, 'height')).length;
      const details = { total: images.length, missing };
      if (missing === 0) return pass(this.id, 'All images declare width and height (prevents layout shift).', details);
      if (missing / images.length <= 0.25) {
        return warn(this.id, `${missing} of ${images.length} images lack width/height attributes.`, details);
      }
      return fail(this.id, `${missing} of ${images.length} images lack width/height attributes (CLS risk).`, details);
    },
  },
  {
    id: 'speed.images.lazy',
    run(ctx) {
      const images = ctx.images;
      const belowFold = images.slice(2);
      if (belowFold.length === 0) return info(this.id, 'Too few images for lazy-loading to matter.', { total: images.length });
      const eager = belowFold.filter((img) => (attr(img, 'loading') ?? '').toLowerCase() !== 'lazy').length;
      const details = { total: images.length, eager };
      if (eager < 3) return pass(this.id, 'Below-the-fold images are lazy-loaded.', details);
      return warn(this.id, `${eager} below-the-fold images load eagerly; add loading="lazy".`, details);
    },
  },
  {
    id: 'speed.scripts.blocking',
    run(ctx) {
      const blocking = ctx.headScripts.filter((script) => {
        if (!attr(script, 'src')) return false;
        if (attr(script, 'async') !== undefined || attr(script, 'defer') !== undefined) return false;
        return (attr(script, 'type') ?? '').toLowerCase() !== 'module';
      }).length;
      const details = { blocking };
      if (blocking === 0) return pass(this.id, 'No render-blocking scripts in <head>.', details);
      if (blocking <= 2) return warn(this.id, `${blocking} render-blocking script(s) in <head>; add defer or async.`, details);
      return fail(this.id, `${blocking} render-blocking scripts in <head>; add defer or async.`, details);
    },
  },
];
