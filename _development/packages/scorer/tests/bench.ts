/**
 * Manual benchmark for the check engine. Not part of the test suite and not run in CI.
 *
 *   npm run bench -w packages/scorer                              # synthetic page only
 *   npm run bench -w packages/scorer -- <directory-with-html-files> [runs]
 *   npx tsx packages/scorer/tests/bench.ts ./fixtures 30
 *
 * A synthetic 400-section page is always measured, so the numbers can be compared between
 * machines and commits without saved fixtures; every *.html file in the directory, if one is
 * given, is measured next to it. For each page it reports the median wall-clock time, in
 * milliseconds, of each phase of analyze():
 *
 *   parse     node-html-parser alone, with the options buildContext() uses
 *   index     indexDocument(): the one-pass DOM index, on a freshly parsed tree
 *   checks    running every registered check against a fresh context
 *   analyze   the whole analyze() call (parse + index + checks + scoring)
 *   bodyText  reading the lazy ctx.bodyText once, on a fresh context
 *
 * The code under test is synchronous, so on an otherwise idle machine wall-clock time is a
 * close proxy for the CPU time Cloudflare Workers bills. Garbage collection is included, as
 * it is in the Worker. The free plan allows 10 ms of CPU per request; apps/scanner caps the
 * input HTML at 1 MB so that typical pages stay inside the limit. Re-run this after adding
 * checks and compare with the numbers in docs/engineering/site-audit-tool.md, section 7.
 */
import { readdirSync, readFileSync, statSync } from 'node:fs';
import { join, resolve } from 'node:path';
import { performance } from 'node:perf_hooks';
import { parse } from 'node-html-parser';
import { analyze, buildContext, checks } from '../src/index';
import { PARSE_OPTIONS, indexDocument } from '../src/dom';
import type { PageContext } from '../src/types';

const WARMUP = 5;

interface Row {
  name: string;
  kb: number;
  parse: number;
  index: number;
  checks: number;
  analyze: number;
  bodyText: number;
}

/** A content-heavy page with one image per section; 400 sections is about 290 KB. */
function syntheticPage(sections: number): string {
  const section = (i: number) =>
    `<section><h2>Why does section ${i} matter?</h2><p>${'Lorem ipsum dolor sit amet, consectetur adipiscing elit. '.repeat(
      8,
    )}</p><ul><li><a href="/p/${i}">Link ${i}</a></li><li><a href="/q/${i}">Another ${i}</a></li></ul><img src="/img/${i}.jpg" alt="Figure ${i}" width="640" height="360" loading="lazy"><div class="c"><span>${i}</span><em>x</em><strong>y</strong></div></section>`;
  const body = Array.from({ length: sections }, (_v, i) => section(i)).join('\n');
  return `<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>A synthetic page for the scorer benchmark</title><meta name="description" content="A synthetic page with ${sections} sections used to measure parse and analyze time in the scorer package.">
<link rel="canonical" href="https://example.com/bench/"><meta property="og:title" content="Synthetic"><meta property="og:description" content="Bench"><meta property="og:image" content="https://example.com/og.jpg"><script src="/app.js" defer></script><script type="application/ld+json">{"@context":"https://schema.org","@type":"Article","dateModified":"2026-10-01","author":{"@type":"Person","name":"Jane"}}</script></head><body><main><article><h1>Synthetic page</h1>${body}</article></main></body></html>`;
}

function median(samples: number[]): number {
  const sorted = [...samples].sort((a, b) => a - b);
  const mid = Math.floor(sorted.length / 2);
  if (sorted.length === 0) return 0;
  return sorted.length % 2 === 1 ? (sorted[mid] as number) : ((sorted[mid - 1] as number) + (sorted[mid] as number)) / 2;
}

/**
 * Median wall-clock milliseconds of `run` over `runs` timed runs after a warm-up.
 * `setup` runs before each timed run, outside the timer, so a phase can be measured on fresh input.
 */
function time<T>(runs: number, setup: () => T, run: (input: T) => unknown): number {
  for (let i = 0; i < WARMUP; i += 1) run(setup());
  const samples: number[] = [];
  for (let i = 0; i < runs; i += 1) {
    const input = setup();
    const start = performance.now();
    run(input);
    samples.push(performance.now() - start);
  }
  return median(samples);
}

function runChecks(ctx: PageContext): number {
  let count = 0;
  for (const check of checks) count += check.run(ctx).status.length;
  return count;
}

function bench(name: string, html: string, runs: number): Row {
  const input = { url: `https://example.com/${name}`, html };
  return {
    name,
    kb: Math.round(Buffer.byteLength(html) / 1024),
    parse: time(runs, () => html, (h) => parse(h, PARSE_OPTIONS)),
    index: time(runs, () => parse(html, PARSE_OPTIONS), (doc) => indexDocument(doc)),
    checks: time(runs, () => buildContext(input), runChecks),
    analyze: time(runs, () => input, analyze),
    bodyText: time(runs, () => buildContext(input), (ctx) => ctx.bodyText.length),
  };
}

function format(rows: Row[]): string {
  const header = ['page', 'size', 'parse', 'index', 'checks', 'analyze', 'bodyText'];
  const lines = rows.map((row) => [
    row.name,
    `${row.kb} KB`,
    row.parse.toFixed(1),
    row.index.toFixed(1),
    row.checks.toFixed(1),
    row.analyze.toFixed(1),
    row.bodyText.toFixed(1),
  ]);
  const widths = header.map((h, i) => Math.max(h.length, ...lines.map((line) => (line[i] as string).length)));
  const pad = (cells: string[]) =>
    cells.map((cell, i) => (i === 0 ? cell.padEnd(widths[i] as number) : cell.padStart(widths[i] as number))).join('  ');
  return [pad(header), ...lines.map(pad)].join('\n');
}

function main(): void {
  const runs = Math.max(1, Number(process.argv[3] ?? 30) || 30);
  const pages: Array<[string, string]> = [['synthetic (400 sections)', syntheticPage(400)]];

  const dirArg = process.argv[2];
  if (dirArg) {
    const dir = resolve(dirArg);
    const files = readdirSync(dir)
      .filter((name) => /\.html?$/i.test(name) && statSync(join(dir, name)).isFile())
      .sort();
    if (files.length === 0) {
      console.error(`No .html files found in ${dir}`);
      process.exit(1);
    }
    for (const file of files) pages.push([file, readFileSync(join(dir, file), 'utf8')]);
  }

  console.log(`node ${process.version}, ${checks.length} checks, median of ${runs} runs, milliseconds\n`);
  console.log(format(pages.map(([name, html]) => bench(name, html, runs))));
}

main();
