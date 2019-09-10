<?php
// +----------------------------------------------------------------------
// | TopThink [ WE CAN DO IT JUST THINK IT ]
// +----------------------------------------------------------------------
// | Copyright (c) 2016 http://www.topthink.com All rights reserved.
// +----------------------------------------------------------------------
// | Author: zhangyajun <448901948@qq.com>
// +----------------------------------------------------------------------

namespace think\migration\command\migrate;

use Phinx\Util\Util;
use think\console\input\Argument as InputArgument;
use think\console\Input;
use think\console\Output;
use think\migration\command\Migrate;
use think\Db;
use think\Env;

class Create extends Migrate
{

    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this->setName('migrate:create')
             ->setDescription('Create a new migration')
             ->addArgument('name', InputArgument::REQUIRED, 'What is the name of the migration?')
             ->setHelp(sprintf('%sCreates a new database migration%s', PHP_EOL, PHP_EOL));
    }

    /**
     * Create the new migration.
     *
     * @param Input  $input
     * @param Output $output
     * @throws \RuntimeException
     * @throws \InvalidArgumentException
     * @return void
     */
    protected function execute(Input $input, Output $output)
    {
        $path = $this->getPath();

        if (!file_exists($path)) {
            if ($this->output->confirm($this->input, 'Create migrations directory? [y]/n')) {
                mkdir($path, 0755, true);
            }
        }

        $this->verifyMigrationDirectory($path);

        $path      = realpath($path);
        $className = $input->getArgument('name');

        if (!Util::isValidPhinxClassName($className)) {
            throw new \InvalidArgumentException(sprintf('The migration class name "%s" is invalid. Please use CamelCase format.', $className));
        }

        if (!Util::isUniqueMigrationClassName($className, $path)) {
            throw new \InvalidArgumentException(sprintf('The migration class name "%s" already exists', $className));
        }

        // Compute the file path
        $fileName = Util::mapClassNameToFileName($className);
        $filePath = $path . DIRECTORY_SEPARATOR . $fileName;

        if (is_file($filePath)) {
            throw new \InvalidArgumentException(sprintf('The file "%s" already exists', $filePath));
        }

        // Verify that the template creation class (or the aliased class) exists and that it implements the required interface.
        $aliasedClassName = null;

        // Load the alternative template if it is defined.
        $contents = file_get_contents($this->getTemplate());

        $this->getTableBuild();
        
        // inject the class names appropriate to this migration
        $contents = strtr($contents, [
            '$className' => $className,
        ]);

        if (false === file_put_contents($filePath, $contents)) {
            throw new \RuntimeException(sprintf('The file "%s" could not be written to', $path));
        }

        $output->writeln('<info>created</info> .' . str_replace(getcwd(), '', $filePath));
    }

    protected function getTemplate()
    {
        return __DIR__ . '/../stubs/migrate.stub';
    }
    
    protected function getTableBuild($tableName = 'users') {
        $phinxmigration = new \think\migration\Migrator(Util::getCurrentTimestamp());
        $phinxmigration->setAdapter($this->getAdapter());
        if($phinxmigration->hasTable($tableName)) {
            $config = $this->getDbConfig();
            $db = $config['name'];
            $tableInfos = $this->getTablesInfos($tableName,$db);
            $this->buildCreateTableSql($tableInfos);
        }else{
            return '';
        }
    }
    
    /**
            * 获取表属性
     * @param string $tableName
     * @param string $db
     */
    private function getTablesInfos($tableName,$db) {
        $tableInfos = array();
        $tableInfos['tableBaseInfos'] = $this->getTableBaseInfos($tableName,$db);
        $tableInfos['fieldInfos'] = $this->getFieldInfos($tableName,$db);
        $tableInfos['tableIndexs'] = $this->getFieldIndexInfos($tableName,$db);     //主要针对查询  //返回一个维数组
        
        return $tableInfos;
    }
    
    /**
             * 获取表基本属性
     * @param string $tableName
     * @param string $db
     */
    private function getTableBaseInfos($tableName,$db) {
        static $tablesBaseInfos;
        if(!isset($tablesBaseInfos)) {
            $sql = "Select table_name,TABLE_COMMENT,TABLE_COLLATION,ENGINE from INFORMATION_SCHEMA.TABLES Where table_schema = '$db'";
            $res = Db::query($sql);     //主要针对查询  //返回一个维数组
            
            $tablesBaseInfos = array();
            foreach($res as $val) {
                if(empty($val['TABLE_COMMENT'])) {
                    $val['TABLE_COMMENT'] = '请添加表说明';
                }
                $tablesBaseInfos[$val['table_name']] = $val;
            }
        }
        return $tablesBaseInfos[$tableName];
    }
    /**
            * 获取表字段属性
     * @param string $tableName
     * @param string $db
     */
    private function getFieldInfos($tableName,$db) {
        $sql = "SHOW FULL COLUMNS FROM `$db`.`$tableName`";
        $res = Db::query($sql);     //主要针对查询  //返回一个维数组
        foreach($res as &$val) {
            if(empty($val['Comment'])) {
                $val['Comment'] = '请添字段说明';
            }
        }
        
        return $res;
    }
    /**
             * 获取表索引属性
     * @param string $tableName
     * @param string $db
     */
    private function getFieldIndexInfos($tableName,$db) {
        $sql = "show index FROM `$db`.`$tableName`";
        $index = Db::query($sql);     //主要针对查询  //返回一个维数组
        
        return $index;
    }
    
    /**
     * $tableInfos['tableBaseInfos'] = $this->getTableBaseInfos($tableName,$db);
       $tableInfos['fieldInfos'] = $this->getFieldInfos($tableName,$db);
       $tableInfos['tableIndexs'] = $this->getFieldIndexInfos($tableName,$db);
     * @param array $tableInfos
     */
    private function buildCreateTableSql($tableInfos) {
        $sql = '';
        
        //表基本信息
        $tableOptions = [];
        $tableOptions['engine'] = $tableInfos['tableBaseInfos']['ENGINE'];
        $tableOptions['collation'] = $tableInfos['tableBaseInfos']['TABLE_COLLATION'];
        $tableOptions['comment'] = $tableInfos['tableBaseInfos']['TABLE_COMMENT'];
        $tableOptionsJson = json_encode($tableOptions);
        
        //表字段
        $sql .= <<<SQL
        \$table = \$this->table('{$tableInfos['tableBaseInfos']['table_name']}',json_decode('$tableOptionsJson',true));
SQL;
        
        var_dump($tableInfos['fieldInfos']);exit;
        $sql .= <<<SQL
            \$table->addColumn('username', 'string',array('limit' => 15,'default'=>'','comment'=>'用户名，登陆使用'))
            ->addColumn('password', 'string',array('limit' => 32,'default'=>md5('123456'),'comment'=>'用户密码'))
            ->addColumn('login_status', 'boolean',array('limit' => 1,'default'=>0,'comment'=>'登陆状态'))
            ->addColumn('login_code', 'string',array('limit' => 32,'default'=>0,'comment'=>'排他性登陆标识'))
            ->addColumn('last_login_ip', 'integer',array('limit' => 11,'default'=>0,'comment'=>'最后登录IP'))
            ->addColumn('is_delete', 'boolean',array('limit' => 1,'default'=>0,'comment'=>'删除状态，1已删除'))
            ->addIndex(array('username'), array('unique' => true))
            ->create();
SQL;
        echo $sql;exit;
        
        var_dump($tableInfos['tableBaseInfos']);exit;
    }

}
