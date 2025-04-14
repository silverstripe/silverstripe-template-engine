<?php

/*
WARNING: This file has been machine generated. Do not edit it, or your changes will be overwritten next time it is compiled.
*/




namespace SilverStripe\TemplateEngine;

use SilverStripe\Core\Injector\Injector;
use Parser;
use InvalidArgumentException;
use SilverStripe\TemplateEngine\Exception\SSTemplateParseException;

// Make sure to include the base parser code
$base = dirname(__FILE__);
require_once($base.'/../thirdparty/php-peg/Parser.php');

/**
  * This is the parser for the SilverStripe template language. It gets called on a string and uses a php-peg parser
  * to match that string against the language structure, building up the PHP code to execute that structure as it
  * parses
  *
  * The $result array that is built up as part of the parsing (see thirdparty/php-peg/README.md for more on how
  * parsers build results) has one special member, 'php', which contains the php equivalent of that part of the
  * template tree.
  *
  * Some match rules generate alternate php, or other variations, so check the per-match documentation too.
  *
  * Terms used:
  *
  * Marked: A string or lookup in the template that has been explicitly marked as such - lookups by prepending with
  * "$" (like $Foo.Bar), strings by wrapping with single or double quotes ('Foo' or "Foo")
  *
  * Bare: The opposite of marked. An argument that has to has it's type inferred by usage and 2.4 defaults.
  *
  * Example of using a bare argument for a loop block: <% loop Foo %>
  *
  * Block: One of two SS template structures. The special characters "<%" and "%>" are used to wrap the opening and
  * (required or forbidden depending on which block exactly) closing block marks.
  *
  * Open Block: An SS template block that doesn't wrap any content or have a closing end tag (in fact, a closing end
  * tag is forbidden)
  *
  * Closed Block: An SS template block that wraps content, and requires a counterpart <% end_blockname %> tag
  *
  * Angle Bracket: angle brackets "<" and ">" are used to eat whitespace between template elements
  * N: eats white space including newlines (using in legacy _t support)
  */
class SSTemplateParser extends Parser implements TemplateParser
{

    /**
     * @var bool - Set true by SSTemplateParser::compileString if the template should include comments intended
     * for debugging (template source, included files, etc)
     */
    protected $includeDebuggingComments = false;

    /**
     * Stores the user-supplied closed block extension rules in the form:
     * [
     *   'name' => function (&$res) {}
     * ]
     * See SSTemplateParser::ClosedBlock_Handle_Loop for an example of what the callable should look like
     * @var array
     */
    protected $closedBlocks = [];

    /**
     * Stores the user-supplied open block extension rules in the form:
     * [
     *   'name' => function (&$res) {}
     * ]
     * See SSTemplateParser::OpenBlock_Handle_Base_tag for an example of what the callable should look like
     * @var array
     */
    protected $openBlocks = [];

    /**
     * Allow the injection of new closed & open block callables
     * @param array $closedBlocks
     * @param array $openBlocks
     */
    public function __construct($closedBlocks = [], $openBlocks = [])
    {
        parent::__construct(null);
        $this->setClosedBlocks($closedBlocks);
        $this->setOpenBlocks($openBlocks);
    }

    /**
     * Override the function that constructs the result arrays to also prepare a 'php' item in the array
     */
    function construct($matchrule, $name, $arguments = null)
    {
        $res = parent::construct($matchrule, $name, $arguments);
        if (!isset($res['php'])) {
            $res['php'] = '';
        }
        return $res;
    }

    /**
     * Set the closed blocks that the template parser should use
     *
     * This method will delete any existing closed blocks, please use addClosedBlock if you don't
     * want to overwrite
     * @param array $closedBlocks
     * @throws InvalidArgumentException
     */
    public function setClosedBlocks($closedBlocks)
    {
        $this->closedBlocks = [];
        foreach ((array) $closedBlocks as $name => $callable) {
            $this->addClosedBlock($name, $callable);
        }
    }

    /**
     * Set the open blocks that the template parser should use
     *
     * This method will delete any existing open blocks, please use addOpenBlock if you don't
     * want to overwrite
     * @param array $openBlocks
     * @throws InvalidArgumentException
     */
    public function setOpenBlocks($openBlocks)
    {
        $this->openBlocks = [];
        foreach ((array) $openBlocks as $name => $callable) {
            $this->addOpenBlock($name, $callable);
        }
    }

    /**
     * Add a closed block callable to allow <% name %><% end_name %> syntax
     * @param string $name The name of the token to be used in the syntax <% name %><% end_name %>
     * @param callable $callable The function that modifies the generation of template code
     * @throws InvalidArgumentException
     */
    public function addClosedBlock($name, $callable)
    {
        $this->validateExtensionBlock($name, $callable, 'Closed block');
        $this->closedBlocks[$name] = $callable;
    }

    /**
     * Add a closed block callable to allow <% name %> syntax
     * @param string $name The name of the token to be used in the syntax <% name %>
     * @param callable $callable The function that modifies the generation of template code
     * @throws InvalidArgumentException
     */
    public function addOpenBlock($name, $callable)
    {
        $this->validateExtensionBlock($name, $callable, 'Open block');
        $this->openBlocks[$name] = $callable;
    }

    /**
     * Ensures that the arguments to addOpenBlock and addClosedBlock are valid
     * @param $name
     * @param $callable
     * @param $type
     * @throws InvalidArgumentException
     */
    protected function validateExtensionBlock($name, $callable, $type)
    {
        if (!is_string($name)) {
            throw new InvalidArgumentException(
                sprintf(
                    "Name argument for %s must be a string",
                    $type
                )
            );
        } elseif (!is_callable($callable)) {
            throw new InvalidArgumentException(
                sprintf(
                    "Callable %s argument named '%s' is not callable",
                    $type,
                    $name
                )
            );
        }
    }

    /* Template: (Comment | Translate | If | Require | CacheBlock | UncachedBlock | Include | ClosedBlock |
    OpenBlock | MalformedBlock | MalformedBracketInjection | Injection | Text)+ */
    protected $match_Template_typestack = array('Template');
    function match_Template ($stack = array()) {
    	$matchrule = "Template"; $result = $this->construct($matchrule, $matchrule, null);
    	$count = 0;
    	while (true) {
    		$res_50 = $result;
    		$pos_50 = $this->pos;
    		$_49 = NULL;
    		do {
    			$_47 = NULL;
    			do {
    				$res_0 = $result;
    				$pos_0 = $this->pos;
    				$matcher = 'match_'.'Comment'; $key = $matcher; $pos = $this->pos;
    				$subres = ( $this->packhas( $key, $pos ) ? $this->packread( $key, $pos ) : $this->packwrite( $key, $pos, $this->$matcher(array_merge($stack, array($result))) ) );
    				if ($subres !== FALSE) {
    					$this->store( $result, $subres );
    					$_47 = TRUE; break;
    				}
    				$result = $res_0;
    				$this->pos = $pos_0;
    				$_45 = NULL;
    				do {
    					$res_2 = $result;
    					$pos_2 = $this->pos;
    					$matcher = 'match_'.'Translate'; $key = $matcher; $pos = $this->pos;
    					$subres = ( $this->packhas( $key, $pos ) ? $this->packread( $key, $pos ) : $this->packwrite( $key, $pos, $this->$matcher(array_merge($stack, array($result))) ) );
    					if ($subres !== FALSE) {
    						$this->store( $result, $subres );
    						$_45 = TRUE; break;
    					}
    					$result = $res_2;
    					$this->pos = $pos_2;
    					$_43 = NULL;
    					do {
    						$res_4 = $result;
    						$pos_4 = $this->pos;
    						$matcher = 'match_'.'If'; $key = $matcher; $pos = $this->pos;
    						$subres = ( $this->packhas( $key, $pos ) ? $this->packread( $key, $pos ) : $this->packwrite( $key, $pos, $this->$matcher(array_merge($stack, array($result))) ) );
    						if ($subres !== FALSE) {
    							$this->store( $result, $subres );
    							$_43 = TRUE; break;
    						}
    						$result = $res_4;
    						$this->pos = $pos_4;
    						$_41 = NULL;
    						do {
    							$res_6 = $result;
    							$pos_6 = $this->pos;
    							$matcher = 'match_'.'Require'; $key = $matcher; $pos = $this->pos;
    							$subres = ( $this->packhas( $key, $pos ) ? $this->packread( $key, $pos ) : $this->packwrite( $key, $pos, $this->$matcher(array_merge($stack, array($result))) ) );
    							if ($subres !== FALSE) {
    								$this->store( $result, $subres );
    								$_41 = TRUE; break;
    							}
    							$result = $res_6;
    							$this->pos = $pos_6;
    							$_39 = NULL;
    							do {
    								$res_8 = $result;
    								$pos_8 = $this->pos;
    								$matcher = 'match_'.'CacheBlock'; $key = $matcher; $pos = $this->pos;
    								$subres = ( $this->packhas( $key, $pos ) ? $this->packread( $key, $pos ) : $this->packwrite( $key, $pos, $this->$matcher(array_merge($stack, array($result))) ) );
    								if ($subres !== FALSE) {
    									$this->store( $result, $subres );
    									$_39 = TRUE; break;
    								}
    								$result = $res_8;
    								$this->pos = $pos_8;
    								$_37 = NULL;
    								do {
    									$res_10 = $result;
    									$pos_10 = $this->pos;
    									$matcher = 'match_'.'UncachedBlock'; $key = $matcher; $pos = $this->pos;
    									$subres = ( $this->packhas( $key, $pos ) ? $this->packread( $key, $pos ) : $this->packwrite( $key, $pos, $this->$matcher(array_merge($stack, array($result))) ) );
    									if ($subres !== FALSE) {
    										$this->store( $result, $subres );
    										$_37 = TRUE; break;
    									}
    									$result = $res_10;
    									$this->pos = $pos_10;
    									$_35 = NULL;
    									do {
    										$res_12 = $result;
    										$pos_12 = $this->pos;
    										$matcher = 'match_'.'Include'; $key = $matcher; $pos = $this->pos;
    										$subres = ( $this->packhas( $key, $pos ) ? $this->packread( $key, $pos ) : $this->packwrite( $key, $pos, $this->$matcher(array_merge($stack, array($result))) ) );
    										if ($subres !== FALSE) {
    											$this->store( $result, $subres );
    											$_35 = TRUE; break;
    										}
    										$result = $res_12;
    										$this->pos = $pos_12;
    										$_33 = NULL;
    										do {
    											$res_14 = $result;
    											$pos_14 = $this->pos;
    											$matcher = 'match_'.'ClosedBlock'; $key = $matcher; $pos = $this->pos;
    											$subres = ( $this->packhas( $key, $pos ) ? $this->packread( $key, $pos ) : $this->packwrite( $key, $pos, $this->$matcher(array_merge($stack, array($result))) ) );
    											if ($subres !== FALSE) {
    												$this->store( $result, $subres );
    												$_33 = TRUE; break;
    											}
    											$result = $res_14;
    											$this->pos = $pos_14;
    											$_31 = NULL;
    											do {
    												$res_16 = $result;
    												$pos_16 = $this->pos;
    												$matcher = 'match_'.'OpenBlock'; $key = $matcher; $pos = $this->pos;
    												$subres = ( $this->packhas( $key, $pos ) ? $this->packread( $key, $pos ) : $this->packwrite( $key, $pos, $this->$matcher(array_merge($stack, array($result))) ) );
    												if ($subres !== FALSE) {
    													$this->store( $result, $subres );
    													$_31 = TRUE; break;
    												}
    												$result = $res_16;
    												$this->pos = $pos_16;
    												$_29 = NULL;
    												do {
    													$res_18 = $result;
    													$pos_18 = $this->pos;
    													$matcher = 'match_'.'MalformedBlock'; $key = $matcher; $pos = $this->pos;
    													$subres = ( $this->packhas( $key, $pos ) ? $this->packread( $key, $pos ) : $this->packwrite( $key, $pos, $this->$matcher(array_merge($stack, array($result))) ) );
    													if ($subres !== FALSE) {
    														$this->store( $result, $subres );
    														$_29 = TRUE; break;
    													}
    													$result = $res_18;
    													$this->pos = $pos_18;
    													$_27 = NULL;
    													do {
    														$res_20 = $result;
    														$pos_20 = $this->pos;
    														$matcher = 'match_'.'MalformedBracketInjection'; $key = $matcher; $pos = $this->pos;
    														$subres = ( $this->packhas( $key, $pos ) ? $this->packread( $key, $pos ) : $this->packwrite( $key, $pos, $this->$matcher(array_merge($stack, array($result))) ) );
    														if ($subres !== FALSE) {
    															$this->store( $result, $subres );
    															$_27 = TRUE; break;
    														}
    														$result = $res_20;
    														$this->pos = $pos_20;
    														$_25 = NULL;
    														do {
    															$res_22 = $result;
    															$pos_22 = $this->pos;
    															$matcher = 'match_'.'Injection'; $key = $matcher; $pos = $this->pos;
    															$subres = ( $this->packhas( $key, $pos ) ? $this->packread( $key, $pos ) : $this->packwrite( $key, $pos, $this->$matcher(array_merge($stack, array($result))) ) );
    															if ($subres !== FALSE) {
    																$this->store( $result, $subres );
    																$_25 = TRUE; break;
    															}
    															$result = $res_22;
    															$this->pos = $pos_22;
    															$matcher = 'match_'.'Text'; $key = $matcher; $pos = $this->pos;
    															$subres = ( $this->packhas( $key, $pos ) ? $this->packread( $key, $pos ) : $this->packwrite( $key, $pos, $this->$matcher(array_merge($stack, array($result))) ) );
    															if ($subres !== FALSE) {
    																$this->store( $result, $subres );
    																$_25 = TRUE; break;
    															}
    															$result = $res_22;
    															$this->pos = $pos_22;
    															$_25 = FALSE; break;
    														}
    														while(0);
    														if( $_25 === TRUE ) { $_27 = TRUE; break; }
    														$result = $res_20;
    														$this->pos = $pos_20;
    														$_27 = FALSE; break;
    													}
    													while(0);
    													if( $_27 === TRUE ) { $_29 = TRUE; break; }
    													$result = $res_18;
    													$this->pos = $pos_18;
    													$_29 = FALSE; break;
    												}
    												while(0);
    												if( $_29 === TRUE ) { $_31 = TRUE; break; }
    												$result = $res_16;
    												$this->pos = $pos_16;
    												$_31 = FALSE; break;
    											}
    											while(0);
    											if( $_31 === TRUE ) { $_33 = TRUE; break; }
    											$result = $res_14;
    											$this->pos = $pos_14;
    											$_33 = FALSE; break;
    										}
    										while(0);
    										if( $_33 === TRUE ) { $_35 = TRUE; break; }
    										$result = $res_12;
    										$this->pos = $pos_12;
    										$_35 = FALSE; break;
    									}
    									while(0);
    									if( $_35 === TRUE ) { $_37 = TRUE; break; }
    									$result = $res_10;
    									$this->pos = $pos_10;
    									$_37 = FALSE; break;
    								}
    								while(0);
    								if( $_37 === TRUE ) { $_39 = TRUE; break; }
    								$result = $res_8;
    								$this->pos = $pos_8;
    								$_39 = FALSE; break;
    							}
    							while(0);
    							if( $_39 === TRUE ) { $_41 = TRUE; break; }
    							$result = $res_6;
    							$this->pos = $pos_6;
    							$_41 = FALSE; break;
    						}
    						while(0);
    						if( $_41 === TRUE ) { $_43 = TRUE; break; }
    						$result = $res_4;
    						$this->pos = $pos_4;
    						$_43 = FALSE; break;
    					}
    					while(0);
    					if( $_43 === TRUE ) { $_45 = TRUE; break; }
    					$result = $res_2;
    					$this->pos = $pos_2;
    					$_45 = FALSE; break;
    				}
    				while(0);
    				if( $_45 === TRUE ) { $_47 = TRUE; break; }
    				$result = $res_0;
    				$this->pos = $pos_0;
    				$_47 = FALSE; break;
    			}
    			while(0);
    			if( $_47 === FALSE) { $_49 = FALSE; break; }
    			$_49 = TRUE; break;
    		}
    		while(0);
    		if( $_49 === FALSE) {
    			$result = $res_50;
    			$this->pos = $pos_50;
    			unset( $res_50 );
    			unset( $pos_50 );
    			break;
    		}
    		$count += 1;
    	}
    	if ($count > 0) { return $this->finalise($result); }
    	else { return FALSE; }
    }



    function Template_STR(&$res, $sub)
    {
        $res['php'] .= $sub['php'] . PHP_EOL ;
    }

    /* Word: / [A-Za-z_] [A-Za-z0-9_]* / */
    protected $match_Word_typestack = array('Word');
    function match_Word ($stack = array()) {
    	$matchrule = "Word"; $result = $this->construct($matchrule, $matchrule, null);
    	if (( $subres = $this->rx( '/ [A-Za-z_] [A-Za-z0-9_]* /' ) ) !== FALSE) {
    		$result["text"] .= $subres;
    		return $this->finalise($result);
    	}
    	else { return FALSE; }
    }


    /* NamespacedWord: / [A-Za-z_\/\\] [A-Za-z0-9_\/\\]* / */
    protected $match_NamespacedWord_typestack = array('NamespacedWord');
    function match_NamespacedWord ($stack = array()) {
    	$matchrule = "NamespacedWord"; $result = $this->construct($matchrule, $matchrule, null);
    	if (( $subres = $this->rx( '/ [A-Za-z_\/\\\\] [A-Za-z0-9_\/\\\\]* /' ) ) !== FALSE) {
    		$result["text"] .= $subres;
    		return $this->finalise($result);
    	}
    	else { return FALSE; }
    }


    /* Number: / [0-9]+ / */
    protected $match_Number_typestack = array('Number');
    function match_Number ($stack = array()) {
    	$matchrule = "Number"; $result = $this->construct($matchrule, $matchrule, null);
    	if (( $subres = $this->rx( '/ [0-9]+ /' ) ) !== FALSE) {
    		$result["text"] .= $subres;
    		return $this->finalise($result);
    	}
    	else { return FALSE; }
    }


    /* Value: / [A-Za-z0-9_]+ / */
    protected $match_Value_typestack = array('Value');
    function match_Value ($stack = array()) {
    	$matchrule = "Value"; $result = $this->construct($matchrule, $matchrule, null);
    	if (( $subres = $this->rx( '/ [A-Za-z0-9_]+ /' ) ) !== FALSE) {
    		$result["text"] .= $subres;
    		return $this->finalise($result);
    	}
    	else { return FALSE; }
    }


    /* CallArguments: :Argument ( < "," < :Argument )* */
    protected $match_CallArguments_typestack = array('CallArguments');
    function match_CallArguments ($stack = array()) {
    	$matchrule = "CallArguments"; $result = $this->construct($matchrule, $matchrule, null);
    	$_62 = NULL;
    	do {
    		$matcher = 'match_'.'Argument'; $key = $matcher; $pos = $this->pos;
    		$subres = ( $this->packhas( $key, $pos ) ? $this->packread( $key, $pos ) : $this->packwrite( $key, $pos, $this->$matcher(array_merge($stack, array($result))) ) );
    		if ($subres !== FALSE) {
    			$this->store( $result, $subres, "Argument" );
    		}
    		else { $_62 = FALSE; break; }
    		while (true) {
    			$res_61 = $result;
    			$pos_61 = $this->pos;
    			$_60 = NULL;
    			do {
    				if (( $subres = $this->whitespace(  ) ) !== FALSE) { $result["text"] .= $subres; }
    				if (substr($this->string ?? '',$this->pos ?? 0,1) == ',') {
    					$this->pos += 1;
    					$result["text"] .= ',';
    				}
    				else { $_60 = FALSE; break; }
    				if (( $subres = $this->whitespace(  ) ) !== FALSE) { $result["text"] .= $subres; }
    				$matcher = 'match_'.'Argument'; $key = $matcher; $pos = $this->pos;
    				$subres = ( $this->packhas( $key, $pos ) ? $this->packread( $key, $pos ) : $this->packwrite( $key, $pos, $this->$matcher(array_merge($stack, array($result))) ) );
    				if ($subres !== FALSE) {
    					$this->store( $result, $subres, "Argument" );
    				}
    				else { $_60 = FALSE; break; }
    				$_60 = TRUE; break;
    			}
    			while(0);
    			if( $_60 === FALSE) {
    				$result = $res_61;
    				$this->pos = $pos_61;
    				unset( $res_61 );
    				unset( $pos_61 );
    				break;
    			}
    		}
    		$_62 = TRUE; break;
    	}
    	while(0);
    	if( $_62 === TRUE ) { return $this->finalise($result); }
    	if( $_62 === FALSE) { return FALSE; }
    }




    /**
     * Values are bare words in templates, but strings in PHP. We rely on PHP's type conversion to back-convert
     * strings to numbers when needed.
     */
    function CallArguments_Argument(&$res, $sub)
    {
        if ($res['php'] !== '') {
            $res['php'] .= ', ';
        }

        $res['php'] .= ($sub['ArgumentMode'] == 'default') ? $sub['string_php'] :
            str_replace('$$FINAL', 'getValueAsArgument', $sub['php'] ?? '');
    }

    /* Call: Method:Word ( "(" < :CallArguments? > ")" )? */
    protected $match_Call_typestack = array('Call');
    function match_Call ($stack = array()) {
    	$matchrule = "Call"; $result = $this->construct($matchrule, $matchrule, null);
    	$_72 = NULL;
    	do {
    		$matcher = 'match_'.'Word'; $key = $matcher; $pos = $this->pos;
    		$subres = ( $this->packhas( $key, $pos ) ? $this->packread( $key, $pos ) : $this->packwrite( $key, $pos, $this->$matcher(array_merge($stack, array($result))) ) );
    		if ($subres !== FALSE) {
    			$this->store( $result, $subres, "Method" );
    		}
    		else { $_72 = FALSE; break; }
    		$res_71 = $result;
    		$pos_71 = $this->pos;
    		$_70 = NULL;
    		do {
    			if (substr($this->string ?? '',$this->pos ?? 0,1) == '(') {
    				$this->pos += 1;
    				$result["text"] .= '(';
    			}
    			else { $_70 = FALSE; break; }
    			if (( $subres = $this->whitespace(  ) ) !== FALSE) { $result["text"] .= $subres; }
    			$res_67 = $result;
    			$pos_67 = $this->pos;
    			$matcher = 'match_'.'CallArguments'; $key = $matcher; $pos = $this->pos;
    			$subres = ( $this->packhas( $key, $pos ) ? $this->packread( $key, $pos ) : $this->packwrite( $key, $pos, $this->$matcher(array_merge($stack, array($result))) ) );
    			if ($subres !== FALSE) {
    				$this->store( $result, $subres, "CallArguments" );
    			}
    			else {
    				$result = $res_67;
    				$this->pos = $pos_67;
    				unset( $res_67 );
    				unset( $pos_67 );
    			}
    			if (( $subres = $this->whitespace(  ) ) !== FALSE) { $result["text"] .= $subres; }
    			if (substr($this->string ?? '',$this->pos ?? 0,1) == ')') {
    				$this->pos += 1;
    				$result["text"] .= ')';
    			}
    			else { $_70 = FALSE; break; }
    			$_70 = TRUE; break;
    		}
    		while(0);
    		if( $_70 === FALSE) {
    			$result = $res_71;
    			$this->pos = $pos_71;
    			unset( $res_71 );
    			unset( $pos_71 );
    		}
    		$_72 = TRUE; break;
    	}
    	while(0);
    	if( $_72 === TRUE ) { return $this->finalise($result); }
    	if( $_72 === FALSE) { return FALSE; }
    }


    /* LookupStep: :Call &"." */
    protected $match_LookupStep_typestack = array('LookupStep');
    function match_LookupStep ($stack = array()) {
    	$matchrule = "LookupStep"; $result = $this->construct($matchrule, $matchrule, null);
    	$_76 = NULL;
    	do {
    		$matcher = 'match_'.'Call'; $key = $matcher; $pos = $this->pos;
    		$subres = ( $this->packhas( $key, $pos ) ? $this->packread( $key, $pos ) : $this->packwrite( $key, $pos, $this->$matcher(array_merge($stack, array($result))) ) );
    		if ($subres !== FALSE) {
    			$this->store( $result, $subres, "Call" );
    		}
    		else { $_76 = FALSE; break; }
    		$res_75 = $result;
    		$pos_75 = $this->pos;
    		if (substr($this->string ?? '',$this->pos ?? 0,1) == '.') {
    			$this->pos += 1;
    			$result["text"] .= '.';
    			$result = $res_75;
    			$this->pos = $pos_75;
    		}
    		else {
    			$result = $res_75;
    			$this->pos = $pos_75;
    			$_76 = FALSE; break;
    		}
    		$_76 = TRUE; break;
    	}
    	while(0);
    	if( $_76 === TRUE ) { return $this->finalise($result); }
    	if( $_76 === FALSE) { return FALSE; }
    }


    /* LastLookupStep: :Call */
    protected $match_LastLookupStep_typestack = array('LastLookupStep');
    function match_LastLookupStep ($stack = array()) {
    	$matchrule = "LastLookupStep"; $result = $this->construct($matchrule, $matchrule, null);
    	$matcher = 'match_'.'Call'; $key = $matcher; $pos = $this->pos;
    	$subres = ( $this->packhas( $key, $pos ) ? $this->packread( $key, $pos ) : $this->packwrite( $key, $pos, $this->$matcher(array_merge($stack, array($result))) ) );
    	if ($subres !== FALSE) {
    		$this->store( $result, $subres, "Call" );
    		return $this->finalise($result);
    	}
    	else { return FALSE; }
    }


