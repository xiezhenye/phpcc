<?php
include '../src/phpcc.php';


class Json {
    protected $parser;
    protected $stack = [];
    protected $charset = 'utf-8';
    
    function __construct() {
        $tokens = [
            '{','}',',',':','true','false','null','[',']',
            'number'=>'[-]?(0|[1-9][0-9]*)(\.[0-9]+)?([eE][+-]?[0-9]+)?',
            'string'=>'"([^"\\\\]|[\\\\](["\\\\/bfnrt]|u[0-9a-z]{4}))*"',
            'sp'=>'\s+',
        ];
        $rules = [
            'Value'=>[
                [[ ['|','string','number','Object','Array','true','false','null'] ], true],
            ],
            'Object'=>[
                [['{',['?',"string",":","Value",['*',',',"string",":","Value"]],'}'],true],
            ],
            'Array'=>[
                [['[', ['?', 'Value', ['*', ',', 'Value']], ']'], true],
            ],
        ];
        $lexer = new phpcc\Lexer($tokens);
        $parser = new phpcc\Parser();
        $parser->setLexer($lexer);
        $parser->init($rules);
        $parser->setSkipTokens(['sp']);
        $this->parser = $parser;
    }
    
    function parseString($s) {
        $ret = substr($s, 1, -1);
        $map = [
          '/'=>'/',
          '"'=>'"',
          'n'=>"\n",
          'r'=>"\r",
          't'=>"\t",
          'b'=>"\x08",
          'f'=>"\f",
        ];
        $regex = '([\\\\](["\\\\/bfnrt]|u[0-9a-z]{4}))U';
        $ret = preg_replace_callback($regex, function($m) use ($map) {
            if (isset($map[$m[1]])) {
                return $map[$m[1]];
            }
            $c = substr($m[1], 3,2).substr($m[1], 1,2);
            return iconv('utf-16', $this->charset, pack('H*', $c));
        }, $ret);
        return $ret;
    }
    
    function parse($s) {
        $stack = [];
        //$this->parser->printTree($s, true);
        $this->parser->parse($s, function($name, $tokens) use (&$stack){
            switch ($name) {
            case 'Value':
                $need_push = true;
                switch ($tokens[0][0]) {
                case 'number':
                    $v = floatval($tokens[0][1]);
                    break;
                case 'string':
                    $v = $this->parseString($tokens[0][1]);
                    break;
                case 'true':
                    $v = true;
                    break;
                case 'false':
                    $v = false;
                    break;
                case 'null':
                    $v = null;
                    break;
                default:
                    $need_push = false;
                    break;
                }
                if ($need_push) {
                    array_push($stack, $v);
                }
                break;
            case 'Object':
                $obj = [];
                for ($i = count($tokens) - 4; $i > 0; $i-= 4) {
                    $k = $this->parseString($tokens[$i][1]);
                    $v = array_pop($stack);
                    $obj[$k] = $v;
                }
                array_push($stack, (object)array_reverse($obj, true));
                break;
            case 'Array':
                $arr = [];
                for ($i = count($tokens) - 2; $i > 0; $i-= 2) {
                    $v = array_pop($stack);
                    $arr[]= $v;
                }
                array_push($stack, array_reverse($arr));
                break;
            }
        });
        return $stack[0];
    }
}

$j = new Json();

var_dump($j->parse('{"abc":123,"B":["a","b","c"],"c":"\\u795e\\u4ed9"}'));
var_dump($j->parse('{"a":{"V":1},"b":{},"c":[],"d":true}'));
var_dump($j->parse('"abcde"'));
var_dump($j->parse('null'));
var_dump($j->parse('false'));
