<?php

class App_code_coverage
{
    /**
     * 覆盖系统开关
     * @var bool
     */
    private $switch = false;

    /**
     * 覆盖系统模式
     * @var string
     * @example testing / production
     */
    private $mode = 'testing';

    /**
     * 启动信息
     * @var array
     */
    private $bootInfo = [

        // 目录相关
        'directory' => [
            'eden_dir'   => '', // 如: /data/www/
            'root_dir'   => '', // 如: /data/www/your_project/
            'app_dir'    => '', // 如: /data/www/your_project/app/app_name/
            'output_dir' => '', // 如: /data/www/your_project/reports
        ],
        'env'       => 'testing',
    ];

    /**
     * 配置信息
     * @var array
     */
    private $configInfo = [
        'raw'       => [],

        // 存储格式为md5
        'allowlist' => [
            'directories' => [],
            'files'       => [],
            'functions'   => [],
        ],
        'denylist'  => [
            'directories' => [],
            'files'       => [],
        ],
    ];

    /**
     * 业务信息
     * @var array
     */
    private $businessInfo = [

        // 接口相关
        'api'    => [
            'name'     => '',
            'params'   => [],
            'response' => [],
            'cost'     => [
                'memory'      => [],
                'millisecond' => [],
            ],
        ],

        // 源码相关
        'source' => [
            'project' => '', // 项目名如your_project
            'class'   => '', // 接口类名
        ],
    ];

    /**
     * 资源信息
     * @var array
     */
    private $resourceInfo = [
        'js'  => '',
        'css' => '',
    ];

    /**
     * 代码运行信息
     * @var array
     */
    private $runInfo = [

        // 执行路径
        'path'    => [
            // 原始级别(如xdebug、PR生成的数据)
            'generated' => [],
            // 文件级别
            'file'      => [],
            // 函数级别
            'function'  => [],
        ],

        // 统计信息
        'numeric' => [
            'file'     => [
                'directory_num' => 0,
                'file_num'      => 0,
            ],
            'function' => [
                'directory_num' => 0,
                'file_num'      => 0,
                'function_num'  => 0,

                // 渲染阶段生成
                'coverage'      => 0,
            ],
        ],
    ];

    public function __construct()
    {
        $this->initBootInfo();

        $config           = [];
        $env              = $this->bootInfo['env'];
        $appDir           = $this->bootInfo['directory']['app_dir'];
        $codeCoverageFile = sprintf('%sconfig/%s/code_coverage.php', $appDir, $env);
        if (file_exists($codeCoverageFile)) {
            $config = APPC('code_coverage');
        } else {
            $phplibDir        = $this->bootInfo['directory']['phplib_dir'];
            $codeCoverageFile = sprintf('%sconfig/%s/code_coverage.php', $phplibDir, $env);
            if (file_exists($codeCoverageFile)) {
                $config = C('code_coverage');
            }
        }

        if (!empty($config) && isset($config['switch']) && 'on' === $config['switch']) {
            $this->switch = true;
        }
        if (!$this->isInstalledXdebug()) {
            $this->switch = false;
        }

        if ($this->isSwitchOn()) {
            $this->initConfigInfo($config);
        }
    }

    /**
     * 返回开关状态
     * @return bool
     */
    public function isSwitchOn()
    {
        return $this->switch;
    }

    /**
     * 是否安装了xdebug
     * @return bool
     */
    public function isInstalledXdebug()
    {
        if (!extension_loaded('Xdebug') || !function_exists('xdebug_start_code_coverage')) {
            return false;
        }
        if (!function_exists('xdebug_stop_code_coverage') || !function_exists('xdebug_get_code_coverage')) {
            return false;
        }
        return true;
    }

    /**
     * 是否是testing模式
     * @return bool
     */
    public function isTestingMode()
    {
        return 'testing' === $this->mode;
    }

    /**
     * 是否是production模式
     * @return bool
     */
    public function isProductionMode()
    {
        return 'production' === $this->mode;
    }

    /**
     * 是否被允许执行
     * @param $allowKey
     * @param $value
     * @return bool
     */
    public function isRunAllowed($allowKey, $value)
    {
        $allowlist = $this->configInfo['allowlist'][$allowKey];
        $allow     = empty($allowlist) || in_array($value, $allowlist);

        $denylist = $this->configInfo['denylist'][$allowKey];
        $deny     = !empty($denylist) && in_array($value, $denylist);
        return $allow && !$deny;
    }

