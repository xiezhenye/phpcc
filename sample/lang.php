<?php
include '../src/phpcc.php';

class Lang {
    protected $parser = null;
    protected $ctx = [];
    protected $globals = [];

    function __construct() {
        $tokens = [
            'sp'=>'[ \t]',
            'comment'=>'#.*',
            'd'=>'[0-9]+',
            'f'=>'[0-9]+\.[0-9]+',
            'nl'=>'[\n]+',
            '=','+','-','*','/','%','(',')','{','}','[',']',',',
            '==','!=','>','<','>=','<=','!','~',
            'name'=>'[a-zA-Z_][a-zA-Z0-9_]*',
            'qname'=>'`[a-zA-Z_][a-zA-Z0-9_]*',
            'func',
        ];
        $rules = [
            'Exps' => [
                [[['+','Exp'] ], true],
            ],
            'Exp' => [
                [['Exp1','nl' ], false],
                [['nl' ], false],
            ],
            'Block' => [
                [['{', 'Exps', '}'], true],
            ],
            'Func' => [
                [['func', 'name', 'ArgList', 'Block'], true],
            ],
            'ArgList' => [
                [ ['(', ['?', 'Arg', ['*', ',', 'Arg']] ,')'] , true],
            ],
            'Arg' => [
                [ ['name'] , false],
                [ ['qname'] , false],
            ],
            'Call' => [
                [['name', '(',['?', 'Exp2',['*', ',', 'Exp2']],')'], true],
            ],
            'Exp1' => [
                [['Exp2'], false],
            ],
            'Exp2' => [
                [['Exp3'], false],
                [['name','=','Exp2'], true, 'Set'],
                [['Func'], false],
                [['Block'], false],
            ],
            'Exp3' => [
                [['Exp3','==','Exp4'], true, 'Eq'],
                [['Exp3','!=','Exp4'], true, 'Ne'],
                [['Exp3','<=','Exp4'], true, 'Le'],
                [['Exp3','>=','Exp4'], true, 'Ge'],
                [['Exp3','<','Exp4'], true, 'Lt'],
                [['Exp3','>','Exp4'], true, 'Gt'],
                [['Exp4'], false],
            ],
            'Exp4' => [
                [['Exp4','+','Exp5'], true, 'Plus'],
                [['Exp4','-','Exp5'], true, 'Minus'],
                [['Exp5'], false],
            ],
            'Exp5' => [
                [['Exp5','*','Exp6'], true, 'Multiply'],
                [['Exp5','/','Exp6'], true, 'Divide'],
                [['Exp5','%','Exp6'], true, 'Mod'],
                [['Exp6'], false],
            ],
            'Exp6' => [
                [['-', 'Exp6'], true, 'Reverse'],
                [['!', 'Exp6'], true, 'Not'],
                [['~', 'Exp6'], true, 'Deqoute'],
                [['Value'], false],
                [['Call'], false],
                [['(','Exp2',')'], false],
                //[['Exp6','[','d',']'], true, 'Subscript'],
            ],
            'Value' => [
                [['Var'], false],
                [['Scala'], false],
                //[['Table'], false],
            ],
            'Scala' => [
                [['d'], true],
                [['f'], true],
            ],
//            'Table' => [
//                [ ['[', ['?', 'Value', ['*', ',', 'Value']] ,']'] , true],
//            ],
            'Var' => [
                [['name'], true],
            ],
        ];
        $lexer = new phpcc\Lexer($tokens);
        $parser = new phpcc\Parser();
        $parser->setLexer($lexer);
        $parser->init($rules);
        $parser->setSkipTokens(['sp','comment']);
        $this->parser = $parser;
        $this->setProcessors();
        $this->setDefaultVars();
    }
    
    function setDefaultVars() {
        $this->addGlobalNativeFunction('sqrt', 'sqrt');
        $this->addGlobalNativeFunction('pow', 'pow');
        $this->addGlobalNativeFunction('print', function($n){
            echo json_encode($n),"\n";
            return 0;
        });
        $this->globals['if'] = function($args, &$ctx) {
            $v = $this->parseTree($args[0], $ctx);
            if ($v) {
                return $this->parseTree($args[1], $ctx);
            } else {
                return $this->parseTree($args[2], $ctx);
            }
        };
        $this->globals['while'] = function($args, &$ctx) {
            while (true) {
                $cond = $this->parseTree($args[0], $ctx);
                if (!$cond) {
                    break;
                }
                $ret = $this->parseTree($args[1], $ctx);
            }
            return $ret;
        };
    }

    function addGlobalNativeFunction($name, $callable) {
        $this->globals[$name] = $this->buildNativeCallable($callable);
    }

    function newContext() {
        return [
            0=>null, // ret, op1
            1=>null, // op2
        ];
    }

    protected  function binOps(&$ctx, $toks) {
        $this->parseTree($toks[0], $ctx);
        $t1 = $ctx[0];
        $this->parseTree($toks[1], $ctx);
        $ctx[1] = $ctx[0];
        $ctx[0] = $t1;
    }

