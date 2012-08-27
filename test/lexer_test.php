<?php
namespace phpcc;
include '../src/Lexer.php';

class LexerTest extends \PHPUnit_Framework_TestCase {
    function setUp() {
        
    }
    
    function testCountGroups() {
        $lexer = new Lexer();
        $count = $lexer->countGroups('(');
        $this->assertEquals(1, $count);
        
        $count = $lexer->countGroups('(aa)((bb)gg)');
        $this->assertEquals(3, $count);
        
        $count = $lexer->countGroups('\\(');
        $this->assertEquals(0, $count);
        
        $count = $lexer->countGroups('\\\\(');
        $this->assertEquals(1, $count);
        
        $count = $lexer->countGroups('(\\\\()');
        $this->assertEquals(2, $count);
        
        $count = $lexer->countGroups('\\(()');
        $this->assertEquals(1, $count);
    }
    
    function testLex() {
        $m = [
            'd'=>'[1-9][0-9]*',
            'sp'=>'\s+',
            '+'=>'\+',
            '-'=>'-',
            '*'=>'\*',
            '/'=>'\/',
            '('=>'\(',
            ')'=>'\)',
        ];
        $lexer = new Lexer($m);
        $tokens = $lexer->getAllTokens('(123+44)/56');
        $this->assertEquals(7, count($tokens));
    }
    
}
