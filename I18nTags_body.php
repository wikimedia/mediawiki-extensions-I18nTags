<?php

use MediaWiki\Languages\LanguageNameUtils;
use MediaWiki\MediaWikiServices;

class I18nTags {
	public static function onParserFirstCallInit( Parser $parser ) {
		$parser->setHook( 'formatnum', [ __CLASS__, 'formatNumber' ] );
		$parser->setHook( 'grammar', [ __CLASS__, 'grammar' ] );
		$parser->setHook( 'plural', [ __CLASS__, 'plural' ] );
		$parser->setHook( 'linktrail', [ __CLASS__, 'linktrail' ] );
		$parser->setFunctionHook( 'languagename', [ __CLASS__, 'languageName' ] );
	}

	public static function formatNumber( $data, array $params, Parser $parser, PPFrame $frame ) {
		$lang = self::languageObject( $params['language'] ?? null ) ?? $parser->getTargetLanguage();

		$text = $lang->formatNum( $data );
		return $parser->recursiveTagParse( $text, $frame );
	}

	public static function grammar( $data, array $params, Parser $parser, PPFrame $frame ) {
		$case = isset( $params['case'] ) ? $params['case'] : '';
		$lang = self::languageObject( $params['language'] ?? null ) ?? $parser->getTargetLanguage();

		$text = $lang->convertGrammar( $data, $case );
		return $parser->recursiveTagParse( $text, $frame );
	}

	public static function plural( $data, array $params, Parser $parser, PPFrame $frame ) {
		[ $from, $to ] = self::getRange( isset( $params['n'] ) ? $params['n'] : '' );
		$args = explode( '|', $data );
		$lang = self::languageObject( $params['language'] ?? null ) ?? $parser->getTargetLanguage();

		$format = isset( $params['format'] ) ? $params['format'] : '%s';
		$format = str_replace( '\n', "\n", $format );

		$s = '';
		for ( $i = $from; $i <= $to; $i++ ) {
			$t = $lang->convertPlural( $i, $args );
			$fmtn = $lang->formatNum( $i );
			$s .= str_replace(
				[ '%d', '%s' ],
				[ $i, wfMsgReplaceArgs( $t, [ $fmtn ] ) ],
				$format
			);
		}

		return $parser->recursiveTagParse( $s, $frame );
	}

	public static function linktrail( $data, array $params, Parser $parser, PPFrame $frame ) {
		$lang = self::languageObject( $params['language'] ?? null ) ?? $parser->getTargetLanguage();
		$regex = $lang->linkTrail();

		$inside = '';
		if ( $data != '' ) {
			$predata = [];
			preg_match( '/^\[\[([^\]|]+)(\|[^\]]+)?\]\](.*)$/sDu', $data, $predata );
			$m = [];
			if ( preg_match( $regex, $predata[3], $m ) ) {
				$inside = $m[1];
				$data = $m[2];
			}
		}
		$predata = isset( $predata[2] )
			? $predata[2]
			: ( isset( $predata[1] ) ? $predata[1] : $predata[0] );

		$text = "<strong>$predata$inside</strong>$data";
		return $parser->recursiveTagParse( $text, $frame );
	}

	public static function languageName( &$parser, $code = '', $outputLanguage = '' ) {
		if ( !$code ) {
			return '';
		}
		if ( !$outputLanguage ) {
			$outputLanguage = $parser->getOptions()->getUserLang();
		}
		$cldr = is_callable( [ 'LanguageNames', 'getNames' ] );
		if ( $outputLanguage !== 'native' && $cldr ) {
			$languages = LanguageNames::getNames( $outputLanguage,
				LanguageNames::FALLBACK_NORMAL,
				LanguageNames::LIST_MW_AND_CLDR
			);
		} else {
			$languages = MediaWikiServices::getInstance()->getLanguageNameUtils()
				->getLanguageNames( LanguageNameUtils::AUTONYMS, LanguageNameUtils::DEFINED );
		}

		return isset( $languages[$code] ) ? $languages[$code] : $code;
	}

	public static function languageObject( ?string $input ): ?Language {
		$services = MediaWikiServices::getInstance();
		$factory = $services->getLanguageFactory();
		$utils = $services->getLanguageNameUtils();

		if ( $input && $utils->isKnownLanguageTag( $input ) ) {
			return $factory->getLanguage( $input );
		}

		return null;
	}

	public static function getRange( $s, $min = false, $max = false ) {
		$matches = [];
		if ( preg_match( '/(\d+)-(\d+)/', $s, $matches ) ) {
			$from = $matches[1];
			$to = $matches[2];
		} else {
			$from = $to = (int)$s;
		}

		if ( $from > $to ) {
			$UNDEFINED = $to;
			$to = $from;
			$from = $UNDEFINED;
		}
		if ( $min !== false ) {
			$from = max( $min, $from );
		}
		if ( $max !== false ) {
			$to = min( $max, $to );
		}

		return [ $from, $to ];
	}
}
