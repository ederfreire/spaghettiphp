<?php
/**
 *  Short description.
 *
 *  @license   http://www.opensource.org/licenses/mit-license.php The MIT License
 *  @copyright Copyright 2008-2009, Spaghetti* Framework (http://spaghettiphp.org/)
 */

/**
 *  O Spaghetti foi criado para suportar as versões 5.2 e posteriores do PHP. Caso
 *  a versão instalada seja mais antiga, o Spaghetti emitirá um erro.
 */
if(version_compare(PHP_VERSION, "5.2") < 0):
    trigger_error("Spaghetti only works with PHP 5.2 or newer", E_USER_ERROR);
endif;

$config_path = dirname(dirname(dirname(__FILE__))) . DIRECTORY_SEPARATOR . "config" . DIRECTORY_SEPARATOR;
require_once $config_path . "paths.php";
require_once "core/Loader.php";
require_once $config_path . "settings.php";
