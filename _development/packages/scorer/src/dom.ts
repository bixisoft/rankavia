import { NodeType, TextNode, parse, type HTMLElement } from 'node-html-parser';
import type { CheckResult, Heading, LinkTag, MetaTag, PageContext, PageInput, Status } from './types';

/**
 * Parser options shared by buildContext() and tests/bench.ts.
 *
 * node-html-parser matches tag selectors and attribute names case-insensitively without the
 * `lowerCaseTagName` option, which lower-cases the whole document once per <script>, <style>,
 * <pre> and <noscript> and cost ten times the parse on real pages, so it is deliberately not
 * used. The one thing given up is a closing tag whose case differs from its opening tag
 * (<SCRIPT>…</script>), which the parser then treats as unclosed.
 */
export const PARSE_OPTIONS = { comment: false } as const;

/** Elements whose content is never rendered as text; excluded from bodyText. */
const TEXTLESS = new Set(['script', 'style', 'noscript', 'template']);

/** Block-level elements. A space is inserted around their text so adjacent blocks do not merge into one word. */
const BLOCK = new Set([
  'address', 'article', 'aside', 'blockquote', 'br', 'dd', 'details', 'dialog', 'div', 'dl', 'dt',
  'fieldset', 'figcaption', 'figure', 'footer', 'form', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'header',
  'hgroup', 'hr', 'li', 'main', 'nav', 'ol', 'p', 'pre', 'section', 'summary', 'table', 'td', 'th',
  'tr', 'ul',
]);

const NO_ELEMENTS: readonly HTMLElement[] = [];

/** The part of PageContext that indexDocument() fills in one pass. */
export interface DomIndex {
  title: string;
  lang: string;
  metas: MetaTag[];
  links: LinkTag[];
  headings: Heading[];
  images: HTMLElement[];
  headScripts: HTMLElement[];
  elements: Map<string, HTMLElement[]>;
  jsonLd: unknown[];
  jsonLdErrors: number;
}

/** Parse HTML and index what checks need in a single pass over the tree. */
export function buildContext(input: PageInput): PageContext {
  const doc = parse(input.html, PARSE_OPTIONS);
  const index = indexDocument(doc);

  const headers: Record<string, string> = {};
  for (const [name, value] of Object.entries(input.headers ?? {})) {
    headers[name.toLowerCase()] = value;
  }

  let bodyText: string | undefined;

  return {
    ...input,
    headers,
    doc,
    ...index,
    get bodyText(): string {
      bodyText ??= normalise(decodeEntities(visibleRawText(index.elements.get('body')?.[0] ?? doc)));
      return bodyText;
    },
  };
}

/** One iterative pre-order walk that fills every index at once. Exported for tests/bench.ts only. */
export function indexDocument(doc: HTMLElement): DomIndex {
  const index: DomIndex = {
    title: '',
    lang: '',
    metas: [],
    links: [],
    headings: [],
    images: [],
    headScripts: [],
    elements: new Map(),
    jsonLd: [],
    jsonLdErrors: 0,
  };

  // Children are pushed in reverse so document order is kept.
  const stack: Array<[HTMLElement, boolean]> = [[doc, false]];
  while (stack.length > 0) {
    const item = stack.pop();
    if (!item) break;
    const [el, inHead] = item;
    const tag = el.rawTagName ? el.rawTagName.toLowerCase() : '';
    const insideHead = inHead || tag === 'head';

    if (tag !== '') {
      const siblings = index.elements.get(tag);
      if (siblings) siblings.push(el);
      else index.elements.set(tag, [el]);
    }

    switch (tag) {
      case 'title':
        if (index.title === '') index.title = textOf(el);
        break;
      case 'html':
        if (index.lang === '') index.lang = (attr(el, 'lang') ?? '').trim();
        break;
      case 'meta':
        index.metas.push({
          name: token(attr(el, 'name')),
          property: token(attr(el, 'property')),
          httpEquiv: token(attr(el, 'http-equiv')),
          content: (attr(el, 'content') ?? '').trim(),
        });
        break;
      case 'link':
        index.links.push({
          rel: (attr(el, 'rel') ?? '').toLowerCase().split(/\s+/).filter(Boolean),
          href: (attr(el, 'href') ?? '').trim(),
          el,
        });
        break;
      case 'h1':
      case 'h2':
      case 'h3':
      case 'h4':
      case 'h5':
      case 'h6':
        index.headings.push({ level: Number(tag.charAt(1)), text: textOf(el) });
        break;
      case 'img':
        index.images.push(el);
        break;
      case 'script':
        if (insideHead) index.headScripts.push(el);
        if ((attr(el, 'type') ?? '').trim().toLowerCase() === 'application/ld+json') {
          try {
            index.jsonLd.push(JSON.parse(el.rawText));
          } catch {
            index.jsonLdErrors += 1;
          }
        }
        break;
      default:
        break;
    }

    const children = el.childNodes;
    for (let i = children.length - 1; i >= 0; i -= 1) {
      const child = children[i];
      if (child && child.nodeType === NodeType.ELEMENT_NODE) stack.push([child as HTMLElement, insideHead]);
    }
  }

  return index;
}

