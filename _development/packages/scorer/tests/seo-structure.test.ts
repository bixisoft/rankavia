import { describe, expect, it } from 'vitest';
import { buildContext } from '../src/dom';
import { seoChecks } from '../src/checks/seo';

function check(id: string, html: string) {
  const item = seoChecks.find((candidate) => candidate.id === id)!;
  return item.run(buildContext({ url: 'https://example.test/', html }));
}

describe('SEO structure checks', () => {
  it('normalizes Unicode whitespace consistently with PHP source checks', () => {
    expect(check('seo.description.unique', '<meta name="&nbsp;DESCRIPTION&nbsp;"><meta name="description">').details).toEqual({ count: 2 });
    expect(check('seo.canonical.unique', '<link rel="&nbsp;CANONICAL&nbsp;"><link rel="canonical">').details).toEqual({ count: 2 });
    const links = check('seo.links.named', '<a href="/">&nbsp;</a><a href="/" aria-label="&nbsp;"></a><a href="/"><img alt="&nbsp;"></a>');
    expect(links.status).toBe('warn');
    expect(links.details).toEqual({ total: 3, missing: 3 });
  });
  it.each([
    ['seo.title.unique', '<title>One</title>'],
    ['seo.description.unique', '<meta name="description" content="One">'],
    ['seo.canonical.unique', '<link rel="canonical" href="/one">'],
  ])('%s reports absent, one and duplicate declarations', (id, declaration) => {
    expect(check(id, '').status).toBe('info');
    expect(check(id, '').details).toEqual({ count: 0 });
    expect(check(id, declaration).status).toBe('pass');
    expect(check(id, declaration).details).toEqual({ count: 1 });
    expect(check(id, declaration + declaration).status).toBe('warn');
    expect(check(id, declaration + declaration).details).toEqual({ count: 2 });
  });

  it('accepts text, aria-label and image alt while counting decorative empty links', () => {
    expect(check('seo.links.named', '<a>No href</a>').status).toBe('info');
    const result = check('seo.links.named', '<a href="/text"><span>Text</span></a><a href="/aria" aria-label="Menu"></a><a href="/image"><img alt="Home"></a><a href="/empty"><img alt=""></a><a href="/blank"> </a><a>No href</a>');
    expect(result.status).toBe('warn');
    expect(result.details).toEqual({ total: 5, missing: 2 });
    expect(check('seo.links.named', '<a href="/"><span>Named</span></a>').status).toBe('pass');
  });
});
