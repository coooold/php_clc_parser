<?php
include(__DIR__ . '/../src/Parser.php');

use \CLCParser\Parser;

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
