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
use Phinx\Db\Adapter\MysqlAdapter;

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
             ->addArgument('table', InputArgument::OPTIONAL, '')
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

        $createTable = $this->getTableBuild($input->getArgument('table'));
        
        // inject the class names appropriate to this migration
        $contents = strtr($contents, [
            '$className' => $className,
            '$createTable' => $createTable,
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
            return $this->buildCreateTableSql($tableInfos);
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
                    $val['TABLE_COMMENT'] = '';
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
        $typeLimitMap = [];
//         $typeLimitMap['TINYBLOB'] = 'BLOB_TINY';
//         $typeLimitMap['BLOB'] = 'BLOB_REGULAR';
//         $typeLimitMap['MEDIUMBLOB'] = 'BLOB_MEDIUM';
//         $typeLimitMap['LONGBLOB'] = 'BLOB_LONG';

        $typeLimitMap['TINYTEXT'] = MysqlAdapter::TEXT_TINY;
        $typeLimitMap['TEXT'] = MysqlAdapter::TEXT_REGULAR;
        $typeLimitMap['MEDIUMTEXT'] = MysqlAdapter::TEXT_MEDIUM;
        $typeLimitMap['LONGTEXT'] = MysqlAdapter::TEXT_LONG;
        
        
        $typeLimitMap['TINYINT'] = MysqlAdapter::INT_TINY;
        $typeLimitMap['SMALLINT'] = MysqlAdapter::INT_SMALL;
        $typeLimitMap['MEDIUMINT'] = MysqlAdapter::INT_MEDIUM;
        $typeLimitMap['INT'] = MysqlAdapter::INT_REGULAR;
        $typeLimitMap['BIGINT'] = MysqlAdapter::INT_BIG;
        
        $typeMap = [];
        //         $typeMap['TINYBLOB'] = 'BLOB_TINY';
        //         $typeMap['BLOB'] = 'BLOB_REGULAR';
        //         $typeMap['MEDIUMBLOB'] = 'BLOB_MEDIUM';
        //         $typeMap['LONGBLOB'] = 'BLOB_LONG';

        $typeMap['TINYTEXT'] = 'text';
        $typeMap['TEXT'] = 'text';
        $typeMap['MEDIUMTEXT'] = 'text';
        $typeMap['LONGTEXT'] = 'text';
        
        $typeMap['TINYINT'] = 'integer';
        $typeMap['SMALLINT'] = 'integer';
        $typeMap['MEDIUMINT'] = 'integer';
        $typeMap['INT'] = 'integer';
        $typeMap['BIGINT'] = 'integer';
        
        $typeMap['VARCHAR'] = 'string';
        
        $typeMap['TIMESTAMP'] = 'timestamp';
        
        MysqlAdapter::INT_SMALL;
        
        $sql = '';
        
        
        //表字段
        foreach($tableInfos['fieldInfos'] as $fieldInfo) {
            $TypeArr = explode('(', $fieldInfo['Type'],2);
            $TypeArr = array_map(function($v) {
                return trim($v,'()');
            }, $TypeArr);
            
            
            $type = strtoupper($TypeArr[0]);
            
            $options = [];
            if(!empty($TypeArr[1])) {
                $options['limit'] = $typeLimitMap[$type]??$TypeArr[1];
            }
            $options['default'] = $fieldInfo['Default'];
            $options['comment'] = $fieldInfo['Comment'];
            if($fieldInfo['Null'] == 'YES') {
                $options['null'] = true;
            }
            
            $optionsStr = var_export($options,true);
            $type = $typeMap[$type]??$type;
            
            
            $sql .= <<<SQL
\$table->addColumn('{$fieldInfo['Field']}', '{$type}',{$optionsStr});
SQL;
            $sql .= PHP_EOL;
        }
        
        
        //索引
        $tableIndexArr = [];
        foreach($tableInfos['tableIndexs'] as $tableIndex) {
            if(!isset($tableIndexArr[$tableIndex['Key_name']])) {
                $tableIndexArr[$tableIndex['Key_name']] = [$tableIndex];
            }else{
                $tableIndexArr[$tableIndex['Key_name']][] = $tableIndex;
            }
        }
        
        $primary_key = [];
        foreach($tableIndexArr as $tableIndexs) {
            $fieldsArr = [];
            $options = ['limit'=>[]];
            foreach($tableIndexs as $tableIndex) {
                $fieldsArr[] = $tableIndex['Column_name'];
                $options['name'] = $tableIndex['Key_name'];
                if($tableIndex['Non_unique'] === 0 && $tableIndex['Index_type'] == 'BTREE'){
                    $primary_key[] = $tableIndex['Column_name'];
                    continue 2;
                }elseif($tableIndex['Index_type'] == 'BTREE'){
                    $options['type'] = 'INDEX';
                }elseif($tableIndex['Index_type'] == 'FULLTEXT') {
                    $options['type'] = 'FULLTEXT';
                }
                
                
                if(!empty($tableIndex['Sub_part'])) {
                    $options['limit'][$tableIndex['Column_name']] = $tableIndex['Sub_part'];
                }
            }
            
            $fieldsArrStr = var_export($fieldsArr,true);
            $optionsStr = var_export($options,true);
            
            //索引
            $sql .= <<<SQL
\$table->addIndex({$fieldsArrStr}, {$optionsStr});
SQL;
            $sql .= PHP_EOL;
        }
        
        //表基本信息
        $tableOptions = [];
        $tableOptions['engine'] = $tableInfos['tableBaseInfos']['ENGINE'];
        $tableOptions['collation'] = $tableInfos['tableBaseInfos']['TABLE_COLLATION'];
        $tableOptions['comment'] = $tableInfos['tableBaseInfos']['TABLE_COMMENT'];
        if(!empty($primary_key)) {
            $tableOptions['id'] = false;
            $tableOptions['primary_key'] = $primary_key;
        }
        $tableOptionsStr = var_export($tableOptions,true);
        
        
        $tabelBegain = <<<SQL
\$table = \$this->table('{$tableInfos['tableBaseInfos']['table_name']}',{$tableOptionsStr});
SQL;
        $tabelEnd = <<<SQL
\$table->create();
SQL;
        $sql = $tabelBegain.PHP_EOL.$sql.$tabelEnd;
        
        //格式化
        $tab = '        ';
        $sql = $tab.implode(PHP_EOL.$tab, preg_split("/\r\n|\n|\r/", $sql));
        return $sql;
    }
}
