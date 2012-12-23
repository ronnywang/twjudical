<?php

set_include_path(
    __DIR__ . '/libs/'
    . PATH_SEPARATOR . __DIR__ . '/models'
);

include(__DIR__ . '/libs/Pix/Loader.php');
Pix_Loader::registerAutoload();

Pix_Table::setDefaultDb(new Pix_Table_Db_Adapter_Sqlite(__DIR__ . '/db.sqlite'));
Pix_Table::addStaticResultSetHelper('Pix_Array_Volume');
