<?php

require_once('include/TaxonConceptType.php');
require_once('include/TaxonNameType.php');

/*

    Register of types because the schema must only have one instance 
    of each type in it.

*/

class TypeRegister {

    private static $taxonConceptType;
    private static $taxonNameType;

    public static function taxonConceptType(){
        return self::$taxonConceptType ?: (self::$taxonConceptType = new TaxonConceptType());
    }

    public static function taxonNameType(){
        return self::$taxonNameType ?: (self::$taxonNameType = new TaxonNameType());
    }

}