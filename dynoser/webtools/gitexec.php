<?php
namespace dynoser\webtools;

use CzProject\GitPhp\Git;
use CzProject\GitPhp\GitRepository;
use CzProject\GitPhp\GitException;
use dynoser\autoload\AutoLoadSetup;
use dynoser\autoload\AutoLoader;

class GitExec {
    public static $passArr = [
        'test' => 'da8be698d805f74da997ac7ad381b5aaa76384c9e27f78ae5d5688be95e39d92',  //Nhkb
    ];
}

class MiniShell {
    public function __construct($lastDir = '') {
        // search autoloader and run or die
        foreach([
            'vendor/autoload.php',
            __DIR__ . '/vendor/autoload.php',
            __DIR__ . '/vendor/dynoser/autoload/autoload.php',
            $lastDir,
        ''] as $chkFile) {
            $chkFile || die("Not found autoloader");
            if (\is_file($chkFile)) break;
        }
        require_once $chkFile;
    }

    public static function getWhereArr($progName) {
        $whereCmd = ('\\' === \DIRECTORY_SEPARATOR) ? 'where ' : 'which ';
        return self::getShellNonEmptyRows($whereCmd . $progName);
    }

    public static function getShellNonEmptyRows($cmd) {
        $shellStrs = \shell_exec($cmd);
        if (!$shellStrs) {
            return [];
        }
        $shellRows = \preg_split('/\r\n|\r|\n/', $shellStrs);

        $rowsArr = [];

        foreach ($shellRows as $row) {
            $row = \trim($row);
            if (!$row) continue;
            $rowsArr[] = $row;
        }

        return $rowsArr;
    }

    public static string $phpBin = '';
    public static string $phpIni = '';
  
    public static function getPHP($addPhpIni = true) {
        if (!self::$phpBin) {
            $whereFiles = self::getWhereArr('php');
            self::$phpBin = $whereFiles ? 'php' : PHP_BINARY;
        }
        $phpRun = self::$phpBin;
        if ($addPhpIni) {
            $phpIni = self::$phpIni ? self::$phpIni : \php_ini_loaded_file();
            if (!$phpIni) {
                $phpIni = self::getPHPini();
            }
            self::$phpIni = $phpIni ? $phpIni : '';
            if (self::$phpIni) {
                $phpRun .= ' -c "' . self::$phpIni . '"';
            }
        }
        return $phpRun;
    }

    public static function getPHPini() {
        if (!self::$phpIni) {
            $phpRun = self::getPHP(false);
            $rows = self::getShellNonEmptyRows($phpRun . ' --ini');
            if ($rows) {
                foreach($rows as $row) {
                    $i = strpos($row, 'ile:');
                    if ($i) {
                        self::$phpIni = \trim(\substr($row, $i + 5));
                        break;
                    }
                }
            }
        }
        return self::$phpIni;
    }
}

$instClass = $_REQUEST['instClass'] ?? '';
$nsMapURL = $_REQUEST['nsMapURL'] ?? '';

$gitBinary = $_REQUEST['gitBinary'] ?? '';
$gitArguments = $_REQUEST['gitArguments'] ?? '';
$username = $_REQUEST['username'] ?? '';
$password = $_REQUEST['password'] ?? '';

$passExpected = GitExec::$passArr[$username] ?? '';
$passIsOk = $passExpected && (\hash('sha256', $password) === GitExec::$passArr[$username]);

