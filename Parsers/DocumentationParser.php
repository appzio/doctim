<?php

namespace Doctim\Parsers;


use function explode;
use function implode;
use function preg_match;
use function preg_replace;
use function str_replace;
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

        foreach($data as $item){
            $string .= $item['data'];
            if($item['type'] == 'T_STRING'){
                $output['name'] = $item['data'];
            }
        }

        $output['call'] = $string;
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
                $output['name'] = $item['data'];
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
    private function docGetDescription($data){
        $parts = explode('@', $data);
        if(isset($parts[0])){
            $string = trim($parts[0]);
            $string = preg_replace('/\h+/', ' ', $string);
            $string = preg_replace('/\v\h+/', chr(10), $string);
            $string = preg_replace('/\v\v/', '<linebreak>', $string);
            $string = preg_replace('/\v/', ' ', $string);
            $string = str_replace('<linebreak>', chr(10).chr(10), $string);
            return $string;
        }
    }

    /**
     * Parses through the phpdoc comment section and returns defined parts
     * @param $data
     * @return mixed
     */

    private function docGetComment($data){
        $data = str_replace('/*', '', $data);
        $data = str_replace('*/', '', $data);
        $data = str_replace('*', '', $data);
        $output['summary'] = $this->docGetDescription($data);
        $output['parameters'] = $this->docGetParameters($data);
        $output['object'] = $this->docGetObject($data);
        $output['examples'] = $this->docGetExamples($data);
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
            $part = trim($part);
            if(substr($part, 0,5) == 'param'){
                preg_match('/\$.*[[:space:]]/U', $part,$parameters);
                if(isset($parameters[0])){
                    $varname = trim($parameters[0]);
                    $docpart = preg_replace('/.*\v/U', '', $part,1);
                    $docpart = str_replace('param '.$varname, '', $docpart);
                    $output[$varname] = trim($docpart);
                }
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
        $output = array();

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
        $output = array();

        foreach($parts as $part){
            $part = trim($part);
            if(substr($part, 0,3) == 'example'){
                $docpart = trim($part);
                $docpart = str_replace('example ', '', $docpart);
                return $docpart;
            }
        }

        return $output;
    }





}