<?php

namespace phpcc;

include '../src/Lexer.php';
include '../src/Parser.php';

class ParserTest extends \PHPUnit_Framework_TestCase {
    function setUp() {
        
    }
    
    function testFirst() {
        $buidler = new LALR1Builder([
            'A'=>[
                [['(','A',')'], true],
                [['d'], true],
              ]
        ]);
        $f = $buidler->getFirst('A');
        $this->assertEquals(['d'=>'d','('=>'(',], $f);
        
        $f = $buidler->getFirst('d');
        $this->assertEquals(['d'=>'d'], $f);
        
        $f = $buidler->getFirst('(');
        $this->assertEquals(['('=>'('], $f);
        
        $buidler = new LALR1Builder([
            'A'=>[
                [['d'], true],
                [['B'], true],
              ],
            'B'=>[
                [['f'], true],
              ]
        ]);
        $f = $buidler->getFirst('A');
        $this->assertEquals(['d'=>'d','f'=>'f',], $f);
        $f = $buidler->getFirst('B');
        $this->assertEquals(['f'=>'f',], $f);
    }
    
    function testIsFinal() {
        $buidler = new LALR1Builder([
            'A'=>[
                  [['(','A',')'], true],
                  [['d'], true],
                ]
        ]);
        $this->assertTrue($buidler->isFinal('d'));
        $this->assertTrue($buidler->isFinal('('));
        $this->assertFalse($buidler->isFinal('A'));
    }
    
    function testRoot() {
        $buidler = new LALR1Builder([
            'A'=>[
                [['(','B',')'], true],
                [['d'], true],
              ],
            'B'=>[
                [['(','A',')'], true],
                [['d'], true],
              ]
        ]);
        $this->assertEquals('A', $buidler->root());
        
        $buidler = new LALR1Builder([
            'B'=>[
                [['(','A',')'], true],
                [['d'], true],
              ],
            'A'=>[
                [['(','B',')'], true],
                [['d'], true],
              ],
            
        ]);
        $this->assertEquals('B', $buidler->root());
    }
    
    function testRuleHash() {
        $buidler = new LALR1Builder(null);
        $rule = ['A',0,0];
        $hash = $buidler->ruleHash($rule);
        $this->assertEquals(md5('["A",0,0]'), $hash);
    }
    
    function testShift() {
        $buidler = new LALR1Builder([
            'A'=>[
                [['(','A',')'], true],
                [['d'], true],
              ],
        ]);
        $result = $buidler->shiftStateRule(['A',0,0,[''=>'']]);
        $this->assertEquals(4, count($result));
        $this->assertEquals('A', $result[0]);
        $this->assertEquals(0, $result[1]);
        $this->assertEquals(1, $result[2]);
        $this->assertEquals([''=>'',')'=>')'], $result[3]);
    }
    
    function testState() {
        $buidler = new LALR1Builder([
            'A'=>[
                [['d'], true],
              ],
        ]);
        $states = $buidler->build();
        $this->assertEquals(2, count($states));
        $states2 = $buidler->optimize();
        $this->assertEquals(2, count($states2));
        //var_dump($states2[1][2]);
        $this->assertEquals(1, $states2[0][2]['d']);
        $this->assertEquals(0, count($states2[1][2]));
        $this->assertEquals(0, count($states2[0][0]));
        $this->assertEquals(0, count($states2[0][1]));
        $this->assertEquals([''=>0], $states2[1][1]);
        $this->assertEquals(1, count($states2[1][0]));
        $this->assertEquals(['A',['d'], true,'A'], $states2[1][0][0]);
    }
    
    function testLALRException() {
        $buidler = new LALR1Builder([
            'A'=>[
                [['A','a'], true],
                [['A','a'], true],
            ],
        ]);
        try {
            $buidler->build();
            $buidler->optimize();
            $this->fail();
        } catch (LALR1Exception $e) {
            //$this->assertEquals(LALR1Exception::REDUCE_REDUCE_CONFLICT, $e->getCode());
        }
    }
    
    function testRuleCallback() {
        $d = 0;
        $cb = function($rule, $tokens) use (&$d) {
            $d = $tokens;
        };
        $tokens = [
            'd'=>'[0-9]+',
        ];
        $rules = [
            'A'  => [
                [['d'], $cb],
            ],
        ];
        $lexer = new Lexer($tokens);
        $parser = new Parser();
        $parser->setLexer($lexer);
        $parser->init($rules);
        $parser->parse("123", function($rule, $tokens){
            $this->fail();
        });
        $this->assertCount(1, $d);
        $this->assertEquals('d', $d[0][0]);
        $this->assertEquals('123', $d[0][1]);
    }
    
    function testParseException() {
        $tokens = [
            'd'=>'[0-9]',
            '+'=>'\+',
            '-'=>'-',
        ];
        $rules = [
            'exp'  => [
                [['d','+','d'], true],
                [['d','-','d'], true],
            ],
        ];
        $lexer = new Lexer($tokens);
        $parser = new Parser();
        $parser->setLexer($lexer);
        $parser->init($rules);
        try {
            $parser->parse("1++", function(){});
            $this->fail();
        } catch (ParseExcepton $e) {
            //
        }
        try {
            $parser->parse("1+", function(){});
            $this->fail();
        } catch (ParseExcepton $e) {
            //
        }
    }
}
