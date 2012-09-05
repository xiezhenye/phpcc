<?php
include '../src/Lexer.php';
include '../src/Parser.php';

class Calculator {
    protected $parser;
    protected $stack = [];
    
    function __construct() {
        $tokens = [
            'd'=>'[1-9][0-9]*',
            'f'=>'[0-9]+\.[0-9]+',
            'sp'=>'\s+',
            '+'=>'\+',
            '-'=>'-',
            '*'=>'\*',
            '/'=>'\/',
            '('=>'\(',
            ')'=>'\)',
            'var'=>'[A-Z]+',
            'sin'=>'sin',
            'cos'=>'cos',
            'tan'=>'tan',
            'ln'=>'ln',
            'pi'=>'pi',
            'e'=>'e',
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
                [['Const'], false],
                [['Scala'], false],
            ],
            'Scala' => [
                [['d'], true],
                [['f'], true],
            ],
            'Const' => [
                [['pi'], true],
                [['e'], true],
            ]
        ];
        $lexer = new phpcc\Lexer($tokens);
        $parser = new phpcc\Parser();
        $parser->setLexer($lexer);
        $parser->init($rules);
        $parser->setSkipTokens(['sp']);
        //echo json_encode($parser->dump()),"\n";
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
                break;
            }
            array_push($this->stack, $r);
        }
    }
    
    function calc($expression) {
        $this->stack = [];
        $this->parser->parse($expression, [$this, '_calc']);
        return $this->stack[0];
    }
}

/////////////////////////////////////////////////////////////

$calc = new Calculator();

$exp = '100 - (35 - 4)';
$result = $calc->calc($exp);
echo "$exp = $result\n";

$exp = '2 * (3 + 4)';
$result = $calc->calc($exp);
echo "$exp = $result\n";

$exp = '9 / 3 * 2';
$result = $calc->calc($exp);
echo "$exp = $result\n";

$exp = '(4+1) * (6 - 1)';
$result = $calc->calc($exp);
echo "$exp = $result\n";

$exp = '-(11+29)';
$result = $calc->calc($exp);
echo "$exp = $result\n";

$exp = '4 - -3 * 4';
$result = $calc->calc($exp);
echo "$exp = $result\n";

$exp = '-1 * -1';
$result = $calc->calc($exp);
echo "$exp = $result\n";

$exp = '2.5 * 8';
$result = $calc->calc($exp);
echo "$exp = $result\n";

$exp = 'cos pi/2';
$result = $calc->calc($exp);
echo "$exp = $result\n";

$exp = '4 pi-1';
$result = $calc->calc($exp);
echo "$exp = $result\n";

$exp = '2cos -pi';
$result = $calc->calc($exp);
echo "$exp = $result\n";