    /**
     * 解析许可列表的配置
     * @param $permitKey string
     * @param $permitList array
     */
    public function subAnalysisPermitConfig($permitKey, $permitList)
    {
        foreach ($permitList as $directory => $fileList) {
            if ('*' === $fileList || empty($fileList)) {
                $this->configInfo[$permitKey]['directories'][] = md5($directory);
                continue;
            }
            if (!is_array($fileList)) {
                continue;
            }

            foreach ($fileList as $file => $methodList) {
                if ('*' === $methodList || empty($methodList)) {
                    $this->configInfo[$permitKey]['files'][] = md5($directory . $file);
                    continue;
                }
                if (!is_array($methodList)) {
                    continue;
                }

                foreach ($methodList as $method) {
                    $this->configInfo[$permitKey]['functions'][] = md5($directory . $file . $method);
                }
            }
        }
    }

    /**
     * 解析整个配置文件
     * @param void
     * @return void
     */
    public function analysisConfig()
    {
        $this->mode = $this->configInfo['raw']['mode'];
        $allowlist  = $this->configInfo['raw']['allowlist'];
        $denylist   = $this->configInfo['raw']['denylist'];
        $this->subAnalysisPermitConfig('allowlist', $allowlist);
        $this->subAnalysisPermitConfig('denylist', $denylist);
    }

    /**
     * 获取文件的简称
     * @param $file
     * @return string
     */
    public function getAliasFile($file)
    {
        $rootDir = $this->bootInfo['directory']['root_dir'];
        $pieces  = explode($rootDir, $file);
        return isset($pieces[1]) ? '/' . $this->businessInfo['source']['project'] . '/' . $pieces[1] : '';
    }

    /**
     * 获取文件的目录名
     * @param $aliasFile
     * @return string
     */
    public function getDirectory($aliasFile)
    {
        $pieces = explode('/', $aliasFile);
        if (empty($pieces)) {
            return '';
        }
        unset($pieces[count($pieces) - 1]);
        return implode('/', $pieces) . '/';
    }

    /**
     * 获取文件名
     * @param $aliasFile
     * @return string
     * @desc 如xxx/xxx/test.php则只返回test.php
     */
    public function getFile($aliasFile)
    {
        $pieces = explode('/', $aliasFile);
        if (empty($pieces)) {
            return '';
        }
        return end($pieces);
    }

    /**
     * 获取项目名
     * @return string
     */
    public function getProjectName()
    {
        $rootDir = $this->bootInfo['directory']['root_dir'];
        $pieces  = explode('/', $rootDir);
        $index   = count($pieces) - 2;
        return isset($pieces[$index]) ? $pieces[$index] : '';
    }

    /**
     * 获取接口参数
     * @return string
     */
    public function getRequestParams()
    {
        $params   = [];
        $inputSrv = &load_class('Input', 'core');
        $get      = $inputSrv->get(null, true);
        $post     = $inputSrv->post(null, true);
        if (is_array($get)) {
            foreach ($get as $key => $value) {
                $params[$key] = $value;
            }
        }
        if (is_array($post)) {
            foreach ($post as $key => $value) {
                $params[$key] = $value;
            }
        }

        $input = file_get_contents("php://input");
        if (!empty($input)) {
            $input = @json_decode($input, true);
        }
        if (is_array($input)) {
            foreach ($input as $key => $value) {
                $params[$key] = $value;
            }
        }

        return !empty($params) ? json_encode($params) : '无';
    }

    /**
     * 求交集行
     * @param $lines
     * @param $startLine
     * @param $endLine
     * @return array
     */
    public function intersectLines($lines, $startLine, $endLine)
    {
        return array_intersect($lines, range($startLine, $endLine));
    }

    /**
     * 通用除法
     * @param $a
     * @param $b
     * @param int $percent 结果是百分比时percent=100, 否则percent=1
     * @param int $round
     * @return float
     */
    public function divide($a, $b, $percent = 100, $round = 2)
    {
        if (empty($b)) {
            $b = 0.0001;
        }
        return round(($a / $b) * $percent, $round);
    }

    /**
     * 提取文件的类名
     * @param $file
     * @return string
     */
    public function extractClass($file)
    {
        $pieces = explode('.', $file);
        return isset($pieces[0]) ? $pieces[0] : '';
    }

    /**
     * 分类xdebug返回的coverage信息
     * @param $coverage
     * @return array
     */
    public function classifyGeneratedCoverage($coverage)
    {
        $category = ['executed_lines' => [], 'unexecuted_lines' => [], 'dead_lines' => []];

        // 不需要多余排序, $coverage是key为行数的数组, 这里的line已经是有序的了
        foreach ($coverage as $line => $value) {

            if (-2 === $value) {
                $category['dead_lines'][] = $line;
            } elseif (-1 === $value) {
                $category['unexecuted_lines'][] = $line;
            } elseif (1 === $value) {
                $category['executed_lines'][] = $line;
            }
        }

        return $category;
    }