    /* Lookup: LookupStep ("." LookupStep)* "." LastLookupStep | LastLookupStep */
    protected $match_Lookup_typestack = array('Lookup');
    function match_Lookup ($stack = array()) {
    	$matchrule = "Lookup"; $result = $this->construct($matchrule, $matchrule, null);
    	$_90 = NULL;
    	do {
    		$res_79 = $result;
    		$pos_79 = $this->pos;
    		$_87 = NULL;
    		do {
    			$matcher = 'match_'.'LookupStep'; $key = $matcher; $pos = $this->pos;
    			$subres = ( $this->packhas( $key, $pos ) ? $this->packread( $key, $pos ) : $this->packwrite( $key, $pos, $this->$matcher(array_merge($stack, array($result))) ) );
    			if ($subres !== FALSE) {
    				$this->store( $result, $subres );
    			}
    			else { $_87 = FALSE; break; }
    			while (true) {
    				$res_84 = $result;
    				$pos_84 = $this->pos;
    				$_83 = NULL;
    				do {
    					if (substr($this->string ?? '',$this->pos ?? 0,1) == '.') {
    						$this->pos += 1;
    						$result["text"] .= '.';
    					}
    					else { $_83 = FALSE; break; }
    					$matcher = 'match_'.'LookupStep'; $key = $matcher; $pos = $this->pos;
    					$subres = ( $this->packhas( $key, $pos ) ? $this->packread( $key, $pos ) : $this->packwrite( $key, $pos, $this->$matcher(array_merge($stack, array($result))) ) );
    					if ($subres !== FALSE) {
    						$this->store( $result, $subres );
    					}
    					else { $_83 = FALSE; break; }
    					$_83 = TRUE; break;
    				}
    				while(0);
    				if( $_83 === FALSE) {
    					$result = $res_84;
    					$this->pos = $pos_84;
    					unset( $res_84 );
    					unset( $pos_84 );
    					break;
    				}
    			}
    			if (substr($this->string ?? '',$this->pos ?? 0,1) == '.') {
    				$this->pos += 1;
    				$result["text"] .= '.';
    			}
    			else { $_87 = FALSE; break; }
    			$matcher = 'match_'.'LastLookupStep'; $key = $matcher; $pos = $this->pos;
    			$subres = ( $this->packhas( $key, $pos ) ? $this->packread( $key, $pos ) : $this->packwrite( $key, $pos, $this->$matcher(array_merge($stack, array($result))) ) );
    			if ($subres !== FALSE) {
    				$this->store( $result, $subres );
    			}
    			else { $_87 = FALSE; break; }
    			$_87 = TRUE; break;
    		}
    		while(0);
    		if( $_87 === TRUE ) { $_90 = TRUE; break; }
    		$result = $res_79;
    		$this->pos = $pos_79;
    		$matcher = 'match_'.'LastLookupStep'; $key = $matcher; $pos = $this->pos;
    		$subres = ( $this->packhas( $key, $pos ) ? $this->packread( $key, $pos ) : $this->packwrite( $key, $pos, $this->$matcher(array_merge($stack, array($result))) ) );
    		if ($subres !== FALSE) {
    			$this->store( $result, $subres );
    			$_90 = TRUE; break;
    		}
    		$result = $res_79;
    		$this->pos = $pos_79;
    		$_90 = FALSE; break;
    	}
    	while(0);
    	if( $_90 === TRUE ) { return $this->finalise($result); }
    	if( $_90 === FALSE) { return FALSE; }
    }




    function Lookup__construct(&$res)
    {
        $res['php'] = '$scope->locally()';
        $res['LookupSteps'] = [];
    }

    /**
     * The basic generated PHP of LookupStep and LastLookupStep is the same, except that LookupStep calls 'scopeToIntermediateValue' to
     * get the next ModelData in the sequence, and LastLookupStep calls different methods (getOutputValue, hasValue, scopeToIntermediateValue)
     * depending on the context the lookup is used in.
     */
    function Lookup_AddLookupStep(&$res, $sub, $method)
    {
        $res['LookupSteps'][] = $sub;

        $property = $sub['Call']['Method']['text'];

        $arguments = '';
        if (isset($sub['Call']['CallArguments']) && isset($sub['Call']['CallArguments']['php'])) {
            $arguments = $sub['Call']['CallArguments']['php'];
        }
        $res['php'] .= "->$method('$property', [$arguments])";
    }

    function Lookup_LookupStep(&$res, $sub)
    {
        $this->Lookup_AddLookupStep($res, $sub, 'scopeToIntermediateValue');
    }

    function Lookup_LastLookupStep(&$res, $sub)
    {
        $this->Lookup_AddLookupStep($res, $sub, '$$FINAL');
    }


    /* Translate: "<%t" < Entity < (Default:QuotedString)? < (!("is" "=") < "is" < Context:QuotedString)? <
    (InjectionVariables)? > "%>" */
    protected $match_Translate_typestack = array('Translate');
    function match_Translate ($stack = array()) {
    	$matchrule = "Translate"; $result = $this->construct($matchrule, $matchrule, null);
    	$_116 = NULL;
    	do {
    		if (( $subres = $this->literal( '<%t' ) ) !== FALSE) { $result["text"] .= $subres; }
    		else { $_116 = FALSE; break; }
    		if (( $subres = $this->whitespace(  ) ) !== FALSE) { $result["text"] .= $subres; }
    		$matcher = 'match_'.'Entity'; $key = $matcher; $pos = $this->pos;
    		$subres = ( $this->packhas( $key, $pos ) ? $this->packread( $key, $pos ) : $this->packwrite( $key, $pos, $this->$matcher(array_merge($stack, array($result))) ) );
    		if ($subres !== FALSE) {
    			$this->store( $result, $subres );
    		}
    		else { $_116 = FALSE; break; }
    		if (( $subres = $this->whitespace(  ) ) !== FALSE) { $result["text"] .= $subres; }
    		$res_98 = $result;
    		$pos_98 = $this->pos;
    		$_97 = NULL;
    		do {
    			$matcher = 'match_'.'QuotedString'; $key = $matcher; $pos = $this->pos;
    			$subres = ( $this->packhas( $key, $pos ) ? $this->packread( $key, $pos ) : $this->packwrite( $key, $pos, $this->$matcher(array_merge($stack, array($result))) ) );
    			if ($subres !== FALSE) {
    				$this->store( $result, $subres, "Default" );
    			}
    			else { $_97 = FALSE; break; }
    			$_97 = TRUE; break;
    		}
    		while(0);
    		if( $_97 === FALSE) {
    			$result = $res_98;
    			$this->pos = $pos_98;
    			unset( $res_98 );
    			unset( $pos_98 );
    		}
    		if (( $subres = $this->whitespace(  ) ) !== FALSE) { $result["text"] .= $subres; }
    		$res_109 = $result;
    		$pos_109 = $this->pos;
    		$_108 = NULL;
    		do {
    			$res_103 = $result;
    			$pos_103 = $this->pos;
    			$_102 = NULL;
    			do {
    				if (( $subres = $this->literal( 'is' ) ) !== FALSE) { $result["text"] .= $subres; }
    				else { $_102 = FALSE; break; }
    				if (substr($this->string ?? '',$this->pos ?? 0,1) == '=') {
    					$this->pos += 1;
    					$result["text"] .= '=';
    				}
    				else { $_102 = FALSE; break; }
    				$_102 = TRUE; break;
    			}
    			while(0);
    			if( $_102 === TRUE ) {
    				$result = $res_103;
    				$this->pos = $pos_103;
    				$_108 = FALSE; break;
    			}
    			if( $_102 === FALSE) {
    				$result = $res_103;
    				$this->pos = $pos_103;
    			}
    			if (( $subres = $this->whitespace(  ) ) !== FALSE) { $result["text"] .= $subres; }
    			if (( $subres = $this->literal( 'is' ) ) !== FALSE) { $result["text"] .= $subres; }
    			else { $_108 = FALSE; break; }
    			if (( $subres = $this->whitespace(  ) ) !== FALSE) { $result["text"] .= $subres; }
    			$matcher = 'match_'.'QuotedString'; $key = $matcher; $pos = $this->pos;
    			$subres = ( $this->packhas( $key, $pos ) ? $this->packread( $key, $pos ) : $this->packwrite( $key, $pos, $this->$matcher(array_merge($stack, array($result))) ) );
    			if ($subres !== FALSE) {
    				$this->store( $result, $subres, "Context" );
    			}
    			else { $_108 = FALSE; break; }
    			$_108 = TRUE; break;
    		}
    		while(0);
    		if( $_108 === FALSE) {
    			$result = $res_109;
    			$this->pos = $pos_109;
    			unset( $res_109 );
    			unset( $pos_109 );
    		}
    		if (( $subres = $this->whitespace(  ) ) !== FALSE) { $result["text"] .= $subres; }
    		$res_113 = $result;
    		$pos_113 = $this->pos;
    		$_112 = NULL;
    		do {
    			$matcher = 'match_'.'InjectionVariables'; $key = $matcher; $pos = $this->pos;
    			$subres = ( $this->packhas( $key, $pos ) ? $this->packread( $key, $pos ) : $this->packwrite( $key, $pos, $this->$matcher(array_merge($stack, array($result))) ) );
    			if ($subres !== FALSE) {
    				$this->store( $result, $subres );
    			}
    			else { $_112 = FALSE; break; }
    			$_112 = TRUE; break;
    		}
    		while(0);
    		if( $_112 === FALSE) {
    			$result = $res_113;
    			$this->pos = $pos_113;
    			unset( $res_113 );
    			unset( $pos_113 );
    		}
    		if (( $subres = $this->whitespace(  ) ) !== FALSE) { $result["text"] .= $subres; }
    		if (( $subres = $this->literal( '%>' ) ) !== FALSE) { $result["text"] .= $subres; }
    		else { $_116 = FALSE; break; }
    		$_116 = TRUE; break;
    	}
    	while(0);
    	if( $_116 === TRUE ) { return $this->finalise($result); }
    	if( $_116 === FALSE) { return FALSE; }
    }


    /* InjectionVariables: (< InjectionName:Word "=" Argument)+ */
    protected $match_InjectionVariables_typestack = array('InjectionVariables');
    function match_InjectionVariables ($stack = array()) {
    	$matchrule = "InjectionVariables"; $result = $this->construct($matchrule, $matchrule, null);
    	$count = 0;
    	while (true) {
    		$res_123 = $result;
    		$pos_123 = $this->pos;
    		$_122 = NULL;
    		do {
    			if (( $subres = $this->whitespace(  ) ) !== FALSE) { $result["text"] .= $subres; }
    			$matcher = 'match_'.'Word'; $key = $matcher; $pos = $this->pos;
    			$subres = ( $this->packhas( $key, $pos ) ? $this->packread( $key, $pos ) : $this->packwrite( $key, $pos, $this->$matcher(array_merge($stack, array($result))) ) );
    			if ($subres !== FALSE) {
    				$this->store( $result, $subres, "InjectionName" );
    			}
    			else { $_122 = FALSE; break; }
    			if (substr($this->string ?? '',$this->pos ?? 0,1) == '=') {
    				$this->pos += 1;
    				$result["text"] .= '=';
    			}
    			else { $_122 = FALSE; break; }
    			$matcher = 'match_'.'Argument'; $key = $matcher; $pos = $this->pos;
    			$subres = ( $this->packhas( $key, $pos ) ? $this->packread( $key, $pos ) : $this->packwrite( $key, $pos, $this->$matcher(array_merge($stack, array($result))) ) );
    			if ($subres !== FALSE) {
    				$this->store( $result, $subres );
    			}
    			else { $_122 = FALSE; break; }
    			$_122 = TRUE; break;
    		}
    		while(0);
    		if( $_122 === FALSE) {
    			$result = $res_123;
    			$this->pos = $pos_123;
    			unset( $res_123 );
    			unset( $pos_123 );
    			break;
    		}
    		$count += 1;
    	}
    	if ($count > 0) { return $this->finalise($result); }
    	else { return FALSE; }
    }


    /* Entity: / [A-Za-z_\\] [\w\.\\]* / */
    protected $match_Entity_typestack = array('Entity');
    function match_Entity ($stack = array()) {
    	$matchrule = "Entity"; $result = $this->construct($matchrule, $matchrule, null);
    	if (( $subres = $this->rx( '/ [A-Za-z_\\\\] [\w\.\\\\]* /' ) ) !== FALSE) {
    		$result["text"] .= $subres;
    		return $this->finalise($result);
    	}
    	else { return FALSE; }
    }




    function Translate__construct(&$res)
    {
        $res['php'] = '$val .= _t(';
    }

    function Translate_Entity(&$res, $sub)
    {
        $res['php'] .= "'$sub[text]'";
    }

    function Translate_Default(&$res, $sub)
    {
        $res['php'] .= ",$sub[text]";
    }

    function Translate_Context(&$res, $sub)
    {
        $res['php'] .= ",$sub[text]";
    }

    function Translate_InjectionVariables(&$res, $sub)
    {
        $res['php'] .= ",$sub[php]";
    }

    function Translate__finalise(&$res)
    {
        $res['php'] .= ');';
    }

    function InjectionVariables__construct(&$res)
    {
        $res['php'] = "[";
    }

    function InjectionVariables_InjectionName(&$res, $sub)
    {
        $res['php'] .= "'$sub[text]'=>";
    }

    function InjectionVariables_Argument(&$res, $sub)
    {
        $res['php'] .= str_replace('$$FINAL', 'getOutputValue', $sub['php'] ?? '') . ',';
    }

    function InjectionVariables__finalise(&$res)
    {
        if (substr($res['php'] ?? '', -1) == ',') {
            $res['php'] = substr($res['php'] ?? '', 0, -1); //remove last comma in the array
        }
        $res['php'] .= ']';
    }

    /* MalformedBracketInjection: "{$" :Lookup !( "}" ) */
    protected $match_MalformedBracketInjection_typestack = array('MalformedBracketInjection');
    function match_MalformedBracketInjection ($stack = array()) {
    	$matchrule = "MalformedBracketInjection"; $result = $this->construct($matchrule, $matchrule, null);
    	$_130 = NULL;
    	do {
    		if (( $subres = $this->literal( '{$' ) ) !== FALSE) { $result["text"] .= $subres; }
    		else { $_130 = FALSE; break; }
    		$matcher = 'match_'.'Lookup'; $key = $matcher; $pos = $this->pos;
    		$subres = ( $this->packhas( $key, $pos ) ? $this->packread( $key, $pos ) : $this->packwrite( $key, $pos, $this->$matcher(array_merge($stack, array($result))) ) );
    		if ($subres !== FALSE) {
    			$this->store( $result, $subres, "Lookup" );
    		}
    		else { $_130 = FALSE; break; }
    		$res_129 = $result;
    		$pos_129 = $this->pos;
    		$_128 = NULL;
    		do {
    			if (substr($this->string ?? '',$this->pos ?? 0,1) == '}') {
    				$this->pos += 1;
    				$result["text"] .= '}';
    			}
    			else { $_128 = FALSE; break; }
    			$_128 = TRUE; break;
    		}
    		while(0);
    		if( $_128 === TRUE ) {
    			$result = $res_129;
    			$this->pos = $pos_129;
    			$_130 = FALSE; break;
    		}
    		if( $_128 === FALSE) {
    			$result = $res_129;
    			$this->pos = $pos_129;
    		}
    		$_130 = TRUE; break;
    	}
    	while(0);
    	if( $_130 === TRUE ) { return $this->finalise($result); }
    	if( $_130 === FALSE) { return FALSE; }
    }



    function MalformedBracketInjection__finalise(&$res)
    {
        $lookup = $res['text'];
        throw new SSTemplateParseException("Malformed bracket injection $lookup. Perhaps you have forgotten the " .
            "closing bracket (})?", $this);
    }

    /* SimpleInjection: '$' :Lookup */
    protected $match_SimpleInjection_typestack = array('SimpleInjection');
    function match_SimpleInjection ($stack = array()) {
    	$matchrule = "SimpleInjection"; $result = $this->construct($matchrule, $matchrule, null);
    	$_134 = NULL;
    	do {
    		if (substr($this->string ?? '',$this->pos ?? 0,1) == '$') {
    			$this->pos += 1;
    			$result["text"] .= '$';
    		}
    		else { $_134 = FALSE; break; }
    		$matcher = 'match_'.'Lookup'; $key = $matcher; $pos = $this->pos;
    		$subres = ( $this->packhas( $key, $pos ) ? $this->packread( $key, $pos ) : $this->packwrite( $key, $pos, $this->$matcher(array_merge($stack, array($result))) ) );
    		if ($subres !== FALSE) {
    			$this->store( $result, $subres, "Lookup" );
    		}
    		else { $_134 = FALSE; break; }
    		$_134 = TRUE; break;
    	}
    	while(0);
    	if( $_134 === TRUE ) { return $this->finalise($result); }
    	if( $_134 === FALSE) { return FALSE; }
    }


    /* BracketInjection: '{$' :Lookup "}" */
    protected $match_BracketInjection_typestack = array('BracketInjection');
    function match_BracketInjection ($stack = array()) {
    	$matchrule = "BracketInjection"; $result = $this->construct($matchrule, $matchrule, null);
    	$_139 = NULL;
    	do {
    		if (( $subres = $this->literal( '{$' ) ) !== FALSE) { $result["text"] .= $subres; }
    		else { $_139 = FALSE; break; }
    		$matcher = 'match_'.'Lookup'; $key = $matcher; $pos = $this->pos;
    		$subres = ( $this->packhas( $key, $pos ) ? $this->packread( $key, $pos ) : $this->packwrite( $key, $pos, $this->$matcher(array_merge($stack, array($result))) ) );
    		if ($subres !== FALSE) {
    			$this->store( $result, $subres, "Lookup" );
    		}
    		else { $_139 = FALSE; break; }
    		if (substr($this->string ?? '',$this->pos ?? 0,1) == '}') {
    			$this->pos += 1;
    			$result["text"] .= '}';
    		}
    		else { $_139 = FALSE; break; }
    		$_139 = TRUE; break;
    	}
    	while(0);
    	if( $_139 === TRUE ) { return $this->finalise($result); }
    	if( $_139 === FALSE) { return FALSE; }
    }


    /* Injection: BracketInjection | SimpleInjection */
    protected $match_Injection_typestack = array('Injection');
    function match_Injection ($stack = array()) {
    	$matchrule = "Injection"; $result = $this->construct($matchrule, $matchrule, null);
    	$_144 = NULL;
    	do {
    		$res_141 = $result;
    		$pos_141 = $this->pos;
    		$matcher = 'match_'.'BracketInjection'; $key = $matcher; $pos = $this->pos;
    		$subres = ( $this->packhas( $key, $pos ) ? $this->packread( $key, $pos ) : $this->packwrite( $key, $pos, $this->$matcher(array_merge($stack, array($result))) ) );
    		if ($subres !== FALSE) {
    			$this->store( $result, $subres );
    			$_144 = TRUE; break;
    		}
    		$result = $res_141;
    		$this->pos = $pos_141;
    		$matcher = 'match_'.'SimpleInjection'; $key = $matcher; $pos = $this->pos;
    		$subres = ( $this->packhas( $key, $pos ) ? $this->packread( $key, $pos ) : $this->packwrite( $key, $pos, $this->$matcher(array_merge($stack, array($result))) ) );
    		if ($subres !== FALSE) {
    			$this->store( $result, $subres );
    			$_144 = TRUE; break;
    		}
    		$result = $res_141;
    		$this->pos = $pos_141;
    		$_144 = FALSE; break;
    	}
    	while(0);
    	if( $_144 === TRUE ) { return $this->finalise($result); }
    	if( $_144 === FALSE) { return FALSE; }
    }



    function Injection_STR(&$res, $sub)
    {
        $res['php'] = '$val .= '. str_replace('$$FINAL', 'getOutputValue', $sub['Lookup']['php'] ?? '') . ';';
    }

    /* DollarMarkedLookup: SimpleInjection */
    protected $match_DollarMarkedLookup_typestack = array('DollarMarkedLookup');
    function match_DollarMarkedLookup ($stack = array()) {
    	$matchrule = "DollarMarkedLookup"; $result = $this->construct($matchrule, $matchrule, null);
    	$matcher = 'match_'.'SimpleInjection'; $key = $matcher; $pos = $this->pos;
    	$subres = ( $this->packhas( $key, $pos ) ? $this->packread( $key, $pos ) : $this->packwrite( $key, $pos, $this->$matcher(array_merge($stack, array($result))) ) );
    	if ($subres !== FALSE) {
    		$this->store( $result, $subres );
    		return $this->finalise($result);
    	}
    	else { return FALSE; }
    }



    function DollarMarkedLookup_STR(&$res, $sub)
    {
        $res['Lookup'] = $sub['Lookup'];
    }

    /* QuotedString: q:/['"]/   String:/ (\\\\ | \\. | [^$q\\])* /   '$q' */
    protected $match_QuotedString_typestack = array('QuotedString');
    function match_QuotedString ($stack = array()) {
    	$matchrule = "QuotedString"; $result = $this->construct($matchrule, $matchrule, null);
    	$_150 = NULL;
    	do {
    		$stack[] = $result; $result = $this->construct( $matchrule, "q" ); 
    		if (( $subres = $this->rx( '/[\'"]/' ) ) !== FALSE) {
    			$result["text"] .= $subres;
    			$subres = $result; $result = array_pop($stack);
    			$this->store( $result, $subres, 'q' );
    		}
    		else {
    			$result = array_pop($stack);
    			$_150 = FALSE; break;
    		}
    		$stack[] = $result; $result = $this->construct( $matchrule, "String" ); 
    		if (( $subres = $this->rx( '/ (\\\\\\\\ | \\\\. | [^'.$this->expression($result, $stack, 'q').'\\\\])* /' ) ) !== FALSE) {
    			$result["text"] .= $subres;
    			$subres = $result; $result = array_pop($stack);
    			$this->store( $result, $subres, 'String' );
    		}
    		else {
    			$result = array_pop($stack);
    			$_150 = FALSE; break;
    		}
    		if (( $subres = $this->literal( ''.$this->expression($result, $stack, 'q').'' ) ) !== FALSE) { $result["text"] .= $subres; }
    		else { $_150 = FALSE; break; }
    		$_150 = TRUE; break;
    	}
    	while(0);
    	if( $_150 === TRUE ) { return $this->finalise($result); }
    	if( $_150 === FALSE) { return FALSE; }
    }


    /* Null: / (null)\b /i */
    protected $match_Null_typestack = array('Null');
    function match_Null ($stack = array()) {
    	$matchrule = "Null"; $result = $this->construct($matchrule, $matchrule, null);
    	if (( $subres = $this->rx( '/ (null)\b /i' ) ) !== FALSE) {
    		$result["text"] .= $subres;
    		return $this->finalise($result);
    	}
    	else { return FALSE; }
    }


    /* Boolean: / (true|false)\b  /i */
    protected $match_Boolean_typestack = array('Boolean');
    function match_Boolean ($stack = array()) {
    	$matchrule = "Boolean"; $result = $this->construct($matchrule, $matchrule, null);
    	if (( $subres = $this->rx( '/ (true|false)\b  /i' ) ) !== FALSE) {
    		$result["text"] .= $subres;
    		return $this->finalise($result);
    	}
    	else { return FALSE; }
    }


    /* Sign: / [+-] / */
    protected $match_Sign_typestack = array('Sign');
    function match_Sign ($stack = array()) {
    	$matchrule = "Sign"; $result = $this->construct($matchrule, $matchrule, null);
    	if (( $subres = $this->rx( '/ [+-] /' ) ) !== FALSE) {
    		$result["text"] .= $subres;
    		return $this->finalise($result);
    	}
    	else { return FALSE; }
    }


    /* Float: / [0-9]*\.?[0-9]+([eE][-+]?[0-9]+)? / */
    protected $match_Float_typestack = array('Float');
    function match_Float ($stack = array()) {
    	$matchrule = "Float"; $result = $this->construct($matchrule, $matchrule, null);
    	if (( $subres = $this->rx( '/ [0-9]*\.?[0-9]+([eE][-+]?[0-9]+)? /' ) ) !== FALSE) {
    		$result["text"] .= $subres;
    		return $this->finalise($result);
    	}
    	else { return FALSE; }
    }


    /* Hexadecimal: / 0[xX][0-9a-fA-F]+ / */
    protected $match_Hexadecimal_typestack = array('Hexadecimal');
    function match_Hexadecimal ($stack = array()) {
    	$matchrule = "Hexadecimal"; $result = $this->construct($matchrule, $matchrule, null);
    	if (( $subres = $this->rx( '/ 0[xX][0-9a-fA-F]+ /' ) ) !== FALSE) {
    		$result["text"] .= $subres;
    		return $this->finalise($result);
    	}
    	else { return FALSE; }
    }


    /* Octal: / 0[0-7]+ / */
    protected $match_Octal_typestack = array('Octal');
    function match_Octal ($stack = array()) {
    	$matchrule = "Octal"; $result = $this->construct($matchrule, $matchrule, null);
    	if (( $subres = $this->rx( '/ 0[0-7]+ /' ) ) !== FALSE) {
    		$result["text"] .= $subres;
    		return $this->finalise($result);
    	}
    	else { return FALSE; }
    }


    /* Binary: / 0[bB][01]+ / */
    protected $match_Binary_typestack = array('Binary');
    function match_Binary ($stack = array()) {
    	$matchrule = "Binary"; $result = $this->construct($matchrule, $matchrule, null);
    	if (( $subres = $this->rx( '/ 0[bB][01]+ /' ) ) !== FALSE) {
    		$result["text"] .= $subres;
    		return $this->finalise($result);
    	}
    	else { return FALSE; }
    }


    /* Decimal: / 0 | [1-9][0-9]* / */
    protected $match_Decimal_typestack = array('Decimal');
    function match_Decimal ($stack = array()) {
    	$matchrule = "Decimal"; $result = $this->construct($matchrule, $matchrule, null);
    	if (( $subres = $this->rx( '/ 0 | [1-9][0-9]* /' ) ) !== FALSE) {
    		$result["text"] .= $subres;
    		return $this->finalise($result);
    	}
    	else { return FALSE; }
    }


