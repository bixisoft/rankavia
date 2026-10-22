import type { Check } from '../types';
import { findInJson, pass, warn } from '../dom';

export const aeoChecks: Check[] = [
  {
    id: 'aeo.headings.questions',
    run(ctx) {
      const headings = ctx.headings.filter((heading) => heading.level === 2 || heading.level === 3);
      const questions = headings.filter((heading) => heading.text.endsWith('?')).length;
      if (questions > 0) {
        return pass(this.id, `${questions} question-style heading(s) help answer engines match queries.`, {
          questions,
          headings: headings.length,
        });
      }
      return warn(this.id, 'No question-style H2/H3 headings; phrase key sections as the questions people ask.', {
        questions: 0,
        headings: headings.length,
      });
    },
  },
  {
    id: 'aeo.author.schema',
    run(ctx) {
      const hasAuthor = findInJson(
        ctx.jsonLd,
        (key, value) =>
          key === 'author' ||
          (key === '@type' && (value === 'Person' || (Array.isArray(value) && value.includes('Person')))),
      );
      return hasAuthor
        ? pass(this.id, 'Author or Person markup present (E-E-A-T signal).')
        : warn(this.id, 'No author/Person structured data; add an author with a bio and sameAs links.');
    },
  },
];