    /**
     * 统计项自增
     * @param $pathLevelKey
     * @param $numericName
     * @param $amount
     */
    public function incNumeric($pathLevelKey, $numericName, $amount)
    {
        $this->runInfo['numeric'][$pathLevelKey][$numericName] += $amount;
    }

    /**
     * 统计项赋值
     * @param $pathLevelKey
     * @param $numericName
     * @param $val
     */
    public function valNumeric($pathLevelKey, $numericName, $val)
    {
        $this->runInfo['numeric'][$pathLevelKey][$numericName] = $val;
    }

    /**
     * push某行到文件的无效行数组中
     * @param $directory
     * @param $file
     * @param $line
     */
    public function pushFileInvalidLines($directory, $file, $line)
    {
        $this->runInfo['path']['file'][$directory][$file]['rendering']['invalid_lines'][] = $line;
    }

    /**
     * 反射类的方法
     * @param $aliasFile string
     * @param $class string
     * @param $fileCoverage array
     * @return array
     * @desc 返回方法映射, 由于统计需要, 所以会包含部分的脏Key, 如果不需要, clearDirtyKey清洗即可
     */
    public function reflectMethodMap($aliasFile, $class, $fileCoverage)
    {
        $usedMethodMap = [];
        $methodMap     = [
            'public' => $usedMethodMap, // 脏key: 使用public关键字防止方法命名冲突
        ];
        try {

            $reflect = new ReflectionClass($class);
            $methods = $reflect->getMethods();
            foreach ($methods as $method) {

                // 创建方法反射对象
                $method = $method->getName();
                if (!$this->isRunAllowed('functions', md5($aliasFile . $method))) {
                    continue;
                }

                // 获取方法的行数信息
                $reflectMethod = new ReflectionMethod($class, $method);
                $startLine     = $reflectMethod->getStartLine();
                $endLine       = $reflectMethod->getEndLine();

                $functionExecutedLines = $this->intersectLines($fileCoverage['executed_lines'], $startLine, $endLine);
                $isMethodUsed          = !empty($functionExecutedLines);

                $map = [

                    // 反射阶段就能计算出来的数据
                    'reflecting' => [
                        'is_used'        => $isMethodUsed,
                        'start_line'     => $startLine,
                        'end_line'       => $endLine,
                        'line_num'       => ($endLine - $startLine) + 1, // 函数的总行数
                        'executed_lines' => $functionExecutedLines, // 方法内执行的代码行
                    ],

                    // 渲染源码阶段才能计算出来的数据
                    'rendering'  => [
                        'invalid_lines' => [], // 函数无效的行
                        'code_line_num' => 0, // 函数的有效行数
                        'coverage'      => 0, // 覆盖率(纯数值)
                        'coverage_text' => '0%', // 覆盖率(百分号后缀)
                    ],
                ];
                if ($isMethodUsed) {
                    $usedMethodMap[$method]       = $map;
                    $methodMap['public'][$method] = $map;
                }
                $methodMap[$method] = $map;
            }
        } catch (\ReflectionException $exception) {
            return $methodMap;
        }

        return $methodMap;
    }

    /**
     * 计算函数渲染阶段的相关数据
     * @param $directory
     * @param $file
     * @param $function
     * @param $functionCoverage
     * @return array
     */
    public function calcFunctionRenderingInfo($directory, $file, $function, $functionCoverage)
    {
        $reflecting       = $functionCoverage['reflecting'];
        $fileInvalidLines = $this->runInfo['path']['file'][$directory][$file]['rendering']['invalid_lines'];

        $functionStartLine    = $reflecting['start_line'];
        $functionEndLine      = $reflecting['end_line'];
        $functionInvalidLines = $this->intersectLines($fileInvalidLines, $functionStartLine, $functionEndLine);
        $functionCodeLineNum  = $reflecting['line_num'] - count($functionInvalidLines);

        $coverage     = $this->divide(count($reflecting['executed_lines']), $functionCodeLineNum);
        $coverageText = $coverage . '%';

        $this->runInfo['path']['function'][$directory][$file][$function]['rendering'] = [
            'invalid_lines' => $functionInvalidLines,
            'code_line_num' => $functionCodeLineNum,
            'coverage'      => $coverage,
            'coverage_text' => $coverageText,
        ];

        return [$coverage];
    }