    /* IntegerOrFloat: ( Sign )? ( Hexadecimal | Binary | Float | Octal | Decimal ) */
    protected $match_IntegerOrFloat_typestack = array('IntegerOrFloat');
    function match_IntegerOrFloat ($stack = array()) {
    	$matchrule = "IntegerOrFloat"; $result = $this->construct($matchrule, $matchrule, null);
    	$_182 = NULL;
    	do {
    		$res_162 = $result;
    		$pos_162 = $this->pos;
    		$_161 = NULL;
    		do {
    			$matcher = 'match_'.'Sign'; $key = $matcher; $pos = $this->pos;
    			$subres = ( $this->packhas( $key, $pos ) ? $this->packread( $key, $pos ) : $this->packwrite( $key, $pos, $this->$matcher(array_merge($stack, array($result))) ) );
    			if ($subres !== FALSE) {
    				$this->store( $result, $subres );
    			}
    			else { $_161 = FALSE; break; }
    			$_161 = TRUE; break;
    		}
    		while(0);
    		if( $_161 === FALSE) {
    			$result = $res_162;
    			$this->pos = $pos_162;
    			unset( $res_162 );
    			unset( $pos_162 );
    		}
    		$_180 = NULL;
    		do {
    			$_178 = NULL;
    			do {
    				$res_163 = $result;
    				$pos_163 = $this->pos;
    				$matcher = 'match_'.'Hexadecimal'; $key = $matcher; $pos = $this->pos;
    				$subres = ( $this->packhas( $key, $pos ) ? $this->packread( $key, $pos ) : $this->packwrite( $key, $pos, $this->$matcher(array_merge($stack, array($result))) ) );
    				if ($subres !== FALSE) {
    					$this->store( $result, $subres );
    					$_178 = TRUE; break;
    				}
    				$result = $res_163;
    				$this->pos = $pos_163;
    				$_176 = NULL;
    				do {
    					$res_165 = $result;
    					$pos_165 = $this->pos;
    					$matcher = 'match_'.'Binary'; $key = $matcher; $pos = $this->pos;
    					$subres = ( $this->packhas( $key, $pos ) ? $this->packread( $key, $pos ) : $this->packwrite( $key, $pos, $this->$matcher(array_merge($stack, array($result))) ) );
    					if ($subres !== FALSE) {
    						$this->store( $result, $subres );
    						$_176 = TRUE; break;
    					}
    					$result = $res_165;
    					$this->pos = $pos_165;
    					$_174 = NULL;
    					do {
    						$res_167 = $result;
    						$pos_167 = $this->pos;
    						$matcher = 'match_'.'Float'; $key = $matcher; $pos = $this->pos;
    						$subres = ( $this->packhas( $key, $pos ) ? $this->packread( $key, $pos ) : $this->packwrite( $key, $pos, $this->$matcher(array_merge($stack, array($result))) ) );
    						if ($subres !== FALSE) {
    							$this->store( $result, $subres );
    							$_174 = TRUE; break;
    						}
    						$result = $res_167;
    						$this->pos = $pos_167;
    						$_172 = NULL;
    						do {
    							$res_169 = $result;
    							$pos_169 = $this->pos;
    							$matcher = 'match_'.'Octal'; $key = $matcher; $pos = $this->pos;
    							$subres = ( $this->packhas( $key, $pos ) ? $this->packread( $key, $pos ) : $this->packwrite( $key, $pos, $this->$matcher(array_merge($stack, array($result))) ) );
    							if ($subres !== FALSE) {
    								$this->store( $result, $subres );
    								$_172 = TRUE; break;
    							}
    							$result = $res_169;
    							$this->pos = $pos_169;
    							$matcher = 'match_'.'Decimal'; $key = $matcher; $pos = $this->pos;
    							$subres = ( $this->packhas( $key, $pos ) ? $this->packread( $key, $pos ) : $this->packwrite( $key, $pos, $this->$matcher(array_merge($stack, array($result))) ) );
    							if ($subres !== FALSE) {
    								$this->store( $result, $subres );
    								$_172 = TRUE; break;
    							}
    							$result = $res_169;
    							$this->pos = $pos_169;
    							$_172 = FALSE; break;
    						}
    						while(0);
    						if( $_172 === TRUE ) { $_174 = TRUE; break; }
    						$result = $res_167;
    						$this->pos = $pos_167;
    						$_174 = FALSE; break;
    					}
    					while(0);
    					if( $_174 === TRUE ) { $_176 = TRUE; break; }
    					$result = $res_165;
    					$this->pos = $pos_165;
    					$_176 = FALSE; break;
    				}
    				while(0);
    				if( $_176 === TRUE ) { $_178 = TRUE; break; }
    				$result = $res_163;
    				$this->pos = $pos_163;
    				$_178 = FALSE; break;
    			}
    			while(0);
    			if( $_178 === FALSE) { $_180 = FALSE; break; }
    			$_180 = TRUE; break;
    		}
    		while(0);
    		if( $_180 === FALSE) { $_182 = FALSE; break; }
    		$_182 = TRUE; break;
    	}
    	while(0);
    	if( $_182 === TRUE ) { return $this->finalise($result); }
    	if( $_182 === FALSE) { return FALSE; }
    }


    /* FreeString: /[^,)%!=><|&]+/ */
    protected $match_FreeString_typestack = array('FreeString');
    function match_FreeString ($stack = array()) {
    	$matchrule = "FreeString"; $result = $this->construct($matchrule, $matchrule, null);
    	if (( $subres = $this->rx( '/[^,)%!=><|&]+/' ) ) !== FALSE) {
    		$result["text"] .= $subres;
    		return $this->finalise($result);
    	}
    	else { return FALSE; }
    }


    /* Argument:
    :DollarMarkedLookup |
    :QuotedString |
    :Null |
    :Boolean |
    :IntegerOrFloat |
    :Lookup !(< FreeString)|
    :FreeString */
    protected $match_Argument_typestack = array('Argument');
    function match_Argument ($stack = array()) {
    	$matchrule = "Argument"; $result = $this->construct($matchrule, $matchrule, null);
    	$_214 = NULL;
    	do {
    		$res_185 = $result;
    		$pos_185 = $this->pos;
    		$matcher = 'match_'.'DollarMarkedLookup'; $key = $matcher; $pos = $this->pos;
    		$subres = ( $this->packhas( $key, $pos ) ? $this->packread( $key, $pos ) : $this->packwrite( $key, $pos, $this->$matcher(array_merge($stack, array($result))) ) );
    		if ($subres !== FALSE) {
    			$this->store( $result, $subres, "DollarMarkedLookup" );
    			$_214 = TRUE; break;
    		}
    		$result = $res_185;
    		$this->pos = $pos_185;
    		$_212 = NULL;
    		do {
    			$res_187 = $result;
    			$pos_187 = $this->pos;
    			$matcher = 'match_'.'QuotedString'; $key = $matcher; $pos = $this->pos;
    			$subres = ( $this->packhas( $key, $pos ) ? $this->packread( $key, $pos ) : $this->packwrite( $key, $pos, $this->$matcher(array_merge($stack, array($result))) ) );
    			if ($subres !== FALSE) {
    				$this->store( $result, $subres, "QuotedString" );
    				$_212 = TRUE; break;
    			}
    			$result = $res_187;
    			$this->pos = $pos_187;
    			$_210 = NULL;
    			do {
    				$res_189 = $result;
    				$pos_189 = $this->pos;
    				$matcher = 'match_'.'Null'; $key = $matcher; $pos = $this->pos;
    				$subres = ( $this->packhas( $key, $pos ) ? $this->packread( $key, $pos ) : $this->packwrite( $key, $pos, $this->$matcher(array_merge($stack, array($result))) ) );
    				if ($subres !== FALSE) {
    					$this->store( $result, $subres, "Null" );
    					$_210 = TRUE; break;
    				}
    				$result = $res_189;
    				$this->pos = $pos_189;
    				$_208 = NULL;
    				do {
    					$res_191 = $result;
    					$pos_191 = $this->pos;
    					$matcher = 'match_'.'Boolean'; $key = $matcher; $pos = $this->pos;
    					$subres = ( $this->packhas( $key, $pos ) ? $this->packread( $key, $pos ) : $this->packwrite( $key, $pos, $this->$matcher(array_merge($stack, array($result))) ) );
    					if ($subres !== FALSE) {
    						$this->store( $result, $subres, "Boolean" );
    						$_208 = TRUE; break;
    					}
    					$result = $res_191;
    					$this->pos = $pos_191;
    					$_206 = NULL;
    					do {
    						$res_193 = $result;
    						$pos_193 = $this->pos;
    						$matcher = 'match_'.'IntegerOrFloat'; $key = $matcher; $pos = $this->pos;
    						$subres = ( $this->packhas( $key, $pos ) ? $this->packread( $key, $pos ) : $this->packwrite( $key, $pos, $this->$matcher(array_merge($stack, array($result))) ) );
    						if ($subres !== FALSE) {
    							$this->store( $result, $subres, "IntegerOrFloat" );
    							$_206 = TRUE; break;
    						}
    						$result = $res_193;
    						$this->pos = $pos_193;
    						$_204 = NULL;
    						do {
    							$res_195 = $result;
    							$pos_195 = $this->pos;
    							$_201 = NULL;
    							do {
    								$matcher = 'match_'.'Lookup'; $key = $matcher; $pos = $this->pos;
    								$subres = ( $this->packhas( $key, $pos ) ? $this->packread( $key, $pos ) : $this->packwrite( $key, $pos, $this->$matcher(array_merge($stack, array($result))) ) );
    								if ($subres !== FALSE) {
    									$this->store( $result, $subres, "Lookup" );
    								}
    								else { $_201 = FALSE; break; }
    								$res_200 = $result;
    								$pos_200 = $this->pos;
    								$_199 = NULL;
    								do {
    									if (( $subres = $this->whitespace(  ) ) !== FALSE) {
    										$result["text"] .= $subres;
    									}
    									$matcher = 'match_'.'FreeString'; $key = $matcher; $pos = $this->pos;
    									$subres = ( $this->packhas( $key, $pos ) ? $this->packread( $key, $pos ) : $this->packwrite( $key, $pos, $this->$matcher(array_merge($stack, array($result))) ) );
    									if ($subres !== FALSE) {
    										$this->store( $result, $subres );
    									}
    									else { $_199 = FALSE; break; }
    									$_199 = TRUE; break;
    								}
    								while(0);
    								if( $_199 === TRUE ) {
    									$result = $res_200;
    									$this->pos = $pos_200;
    									$_201 = FALSE; break;
    								}
    								if( $_199 === FALSE) {
    									$result = $res_200;
    									$this->pos = $pos_200;
    								}
    								$_201 = TRUE; break;
    							}
    							while(0);
    							if( $_201 === TRUE ) { $_204 = TRUE; break; }
    							$result = $res_195;
    							$this->pos = $pos_195;
    							$matcher = 'match_'.'FreeString'; $key = $matcher; $pos = $this->pos;
    							$subres = ( $this->packhas( $key, $pos ) ? $this->packread( $key, $pos ) : $this->packwrite( $key, $pos, $this->$matcher(array_merge($stack, array($result))) ) );
    							if ($subres !== FALSE) {
    								$this->store( $result, $subres, "FreeString" );
    								$_204 = TRUE; break;
    							}
    							$result = $res_195;
    							$this->pos = $pos_195;
    							$_204 = FALSE; break;
    						}
    						while(0);
    						if( $_204 === TRUE ) { $_206 = TRUE; break; }
    						$result = $res_193;
    						$this->pos = $pos_193;
    						$_206 = FALSE; break;
    					}
    					while(0);
    					if( $_206 === TRUE ) { $_208 = TRUE; break; }
    					$result = $res_191;
    					$this->pos = $pos_191;
    					$_208 = FALSE; break;
    				}
    				while(0);
    				if( $_208 === TRUE ) { $_210 = TRUE; break; }
    				$result = $res_189;
    				$this->pos = $pos_189;
    				$_210 = FALSE; break;
    			}
    			while(0);
    			if( $_210 === TRUE ) { $_212 = TRUE; break; }
    			$result = $res_187;
    			$this->pos = $pos_187;
    			$_212 = FALSE; break;
    		}
    		while(0);
    		if( $_212 === TRUE ) { $_214 = TRUE; break; }
    		$result = $res_185;
    		$this->pos = $pos_185;
    		$_214 = FALSE; break;
    	}
    	while(0);
    	if( $_214 === TRUE ) { return $this->finalise($result); }
    	if( $_214 === FALSE) { return FALSE; }
    }




    /**
     * If we get a bare value, we don't know enough to determine exactly what php would be the translation, because
     * we don't know if the position of use indicates a lookup or a string argument.
     *
     * Instead, we record 'ArgumentMode' as a member of this matches results node, which can be:
     *   - lookup if this argument was unambiguously a lookup (marked as such)
     *   - string is this argument was unambiguously a string (marked as such, or impossible to parse as lookup)
     *   - default if this argument needs to be handled as per 2.4
     *
     * In the case of 'default', there is no php member of the results node, but instead 'lookup_php', which
     * should be used by the parent if the context indicates a lookup, and 'string_php' which should be used
     * if the context indicates a string
     */

    function Argument_DollarMarkedLookup(&$res, $sub)
    {
        $res['ArgumentMode'] = 'lookup';
        $res['php'] = $sub['Lookup']['php'];
    }

    function Argument_QuotedString(&$res, $sub)
    {
        $res['ArgumentMode'] = 'string';
        $res['php'] = "'" . str_replace("'", "\\'", $sub['String']['text'] ?? '') . "'";
    }

    function Argument_Null(&$res, $sub)
    {
        $res['ArgumentMode'] = 'string';
        $res['php'] = $sub['text'];
    }

    function Argument_Boolean(&$res, $sub)
    {
        $res['ArgumentMode'] = 'string';
        $res['php'] = $sub['text'];
    }

    function Argument_IntegerOrFloat(&$res, $sub)
    {
        $res['ArgumentMode'] = 'string';
        $res['php'] = $sub['text'];
    }

    function Argument_Lookup(&$res, $sub)
    {
        if (count($sub['LookupSteps'] ?? []) == 1 && !isset($sub['LookupSteps'][0]['Call']['Arguments'])) {
            $res['ArgumentMode'] = 'default';
            $res['lookup_php'] = $sub['php'];
            $res['string_php'] = "'".$sub['LookupSteps'][0]['Call']['Method']['text']."'";
        } else {
            $res['ArgumentMode'] = 'lookup';
            $res['php'] = $sub['php'];
        }
    }

    function Argument_FreeString(&$res, $sub)
    {
        $res['ArgumentMode'] = 'string';
        $res['php'] = "'" . str_replace("'", "\\'", trim($sub['text'] ?? '')) . "'";
    }

    /* ComparisonOperator: "!=" | "==" | ">=" | ">" | "<=" | "<" | "=" */
    protected $match_ComparisonOperator_typestack = array('ComparisonOperator');
    function match_ComparisonOperator ($stack = array()) {
    	$matchrule = "ComparisonOperator"; $result = $this->construct($matchrule, $matchrule, null);
    	$_239 = NULL;
    	do {
    		$res_216 = $result;
    		$pos_216 = $this->pos;
    		if (( $subres = $this->literal( '!=' ) ) !== FALSE) {
    			$result["text"] .= $subres;
    			$_239 = TRUE; break;
    		}
    		$result = $res_216;
    		$this->pos = $pos_216;
    		$_237 = NULL;
    		do {
    			$res_218 = $result;
    			$pos_218 = $this->pos;
    			if (( $subres = $this->literal( '==' ) ) !== FALSE) {
    				$result["text"] .= $subres;
    				$_237 = TRUE; break;
    			}
    			$result = $res_218;
    			$this->pos = $pos_218;
    			$_235 = NULL;
    			do {
    				$res_220 = $result;
    				$pos_220 = $this->pos;
    				if (( $subres = $this->literal( '>=' ) ) !== FALSE) {
    					$result["text"] .= $subres;
    					$_235 = TRUE; break;
    				}
    				$result = $res_220;
    				$this->pos = $pos_220;
    				$_233 = NULL;
    				do {
    					$res_222 = $result;
    					$pos_222 = $this->pos;
    					if (substr($this->string ?? '',$this->pos ?? 0,1) == '>') {
    						$this->pos += 1;
    						$result["text"] .= '>';
    						$_233 = TRUE; break;
    					}
    					$result = $res_222;
    					$this->pos = $pos_222;
    					$_231 = NULL;
    					do {
    						$res_224 = $result;
    						$pos_224 = $this->pos;
    						if (( $subres = $this->literal( '<=' ) ) !== FALSE) {
    							$result["text"] .= $subres;
    							$_231 = TRUE; break;
    						}
    						$result = $res_224;
    						$this->pos = $pos_224;
    						$_229 = NULL;
    						do {
    							$res_226 = $result;
    							$pos_226 = $this->pos;
    							if (substr($this->string ?? '',$this->pos ?? 0,1) == '<') {
    								$this->pos += 1;
    								$result["text"] .= '<';
    								$_229 = TRUE; break;
    							}
    							$result = $res_226;
    							$this->pos = $pos_226;
    							if (substr($this->string ?? '',$this->pos ?? 0,1) == '=') {
    								$this->pos += 1;
    								$result["text"] .= '=';
    								$_229 = TRUE; break;
    							}
    							$result = $res_226;
    							$this->pos = $pos_226;
    							$_229 = FALSE; break;
    						}
    						while(0);
    						if( $_229 === TRUE ) { $_231 = TRUE; break; }
    						$result = $res_224;
    						$this->pos = $pos_224;
    						$_231 = FALSE; break;
    					}
    					while(0);
    					if( $_231 === TRUE ) { $_233 = TRUE; break; }
    					$result = $res_222;
    					$this->pos = $pos_222;
    					$_233 = FALSE; break;
    				}
    				while(0);
    				if( $_233 === TRUE ) { $_235 = TRUE; break; }
    				$result = $res_220;
    				$this->pos = $pos_220;
    				$_235 = FALSE; break;
    			}
    			while(0);
    			if( $_235 === TRUE ) { $_237 = TRUE; break; }
    			$result = $res_218;
    			$this->pos = $pos_218;
    			$_237 = FALSE; break;
    		}
    		while(0);
    		if( $_237 === TRUE ) { $_239 = TRUE; break; }
    		$result = $res_216;
    		$this->pos = $pos_216;
    		$_239 = FALSE; break;
    	}
    	while(0);
    	if( $_239 === TRUE ) { return $this->finalise($result); }
    	if( $_239 === FALSE) { return FALSE; }
    }


    /* Comparison: Argument < ComparisonOperator > Argument */
    protected $match_Comparison_typestack = array('Comparison');
    function match_Comparison ($stack = array()) {
    	$matchrule = "Comparison"; $result = $this->construct($matchrule, $matchrule, null);
    	$_246 = NULL;
    	do {
    		$matcher = 'match_'.'Argument'; $key = $matcher; $pos = $this->pos;
    		$subres = ( $this->packhas( $key, $pos ) ? $this->packread( $key, $pos ) : $this->packwrite( $key, $pos, $this->$matcher(array_merge($stack, array($result))) ) );
    		if ($subres !== FALSE) {
    			$this->store( $result, $subres );
    		}
    		else { $_246 = FALSE; break; }
    		if (( $subres = $this->whitespace(  ) ) !== FALSE) { $result["text"] .= $subres; }
    		$matcher = 'match_'.'ComparisonOperator'; $key = $matcher; $pos = $this->pos;
    		$subres = ( $this->packhas( $key, $pos ) ? $this->packread( $key, $pos ) : $this->packwrite( $key, $pos, $this->$matcher(array_merge($stack, array($result))) ) );
    		if ($subres !== FALSE) {
    			$this->store( $result, $subres );
    		}
    		else { $_246 = FALSE; break; }
    		if (( $subres = $this->whitespace(  ) ) !== FALSE) { $result["text"] .= $subres; }
    		$matcher = 'match_'.'Argument'; $key = $matcher; $pos = $this->pos;
    		$subres = ( $this->packhas( $key, $pos ) ? $this->packread( $key, $pos ) : $this->packwrite( $key, $pos, $this->$matcher(array_merge($stack, array($result))) ) );
    		if ($subres !== FALSE) {
    			$this->store( $result, $subres );
    		}
    		else { $_246 = FALSE; break; }
    		$_246 = TRUE; break;
    	}
    	while(0);
    	if( $_246 === TRUE ) { return $this->finalise($result); }
    	if( $_246 === FALSE) { return FALSE; }
    }



    function Comparison_Argument(&$res, $sub)
    {
        if ($sub['ArgumentMode'] == 'default') {
            if (!empty($res['php'])) {
                $res['php'] .= $sub['string_php'];
            } else {
                $res['php'] = str_replace('$$FINAL', 'getOutputValue', $sub['lookup_php'] ?? '');
            }
        } else {
            $res['php'] .= str_replace('$$FINAL', 'getOutputValue', $sub['php'] ?? '');
        }
    }

    function Comparison_ComparisonOperator(&$res, $sub)
    {
        $res['php'] .= ($sub['text'] == '=' ? '==' : $sub['text']);
    }

    /* PresenceCheck: (Not:'not' <)? Argument */
    protected $match_PresenceCheck_typestack = array('PresenceCheck');
    function match_PresenceCheck ($stack = array()) {
    	$matchrule = "PresenceCheck"; $result = $this->construct($matchrule, $matchrule, null);
    	$_253 = NULL;
    	do {
    		$res_251 = $result;
    		$pos_251 = $this->pos;
    		$_250 = NULL;
    		do {
    			$stack[] = $result; $result = $this->construct( $matchrule, "Not" ); 
    			if (( $subres = $this->literal( 'not' ) ) !== FALSE) {
    				$result["text"] .= $subres;
    				$subres = $result; $result = array_pop($stack);
    				$this->store( $result, $subres, 'Not' );
    			}
    			else {
    				$result = array_pop($stack);
    				$_250 = FALSE; break;
    			}
    			if (( $subres = $this->whitespace(  ) ) !== FALSE) { $result["text"] .= $subres; }
    			$_250 = TRUE; break;
    		}
    		while(0);
    		if( $_250 === FALSE) {
    			$result = $res_251;
    			$this->pos = $pos_251;
    			unset( $res_251 );
    			unset( $pos_251 );
    		}
    		$matcher = 'match_'.'Argument'; $key = $matcher; $pos = $this->pos;
    		$subres = ( $this->packhas( $key, $pos ) ? $this->packread( $key, $pos ) : $this->packwrite( $key, $pos, $this->$matcher(array_merge($stack, array($result))) ) );
    		if ($subres !== FALSE) {
    			$this->store( $result, $subres );
    		}
    		else { $_253 = FALSE; break; }
    		$_253 = TRUE; break;
    	}
    	while(0);
    	if( $_253 === TRUE ) { return $this->finalise($result); }
    	if( $_253 === FALSE) { return FALSE; }
    }



    function PresenceCheck_Not(&$res, $sub)
    {
        $res['php'] = '!';
    }

    function PresenceCheck_Argument(&$res, $sub)
    {
        if ($sub['ArgumentMode'] == 'string') {
            $res['php'] .= '((bool)'.$sub['php'].')';
        } else {
            $php = ($sub['ArgumentMode'] == 'default' ? $sub['lookup_php'] : $sub['php']);
            $res['php'] .= str_replace('$$FINAL', 'hasValue', $php ?? '');
        }
    }

    /* IfArgumentPortion: Comparison | PresenceCheck */
    protected $match_IfArgumentPortion_typestack = array('IfArgumentPortion');
    function match_IfArgumentPortion ($stack = array()) {
    	$matchrule = "IfArgumentPortion"; $result = $this->construct($matchrule, $matchrule, null);
    	$_258 = NULL;
    	do {
    		$res_255 = $result;
    		$pos_255 = $this->pos;
    		$matcher = 'match_'.'Comparison'; $key = $matcher; $pos = $this->pos;
    		$subres = ( $this->packhas( $key, $pos ) ? $this->packread( $key, $pos ) : $this->packwrite( $key, $pos, $this->$matcher(array_merge($stack, array($result))) ) );
    		if ($subres !== FALSE) {
    			$this->store( $result, $subres );
    			$_258 = TRUE; break;
    		}
    		$result = $res_255;
    		$this->pos = $pos_255;
    		$matcher = 'match_'.'PresenceCheck'; $key = $matcher; $pos = $this->pos;
    		$subres = ( $this->packhas( $key, $pos ) ? $this->packread( $key, $pos ) : $this->packwrite( $key, $pos, $this->$matcher(array_merge($stack, array($result))) ) );
    		if ($subres !== FALSE) {
    			$this->store( $result, $subres );
    			$_258 = TRUE; break;
    		}
    		$result = $res_255;
    		$this->pos = $pos_255;
    		$_258 = FALSE; break;
    	}
    	while(0);
    	if( $_258 === TRUE ) { return $this->finalise($result); }
    	if( $_258 === FALSE) { return FALSE; }
    }



    function IfArgumentPortion_STR(&$res, $sub)
    {
        $res['php'] = $sub['php'];
    }

    /* BooleanOperator: "||" | "&&" */
    protected $match_BooleanOperator_typestack = array('BooleanOperator');
    function match_BooleanOperator ($stack = array()) {
    	$matchrule = "BooleanOperator"; $result = $this->construct($matchrule, $matchrule, null);
    	$_263 = NULL;
    	do {
    		$res_260 = $result;
    		$pos_260 = $this->pos;
    		if (( $subres = $this->literal( '||' ) ) !== FALSE) {
    			$result["text"] .= $subres;
    			$_263 = TRUE; break;
    		}
    		$result = $res_260;
    		$this->pos = $pos_260;
    		if (( $subres = $this->literal( '&&' ) ) !== FALSE) {
    			$result["text"] .= $subres;
    			$_263 = TRUE; break;
    		}
    		$result = $res_260;
    		$this->pos = $pos_260;
    		$_263 = FALSE; break;
    	}
    	while(0);
    	if( $_263 === TRUE ) { return $this->finalise($result); }
    	if( $_263 === FALSE) { return FALSE; }
    }


