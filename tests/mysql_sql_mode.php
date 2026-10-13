<?php
// Native regression check; global defaults are varied only on a disposable CI server.
if (PHP_SAPI !== 'cli') exit(1);
require_once __DIR__ . '/../includes/db.php';
if (APP_ENV !== 'testing' || !str_starts_with(DB_NAME, 'tabeeb_test_') ||
    !in_array(DB_HOST, ['localhost', '127.0.0.1'], true) ||
    getenv('SQL_MODE_TEST_DISPOSABLE') !== '1') {
    throw new RuntimeException('SQL mode regression requires an explicitly disposable local testing server.');
}
$control = new PDO('mysql:host=' . DB_HOST . ';port=' . DB_PORT . ';dbname=' . DB_NAME . ';charset=utf8mb4',
    DB_USER, DB_PASS, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$original = (string)$control->query('SELECT @@GLOBAL.sql_mode')->fetchColumn();
$fixtures = ['', 'NO_ENGINE_SUBSTITUTION', 'STRICT_ALL_TABLES,NO_ENGINE_SUBSTITUTION',
    'STRICT_TRANS_TABLES,ANSI_QUOTES,NO_ENGINE_SUBSTITUTION'];
$passed = 0;
try {
    foreach ($fixtures as $fixture) {
        $control->exec('SET GLOBAL sql_mode = ' . $control->quote($fixture));
        $global = (string)$control->query('SELECT @@GLOBAL.sql_mode')->fetchColumn();
        DB::resetConnection();
        $application = DB::getConnection();
        if (!$application) throw new RuntimeException('Application connection failed.');
        $actual = explode(',', (string)$application->query('SELECT @@SESSION.sql_mode')->fetchColumn());
        if (!in_array('STRICT_TRANS_TABLES', $actual, true) && !in_array('STRICT_ALL_TABLES', $actual, true)) {
            throw new RuntimeException('Application connection did not enable strict SQL mode.');
        }
        $passed++;
        foreach (array_filter(explode(',', $global)) as $mode) {
            if (!in_array($mode, $actual, true)) throw new RuntimeException('An existing SQL mode was removed.');
        }
        if (str_contains($global, 'STRICT_ALL_TABLES') && in_array('STRICT_TRANS_TABLES', $actual, true)) {
            throw new RuntimeException('Existing STRICT_ALL_TABLES was changed unnecessarily.');
        }
        $passed++;
        if ($global !== (string)$control->query('SELECT @@GLOBAL.sql_mode')->fetchColumn()) {
            throw new RuntimeException('Application connection changed the global mode.');
        }
        $passed++;
        $application->exec('CREATE TEMPORARY TABLE tabeeb_sql_mode_probe (value INT NOT NULL) ENGINE=InnoDB');
        $rejected = false;
        try {
            $application->exec("INSERT INTO tabeeb_sql_mode_probe (value) VALUES ('invalid_integer')");
        } catch (PDOException $expected) {
            $rejected = true;
        }
        if (!$rejected) throw new RuntimeException('Invalid data was silently accepted.');
        $passed++;
        $application->exec('INSERT INTO tabeeb_sql_mode_probe (value) VALUES (42)');
        if ((int)$application->query('SELECT COUNT(*) FROM tabeeb_sql_mode_probe')->fetchColumn() !== 1) {
            throw new RuntimeException('Valid data failed or an invalid row was saved.');
        }
        $passed++;
    }
} finally {
    $control->exec('SET GLOBAL sql_mode = ' . $control->quote($original));
    DB::resetConnection();
}
echo json_encode(['passed' => $passed, 'suite' => 'native MariaDB session strictness, inherited modes, global isolation and invalid-write rejection']) . "\n";