    /**
     * 清洗脏key
     * @param $from string 脏key产生于哪个方法
     * @param $value array 需要被清洗的值
     * @return array
     */
    public function clearDirtyKey($from, $value)
    {
        switch ($from) {
            case 'reflectMethodMap':
                unset($value['public']);
                break;
            default:
                break;
        }
        return $value;
    }

    /**
     * 前置操作
     */
    public function preset()
    {
        if ($this->isTestingMode()) {
            $this->initResourceInfo();
            $this->initBusinessInfo();
        }
        // 后续处理
    }

    /**
     * 开始
     */
    public function start()
    {
        if (!$this->isSwitchOn()) {
            return;
        }
        $this->preset();
    }

    /**
     * 结束
     */
    public function end()
    {
        if (!$this->isSwitchOn()) {
            return;
        }
        $this->afterSet();
    }

    /**
     * 后置操作
     */
    public function afterSet()
    {
        if ($this->isTestingMode()) {
            $this->finishBusinessInfo();

            $this->recordRunInfo($this->finishGeneratedCoverage());

            $this->outputHTMLFile();
        }
        if ($this->isProductionMode()) {
            $this->outputMetric();
        }
        // 后续处理
    }

    /**
     * 初始化启动数据
     */
    public function initBootInfo()
    {
        $rootDir   = FCPATH;
        $appDir    = NEWAPP;
        $env       = ENVIRONMENT;
        $phplibDir = APPPATH;

        $pieces = explode('/', $rootDir);
        $count  = count($pieces);
        unset($pieces[$count - 1]);
        unset($pieces[$count - 2]);
        $edenDir = implode('/', $pieces) . '/';

        $this->bootInfo['env']                     = $env;
        $this->bootInfo['directory']['eden_dir']   = $edenDir;
        $this->bootInfo['directory']['root_dir']   = $rootDir;
        $this->bootInfo['directory']['app_dir']    = $appDir;
        $this->bootInfo['directory']['phplib_dir'] = $phplibDir;
        $this->bootInfo['directory']['output_dir'] = '/www/reports/';
    }

    /**
     * 初始化配置数据
     * @param $config
     */
    public function initConfigInfo($config)
    {
        $this->configInfo['raw'] = $config;
        $this->analysisConfig();
    }

    /**
     * 初始化资源数据
     */
    public function initResourceInfo()
    {
        $this->resourceInfo['css'] = '<link href="https://cdn.bootcdn.net/ajax/libs/highlight.js/10.1.2/styles/atelier-forest-dark.min.css" rel="stylesheet">';
        $this->resourceInfo['css'] .= '<style>*{margin:0;}body{margin-bottom:20px;}.pl-0{padding-left:0;}.mt-10{margin-top:10px;}.fs-13{font-size:13px;}.toolbox{position: fixed;bottom: 30px;right: 50px;}#container{width:1340px;margin:0 auto;}#header{background:#f5f5dc;padding: 15px 20px;margin-bottom: 20px;border-bottom:1px solid #ddd;}h4{margin:20px auto 10px auto;border-bottom: 1px solid #ddd;padding-bottom: 8px;margin-bottom: 0;}#table{border:1px solid #ddd;border-collapse:collapse;}td{font-size:12px;border:1px solid #ddd;text-align:center;padding:15px 10px;word-wrap:break-word;word-break:normal;}.field_name{background:#eee}.result_success{color:green}.result_error{color:red}.editor{width:1350px;margin:0 auto;background:#f5f2f2;font-size:14px;font-family:monospace;border:1px solid #ddd;padding:0 0 20px 0;display:none;}.highlight,.highlight_black{background:#c0d0ff;background:#020302;height:30px;line-height:30px;color:#555;margin:0;padding:0 10px}.highlight_black{color:#fff;background:#4daf74}.line_number{color:#1273e6;padding-right:10px}p{height:30px;line-height:30px;margin:0;padding:0 10px}.my_a{padding-right:5px}.file_category{width:1340px;text-align:left;margin:0 auto 0 auto;padding:7px 0;color:#777;padding-left:10px;border-bottom: 1px solid #ddd;}.file_toggle{width:1320px;text-align:left;margin:10px auto 0 auto;padding:7px 0;color:#666;padding-left:30px;}.file_toggle:hover,.file_category:hover{background:#eee;cursor:pointer;}.file_folder{display:none;}.menu_list{display:inline-block;width:300px;text-align:right;font-weight:lighter;float:right;font-size:13px;}.menu:hover,button:hover{cursor:pointer;}#Btn_next_file:hover,#Btn_pre_file:hover{opacity:1;}#Btn_next_file,#Btn_pre_file{border-radius:50px;width:50px;height:50px;border: 1px solid #ddd;margin-bottom:10px;font-size:17px;opacity:0.5;background:#ddd;}.editor_info_phase{background:#FFF;padding:3px 15px;height:auto}</style>';
        $this->resourceInfo['js']  = '<script src="https://cdn.bootcdn.net/ajax/libs/jquery/3.5.1/jquery.js"></script><script>$(".file_toggle").click(function(){$(this).next().slideToggle();let angleRight=$(this).find(".fa-angle-right");angleRight.attr("data-rotate", (0 === parseInt(angleRight.attr("data-rotate")))?90:0).css({"transform": "rotate("+ angleRight.attr("data-rotate") +"deg)"});});$(".file_category").click(function(){$(this).next(".file_folder").slideToggle();let folder_icon=$(this).find(".folder_icon");if(0===parseInt(folder_icon.attr("data-open"))){folder_icon.attr("data-open",1);folder_icon.removeClass("fa-folder").addClass("fa-folder-open")}else{folder_icon.attr("data-open",0);folder_icon.addClass("fa-folder").removeClass("fa-folder-open")}});$(".menu_collapse_all").click(function(){$(this).parent().parent().next(".file_explore").children(".file_folder").slideToggle();$(this).parent().parent().next(".file_explore").children(".editor").slideToggle();});</script>';
        $this->resourceInfo['js']  .= '<script>let currentFileId = 0;$("#Btn_next_file").click(function(){++currentFileId;window.location.href=(window.location.href.split("#")[0]+"#file_toggle_"+currentFileId);});$("#Btn_pre_file").click(function(){--currentFileId;currentFileId=(currentFileId<1)?1:currentFileId;window.location.href=(window.location.href.split("#")[0]+"#file_toggle_"+currentFileId);});</script>';
        $this->resourceInfo['js']  .= '<script src="https://cdn.bootcdn.net/ajax/libs/highlight.js/10.1.2/highlight.min.js"></script><script type="text/javascript">hljs.initHighlightingOnLoad();</script>';
    }

