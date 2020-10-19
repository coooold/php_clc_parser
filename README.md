### 中图分类法解析器

《中图分类法》是《中国图书馆分类法》的简称，是我国通用的类分图书的工具。根据图书资料的特点，按照从总到分，从一般到具体的编制原则，确定分类体系，在五个基本部类的基础上，组成二十二个大类。

《中图法》的标记符号采用汉语拼音字母与阿拉伯数字相结合的混合号码。即用一个字母表示一个大类，以字母的顺序反映大类的序列。字母后用数字表示大类以下类目的划分。数字的编号使用小数制。称为中图分类号。

中图分类号有较为复杂的规则体系，涉及到新分类的插入、展开、移除等，很难通过简单的正则表达式进行处理。使用该类可传入图书原始信息中的中图分类号，解析成一二三级中图分类，并获取对应名称与路径信息。

该库未对性能进行优化，不适合提供线上业务，仅适用于后台信息处理。

使用方法：

```
composer require coooold/php_clc_parser
```

```PHP
// 解析中图分类号，并获取对应的三级分类数组。注意，可能有多个结果，例如 
use \CLCParser\Parser;
Parser::parse('K825.2；E251-53');

/*
Array
(
    [K825.2] => Array
        (
            [0] => K
            [1] => K81
            [2] => K82
        )

    [E251-53] => Array
        (
            [0] => E
            [1] => E2
            [2] => E25
        )

)
*/
```

```PHP
// 获取一二三级中图分类号对应的名称、路径信息
use \CLCParser\Parser;
Parser::getCLCInfoByCode('D92');

/*
Array
(
    [code] => D92
    [name] => 中国法律
    [path] => Array
        (
            [0] => D
            [1] => D9
            [2] => D92
        )

    [namePath] => Array
        (
            [0] => 政治、法律
            [1] => 法律
            [2] => 中国法律
        )

)
*/

```


综合使用案例：
```PHP
$s = <<<DOC
        A
        O1-62
        J523.2"17"+3:G5
        TP312
        K837.125.6(202)+R173:G25a
        [X-019]
        F08:G40-054
        K876.3=49
        G49a
        K825.2；E251-53
        I287.8
        I712.45
        I611.65
        K854-53
        F0-0
        {D922.59} 
        DOC;

foreach (explode("\n", $s) as $code) {
    $code = trim($code);
    echo "\n===== 解析 ", $code, " ===== \n";

    $res = Parser::parse($code);
    foreach ($res as $k => $v) {
        echo "> $k :\n";
        $lastCode = array_pop($v);
        $info = Parser::getCLCInfoByCode($lastCode);
        print_r($info);
    }
}
```