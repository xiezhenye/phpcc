# phpcc

phpcc 是一个元编译器，也就是一个用来编写编译器/解释器的库。


## usage

如在样例 sample/json.php 中那样

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

使用 phpcc 需要自己定义词法和语法规则。在 phpcc 中，词法、语法规则都用 php 语法表示，而不是使用类似 yacc 的特殊语法文件。这里用了 php 5.4 中的数组简写方式。

词法规则定义了词，或者说终结符的规则，是一个混合整数下标和字符串下标的数组。使用整数下标（在 php 中，没有写明下标就是自动生成的整数下标）的表示关键词，它的值和名字是一样的。如上面例子中的 '{'、':'、'true'。而用字符串下标定义的则是一个定义了词的正则表达式。如上面例子里的 'string', 'sp'。同时满足多个规则的时候，返回匹配长度最长的结果。长度相同的情况下，优先返回关键词。

语法规则也是一个数组。其中的每一个键就是规则名，或者说非终结符的名字。值是每个规则名对应的规则列表。第一个键表示语法的根规则。

