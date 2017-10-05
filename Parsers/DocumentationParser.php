<?php

namespace Doctim\Parsers;


use function exp;
use function explode;
use function implode;
use function preg_match;
use function preg_replace;
use function str_replace;
use function stristr;
use function substr;

trait DocumentationParser
{

    /**
     * Returns the uses for the class
     * @param $data
     * @return array
     */
    private function docGetUse($data){
        $string = '';
        $output = array();

        $call = $this->glue($data);
        $call = $this->cleanup($call,false);

        foreach($data as $item){
            $string .= $item['data'];
            if($item['type'] == 'T_STRING'){
                $string = str_replace('use', '', $item['data']);
                $output['name'] = $this->cleanup($string,false);
            }
        }

        $output['call'] = $call;
        return $output;
    }

    /**
     * Returns class property
     * @param $data
     * @return array
     */
    private function docGetProperty($data){
        $output = array();

        foreach($data as $item){
            if($item['type'] == 'T_VARIABLE'){
                $output['name'] = $this->cleanup($item['data'],false);
            }
        }

        if(isset($data[5])){
            $output['type'] = $data[5]['data'];
        }

        return $output;
    }


    /**
     * Extracts individual method's information
     * @param $data
     * @return array
     */
    private function docGetMethod($data){
        $string = '';
        $lastpart = false;
        $output = array();
        $output['variables'] = array();

        foreach($data as $item){
            if(!$lastpart){
                $string .= $item['data'];
            }

            if($item['type'] == 'T_FUNCTION'){
                $lastpart = true;
            }

            if($lastpart){
                if($item['type'] == 'T_STRING' AND !isset($output['name'])){
                    $output['name'] = $item['data'];
                }
            }

            if($item['type'] == 'T_VARIABLE'){
                $output['variables'][] = $item['data'];
            }
        }

        $output['call'] = $string.' '.$output['name'].'('.implode(',', $output['variables']).')';
        return $output;
    }


    /**
     * Get the top comment part of the phpdoc comment section
     * @param $data
     * @return mixed|string
     */

    private function docGetFileComment(){

       foreach ($this->tokens as $token){
            if($token[0]['type'] == 'T_NAMESPACE'){
                $output['summary'] = false;
                return $output;
            }

            if($token[0]['type'] == 'T_DOC_COMMENT'){

                $out = $this->glue($token);
                $out = str_replace('/*', '', $out);
                $out = str_replace('*/', '', $out);
                $out = str_replace('*', '', $out);

                $return = $this->extractLinkAndComment($out);
                return $return;

            }
        }

        $output['summary'] = false;
        return $output;
    }

    private function extractLinkAndComment($data){

        if(stristr($data, '@')){
            $parts = explode('@', $data);
            $output = array();

            foreach($parts as $part){
                $part = trim($part);
                if(substr($part, 0,4) == 'link'){
                    $link = str_replace('link', '', $part);
                    $link = str_replace('--', '', $link);
                    $link = trim($link);
                    $output['link'] = $link;
                }
            }

            $output['summary'] = $this->cleanup($parts[0],true);
            return $output;
        } else {
            $output['summary'] = $this->cleanup($data,true);
            return $output;

        }

    }


    /**
     * Gets the entire comment without @ marked lines
     * @param $data
     * @return mixed|string
     */
    private function docGetDescription($data){

        $output = preg_replace('/@.*/', '',$data);
        $output = $this->cleanup($output,true);
        return $output;
    }

    private function cleanup($string,$preserve_chapter_breaks=true){
        $string = preg_replace('/\h+/', ' ', $string);
        $string = preg_replace('/\v\h+/', chr(10), $string);

        if($preserve_chapter_breaks){
            $string = preg_replace('/\v\v/', '<linebreak>', $string);
        }

        $string = preg_replace('/\v/', ' ', $string);
        $string = preg_replace('/\n/', ' ', $string);

        if($preserve_chapter_breaks) {
            $string = str_replace('<linebreak>', $this->linefeed.$this->linefeed, $string);
        }

        $string = str_replace($this->linefeed.$this->linefeed.$this->linefeed, $this->linefeed.$this->linefeed, $string);
        $string = str_replace($this->linefeed.$this->linefeed.$this->linefeed, $this->linefeed.$this->linefeed, $string);
        $string = str_replace($this->linefeed.$this->linefeed.$this->linefeed, $this->linefeed.$this->linefeed, $string);

        return trim($string);
    }