/** Raw (still entity-encoded) text under `root`, skipping TEXTLESS subtrees, with spaces around block elements. */
function visibleRawText(root: HTMLElement): string {
  const parts: string[] = [];
  const visit = (parent: HTMLElement): void => {
    for (const child of parent.childNodes) {
      if (child.nodeType === NodeType.TEXT_NODE) {
        parts.push(child.rawText);
      } else if (child.nodeType === NodeType.ELEMENT_NODE) {
        const el = child as HTMLElement;
        const tag = el.rawTagName.toLowerCase();
        if (TEXTLESS.has(tag)) continue;
        const block = BLOCK.has(tag);
        if (block) parts.push(' ');
        visit(el);
        if (block) parts.push(' ');
      }
    }
  };
  visit(root);
  return parts.join('');
}

/**
 * Decode HTML entities with the parser's own decoder, so there is no direct dependency on `he`.
 * Entities always start with an ampersand, so text without one is returned as is.
 */
function decodeEntities(raw: string): string {
  return raw.includes('&') ? new TextNode(raw).text : raw;
}

/**
 * Case-insensitive attribute lookup: undefined when the attribute is absent, '' when it has no value.
 *
 * Reads the parser's cached raw attribute map and decodes entities only in the value asked for.
 * The parser's getAttribute() decodes every attribute of the element on first use, which on an
 * image-heavy page (long srcset values) was the most expensive thing a check could do, and its
 * `attributes` getter re-decodes on every call; neither is used in checks.
 */
export function attr(el: HTMLElement, name: string): string | undefined {
  // Values are null, not '', for attributes written without a value (<script async>).
  const raw = el.rawAttributes as Record<string, string | null>;
  let value: string | null | undefined = Object.hasOwn(raw, name) ? raw[name] : undefined;
  if (value === undefined) {
    const wanted = name.toLowerCase();
    for (const key in raw) {
      if (key.toLowerCase() === wanted) {
        value = raw[key];
        break;
      }
    }
    if (value === undefined) return undefined;
  }
  return value === null ? '' : decodeEntities(value);
}

/** Content of the first <meta> whose name/property/http-equiv matches (case-insensitive). */
export function metaContent(
  ctx: Pick<PageContext, 'metas'>,
  attribute: 'name' | 'property' | 'http-equiv',
  value: string,
): string | undefined {
  const wanted = value.toLowerCase();
  const key = attribute === 'http-equiv' ? 'httpEquiv' : attribute;
  for (const meta of ctx.metas) {
    if (meta[key] === wanted) return meta.content;
  }
  return undefined;
}

/** First <link> whose rel contains the token (case-insensitive). */
export function linkByRel(ctx: Pick<PageContext, 'links'>, rel: string): LinkTag | undefined {
  const wanted = rel.toLowerCase();
  return ctx.links.find((link) => link.rel.includes(wanted));
}

/** Every element with the tag name (case-insensitive), in document order. */
export function byTag(ctx: Pick<PageContext, 'elements'>, tag: string): readonly HTMLElement[] {
  return ctx.elements.get(tag.toLowerCase()) ?? NO_ELEMENTS;
}

/** Whitespace-normalised, entity-decoded text content of one element. */
export function textOf(el: HTMLElement | null | undefined): string {
  return el ? normalise(decodeEntities(el.rawText)) : '';
}

export function truncate(value: string, max = 80): string {
  return value.length > max ? `${value.slice(0, max - 1)}…` : value;
}

/** Depth-first walk over decoded JSON-LD; true as soon as the predicate matches a key/value pair. */
export function findInJson(
  value: unknown,
  predicate: (key: string | null, value: unknown) => boolean,
  depth = 0,
): boolean {
  if (depth > 12) return false;
  if (Array.isArray(value)) {
    return value.some((item) => findInJson(item, predicate, depth + 1));
  }
  if (value !== null && typeof value === 'object') {
    for (const [key, child] of Object.entries(value as Record<string, unknown>)) {
      if (predicate(key, child)) return true;
      if (findInJson(child, predicate, depth + 1)) return true;
    }
  }
  return false;
}

export function result(
  id: string,
  status: Status,
  message: string,
  details?: Record<string, unknown>,
): CheckResult {
  return details ? { id, status, message, details } : { id, status, message };
}

export const pass = (id: string, message: string, details?: Record<string, unknown>) =>
  result(id, 'pass', message, details);
export const warn = (id: string, message: string, details?: Record<string, unknown>) =>
  result(id, 'warn', message, details);
export const fail = (id: string, message: string, details?: Record<string, unknown>) =>
  result(id, 'fail', message, details);
export const info = (id: string, message: string, details?: Record<string, unknown>) =>
  result(id, 'info', message, details);

function normalise(value: string): string {
  return value.replace(/\s+/g, ' ').trim();
}

/** Trimmed, lower-cased attribute value, or undefined when the attribute is absent. */
function token(value: string | undefined): string | undefined {
  return value === undefined ? undefined : value.trim().toLowerCase();
}
