<?php

class ParserExpression {

	function __construct( $parser, $substack, $result ) {
		$this->parser = $parser ;
		$this->substack = $substack ;
		$this->result = $result ;
	}

	function find( $exp ) {
		$rule_callback = array( $this->parser, "{$this->result['name']}_DLR{$exp}" ) ;
		$pars_callback = array( $this->parser, "DLR{$exp}" ) ;

		/* If the current result has that expression, return it */
		if ( isset( $this->result[$exp] ) ) return $this->result[$exp] ;

		/* Search backwards through the sub-expression stacks */
		for ( $i = count( $this->substack ) - 1 ; $i >= 0 ; $i-- ) {
			if ( isset( $this->substack[$i][$exp] ) ) return $this->substack[$i][$exp] ;
		}

		/* If we have a rule-attached method, call that */
		if ( is_callable( $rule_callback ) ) return call_user_func( $rule_callback, $result ) ;

		/* If we have a class-wide method, call that */
		if ( is_callable( $pars_callback ) ) return call_user_func( $pars_callback, $result ) ;

		/* If we have a global function, call that */
		if ( function_exists( $exp ) ) return call_user_func( $exp, $result ) ;

		/* If we have a global constant, call that */
		if ( defined( $exp ) ) return constant( $expression ) ;

		return FALSE ;
	}

	function callback( $m ) {
		$res = $this->find( $m[1] ) ;
		if ( $res === FALSE ) return "" ;
		if ( is_string( $res ) ) return $res ;
		if ( isset( $res['text'] ) ) return $res['text'] ;

		// If we find no matches, assume we don't want a replacement, and replace it with itself
		return $m[0] ;
	}

	function expand( $var ) {
		return preg_replace_callback( '/\$(\w+)/', array( $this, 'callback' ), $var ) ;
	}

	function match( $var ) {
		return $this->find( $var ) ;
	}
}

/**
 * We cache the last regex result. This is a low-cost optimization, because we have to do an un-anchored match + check match position anyway
 * (alternative is to do an anchored match on a string cut with substr, but that is very slow for long strings). We then don't need to recheck
 * for any position between current position and eventual match position - result will be the same
 *
 *  Of course, the next regex might be outside that bracket - after the bracket if other matches have progressed beyond the match position, or before
 *  the bracket if a failed match + restore has moved the current position backwards - so we have to check that too.
 */
class ParserRegexp {
	function __construct( $parser, $rx ) {
		$this->parser = $parser ;
		$this->rx = $rx . 'Sx' ;

		$this->matches = NULL ;
		$this->match_pos = NULL ; // NULL is no-match-to-end-of-string, unless check_pos also == NULL, in which case means undefined
		$this->check_pos = NULL ;
	}

	function match() {
		$current_pos = $this->parser->pos ;
		$dirty = $this->check_pos === NULL || $this->check_pos > $current_pos || ( $this->match_pos !== NULL && $this->match_pos < $current_pos ) ;

		if ( $dirty ) {
			$this->check_pos = $current_pos ;
			$matched = preg_match( $this->rx, $this->parser->string, $this->matches, PREG_OFFSET_CAPTURE, $this->check_pos) ;
			if ( $matched ) $this->match_pos = $this->matches[0][1] ; else $this->match_pos = NULL ;
		}

		if ( $this->match_pos === $current_pos ) {
			$this->parser->pos += strlen( $this->matches[0][0] );
			return $this->matches[0][0] ;
		}

		return FALSE ;
	}
}

/**
 * Parser base class
 * - handles current position in string
 * - handles matching that position against literal or rx
 * - some abstraction of code that would otherwise be repeated many times in a compiled grammer, mostly related to calling user functions
 *   for result construction and building
 */
class Parser {
	function __construct( $string ) {
		$this->string = $string ;
		$this->pos = 0 ;

		$this->depth = 0 ;

		$this->regexps = array() ;
	}

	function whitespace() {
		$matched = preg_match( '/[ \t]+/', $this->string, $matches, PREG_OFFSET_CAPTURE, $this->pos ) ;
		if ( $matched && $matches[0][1] == $this->pos ) {
			$this->pos += strlen( $matches[0][0] );
			return ' ' ;
		}
		return FALSE ;
	}

 	function literal( $token ) {
 		/* Debugging: * / print( "Looking for token '$token' @ '" . substr( $this->string, $this->pos ) . "'\n" ) ; /* */
 		$toklen = strlen( $token ) ;
 		$substr = substr( $this->string, $this->pos, $toklen ) ;
		if ( $substr == $token ) {
			$this->pos += $toklen ;
			return $token ;
		}
		return FALSE ;
	}

	function rx( $rx ) {
		if ( !isset( $this->regexps[$rx] ) ) $this->regexps[$rx] = new ParserRegexp( $this, $rx ) ;
		return $this->regexps[$rx]->match() ;
	}

	function expand( $var, $substack, $result ) {
		$cb = new Parser_ExpressionCallback( $this, $substack, $result ) ;
		$v = preg_replace_callback( '/\$(\w+)/', array( $cb, 'callback' ), $var ) ;
		print "Expanded var: $v" ;
		return $v ;
	}

	function php( $var, $substack, $result ) {
		$ex = $this->get_expression( $var, $substack, $result ) ;
		print_r( $result ) ;

		if ( is_string( $ex ) ) {
			return ( preg_match( '{^\s*/}', $ex ) ? $this->rx( $ex ) : $this->literal( $ex ) ) ;
		}
		return $ex ;
	}