    /* IfArgument: :IfArgumentPortion ( < :BooleanOperator < :IfArgumentPortion )* */
    protected $match_IfArgument_typestack = array('IfArgument');
    function match_IfArgument ($stack = array()) {
    	$matchrule = "IfArgument"; $result = $this->construct($matchrule, $matchrule, null);
    	$_272 = NULL;
    	do {
    		$matcher = 'match_'.'IfArgumentPortion'; $key = $matcher; $pos = $this->pos;
    		$subres = ( $this->packhas( $key, $pos ) ? $this->packread( $key, $pos ) : $this->packwrite( $key, $pos, $this->$matcher(array_merge($stack, array($result))) ) );
    		if ($subres !== FALSE) {
    			$this->store( $result, $subres, "IfArgumentPortion" );
    		}
    		else { $_272 = FALSE; break; }
    		while (true) {
    			$res_271 = $result;
    			$pos_271 = $this->pos;
    			$_270 = NULL;
    			do {
    				if (( $subres = $this->whitespace(  ) ) !== FALSE) { $result["text"] .= $subres; }
    				$matcher = 'match_'.'BooleanOperator'; $key = $matcher; $pos = $this->pos;
    				$subres = ( $this->packhas( $key, $pos ) ? $this->packread( $key, $pos ) : $this->packwrite( $key, $pos, $this->$matcher(array_merge($stack, array($result))) ) );
    				if ($subres !== FALSE) {
    					$this->store( $result, $subres, "BooleanOperator" );
    				}
    				else { $_270 = FALSE; break; }
    				if (( $subres = $this->whitespace(  ) ) !== FALSE) { $result["text"] .= $subres; }
    				$matcher = 'match_'.'IfArgumentPortion'; $key = $matcher; $pos = $this->pos;
    				$subres = ( $this->packhas( $key, $pos ) ? $this->packread( $key, $pos ) : $this->packwrite( $key, $pos, $this->$matcher(array_merge($stack, array($result))) ) );
    				if ($subres !== FALSE) {
    					$this->store( $result, $subres, "IfArgumentPortion" );
    				}
    				else { $_270 = FALSE; break; }
    				$_270 = TRUE; break;
    			}
    			while(0);
    			if( $_270 === FALSE) {
    				$result = $res_271;
    				$this->pos = $pos_271;
    				unset( $res_271 );
    				unset( $pos_271 );
    				break;
    			}
    		}
    		$_272 = TRUE; break;
    	}
    	while(0);
    	if( $_272 === TRUE ) { return $this->finalise($result); }
    	if( $_272 === FALSE) { return FALSE; }
    }



    function IfArgument_IfArgumentPortion(&$res, $sub)
    {
        $res['php'] .= $sub['php'];
    }

    function IfArgument_BooleanOperator(&$res, $sub)
    {
        $res['php'] .= $sub['text'];
    }

    /* IfPart: '<%' < 'if' [ :IfArgument > '%>' Template:$TemplateMatcher? */
    protected $match_IfPart_typestack = array('IfPart');
    function match_IfPart ($stack = array()) {
    	$matchrule = "IfPart"; $result = $this->construct($matchrule, $matchrule, null);
    	$_282 = NULL;
    	do {
    		if (( $subres = $this->literal( '<%' ) ) !== FALSE) { $result["text"] .= $subres; }
    		else { $_282 = FALSE; break; }
    		if (( $subres = $this->whitespace(  ) ) !== FALSE) { $result["text"] .= $subres; }
    		if (( $subres = $this->literal( 'if' ) ) !== FALSE) { $result["text"] .= $subres; }
    		else { $_282 = FALSE; break; }
    		if (( $subres = $this->whitespace(  ) ) !== FALSE) { $result["text"] .= $subres; }
    		else { $_282 = FALSE; break; }
    		$matcher = 'match_'.'IfArgument'; $key = $matcher; $pos = $this->pos;
    		$subres = ( $this->packhas( $key, $pos ) ? $this->packread( $key, $pos ) : $this->packwrite( $key, $pos, $this->$matcher(array_merge($stack, array($result))) ) );
    		if ($subres !== FALSE) {
    			$this->store( $result, $subres, "IfArgument" );
    		}
    		else { $_282 = FALSE; break; }
    		if (( $subres = $this->whitespace(  ) ) !== FALSE) { $result["text"] .= $subres; }
    		if (( $subres = $this->literal( '%>' ) ) !== FALSE) { $result["text"] .= $subres; }
    		else { $_282 = FALSE; break; }
    		$res_281 = $result;
    		$pos_281 = $this->pos;
    		$matcher = 'match_'.$this->expression($result, $stack, 'TemplateMatcher'); $key = $matcher; $pos = $this->pos;
    		$subres = ( $this->packhas( $key, $pos ) ? $this->packread( $key, $pos ) : $this->packwrite( $key, $pos, $this->$matcher(array_merge($stack, array($result))) ) );
    		if ($subres !== FALSE) {
    			$this->store( $result, $subres, "Template" );
    		}
    		else {
    			$result = $res_281;
    			$this->pos = $pos_281;
    			unset( $res_281 );
    			unset( $pos_281 );
    		}
    		$_282 = TRUE; break;
    	}
    	while(0);
    	if( $_282 === TRUE ) { return $this->finalise($result); }
    	if( $_282 === FALSE) { return FALSE; }
    }


    /* ElseIfPart: '<%' < 'else_if' [ :IfArgument > '%>' Template:$TemplateMatcher? */
    protected $match_ElseIfPart_typestack = array('ElseIfPart');
    function match_ElseIfPart ($stack = array()) {
    	$matchrule = "ElseIfPart"; $result = $this->construct($matchrule, $matchrule, null);
    	$_292 = NULL;
    	do {
    		if (( $subres = $this->literal( '<%' ) ) !== FALSE) { $result["text"] .= $subres; }
    		else { $_292 = FALSE; break; }
    		if (( $subres = $this->whitespace(  ) ) !== FALSE) { $result["text"] .= $subres; }
    		if (( $subres = $this->literal( 'else_if' ) ) !== FALSE) { $result["text"] .= $subres; }
    		else { $_292 = FALSE; break; }
    		if (( $subres = $this->whitespace(  ) ) !== FALSE) { $result["text"] .= $subres; }
    		else { $_292 = FALSE; break; }
    		$matcher = 'match_'.'IfArgument'; $key = $matcher; $pos = $this->pos;
    		$subres = ( $this->packhas( $key, $pos ) ? $this->packread( $key, $pos ) : $this->packwrite( $key, $pos, $this->$matcher(array_merge($stack, array($result))) ) );
    		if ($subres !== FALSE) {
    			$this->store( $result, $subres, "IfArgument" );
    		}
    		else { $_292 = FALSE; break; }
    		if (( $subres = $this->whitespace(  ) ) !== FALSE) { $result["text"] .= $subres; }
    		if (( $subres = $this->literal( '%>' ) ) !== FALSE) { $result["text"] .= $subres; }
    		else { $_292 = FALSE; break; }
    		$res_291 = $result;
    		$pos_291 = $this->pos;
    		$matcher = 'match_'.$this->expression($result, $stack, 'TemplateMatcher'); $key = $matcher; $pos = $this->pos;
    		$subres = ( $this->packhas( $key, $pos ) ? $this->packread( $key, $pos ) : $this->packwrite( $key, $pos, $this->$matcher(array_merge($stack, array($result))) ) );
    		if ($subres !== FALSE) {
    			$this->store( $result, $subres, "Template" );
    		}
    		else {
    			$result = $res_291;
    			$this->pos = $pos_291;
    			unset( $res_291 );
    			unset( $pos_291 );
    		}
    		$_292 = TRUE; break;
    	}
    	while(0);
    	if( $_292 === TRUE ) { return $this->finalise($result); }
    	if( $_292 === FALSE) { return FALSE; }
    }


    /* ElsePart: '<%' < 'else' > '%>' Template:$TemplateMatcher? */
    protected $match_ElsePart_typestack = array('ElsePart');
    function match_ElsePart ($stack = array()) {
    	$matchrule = "ElsePart"; $result = $this->construct($matchrule, $matchrule, null);
    	$_300 = NULL;
    	do {
    		if (( $subres = $this->literal( '<%' ) ) !== FALSE) { $result["text"] .= $subres; }
    		else { $_300 = FALSE; break; }
    		if (( $subres = $this->whitespace(  ) ) !== FALSE) { $result["text"] .= $subres; }
    		if (( $subres = $this->literal( 'else' ) ) !== FALSE) { $result["text"] .= $subres; }
    		else { $_300 = FALSE; break; }
    		if (( $subres = $this->whitespace(  ) ) !== FALSE) { $result["text"] .= $subres; }
    		if (( $subres = $this->literal( '%>' ) ) !== FALSE) { $result["text"] .= $subres; }
    		else { $_300 = FALSE; break; }
    		$res_299 = $result;
    		$pos_299 = $this->pos;
    		$matcher = 'match_'.$this->expression($result, $stack, 'TemplateMatcher'); $key = $matcher; $pos = $this->pos;
    		$subres = ( $this->packhas( $key, $pos ) ? $this->packread( $key, $pos ) : $this->packwrite( $key, $pos, $this->$matcher(array_merge($stack, array($result))) ) );
    		if ($subres !== FALSE) {
    			$this->store( $result, $subres, "Template" );
    		}
    		else {
    			$result = $res_299;
    			$this->pos = $pos_299;
    			unset( $res_299 );
    			unset( $pos_299 );
    		}
    		$_300 = TRUE; break;
    	}
    	while(0);
    	if( $_300 === TRUE ) { return $this->finalise($result); }
    	if( $_300 === FALSE) { return FALSE; }
    }


    /* If: IfPart ElseIfPart* ElsePart? '<%' < 'end_if' > '%>' */
    protected $match_If_typestack = array('If');
    function match_If ($stack = array()) {
    	$matchrule = "If"; $result = $this->construct($matchrule, $matchrule, null);
    	$_310 = NULL;
    	do {
    		$matcher = 'match_'.'IfPart'; $key = $matcher; $pos = $this->pos;
    		$subres = ( $this->packhas( $key, $pos ) ? $this->packread( $key, $pos ) : $this->packwrite( $key, $pos, $this->$matcher(array_merge($stack, array($result))) ) );
    		if ($subres !== FALSE) {
    			$this->store( $result, $subres );
    		}
    		else { $_310 = FALSE; break; }
    		while (true) {
    			$res_303 = $result;
    			$pos_303 = $this->pos;
    			$matcher = 'match_'.'ElseIfPart'; $key = $matcher; $pos = $this->pos;
    			$subres = ( $this->packhas( $key, $pos ) ? $this->packread( $key, $pos ) : $this->packwrite( $key, $pos, $this->$matcher(array_merge($stack, array($result))) ) );
    			if ($subres !== FALSE) {
    				$this->store( $result, $subres );
    			}
    			else {
    				$result = $res_303;
    				$this->pos = $pos_303;
    				unset( $res_303 );
    				unset( $pos_303 );
    				break;
    			}
    		}
    		$res_304 = $result;
    		$pos_304 = $this->pos;
    		$matcher = 'match_'.'ElsePart'; $key = $matcher; $pos = $this->pos;
    		$subres = ( $this->packhas( $key, $pos ) ? $this->packread( $key, $pos ) : $this->packwrite( $key, $pos, $this->$matcher(array_merge($stack, array($result))) ) );
    		if ($subres !== FALSE) {
    			$this->store( $result, $subres );
    		}
    		else {
    			$result = $res_304;
    			$this->pos = $pos_304;
    			unset( $res_304 );
    			unset( $pos_304 );
    		}
    		if (( $subres = $this->literal( '<%' ) ) !== FALSE) { $result["text"] .= $subres; }
    		else { $_310 = FALSE; break; }
    		if (( $subres = $this->whitespace(  ) ) !== FALSE) { $result["text"] .= $subres; }
    		if (( $subres = $this->literal( 'end_if' ) ) !== FALSE) { $result["text"] .= $subres; }
    		else { $_310 = FALSE; break; }
    		if (( $subres = $this->whitespace(  ) ) !== FALSE) { $result["text"] .= $subres; }
    		if (( $subres = $this->literal( '%>' ) ) !== FALSE) { $result["text"] .= $subres; }
    		else { $_310 = FALSE; break; }
    		$_310 = TRUE; break;
    	}
    	while(0);
    	if( $_310 === TRUE ) { return $this->finalise($result); }
    	if( $_310 === FALSE) { return FALSE; }
    }



    function If_IfPart(&$res, $sub)
    {
        $res['php'] =
            'if (' . $sub['IfArgument']['php'] . ') { ' . PHP_EOL .
                (isset($sub['Template']) ? $sub['Template']['php'] : '') . PHP_EOL .
            '}';
    }

    function If_ElseIfPart(&$res, $sub)
    {
        $res['php'] .=
            'else if (' . $sub['IfArgument']['php'] . ') { ' . PHP_EOL .
                (isset($sub['Template']) ? $sub['Template']['php'] : '') . PHP_EOL .
            '}';
    }

    function If_ElsePart(&$res, $sub)
    {
        $res['php'] .=
            'else { ' . PHP_EOL .
                (isset($sub['Template']) ? $sub['Template']['php'] : '') . PHP_EOL .
            '}';
    }

    /* Require: '<%' < 'require' [ Call:(Method:Word "(" < :CallArguments  > ")") > '%>' */
    protected $match_Require_typestack = array('Require');
    function match_Require ($stack = array()) {
    	$matchrule = "Require"; $result = $this->construct($matchrule, $matchrule, null);
    	$_326 = NULL;
    	do {
    		if (( $subres = $this->literal( '<%' ) ) !== FALSE) { $result["text"] .= $subres; }
    		else { $_326 = FALSE; break; }
    		if (( $subres = $this->whitespace(  ) ) !== FALSE) { $result["text"] .= $subres; }
    		if (( $subres = $this->literal( 'require' ) ) !== FALSE) { $result["text"] .= $subres; }
    		else { $_326 = FALSE; break; }
    		if (( $subres = $this->whitespace(  ) ) !== FALSE) { $result["text"] .= $subres; }
    		else { $_326 = FALSE; break; }
    		$stack[] = $result; $result = $this->construct( $matchrule, "Call" ); 
    		$_322 = NULL;
    		do {
    			$matcher = 'match_'.'Word'; $key = $matcher; $pos = $this->pos;
    			$subres = ( $this->packhas( $key, $pos ) ? $this->packread( $key, $pos ) : $this->packwrite( $key, $pos, $this->$matcher(array_merge($stack, array($result))) ) );
    			if ($subres !== FALSE) {
    				$this->store( $result, $subres, "Method" );
    			}
    			else { $_322 = FALSE; break; }
    			if (substr($this->string ?? '',$this->pos ?? 0,1) == '(') {
    				$this->pos += 1;
    				$result["text"] .= '(';
    			}
    			else { $_322 = FALSE; break; }
    			if (( $subres = $this->whitespace(  ) ) !== FALSE) { $result["text"] .= $subres; }
    			$matcher = 'match_'.'CallArguments'; $key = $matcher; $pos = $this->pos;
    			$subres = ( $this->packhas( $key, $pos ) ? $this->packread( $key, $pos ) : $this->packwrite( $key, $pos, $this->$matcher(array_merge($stack, array($result))) ) );
    			if ($subres !== FALSE) {
    				$this->store( $result, $subres, "CallArguments" );
    			}
    			else { $_322 = FALSE; break; }
    			if (( $subres = $this->whitespace(  ) ) !== FALSE) { $result["text"] .= $subres; }
    			if (substr($this->string ?? '',$this->pos ?? 0,1) == ')') {
    				$this->pos += 1;
    				$result["text"] .= ')';
    			}
    			else { $_322 = FALSE; break; }
    			$_322 = TRUE; break;
    		}
    		while(0);
    		if( $_322 === TRUE ) {
    			$subres = $result; $result = array_pop($stack);
    			$this->store( $result, $subres, 'Call' );
    		}
    		if( $_322 === FALSE) {
    			$result = array_pop($stack);
    			$_326 = FALSE; break;
    		}
    		if (( $subres = $this->whitespace(  ) ) !== FALSE) { $result["text"] .= $subres; }
    		if (( $subres = $this->literal( '%>' ) ) !== FALSE) { $result["text"] .= $subres; }
    		else { $_326 = FALSE; break; }
    		$_326 = TRUE; break;
    	}
    	while(0);
    	if( $_326 === TRUE ) { return $this->finalise($result); }
    	if( $_326 === FALSE) { return FALSE; }
    }



    function Require_Call(&$res, $sub)
    {
        $requirements = '\\SilverStripe\\View\\Requirements';
        $res['php'] = "{$requirements}::".$sub['Method']['text'].'('.$sub['CallArguments']['php'].');';
    }


    /* CacheBlockArgument:
   !( "if " | "unless " )
    (
        :DollarMarkedLookup |
        :QuotedString |
        :Lookup
    ) */
    protected $match_CacheBlockArgument_typestack = array('CacheBlockArgument');
    function match_CacheBlockArgument ($stack = array()) {
    	$matchrule = "CacheBlockArgument"; $result = $this->construct($matchrule, $matchrule, null);
    	$_346 = NULL;
    	do {
    		$res_334 = $result;
    		$pos_334 = $this->pos;
    		$_333 = NULL;
    		do {
    			$_331 = NULL;
    			do {
    				$res_328 = $result;
    				$pos_328 = $this->pos;
    				if (( $subres = $this->literal( 'if ' ) ) !== FALSE) {
    					$result["text"] .= $subres;
    					$_331 = TRUE; break;
    				}
    				$result = $res_328;
    				$this->pos = $pos_328;
    				if (( $subres = $this->literal( 'unless ' ) ) !== FALSE) {
    					$result["text"] .= $subres;
    					$_331 = TRUE; break;
    				}
    				$result = $res_328;
    				$this->pos = $pos_328;
    				$_331 = FALSE; break;
    			}
    			while(0);
    			if( $_331 === FALSE) { $_333 = FALSE; break; }
    			$_333 = TRUE; break;
    		}
    		while(0);
    		if( $_333 === TRUE ) {
    			$result = $res_334;
    			$this->pos = $pos_334;
    			$_346 = FALSE; break;
    		}
    		if( $_333 === FALSE) {
    			$result = $res_334;
    			$this->pos = $pos_334;
    		}
    		$_344 = NULL;
    		do {
    			$_342 = NULL;
    			do {
    				$res_335 = $result;
    				$pos_335 = $this->pos;
    				$matcher = 'match_'.'DollarMarkedLookup'; $key = $matcher; $pos = $this->pos;
    				$subres = ( $this->packhas( $key, $pos ) ? $this->packread( $key, $pos ) : $this->packwrite( $key, $pos, $this->$matcher(array_merge($stack, array($result))) ) );
    				if ($subres !== FALSE) {
    					$this->store( $result, $subres, "DollarMarkedLookup" );
    					$_342 = TRUE; break;
    				}
    				$result = $res_335;
    				$this->pos = $pos_335;
    				$_340 = NULL;
    				do {
    					$res_337 = $result;
    					$pos_337 = $this->pos;
    					$matcher = 'match_'.'QuotedString'; $key = $matcher; $pos = $this->pos;
    					$subres = ( $this->packhas( $key, $pos ) ? $this->packread( $key, $pos ) : $this->packwrite( $key, $pos, $this->$matcher(array_merge($stack, array($result))) ) );
    					if ($subres !== FALSE) {
    						$this->store( $result, $subres, "QuotedString" );
    						$_340 = TRUE; break;
    					}
    					$result = $res_337;
    					$this->pos = $pos_337;
    					$matcher = 'match_'.'Lookup'; $key = $matcher; $pos = $this->pos;
    					$subres = ( $this->packhas( $key, $pos ) ? $this->packread( $key, $pos ) : $this->packwrite( $key, $pos, $this->$matcher(array_merge($stack, array($result))) ) );
    					if ($subres !== FALSE) {
    						$this->store( $result, $subres, "Lookup" );
    						$_340 = TRUE; break;
    					}
    					$result = $res_337;
    					$this->pos = $pos_337;
    					$_340 = FALSE; break;
    				}
    				while(0);
    				if( $_340 === TRUE ) { $_342 = TRUE; break; }
    				$result = $res_335;
    				$this->pos = $pos_335;
    				$_342 = FALSE; break;
    			}
    			while(0);
    			if( $_342 === FALSE) { $_344 = FALSE; break; }
    			$_344 = TRUE; break;
    		}
    		while(0);
    		if( $_344 === FALSE) { $_346 = FALSE; break; }
    		$_346 = TRUE; break;
    	}
    	while(0);
    	if( $_346 === TRUE ) { return $this->finalise($result); }
    	if( $_346 === FALSE) { return FALSE; }
    }



    function CacheBlockArgument_DollarMarkedLookup(&$res, $sub)
    {
        $res['php'] = $sub['Lookup']['php'];
    }

    function CacheBlockArgument_QuotedString(&$res, $sub)
    {
        $res['php'] = "'" . str_replace("'", "\\'", $sub['String']['text'] ?? '') . "'";
    }

    function CacheBlockArgument_Lookup(&$res, $sub)
    {
        $res['php'] = $sub['php'];
    }

    /* CacheBlockArguments: CacheBlockArgument ( < "," < CacheBlockArgument )* */
    protected $match_CacheBlockArguments_typestack = array('CacheBlockArguments');
    function match_CacheBlockArguments ($stack = array()) {
    	$matchrule = "CacheBlockArguments"; $result = $this->construct($matchrule, $matchrule, null);
    	$_355 = NULL;
    	do {
    		$matcher = 'match_'.'CacheBlockArgument'; $key = $matcher; $pos = $this->pos;
    		$subres = ( $this->packhas( $key, $pos ) ? $this->packread( $key, $pos ) : $this->packwrite( $key, $pos, $this->$matcher(array_merge($stack, array($result))) ) );
    		if ($subres !== FALSE) {
    			$this->store( $result, $subres );
    		}
    		else { $_355 = FALSE; break; }
    		while (true) {
    			$res_354 = $result;
    			$pos_354 = $this->pos;
    			$_353 = NULL;
    			do {
    				if (( $subres = $this->whitespace(  ) ) !== FALSE) { $result["text"] .= $subres; }
    				if (substr($this->string ?? '',$this->pos ?? 0,1) == ',') {
    					$this->pos += 1;
    					$result["text"] .= ',';
    				}
    				else { $_353 = FALSE; break; }
    				if (( $subres = $this->whitespace(  ) ) !== FALSE) { $result["text"] .= $subres; }
    				$matcher = 'match_'.'CacheBlockArgument'; $key = $matcher; $pos = $this->pos;
    				$subres = ( $this->packhas( $key, $pos ) ? $this->packread( $key, $pos ) : $this->packwrite( $key, $pos, $this->$matcher(array_merge($stack, array($result))) ) );
    				if ($subres !== FALSE) {
    					$this->store( $result, $subres );
    				}
    				else { $_353 = FALSE; break; }
    				$_353 = TRUE; break;
    			}
    			while(0);
    			if( $_353 === FALSE) {
    				$result = $res_354;
    				$this->pos = $pos_354;
    				unset( $res_354 );
    				unset( $pos_354 );
    				break;
    			}
    		}
    		$_355 = TRUE; break;
    	}
    	while(0);
    	if( $_355 === TRUE ) { return $this->finalise($result); }
    	if( $_355 === FALSE) { return FALSE; }
    }



    function CacheBlockArguments_CacheBlockArgument(&$res, $sub)
    {
        if (!empty($res['php'])) {
            $res['php'] .= ".'_'.";
        } else {
            $res['php'] = '';
        }

        $res['php'] .= str_replace('$$FINAL', 'getOutputValue', $sub['php'] ?? '');
    }

