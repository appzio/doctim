<?php

namespace Doctim\Parsers;


use function str_replace;
use function stristr;

class Parser {

    use DocumentationParser;

    public $source_dir;
    public $tokens;
    public $linefeed;

    /**
     * Main parses function for individual PHP file
     * @param $file
     * This should be a full path
     * @return array | bool
     * Returns the file's structure in array or false if file is not found
     */

    public function parse($file){
        $original = $file;
        $file = $this->source_dir.$file;

        if(is_dir($file) OR !file_exists($file)){
            return false;
        }

        $res = token_get_all(file_get_contents($file));
        $tokens = array();

        foreach($res as $t){
            if(!empty($t[2])){
                $name = token_name($t[0]);
                $arr = array('type' => $name,'data'=>$t[1]);
                $tokens[$t[2]][] = $arr;
                $tokens_by_type[$name][] = $arr;
            }
        }

        $this->tokens = $tokens;

        $output['file_name'] = $original;
        $output['doc_comment'] = $this->docGetFileComment();
        $output['doc_namespace'] = $this->extractDocNamespace();

        $class = $this->extractClassInfo();
        if($class){
            $output['class'] = $class;
        }

        $trait = $this->extractTraitInfo();

        if($trait){
            $output['trait'] = $trait;
        }

        $output['methods'] = $this->extract('T_FUNCTION');
        $output['uses'] = $this->extract('T_USE');
        $output['public_properties'] = $this->extract('T_PUBLIC');
        $output['private_properties'] = $this->extract('T_PRIVATE');

        return $output;
    }

    public function parseMdfile($file){
        $original = $file;
        $file = $this->source_dir.$file;

        if(is_dir($file) OR !file_exists($file)){
            return false;
        }

        $output['file_name'] = $original;
        $output['markup'] = file_get_contents($file);
        return $output;
    }


    /**
     * This will extract the desired parts along with their comments, based on the token_name types
     * @param string $extract_type
     * @return array
     */
    private function extract($extract_type='T_FUNCTION'){

        $counter = 0;
        $comment_line = 0;
        $comment = '';
        $output = array();

        foreach ($this->tokens as $token) {
            if($token[0]['type'] == 'T_DOC_COMMENT'){
                $comment = $this->docGetComment($token[0]['data']);
                $comment_line = $counter;
            }
            
            if($this->findType($extract_type,$token)){

                foreach($token as $searchitem){
                    if(isset($searchitem['type']) AND $searchitem['type'] == $extract_type){
                        switch($extract_type){
                            case 'T_FUNCTION':
                                $info = $this->docGetMethod($token);
                                break;

                            case 'T_USE':
                                $info = $this->docGetUse($token);
                                break;

                            case 'T_PRIVATE':
                                $info = $this->docGetProperty($token);
                                break;

                            case 'T_PUBLIC':
                                $info = $this->docGetProperty($token);
                                break;

                        }

                        if(isset($info['name'])){
                            $name = $info['name'];
                            $output[$name] = $info;

                            if($counter-$comment_line < 3){
                                $output[$name]['comment'] = $comment;
                            }
                        }

                        $comment = false;
                        $comment_line = false;
                    }
                }
            }

            $counter++;

        }

        return $output;


    }

    /**
     * Returns the main comment block of the class
     * @return bool|mixed
     */
    private function extractDocComment()
    {

        foreach ($this->tokens as $token){
            if($token[0]['type'] == 'T_DOC_COMMENT'){
                $data = $token[0]['data'];
                return $this->docGetComment($data);
            }
        }

        return false;
    }


    /**
     * Returns the classes namespace
     * @return bool|string
     */
    private function extractDocNamespace()
    {

        foreach ($this->tokens as $token){
            if($token[0]['type'] == 'T_NAMESPACE'){
                $out = $this->glue($token);
                $out = str_replace('namespace', '', $out);
                $out = $this->cleanup($out,false);
                return $out;
            }
        }

        return false;
    }

    /**
     * Will simply glue the data array into a string
     * @param $data
     * @return string
     */
    private function glue($data){
        $string = '';
        foreach($data as $item){
            $string .= $item['data'];
        }

        return $string;
    }


    /**
     * Get the class name
     * @return bool|string
     */
    private function extractClassInfo()
    {
        foreach ($this->tokens as $token){
            if($token[0]['type'] == 'T_CLASS'){
                $string = '';
                foreach($token as $part){
                    $string .= $part['data'];
                }
            }
        }

        if(isset($string)){
            $string = str_replace('class', '', $string);
            return trim($string);
        }

        return false;
    }


    /**
     * Get the trait name
     * @return bool|string
     */
    private function extractTraitInfo()
    {
        foreach ($this->tokens as $token){
            if($token[0]['type'] == 'T_TRAIT'){
                $string = '';
                foreach($token as $part){
                    $string .= $part['data'];
                }
            }
        }

        if(isset($string)){
            $string = str_replace('trait ', '', $string);
            return trim($string);
        }

        return false;
    }


    /**
     * @param $extract_type
     * T_CLASS | T_FUNCTION for example
     * @param $token
     * data array to search
     * @return bool
     */
    private function findType($extract_type, $token)
    {

        if($extract_type == 'T_PUBLIC' OR $extract_type == 'T_PRIVATE') {
            $proof = 0;

            foreach ($token as $item) {
                if($item['type'] == $extract_type){
                    $proof++;
                }

                if($item['type'] == 'T_VARIABLE'){
                    $proof++;
                }

                if($item['type'] == 'T_FUNCTION'){
                    return false;
                }
            }

            if($proof == 2){
                return true;
            }

            return false;
        }


        foreach($token as $item){
            if($item['type'] == $extract_type){
                return true;
            }
        }
        return false;
    }

}