	function packhas( $key, $pos ) {
		return false ;
	}

	function packread( $key, $pos ) {
		throw 'PackRead after PackHas=>false in Parser.php' ;
	}

	function packwrite( $key, $pos, $res ) {
		return $res ;
	}

	function construct( $name ) {
		$result = array( 'type' => 'node', 'name' => $name, 'text' => '' ) ;

		$callback = array( $this, "{$name}__construct" ) ;
		if ( is_callable( $callback ) ) {
			call_user_func_array( $callback, array( &$result ) ) ;
		}

		return $result ;
	}

	function finalise( $name, &$result ) {
		$callback = array( $this, "{$name}__finalise" ) ;
		if ( is_callable( $callback ) ) {
			call_user_func_array( $callback, array( &$result ) ) ;
		}

		return $result ;
	}

	function store ( &$result, $subres, $storetag = NULL ) {
		$result['text'] .= $subres['text'] ;

		$globalcb = array( $this, "{$result['name']}_STR" ) ;
		$callback = array( $this, $storetag ? "{$result['name']}_{$storetag}" : "{$result['name']}_{$subres['name']}" ) ;

		if ( is_callable( $callback ) ) {
			call_user_func_array( $callback, array( &$result, $subres ) ) ;
		}
		elseif ( is_callable( $globalcb ) ) {
			call_user_func_array( $globalcb, array( &$result, $subres ) ) ;
		}
		elseif ( $storetag ) {
			if ( !isset( $result[$storetag] ) ) $result[$storetag] = $subres ;
			else {
				if ( isset( $result[$storetag]['text'] ) ) $result[$storetag] = array( $result[$storetag] ) ;
				$result[$storetag][] = $subres ;
			}
		}
	}
}

/**
 * By inheriting from Packrat instead of Parser, the parser will run in linear time (instead of exponential like
 * Parser), but will require a lot more memory, since every match-attempt at every position is memorised.
 *
 * We now use a string as a byte-array to store position information rather than a straight array for memory reasons. This
 * means there is a (roughly) 8MB limit on the size of the string we can parse
 *
 * @author Hamish Friedlander
 */
class Packrat extends Parser {
	function __construct( $string ) {
		parent::__construct( $string ) ;

		$max = unpack( 'N', "\x00\xFD\xFF\xFF" ) ;
		if ( strlen( $string ) > $max[1] ) user_error( 'Attempting to parse string longer than Packrat Parser can handle', E_USER_ERROR ) ;

		$this->packstatebase = str_repeat( "\xFF", strlen( $string )*3 ) ;
		$this->packstate = array() ;
		$this->packres = array() ;
	}

	function packhas( $key, $pos ) {
		$pos *= 3 ;
		return isset( $this->packstate[$key] ) && $this->packstate[$key][$pos] != "\xFF" ;
	}

	function packread( $key, $pos ) {
		$pos *= 3 ;
		if ( $this->packstate[$key][$pos] == "\xFE" ) return FALSE ;

		$this->pos = ord($this->packstate[$key][$pos]) << 16 | ord($this->packstate[$key][$pos+1]) << 8 | ord($this->packstate[$key][$pos+2]) ;
		return $this->packres["$key:$pos"] ;
	}

	function packwrite( $key, $pos, $res ) {
		if ( !isset( $this->packstate[$key] ) ) $this->packstate[$key] = $this->packstatebase ;

		$pos *= 3 ;

		if ( $res !== FALSE ) {
			$i = pack( 'N', $this->pos ) ;

			$this->packstate[$key][$pos]   = $i[1] ;
			$this->packstate[$key][$pos+1] = $i[2] ;
			$this->packstate[$key][$pos+2] = $i[3] ;

			$this->packres["$key:$pos"] = $res ;
		}
		else {
			$this->packstate[$key][$pos] = "\xFE" ;
		}

		return $res ;
	}
}

/**
 * FalseOnlyPackrat only remembers which results where false. Experimental.
 *
 * @author Hamish Friedlander
 */
class FalseOnlyPackrat extends Parser {
	function __construct( $string ) {
		parent::__construct( $string ) ;

		$this->packstatebase = str_repeat( '.', strlen( $string ) ) ;
		$this->packstate = array() ;
	}

	function packhas( $key, $pos ) {
		return isset( $this->packstate[$key] ) && $this->packstate[$key][$pos] == 'F' ;
	}

	function packread( $key, $pos ) {
		return FALSE ;
	}

	function packwrite( $key, $pos, $res ) {
		if ( !isset( $this->packstate[$key] ) ) $this->packstate[$key] = $this->packstatebase ;

		if ( $res === FALSE ) {
			$this->packstate[$key][$pos] = 'F' ;
		}

		return $res ;
	}
}

/**
 * Conservative Packrat will only memo-ize a result on the second hit, making it more memory-lean than Packrat,
 * but less likely to go exponential that Parser. Because the store logic is much more complicated this is a net
 * loss over Parser for many simple grammars.
 *
 * @author Hamish Friedlander
 */
class ConservativePackrat extends Parser {
	function packhas( $key ) {
		return isset( $this->packres[$key] ) && $this->packres[$key] !== NULL ;
	}

	function packread( $key ) {
		$this->pos = $this->packpos[$key];
		return $this->packres[$key] ;
	}

	function packwrite( $key, $res ) {
		if ( isset( $this->packres[$key] ) ) {
			$this->packres[$key] = $res ;
			$this->packpos[$key] = $this->pos ;
		}
		else {
			$this->packres[$key] = NULL ;
		}
		return $res ;
	}
}

