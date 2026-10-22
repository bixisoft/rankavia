import type { HTMLElement } from 'node-html-parser';

export type Category = 'seo' | 'aeo' | 'geo' | 'speed';
export type Severity = 'critical' | 'high' | 'medium' | 'low';
export type Status = 'pass' | 'warn' | 'fail' | 'info';

/** Catalogue entry from checks.json. Shared verbatim with the PHP side. */
export interface CheckMeta {
  id: string;
  category: Category;
  title: string;
  severity: Severity;
  weight: number;
  description: string;
}

/** Outcome of one check. */
export interface CheckResult {
  id: string;
  status: Status;
  message: string;
  details?: Record<string, unknown>;
}

export type ScoredResult = CheckMeta & CheckResult;

/** What callers hand to analyze(). */
export interface PageInput {
  url: string;
  html: string;
  /** Lower-cased response header names to values. */
  headers?: Record<string, string>;
}

/** One <meta> tag; attribute names and values are trimmed and lower-cased, content is trimmed. */
export interface MetaTag {
  name?: string;
  property?: string;
  httpEquiv?: string;
  content: string;
}

/** One <link> tag with its rel tokens lower-cased. */
export interface LinkTag {
  rel: string[];
  href: string;
  el: HTMLElement;
}

export interface Heading {
  /** 1 to 6. */
  level: number;
  /** Whitespace-normalised text. */
  text: string;
}

/**
 * Parsed page handed to each check. Everything checks need is collected in one
 * pass over the DOM by buildContext() so that checks never re-walk the tree;
 * the Cloudflare Workers free plan allows 10 ms of CPU per request.
 */
export interface PageContext extends PageInput {
  doc: HTMLElement;
  /** Text of the first <title>, whitespace-normalised. */
  title: string;
  /** The <html lang> attribute, trimmed. */
  lang: string;
  metas: MetaTag[];
  links: LinkTag[];
  /** Every h1–h6 in document order. */
  headings: Heading[];
  /** Every <img> in document order. */
  images: HTMLElement[];
  /** <script> elements inside <head>. */
  headScripts: HTMLElement[];
  /**
   * Every element for each lower-cased tag name, in document order, so a new check can look at
   * any tag without another walk or a new field here. Read it through byTag().
   */
  elements: ReadonlyMap<string, readonly HTMLElement[]>;
  /**
   * Visible text of <body> (of the whole document when there is no <body>): entity-decoded,
   * whitespace-normalised, without script, style, noscript and template content. Computed on
   * first access and cached, so checks that do not need it cost nothing.
   */
  readonly bodyText: string;
  /** Decoded JSON-LD blocks. */
  jsonLd: unknown[];
  /** JSON-LD blocks that failed to parse. */
  jsonLdErrors: number;
}

export interface Check {
  id: string;
  run(ctx: PageContext): CheckResult;
}

export interface CategoryScore {
  /** 0–100, or null when no scored (non-info) result exists for the category. */
  score: number | null;
  passed: number;
  warned: number;
  failed: number;
  info: number;
}

export interface Scores {
  overall: number;
  categories: Partial<Record<Category, CategoryScore>>;
}

export interface Report {
  url: string;
  generatedAt: string;
  scores: Scores;
  results: ScoredResult[];
}
