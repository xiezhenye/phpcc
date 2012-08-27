<?php
namespace phpcc;
include '../src/Lexer.php';

class RevokeDBSecurityGroupIngressTest extends \PHPUnit_Framework_TestCase {
    function setUp() {
        
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
