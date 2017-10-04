<?php
/**
 * Simple PHPDOC document parser to create a json output. It is created very much
 * for our own purposes at Appzio, so it might not do every trick in the book,
 * but extending is very easy.
 *
 * Takes input of a directory and produces one json file with the hierarchy & code
 * structure along with comments in a flat array.
 *
 * Supported items:
 * - class name
 * - trait name
 * - class namespace
 * - class methods
 * - class properties
 * - class uses
 * - documentation blocks, with the following specials:
 *   - @var -- if class property contains an object, this points to the object
 *   - @param $varname -- function variable
 *   - @example https://github.com/appzio/phplib2-examples/Components/Elements/getImage/ -- links to repository with example code
 *
 * calling from command line (you can define target as a second parameter, but its not required)
 * php Doctim.php /Users/MyUser/documents/source/
 *
 * calling from browser
 * Doctim.php?source=/Users/MyUser/documents/source/
 *
 * @author Timo Railo (timo@appzio.com)
 * @license MIT LICENSE
 *
 *
 */

namespace Doctim;
error_reporting(E_ALL);
require_once ('Autoloader.php');

use Doctim\Parsers\Parser;

$doc = new Doctim();
$doc->run();

class Doctim {

    public $source_dir;
    public $target_dir;
    public $files;
    public $parser;
    private $output;
    public $linefeed = "<\br>";
    public $parse_md = true;

    /**
     * Will accept input either from command line or from browser, sets the directories. Make sure to use full paths.
     */
    public function __construct()
    {

        if(!is_dir($GLOBALS['argv'][1]) AND !isset($_REQUEST['source'])){
            $this->errorOutput('Source directory does not exist','crash');
        }

        $mylocation = pathinfo(__FILE__)['dirname'];

        $this->source_dir = $GLOBALS['argv'][1] ? $GLOBALS['argv'][1] : $_REQUEST['source'];
        $this->target_dir = $GLOBALS['argv'][2] ? $GLOBALS['argv'][2] : isset($_REQUEST['target']) ? $_REQUEST['target'] : $mylocation.'/Output/';

        if(!is_dir($this->target_dir)){
            mkdir($this->target_dir,0777,true);
        }

        $this->parser = new Parser();
        $this->parser->source_dir = $this->source_dir;
        $this->parser->linefeed = $this->linefeed;
    }

    /**
     * Gets all the files in the directory and parses through them
     */
    public function run(){
        $this->errorOutput('Starting...');

        // determine directory structure and create mapping array
        $filelist = $this->getFiles($this->source_dir);

        $output['hierarchy'] = $filelist;
        $this->parseFileList($filelist);
        $output['docs'] = $this->output;

        if(substr($this->target_dir,-5,5) == '.json'){
            file_put_contents($this->target_dir,json_encode($output,JSON_UNESCAPED_UNICODE));
        } else {
            $filename = basename($this->source_dir) .'-'.date('d-m-Y-m-h') .'.json';
            file_put_contents($this->target_dir.$filename,json_encode($output,JSON_UNESCAPED_UNICODE));
        }

        $this->errorOutput('Done.');
        die();
    }


    /**
     * recursive function which goes through the entire file list producing a flat result
     * @param array $filelist
     * @param bool $dir
     */
    private function parseFileList($filelist=array(), $dir=false){

        foreach ($filelist as $key=>$file){
            if(!is_string($key)){
                if(substr($file, -3, 3) == '.MD'){
                    $parse_result = $this->parser->parseMdFile($dir.$file);
                } else {
                    $parse_result = $this->parser->parse($dir.$file);
                }

                $this->output[] = $parse_result;

            } else {
                $this->parseFileList($file,$dir.$key.'/');
            }
        }
    }
    

    /* gets an array of all php files */
    public function getFiles($dir)
    {
        $cdir = scandir($dir);
        $result = array();

        foreach ($cdir as $key => $value) {
            if (substr($value, 0, 1) == '.') {
                continue;
            }

            if (is_dir($dir . DIRECTORY_SEPARATOR . $value)) {
                $result[$value] = $this->getFiles($dir . DIRECTORY_SEPARATOR . $value);
            } else {
                if($this->parse_md){
                    if (substr($value, -4, 4) == '.php' OR substr($value, -3, 3) == '.MD') {
                        $result[] = $value;
                    }
                } else {
                    if (substr($value, -4, 4) == '.php') {
                        $result[] = $value;
                    }
                }
            }
        }

        return $result;
    }

    /**
     * Just a simply output wrapper
     * @param $error
     * @param string $level
     */
    public function errorOutput($error, $level='notice'){
        if($level == 'crash'){
            echo($error.chr(10));
            die();
        }
        if($level == 'notice'){
            echo($error.chr(10));
        }
    }

}