    /* CacheBlockTemplate: (Comment | Translate | If | Require |    Include | ClosedBlock |
    OpenBlock | MalformedBlock | MalformedBracketInjection | Injection | Text)+ */
    protected $match_CacheBlockTemplate_typestack = array('CacheBlockTemplate','Template');
    function match_CacheBlockTemplate ($stack = array()) {
    	$matchrule = "CacheBlockTemplate"; $result = $this->construct($matchrule, $matchrule, array('TemplateMatcher' => 'CacheRestrictedTemplate'));
    	$count = 0;
    	while (true) {
    		$res_399 = $result;
    		$pos_399 = $this->pos;
    		$_398 = NULL;
    		do {
    			$_396 = NULL;
    			do {
    				$res_357 = $result;
    				$pos_357 = $this->pos;
    				$matcher = 'match_'.'Comment'; $key = $matcher; $pos = $this->pos;
    				$subres = ( $this->packhas( $key, $pos ) ? $this->packread( $key, $pos ) : $this->packwrite( $key, $pos, $this->$matcher(array_merge($stack, array($result))) ) );
    				if ($subres !== FALSE) {
    					$this->store( $result, $subres );
    					$_396 = TRUE; break;
    				}
    				$result = $res_357;
    				$this->pos = $pos_357;
    				$_394 = NULL;
    				do {
    					$res_359 = $result;
    					$pos_359 = $this->pos;
    					$matcher = 'match_'.'Translate'; $key = $matcher; $pos = $this->pos;
    					$subres = ( $this->packhas( $key, $pos ) ? $this->packread( $key, $pos ) : $this->packwrite( $key, $pos, $this->$matcher(array_merge($stack, array($result))) ) );
    					if ($subres !== FALSE) {
    						$this->store( $result, $subres );
    						$_394 = TRUE; break;
    					}
    					$result = $res_359;
    					$this->pos = $pos_359;
    					$_392 = NULL;
    					do {
    						$res_361 = $result;
    						$pos_361 = $this->pos;
    						$matcher = 'match_'.'If'; $key = $matcher; $pos = $this->pos;
    						$subres = ( $this->packhas( $key, $pos ) ? $this->packread( $key, $pos ) : $this->packwrite( $key, $pos, $this->$matcher(array_merge($stack, array($result))) ) );
    						if ($subres !== FALSE) {
    							$this->store( $result, $subres );
    							$_392 = TRUE; break;
    						}
    						$result = $res_361;
    						$this->pos = $pos_361;
    						$_390 = NULL;
    						do {
    							$res_363 = $result;
    							$pos_363 = $this->pos;
    							$matcher = 'match_'.'Require'; $key = $matcher; $pos = $this->pos;
    							$subres = ( $this->packhas( $key, $pos ) ? $this->packread( $key, $pos ) : $this->packwrite( $key, $pos, $this->$matcher(array_merge($stack, array($result))) ) );
    							if ($subres !== FALSE) {
    								$this->store( $result, $subres );
    								$_390 = TRUE; break;
    							}
    							$result = $res_363;
    							$this->pos = $pos_363;
    							$_388 = NULL;
    							do {
    								$res_365 = $result;
    								$pos_365 = $this->pos;
    								$matcher = 'match_'.'Include'; $key = $matcher; $pos = $this->pos;
    								$subres = ( $this->packhas( $key, $pos ) ? $this->packread( $key, $pos ) : $this->packwrite( $key, $pos, $this->$matcher(array_merge($stack, array($result))) ) );
    								if ($subres !== FALSE) {
    									$this->store( $result, $subres );
    									$_388 = TRUE; break;
    								}
    								$result = $res_365;
    								$this->pos = $pos_365;
    								$_386 = NULL;
    								do {
    									$res_367 = $result;
    									$pos_367 = $this->pos;
    									$matcher = 'match_'.'ClosedBlock'; $key = $matcher; $pos = $this->pos;
    									$subres = ( $this->packhas( $key, $pos ) ? $this->packread( $key, $pos ) : $this->packwrite( $key, $pos, $this->$matcher(array_merge($stack, array($result))) ) );
    									if ($subres !== FALSE) {
    										$this->store( $result, $subres );
    										$_386 = TRUE; break;
    									}
    									$result = $res_367;
    									$this->pos = $pos_367;
    									$_384 = NULL;
    									do {
    										$res_369 = $result;
    										$pos_369 = $this->pos;
    										$matcher = 'match_'.'OpenBlock'; $key = $matcher; $pos = $this->pos;
    										$subres = ( $this->packhas( $key, $pos ) ? $this->packread( $key, $pos ) : $this->packwrite( $key, $pos, $this->$matcher(array_merge($stack, array($result))) ) );
    										if ($subres !== FALSE) {
    											$this->store( $result, $subres );
    											$_384 = TRUE; break;
    										}
    										$result = $res_369;
    										$this->pos = $pos_369;
    										$_382 = NULL;
    										do {
    											$res_371 = $result;
    											$pos_371 = $this->pos;
    											$matcher = 'match_'.'MalformedBlock'; $key = $matcher; $pos = $this->pos;
    											$subres = ( $this->packhas( $key, $pos ) ? $this->packread( $key, $pos ) : $this->packwrite( $key, $pos, $this->$matcher(array_merge($stack, array($result))) ) );
    											if ($subres !== FALSE) {
    												$this->store( $result, $subres );
    												$_382 = TRUE; break;
    											}
    											$result = $res_371;
    											$this->pos = $pos_371;
    											$_380 = NULL;
    											do {
    												$res_373 = $result;
    												$pos_373 = $this->pos;
    												$matcher = 'match_'.'MalformedBracketInjection'; $key = $matcher; $pos = $this->pos;
    												$subres = ( $this->packhas( $key, $pos ) ? $this->packread( $key, $pos ) : $this->packwrite( $key, $pos, $this->$matcher(array_merge($stack, array($result))) ) );
    												if ($subres !== FALSE) {
    													$this->store( $result, $subres );
    													$_380 = TRUE; break;
    												}
    												$result = $res_373;
    												$this->pos = $pos_373;
    												$_378 = NULL;
    												do {
    													$res_375 = $result;
    													$pos_375 = $this->pos;
    													$matcher = 'match_'.'Injection'; $key = $matcher; $pos = $this->pos;
    													$subres = ( $this->packhas( $key, $pos ) ? $this->packread( $key, $pos ) : $this->packwrite( $key, $pos, $this->$matcher(array_merge($stack, array($result))) ) );
    													if ($subres !== FALSE) {
    														$this->store( $result, $subres );
    														$_378 = TRUE; break;
    													}
    													$result = $res_375;
    													$this->pos = $pos_375;
    													$matcher = 'match_'.'Text'; $key = $matcher; $pos = $this->pos;
    													$subres = ( $this->packhas( $key, $pos ) ? $this->packread( $key, $pos ) : $this->packwrite( $key, $pos, $this->$matcher(array_merge($stack, array($result))) ) );
    													if ($subres !== FALSE) {
    														$this->store( $result, $subres );
    														$_378 = TRUE; break;
    													}
    													$result = $res_375;
    													$this->pos = $pos_375;
    													$_378 = FALSE; break;
    												}
    												while(0);
    												if( $_378 === TRUE ) { $_380 = TRUE; break; }
    												$result = $res_373;
    												$this->pos = $pos_373;
    												$_380 = FALSE; break;
    											}
    											while(0);
    											if( $_380 === TRUE ) { $_382 = TRUE; break; }
    											$result = $res_371;
    											$this->pos = $pos_371;
    											$_382 = FALSE; break;
    										}
    										while(0);
    										if( $_382 === TRUE ) { $_384 = TRUE; break; }
    										$result = $res_369;
    										$this->pos = $pos_369;
    										$_384 = FALSE; break;
    									}
    									while(0);
    									if( $_384 === TRUE ) { $_386 = TRUE; break; }
    									$result = $res_367;
    									$this->pos = $pos_367;
    									$_386 = FALSE; break;
    								}
    								while(0);
    								if( $_386 === TRUE ) { $_388 = TRUE; break; }
    								$result = $res_365;
    								$this->pos = $pos_365;
    								$_388 = FALSE; break;
    							}
    							while(0);
    							if( $_388 === TRUE ) { $_390 = TRUE; break; }
    							$result = $res_363;
    							$this->pos = $pos_363;
    							$_390 = FALSE; break;
    						}
    						while(0);
    						if( $_390 === TRUE ) { $_392 = TRUE; break; }
    						$result = $res_361;
    						$this->pos = $pos_361;
    						$_392 = FALSE; break;
    					}
    					while(0);
    					if( $_392 === TRUE ) { $_394 = TRUE; break; }
    					$result = $res_359;
    					$this->pos = $pos_359;
    					$_394 = FALSE; break;
    				}
    				while(0);
    				if( $_394 === TRUE ) { $_396 = TRUE; break; }
    				$result = $res_357;
    				$this->pos = $pos_357;
    				$_396 = FALSE; break;
    			}
    			while(0);
    			if( $_396 === FALSE) { $_398 = FALSE; break; }
    			$_398 = TRUE; break;
    		}
    		while(0);
    		if( $_398 === FALSE) {
    			$result = $res_399;
    			$this->pos = $pos_399;
    			unset( $res_399 );
    			unset( $pos_399 );
    			break;
    		}
    		$count += 1;
    	}
    	if ($count > 0) { return $this->finalise($result); }
    	else { return FALSE; }
    }




    /* UncachedBlock:
    '<%' < "uncached" < CacheBlockArguments? ( < Conditional:("if"|"unless") > Condition:IfArgument )? > '%>'
        Template:$TemplateMatcher?
        '<%' < 'end_' ("uncached"|"cached"|"cacheblock") > '%>' */
    protected $match_UncachedBlock_typestack = array('UncachedBlock');
    function match_UncachedBlock ($stack = array()) {
    	$matchrule = "UncachedBlock"; $result = $this->construct($matchrule, $matchrule, null);
    	$_436 = NULL;
    	do {
    		if (( $subres = $this->literal( '<%' ) ) !== FALSE) { $result["text"] .= $subres; }
    		else { $_436 = FALSE; break; }
    		if (( $subres = $this->whitespace(  ) ) !== FALSE) { $result["text"] .= $subres; }
    		if (( $subres = $this->literal( 'uncached' ) ) !== FALSE) { $result["text"] .= $subres; }
    		else { $_436 = FALSE; break; }
    		if (( $subres = $this->whitespace(  ) ) !== FALSE) { $result["text"] .= $subres; }
    		$res_404 = $result;
    		$pos_404 = $this->pos;
    		$matcher = 'match_'.'CacheBlockArguments'; $key = $matcher; $pos = $this->pos;
    		$subres = ( $this->packhas( $key, $pos ) ? $this->packread( $key, $pos ) : $this->packwrite( $key, $pos, $this->$matcher(array_merge($stack, array($result))) ) );
    		if ($subres !== FALSE) {
    			$this->store( $result, $subres );
    		}
    		else {
    			$result = $res_404;
    			$this->pos = $pos_404;
    			unset( $res_404 );
    			unset( $pos_404 );
    		}
    		$res_416 = $result;
    		$pos_416 = $this->pos;
    		$_415 = NULL;
    		do {
    			if (( $subres = $this->whitespace(  ) ) !== FALSE) { $result["text"] .= $subres; }
    			$stack[] = $result; $result = $this->construct( $matchrule, "Conditional" ); 
    			$_411 = NULL;
    			do {
    				$_409 = NULL;
    				do {
    					$res_406 = $result;
    					$pos_406 = $this->pos;
    					if (( $subres = $this->literal( 'if' ) ) !== FALSE) {
    						$result["text"] .= $subres;
    						$_409 = TRUE; break;
    					}
    					$result = $res_406;
    					$this->pos = $pos_406;
    					if (( $subres = $this->literal( 'unless' ) ) !== FALSE) {
    						$result["text"] .= $subres;
    						$_409 = TRUE; break;
    					}
    					$result = $res_406;
    					$this->pos = $pos_406;
    					$_409 = FALSE; break;
    				}
    				while(0);
    				if( $_409 === FALSE) { $_411 = FALSE; break; }
    				$_411 = TRUE; break;
    			}
    			while(0);
    			if( $_411 === TRUE ) {
    				$subres = $result; $result = array_pop($stack);
    				$this->store( $result, $subres, 'Conditional' );
    			}
    			if( $_411 === FALSE) {
    				$result = array_pop($stack);
    				$_415 = FALSE; break;
    			}
    			if (( $subres = $this->whitespace(  ) ) !== FALSE) { $result["text"] .= $subres; }
    			$matcher = 'match_'.'IfArgument'; $key = $matcher; $pos = $this->pos;
    			$subres = ( $this->packhas( $key, $pos ) ? $this->packread( $key, $pos ) : $this->packwrite( $key, $pos, $this->$matcher(array_merge($stack, array($result))) ) );
    			if ($subres !== FALSE) {
    				$this->store( $result, $subres, "Condition" );
    			}
    			else { $_415 = FALSE; break; }
    			$_415 = TRUE; break;
    		}
    		while(0);
    		if( $_415 === FALSE) {
    			$result = $res_416;
    			$this->pos = $pos_416;
    			unset( $res_416 );
    			unset( $pos_416 );
    		}
    		if (( $subres = $this->whitespace(  ) ) !== FALSE) { $result["text"] .= $subres; }
    		if (( $subres = $this->literal( '%>' ) ) !== FALSE) { $result["text"] .= $subres; }
    		else { $_436 = FALSE; break; }
    		$res_419 = $result;
    		$pos_419 = $this->pos;
    		$matcher = 'match_'.$this->expression($result, $stack, 'TemplateMatcher'); $key = $matcher; $pos = $this->pos;
    		$subres = ( $this->packhas( $key, $pos ) ? $this->packread( $key, $pos ) : $this->packwrite( $key, $pos, $this->$matcher(array_merge($stack, array($result))) ) );
    		if ($subres !== FALSE) {
    			$this->store( $result, $subres, "Template" );
    		}
    		else {
    			$result = $res_419;
    			$this->pos = $pos_419;
    			unset( $res_419 );
    			unset( $pos_419 );
    		}
    		if (( $subres = $this->literal( '<%' ) ) !== FALSE) { $result["text"] .= $subres; }
    		else { $_436 = FALSE; break; }
    		if (( $subres = $this->whitespace(  ) ) !== FALSE) { $result["text"] .= $subres; }
    		if (( $subres = $this->literal( 'end_' ) ) !== FALSE) { $result["text"] .= $subres; }
    		else { $_436 = FALSE; break; }
    		$_432 = NULL;
    		do {
    			$_430 = NULL;
    			do {
    				$res_423 = $result;
    				$pos_423 = $this->pos;
    				if (( $subres = $this->literal( 'uncached' ) ) !== FALSE) {
    					$result["text"] .= $subres;
    					$_430 = TRUE; break;
    				}
    				$result = $res_423;
    				$this->pos = $pos_423;
    				$_428 = NULL;
    				do {
    					$res_425 = $result;
    					$pos_425 = $this->pos;
    					if (( $subres = $this->literal( 'cached' ) ) !== FALSE) {
    						$result["text"] .= $subres;
    						$_428 = TRUE; break;
    					}
    					$result = $res_425;
    					$this->pos = $pos_425;
    					if (( $subres = $this->literal( 'cacheblock' ) ) !== FALSE) {
    						$result["text"] .= $subres;
    						$_428 = TRUE; break;
    					}
    					$result = $res_425;
    					$this->pos = $pos_425;
    					$_428 = FALSE; break;
    				}
    				while(0);
    				if( $_428 === TRUE ) { $_430 = TRUE; break; }
    				$result = $res_423;
    				$this->pos = $pos_423;
    				$_430 = FALSE; break;
    			}
    			while(0);
    			if( $_430 === FALSE) { $_432 = FALSE; break; }
    			$_432 = TRUE; break;
    		}
    		while(0);
    		if( $_432 === FALSE) { $_436 = FALSE; break; }
    		if (( $subres = $this->whitespace(  ) ) !== FALSE) { $result["text"] .= $subres; }
    		if (( $subres = $this->literal( '%>' ) ) !== FALSE) { $result["text"] .= $subres; }
    		else { $_436 = FALSE; break; }
    		$_436 = TRUE; break;
    	}
    	while(0);
    	if( $_436 === TRUE ) { return $this->finalise($result); }
    	if( $_436 === FALSE) { return FALSE; }
    }



    function UncachedBlock_Template(&$res, $sub)
    {
        $res['php'] = $sub['php'];
    }

    /* CacheRestrictedTemplate: (Comment | Translate | If | Require | CacheBlock | UncachedBlock | Include | ClosedBlock |
    OpenBlock | MalformedBlock | MalformedBracketInjection | Injection | Text)+ */
    protected $match_CacheRestrictedTemplate_typestack = array('CacheRestrictedTemplate','Template');
    function match_CacheRestrictedTemplate ($stack = array()) {
    	$matchrule = "CacheRestrictedTemplate"; $result = $this->construct($matchrule, $matchrule, null);
    	$count = 0;
    	while (true) {
    		$res_488 = $result;
    		$pos_488 = $this->pos;
    		$_487 = NULL;
    		do {
    			$_485 = NULL;
    			do {
    				$res_438 = $result;
    				$pos_438 = $this->pos;
    				$matcher = 'match_'.'Comment'; $key = $matcher; $pos = $this->pos;
    				$subres = ( $this->packhas( $key, $pos ) ? $this->packread( $key, $pos ) : $this->packwrite( $key, $pos, $this->$matcher(array_merge($stack, array($result))) ) );
    				if ($subres !== FALSE) {
    					$this->store( $result, $subres );
    					$_485 = TRUE; break;
    				}
    				$result = $res_438;
    				$this->pos = $pos_438;
    				$_483 = NULL;
    				do {
    					$res_440 = $result;
    					$pos_440 = $this->pos;
    					$matcher = 'match_'.'Translate'; $key = $matcher; $pos = $this->pos;
    					$subres = ( $this->packhas( $key, $pos ) ? $this->packread( $key, $pos ) : $this->packwrite( $key, $pos, $this->$matcher(array_merge($stack, array($result))) ) );
    					if ($subres !== FALSE) {
    						$this->store( $result, $subres );
    						$_483 = TRUE; break;
    					}
    					$result = $res_440;
    					$this->pos = $pos_440;
    					$_481 = NULL;
    					do {
    						$res_442 = $result;
    						$pos_442 = $this->pos;
    						$matcher = 'match_'.'If'; $key = $matcher; $pos = $this->pos;
    						$subres = ( $this->packhas( $key, $pos ) ? $this->packread( $key, $pos ) : $this->packwrite( $key, $pos, $this->$matcher(array_merge($stack, array($result))) ) );
    						if ($subres !== FALSE) {
    							$this->store( $result, $subres );
    							$_481 = TRUE; break;
    						}
    						$result = $res_442;
    						$this->pos = $pos_442;
    						$_479 = NULL;
    						do {
    							$res_444 = $result;
    							$pos_444 = $this->pos;
    							$matcher = 'match_'.'Require'; $key = $matcher; $pos = $this->pos;
    							$subres = ( $this->packhas( $key, $pos ) ? $this->packread( $key, $pos ) : $this->packwrite( $key, $pos, $this->$matcher(array_merge($stack, array($result))) ) );
    							if ($subres !== FALSE) {
    								$this->store( $result, $subres );
    								$_479 = TRUE; break;
    							}
    							$result = $res_444;
    							$this->pos = $pos_444;
    							$_477 = NULL;
    							do {
    								$res_446 = $result;
    								$pos_446 = $this->pos;
    								$matcher = 'match_'.'CacheBlock'; $key = $matcher; $pos = $this->pos;
    								$subres = ( $this->packhas( $key, $pos ) ? $this->packread( $key, $pos ) : $this->packwrite( $key, $pos, $this->$matcher(array_merge($stack, array($result))) ) );
    								if ($subres !== FALSE) {
    									$this->store( $result, $subres );
    									$_477 = TRUE; break;
    								}
    								$result = $res_446;
    								$this->pos = $pos_446;
    								$_475 = NULL;
    								do {
    									$res_448 = $result;
    									$pos_448 = $this->pos;
    									$matcher = 'match_'.'UncachedBlock'; $key = $matcher; $pos = $this->pos;
    									$subres = ( $this->packhas( $key, $pos ) ? $this->packread( $key, $pos ) : $this->packwrite( $key, $pos, $this->$matcher(array_merge($stack, array($result))) ) );
    									if ($subres !== FALSE) {
    										$this->store( $result, $subres );
    										$_475 = TRUE; break;
    									}
    									$result = $res_448;
    									$this->pos = $pos_448;
    									$_473 = NULL;
    									do {
    										$res_450 = $result;
    										$pos_450 = $this->pos;
    										$matcher = 'match_'.'Include'; $key = $matcher; $pos = $this->pos;
    										$subres = ( $this->packhas( $key, $pos ) ? $this->packread( $key, $pos ) : $this->packwrite( $key, $pos, $this->$matcher(array_merge($stack, array($result))) ) );
    										if ($subres !== FALSE) {
    											$this->store( $result, $subres );
    											$_473 = TRUE; break;
    										}
    										$result = $res_450;
    										$this->pos = $pos_450;
    										$_471 = NULL;
    										do {
    											$res_452 = $result;
    											$pos_452 = $this->pos;
    											$matcher = 'match_'.'ClosedBlock'; $key = $matcher; $pos = $this->pos;
    											$subres = ( $this->packhas( $key, $pos ) ? $this->packread( $key, $pos ) : $this->packwrite( $key, $pos, $this->$matcher(array_merge($stack, array($result))) ) );
    											if ($subres !== FALSE) {
    												$this->store( $result, $subres );
    												$_471 = TRUE; break;
    											}
    											$result = $res_452;
    											$this->pos = $pos_452;
    											$_469 = NULL;
    											do {
    												$res_454 = $result;
    												$pos_454 = $this->pos;
    												$matcher = 'match_'.'OpenBlock'; $key = $matcher; $pos = $this->pos;
    												$subres = ( $this->packhas( $key, $pos ) ? $this->packread( $key, $pos ) : $this->packwrite( $key, $pos, $this->$matcher(array_merge($stack, array($result))) ) );
    												if ($subres !== FALSE) {
    													$this->store( $result, $subres );
    													$_469 = TRUE; break;
    												}
    												$result = $res_454;
    												$this->pos = $pos_454;
    												$_467 = NULL;
    												do {
    													$res_456 = $result;
    													$pos_456 = $this->pos;
    													$matcher = 'match_'.'MalformedBlock'; $key = $matcher; $pos = $this->pos;
    													$subres = ( $this->packhas( $key, $pos ) ? $this->packread( $key, $pos ) : $this->packwrite( $key, $pos, $this->$matcher(array_merge($stack, array($result))) ) );
    													if ($subres !== FALSE) {
    														$this->store( $result, $subres );
    														$_467 = TRUE; break;
    													}
    													$result = $res_456;
    													$this->pos = $pos_456;
    													$_465 = NULL;
    													do {
    														$res_458 = $result;
    														$pos_458 = $this->pos;
    														$matcher = 'match_'.'MalformedBracketInjection'; $key = $matcher; $pos = $this->pos;
    														$subres = ( $this->packhas( $key, $pos ) ? $this->packread( $key, $pos ) : $this->packwrite( $key, $pos, $this->$matcher(array_merge($stack, array($result))) ) );
    														if ($subres !== FALSE) {
    															$this->store( $result, $subres );
    															$_465 = TRUE; break;
    														}
    														$result = $res_458;
    														$this->pos = $pos_458;
    														$_463 = NULL;
    														do {
    															$res_460 = $result;
    															$pos_460 = $this->pos;
    															$matcher = 'match_'.'Injection'; $key = $matcher; $pos = $this->pos;
    															$subres = ( $this->packhas( $key, $pos ) ? $this->packread( $key, $pos ) : $this->packwrite( $key, $pos, $this->$matcher(array_merge($stack, array($result))) ) );
    															if ($subres !== FALSE) {
    																$this->store( $result, $subres );
    																$_463 = TRUE; break;
    															}
    															$result = $res_460;
    															$this->pos = $pos_460;
    															$matcher = 'match_'.'Text'; $key = $matcher; $pos = $this->pos;
    															$subres = ( $this->packhas( $key, $pos ) ? $this->packread( $key, $pos ) : $this->packwrite( $key, $pos, $this->$matcher(array_merge($stack, array($result))) ) );
    															if ($subres !== FALSE) {
    																$this->store( $result, $subres );
    																$_463 = TRUE; break;
    															}
    															$result = $res_460;
    															$this->pos = $pos_460;
    															$_463 = FALSE; break;
    														}
    														while(0);
    														if( $_463 === TRUE ) { $_465 = TRUE; break; }
    														$result = $res_458;
    														$this->pos = $pos_458;
    														$_465 = FALSE; break;
    													}
    													while(0);
    													if( $_465 === TRUE ) { $_467 = TRUE; break; }
    													$result = $res_456;
    													$this->pos = $pos_456;
    													$_467 = FALSE; break;
    												}
    												while(0);
    												if( $_467 === TRUE ) { $_469 = TRUE; break; }
    												$result = $res_454;
    												$this->pos = $pos_454;
    												$_469 = FALSE; break;
    											}
    											while(0);
    											if( $_469 === TRUE ) { $_471 = TRUE; break; }
    											$result = $res_452;
    											$this->pos = $pos_452;
    											$_471 = FALSE; break;
    										}
    										while(0);
    										if( $_471 === TRUE ) { $_473 = TRUE; break; }
    										$result = $res_450;
    										$this->pos = $pos_450;
    										$_473 = FALSE; break;
    									}
    									while(0);
    									if( $_473 === TRUE ) { $_475 = TRUE; break; }
    									$result = $res_448;
    									$this->pos = $pos_448;
    									$_475 = FALSE; break;
    								}
    								while(0);
    								if( $_475 === TRUE ) { $_477 = TRUE; break; }
    								$result = $res_446;
    								$this->pos = $pos_446;
    								$_477 = FALSE; break;
    							}
    							while(0);
    							if( $_477 === TRUE ) { $_479 = TRUE; break; }
    							$result = $res_444;
    							$this->pos = $pos_444;
    							$_479 = FALSE; break;
    						}
    						while(0);
    						if( $_479 === TRUE ) { $_481 = TRUE; break; }
    						$result = $res_442;
    						$this->pos = $pos_442;
    						$_481 = FALSE; break;
    					}
    					while(0);
    					if( $_481 === TRUE ) { $_483 = TRUE; break; }
    					$result = $res_440;
    					$this->pos = $pos_440;
    					$_483 = FALSE; break;
    				}
    				while(0);
    				if( $_483 === TRUE ) { $_485 = TRUE; break; }
    				$result = $res_438;
    				$this->pos = $pos_438;
    				$_485 = FALSE; break;
    			}
    			while(0);
    			if( $_485 === FALSE) { $_487 = FALSE; break; }
    			$_487 = TRUE; break;
    		}
    		while(0);
    		if( $_487 === FALSE) {
    			$result = $res_488;
    			$this->pos = $pos_488;
    			unset( $res_488 );
    			unset( $pos_488 );
    			break;
    		}
    		$count += 1;
    	}
    	if ($count > 0) { return $this->finalise($result); }
    	else { return FALSE; }
    }



    function CacheRestrictedTemplate_CacheBlock(&$res, $sub)
    {
        throw new SSTemplateParseException('You cant have cache blocks nested within with, loop or control blocks ' .
            'that are within cache blocks', $this);
    }

    function CacheRestrictedTemplate_UncachedBlock(&$res, $sub)
    {
        throw new SSTemplateParseException('You cant have uncache blocks nested within with, loop or control blocks ' .
            'that are within cache blocks', $this);
    }