    /**
     * 初始化业务数据
     */
    public function initBusinessInfo()
    {
        $paramString = json_encode(json_decode($this->getRequestParams(), true), JSON_UNESCAPED_UNICODE);

        $this->businessInfo['api']['cost']['memory'][]      = round(memory_get_usage() / 1024 / 1024, 2);
        $this->businessInfo['api']['cost']['millisecond'][] = round(microtime(true), 2);
        $this->businessInfo['api']['name']                  = $_SERVER['PATH_INFO'];
        $this->businessInfo['api']['params']                = $paramString;
        $this->businessInfo['source']['project']            = $this->getProjectName();
        $this->businessInfo['source']['class']              = ACTION;
        xdebug_start_code_coverage(XDEBUG_CC_UNUSED | XDEBUG_CC_DEAD_CODE);
        ob_start();
    }

    /**
     * 结束业务数据
     */
    public function finishBusinessInfo()
    {
        $result = ob_get_contents();
        $result = json_decode($result, true);
        if (isset($result['result']) && is_array($result['result'])) {
            $result['result'] = array_slice($result['result'], 0, 1);
        }
        $resultString = json_encode($result, JSON_UNESCAPED_UNICODE);

        $this->businessInfo['api']['response']              = $resultString;
        $this->businessInfo['api']['cost']['memory'][]      = round(memory_get_usage() / 1024 / 1024, 2);
        $this->businessInfo['api']['cost']['millisecond'][] = round(microtime(true), 2);
    }

    /**
     * 结束覆盖数据
     * @return array
     */
    public function finishGeneratedCoverage()
    {
        $coverage = xdebug_get_code_coverage();
        xdebug_stop_code_coverage(true);
        return $coverage;
    }

    /**
     * 子记录运行数据:如xdebug返回的覆盖数据
     * @param $generatedCoverage
     */
    public function subRecordGeneratedRunInfo($generatedCoverage)
    {
        $this->runInfo['path']['generated'] = $generatedCoverage;
    }

    /**
     * 子记录运行数据:文件级别的代码覆盖数据
     * @param $directory
     * @param $file
     * @param $fileCoverage
     */
    public function subRecordFileRunInfo($directory, $file, $fileCoverage)
    {
        if (!isset($this->runInfo['path']['file'][$directory])) {

            $this->incNumeric('file', 'directory_num', 1);
            $this->runInfo['path']['file'][$directory] = [];
        }
        if (!isset($this->runInfo['path']['file'][$directory][$file])) {

            $this->incNumeric('file', 'file_num', 1);
            $this->runInfo['path']['file'][$directory][$file] = $fileCoverage;
        }
    }

