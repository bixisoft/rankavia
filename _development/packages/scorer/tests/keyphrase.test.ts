import { describe, expect, it } from 'vitest';
import { analyzeKeyphrase, normalizeKeyphrase } from '../src';

const page = (html: string, url = 'https://example.com/blue-shoes') => ({ html, url });

describe('keyphrase placement', () => {
  it('counts whole words in visible content, including punctuation, without script/style/template', () => {
    const result = analyzeKeyphrase(page('<head><title>Blue shoes</title><meta name="description" content="Blue shoes"></head><body><h1>Blue shoes</h1><p>Blue shoes, blue shoes!</p><script>blue shoes</script><style>blue shoes</style><template>blue shoes</template></body>'), 'BLUE shoes');
    expect(result.bodyCount).toBe(3);
    expect(result.checks.find(c => c.id === 'url')?.found).toBe('Exact phrase found.');
    expect(result.matched).toBe(6);
    expect(JSON.stringify(result)).not.toContain('<p>');
  });
  it('reports measured absent fields and nonmatches as warnings', () => {
    const missing = analyzeKeyphrase(page('<p>unrelated copy</p>', 'https://example.com/'), 'blue shoes');
    expect(missing.bodyCount).toBe(0);
    expect(missing.checks.find(c => c.id === 'title')?.status).toBe('warn');
    expect(missing.checks.find(c => c.id === 'body')?.status).toBe('warn');
  });
  it('does not count overlapping phrases twice in density', () => {
    const result = analyzeKeyphrase(page('<p>a a a</p>'), 'a a');
    expect(result.bodyCount).toBe(1);
    expect(result.density).toBeLessThanOrEqual(100);
  });
  it('matches Arabic text case-insensitively and does not match substrings', () => {
    const arabic = analyzeKeyphrase(page('<p>الكتاب، الكتابة الكتاب.</p>'), 'الكتاب');
    expect(arabic.bodyCount).toBe(2);
    expect(analyzeKeyphrase(page('<p>notebook</p>'), 'book').bodyCount).toBe(0);
  });
  it.each(['', '   ', '!!!', 'a'.repeat(101), Array(11).fill('word').join(' '), '<script>'])('rejects invalid phrase %s', phrase => {
    expect(() => normalizeKeyphrase(phrase)).toThrow();
  });
  it('rejects nonstrings and controls', () => {
    expect(() => normalizeKeyphrase(42)).toThrow();
    expect(() => normalizeKeyphrase('foo\u0000bar')).toThrow();
  });
});