    /* CacheBlock:
    '<%' < CacheTag:("cached"|"cacheblock") < (CacheBlockArguments)? ( < Conditional:("if"|"unless") >
    Condition:IfArgument )? > '%>'
        (CacheBlock | UncachedBlock | CacheBlockTemplate)*
    '<%' < 'end_' ("cached"|"uncached"|"cacheblock") > '%>' */
    protected $match_CacheBlock_typestack = array('CacheBlock');
    function match_CacheBlock ($stack = array()) {
    	$matchrule = "CacheBlock"; $result = $this->construct($matchrule, $matchrule, null);
    	$_543 = NULL;
    	do {
    		if (( $subres = $this->literal( '<%' ) ) !== FALSE) { $result["text"] .= $subres; }
    		else { $_543 = FALSE; break; }
    		if (( $subres = $this->whitespace(  ) ) !== FALSE) { $result["text"] .= $subres; }
    		$stack[] = $result; $result = $this->construct( $matchrule, "CacheTag" ); 
    		$_496 = NULL;
    		do {
    			$_494 = NULL;
    			do {
    				$res_491 = $result;
    				$pos_491 = $this->pos;
    				if (( $subres = $this->literal( 'cached' ) ) !== FALSE) {
    					$result["text"] .= $subres;
    					$_494 = TRUE; break;
    				}
    				$result = $res_491;
    				$this->pos = $pos_491;
    				if (( $subres = $this->literal( 'cacheblock' ) ) !== FALSE) {
    					$result["text"] .= $subres;
    					$_494 = TRUE; break;
    				}
    				$result = $res_491;
    				$this->pos = $pos_491;
    				$_494 = FALSE; break;
    			}
    			while(0);
    			if( $_494 === FALSE) { $_496 = FALSE; break; }
    			$_496 = TRUE; break;
    		}
    		while(0);
    		if( $_496 === TRUE ) {
    			$subres = $result; $result = array_pop($stack);
    			$this->store( $result, $subres, 'CacheTag' );
    		}
    		if( $_496 === FALSE) {
    			$result = array_pop($stack);
    			$_543 = FALSE; break;
    		}
    		if (( $subres = $this->whitespace(  ) ) !== FALSE) { $result["text"] .= $subres; }
    		$res_501 = $result;
    		$pos_501 = $this->pos;
    		$_500 = NULL;
    		do {
    			$matcher = 'match_'.'CacheBlockArguments'; $key = $matcher; $pos = $this->pos;
    			$subres = ( $this->packhas( $key, $pos ) ? $this->packread( $key, $pos ) : $this->packwrite( $key, $pos, $this->$matcher(array_merge($stack, array($result))) ) );
    			if ($subres !== FALSE) {
    				$this->store( $result, $subres );
    			}
    			else { $_500 = FALSE; break; }
    			$_500 = TRUE; break;
    		}
    		while(0);
    		if( $_500 === FALSE) {
    			$result = $res_501;
    			$this->pos = $pos_501;
    			unset( $res_501 );
    			unset( $pos_501 );
    		}
    		$res_513 = $result;
    		$pos_513 = $this->pos;
    		$_512 = NULL;
    		do {
    			if (( $subres = $this->whitespace(  ) ) !== FALSE) { $result["text"] .= $subres; }
    			$stack[] = $result; $result = $this->construct( $matchrule, "Conditional" ); 
    			$_508 = NULL;
    			do {
    				$_506 = NULL;
    				do {
    					$res_503 = $result;
    					$pos_503 = $this->pos;
    					if (( $subres = $this->literal( 'if' ) ) !== FALSE) {
    						$result["text"] .= $subres;
    						$_506 = TRUE; break;
    					}
    					$result = $res_503;
    					$this->pos = $pos_503;
    					if (( $subres = $this->literal( 'unless' ) ) !== FALSE) {
    						$result["text"] .= $subres;
    						$_506 = TRUE; break;
    					}
    					$result = $res_503;
    					$this->pos = $pos_503;
    					$_506 = FALSE; break;
    				}
    				while(0);
    				if( $_506 === FALSE) { $_508 = FALSE; break; }
    				$_508 = TRUE; break;
    			}
    			while(0);
    			if( $_508 === TRUE ) {
    				$subres = $result; $result = array_pop($stack);
    				$this->store( $result, $subres, 'Conditional' );
    			}
    			if( $_508 === FALSE) {
    				$result = array_pop($stack);
    				$_512 = FALSE; break;
    			}
    			if (( $subres = $this->whitespace(  ) ) !== FALSE) { $result["text"] .= $subres; }
    			$matcher = 'match_'.'IfArgument'; $key = $matcher; $pos = $this->pos;
    			$subres = ( $this->packhas( $key, $pos ) ? $this->packread( $key, $pos ) : $this->packwrite( $key, $pos, $this->$matcher(array_merge($stack, array($result))) ) );
    			if ($subres !== FALSE) {
    				$this->store( $result, $subres, "Condition" );
    			}
    			else { $_512 = FALSE; break; }
    			$_512 = TRUE; break;
    		}
    		while(0);
    		if( $_512 === FALSE) {
    			$result = $res_513;
    			$this->pos = $pos_513;
    			unset( $res_513 );
    			unset( $pos_513 );
    		}
    		if (( $subres = $this->whitespace(  ) ) !== FALSE) { $result["text"] .= $subres; }
    		if (( $subres = $this->literal( '%>' ) ) !== FALSE) { $result["text"] .= $subres; }
    		else { $_543 = FALSE; break; }
    		while (true) {
    			$res_526 = $result;
    			$pos_526 = $this->pos;
    			$_525 = NULL;
    			do {
    				$_523 = NULL;
    				do {
    					$res_516 = $result;
    					$pos_516 = $this->pos;
    					$matcher = 'match_'.'CacheBlock'; $key = $matcher; $pos = $this->pos;
    					$subres = ( $this->packhas( $key, $pos ) ? $this->packread( $key, $pos ) : $this->packwrite( $key, $pos, $this->$matcher(array_merge($stack, array($result))) ) );
    					if ($subres !== FALSE) {
    						$this->store( $result, $subres );
    						$_523 = TRUE; break;
    					}
    					$result = $res_516;
    					$this->pos = $pos_516;
    					$_521 = NULL;
    					do {
    						$res_518 = $result;
    						$pos_518 = $this->pos;
    						$matcher = 'match_'.'UncachedBlock'; $key = $matcher; $pos = $this->pos;
    						$subres = ( $this->packhas( $key, $pos ) ? $this->packread( $key, $pos ) : $this->packwrite( $key, $pos, $this->$matcher(array_merge($stack, array($result))) ) );
    						if ($subres !== FALSE) {
    							$this->store( $result, $subres );
    							$_521 = TRUE; break;
    						}
    						$result = $res_518;
    						$this->pos = $pos_518;
    						$matcher = 'match_'.'CacheBlockTemplate'; $key = $matcher; $pos = $this->pos;
    						$subres = ( $this->packhas( $key, $pos ) ? $this->packread( $key, $pos ) : $this->packwrite( $key, $pos, $this->$matcher(array_merge($stack, array($result))) ) );
    						if ($subres !== FALSE) {
    							$this->store( $result, $subres );
    							$_521 = TRUE; break;
    						}
    						$result = $res_518;
    						$this->pos = $pos_518;
    						$_521 = FALSE; break;
    					}
    					while(0);
    					if( $_521 === TRUE ) { $_523 = TRUE; break; }
    					$result = $res_516;
    					$this->pos = $pos_516;
    					$_523 = FALSE; break;
    				}
    				while(0);
    				if( $_523 === FALSE) { $_525 = FALSE; break; }
    				$_525 = TRUE; break;
    			}
    			while(0);
    			if( $_525 === FALSE) {
    				$result = $res_526;
    				$this->pos = $pos_526;
    				unset( $res_526 );
    				unset( $pos_526 );
    				break;
    			}
    		}
    		if (( $subres = $this->literal( '<%' ) ) !== FALSE) { $result["text"] .= $subres; }
    		else { $_543 = FALSE; break; }
    		if (( $subres = $this->whitespace(  ) ) !== FALSE) { $result["text"] .= $subres; }
    		if (( $subres = $this->literal( 'end_' ) ) !== FALSE) { $result["text"] .= $subres; }
    		else { $_543 = FALSE; break; }
    		$_539 = NULL;
    		do {
    			$_537 = NULL;
    			do {
    				$res_530 = $result;
    				$pos_530 = $this->pos;
    				if (( $subres = $this->literal( 'cached' ) ) !== FALSE) {
    					$result["text"] .= $subres;
    					$_537 = TRUE; break;
    				}
    				$result = $res_530;
    				$this->pos = $pos_530;
    				$_535 = NULL;
    				do {
    					$res_532 = $result;
    					$pos_532 = $this->pos;
    					if (( $subres = $this->literal( 'uncached' ) ) !== FALSE) {
    						$result["text"] .= $subres;
    						$_535 = TRUE; break;
    					}
    					$result = $res_532;
    					$this->pos = $pos_532;
    					if (( $subres = $this->literal( 'cacheblock' ) ) !== FALSE) {
    						$result["text"] .= $subres;
    						$_535 = TRUE; break;
    					}
    					$result = $res_532;
    					$this->pos = $pos_532;
    					$_535 = FALSE; break;
    				}
    				while(0);
    				if( $_535 === TRUE ) { $_537 = TRUE; break; }
    				$result = $res_530;
    				$this->pos = $pos_530;
    				$_537 = FALSE; break;
    			}
    			while(0);
    			if( $_537 === FALSE) { $_539 = FALSE; break; }
    			$_539 = TRUE; break;
    		}
    		while(0);
    		if( $_539 === FALSE) { $_543 = FALSE; break; }
    		if (( $subres = $this->whitespace(  ) ) !== FALSE) { $result["text"] .= $subres; }
    		if (( $subres = $this->literal( '%>' ) ) !== FALSE) { $result["text"] .= $subres; }
    		else { $_543 = FALSE; break; }
    		$_543 = TRUE; break;
    	}
    	while(0);
    	if( $_543 === TRUE ) { return $this->finalise($result); }
    	if( $_543 === FALSE) { return FALSE; }
    }



    function CacheBlock__construct(&$res)
    {
        $res['subblocks'] = 0;
    }

    function CacheBlock_CacheBlockArguments(&$res, $sub)
    {
        $res['key'] = !empty($sub['php']) ? $sub['php'] : '';
    }

    function CacheBlock_Condition(&$res, $sub)
    {
        $res['condition'] = ($res['Conditional']['text'] == 'if' ? '(' : '!(') . $sub['php'] . ') && ';
    }

    function CacheBlock_CacheBlock(&$res, $sub)
    {
        $res['php'] .= $sub['php'];
    }

    function CacheBlock_UncachedBlock(&$res, $sub)
    {
        $res['php'] .= $sub['php'];
    }

    function CacheBlock_CacheBlockTemplate(&$res, $sub)
    {
        // Get the block counter
        $block = ++$res['subblocks'];
        // Build the key for this block from the global key (evaluated in a closure within the template),
        // the passed cache key, the block index, and the sha hash of the template.
        $res['php'] .= '$keyExpression = function() use ($scope, $cache) {' . PHP_EOL;
        $res['php'] .= '$val = \'\';' . PHP_EOL;
        if ($globalKey = SSTemplateEngine::config()->get('global_key')) {
            // Embed the code necessary to evaluate the globalKey directly into the template,
            // so that SSTemplateParser only needs to be called during template regeneration.
            // Warning: If the global key is changed, it's necessary to flush the template cache.
            $parser = Injector::inst()->get(__CLASS__, false);
            $result = $parser->compileString($globalKey, '', false, false);
            if (!$result) {
                throw new SSTemplateParseException('Unexpected problem parsing template', $parser);
            }
            $res['php'] .= $result . PHP_EOL;
        }
        $res['php'] .= 'return $val;' . PHP_EOL;
        $res['php'] .= '};' . PHP_EOL;
        $key = 'sha1($keyExpression())' // Global key
            . '.\'_' . sha1($sub['php'] ?? '') // sha of template
            . (isset($res['key']) && $res['key'] ? "_'.sha1(".$res['key'].")" : "'") // Passed key
            . ".'_$block'"; // block index
        // Get any condition
        $condition = isset($res['condition']) ? $res['condition'] : '';

        $res['php'] .= 'if ('.$condition.'($partial = $cache->get('.$key.'))) $val .= $partial;' . PHP_EOL;
        $res['php'] .= 'else { $oldval = $val; $val = "";' . PHP_EOL;
        $res['php'] .= $sub['php'] . PHP_EOL;
        $res['php'] .= $condition . ' $cache->set('.$key.', $val); $val = $oldval . $val;' . PHP_EOL;
        $res['php'] .= '}';
    }

    /* NamedArgument: Name:Word "=" Value:Argument */
    protected $match_NamedArgument_typestack = array('NamedArgument');
    function match_NamedArgument ($stack = array()) {
    	$matchrule = "NamedArgument"; $result = $this->construct($matchrule, $matchrule, null);
    	$_548 = NULL;
    	do {
    		$matcher = 'match_'.'Word'; $key = $matcher; $pos = $this->pos;
    		$subres = ( $this->packhas( $key, $pos ) ? $this->packread( $key, $pos ) : $this->packwrite( $key, $pos, $this->$matcher(array_merge($stack, array($result))) ) );
    		if ($subres !== FALSE) {
    			$this->store( $result, $subres, "Name" );
    		}
    		else { $_548 = FALSE; break; }
    		if (substr($this->string ?? '',$this->pos ?? 0,1) == '=') {
    			$this->pos += 1;
    			$result["text"] .= '=';
    		}
    		else { $_548 = FALSE; break; }
    		$matcher = 'match_'.'Argument'; $key = $matcher; $pos = $this->pos;
    		$subres = ( $this->packhas( $key, $pos ) ? $this->packread( $key, $pos ) : $this->packwrite( $key, $pos, $this->$matcher(array_merge($stack, array($result))) ) );
    		if ($subres !== FALSE) {
    			$this->store( $result, $subres, "Value" );
    		}
    		else { $_548 = FALSE; break; }
    		$_548 = TRUE; break;
    	}
    	while(0);
    	if( $_548 === TRUE ) { return $this->finalise($result); }
    	if( $_548 === FALSE) { return FALSE; }
    }



    function NamedArgument_Name(&$res, $sub)
    {
        $res['php'] = "'" . $sub['text'] . "' => ";
    }

    function NamedArgument_Value(&$res, $sub)
    {
        switch ($sub['ArgumentMode']) {
            case 'string':
                $res['php'] .= $sub['php'];
                break;

            case 'default':
                $res['php'] .= $sub['string_php'];
                break;

            default:
                $res['php'] .= str_replace('$$FINAL', 'scopeToIntermediateValue', $sub['php'] ?? '') . '->self()';
                break;
        }
    }

    /* Include: "<%" < "include" < Template:NamespacedWord < (NamedArgument ( < "," < NamedArgument )*)? > "%>" */
    protected $match_Include_typestack = array('Include');
    function match_Include ($stack = array()) {
    	$matchrule = "Include"; $result = $this->construct($matchrule, $matchrule, null);
    	$_567 = NULL;
    	do {
    		if (( $subres = $this->literal( '<%' ) ) !== FALSE) { $result["text"] .= $subres; }
    		else { $_567 = FALSE; break; }
    		if (( $subres = $this->whitespace(  ) ) !== FALSE) { $result["text"] .= $subres; }
    		if (( $subres = $this->literal( 'include' ) ) !== FALSE) { $result["text"] .= $subres; }
    		else { $_567 = FALSE; break; }
    		if (( $subres = $this->whitespace(  ) ) !== FALSE) { $result["text"] .= $subres; }
    		$matcher = 'match_'.'NamespacedWord'; $key = $matcher; $pos = $this->pos;
    		$subres = ( $this->packhas( $key, $pos ) ? $this->packread( $key, $pos ) : $this->packwrite( $key, $pos, $this->$matcher(array_merge($stack, array($result))) ) );
    		if ($subres !== FALSE) {
    			$this->store( $result, $subres, "Template" );
    		}
    		else { $_567 = FALSE; break; }
    		if (( $subres = $this->whitespace(  ) ) !== FALSE) { $result["text"] .= $subres; }
    		$res_564 = $result;
    		$pos_564 = $this->pos;
    		$_563 = NULL;
    		do {
    			$matcher = 'match_'.'NamedArgument'; $key = $matcher; $pos = $this->pos;
    			$subres = ( $this->packhas( $key, $pos ) ? $this->packread( $key, $pos ) : $this->packwrite( $key, $pos, $this->$matcher(array_merge($stack, array($result))) ) );
    			if ($subres !== FALSE) {
    				$this->store( $result, $subres );
    			}
    			else { $_563 = FALSE; break; }
    			while (true) {
    				$res_562 = $result;
    				$pos_562 = $this->pos;
    				$_561 = NULL;
    				do {
    					if (( $subres = $this->whitespace(  ) ) !== FALSE) { $result["text"] .= $subres; }
    					if (substr($this->string ?? '',$this->pos ?? 0,1) == ',') {
    						$this->pos += 1;
    						$result["text"] .= ',';
    					}
    					else { $_561 = FALSE; break; }
    					if (( $subres = $this->whitespace(  ) ) !== FALSE) { $result["text"] .= $subres; }
    					$matcher = 'match_'.'NamedArgument'; $key = $matcher; $pos = $this->pos;
    					$subres = ( $this->packhas( $key, $pos ) ? $this->packread( $key, $pos ) : $this->packwrite( $key, $pos, $this->$matcher(array_merge($stack, array($result))) ) );
    					if ($subres !== FALSE) {
    						$this->store( $result, $subres );
    					}
    					else { $_561 = FALSE; break; }
    					$_561 = TRUE; break;
    				}
    				while(0);
    				if( $_561 === FALSE) {
    					$result = $res_562;
    					$this->pos = $pos_562;
    					unset( $res_562 );
    					unset( $pos_562 );
    					break;
    				}
    			}
    			$_563 = TRUE; break;
    		}
    		while(0);
    		if( $_563 === FALSE) {
    			$result = $res_564;
    			$this->pos = $pos_564;
    			unset( $res_564 );
    			unset( $pos_564 );
    		}
    		if (( $subres = $this->whitespace(  ) ) !== FALSE) { $result["text"] .= $subres; }
    		if (( $subres = $this->literal( '%>' ) ) !== FALSE) { $result["text"] .= $subres; }
    		else { $_567 = FALSE; break; }
    		$_567 = TRUE; break;
    	}
    	while(0);
    	if( $_567 === TRUE ) { return $this->finalise($result); }
    	if( $_567 === FALSE) { return FALSE; }
    }



    function Include__construct(&$res)
    {
        $res['arguments'] = [];
    }

    function Include_Template(&$res, $sub)
    {
        $res['template'] = "'" . $sub['text'] . "'";
    }

    function Include_NamedArgument(&$res, $sub)
    {
        $res['arguments'][] = $sub['php'];
    }

    function Include__finalise(&$res)
    {
        $template = $res['template'];
        $arguments = $res['arguments'];

        // Note: 'type' here is important to disable subTemplates in SSTemplateEngine::getSubtemplateFor()
        $res['php'] = '$val .= \\SilverStripe\\TemplateEngine\\SSTemplateEngine::execute_template([["type" => "Includes", '.$template.'], '.$template.'], $scope->getCurrentItem(), [' .
            implode(',', $arguments)."], \$scope, true);\n";

        if ($this->includeDebuggingComments) { // Add include filename comments on dev sites
            $res['php'] =
                '$val .= \'<!-- include '.addslashes($template ?? '').' -->\';'. "\n".
                $res['php'].
                '$val .= \'<!-- end include '.addslashes($template ?? '').' -->\';'. "\n";
        }
    }

    /* BlockArguments: :Argument ( < "," < :Argument)* */
    protected $match_BlockArguments_typestack = array('BlockArguments');
    function match_BlockArguments ($stack = array()) {
    	$matchrule = "BlockArguments"; $result = $this->construct($matchrule, $matchrule, null);
    	$_576 = NULL;
    	do {
    		$matcher = 'match_'.'Argument'; $key = $matcher; $pos = $this->pos;
    		$subres = ( $this->packhas( $key, $pos ) ? $this->packread( $key, $pos ) : $this->packwrite( $key, $pos, $this->$matcher(array_merge($stack, array($result))) ) );
    		if ($subres !== FALSE) {
    			$this->store( $result, $subres, "Argument" );
    		}
    		else { $_576 = FALSE; break; }
    		while (true) {
    			$res_575 = $result;
    			$pos_575 = $this->pos;
    			$_574 = NULL;
    			do {
    				if (( $subres = $this->whitespace(  ) ) !== FALSE) { $result["text"] .= $subres; }
    				if (substr($this->string ?? '',$this->pos ?? 0,1) == ',') {
    					$this->pos += 1;
    					$result["text"] .= ',';
    				}
    				else { $_574 = FALSE; break; }
    				if (( $subres = $this->whitespace(  ) ) !== FALSE) { $result["text"] .= $subres; }
    				$matcher = 'match_'.'Argument'; $key = $matcher; $pos = $this->pos;
    				$subres = ( $this->packhas( $key, $pos ) ? $this->packread( $key, $pos ) : $this->packwrite( $key, $pos, $this->$matcher(array_merge($stack, array($result))) ) );
    				if ($subres !== FALSE) {
    					$this->store( $result, $subres, "Argument" );
    				}
    				else { $_574 = FALSE; break; }
    				$_574 = TRUE; break;
    			}
    			while(0);
    			if( $_574 === FALSE) {
    				$result = $res_575;
    				$this->pos = $pos_575;
    				unset( $res_575 );
    				unset( $pos_575 );
    				break;
    			}
    		}
    		$_576 = TRUE; break;
    	}
    	while(0);
    	if( $_576 === TRUE ) { return $this->finalise($result); }
    	if( $_576 === FALSE) { return FALSE; }
    }


    /* NotBlockTag: "end_" | (("if" | "else_if" | "else" | "require" | "cached" | "uncached" | "cacheblock" | "include")]) */
    protected $match_NotBlockTag_typestack = array('NotBlockTag');
    function match_NotBlockTag ($stack = array()) {
    	$matchrule = "NotBlockTag"; $result = $this->construct($matchrule, $matchrule, null);
    	$_614 = NULL;
    	do {
    		$res_578 = $result;
    		$pos_578 = $this->pos;
    		if (( $subres = $this->literal( 'end_' ) ) !== FALSE) {
    			$result["text"] .= $subres;
    			$_614 = TRUE; break;
    		}
    		$result = $res_578;
    		$this->pos = $pos_578;
    		$_612 = NULL;
    		do {
    			$_609 = NULL;
    			do {
    				$_607 = NULL;
    				do {
    					$res_580 = $result;
    					$pos_580 = $this->pos;
    					if (( $subres = $this->literal( 'if' ) ) !== FALSE) {
    						$result["text"] .= $subres;
    						$_607 = TRUE; break;
    					}
    					$result = $res_580;
    					$this->pos = $pos_580;
    					$_605 = NULL;
    					do {
    						$res_582 = $result;
    						$pos_582 = $this->pos;
    						if (( $subres = $this->literal( 'else_if' ) ) !== FALSE) {
    							$result["text"] .= $subres;
    							$_605 = TRUE; break;
    						}
    						$result = $res_582;
    						$this->pos = $pos_582;
    						$_603 = NULL;
    						do {
    							$res_584 = $result;
    							$pos_584 = $this->pos;
    							if (( $subres = $this->literal( 'else' ) ) !== FALSE) {
    								$result["text"] .= $subres;
    								$_603 = TRUE; break;
    							}
    							$result = $res_584;
    							$this->pos = $pos_584;
    							$_601 = NULL;
    							do {
    								$res_586 = $result;
    								$pos_586 = $this->pos;
    								if (( $subres = $this->literal( 'require' ) ) !== FALSE) {
    									$result["text"] .= $subres;
    									$_601 = TRUE; break;
    								}
    								$result = $res_586;
    								$this->pos = $pos_586;
    								$_599 = NULL;
    								do {
    									$res_588 = $result;
    									$pos_588 = $this->pos;
    									if (( $subres = $this->literal( 'cached' ) ) !== FALSE) {
    										$result["text"] .= $subres;
    										$_599 = TRUE; break;
    									}
    									$result = $res_588;
    									$this->pos = $pos_588;
    									$_597 = NULL;
    									do {
    										$res_590 = $result;
    										$pos_590 = $this->pos;
    										if (( $subres = $this->literal( 'uncached' ) ) !== FALSE) {
    											$result["text"] .= $subres;
    											$_597 = TRUE; break;
    										}
    										$result = $res_590;
    										$this->pos = $pos_590;
    										$_595 = NULL;
    										do {
    											$res_592 = $result;
    											$pos_592 = $this->pos;
    											if (( $subres = $this->literal( 'cacheblock' ) ) !== FALSE) {
    												$result["text"] .= $subres;
    												$_595 = TRUE; break;
    											}
    											$result = $res_592;
    											$this->pos = $pos_592;
    											if (( $subres = $this->literal( 'include' ) ) !== FALSE) {
    												$result["text"] .= $subres;
    												$_595 = TRUE; break;
    											}
    											$result = $res_592;
    											$this->pos = $pos_592;
    											$_595 = FALSE; break;
    										}
    										while(0);
    										if( $_595 === TRUE ) { $_597 = TRUE; break; }
    										$result = $res_590;
    										$this->pos = $pos_590;
    										$_597 = FALSE; break;
    									}
    									while(0);
    									if( $_597 === TRUE ) { $_599 = TRUE; break; }
    									$result = $res_588;
    									$this->pos = $pos_588;
    									$_599 = FALSE; break;
    								}
    								while(0);
    								if( $_599 === TRUE ) { $_601 = TRUE; break; }
    								$result = $res_586;
    								$this->pos = $pos_586;
    								$_601 = FALSE; break;
    							}
    							while(0);
    							if( $_601 === TRUE ) { $_603 = TRUE; break; }
    							$result = $res_584;
    							$this->pos = $pos_584;
    							$_603 = FALSE; break;
    						}
    						while(0);
    						if( $_603 === TRUE ) { $_605 = TRUE; break; }
    						$result = $res_582;
    						$this->pos = $pos_582;
    						$_605 = FALSE; break;
    					}
    					while(0);
    					if( $_605 === TRUE ) { $_607 = TRUE; break; }
    					$result = $res_580;
    					$this->pos = $pos_580;
    					$_607 = FALSE; break;
    				}
    				while(0);
    				if( $_607 === FALSE) { $_609 = FALSE; break; }
    				$_609 = TRUE; break;
    			}
    			while(0);
    			if( $_609 === FALSE) { $_612 = FALSE; break; }
    			if (( $subres = $this->whitespace(  ) ) !== FALSE) { $result["text"] .= $subres; }
    			else { $_612 = FALSE; break; }
    			$_612 = TRUE; break;
    		}
    		while(0);
    		if( $_612 === TRUE ) { $_614 = TRUE; break; }
    		$result = $res_578;
    		$this->pos = $pos_578;
    		$_614 = FALSE; break;
    	}
    	while(0);
    	if( $_614 === TRUE ) { return $this->finalise($result); }
    	if( $_614 === FALSE) { return FALSE; }
    }


