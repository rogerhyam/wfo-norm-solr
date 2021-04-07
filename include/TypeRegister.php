<?php

require_once('include/TaxonConceptType.php');
require_once('include/TaxonNameType.php');
require_once('include/ClassificationType.php');
require_once('include/TaxonConceptStatType.php');

/*

    Register of types because the schema must only have one instance 
    of each type in it.

*/

class TypeRegister {

    private static $taxonConceptType;
    private static $taxonConceptStatType;
    private static $taxonNameType;
    private static $classificationType;

    public static function taxonConceptType(){
        return self::$taxonConceptType ?: (self::$taxonConceptType = new TaxonConceptType());
    }

    public static function taxonNameType(){
        return self::$taxonNameType ?: (self::$taxonNameType = new TaxonNameType());
    }

    public static function classificationType(){
        return self::$classificationType ?: (self::$classificationType = new ClassificationType());
    }

    public static function taxonConceptStatType(){
        return self::$taxonConceptStatType ?: (self::$taxonConceptStatType = new TaxonConceptStatType());
    }

}