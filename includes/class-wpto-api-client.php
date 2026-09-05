<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Talks to the AI provider through WordPress core's AI Client
 * (wp_ai_client_prompt(), WordPress 7.0+). The site owner picks and
 * configures the provider once under Settings; this plugin never handles
 * credentials or contacts a provider directly.
 */
class WPTO_Api_Client {

	const VALID_TYPES = array( 'near_duplicate', 'semantic_overlap', 'low_usage_merge' );

	const MAX_TOKENS = 4096;

	/**
	 * Analyze a batch of tags via the configured AI provider.
	 *
	 * @param array $tags Array of ['id' => int, 'name' => string, 'count' => int].
	 * @return array|WP_Error List of validated suggestion arrays, or WP_Error on failure.
	 */
	public function analyze_batch( array $tags ) {
		if ( ! function_exists( 'wp_ai_client_prompt' ) ) {
			return new WP_Error( 'wpto_no_ai_client', __( 'The AI Analysis feature requires WordPress 7.0 or newer, which bundles the core AI Client.', 'tag-loom' ) );
		}

		$valid_ids = wp_list_pluck( $tags, 'id' );

		$builder = wp_ai_client_prompt( wp_json_encode( $tags ) )
			->using_system_instruction( $this->build_system_prompt() )
			->using_max_tokens( self::MAX_TOKENS )
			->using_temperature( 0.2 );

		$preferred = WPTO_Settings::get_model_preference();
		if ( ! empty( $preferred ) ) {
			$builder->using_model_preference( ...$preferred );
		}

		if ( ! $builder->is_supported_for_text_generation() ) {
			return new WP_Error( 'wpto_no_ai_provider', __( 'No AI provider is configured. Set one up in the WordPress settings before running an analysis.', 'tag-loom' ) );
		}

		$text = $builder->generate_text();

		if ( is_wp_error( $text ) ) {
			return $text;
		}

		if ( ! is_string( $text ) || '' === trim( $text ) ) {
			return new WP_Error( 'wpto_bad_response', __( 'The AI provider returned an empty response.', 'tag-loom' ) );
		}

		return $this->parse_suggestions( $text, $valid_ids );
	}

	/**
	 * Reports whether a usable text-generation provider is configured.
	 *
	 * @return true|WP_Error True when the AI Client can generate text, WP_Error otherwise.
	 */
	public function provider_available() {
		if ( ! function_exists( 'wp_ai_client_prompt' ) ) {
			return new WP_Error( 'wpto_no_ai_client', __( 'The AI Analysis feature requires WordPress 7.0 or newer, which bundles the core AI Client.', 'tag-loom' ) );
		}

		if ( wp_ai_client_prompt( 'ping' )->is_supported_for_text_generation() ) {
			return true;
		}

		return new WP_Error( 'wpto_no_ai_provider', __( 'No AI provider is configured. Set one up in the WordPress settings before running an analysis.', 'tag-loom' ) );
	}

	private function build_system_prompt() {
		$language = trim( WPTO_Settings::get_ai_language() );

		if ( '' !== $language ) {
			$language_instruction = sprintf( 'Always write the "reason" field in %s, regardless of the language of the tag names.', $language );
		} else {
			$language_instruction = 'Write the "reason" field in the same language as the tag names.';
		}

		return "You are an assistant analyzing a list of WordPress tags (JSON format: id, name, count) to identify:\n"
			. "1) near_duplicate: textual near-duplicates (typos, plurals, casing, hyphens/spaces) referring to the exact same thing, e.g. \"Wii U\" and \"Nintendo Wii U\"\n"
			. "2) semantic_overlap: tags that are true synonyms describing the exact same underlying concept with different wording, NOT tags that are merely related, topically adjacent, or a broader/narrower category of each other\n"
			. "3) low_usage_merge: tags with a very low count (typically 1-2 posts) that are a clear, unambiguous narrower case of a broader tag already present in the list, where merging loses no meaningful distinction\n\n"
			. "Be conservative for types 2 and 3: these are far more prone to false positives than type 1. If you are not highly confident two tags mean the same thing, do not suggest merging them - omitting a suggestion is always better than a wrong one. Only use confidence 0.7 or higher for semantic_overlap and low_usage_merge; near_duplicate can use the full range.\n\n"
			. "Reply with ONLY valid JSON, no extra text, no code block, in this exact format:\n"
			. '{"suggestions":[{"type":"near_duplicate|semantic_overlap|low_usage_merge","source_tag_ids":[123],"target_tag_id":456,"reason":"...","confidence":0.0}]}' . "\n\n"
			. "Rules: only use ids present in the given list; target_tag_id must differ from every source_tag_id; "
			. "for any pair of tags, propose merging them in at most one direction, never both (e.g. if you suggest A into B, do not also suggest B into A); "
			. "if you find no meaningful suggestions, return {\"suggestions\":[]}. {$language_instruction}";
	}

	private function parse_suggestions( $text, array $valid_ids ) {
		$text = trim( $text );

		// Strip an accidental markdown code fence if the model added one.
		if ( 0 === strpos( $text, '```' ) ) {
			$text = preg_replace( '/^```[a-zA-Z]*\n?/', '', $text );
			$text = preg_replace( '/```$/', '', $text );
			$text = trim( $text );
		}

		$decoded = json_decode( $text, true );

		if ( ! is_array( $decoded ) || ! isset( $decoded['suggestions'] ) || ! is_array( $decoded['suggestions'] ) ) {
			return new WP_Error( 'wpto_invalid_json', __( 'The model response does not contain valid JSON.', 'tag-loom' ) );
		}

		$valid_ids_flip = array_flip( array_map( 'intval', $valid_ids ) );
		$suggestions    = array();

		foreach ( $decoded['suggestions'] as $raw ) {
			if ( ! is_array( $raw ) ) {
				continue;
			}

			$type = isset( $raw['type'] ) ? sanitize_key( $raw['type'] ) : '';
			if ( ! in_array( $type, self::VALID_TYPES, true ) ) {
				continue;
			}

			$source_ids = array();
			if ( ! empty( $raw['source_tag_ids'] ) && is_array( $raw['source_tag_ids'] ) ) {
				foreach ( $raw['source_tag_ids'] as $sid ) {
					$sid = (int) $sid;
					if ( isset( $valid_ids_flip[ $sid ] ) ) {
						$source_ids[] = $sid;
					}
				}
			}

			$target_id = isset( $raw['target_tag_id'] ) ? (int) $raw['target_tag_id'] : 0;

			if ( empty( $source_ids ) || ! $target_id || ! isset( $valid_ids_flip[ $target_id ] ) ) {
				continue;
			}

			if ( in_array( $target_id, $source_ids, true ) ) {
				continue;
			}

			$confidence = isset( $raw['confidence'] ) ? (float) $raw['confidence'] : 0.5;
			$confidence = max( 0.0, min( 1.0, $confidence ) );

			$suggestions[] = array(
				'type'            => $type,
				'source_term_ids' => $source_ids,
				'target_term_id'  => $target_id,
				'reason'          => isset( $raw['reason'] ) ? sanitize_text_field( $raw['reason'] ) : '',
				'confidence'      => $confidence,
			);
		}

		return $suggestions;
	}
}
