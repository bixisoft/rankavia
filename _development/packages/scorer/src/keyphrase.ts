import { buildContext, metaContent } from './dom';
import type { PageInput } from './types';

const WORD = /[\p{L}\p{N}][\p{L}\p{M}\p{N}]*/gu;
const words = (value: string): string[] => value.toLocaleLowerCase().match(WORD) ?? [];

/** A bounded phrase suitable for comparison and safe aggregate persistence. */
export function normalizeKeyphrase(value: unknown): string {
  if (typeof value !== 'string') throw new TypeError('Invalid focus keyphrase.');
  const phrase = value.normalize('NFC').trim().replace(/\s+/g, ' ');
  if (!phrase || phrase.length > 100 || words(phrase).length === 0 || words(phrase).length > 10 || /[\p{C}<>]/u.test(phrase)) {
    throw new TypeError('Invalid focus keyphrase.');
  }
  return phrase;
}

export interface KeyphraseCheck {
  id: string;
  title: string;
  status: 'pass' | 'warn' | 'unknown';
  found: string;
  fix: string;
}

/** Factual placement coverage; separate from the 19 catalogue checks. */
export function analyzeKeyphrase(input: PageInput, value: string) {
  const phrase = normalizeKeyphrase(value);
  const target = words(phrase);
  const ctx = buildContext(input);
  const body = words(ctx.bodyText);
  const count = (haystack: string[]): number => {
    let matches = 0;
    for (let i = 0; i <= haystack.length - target.length; i++) {
      if (target.every((word, offset) => word === haystack[i + offset])) {
        matches++;
        i += target.length - 1;
      }
    }
    return matches;
  };
  const description = metaContent(ctx, 'name', 'description') ?? '';
  let path = '';
  try { path = decodeURIComponent(new URL(input.url).pathname); } catch { /* URL placement is unknown. */ }
  const placements: Array<[string, string, string | null, string]> = [
    ['title', 'Page title', ctx.title || null, 'Consider using the phrase naturally in the page title.'],
    ['description', 'Meta description', description || null, 'Consider using the phrase naturally in the meta description.'],
    ['h1', 'H1 heading', ctx.headings.filter(h => h.level === 1).map(h => h.text).join(' ') || null, 'Consider using the phrase naturally in an H1.'],
    ['url', 'URL path', path || null, 'Consider a descriptive URL path when changing it is appropriate.'],
    ['body', 'Visible body', ctx.bodyText || null, 'Consider mentioning the phrase naturally in visible page copy.'],
    ['intro', 'Opening text', body.length ? body.slice(0, 100).join(' ') : null, 'Consider introducing the topic near the start of the page.'],
  ];
  const checks: KeyphraseCheck[] = placements.map(([id, title, source, fix]) => {
    const found = source !== null && count(words(source)) > 0;
    return { id, title, status: found ? 'pass' : 'warn', found: found ? 'Exact phrase found.' : 'Exact phrase not found.', fix };
  });
  const assessed = checks.filter(check => check.status !== 'unknown').length;
  const matched = checks.filter(check => check.status === 'pass').length;
  const bodyCount = count(body);
  return { phrase, score: assessed ? Math.round(100 * matched / assessed) : null, matched, assessed,
    bodyCount, wordCount: body.length, density: body.length ? Math.round(bodyCount * target.length / body.length * 10000) / 100 : null, checks };
}