if ($passIsOk) {
    if (!$nsMapURL) {
        $siteId = $_SERVER['HTTP_HOST'] ?? 'all';
        $siteId = 'all'; // temporary always "all"
        if (substr($siteId, 0, 4) === 'www.') $siteId = substr($siteId, 4);
        $siteId = \substr($siteId, 0, \strcspn($siteId, '.'));
        $nsMapURL = '/storage/modules/get.php?path=nsmap/'. $siteId . '/' . $siteId . '.hashsig.zip';
    }
    if (\substr($nsMapURL, 0, 4) === 'http') {
        if (!\strpos($nsMapURL, '|')) {
            $nsMapURL .= '|EkDohf20jN/9kXW/WL3ZXo245ggek9TiTWzzmBriMTU=';
        }
        define('DYNO_NSMAP_URL', $nsMapURL);
    }

    $ms = new MiniShell();

    if (!$gitBinary) {
        $gitWhereArr = $ms->getWhereArr("git");
        if ($gitWhereArr && \is_array($gitWhereArr)) {
            $gitBinary = \reset($gitWhereArr);
        } else {
            echo "Git path was not auto-detected. Enter manually or will use simple 'git'\n";
        }
    }
    if ($gitBinary) {
        define('GIT_BINARY', $gitBinary); //"C:/Program Files/Git/cmd/git.exe"
    }

    $repoPath = AutoLoadSetup::$rootDir;

    $git = new Git();

    if ($gitArguments) {
        $gitArgumentsParts = \explode(' ', $gitArguments);
        try {
            $repository = $git->open($repoPath);
            $output = $repository->execute(...$gitArgumentsParts);
        } catch (\Throwable $e) {
            $output = [$e->getMessage()];
            if (\property_exists($e, 'runnerResult')) {
                if ($e = $e->getRunnerResult()) {
                    $errorOutput = $e->getErrorOutput();
                    if (is_array($errorOutput)) {
                        $output = \array_merge($output, $errorOutput);
                    }
                }
            }
        }
    } else {
        $output = [];
    }
} else {
    $output = ['Bad password'];
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Git Command Executor</title>
</head>
<body>
    <h1>Git Command Executor</h1>
    <form method="post">
<?php
if ($passIsOk) {
    echo '<label for="gitArguments">Enter Git arguments:</label>';
    echo '<input type="text" name="gitArguments" id="gitArguments" size="80" value="' . $gitArguments . '">';
}
if ($passIsOk && $gitBinary && \is_file($gitBinary)) {
    echo '<input type="hidden" name="gitBinary" value="' . $gitBinary . '">';
} else {
    echo '<br/><label for="gitBinary">Git Binary Path:</label>';
    echo '<input type="text" name="gitBinary" size="50" value="' . $gitBinary . '">';
}

if ($passIsOk) {
    echo '<input type="hidden" name="username" id="username" value="' . $username . '">';
    echo '<input type="hidden" name="password" id="password" value="'. $password .'">';
    echo '<input type="hidden" name="nsMapURL" id="nsMapURL" value="' . $nsMapURL . '"><br/>';
} else {
    echo '<br/><label for="username">username and password:</label>';
    echo '<input type="username" name="username" id="username" value="' . $username . '">';
    echo '<input type="password" name="password" id="password" value="'. $password .'">';
}
?>
    <input type="submit" value="Execute">
    </form>
    <h2>Git output:</h2>
    <pre><?php
        echo print_r($output);
    ?></pre>
<?php
    if ($passIsOk) {
        echo '<hr/>';
        echo '<form method="post">';
        echo '<label for="nsMapURL">nsmap URL:</label>';
        echo '<input type="text" name="nsMapURL" id="nsMapURL" size="50" value="' . $nsMapURL . '"><br/>';
        echo '<label for="instClass">Install class:</label>';
        echo '<input type="text" name="instClass" id="instClass" size="50" value="' . $instClass . '">';
        echo '<input type="hidden" name="username" id="username" value="' . $username . '">';
        echo '<input type="hidden" name="password" id="password" value="'. $password .'">';
        echo '<input type="hidden" name="gitBinary" value="' . $gitBinary . '">';
        echo '<input type="submit" value="Run">';
        echo '</form>';

        if ($instClass) {
            $classFullName = \trim(\strtr($instClass, '/', '\\'), '\\ ');

            echo "<pre>Try install class: '$classFullName' ... ";
            
            try {
                $res = AutoLoader::autoLoad($classFullName, false);
                if ($res) {
                    echo "OK\n";
                } else {
                    echo "Not found\n";
                }
    
            } catch (\Throwable $e) {
                $error = $e->getMessage();
                echo "\\Exception: $error \n";
            } finally {
                echo "</pre>";
            }
        }
    }
?>
</body>
</html>