    /**
     * 子记录运行数据:函数级别的代码覆盖数据
     * @param $directory
     * @param $file
     * @param $functionCoverage
     */
    public function subRecordFunctionRunInfo($directory, $file, $functionCoverage)
    {
        if (!isset($this->runInfo['path']['function'][$directory])) {

            $this->incNumeric('function', 'directory_num', 1);
            $this->runInfo['path']['function'][$directory] = [];
        }
        if (!isset($this->runInfo['path']['function'][$directory][$file])) {

            $this->incNumeric('function', 'file_num', 1);
            $this->incNumeric('function', 'function_num', count($functionCoverage));
            $this->runInfo['path']['function'][$directory][$file] = $functionCoverage;
        }
    }

    /**
     * 记录运行数据
     * @param $generatedCoverage
     */
    public function recordRunInfo($generatedCoverage = [])
    {
        $this->subRecordGeneratedRunInfo($generatedCoverage);
        foreach ($generatedCoverage as $absoluteFile => $coverage) {

            $aliasFile  = $this->getAliasFile($absoluteFile);
            $directory  = $this->getDirectory($aliasFile);
            $file       = $this->getFile($aliasFile);
            $classified = $this->classifyGeneratedCoverage($coverage);

            $fileCoverage = ['reflecting' => $classified, 'rendering' => ['invalid_lines' => []]];
            $this->subRecordFileRunInfo($directory, $file, $fileCoverage);

            if (!$this->isRunAllowed('directories', md5($directory))) {
                continue;
            }
            if (!$this->isRunAllowed('files', md5($aliasFile))) {
                continue;
            }

            $class     = $this->extractClass($file);
            $methodMap = $this->reflectMethodMap($aliasFile, $class, $classified);

            if (!empty($methodMap['public'])) {
                $functionCoverage = $methodMap['public'];
                $this->subRecordFunctionRunInfo($directory, $file, $functionCoverage);
            }
        }
    }

    /**
     * 生成DOM: 目录bar
     * @param $directory
     * @param $fileNum
     * @param $coverageText
     * @return string
     */
    public function createDirectoryBarDom($directory, $fileNum, $coverageText)
    {
        $dom = "<p class='file_category'><i class='folder_icon fa fa-folder' data-open='0' aria-hidden='true' style='font-size:17px;color:#000;'></i><span style='margin-left:5px;min-width:350px;display: inline-block;'>{$directory}</span><span style='margin-left:10px;font-size:13px;display: inline-block;min-width: 200px;text-align: right;float:right;'>{$fileNum}个文件/<span>覆盖率 {$coverageText}</span></span><span style='clear:both;'></span></p>";
        return $dom;
    }

    /**
     * 生成DOM: 文件bar
     * @param $file
     * @param $fileId
     * @param $coverageAvgText
     * @return string
     */
    public function createFileBarDom($file, $fileId, $coverageAvgText)
    {
        $dom = "<div class='file_toggle' id='file_toggle_{$fileId}'><i class='fa fa-file' aria-hidden='true' style='font-size: 13px;color:#999;'></i> {$file} <span style='font-size:13px;'><i class='fa fa-angle-right' data-rotate='0' aria-hidden='true'></i></span><span style='font-size:14px;margin-left:10px;float:right;'>平均覆盖率 {$coverageAvgText}</span></div>";
        return $dom;
    }

