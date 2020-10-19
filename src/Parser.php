<?php

/**
 * @Author coooold <coooold@outlook.com>
 * @Version 2019
 */

namespace CLCParser;

/**
 * 中图分类号解析器，可以处理一二三级
 */
class Parser
{
    /* 不含复分信息的正则表达式 */
    // 粗略判断，允许非法的如M、TZ等大类目
    const REGEX_CLC_CLASSIC = '[A-Z]{2}\d{0,3}';
    // 仅允许第五版规定的大类目
    const REGEX_CLC_CLASSIC_V5_STRICT = '(?:[A-K]|[N-V]|X|Z)[A-Z]?\d{0,3}';

    // 用于清理的正则
    private $cleanRegex = '';
    // 用于判断二级分类的正则
    private $clcTreeRegex = [];
    // 储存所有中图号对应的信息，到三级为止
    private $clcInfo = [];

    private function __construct()
    {
        $this->cleanRegex = $this->generateCleanRegex();

        $json = file_get_contents(__DIR__ . '/data.json');
        $tree = json_decode($json, true);
        $this->clcTreeRegex = $this->loadTreeJson($tree);

        $this->clcInfo = $this->loadCLCInfo($tree);
    }

    static private $instance = null;
    /**
     * 获得单例
     */
    static private function getInstance()
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * 解析中图分类号，获得三级信息
     * 
     * @param string $str 复杂的图书中图分类号信息
     * 
     * @return array 可能有多个
     */
    static public function parse(string $str): array
    {
        $instance = self::getInstance();

        $res = [];
        $str = str_replace('；', ';', $str);
        $codes = explode(';', $str);
        foreach ($codes as $code) {
            $code = trim($code);
            if (!$code) continue;

            $res[$code] = $instance->parseCode($code);
        }

        return $res;
    }

    /**
     * 通过一二三级中图分类号，查询相关信息
     * 
     * @param string $code 一到三级单个中图分类号
     */
    static public function getCLCInfoByCode(string $code): array
    {
        $instance = self::getInstance();
        return $instance->clcInfo[$code] ?? [];
    }

    /**
     * 解析单个中图分类号
     * 
     * @param string $code 单个中图分类号
     * 
     * @return array 一到三级中图分类号的数组
     */
    private function parseCode($code): array
    {
        $code = $this->clean($code);
        if (!$code) return [];

        // 先解析第一级
        $firstCode = $this->runSubRegexOnCode($code, $this->clcTreeRegex);
        if ($firstCode === false) return [];
        // 再解析第二级
        $secondCode = $this->runSubRegexOnCode($code, $this->clcTreeRegex[$firstCode]['children'] ?? []);
        if ($secondCode === false) return [$firstCode];
        // 再解析第三级
        $thirdCode = $this->runSubRegexOnCode($code, $this->clcTreeRegex[$firstCode]['children'][$secondCode]['children'] ?? []);
        if ($thirdCode === false) return [$firstCode, $secondCode];

        return [$firstCode, $secondCode, $thirdCode];
    }

    /**
     * 传入规则树，扫描下一级规则，传入中图分类号，找到最先匹配项
     * 成功返回中图分类号,失败则返回false
     */
    static private function runSubRegexOnCode($code, $regexTree = [])
    {
        if (!$regexTree) return false;
        foreach ($regexTree as $key => $value) {
            if (preg_match($value['regex'], $code) > 0) {
                return $key;
            }
        }
        return false;
    }

    /**
     * 清洗中图分类号，输出最简单格式
     * @param string $str 复杂格式的单个中图分类号
     * @return string 清洗后的中图分类号
     */
    private function clean(string $str)
    {
        $str = trim($str);
        preg_match($this->cleanRegex, $str, $matches);
        return $matches[1] ?? '';
    }

    /**
     * 加载中图分类号对应的信息，到三级为止
     * @param array $tree 从json加载的中图分类树数据
     * @return array 中图分类信息数组
     */
    private function loadCLCInfo(array $tree): array
    {
        $res = [];
        foreach ($tree as $first) {
            $firstCode = $first['code'];
            $firstName = $first['name'];
            foreach ($first['children'] as $second) {
                $secondCode = $second['code'];
                $secondName = $second['name'];
                foreach ($second['children'] as $third) {
                    $thirdCode = $third['code'];
                    $thirdName = $third['name'];

                    $res[$thirdCode] = [
                        'code' => $thirdCode,
                        'name' => $thirdName,
                        'path' => [$firstCode, $secondCode, $thirdCode],
                        'namePath' => [$firstName, $secondName, $thirdName],
                    ];
                }

                $res[$secondCode] = [
                    'code' => $secondCode,
                    'name' => $secondName,
                    'path' => [$firstCode, $secondCode],
                    'namePath' => [$firstName, $secondName],
                ];
            }

            $res[$firstCode] = [
                'code' => $firstCode,
                'name' => $firstName,
                'path' => [$firstCode],
                'namePath' => [$firstName],
            ];
        }

        return $res;
    }

