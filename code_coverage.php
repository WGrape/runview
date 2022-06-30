<?php
$config = [

    // 优先级 switch > mode
    // 即就算mode为production模式, 如果switch为off也不会开启覆盖系统

    'switch' => 'on',

    'mode' => 'testing', // 支持的模式: testing 和 production, 在production模式下会生成生产环境需要的相关数据

    'allowlist' => [],

    'denylist' => [
        '/your_project/app/app_name/libraries/' => ['App_code_coverage.php' => '*'],
    ],

    'valid_line_expr' => [
        '/^(\t|\n|\r|\s|　)*$/', // 空白行
        // '/^.+\s+=\s*[\[]\s*$/', 这个会执行, 所以先不视为无效行 // = [
        '/^\s*(\{|\})\s*$/', // {
        '/^\s*(protected|public|private)\s+function.+$/', // public function
        '/^\s*(class)\s+.+$/', // class A
        '/^\s*\]\s*;\s*$/', // ];
        '/^\s*\/\/.*$/', // 注释
    ],

    // testing模式
    'testing'         => [
        'is_show_full_code' => false,
    ],

    // production模式
    'production'      => [

    ],
];