    /**
     * 生成DOM: 文件中的代码行
     * @param $directory
     * @param $file
     * @param $fileId
     * @param $fileExecutedLines
     * @return string
     */
    public function createFileCodeRowDom($directory, $file, $fileId, $fileExecutedLines)
    {
        $dom              = '';
        $nextExecutedLine = 0;
        $startLine        = $fileExecutedLines[0];
        $endLine          = end($fileExecutedLines);

        // 读取文件源码
        $i            = 0;
        $absoluteFile = realpath($this->bootInfo['directory']['eden_dir'] . $directory . $file);
        $fp           = fopen($absoluteFile, 'r');
        while (!feof($fp)) {

            ++$i;
            $isExecutedLine = in_array($i, $fileExecutedLines);

            // 使用feof判断, fgets再读取, 可以解决读不到最后一行换行符的问题
            $text = fgets($fp);

            // 无效符记录
            if (!$isExecutedLine) {
                foreach ($this->configInfo['raw']['valid_line_expr'] as $expr) {
                    if (preg_match_all($expr, $text)) {
                        $this->pushFileInvalidLines($directory, $file, $i);
                        break;
                    }
                }
            }

            // 是否展示完整的代码
            $isShowFullCode = $this->configInfo['raw']['testing']['is_show_full_code'];
            if (!$isShowFullCode) {
                if ($i < $startLine - 10) {
                    continue;
                }
                if ($i > $endLine + 10) {
                    break;
                }
                if (isset($fileExecutedLines[$nextExecutedLine]) && $i < $fileExecutedLines[$nextExecutedLine] - 20) {
                    continue;
                }
            }

            // 限制每行展示字数
            if (mb_strlen($text) >= 120) {
                $text = substr($text, 0, 120) . ' ...';
            }

            // 本行代码是执行行
            $highlightProperty = $codeLineProperty = '';
            if ($isExecutedLine) {
                ++$nextExecutedLine;
                $highlightProperty = "class='highlight'";
                $codeLineProperty  = "id='CodeLine-{$fileId}-{$i}'";
            }

            $text = str_replace(' ', '&nbsp;', htmlentities($text));
            $dom  .= "<p {$highlightProperty}><span class='line_number' {$codeLineProperty}>{$i}</span><span>" . $text . "</span></p>";
        }
        fclose($fp);

        $dom = '<pre><code class="php">' . $dom . '</code></pre>';

        return $dom;
    }

    /**
     * 生成DOM: 行锚点
     * @param $fileId
     * @param $fileExecutedLines
     * @return string
     */
    public function createLineAnchoredDom($fileId, $fileExecutedLines)
    {
        $dom = '';
        foreach ($fileExecutedLines as $line) {
            $dom .= ("<a class='my_a' href='#CodeLine-{$fileId}-" . $line . "'>{$line}</a>、");
        }
        return $dom;
    }

    /**
     * 生成DOM: 函数覆盖
     * @param $functionList
     * @return string
     */
    public function createFunctionCoverageDom($functionList)
    {
        $dom = '';
        foreach ($functionList as $function => $functionCoverage) {
            $dom .= "<span style='color:#6b7f88;'>{$function}[{$functionCoverage['rendering']['coverage_text']}] </span>、";
        }
        return $dom;
    }

    /**
     * 生成DOM: 文件资源管理器
     * @param $fileExploreName
     * @return string
     */
    public function createFileExplorerDom($fileExploreName = '代码覆盖')
    {
        $dom     = '';
        $fileId  = 0;
        $path    = $this->runInfo['path'];
        $numeric = $this->runInfo['numeric'];

        $dirsCoverage = 0;
        foreach ($path['function'] as $directory => $fileList) {

            $wrapperDom    = '';
            $filesCoverage = 0;
            foreach ($fileList as $file => $functionList) {

                ++$fileId;
                $fileExecutedLines = $path['file'][$directory][$file]['reflecting']['executed_lines'];

                // 生成DOM: 源码中的每一行代码(包含副作用:pushFileInvalidLines)
                $fileCodeRowDom = $this->createFileCodeRowDom($directory, $file, $fileId, $fileExecutedLines);

                $coverage = 0;
                foreach ($functionList as $function => $functionCoverage) {

                    $renderingInfo = $this->calcFunctionRenderingInfo($directory, $file, $function,
                        $functionCoverage);

                    // 计算此函数的覆盖率并累加
                    list($fnCoverage) = $renderingInfo;
                    $coverage += $fnCoverage;
                }

                // 计算此文件的覆盖率并累加
                $functionNum   = count($functionList);
                $coverage      = $this->divide($coverage, $functionNum, 1);
                $filesCoverage += $coverage;

                $fileBarDom      = $this->createFileBarDom($file, $fileId, $coverage . '%');
                $lineAnchoredDom = $this->createLineAnchoredDom($fileId, $fileExecutedLines);
                $fnCoverageDom   = $this->createFunctionCoverageDom($this->runInfo['path']['function'][$directory][$file]);
                $editorDom       = "<div class='editor'><p class='editor_info_phase'><span>函数覆盖：</span>{$fnCoverageDom}</p><p class='editor_info_phase'><span>行数定位：</span>{$lineAnchoredDom}</p>{$fileCodeRowDom}</div>";
                $wrapperDom      .= ($fileBarDom . $editorDom);
            }

            // 计算此目录的覆盖率并累加
            $fileNum      = count($fileList);
            $coverage     = $this->divide($filesCoverage, $fileNum, 1);
            $dirsCoverage += $coverage;

            $dirBarDom = $this->createDirectoryBarDom($directory, $fileNum, $coverage . '%');
            $dom       .= ($dirBarDom . "<div class='file_folder'>" . $wrapperDom . "</div>");
        }
        $dom = "<div class='file_explore'>{$dom}</div>";

        $coverage = $this->divide($dirsCoverage, $numeric['function']['directory_num'], 1);
        $this->incNumeric('function', 'coverage', $coverage);
        $this->valNumeric('function', 'coverage_text', $coverage . '%');

        $header = "<h4>{$fileExploreName}<span class='menu_list'><span class='menu menu_collapse_all'>收起/展开 <button><i class='fa fa-angle-down' aria-hidden='true'></i></button></span></span></h4>";
        $dom    = $header . $dom;
        return $dom;
    }