    /**
     * 加载资源文件，获得中图分类树状结构
     * @param array $tree 从json加载的中图分类树数据
     * @return array 中图分类正则表达式规则树
     */
    private function loadTreeJson(array $tree): array
    {
        $res = [];

        foreach ($tree as $first) {   // 一级分类
            $firstCode = $first['code'];
            $firstValue = [
                'regex' => $this->buildRegexFromCodes([$firstCode]),
                'children' => [],
            ];

            foreach ($first['children'] as $second) { // 第二级
                $secondCode = $second['code'];
                $subCodes = $this->getChildrenCodesRecursively($second);
                $secondValue = [
                    'regex' => $this->buildRegexFromCodes($subCodes),
                    'children' => [],
                ];


                foreach ($second['children'] as $third) { // 第三级
                    $thirdCode = $third['code'];
                    $subCodes = $this->getChildrenCodesRecursively($third);
                    $thirdValue = [
                        'regex' => $this->buildRegexFromCodes($subCodes),
                        'children' => [],
                    ];
                    $secondValue['children'][$thirdCode] = $thirdValue;
                }
                $firstValue['children'][$secondCode] = $secondValue;
            }

            $res[$firstCode] = $firstValue;
        }

        return $res;
    }

    /**
     * 通过多个中图分类号，创建识别的正则函数
     * @param array $codes 多个中图分类号
     * @return string 正则表达式字符串
     */
    private function buildRegexFromCodes(array $codes): string
    {
        $codesStr = implode('|', $codes);
        $codesRegex = '/^' . str_replace(
            ['.', '+', '-'],
            ['\.', '[+]', '[\-]'],
            $codesStr
        ) . '/i';
        return $codesRegex;
    }

    /**
     * 通过树状结构，遍历加载所有的子孙节点中图分类号
     * @return array
     */
    private function getChildrenCodesRecursively(array $node)
    {
        $res = $this->parseCLCCodeStr($node['code']);
        foreach ($node['children'] as $child) {
            $res = array_merge($res, $this->getChildrenCodesRecursively($child));
        }
        $res = array_reverse($res);
        return $res;
    }

    /**
     * 解析json中的分类号，先做清洗，遇到/字符就展开，返回处理后的所有中图分类号
     * @param string $str 未展开的中图分类号
     * @return array 展开后的中图分类数组
     */
    private function parseCLCCodeStr(string $str)
    {
        $str = str_replace([
            '[', ']', '{', '}'
        ], '', $str);

        $res = [];
        // X922.3/.7 这个格式
        $regex1 = '/^(.+?)\.(\d+)\/\.(\d+)(.*?)$/';
        // T-013/-017 这个格式
        $regex2 = '/^(.+?)[\-](\d+)\/[\-](\d+)(.*?)$/';
        // Z813/817 这个格式
        $regex3 = '/^(.+?)(\d+)\/(\d+)(.*?)$/';

        if (preg_match($regex1, $str, $matches)) {
            $prefix = $matches[1];
            $start = intval($matches[2]);
            $end = intval($matches[3]);
            $postfix = $matches[4];
            for ($i = $start; $i <= $end; $i++) {
                $res[] = "{$prefix}.{$i}{$postfix}";
            }
        } elseif (preg_match($regex2, $str, $matches)) {
            $prefix = $matches[1];
            $start = intval($matches[2]);
            $end = intval($matches[3]);
            $postfix = $matches[4];
            for ($i = $start; $i <= $end; $i++) {
                $res[] = "{$prefix}-{$i}{$postfix}";
            }
        } elseif (preg_match($regex3, $str, $matches)) {
            $prefix = $matches[1];
            $start = intval($matches[2]);
            $end = intval($matches[3]);
            $postfix = $matches[4];
            for ($i = $start; $i <= $end; $i++) {
                $res[] = "{$prefix}{$i}{$postfix}";
            }
        } else {
            $res = [$str];
        }

        return $res;
    }

    /**
     * 输出含复分信息的正则表达式
     * @return string
     */
    private function generateCleanRegex()
    {
        // 可以处理粗略的分类信息
        $regex_clc_simple = '\[?(' . self::REGEX_CLC_CLASSIC_V5_STRICT . '(?:\/\d{1,3})?a?(?:[.\\-=+]\d{1,3}|\(\d{1,3}\)|"\d{1,3}"|<\d{1,3}>)*)\]?';
        // 可以处理形如 {停用分类号}<现用分类号> 的分类信息
        $regex_clc_abandoned_included = '{?' . $regex_clc_simple . '(?:}<(?:' . $regex_clc_simple . ')>)?';
        // 可以处理含组合助记符的分类信息
        $regex_clc_complete = $regex_clc_abandoned_included . '(?:[:+](?:' . $regex_clc_abandoned_included . '))*';

        return "/$regex_clc_complete/";
    }
}
