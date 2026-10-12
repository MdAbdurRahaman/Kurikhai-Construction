<?php
// GitHub CI only: the database name must identify a disposable test database.
if(getenv('APP_ENV')!=='testing'||!str_starts_with((string)getenv('DB_NAME'),'tabeeb_test_'))exit(2);
$pdo=new PDO('mysql:host='.getenv('DB_HOST').';port='.(getenv('DB_PORT')?:3306),getenv('DB_USER'),getenv('DB_PASS'),[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
$name=getenv('DB_NAME');if(!preg_match('/^tabeeb_test_[a-z0-9_]+$/',$name))exit(2);
$pdo->exec('DROP DATABASE IF EXISTS `'.$name.'`');$pdo->exec('CREATE DATABASE `'.$name.'` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