    /**
     * 生成DOM: body
     * @return string
     */
    public function createBodyDom()
    {
        // 由于部分数据在渲染阶段才能计算出来, 所以先渲染
        $fileExplorerDom = $this->createFileExplorerDom();

        $memory      = $this->businessInfo['api']['cost']['memory'][1] - $this->businessInfo['api']['cost']['memory'][0];
        $millisecond = $this->businessInfo['api']['cost']['millisecond'][1] - $this->businessInfo['api']['cost']['millisecond'][0];
        $memory      = round($memory, 2);
        $millisecond = round($millisecond * 1000, 2);

        $content = '<h2 id="header"><span>接口覆盖报告</span></h2>';
        $content .= '<div id="container">';
        $content .= '<h4>接口详情</h4>';
        $content .= '<p class="pl-0 mt-10 fs-13">报告生成时间 ' . date('Y-m-d H:i:s') . '</p>';
        $content .= '<table id="table"><tbody><tr><td rowspan="2" style="text-align:center;">基本信息</td><td class="field_name">接口名</td><td class="field_name">参数</td><td class="field_name">响应</td></tr>';
        $content .= '<tr style="background:#FFF;"><td style="max-width:300px;">' . $this->businessInfo['api']['name'] . '</td><td style="max-width:400px;">' . $this->businessInfo['api']['params'] . '</td><td style="max-width:500px;">' . $this->businessInfo['api']['response'] . '</td></tr>';
        $content .= '<tr><td rowspan="2" style="text-align: center;">代码运行</td><td class="field_name">代码覆盖率</td><td class="field_name">目录文件</td><td class="field_name">耗费</td></tr>';
        $content .= '<tr style="background:#FFF;"><td>' . $this->runInfo['numeric']['function']['coverage_text'] . '</td><td>' . $this->runInfo['numeric']['function']['directory_num'] . '个目录/' . $this->runInfo['numeric']['function']['file_num'] . '个文件/' . $this->runInfo['numeric']['function']['function_num'] . '个函数</td><td>内存占用' . $memory . 'MB / 时间占用 ' . $millisecond . '毫秒</td></tr>';

        $content .= '</tbody></table>';
        $content .= $fileExplorerDom;
        $content .= '</div>';
        $content .= '<div class="toolbox"><div><button id="Btn_pre_file"><i class="fa fa-angle-up" aria-hidden="true"></i></button></div><div><button id="Btn_next_file"><i class="fa fa-angle-down" aria-hidden="true"></i></button></div></div>';

        $dom = '<body>' . $content . '</body>';
        $dom .= $this->resourceInfo['js'];
        return $dom;
    }

    /**
     * 创建Document文档
     * @return string
     */
    public function makeDocument()
    {
        $html = '<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><title>' . $this->businessInfo['api']['name'] . '接口覆盖报告</title>' . $this->resourceInfo['css'] . '<link href="https://cdn.bootcdn.net/ajax/libs/font-awesome/5.13.1/css/all.min.css" rel="stylesheet"></head>';
        $html .= $this->createBodyDom();
        $html .= '</html>';
        return $html;
    }

    /**
     * 输出报告
     */
    public function outputHTMLFile()
    {
        $logid     = LIB_Log::genLogID();
        $html      = $this->makeDocument();
        $class     = $this->businessInfo['source']['class'];
        $outputDir = $this->bootInfo['directory']['output_dir'];
        $file      = sprintf('%s%s-%s-%s.html', $outputDir, $class, $logid, date("Hi"));
        file_put_contents($file, $html);
    }

    /**
     * 输出指标性数据
     */
    public function outputMetric()
    {
    }

    /**
     * 调试程序
     * @return array
     */
    public function debug()
    {
        return [
            'boot_info'        => $this->bootInfo,
            'business_info'    => $this->businessInfo,
            'run_info'         => $this->runInfo,
            'switch'           => $this->switch,
            'is_loaded_xdebug' => $this->isInstalledXdebug(),
        ];
    }
}