    /* ClosedBlock: '<%' < !NotBlockTag BlockName:Word ( [ :BlockArguments ] )? > Zap:'%>' Template:$TemplateMatcher?
    '<%' < 'end_' '$BlockName' > '%>' */
    protected $match_ClosedBlock_typestack = array('ClosedBlock');
    function match_ClosedBlock ($stack = array()) {
    	$matchrule = "ClosedBlock"; $result = $this->construct($matchrule, $matchrule, null);
    	$_634 = NULL;
    	do {
    		if (( $subres = $this->literal( '<%' ) ) !== FALSE) { $result["text"] .= $subres; }
    		else { $_634 = FALSE; break; }
    		if (( $subres = $this->whitespace(  ) ) !== FALSE) { $result["text"] .= $subres; }
    		$res_618 = $result;
    		$pos_618 = $this->pos;
    		$matcher = 'match_'.'NotBlockTag'; $key = $matcher; $pos = $this->pos;
    		$subres = ( $this->packhas( $key, $pos ) ? $this->packread( $key, $pos ) : $this->packwrite( $key, $pos, $this->$matcher(array_merge($stack, array($result))) ) );
    		if ($subres !== FALSE) {
    			$this->store( $result, $subres );
    			$result = $res_618;
    			$this->pos = $pos_618;
    			$_634 = FALSE; break;
    		}
    		else {
    			$result = $res_618;
    			$this->pos = $pos_618;
    		}
    		$matcher = 'match_'.'Word'; $key = $matcher; $pos = $this->pos;
    		$subres = ( $this->packhas( $key, $pos ) ? $this->packread( $key, $pos ) : $this->packwrite( $key, $pos, $this->$matcher(array_merge($stack, array($result))) ) );
    		if ($subres !== FALSE) {
    			$this->store( $result, $subres, "BlockName" );
    		}
    		else { $_634 = FALSE; break; }
    		$res_624 = $result;
    		$pos_624 = $this->pos;
    		$_623 = NULL;
    		do {
    			if (( $subres = $this->whitespace(  ) ) !== FALSE) { $result["text"] .= $subres; }
    			else { $_623 = FALSE; break; }
    			$matcher = 'match_'.'BlockArguments'; $key = $matcher; $pos = $this->pos;
    			$subres = ( $this->packhas( $key, $pos ) ? $this->packread( $key, $pos ) : $this->packwrite( $key, $pos, $this->$matcher(array_merge($stack, array($result))) ) );
    			if ($subres !== FALSE) {
    				$this->store( $result, $subres, "BlockArguments" );
    			}
    			else { $_623 = FALSE; break; }
    			if (( $subres = $this->whitespace(  ) ) !== FALSE) { $result["text"] .= $subres; }
    			else { $_623 = FALSE; break; }
    			$_623 = TRUE; break;
    		}
    		while(0);
    		if( $_623 === FALSE) {
    			$result = $res_624;
    			$this->pos = $pos_624;
    			unset( $res_624 );
    			unset( $pos_624 );
    		}
    		if (( $subres = $this->whitespace(  ) ) !== FALSE) { $result["text"] .= $subres; }
    		$stack[] = $result; $result = $this->construct( $matchrule, "Zap" ); 
    		if (( $subres = $this->literal( '%>' ) ) !== FALSE) {
    			$result["text"] .= $subres;
    			$subres = $result; $result = array_pop($stack);
    			$this->store( $result, $subres, 'Zap' );
    		}
    		else {
    			$result = array_pop($stack);
    			$_634 = FALSE; break;
    		}
    		$res_627 = $result;
    		$pos_627 = $this->pos;
    		$matcher = 'match_'.$this->expression($result, $stack, 'TemplateMatcher'); $key = $matcher; $pos = $this->pos;
    		$subres = ( $this->packhas( $key, $pos ) ? $this->packread( $key, $pos ) : $this->packwrite( $key, $pos, $this->$matcher(array_merge($stack, array($result))) ) );
    		if ($subres !== FALSE) {
    			$this->store( $result, $subres, "Template" );
    		}
    		else {
    			$result = $res_627;
    			$this->pos = $pos_627;
    			unset( $res_627 );
    			unset( $pos_627 );
    		}
    		if (( $subres = $this->literal( '<%' ) ) !== FALSE) { $result["text"] .= $subres; }
    		else { $_634 = FALSE; break; }
    		if (( $subres = $this->whitespace(  ) ) !== FALSE) { $result["text"] .= $subres; }
    		if (( $subres = $this->literal( 'end_' ) ) !== FALSE) { $result["text"] .= $subres; }
    		else { $_634 = FALSE; break; }
    		if (( $subres = $this->literal( ''.$this->expression($result, $stack, 'BlockName').'' ) ) !== FALSE) { $result["text"] .= $subres; }
    		else { $_634 = FALSE; break; }
    		if (( $subres = $this->whitespace(  ) ) !== FALSE) { $result["text"] .= $subres; }
    		if (( $subres = $this->literal( '%>' ) ) !== FALSE) { $result["text"] .= $subres; }
    		else { $_634 = FALSE; break; }
    		$_634 = TRUE; break;
    	}
    	while(0);
    	if( $_634 === TRUE ) { return $this->finalise($result); }
    	if( $_634 === FALSE) { return FALSE; }
    }




    /**
     * As mentioned in the parser comment, block handling is kept fairly generic for extensibility. The match rule
     * builds up two important elements in the match result array:
     *   'ArgumentCount' - how many arguments were passed in the opening tag
     *   'Arguments' an array of the Argument match rule result arrays
     *
     * Once a block has successfully been matched against, it will then look for the actual handler, which should
     * be on this class (either defined or extended on) as ClosedBlock_Handler_Name(&$res), where Name is the
     * tag name, first letter captialized (i.e Control, Loop, With, etc).
     *
     * This function will be called with the match rule result array as it's first argument. It should return
     * the php result of this block as it's return value, or throw an error if incorrect arguments were passed.
     */

    function ClosedBlock__construct(&$res)
    {
        $res['ArgumentCount'] = 0;
    }

    function ClosedBlock_BlockArguments(&$res, $sub)
    {
        if (isset($sub['Argument']['ArgumentMode'])) {
            $res['Arguments'] = [$sub['Argument']];
            $res['ArgumentCount'] = 1;
        } else {
            $res['Arguments'] = $sub['Argument'];
            $res['ArgumentCount'] = count($res['Arguments'] ?? []);
        }
    }

    function ClosedBlock__finalise(&$res)
    {
        $blockname = $res['BlockName']['text'];

        $method = 'ClosedBlock_Handle_'.$blockname;
        if (method_exists($this, $method ?? '')) {
            $res['php'] = $this->$method($res);
        } elseif (isset($this->closedBlocks[$blockname])) {
            $res['php'] = call_user_func($this->closedBlocks[$blockname], $res);
        } else {
            throw new SSTemplateParseException('Unknown closed block "'.$blockname.'" encountered. Perhaps you are ' .
            'not supposed to close this block, or have mis-spelled it?', $this);
        }
    }

    /**
     * This is an example of a block handler function. This one handles the loop tag.
     */
    function ClosedBlock_Handle_Loop(&$res)
    {
        if ($res['ArgumentCount'] > 1) {
            throw new SSTemplateParseException('Too many arguments in control block. Must be one or no' .
                'arguments only.', $this);
        }

        // loop without arguments loops on the current scope
        if ($res['ArgumentCount'] == 0) {
            $on = '$scope->locally()->self()';
        } else {    //loop in the normal way
            $arg = $res['Arguments'][0];
            if ($arg['ArgumentMode'] == 'string') {
                throw new SSTemplateParseException('Control block cant take string as argument.', $this);
            }
            $on = str_replace(
                '$$FINAL',
                'scopeToIntermediateValue',
                ($arg['ArgumentMode'] == 'default') ? $arg['lookup_php'] : $arg['php']
            );
        }

        return
            $on . '; $scope->pushScope(); while ($scope->next() !== false) {' . PHP_EOL .
                $res['Template']['php'] . PHP_EOL .
            '}; $scope->popScope(); ';
    }

    /**
     * The closed block handler for with blocks
     */
    function ClosedBlock_Handle_With(&$res)
    {
        if ($res['ArgumentCount'] != 1) {
            throw new SSTemplateParseException('Either no or too many arguments in with block. Must be one ' .
                'argument only.', $this);
        }

        $arg = $res['Arguments'][0];
        if ($arg['ArgumentMode'] == 'string') {
            throw new SSTemplateParseException('Control block cant take string as argument.', $this);
        }

        $on = str_replace('$$FINAL', 'scopeToIntermediateValue', ($arg['ArgumentMode'] == 'default') ? $arg['lookup_php'] : $arg['php']);
        return
            $on . '; $scope->pushScope();' . PHP_EOL .
                $res['Template']['php'] . PHP_EOL .
            '; $scope->popScope(); ';
    }

    /* OpenBlock: '<%' < !NotBlockTag BlockName:Word ( [ :BlockArguments ] )? > '%>' */
    protected $match_OpenBlock_typestack = array('OpenBlock');
    function match_OpenBlock ($stack = array()) {
    	$matchrule = "OpenBlock"; $result = $this->construct($matchrule, $matchrule, null);
    	$_647 = NULL;
    	do {
    		if (( $subres = $this->literal( '<%' ) ) !== FALSE) { $result["text"] .= $subres; }
    		else { $_647 = FALSE; break; }
    		if (( $subres = $this->whitespace(  ) ) !== FALSE) { $result["text"] .= $subres; }
    		$res_638 = $result;
    		$pos_638 = $this->pos;
    		$matcher = 'match_'.'NotBlockTag'; $key = $matcher; $pos = $this->pos;
    		$subres = ( $this->packhas( $key, $pos ) ? $this->packread( $key, $pos ) : $this->packwrite( $key, $pos, $this->$matcher(array_merge($stack, array($result))) ) );
    		if ($subres !== FALSE) {
    			$this->store( $result, $subres );
    			$result = $res_638;
    			$this->pos = $pos_638;
    			$_647 = FALSE; break;
    		}
    		else {
    			$result = $res_638;
    			$this->pos = $pos_638;
    		}
    		$matcher = 'match_'.'Word'; $key = $matcher; $pos = $this->pos;
    		$subres = ( $this->packhas( $key, $pos ) ? $this->packread( $key, $pos ) : $this->packwrite( $key, $pos, $this->$matcher(array_merge($stack, array($result))) ) );
    		if ($subres !== FALSE) {
    			$this->store( $result, $subres, "BlockName" );
    		}
    		else { $_647 = FALSE; break; }
    		$res_644 = $result;
    		$pos_644 = $this->pos;
    		$_643 = NULL;
    		do {
    			if (( $subres = $this->whitespace(  ) ) !== FALSE) { $result["text"] .= $subres; }
    			else { $_643 = FALSE; break; }
    			$matcher = 'match_'.'BlockArguments'; $key = $matcher; $pos = $this->pos;
    			$subres = ( $this->packhas( $key, $pos ) ? $this->packread( $key, $pos ) : $this->packwrite( $key, $pos, $this->$matcher(array_merge($stack, array($result))) ) );
    			if ($subres !== FALSE) {
    				$this->store( $result, $subres, "BlockArguments" );
    			}
    			else { $_643 = FALSE; break; }
    			if (( $subres = $this->whitespace(  ) ) !== FALSE) { $result["text"] .= $subres; }
    			else { $_643 = FALSE; break; }
    			$_643 = TRUE; break;
    		}
    		while(0);
    		if( $_643 === FALSE) {
    			$result = $res_644;
    			$this->pos = $pos_644;
    			unset( $res_644 );
    			unset( $pos_644 );
    		}
    		if (( $subres = $this->whitespace(  ) ) !== FALSE) { $result["text"] .= $subres; }
    		if (( $subres = $this->literal( '%>' ) ) !== FALSE) { $result["text"] .= $subres; }
    		else { $_647 = FALSE; break; }
    		$_647 = TRUE; break;
    	}
    	while(0);
    	if( $_647 === TRUE ) { return $this->finalise($result); }
    	if( $_647 === FALSE) { return FALSE; }
    }



    function OpenBlock__construct(&$res)
    {
        $res['ArgumentCount'] = 0;
    }

    function OpenBlock_BlockArguments(&$res, $sub)
    {
        if (isset($sub['Argument']['ArgumentMode'])) {
            $res['Arguments'] = [$sub['Argument']];
            $res['ArgumentCount'] = 1;
        } else {
            $res['Arguments'] = $sub['Argument'];
            $res['ArgumentCount'] = count($res['Arguments'] ?? []);
        }
    }

    function OpenBlock__finalise(&$res)
    {
        $blockname = $res['BlockName']['text'];

        $method = 'OpenBlock_Handle_'.$blockname;
        if (method_exists($this, $method ?? '')) {
            $res['php'] = $this->$method($res);
        } elseif (isset($this->openBlocks[$blockname])) {
            $res['php'] = call_user_func($this->openBlocks[$blockname], $res);
        } else {
            throw new SSTemplateParseException('Unknown open block "'.$blockname.'" encountered. Perhaps you missed ' .
            ' the closing tag or have mis-spelled it?', $this);
        }
    }

    /**
     * This is an open block handler, for the <% base_tag %> tag
     */
    function OpenBlock_Handle_Base_tag(&$res)
    {
        if ($res['ArgumentCount'] != 0) {
            throw new SSTemplateParseException('Base_tag takes no arguments', $this);
        }
        $code = '$isXhtml = preg_match(\'/<!DOCTYPE[^>]+xhtml/i\', $val);';
        $code .= PHP_EOL . '$val .= \\SilverStripe\\View\\SSViewer::getBaseTag($isXhtml);';
        return $code;
    }

    /**
     * This is an open block handler, for the <% current_page %> tag
     */
    function OpenBlock_Handle_Current_page(&$res)
    {
        if ($res['ArgumentCount'] != 0) {
            throw new SSTemplateParseException('Current_page takes no arguments', $this);
        }
        return '$val .= $_SERVER[SCRIPT_URL];';
    }

    /* MismatchedEndBlock: '<%' < 'end_' :Word > '%>' */
    protected $match_MismatchedEndBlock_typestack = array('MismatchedEndBlock');
    function match_MismatchedEndBlock ($stack = array()) {
    	$matchrule = "MismatchedEndBlock"; $result = $this->construct($matchrule, $matchrule, null);
    	$_655 = NULL;
    	do {
    		if (( $subres = $this->literal( '<%' ) ) !== FALSE) { $result["text"] .= $subres; }
    		else { $_655 = FALSE; break; }
    		if (( $subres = $this->whitespace(  ) ) !== FALSE) { $result["text"] .= $subres; }
    		if (( $subres = $this->literal( 'end_' ) ) !== FALSE) { $result["text"] .= $subres; }
    		else { $_655 = FALSE; break; }
    		$matcher = 'match_'.'Word'; $key = $matcher; $pos = $this->pos;
    		$subres = ( $this->packhas( $key, $pos ) ? $this->packread( $key, $pos ) : $this->packwrite( $key, $pos, $this->$matcher(array_merge($stack, array($result))) ) );
    		if ($subres !== FALSE) {
    			$this->store( $result, $subres, "Word" );
    		}
    		else { $_655 = FALSE; break; }
    		if (( $subres = $this->whitespace(  ) ) !== FALSE) { $result["text"] .= $subres; }
    		if (( $subres = $this->literal( '%>' ) ) !== FALSE) { $result["text"] .= $subres; }
    		else { $_655 = FALSE; break; }
    		$_655 = TRUE; break;
    	}
    	while(0);
    	if( $_655 === TRUE ) { return $this->finalise($result); }
    	if( $_655 === FALSE) { return FALSE; }
    }



    function MismatchedEndBlock__finalise(&$res)
    {
        $blockname = $res['Word']['text'];
        throw new SSTemplateParseException('Unexpected close tag end_' . $blockname .
            ' encountered. Perhaps you have mis-nested blocks, or have mis-spelled a tag?', $this);
    }

    /* MalformedOpenTag: '<%' < !NotBlockTag Tag:Word  !( ( [ :BlockArguments ] )? > '%>' ) */
    protected $match_MalformedOpenTag_typestack = array('MalformedOpenTag');
    function match_MalformedOpenTag ($stack = array()) {
    	$matchrule = "MalformedOpenTag"; $result = $this->construct($matchrule, $matchrule, null);
    	$_670 = NULL;
    	do {
    		if (( $subres = $this->literal( '<%' ) ) !== FALSE) { $result["text"] .= $subres; }
    		else { $_670 = FALSE; break; }
    		if (( $subres = $this->whitespace(  ) ) !== FALSE) { $result["text"] .= $subres; }
    		$res_659 = $result;
    		$pos_659 = $this->pos;
    		$matcher = 'match_'.'NotBlockTag'; $key = $matcher; $pos = $this->pos;
    		$subres = ( $this->packhas( $key, $pos ) ? $this->packread( $key, $pos ) : $this->packwrite( $key, $pos, $this->$matcher(array_merge($stack, array($result))) ) );
    		if ($subres !== FALSE) {
    			$this->store( $result, $subres );
    			$result = $res_659;
    			$this->pos = $pos_659;
    			$_670 = FALSE; break;
    		}
    		else {
    			$result = $res_659;
    			$this->pos = $pos_659;
    		}
    		$matcher = 'match_'.'Word'; $key = $matcher; $pos = $this->pos;
    		$subres = ( $this->packhas( $key, $pos ) ? $this->packread( $key, $pos ) : $this->packwrite( $key, $pos, $this->$matcher(array_merge($stack, array($result))) ) );
    		if ($subres !== FALSE) {
    			$this->store( $result, $subres, "Tag" );
    		}
    		else { $_670 = FALSE; break; }
    		$res_669 = $result;
    		$pos_669 = $this->pos;
    		$_668 = NULL;
    		do {
    			$res_665 = $result;
    			$pos_665 = $this->pos;
    			$_664 = NULL;
    			do {
    				if (( $subres = $this->whitespace(  ) ) !== FALSE) { $result["text"] .= $subres; }
    				else { $_664 = FALSE; break; }
    				$matcher = 'match_'.'BlockArguments'; $key = $matcher; $pos = $this->pos;
    				$subres = ( $this->packhas( $key, $pos ) ? $this->packread( $key, $pos ) : $this->packwrite( $key, $pos, $this->$matcher(array_merge($stack, array($result))) ) );
    				if ($subres !== FALSE) {
    					$this->store( $result, $subres, "BlockArguments" );
    				}
    				else { $_664 = FALSE; break; }
    				if (( $subres = $this->whitespace(  ) ) !== FALSE) { $result["text"] .= $subres; }
    				else { $_664 = FALSE; break; }
    				$_664 = TRUE; break;
    			}
    			while(0);
    			if( $_664 === FALSE) {
    				$result = $res_665;
    				$this->pos = $pos_665;
    				unset( $res_665 );
    				unset( $pos_665 );
    			}
    			if (( $subres = $this->whitespace(  ) ) !== FALSE) { $result["text"] .= $subres; }
    			if (( $subres = $this->literal( '%>' ) ) !== FALSE) { $result["text"] .= $subres; }
    			else { $_668 = FALSE; break; }
    			$_668 = TRUE; break;
    		}
    		while(0);
    		if( $_668 === TRUE ) {
    			$result = $res_669;
    			$this->pos = $pos_669;
    			$_670 = FALSE; break;
    		}
    		if( $_668 === FALSE) {
    			$result = $res_669;
    			$this->pos = $pos_669;
    		}
    		$_670 = TRUE; break;
    	}
    	while(0);
    	if( $_670 === TRUE ) { return $this->finalise($result); }
    	if( $_670 === FALSE) { return FALSE; }
    }



    function MalformedOpenTag__finalise(&$res)
    {
        $tag = $res['Tag']['text'];
        throw new SSTemplateParseException("Malformed opening block tag $tag. Perhaps you have tried to use operators?", $this);
    }

    /* MalformedCloseTag: '<%' < Tag:('end_' :Word ) !( > '%>' ) */
    protected $match_MalformedCloseTag_typestack = array('MalformedCloseTag');
    function match_MalformedCloseTag ($stack = array()) {
    	$matchrule = "MalformedCloseTag"; $result = $this->construct($matchrule, $matchrule, null);
    	$_682 = NULL;
    	do {
    		if (( $subres = $this->literal( '<%' ) ) !== FALSE) { $result["text"] .= $subres; }
    		else { $_682 = FALSE; break; }
    		if (( $subres = $this->whitespace(  ) ) !== FALSE) { $result["text"] .= $subres; }
    		$stack[] = $result; $result = $this->construct( $matchrule, "Tag" ); 
    		$_676 = NULL;
    		do {
    			if (( $subres = $this->literal( 'end_' ) ) !== FALSE) { $result["text"] .= $subres; }
    			else { $_676 = FALSE; break; }
    			$matcher = 'match_'.'Word'; $key = $matcher; $pos = $this->pos;
    			$subres = ( $this->packhas( $key, $pos ) ? $this->packread( $key, $pos ) : $this->packwrite( $key, $pos, $this->$matcher(array_merge($stack, array($result))) ) );
    			if ($subres !== FALSE) {
    				$this->store( $result, $subres, "Word" );
    			}
    			else { $_676 = FALSE; break; }
    			$_676 = TRUE; break;
    		}
    		while(0);
    		if( $_676 === TRUE ) {
    			$subres = $result; $result = array_pop($stack);
    			$this->store( $result, $subres, 'Tag' );
    		}
    		if( $_676 === FALSE) {
    			$result = array_pop($stack);
    			$_682 = FALSE; break;
    		}
    		$res_681 = $result;
    		$pos_681 = $this->pos;
    		$_680 = NULL;
    		do {
    			if (( $subres = $this->whitespace(  ) ) !== FALSE) { $result["text"] .= $subres; }
    			if (( $subres = $this->literal( '%>' ) ) !== FALSE) { $result["text"] .= $subres; }
    			else { $_680 = FALSE; break; }
    			$_680 = TRUE; break;
    		}
    		while(0);
    		if( $_680 === TRUE ) {
    			$result = $res_681;
    			$this->pos = $pos_681;
    			$_682 = FALSE; break;
    		}
    		if( $_680 === FALSE) {
    			$result = $res_681;
    			$this->pos = $pos_681;
    		}
    		$_682 = TRUE; break;
    	}
    	while(0);
    	if( $_682 === TRUE ) { return $this->finalise($result); }
    	if( $_682 === FALSE) { return FALSE; }
    }



    function MalformedCloseTag__finalise(&$res)
    {
        $tag = $res['Tag']['text'];
        throw new SSTemplateParseException("Malformed closing block tag $tag. Perhaps you have tried to pass an " .
            "argument to one?", $this);
    }

    /* MalformedBlock: MalformedOpenTag | MalformedCloseTag */
    protected $match_MalformedBlock_typestack = array('MalformedBlock');
    function match_MalformedBlock ($stack = array()) {
    	$matchrule = "MalformedBlock"; $result = $this->construct($matchrule, $matchrule, null);
    	$_687 = NULL;
    	do {
    		$res_684 = $result;
    		$pos_684 = $this->pos;
    		$matcher = 'match_'.'MalformedOpenTag'; $key = $matcher; $pos = $this->pos;
    		$subres = ( $this->packhas( $key, $pos ) ? $this->packread( $key, $pos ) : $this->packwrite( $key, $pos, $this->$matcher(array_merge($stack, array($result))) ) );
    		if ($subres !== FALSE) {
    			$this->store( $result, $subres );
    			$_687 = TRUE; break;
    		}
    		$result = $res_684;
    		$this->pos = $pos_684;
    		$matcher = 'match_'.'MalformedCloseTag'; $key = $matcher; $pos = $this->pos;
    		$subres = ( $this->packhas( $key, $pos ) ? $this->packread( $key, $pos ) : $this->packwrite( $key, $pos, $this->$matcher(array_merge($stack, array($result))) ) );
    		if ($subres !== FALSE) {
    			$this->store( $result, $subres );
    			$_687 = TRUE; break;
    		}
    		$result = $res_684;
    		$this->pos = $pos_684;
    		$_687 = FALSE; break;
    	}
    	while(0);
    	if( $_687 === TRUE ) { return $this->finalise($result); }
    	if( $_687 === FALSE) { return FALSE; }
    }




