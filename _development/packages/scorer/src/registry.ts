import catalogue from './checks.json';
import type { Check, CheckMeta } from './types';
import { seoChecks } from './checks/seo';
import { aeoChecks } from './checks/aeo';
import { geoChecks } from './checks/geo';
import { speedChecks } from './checks/speed';

/** Catalogue entries (metadata) shared with the PHP implementation. */
export const metas: CheckMeta[] = catalogue as CheckMeta[];

export const metaById: ReadonlyMap<string, CheckMeta> = new Map(metas.map((meta) => [meta.id, meta]));

/** Every implemented check, in catalogue order. */
export const checks: Check[] = [...seoChecks, ...aeoChecks, ...geoChecks, ...speedChecks];

for (const check of checks) {
  if (!metaById.has(check.id)) {
    throw new Error(`Check "${check.id}" is implemented but missing from checks.json.`);
  }
}
