<?php
/**
 * Turns check results into category and overall scores.
 *
 * @package Bixisoft\Rankavia\Vendor\Core
 */

declare( strict_types=1 );

namespace Bixisoft\Rankavia\Vendor\Core\Score;

/**
 * Scoring formula (identical to packages/scorer in TypeScript):
 *
 * - Each scored result contributes its catalogue weight to the category's possible points.
 * - pass earns the full weight, warn earns half, fail earns nothing, info is not scored.
 * - Category score = round( 100 * earned / possible ); null when nothing was scored.
 * - Overall = round( mean of category scores that are not null ); 0 when none.
 */
final class Scorer {

	/**
	 * Catalogue providing category and weight per check id.
	 *
	 * @var Check_Registry
	 */
	private Check_Registry $registry;

	/**
	 * Constructor.
	 *
	 * @param Check_Registry $registry Check catalogue.
	 */
	public function __construct( Check_Registry $registry ) {
		$this->registry = $registry;
	}

	/**
	 * Score a set of results. Results for unknown check ids are ignored.
	 *
	 * @param Result[] $results Results to score.
	 * @return array{overall: int, categories: array<string, array{score: int|null, passed: int, warned: int, failed: int, info: int}>}
	 */
	public function score( array $results ): array {
		/**
		 * Accumulators per category.
		 *
		 * @var array<string, array{earned: float, possible: int, passed: int, warned: int, failed: int, info: int}> $buckets
		 */
		$buckets = array();

		foreach ( $results as $result ) {
			$meta = $this->registry->get( $result->id() );
			if ( null === $meta ) {
				continue;
			}

			$category = (string) $meta['category'];
			$weight   = (int) $meta['weight'];

			if ( ! isset( $buckets[ $category ] ) ) {
				$buckets[ $category ] = array(
					'earned'   => 0.0,
					'possible' => 0,
					'passed'   => 0,
					'warned'   => 0,
					'failed'   => 0,
					'info'     => 0,
				);
			}

			switch ( $result->status() ) {
				case Result::PASS:
					$buckets[ $category ]['earned']   += $weight;
					$buckets[ $category ]['possible'] += $weight;
					++$buckets[ $category ]['passed'];
					break;
				case Result::WARN:
					$buckets[ $category ]['earned']   += $weight / 2;
					$buckets[ $category ]['possible'] += $weight;
					++$buckets[ $category ]['warned'];
					break;
				case Result::FAIL:
					$buckets[ $category ]['possible'] += $weight;
					++$buckets[ $category ]['failed'];
					break;
				default:
					++$buckets[ $category ]['info'];
					break;
			}
		}

		$categories = array();
		$sum        = 0;
		$scored     = 0;

		foreach ( $buckets as $category => $bucket ) {
			$score = null;
			if ( $bucket['possible'] > 0 ) {
				$score = (int) round( 100 * $bucket['earned'] / $bucket['possible'] );
				$sum  += $score;
				++$scored;
			}
			$categories[ $category ] = array(
				'score'  => $score,
				'passed' => $bucket['passed'],
				'warned' => $bucket['warned'],
				'failed' => $bucket['failed'],
				'info'   => $bucket['info'],
			);
		}

		return array(
			'overall'    => $scored > 0 ? (int) round( $sum / $scored ) : 0,
			'categories' => $categories,
		);
	}
}