    /**
     * Parses through the phpdoc comment section and returns defined parts
     * @param $data
     * @return mixed
     */

    private function docGetComment($data){

        if(stristr($data, '/*')){
            $comment = true;
        }
        
        $data = str_replace('/*', '', $data);
        $data = str_replace('*/', '', $data);
        $data = str_replace('*', '', $data);

        if(isset($comment)){
            $output['summary'] = $this->docGetDescription($data);
        }

        $output['return'] = $this->docGetReturn($data);
        $output['parameters'] = $this->docGetParameters($data);
        $output['links'] = $this->docGetLinks($data);
        $output['object'] = $this->docGetObject($data);
        $output['example'] = $this->docGetExamples($data);

        return $output;
    }

    /**
     * Returns function parameters as an array
     * @param $data
     * @return array
     */
    private function docGetParameters($data){
        $parts = explode('@', $data);
        $output = array();

        foreach($parts as $part){
            /* parse links to their own array */
            if(stristr($part, '[link]')){
                $link_part = explode('[link]', $part);
                if(isset($link_part[1])){
                    $link = trim($link_part[1]);
                }

                $part = $link_part[0];
            }

            $part = trim($part);

            if(substr($part, 0,5) == 'param'){

                preg_match('/\$.*[[:space:]]/U', $part,$parameters);
                if(isset($parameters[0])){
                    $varname = trim($parameters[0]);
                    $docpart = preg_replace('/.*\v/U', '', $part,1);
                    $docpart = str_replace('param '.$varname, '', $docpart);
                    $docpart = str_replace('--', '', $docpart);

                    $output[$varname]['summary'] = trim($docpart);
                    if(isset($link)){
                        $output[$varname]['link'] = $link;
                    }
                }
            }
        }

        return $output;
    }

    /**
     * Returns function links as an array
     * @param $data
     * @return array
     */
    private function docGetLinks($data){
        $parts = explode('@', $data);
        $output = array();


        foreach($parts as $part){
            $part = trim($part);
            if(substr($part, 0,4) == 'link'){
                $only_link = explode($part, '\n');
                 preg_match('/link[[:blank:]].*/', $part,$only_link);
                 $only_link = str_replace('link', '', $only_link[0]);
                 $only_link = trim($only_link);
                 $output[] = $only_link;
            }
        }

        return $output;
    }

    /**
     * Returns a custom delimeter @var which refers to an object that the variable holds
     * @param $data
     * @return array|mixed|string
     */
    private function docGetObject($data){
        $parts = explode('@', $data);
        $output = '';

        foreach($parts as $part){
            $part = trim($part);
            if(substr($part, 0,3) == 'var'){
                $docpart = trim($part);
                $docpart = str_replace('var ', '', $docpart);
                return $docpart;
            }
        }

        return $output;
    }

    /**
     * Returns a custom delimeter @example which is a github url normally
     * @param $data
     * @return array|mixed|string
     */
    private function docGetExamples($data){
        $parts = explode('@', $data);
        $output = '';

        foreach($parts as $part){
            $part = trim($part);
            if(substr($part, 0,7) == 'example'){
                $docpart = trim($part);
                $docpart = str_replace('example ', '', $docpart);
                return $docpart;
            }
        }

        return $output;
    }

    /**
     * Returns a custom delimeter @example which is a github url normally
     * @param $data
     * @return array|mixed|string
     */
    private function docGetReturn($data){
        $parts = explode('@', $data);
        $output = '';

        foreach($parts as $part){
            $part = trim($part);
            if(substr($part, 0,6) == 'return'){
                $docpart = trim($part);
                $docpart = str_replace('return ', '', $docpart);
                return $docpart;
            }
        }

        return $output;
    }






}