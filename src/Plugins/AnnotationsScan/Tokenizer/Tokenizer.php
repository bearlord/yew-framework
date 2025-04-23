<?php

namespace Yew\Plugins\AnnotationsScan\Tokenizer;

class Tokenizer
{

    public static function getClassFromFile(string $pathToFile)
    {
        switch (PHP_MAJOR_VERSION) {
            case 8:
                return self::getClassFromFilePHP8($pathToFile);

            case 7:
            default:
                return self::getClassFromFilePHP7($pathToFile);
        }
    }

    /**
     * @param string $pathToFile
     * @return string|null
     */
    public static function getClassFromFilePHP7(string $pathToFile): ?string
    {
        //Grab the contents of the file
        $contents = file_get_contents($pathToFile);

        //Start with a blank namespace and class
        $namespace = $class = "";

        //Set helper values to know that we have found the namespace/class token and need to collect the string values after them
        $getting_namespace = $getting_class = false;

        //Go through each token and evaluate it as necessary
        foreach (token_get_all($contents) as $token) {

            //If this token is the namespace declaring, then flag that the next tokens will be the namespace name
            if (is_array($token) && $token[0] == T_NAMESPACE) {
                $getting_namespace = true;
            }

            //If this token is the class declaring, then flag that the next tokens will be the class name
            if (is_array($token) && ($token[0] == T_CLASS || $token[0] == T_INTERFACE)) {
                $getting_class = true;
            }

            //While we're grabbing the namespace name...
            if ($getting_namespace === true) {
                //If the token is a string or the namespace separator...
                if (is_array($token) && in_array($token[0], [T_STRING, T_NS_SEPARATOR])) {
                    //Append the token's value to the name of the namespace
                    $namespace .= $token[1];
                } else if ($token === ';') {
                    //If the token is the semicolon, then we're done with the namespace declaration
                    $getting_namespace = false;
                }
            }

            //While we're grabbing the class name...
            if ($getting_class === true) {
                //If the token is a string, it's the name of the class
                if (is_array($token) && $token[0] == T_STRING) {
                    //Store the token's value as the class name
                    $class = $token[1];
                    //Got what we need, stop here
                    break;
                }
            }
        }
        if (empty($class)) {
            return null;
        }
        //Build the fully-qualified class name and return it
        return $namespace ? $namespace . '\\' . $class : $class;
    }

    /**
     * @param $pathToFile
     * @return mixed|string|null
     */
    public static function getClassFromFilePHP8($pathToFile)
    {
        //Grab the contents of the file
        $contents = file_get_contents($pathToFile);

        //Start with a blank namespace and class
        $namespace = $class = "";

        //Set helper values to know that we have found the namespace/class token and need to collect the string values after them
        $getting_namespace = $getting_class = false;

        //Go through each token and evaluate it as necessary
        foreach (token_get_all($contents) as $token) {

            //If this token is the namespace declaring, then flag that the next tokens will be the namespace name
            if (is_array($token) && $token[0] == T_NAMESPACE) {
                $getting_namespace = true;
            }

            //If this token is the class declaring, then flag that the next tokens will be the class name
            if (is_array($token) && ($token[0] == T_CLASS || $token[0] == T_INTERFACE)) {
                $getting_class = true;
            }

            //While we're grabbing the namespace name...
            if ($getting_namespace === true) {
                //If the token is a string or the namespace separator...

                if (is_array($token) && in_array($token[0], [T_STRING, T_NAME_QUALIFIED])) {
                    //Append the token's value to the name of the namespace
                    $namespace .= $token[1];
                } else if ($token === ';') {
                    //If the token is the semicolon, then we're done with the namespace declaration
                    $getting_namespace = false;
                }
            }

            //While we're grabbing the class name...
            if ($getting_class === true) {
                //If the token is a string, it's the name of the class
                if (is_array($token) && $token[0] == T_STRING) {
                    //Store the token's value as the class name
                    $class = $token[1];
                    //Got what we need, stop here
                    break;
                }
            }
        }
        if (empty($class)) {
            return null;
        }

        //Build the fully-qualified class name and return it
        return $namespace ? $namespace . '\\' . $class : $class;
    }
}