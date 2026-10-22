import { buildContext } from './dom';
import { checks, metaById, metas } from './registry';
import type {
  Category,
  CategoryScore,
  Check,
  PageInput,
  Report,
  Scores,
  ScoredResult,
} from './types';

export type * from './types';
export { checks, metas, metaById };
export { buildContext } from './dom';

/**
 * Scoring formula (identical to Bixisoft\Core\Score\Scorer in PHP):
 * pass earns the weight, warn earns half, fail earns nothing, info is not scored.
 * Category score = round(100 * earned / possible) or null when nothing was scored.
 * Overall = round(mean of non-null category scores) or 0.
 */
export function computeScores(results: ReadonlyArray<Pick<ScoredResult, 'category' | 'weight' | 'status'>>): Scores {
  const buckets = new Map<Category, CategoryScore & { earned: number; possible: number }>();

  for (const result of results) {
    let bucket = buckets.get(result.category);
    if (!bucket) {
      bucket = { score: null, passed: 0, warned: 0, failed: 0, info: 0, earned: 0, possible: 0 };
      buckets.set(result.category, bucket);
    }
    switch (result.status) {
      case 'pass':
        bucket.earned += result.weight;
        bucket.possible += result.weight;
        bucket.passed += 1;
        break;
      case 'warn':
        bucket.earned += result.weight / 2;
        bucket.possible += result.weight;
        bucket.warned += 1;
        break;
      case 'fail':
        bucket.possible += result.weight;
        bucket.failed += 1;
        break;
      default:
        bucket.info += 1;
    }
  }

  const categories: Scores['categories'] = {};
  let sum = 0;
  let scored = 0;

  for (const [category, bucket] of buckets) {
    const score = bucket.possible > 0 ? Math.round((100 * bucket.earned) / bucket.possible) : null;
    if (score !== null) {
      sum += score;
      scored += 1;
    }
    categories[category] = {
      score,
      passed: bucket.passed,
      warned: bucket.warned,
      failed: bucket.failed,
      info: bucket.info,
    };
  }

  return { overall: scored > 0 ? Math.round(sum / scored) : 0, categories };
}

/** Run every check against one page and score the outcome. A throwing check becomes an info result. */
export function analyze(input: PageInput, selected: ReadonlyArray<Check> = checks): Report {
  const ctx = buildContext(input);

  const results: ScoredResult[] = selected.map((check) => {
    const meta = metaById.get(check.id);
    if (!meta) throw new Error(`No catalogue entry for check "${check.id}".`);
    try {
      return { ...meta, ...check.run(ctx) };
    } catch (error) {
      const message = error instanceof Error ? error.message : String(error);
      return { ...meta, id: check.id, status: 'info', message: `Check could not run: ${message}` };
    }
  });

  return {
    url: input.url,
    generatedAt: new Date().toISOString(),
    scores: computeScores(results),
    results,
  };
}
