<?php
include __DIR__.'/../src/Lexer.php';
include __DIR__.'/../src/Parser.php';

class Calculator {
    protected $parser;
    protected $stack = [];
    
    function __construct() {
        $tokens = [
            '+', '-', '*', '/', '(', ')',
            'sin', 'cos', 'tan', 'ln',
            'pi', 'e',
            'd'=>'[1-9][0-9]*',
            'f'=>'[0-9]+\.[0-9]+',
            'sp'=>'\s+',
            'var'=>'[A-Z]+',
        ];
        $rules = [
            'Exp'  => [
                [['L1Exp'], false],
            ],
            'L1Exp' => [
                [['L1Exp','+','L2Exp'], true, 'Add'],
                [['L1Exp','-','L2Exp'], true, 'Minus'],
                [['L2Exp'], false],
            ],
            'L2Exp' => [
                [['L2Exp','*','L3Exp'], true, 'Multiply'],
                [['L2Exp','/','L3Exp'], true, 'Divide'],
                [['L3Exp'], false],
            ],
            'L3Exp' => [
                [['-', 'L3Exp'], true, 'Reverse'],
                [['Func'], false],
                [['Number'], false],
                [['(','Exp',')'], false],
                [['Number', 'Const'], true, 'Multiply'],
                [['Number', 'Func'], true, 'Multiply'],
            ],
            'Func' => [
                [['sin', 'L3Exp'], true, 'Sin'],
                [['cos', 'L3Exp'], true, 'Cos'],
                [['tan', 'L3Exp'], true, 'Tan'],
                [['ln', 'L3Exp'], true, 'Ln'],
            ],
            'Number' => [
                [[['|','Scala','Const']], false],
            ],
            'Scala' => [
                [[['|','d','f']], true],
            ],
            'Const' => [
                [[['|','pi','e']], true],
            ]
        ];
        $lexer = new phpcc\Lexer($tokens);
        $parser = new phpcc\Parser();
        $parser->setLexer($lexer);
        $parser->init($rules);
        $parser->setSkipTokens(['sp']);
        $this->parser = $parser;
    }
    
    function _calc($rule, $items) {
        if ($rule == 'Scala') {
            switch ($items[0][0]) {
            case 'd':
                $this->stack[]= intval($items[0][1]);
                break;
            case 'f':
                $this->stack[]= floatval($items[0][1]);
                break;
            }
        } elseif ($rule == 'Const') {
            switch ($items[0][0]) {
            case 'e':
                $this->stack[]= M_E;
                break;
            case 'pi':
                $this->stack[]= M_PI;
                break;
            }
        } else {
            $need_push = true;
            switch ($rule) {
            case 'Add':
                $d2 = array_pop($this->stack);
                $d1 = array_pop($this->stack);
                $r = $d1 + $d2;
                break;
            case 'Minus':
                $d2 = array_pop($this->stack);
                $d1 = array_pop($this->stack);
                $r = $d1 - $d2;
                break;
            case 'Multiply':
                $d2 = array_pop($this->stack);
                $d1 = array_pop($this->stack);
                $r = $d1 * $d2;
                break;
            case 'Divide':
                $d2 = array_pop($this->stack);
                $d1 = array_pop($this->stack);
                $r = $d1 / $d2;
                break;
            case 'Reverse':
                $d1 = array_pop($this->stack);
                $r = -$d1;
                break;
            case 'Sin':
                $d1 = array_pop($this->stack);
                $r = sin($d1);
                break;
            case 'Cos':
                $d1 = array_pop($this->stack);
                $r = cos($d1);
                break;
            case 'Tan':
                $d1 = array_pop($this->stack);
                $r = tan($d1);
                break;
            case 'Ln':
                $d1 = array_pop($this->stack);
                $r = log($d1);
                break;
            default:
                $need_push = false;
                break;
            }
            if ($need_push) {
                array_push($this->stack, $r);
            }
        }
    }
    
    function calc($expression) {
        $this->stack = [];
        $this->parser->parse($expression, [$this, '_calc']);
        return $this->stack[0];
    }
    
    function tree($expression) {
        $this->parser->printTree($expression);
    }
}

/////////////////////////////////////////////////////////////
if ($_SERVER['SCRIPT_FILENAME'] == __FILE__) {
    $calc = new Calculator();
    while ($exp = fgets(STDIN)) {
        try {
            $result = $calc->calc($exp);
            echo "$result\n";
        } catch (Exception $e) {
            echo $e->getMessage(), "\n";
        }
    }
    exit;
}