    /* CommentWithContent: '<%--' ( !"--%>" /(?s)./ )+ '--%>' */
    protected $match_CommentWithContent_typestack = array('CommentWithContent');
    function match_CommentWithContent ($stack = array()) {
    	$matchrule = "CommentWithContent"; $result = $this->construct($matchrule, $matchrule, null);
    	$_695 = NULL;
    	do {
    		if (( $subres = $this->literal( '<%--' ) ) !== FALSE) { $result["text"] .= $subres; }
    		else { $_695 = FALSE; break; }
    		$count = 0;
    		while (true) {
    			$res_693 = $result;
    			$pos_693 = $this->pos;
    			$_692 = NULL;
    			do {
    				$res_690 = $result;
    				$pos_690 = $this->pos;
    				if (( $subres = $this->literal( '--%>' ) ) !== FALSE) {
    					$result["text"] .= $subres;
    					$result = $res_690;
    					$this->pos = $pos_690;
    					$_692 = FALSE; break;
    				}
    				else {
    					$result = $res_690;
    					$this->pos = $pos_690;
    				}
    				if (( $subres = $this->rx( '/(?s)./' ) ) !== FALSE) { $result["text"] .= $subres; }
    				else { $_692 = FALSE; break; }
    				$_692 = TRUE; break;
    			}
    			while(0);
    			if( $_692 === FALSE) {
    				$result = $res_693;
    				$this->pos = $pos_693;
    				unset( $res_693 );
    				unset( $pos_693 );
    				break;
    			}
    			$count += 1;
    		}
    		if ($count > 0) {  }
    		else { $_695 = FALSE; break; }
    		if (( $subres = $this->literal( '--%>' ) ) !== FALSE) { $result["text"] .= $subres; }
    		else { $_695 = FALSE; break; }
    		$_695 = TRUE; break;
    	}
    	while(0);
    	if( $_695 === TRUE ) { return $this->finalise($result); }
    	if( $_695 === FALSE) { return FALSE; }
    }


    /* EmptyComment: '<%----%>' */
    protected $match_EmptyComment_typestack = array('EmptyComment');
    function match_EmptyComment ($stack = array()) {
    	$matchrule = "EmptyComment"; $result = $this->construct($matchrule, $matchrule, null);
    	if (( $subres = $this->literal( '<%----%>' ) ) !== FALSE) {
    		$result["text"] .= $subres;
    		return $this->finalise($result);
    	}
    	else { return FALSE; }
    }


    /* Comment: :EmptyComment | :CommentWithContent */
    protected $match_Comment_typestack = array('Comment');
    function match_Comment ($stack = array()) {
    	$matchrule = "Comment"; $result = $this->construct($matchrule, $matchrule, null);
    	$_701 = NULL;
    	do {
    		$res_698 = $result;
    		$pos_698 = $this->pos;
    		$matcher = 'match_'.'EmptyComment'; $key = $matcher; $pos = $this->pos;
    		$subres = ( $this->packhas( $key, $pos ) ? $this->packread( $key, $pos ) : $this->packwrite( $key, $pos, $this->$matcher(array_merge($stack, array($result))) ) );
    		if ($subres !== FALSE) {
    			$this->store( $result, $subres, "EmptyComment" );
    			$_701 = TRUE; break;
    		}
    		$result = $res_698;
    		$this->pos = $pos_698;
    		$matcher = 'match_'.'CommentWithContent'; $key = $matcher; $pos = $this->pos;
    		$subres = ( $this->packhas( $key, $pos ) ? $this->packread( $key, $pos ) : $this->packwrite( $key, $pos, $this->$matcher(array_merge($stack, array($result))) ) );
    		if ($subres !== FALSE) {
    			$this->store( $result, $subres, "CommentWithContent" );
    			$_701 = TRUE; break;
    		}
    		$result = $res_698;
    		$this->pos = $pos_698;
    		$_701 = FALSE; break;
    	}
    	while(0);
    	if( $_701 === TRUE ) { return $this->finalise($result); }
    	if( $_701 === FALSE) { return FALSE; }
    }



    function Comment__construct(&$res)
    {
        $res['php'] = '';
    }

    /* TopTemplate: (Comment | Translate | If | Require | CacheBlock | UncachedBlock | Include | ClosedBlock |
    OpenBlock |  MalformedBlock | MismatchedEndBlock  | MalformedBracketInjection | Injection | Text)+ */
    protected $match_TopTemplate_typestack = array('TopTemplate','Template');
    function match_TopTemplate ($stack = array()) {
    	$matchrule = "TopTemplate"; $result = $this->construct($matchrule, $matchrule, array('TemplateMatcher' => 'Template'));
    	$count = 0;
    	while (true) {
    		$res_757 = $result;
    		$pos_757 = $this->pos;
    		$_756 = NULL;
    		do {
    			$_754 = NULL;
    			do {
    				$res_703 = $result;
    				$pos_703 = $this->pos;
    				$matcher = 'match_'.'Comment'; $key = $matcher; $pos = $this->pos;
    				$subres = ( $this->packhas( $key, $pos ) ? $this->packread( $key, $pos ) : $this->packwrite( $key, $pos, $this->$matcher(array_merge($stack, array($result))) ) );
    				if ($subres !== FALSE) {
    					$this->store( $result, $subres );
    					$_754 = TRUE; break;
    				}
    				$result = $res_703;
    				$this->pos = $pos_703;
    				$_752 = NULL;
    				do {
    					$res_705 = $result;
    					$pos_705 = $this->pos;
    					$matcher = 'match_'.'Translate'; $key = $matcher; $pos = $this->pos;
    					$subres = ( $this->packhas( $key, $pos ) ? $this->packread( $key, $pos ) : $this->packwrite( $key, $pos, $this->$matcher(array_merge($stack, array($result))) ) );
    					if ($subres !== FALSE) {
    						$this->store( $result, $subres );
    						$_752 = TRUE; break;
    					}
    					$result = $res_705;
    					$this->pos = $pos_705;
    					$_750 = NULL;
    					do {
    						$res_707 = $result;
    						$pos_707 = $this->pos;
    						$matcher = 'match_'.'If'; $key = $matcher; $pos = $this->pos;
    						$subres = ( $this->packhas( $key, $pos ) ? $this->packread( $key, $pos ) : $this->packwrite( $key, $pos, $this->$matcher(array_merge($stack, array($result))) ) );
    						if ($subres !== FALSE) {
    							$this->store( $result, $subres );
    							$_750 = TRUE; break;
    						}
    						$result = $res_707;
    						$this->pos = $pos_707;
    						$_748 = NULL;
    						do {
    							$res_709 = $result;
    							$pos_709 = $this->pos;
    							$matcher = 'match_'.'Require'; $key = $matcher; $pos = $this->pos;
    							$subres = ( $this->packhas( $key, $pos ) ? $this->packread( $key, $pos ) : $this->packwrite( $key, $pos, $this->$matcher(array_merge($stack, array($result))) ) );
    							if ($subres !== FALSE) {
    								$this->store( $result, $subres );
    								$_748 = TRUE; break;
    							}
    							$result = $res_709;
    							$this->pos = $pos_709;
    							$_746 = NULL;
    							do {
    								$res_711 = $result;
    								$pos_711 = $this->pos;
    								$matcher = 'match_'.'CacheBlock'; $key = $matcher; $pos = $this->pos;
    								$subres = ( $this->packhas( $key, $pos ) ? $this->packread( $key, $pos ) : $this->packwrite( $key, $pos, $this->$matcher(array_merge($stack, array($result))) ) );
    								if ($subres !== FALSE) {
    									$this->store( $result, $subres );
    									$_746 = TRUE; break;
    								}
    								$result = $res_711;
    								$this->pos = $pos_711;
    								$_744 = NULL;
    								do {
    									$res_713 = $result;
    									$pos_713 = $this->pos;
    									$matcher = 'match_'.'UncachedBlock'; $key = $matcher; $pos = $this->pos;
    									$subres = ( $this->packhas( $key, $pos ) ? $this->packread( $key, $pos ) : $this->packwrite( $key, $pos, $this->$matcher(array_merge($stack, array($result))) ) );
    									if ($subres !== FALSE) {
    										$this->store( $result, $subres );
    										$_744 = TRUE; break;
    									}
    									$result = $res_713;
    									$this->pos = $pos_713;
    									$_742 = NULL;
    									do {
    										$res_715 = $result;
    										$pos_715 = $this->pos;
    										$matcher = 'match_'.'Include'; $key = $matcher; $pos = $this->pos;
    										$subres = ( $this->packhas( $key, $pos ) ? $this->packread( $key, $pos ) : $this->packwrite( $key, $pos, $this->$matcher(array_merge($stack, array($result))) ) );
    										if ($subres !== FALSE) {
    											$this->store( $result, $subres );
    											$_742 = TRUE; break;
    										}
    										$result = $res_715;
    										$this->pos = $pos_715;
    										$_740 = NULL;
    										do {
    											$res_717 = $result;
    											$pos_717 = $this->pos;
    											$matcher = 'match_'.'ClosedBlock'; $key = $matcher; $pos = $this->pos;
    											$subres = ( $this->packhas( $key, $pos ) ? $this->packread( $key, $pos ) : $this->packwrite( $key, $pos, $this->$matcher(array_merge($stack, array($result))) ) );
    											if ($subres !== FALSE) {
    												$this->store( $result, $subres );
    												$_740 = TRUE; break;
    											}
    											$result = $res_717;
    											$this->pos = $pos_717;
    											$_738 = NULL;
    											do {
    												$res_719 = $result;
    												$pos_719 = $this->pos;
    												$matcher = 'match_'.'OpenBlock'; $key = $matcher; $pos = $this->pos;
    												$subres = ( $this->packhas( $key, $pos ) ? $this->packread( $key, $pos ) : $this->packwrite( $key, $pos, $this->$matcher(array_merge($stack, array($result))) ) );
    												if ($subres !== FALSE) {
    													$this->store( $result, $subres );
    													$_738 = TRUE; break;
    												}
    												$result = $res_719;
    												$this->pos = $pos_719;
    												$_736 = NULL;
    												do {
    													$res_721 = $result;
    													$pos_721 = $this->pos;
    													$matcher = 'match_'.'MalformedBlock'; $key = $matcher; $pos = $this->pos;
    													$subres = ( $this->packhas( $key, $pos ) ? $this->packread( $key, $pos ) : $this->packwrite( $key, $pos, $this->$matcher(array_merge($stack, array($result))) ) );
    													if ($subres !== FALSE) {
    														$this->store( $result, $subres );
    														$_736 = TRUE; break;
    													}
    													$result = $res_721;
    													$this->pos = $pos_721;
    													$_734 = NULL;
    													do {
    														$res_723 = $result;
    														$pos_723 = $this->pos;
    														$matcher = 'match_'.'MismatchedEndBlock'; $key = $matcher; $pos = $this->pos;
    														$subres = ( $this->packhas( $key, $pos ) ? $this->packread( $key, $pos ) : $this->packwrite( $key, $pos, $this->$matcher(array_merge($stack, array($result))) ) );
    														if ($subres !== FALSE) {
    															$this->store( $result, $subres );
    															$_734 = TRUE; break;
    														}
    														$result = $res_723;
    														$this->pos = $pos_723;
    														$_732 = NULL;
    														do {
    															$res_725 = $result;
    															$pos_725 = $this->pos;
    															$matcher = 'match_'.'MalformedBracketInjection'; $key = $matcher; $pos = $this->pos;
    															$subres = ( $this->packhas( $key, $pos ) ? $this->packread( $key, $pos ) : $this->packwrite( $key, $pos, $this->$matcher(array_merge($stack, array($result))) ) );
    															if ($subres !== FALSE) {
    																$this->store( $result, $subres );
    																$_732 = TRUE; break;
    															}
    															$result = $res_725;
    															$this->pos = $pos_725;
    															$_730 = NULL;
    															do {
    																$res_727 = $result;
    																$pos_727 = $this->pos;
    																$matcher = 'match_'.'Injection'; $key = $matcher; $pos = $this->pos;
    																$subres = ( $this->packhas( $key, $pos ) ? $this->packread( $key, $pos ) : $this->packwrite( $key, $pos, $this->$matcher(array_merge($stack, array($result))) ) );
    																if ($subres !== FALSE) {
    																	$this->store( $result, $subres );
    																	$_730 = TRUE; break;
    																}
    																$result = $res_727;
    																$this->pos = $pos_727;
    																$matcher = 'match_'.'Text'; $key = $matcher; $pos = $this->pos;
    																$subres = ( $this->packhas( $key, $pos ) ? $this->packread( $key, $pos ) : $this->packwrite( $key, $pos, $this->$matcher(array_merge($stack, array($result))) ) );
    																if ($subres !== FALSE) {
    																	$this->store( $result, $subres );
    																	$_730 = TRUE; break;
    																}
    																$result = $res_727;
    																$this->pos = $pos_727;
    																$_730 = FALSE; break;
    															}
    															while(0);
    															if( $_730 === TRUE ) {
    																$_732 = TRUE; break;
    															}
    															$result = $res_725;
    															$this->pos = $pos_725;
    															$_732 = FALSE; break;
    														}
    														while(0);
    														if( $_732 === TRUE ) { $_734 = TRUE; break; }
    														$result = $res_723;
    														$this->pos = $pos_723;
    														$_734 = FALSE; break;
    													}
    													while(0);
    													if( $_734 === TRUE ) { $_736 = TRUE; break; }
    													$result = $res_721;
    													$this->pos = $pos_721;
    													$_736 = FALSE; break;
    												}
    												while(0);
    												if( $_736 === TRUE ) { $_738 = TRUE; break; }
    												$result = $res_719;
    												$this->pos = $pos_719;
    												$_738 = FALSE; break;
    											}
    											while(0);
    											if( $_738 === TRUE ) { $_740 = TRUE; break; }
    											$result = $res_717;
    											$this->pos = $pos_717;
    											$_740 = FALSE; break;
    										}
    										while(0);
    										if( $_740 === TRUE ) { $_742 = TRUE; break; }
    										$result = $res_715;
    										$this->pos = $pos_715;
    										$_742 = FALSE; break;
    									}
    									while(0);
    									if( $_742 === TRUE ) { $_744 = TRUE; break; }
    									$result = $res_713;
    									$this->pos = $pos_713;
    									$_744 = FALSE; break;
    								}
    								while(0);
    								if( $_744 === TRUE ) { $_746 = TRUE; break; }
    								$result = $res_711;
    								$this->pos = $pos_711;
    								$_746 = FALSE; break;
    							}
    							while(0);
    							if( $_746 === TRUE ) { $_748 = TRUE; break; }
    							$result = $res_709;
    							$this->pos = $pos_709;
    							$_748 = FALSE; break;
    						}
    						while(0);
    						if( $_748 === TRUE ) { $_750 = TRUE; break; }
    						$result = $res_707;
    						$this->pos = $pos_707;
    						$_750 = FALSE; break;
    					}
    					while(0);
    					if( $_750 === TRUE ) { $_752 = TRUE; break; }
    					$result = $res_705;
    					$this->pos = $pos_705;
    					$_752 = FALSE; break;
    				}
    				while(0);
    				if( $_752 === TRUE ) { $_754 = TRUE; break; }
    				$result = $res_703;
    				$this->pos = $pos_703;
    				$_754 = FALSE; break;
    			}
    			while(0);
    			if( $_754 === FALSE) { $_756 = FALSE; break; }
    			$_756 = TRUE; break;
    		}
    		while(0);
    		if( $_756 === FALSE) {
    			$result = $res_757;
    			$this->pos = $pos_757;
    			unset( $res_757 );
    			unset( $pos_757 );
    			break;
    		}
    		$count += 1;
    	}
    	if ($count > 0) { return $this->finalise($result); }
    	else { return FALSE; }
    }




    /**
     * The TopTemplate also includes the opening stanza to start off the template
     */
    function TopTemplate__construct(&$res)
    {
        $res['php'] = "<?php" . PHP_EOL;
    }

    /* Text: (
        / [^<${\\]+ / |
        / (\\.) / |
        '<' !'%' |
        '$' !(/[A-Za-z_]/) |
        '{' !'$' |
        '{$' !(/[A-Za-z_]/)
    )+ */
    protected $match_Text_typestack = array('Text');
    function match_Text ($stack = array()) {
    	$matchrule = "Text"; $result = $this->construct($matchrule, $matchrule, null);
    	$count = 0;
    	while (true) {
    		$res_796 = $result;
    		$pos_796 = $this->pos;
    		$_795 = NULL;
    		do {
    			$_793 = NULL;
    			do {
    				$res_758 = $result;
    				$pos_758 = $this->pos;
    				if (( $subres = $this->rx( '/ [^<${\\\\]+ /' ) ) !== FALSE) {
    					$result["text"] .= $subres;
    					$_793 = TRUE; break;
    				}
    				$result = $res_758;
    				$this->pos = $pos_758;
    				$_791 = NULL;
    				do {
    					$res_760 = $result;
    					$pos_760 = $this->pos;
    					if (( $subres = $this->rx( '/ (\\\\.) /' ) ) !== FALSE) {
    						$result["text"] .= $subres;
    						$_791 = TRUE; break;
    					}
    					$result = $res_760;
    					$this->pos = $pos_760;
    					$_789 = NULL;
    					do {
    						$res_762 = $result;
    						$pos_762 = $this->pos;
    						$_765 = NULL;
    						do {
    							if (substr($this->string ?? '',$this->pos ?? 0,1) == '<') {
    								$this->pos += 1;
    								$result["text"] .= '<';
    							}
    							else { $_765 = FALSE; break; }
    							$res_764 = $result;
    							$pos_764 = $this->pos;
    							if (substr($this->string ?? '',$this->pos ?? 0,1) == '%') {
    								$this->pos += 1;
    								$result["text"] .= '%';
    								$result = $res_764;
    								$this->pos = $pos_764;
    								$_765 = FALSE; break;
    							}
    							else {
    								$result = $res_764;
    								$this->pos = $pos_764;
    							}
    							$_765 = TRUE; break;
    						}
    						while(0);
    						if( $_765 === TRUE ) { $_789 = TRUE; break; }
    						$result = $res_762;
    						$this->pos = $pos_762;
    						$_787 = NULL;
    						do {
    							$res_767 = $result;
    							$pos_767 = $this->pos;
    							$_772 = NULL;
    							do {
    								if (substr($this->string ?? '',$this->pos ?? 0,1) == '$') {
    									$this->pos += 1;
    									$result["text"] .= '$';
    								}
    								else { $_772 = FALSE; break; }
    								$res_771 = $result;
    								$pos_771 = $this->pos;
    								$_770 = NULL;
    								do {
    									if (( $subres = $this->rx( '/[A-Za-z_]/' ) ) !== FALSE) {
    										$result["text"] .= $subres;
    									}
    									else { $_770 = FALSE; break; }
    									$_770 = TRUE; break;
    								}
    								while(0);
    								if( $_770 === TRUE ) {
    									$result = $res_771;
    									$this->pos = $pos_771;
    									$_772 = FALSE; break;
    								}
    								if( $_770 === FALSE) {
    									$result = $res_771;
    									$this->pos = $pos_771;
    								}
    								$_772 = TRUE; break;
    							}
    							while(0);
    							if( $_772 === TRUE ) { $_787 = TRUE; break; }
    							$result = $res_767;
    							$this->pos = $pos_767;
    							$_785 = NULL;
    							do {
    								$res_774 = $result;
    								$pos_774 = $this->pos;
    								$_777 = NULL;
    								do {
    									if (substr($this->string ?? '',$this->pos ?? 0,1) == '{') {
    										$this->pos += 1;
    										$result["text"] .= '{';
    									}
    									else { $_777 = FALSE; break; }
    									$res_776 = $result;
    									$pos_776 = $this->pos;
    									if (substr($this->string ?? '',$this->pos ?? 0,1) == '$') {
    										$this->pos += 1;
    										$result["text"] .= '$';
    										$result = $res_776;
    										$this->pos = $pos_776;
    										$_777 = FALSE; break;
    									}
    									else {
    										$result = $res_776;
    										$this->pos = $pos_776;
    									}
    									$_777 = TRUE; break;
    								}
    								while(0);
    								if( $_777 === TRUE ) { $_785 = TRUE; break; }
    								$result = $res_774;
    								$this->pos = $pos_774;
    								$_783 = NULL;
    								do {
    									if (( $subres = $this->literal( '{$' ) ) !== FALSE) {
    										$result["text"] .= $subres;
    									}
    									else { $_783 = FALSE; break; }
    									$res_782 = $result;
    									$pos_782 = $this->pos;
    									$_781 = NULL;
    									do {
    										if (( $subres = $this->rx( '/[A-Za-z_]/' ) ) !== FALSE) {
    											$result["text"] .= $subres;
    										}
    										else { $_781 = FALSE; break; }
    										$_781 = TRUE; break;
    									}
    									while(0);
    									if( $_781 === TRUE ) {
    										$result = $res_782;
    										$this->pos = $pos_782;
    										$_783 = FALSE; break;
    									}
    									if( $_781 === FALSE) {
    										$result = $res_782;
    										$this->pos = $pos_782;
    									}
    									$_783 = TRUE; break;
    								}
    								while(0);
    								if( $_783 === TRUE ) { $_785 = TRUE; break; }
    								$result = $res_774;
    								$this->pos = $pos_774;
    								$_785 = FALSE; break;
    							}
    							while(0);
    							if( $_785 === TRUE ) { $_787 = TRUE; break; }
    							$result = $res_767;
    							$this->pos = $pos_767;
    							$_787 = FALSE; break;
    						}
    						while(0);
    						if( $_787 === TRUE ) { $_789 = TRUE; break; }
    						$result = $res_762;
    						$this->pos = $pos_762;
    						$_789 = FALSE; break;
    					}
    					while(0);
    					if( $_789 === TRUE ) { $_791 = TRUE; break; }
    					$result = $res_760;
    					$this->pos = $pos_760;
    					$_791 = FALSE; break;
    				}
    				while(0);
    				if( $_791 === TRUE ) { $_793 = TRUE; break; }
    				$result = $res_758;
    				$this->pos = $pos_758;
    				$_793 = FALSE; break;
    			}
    			while(0);
    			if( $_793 === FALSE) { $_795 = FALSE; break; }
    			$_795 = TRUE; break;
    		}
    		while(0);
    		if( $_795 === FALSE) {
    			$result = $res_796;
    			$this->pos = $pos_796;
    			unset( $res_796 );
    			unset( $pos_796 );
    			break;
    		}
    		$count += 1;
    	}
    	if ($count > 0) { return $this->finalise($result); }
    	else { return FALSE; }
    }




    /**
     * We convert text
     */
    function Text__finalise(&$res)
    {
        $text = $res['text'];

        // Unescape any escaped characters in the text, then put back escapes for any single quotes and backslashes
        $text = stripslashes($text ?? '');
        $text = addcslashes($text ?? '', '\'\\');

        $res['php'] .= '$val .= \'' . $text . '\';' . PHP_EOL;
    }

    /******************
     * Here ends the parser itself. Below are utility methods to use the parser
     */

    /**
     * Compiles some passed template source code into the php code that will execute as per the template source.
     *
     * @throws SSTemplateParseException
     * @param string $string The source of the template
     * @param string $templateName The name of the template, normally the filename the template source was loaded from
     * @param bool $includeDebuggingComments True is debugging comments should be included in the output
     * @param bool $topTemplate True if this is a top template, false if it's just a template
     * @return string The php that, when executed (via include or exec) will behave as per the template source
     */
    public function compileString(string $string, string $templateName = "", bool $includeDebuggingComments = false, bool $topTemplate = true): string
    {
        if (!trim($string ?? '')) {
            $code = '';
        } else {
            parent::__construct($string);

            $this->includeDebuggingComments = $includeDebuggingComments;

            // Ignore UTF8 BOM at beginning of string.
            if (substr($string ?? '', 0, 3) == pack("CCC", 0xef, 0xbb, 0xbf)) {
                $this->pos = 3;
            }

            // Match the source against the parser
            if ($topTemplate) {
                $result = $this->match_TopTemplate();
            } else {
                $result = $this->match_Template();
            }
            if (!$result) {
                throw new SSTemplateParseException('Unexpected problem parsing template', $this);
            }

            // Get the result
            $code = $result['php'];
        }

        // Include top level debugging comments if desired
        if ($includeDebuggingComments && $templateName && stripos($code ?? '', "<?xml") === false) {
            $code = $this->includeDebuggingComments($code, $templateName);
        }

        return $code;
    }

    /**
     * @param string $code
     * @param string $templateName
     * @return string $code
     */
    protected function includeDebuggingComments(string $code, string $templateName): string
    {
        // If this template contains a doctype, put it right after it,
        // if not, put it after the <html> tag to avoid IE glitches.
        // Some cached templates will have a preg_match looking for the doctype, so we use a
        // negative lookbehind to exclude that from our matches.
        if (preg_match('/(?<!preg_match\(\'\/)<!doctype/i', $code)) {
            $code = preg_replace('/((?<!preg_match\(\'\/)<!doctype[^>]*("[^"]")*[^>]*>)/im', "$1\r\n<!-- template $templateName -->", $code ?? '');
            $code .= "\r\n" . '$val .= \'<!-- end template ' . $templateName . ' -->\';';
        } elseif (stripos($code ?? '', "<html") !== false) {
            $code = preg_replace_callback('/(.*)(<html[^>]*>)(.*)/i', function ($matches) use ($templateName) {
                if (stripos($matches[3] ?? '', '<!--') === false && stripos($matches[3] ?? '', '-->') !== false) {
                    // after this <html> tag there is a comment close but no comment has been opened
                    // this most likely means that this <html> tag is inside a comment
                    // we should not add a comment inside a comment (invalid html)
                    // lets append it at the end of the comment
                    // an example case for this is the html5boilerplate: <!--[if IE]><html class="ie"><![endif]-->
                    return $matches[0];
                } else {
                    // all other cases, add the comment and return it
                    return "{$matches[1]}{$matches[2]}<!-- template $templateName -->{$matches[3]}";
                }
            }, $code ?? '');
            $code = preg_replace('/(<\/html[^>]*>)/i', "<!-- end template $templateName -->$1", $code ?? '');
        } else {
            $code = str_replace('<?php' . PHP_EOL, '<?php' . PHP_EOL . '$val .= \'<!-- template ' . $templateName .
                ' -->\';' . "\r\n", $code ?? '');
            $code .= "\r\n" . '$val .= \'<!-- end template ' . $templateName . ' -->\';';
        }
        return $code;
    }

    /**
     * Compiles some file that contains template source code, and returns the php code that will execute as per that
     * source
     *
     * @param string $template - A file path that contains template source code
     * @return string - The php that, when executed (via include or exec) will behave as per the template source
     */
    public function compileFile(string $template): string
    {
        return $this->compileString(file_get_contents($template ?? ''), $template);
    }
}
