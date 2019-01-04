# borisimport

### 使用方法

1. 打开 import.php 文件，在上半部分配置 MySQL 数据库信息

    public $mysqlHost = 'localhost';
    public $mysqlUsername = 'root-username';
    public $mysqlPassword = 'root-password';
    public $mysqlDB = 'boris';
    public $mysqlTable = 'mosmix';

2. 在命令行终端下，运行以下命令进行导入 kml 文件

    php import.php 文件1 [文件2 文件3 ...]

例如

    php import MOSMIX_L_2018122003_01008.kml MOSMIX_L_2018122003_06612.kml