    function  setProcessors() {
        $this->processors = [
            'Set'=>function($toks, &$ctx) {
                $name = $toks[0];
                $this->parseTree($toks[1], $ctx);
                $ctx[$name] = $ctx[0];
            },
            'Scala'=>function($toks, &$ctx) {
                $ctx[0] = $toks[0];
            },
            'Var'=>function($toks, &$ctx) {
                $name = $toks[0];
                if (isset($ctx[$name])) {
                    $ctx[0] =  $ctx[$name];
                } elseif (isset($this->globals[$name])) {
                    $ctx[0] = $this->globals[$name];
                } else {
                    throw new Exception("undefined var $name");
                }
            },
            'Plus'=>function($toks, &$ctx) {
                $this->binOps($ctx, $toks);
                $ctx[0]+= $ctx[1];
            },
            'Minus'=>function($toks, &$ctx) {
                $this->binOps($ctx, $toks);
                $ctx[0]-= $ctx[1];
            },
            'Multiply'=>function($toks, &$ctx) {
                $this->binOps($ctx, $toks);
                $ctx[0]*= $ctx[1];
            },
            'Divide'=>function($toks, &$ctx) {
                $this->binOps($ctx, $toks);
                $ctx[0]/= $ctx[1];
            },
            'Mod'=>function($toks, &$ctx) {
                $this->binOps($ctx, $toks);
                $ctx[0]%= $ctx[1];
            },
            'Eq'=>function($toks, &$ctx) {
                $this->binOps($ctx, $toks);
                $ctx[0] = $ctx[0] == $ctx[1];
            },
            'Ne'=>function($toks, &$ctx) {
                $this->binOps($ctx, $toks);
                $ctx[0] = intval($ctx[0] != $ctx[1]);
            },
            'Lt'=>function($toks, &$ctx) {
                $this->binOps($ctx, $toks);
                $ctx[0] = intval($ctx[0] < $ctx[1]);
            },
            'Gt'=>function($toks, &$ctx) {
                $this->binOps($ctx, $toks);
                $ctx[0] = intval($ctx[0] > $ctx[1]);
            },
            'Le'=>function($toks, &$ctx) {
                $this->binOps($ctx, $toks);
                $ctx[0] = intval($ctx[0] <= $ctx[1]);
            },
            'Ge'=>function($toks, &$ctx) {
                $this->binOps($ctx, $toks);
                $ctx[0] = intval($ctx[0] >= $ctx[1]);
            },
            'Not'=>function($toks, &$ctx) {
                $ctx[0] = intval(!$this->parseTree($toks[0], $ctx));
            },
            'Func' =>function($toks, &$ctx) {
                if (count($toks) == 3) {
                    $al = $toks[1]['tokens'];
                    $code = $toks[2]['tokens'][0];
                    $name = $toks[0];
                } else {
                    $al = $toks[0]['tokens'];
                    $code = $toks[1]['tokens'][0];
                    $name = '';
                }

                $f = ['args'=>$al,'code'=>$code, 'name'=>$name];
                $ctx[0] = $this->buildCallable($f);
                if ($name != '') {
                    $this->globals[$name] = $ctx[0];
                }
            },
            'Deqoute' => function($toks, &$ctx) {
                $v = &$this->parseTree($toks[0], $ctx);
                $ctx[0] = $this->parseTree($v[0], $v[1]);
            },
            'Call' => function($toks, &$ctx) {
                $func_name = array_shift($toks);
                if (isset($this->globals[$func_name])) {
                    $func = $this->globals[$func_name];
                } elseif (isset($ctx[$func_name])) {
                    $func = $ctx[$func_name];
                } else {
                    throw new Exception("symbol $func_name not found");
                }
                $func($toks, $ctx);
            },
        ];
    }

    function buildCallable($func) {
        return function ($raw_args, &$ctx) use($func) {
            if (count($func['args']) != count($raw_args)) {
                return null;
            }
            $new_ctx = $this->newContext();
            foreach ($func['args'] as $i=>$arg_name) {
                if ($arg_name[0] == '`') {
                    $arg_name = substr($arg_name, 1);
                    $new_ctx[$arg_name] = [$raw_args[$i], &$ctx];
                } else {
                    $this->parseTree($raw_args[$i], $ctx);
                    $new_ctx[$arg_name] = $ctx[0];
                }
            }
            $this->parseTree($func['code'], $new_ctx);
            $ctx[0] = $new_ctx[0];
        };
    }

    function buildNativeCallable($func) {
        if (!is_callable($func)) {
            return null;
        }
        return function ($raw_args, &$ctx) use($func) {
            $args = [];
            foreach ($raw_args as $raw_arg) {
                $this->parseTree($raw_arg, $ctx);
                $args[]= $ctx[0];
            }
            $ret = call_user_func_array($func, $args);
            $ctx[0] = $ret;
        };
    }

    function execute($code) {
        $ast = $this->parse($code);
        $ctx = $this->newContext();
        return $this->parseTree($ast, $ctx);
    }

    function parse($code) {
        $ast = ($this->parser->tree($code, false, function(&$t) {
            if ($t[1] === null) {
                return true;
            }
            if ($t[0] == 'd') {
                $t = intval($t[1]);
            } elseif ($t[0] == 'f') {
                $t = floatval($t[1]);
            } elseif ($t[0] == 'name' || $t[0] == 'qname' ) {
                $t = $t[1];
            } else {
                return false;
            }
            return true;
        }));
        return $ast;
    }

    function parseTree($ast, &$ctx) {
        //lazy parse
        $tokens = $ast['tokens'];
        $name = $ast['name'];
        if (!isset($this->processors[$name])) {
            //default processor: parse immediately
            foreach ($tokens as $item) {
                if (!isset($item['name'])) {
                    continue; //final
                }
                $this->parseTree($item, $ctx);
            }
        } else {
            $processor = $this->processors[$name];
            $processor($tokens, $ctx);
        }
        return $ctx[0];
    }
}


/////////////////////////////////////////////////////////////
if ($_SERVER['SCRIPT_FILENAME'] == __FILE__) {
    $f = isset($argv[1]) ? $argv[1] : 'php://stdin';
    $code = file_get_contents($f);
    try {
        $lang = new Lang();
        $result = $lang->execute($code);
        print_r($result);
    } catch (Exception $e) {
        echo $e->getMessage();
    }
    exit;
}


