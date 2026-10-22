<?php
/**
 * Catalogue of AI crawlers and the three preset policies built from it.
 *
 * @package Rankavia
 */

declare( strict_types=1 );

namespace Bixisoft\Rankavia\Crawlers;

/**
 * The crawlers Rankavia can allow or block. Ids double as keys of the ai_crawler_policy setting.
 */
final class Bots {

	public const PURPOSE_SEARCH   = 'search';
	public const PURPOSE_TRAINING = 'training';
	public const PURPOSE_USER     = 'user';

	/**
	 * Policy value for a bot that gets no rule.
	 */
	public const POLICY_NONE = '';

	/**
	 * Policy value that writes "Allow: /".
	 */
	public const POLICY_ALLOW = 'allow';

	/**
	 * Policy value that writes "Disallow: /".
	 */
	public const POLICY_BLOCK = 'block';

	/**
	 * Blocking this one removes the site from Bing search and Copilot, so the block-all preset leaves it alone.
	 */
	public const BINGBOT = 'bingbot';

	/**
	 * Untranslated catalogue: id, robots.txt user-agent token, vendor and purpose.
	 */
	private const CATALOGUE = array(
		array(
			'id'      => 'gptbot',
			'token'   => 'GPTBot',
			'vendor'  => 'OpenAI',
			'purpose' => self::PURPOSE_TRAINING,
		),
		array(
			'id'      => 'oai_searchbot',
			'token'   => 'OAI-SearchBot',
			'vendor'  => 'OpenAI',
			'purpose' => self::PURPOSE_SEARCH,
		),
		array(
			'id'      => 'chatgpt_user',
			'token'   => 'ChatGPT-User',
			'vendor'  => 'OpenAI',
			'purpose' => self::PURPOSE_USER,
		),
		array(
			'id'      => 'claudebot',
			'token'   => 'ClaudeBot',
			'vendor'  => 'Anthropic',
			'purpose' => self::PURPOSE_TRAINING,
		),
		array(
			'id'      => 'claude_searchbot',
			'token'   => 'Claude-SearchBot',
			'vendor'  => 'Anthropic',
			'purpose' => self::PURPOSE_SEARCH,
		),
		array(
			'id'      => 'claude_user',
			'token'   => 'Claude-User',
			'vendor'  => 'Anthropic',
			'purpose' => self::PURPOSE_USER,
		),
		array(
			'id'      => 'perplexitybot',
			'token'   => 'PerplexityBot',
			'vendor'  => 'Perplexity',
			'purpose' => self::PURPOSE_SEARCH,
		),
		array(
			'id'      => 'perplexity_user',
			'token'   => 'Perplexity-User',
			'vendor'  => 'Perplexity',
			'purpose' => self::PURPOSE_USER,
		),
		array(
			'id'      => 'google_extended',
			'token'   => 'Google-Extended',
			'vendor'  => 'Google',
			'purpose' => self::PURPOSE_TRAINING,
		),
		array(
			'id'      => 'applebot_extended',
			'token'   => 'Applebot-Extended',
			'vendor'  => 'Apple',
			'purpose' => self::PURPOSE_TRAINING,
		),
		array(
			'id'      => 'bingbot',
			'token'   => 'Bingbot',
			'vendor'  => 'Microsoft',
			'purpose' => self::PURPOSE_SEARCH,
		),
		array(
			'id'      => 'ccbot',
			'token'   => 'CCBot',
			'vendor'  => 'Common Crawl',
			'purpose' => self::PURPOSE_TRAINING,
		),
		array(
			'id'      => 'bytespider',
			'token'   => 'Bytespider',
			'vendor'  => 'ByteDance',
			'purpose' => self::PURPOSE_TRAINING,
		),
		array(
			'id'      => 'meta_externalagent',
			'token'   => 'Meta-ExternalAgent',
			'vendor'  => 'Meta',
			'purpose' => self::PURPOSE_TRAINING,
		),
	);

	/**
	 * Every bot with its translated label and note. Calls translation functions, so run it on or after init.
	 *
	 * @return array<int, array{id: string, token: string, vendor: string, purpose: string, label: string, note: string}>
	 */
	public static function all(): array {
		$purposes = array(
			self::PURPOSE_SEARCH   => __( 'search', 'rankavia' ),
			self::PURPOSE_TRAINING => __( 'training', 'rankavia' ),
			self::PURPOSE_USER     => __( 'user', 'rankavia' ),
		);
		$notes    = array(
			'oai_searchbot'     => __( 'ChatGPT search results', 'rankavia' ),
			'chatgpt_user'      => __( 'Fetches pages a ChatGPT user asks about', 'rankavia' ),
			'google_extended'   => __( 'A control token for Gemini, not a separate crawler; Google Search is unaffected', 'rankavia' ),
			'applebot_extended' => __( 'Control token; Applebot search is unaffected', 'rankavia' ),
			'bingbot'           => __( 'Blocking it removes the site from Bing search and Copilot', 'rankavia' ),
		);

		$bots = array();
		foreach ( self::CATALOGUE as $bot ) {
			$bots[] = array(
				'id'      => $bot['id'],
				'token'   => $bot['token'],
				'vendor'  => $bot['vendor'],
				'purpose' => $bot['purpose'],
				'label'   => sprintf( '%1$s (%2$s, %3$s)', $bot['token'], $bot['vendor'], $purposes[ $bot['purpose'] ] ),
				'note'    => isset( $notes[ $bot['id'] ] ) ? $notes[ $bot['id'] ] : '',
			);
		}
		return $bots;
	}

	/**
	 * Bot id to robots.txt user-agent token, in catalogue order. Needs no WordPress functions.
	 *
	 * @return array<string, string>
	 */
	public static function tokens(): array {
		$tokens = array();
		foreach ( self::CATALOGUE as $bot ) {
			$tokens[ $bot['id'] ] = $bot['token'];
		}
		return $tokens;
	}

	/**
	 * Bot ids in catalogue order.
	 *
	 * @return array<int, string>
	 */
	public static function ids(): array {
		return array_keys( self::tokens() );
	}

	/**
	 * Preset "Allow all": no rule for any bot.
	 *
	 * @return array<string, string>
	 */
	public static function preset_allow_all(): array {
		return self::policy_for( array() );
	}

	/**
	 * Preset "Search yes, training no": search and user bots allowed, training bots blocked.
	 *
	 * @return array<string, string>
	 */
	public static function preset_search_not_training(): array {
		$policy = array();
		foreach ( self::CATALOGUE as $bot ) {
			$policy[ $bot['id'] ] = self::PURPOSE_TRAINING === $bot['purpose'] ? self::POLICY_BLOCK : self::POLICY_ALLOW;
		}
		return $policy;
	}

	/**
	 * Preset "Block all AI crawlers": every bot blocked except Bingbot, which gets no rule.
	 *
	 * @return array<string, string>
	 */
	public static function preset_block_all(): array {
		$policy = array();
		foreach ( self::ids() as $id ) {
			$policy[ $id ] = self::BINGBOT === $id ? self::POLICY_NONE : self::POLICY_BLOCK;
		}
		return $policy;
	}

	/**
	 * A full policy map where every bot has the given value, or no rule when not listed.
	 *
	 * @param array<string, string> $values Bot id to policy value.
	 * @return array<string, string>
	 */
	private static function policy_for( array $values ): array {
		$policy = array();
		foreach ( self::ids() as $id ) {
			$policy[ $id ] = isset( $values[ $id ] ) ? $values[ $id ] : self::POLICY_NONE;
		}
		return $policy;
	}
}
